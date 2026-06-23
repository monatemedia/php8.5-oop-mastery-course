<?php
declare(strict_types=1);

/**
 * Example 01 — Booting a PHP-DI Container in setUp()
 * ----------------------------------------------------
 * Run via PHPUnit:
 *   ./vendor/bin/phpunit module-5-testing-and-tdd/lesson-5.4-integration-testing/examples/01-container-in-tests.php
 *
 * Prerequisites:
 *   composer require php-di/php-di slim/slim slim/psr7
 *
 * This example covers:
 *   A. Building a PHP-DI container inside setUp()
 *   B. Overriding a single binding with a test double
 *   C. Resolving the subject under test from the container
 *   D. Verifying the container wires interfaces to concrete classes
 *   E. Using the same container for multiple tests without state leak
 *
 * The class hierarchy under test:
 *   ProductService → ProductRepositoryInterface
 *                    ↑
 *                    SqliteProductRepository (uses injected PDO)
 *
 * What this IS:
 *   An integration test — real container, real repository class, real SQL
 *
 * What this IS NOT:
 *   A unit test — we are not substituting the repository with a fake
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// Domain — contracts and classes (would normally live in src/)
// ─────────────────────────────────────────────────────────────────────────────

interface ProductRepositoryInterface
{
    public function findAll(): array;
    public function findById(int $id): ?array;
    public function save(string $name, int $priceCents, string $sku): array;
}

interface LoggerInterface
{
    public function log(string $level, string $message): void;
}

/**
 * Real repository — uses a real PDO connection.
 * In unit tests, this is replaced with a fake.
 * In integration tests, this is the real thing backed by SQLite.
 */
class SqliteProductRepository implements ProductRepositoryInterface
{
    public function __construct(private \PDO $pdo) {}

    public function findAll(): array
    {
        return $this->pdo->query('SELECT * FROM products ORDER BY id')->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result === false ? null : $result;
    }

    public function save(string $name, int $priceCents, string $sku): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO products (name, price, sku) VALUES (?, ?, ?)'
        );
        $stmt->execute([$name, $priceCents, $sku]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->findById($id);
    }
}

class NullLogger implements LoggerInterface
{
    public function log(string $level, string $message): void {}
}

/**
 * Service — orchestrates the repository and logger.
 * This is what we test INDIRECTLY through the container.
 */
class ProductService
{
    public function __construct(
        private ProductRepositoryInterface $repository,
        private LoggerInterface            $logger
    ) {}

    public function getAll(): array
    {
        $this->logger->log('info', 'Fetching all products');
        return $this->repository->findAll();
    }

    public function getById(int $id): ?array
    {
        $product = $this->repository->findById($id);
        if ($product === null) {
            $this->logger->log('info', "Product {$id} not found");
        }
        return $product;
    }

