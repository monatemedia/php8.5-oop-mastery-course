<?php
declare(strict_types=1);

/**
 * Example 02 — SQLite Integration Testing in Depth
 * --------------------------------------------------
 * Run via PHPUnit:
 *   ./vendor/bin/phpunit module-5-testing-and-tdd/lesson-5.4-integration-testing/examples/02-sqlite-integration-test.php
 *
 * This example goes deeper on the SQLite integration test pattern:
 *   A. Multi-table schema with a foreign key relationship
 *   B. Seed helpers — insert known rows cleanly
 *   C. Direct SQL assertions — verify DB state, not just return values
 *   D. Transaction rollback strategy — alternative to fresh connection
 *   E. Testing a repository directly (not through a service)
 *   F. Testing aggregate queries (COUNT, SUM)
 *
 * The domain: a simple Order system.
 *   orders      (id, customer_email, status, created_at)
 *   order_items (id, order_id FK, product_name, price_cents, qty)
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// The class under test: a real SQLite repository
// ─────────────────────────────────────────────────────────────────────────────

class OrderRepository
{
    public function __construct(private \PDO $pdo) {}

    public function create(string $customerEmail): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO orders (customer_email, status, created_at)
             VALUES (?, ?, ?)'
        );
        $stmt->execute([$customerEmail, 'pending', date('Y-m-d H:i:s')]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->findById($id);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function findByEmail(string $email): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM orders WHERE customer_email = ? ORDER BY id'
        );
        $stmt->execute([$email]);
        return $stmt->fetchAll();
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
    }

    public function addItem(int $orderId, string $productName, int $priceCents, int $qty): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO order_items (order_id, product_name, price_cents, qty)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$orderId, $productName, $priceCents, $qty]);

        $id   = (int) $this->pdo->lastInsertId();
        $stmt = $this->pdo->prepare('SELECT * FROM order_items WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getItemsForOrder(int $orderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM order_items WHERE order_id = ? ORDER BY id'
        );
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    }

    public function getTotalCents(int $orderId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(price_cents * qty), 0) FROM order_items WHERE order_id = ?'
        );
        $stmt->execute([$orderId]);
        return (int) $stmt->fetchColumn();
    }

    public function countByStatus(string $status): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM orders WHERE status = ?');
        $stmt->execute([$status]);
        return (int) $stmt->fetchColumn();
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// The test class
// ─────────────────────────────────────────────────────────────────────────────

class SqliteIntegrationTestExampleTest extends TestCase
{
    private \PDO             $pdo;
    private OrderRepository  $repo;

    protected function setUp(): void
    {
        // Fresh in-memory database before every test
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE,            \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        // ── Schema ────────────────────────────────────────────────────────────
        $this->pdo->exec('
            CREATE TABLE orders (
                id             INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_email TEXT    NOT NULL,
                status         TEXT    NOT NULL DEFAULT \'pending\',
                created_at     TEXT    NOT NULL
            )
        ');

        $this->pdo->exec('
            CREATE TABLE order_items (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id     INTEGER NOT NULL REFERENCES orders(id),
                product_name TEXT    NOT NULL,
                price_cents  INTEGER NOT NULL,
                qty          INTEGER NOT NULL DEFAULT 1
            )
        ');

        $this->repo = new OrderRepository($this->pdo);
    }

    // ═══════════════════════════════════════════════════════════
    // PART B — Seed helpers
    // ═══════════════════════════════════════════════════════════

    /**
     * Seed helpers insert known data without going through the repository
     * under test. Using raw PDO for seeds avoids coupling the seed to the
     * method being tested.
     */
    private function seedOrder(string $email, string $status = 'pending'): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO orders (customer_email, status, created_at) VALUES (?, ?, ?)'
        );
        $stmt->execute([$email, $status, '2026-01-01 10:00:00']);
        return (int) $this->pdo->lastInsertId();
    }

    private function seedOrderItem(int $orderId, string $product, int $priceCents, int $qty = 1): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO order_items (order_id, product_name, price_cents, qty) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$orderId, $product, $priceCents, $qty]);
        return (int) $this->pdo->lastInsertId();
    }

    // ═══════════════════════════════════════════════════════════
    // PART A — Basic CRUD integration tests
    // ═══════════════════════════════════════════════════════════

    public function testCreateOrderPersistsToDatabase(): void
    {
        $order = $this->repo->create('alice@example.com');

        $this->assertIsInt((int) $order['id']);
        $this->assertSame('alice@example.com', $order['customer_email']);
        $this->assertSame('pending', $order['status']);
    }

    public function testFindByIdReturnsCreatedOrder(): void
    {
        $created = $this->repo->create('alice@example.com');

        $found = $this->repo->findById((int) $created['id']);

        $this->assertNotNull($found);
        $this->assertSame($created['id'], $found['id']);
        $this->assertSame('alice@example.com', $found['customer_email']);
    }

    public function testFindByIdReturnsNullForUnknownId(): void
    {
        $this->assertNull($this->repo->findById(9999));
    }

    // ═══════════════════════════════════════════════════════════
    // PART C — Direct SQL assertions
    // ═══════════════════════════════════════════════════════════

    /**
     * After a write operation, assert on the database directly.
     * This catches bugs where the service returns the right value but
     * fails to actually persist it (e.g. forgets to call execute()).
     */
    public function testUpdateStatusPersistsNewStatusToDatabase(): void
    {
        $orderId = $this->seedOrder('alice@example.com', 'pending');

        $this->repo->updateStatus($orderId, 'confirmed');

        // Assert directly on the database row
        $row = $this->pdo->prepare('SELECT status FROM orders WHERE id = ?');
        $row->execute([$orderId]);
        $status = $row->fetchColumn();

        $this->assertSame('confirmed', $status);
    }

    public function testAddItemInsertsRowIntoOrderItemsTable(): void
    {
        $orderId = $this->seedOrder('alice@example.com');

        $this->repo->addItem($orderId, 'Widget Pro', 29999, 2);

        // Direct SQL count
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM order_items WHERE order_id = ?');
        $stmt->execute([$orderId]);
        $count = (int) $stmt->fetchColumn();

        $this->assertSame(1, $count);
    }

    // ═══════════════════════════════════════════════════════════
    // PART D — Transaction rollback strategy (alternative to fresh connection)
    // ═══════════════════════════════════════════════════════════

    /**
     * Some test suites use a shared connection and roll back after each test.
     * This is faster than creating a new PDO and re-running migrations.
     *
     * Demonstrated here in a single test — normally setUp()/tearDown() manage this.
     */
    public function testTransactionRollbackRestoresDatabaseState(): void
    {
        // Pre-condition: database is empty
        $countBefore = (int) $this->pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
        $this->assertSame(0, $countBefore);

        // Begin a transaction
        $this->pdo->beginTransaction();

        // Make a change
        $this->repo->create('transient@example.com');
        $countDuring = (int) $this->pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
        $this->assertSame(1, $countDuring);

        // Roll back
        $this->pdo->rollBack();

        // Post-condition: database is empty again
        $countAfter = (int) $this->pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
        $this->assertSame(0, $countAfter);
    }

    // ═══════════════════════════════════════════════════════════
    // PART E — Testing the repository directly (no service layer)
    // ═══════════════════════════════════════════════════════════

    /**
     * Integration tests for repositories test the SQL queries directly.
     * There is no service involved — the repository IS the unit being integrated.
     */
    public function testFindByEmailReturnsAllOrdersForThatCustomer(): void
    {
        $this->seedOrder('alice@example.com');
        $this->seedOrder('alice@example.com'); // two orders for alice
        $this->seedOrder('bob@example.com');   // one for bob

        $aliceOrders = $this->repo->findByEmail('alice@example.com');
        $bobOrders   = $this->repo->findByEmail('bob@example.com');

        $this->assertCount(2, $aliceOrders);
        $this->assertCount(1, $bobOrders);
    }

    public function testFindByEmailReturnsEmptyArrayForUnknownCustomer(): void
    {
        $this->assertSame([], $this->repo->findByEmail('ghost@example.com'));
    }

    public function testGetItemsForOrderReturnsAllItems(): void
    {
        $orderId = $this->seedOrder('alice@example.com');
        $this->seedOrderItem($orderId, 'Widget Pro',  29999, 2);
        $this->seedOrderItem($orderId, 'Widget Lite', 14999, 1);

        $items = $this->repo->getItemsForOrder($orderId);

        $this->assertCount(2, $items);
        $this->assertSame('Widget Pro',  $items[0]['product_name']);
        $this->assertSame('Widget Lite', $items[1]['product_name']);
    }

    public function testGetItemsForOrderReturnsEmptyArrayForEmptyOrder(): void
    {
        $orderId = $this->seedOrder('alice@example.com');

        $this->assertSame([], $this->repo->getItemsForOrder($orderId));
    }

    // ═══════════════════════════════════════════════════════════
    // PART F — Aggregate queries
    // ═══════════════════════════════════════════════════════════

    /**
     * Aggregate queries (SUM, COUNT) are hard to test correctly without a
     * real database. Fakes can implement them incorrectly and the unit test
     * would pass while production SQL is wrong. Integration tests catch this.
     */
    public function testGetTotalCentsReturnsSumOfAllItemTotals(): void
    {
        $orderId = $this->seedOrder('alice@example.com');
        $this->seedOrderItem($orderId, 'Widget Pro',  29999, 2); // 59998
        $this->seedOrderItem($orderId, 'Widget Lite', 14999, 1); // 14999
        // Expected total: 74997

        $total = $this->repo->getTotalCents($orderId);

        $this->assertSame(74997, $total);
    }

    public function testGetTotalCentsReturnsZeroForEmptyOrder(): void
    {
        $orderId = $this->seedOrder('alice@example.com');

        $this->assertSame(0, $this->repo->getTotalCents($orderId));
    }

    public function testCountByStatusReturnsCorrectCount(): void
    {
        $this->seedOrder('a@example.com', 'pending');
        $this->seedOrder('b@example.com', 'pending');
        $this->seedOrder('c@example.com', 'confirmed');

        $this->assertSame(2, $this->repo->countByStatus('pending'));
        $this->assertSame(1, $this->repo->countByStatus('confirmed'));
        $this->assertSame(0, $this->repo->countByStatus('cancelled'));
    }

    /**
     * Multi-order total: different orders do NOT bleed into each other's sum.
     * This test catches a classic SQL bug: forgetting the WHERE clause on a SUM.
     */
    public function testGetTotalCentsDoesNotIncludeItemsFromOtherOrders(): void
    {
        $order1 = $this->seedOrder('alice@example.com');
        $order2 = $this->seedOrder('bob@example.com');

        $this->seedOrderItem($order1, 'Widget Pro', 100, 1);  // order 1: 100
        $this->seedOrderItem($order2, 'Widget Max', 999, 5);  // order 2: 4995

        $this->assertSame(100,  $this->repo->getTotalCents($order1));
        $this->assertSame(4995, $this->repo->getTotalCents($order2));
    }
}