    public function create(string $name, int $priceCents, string $sku): array
    {
        if (empty(trim($name))) {
            throw new \InvalidArgumentException('Product name cannot be empty');
        }
        if ($priceCents <= 0) {
            throw new \InvalidArgumentException('Price must be positive');
        }

        $product = $this->repository->save($name, $priceCents, $sku);
        $this->logger->log('info', "Product created: {$product['id']}");
        return $product;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// The test class
// ─────────────────────────────────────────────────────────────────────────────

class ContainerInTestsExampleTest extends TestCase
{
    private \PDO            $pdo;
    private \DI\Container   $container;
    private ProductService  $service;

    protected function setUp(): void
    {
        // ── Step 1: Create a fresh in-memory SQLite database ─────────────────
        // A new connection is created before every test → clean slate
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE,            \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        $this->pdo->exec('
            CREATE TABLE products (
                id    INTEGER PRIMARY KEY AUTOINCREMENT,
                name  TEXT    NOT NULL,
                price INTEGER NOT NULL,
                sku   TEXT    NOT NULL UNIQUE
            )
        ');

        // ── Step 2: Build the PHP-DI container with test overrides ───────────
        // The container uses real implementations everywhere — except the PDO
        // binding, which points to our in-memory test database.
        $testPdo = $this->pdo; // capture for closure

        $this->container = (new \DI\ContainerBuilder())
            ->addDefinitions([
                // Override PDO with the test connection
                \PDO::class => $testPdo,

                // Wire interfaces to concrete classes (same as production config)
                ProductRepositoryInterface::class =>
                    \DI\autowire(SqliteProductRepository::class),

                LoggerInterface::class =>
                    \DI\autowire(NullLogger::class),
            ])
            ->build();

        // ── Step 3: Resolve the subject under test ───────────────────────────
        // PHP-DI autowires ProductService → SqliteProductRepository → PDO
        // We are testing the container wiring, not just the class in isolation
        $this->service = $this->container->get(ProductService::class);
    }

    // ═══════════════════════════════════════════════════════════
    // PART A — Verifying container wiring
    // ═══════════════════════════════════════════════════════════

    /**
     * Verify that the container resolves ProductRepositoryInterface to
     * SqliteProductRepository. This is a container wiring test — it catches
     * misconfigured bindings that unit tests cannot catch.
     */
    public function testContainerResolvesRepositoryInterfaceToConcreteClass(): void
    {
        $repo = $this->container->get(ProductRepositoryInterface::class);

        $this->assertInstanceOf(SqliteProductRepository::class, $repo);
    }

    public function testContainerResolvesLoggerInterfaceToNullLogger(): void
    {
        $logger = $this->container->get(LoggerInterface::class);

        $this->assertInstanceOf(NullLogger::class, $logger);
    }

    public function testContainerResolvesProductServiceWithAllDependencies(): void
    {
        $service = $this->container->get(ProductService::class);

        $this->assertInstanceOf(ProductService::class, $service);
    }

    // ═══════════════════════════════════════════════════════════
    // PART B — Real integration through the full stack
    // ═══════════════════════════════════════════════════════════

    /**
     * Integration test: service → real repository → real SQLite database.
     * This verifies the whole chain works, not just the service in isolation.
     */
    public function testGetAllReturnsEmptyArrayWhenNoProductsExist(): void
    {
        $products = $this->service->getAll();

        $this->assertSame([], $products);
    }

    public function testCreateAndRetrieveProduct(): void
    {
        // Create via the service (real SQL INSERT under the hood)
        $created = $this->service->create('Widget Pro', 29999, 'WDG-001');

        $this->assertIsInt($created['id']);
        $this->assertSame('Widget Pro', $created['name']);
        $this->assertSame(29999, (int) $created['price']);

        // Retrieve via the service (real SQL SELECT)
        $found = $this->service->getById($created['id']);

        $this->assertNotNull($found);
        $this->assertSame($created['id'], (int) $found['id']);
    }

    public function testGetAllReturnsAllCreatedProducts(): void
    {
        $this->service->create('Widget Pro',  29999, 'WDG-001');
        $this->service->create('Widget Lite', 14999, 'WDG-002');
        $this->service->create('Widget Max',  49999, 'WDG-003');

        $products = $this->service->getAll();

        $this->assertCount(3, $products);
        $this->assertSame('Widget Pro',  $products[0]['name']);
        $this->assertSame('Widget Lite', $products[1]['name']);
        $this->assertSame('Widget Max',  $products[2]['name']);
    }

    public function testGetByIdReturnsNullForNonExistentProduct(): void
    {
        $result = $this->service->getById(999);

        $this->assertNull($result);
    }

    // ═══════════════════════════════════════════════════════════
    // PART C — Isolation: each test gets a fresh database
    // ═══════════════════════════════════════════════════════════

    /**
     * This test proves that setUp() creates a truly fresh database.
     * If state leaked between tests, this would fail because testGetAllReturnsAllCreatedProducts()
     * inserted 3 products. But since setUp() runs before each test and
     * creates a brand-new in-memory connection, this test sees zero products.
     */
    public function testDatabaseIsEmptyAtStartOfEachTest(): void
    {
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();

        $this->assertSame(0, $count);
    }

    // ═══════════════════════════════════════════════════════════
    // PART D — Business logic validation at the integration level
    // ═══════════════════════════════════════════════════════════

    /**
     * These tests verify that validation in the service layer works correctly
     * when called through the real container. The exception propagates through
     * the real stack — not through a fake.
     */
    public function testCreateThrowsForEmptyProductName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Product name cannot be empty');

        $this->service->create('', 29999, 'WDG-001');
    }

    public function testCreateThrowsForZeroPrice(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Price must be positive');

        $this->service->create('Widget', 0, 'WDG-001');
    }

    // ═══════════════════════════════════════════════════════════
    // PART E — Direct PDO assertions (verify DB state after write)
    // ═══════════════════════════════════════════════════════════

    /**
     * Sometimes you want to assert on the DATABASE STATE directly, not just
     * on the service's return value. This is useful to verify that a write
     * persisted correctly at the SQL level.
     */
    public function testCreatePersistsProductToDatabase(): void
    {
        $this->service->create('Widget Pro', 29999, 'WDG-001');

        // Assert directly on the database — bypass the service layer
        $row = $this->pdo->query('SELECT * FROM products WHERE sku = \'WDG-001\'')->fetch();

        $this->assertNotFalse($row);
        $this->assertSame('Widget Pro', $row['name']);
        $this->assertSame(29999, (int) $row['price']);
        $this->assertSame('WDG-001', $row['sku']);
    }
}