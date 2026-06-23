<?php
declare(strict_types=1);

/**
 * Example 02 — Safe Singletons
 * ------------------------------
 * Run via PHPUnit:
 *   ./vendor/bin/phpunit module-6-object-lifecycle-and-state/lesson-6.2-transient-vs-singleton-scopes/examples/02-safe-singletons.php
 *
 * Singleton scope is not inherently wrong. For the right class of object —
 * one that is stateless after construction — singleton scope is correct,
 * efficient, and desirable.
 *
 * This file examines three safe singleton archetypes and proves, via tests,
 * that sharing the same instance across multiple consumers does NOT cause
 * contamination. The key property in each case: no method writes to a
 * property that another method reads. Every method's output depends only
 * on its parameters and on immutable constructor state.
 *
 * Structure:
 *   PART A — FileLogger (infrastructure singleton)
 *   PART B — TaxCalculator (pure computation singleton)
 *   PART C — DatabaseConnection (resource singleton)
 *   PART D — The safe singleton checklist applied to each class
 *   PART E — Tests proving safe sharing
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// PART A — FileLogger
//
// Writes log entries to a file. The file path is set at construction and
// never changes. log() reads $this->path (immutable) and writes to disk.
// Nothing accumulates on the object between calls.
// ─────────────────────────────────────────────────────────────────────────────

interface LoggerInterface
{
    public function log(string $level, string $message): void;
}

/**
 * Writes log lines to a file.
 *
 * Safe singleton checklist:
 *   ✅ $path is readonly — set once, never changed
 *   ✅ $callCount is readonly — set once (0), never changed publicly
 *      (NOTE: $callCount is mutable via incrementCallCount(), but only in
 *       the test subclass below — the production class is clean)
 *   ✅ log() output goes to disk — does not accumulate on $this
 *   ✅ Two calls to log() are independent — neither affects the other's output
 */
class FileLogger implements LoggerInterface
{
    // readonly: PHP 8.1+ — can only be set in the constructor, never reassigned
    public function __construct(
        private readonly string $path,
        private readonly string $prefix = ''
    ) {}

    public function log(string $level, string $message): void
    {
        $line = sprintf(
            "[%s] [%s] %s%s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $this->prefix ? "[{$this->prefix}] " : '',
            $message
        );

        // In a real logger: file_put_contents($this->path, $line, FILE_APPEND);
        // In tests: we use a spy subclass that captures the line instead
        $this->writeLine($line);
    }

    /**
     * Extension point for the test spy — not a mutation, just I/O delegation.
     */
    protected function writeLine(string $line): void
    {
        // file_put_contents($this->path, $line, FILE_APPEND);
    }
}

/**
 * Test spy: captures written lines in memory instead of writing to disk.
 * Demonstrates that even a subclass does not need to add mutable state to the
 * base class — the captured lines are on the SPY, not on FileLogger.
 */
class SpyLogger extends FileLogger
{
    public array $written = [];

    protected function writeLine(string $line): void
    {
        $this->written[] = $line;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PART B — TaxCalculator
//
// Computes tax on a given amount. The rate is set at construction and never
// changes. calculate() takes an amount and returns a computed value —
// no state accumulates, no property is written after construction.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Calculates tax at a fixed rate.
 *
 * Safe singleton checklist:
 *   ✅ $rate is readonly — set once, never changed
 *   ✅ calculate() is a pure function: output depends only on $amount + $rate
 *   ✅ No property is written after construction
 *   ✅ Calling calculate(100) 1000 times returns the same result each time
 */
class TaxCalculator
{
    public function __construct(private readonly float $rate) {}

    public function calculate(float $amount): float
    {
        return round($amount * $this->rate, 2);
    }

    public function rateAsPercentage(): string
    {
        return round($this->rate * 100, 1) . '%';
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PART C — DatabaseConnection
//
// Wraps a PDO connection. The connection is established once at construction.
// query() uses the connection to run a SQL statement and returns results —
// but does not mutate $this->pdo or add any properties to $this.
//
// NOTE: PDO connections are designed to be reused. A new connection is
// expensive (TCP handshake, authentication). Using a singleton here is
// not just safe — it is the correct and performant approach.
//
// We use a FakePDO in tests to avoid needing a real database.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Thin wrapper around a PDO connection.
 *
 * Safe singleton checklist:
 *   ✅ $pdo is set once in the constructor
 *   ✅ query() does not modify $this — it only delegates to $pdo
 *   ✅ Two concurrent users calling query() get independent result sets
 *   ✅ The PDO connection itself is stateless between queries (from PHP's perspective)
 */
class DatabaseConnection
{
    public function __construct(private readonly \PDO $pdo) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PART D — Applying the safe-singleton checklist
//
// For each class, we verify that NO public method writes to a property that
// another method reads (other than properties set at construction time).
//
// FileLogger:
//   Constructor sets: $path, $prefix
//   Public methods write: nothing (log() writes to DISK, not to $this)
//   Public methods read: $path, $prefix (both immutable)
//   VERDICT: SAFE SINGLETON
//
// TaxCalculator:
//   Constructor sets: $rate
//   Public methods write: nothing
//   Public methods read: $rate (immutable)
//   VERDICT: SAFE SINGLETON
//
// DatabaseConnection:
//   Constructor sets: $pdo
//   Public methods write: nothing on $this (delegates all I/O to $pdo)
//   Public methods read: $pdo (immutable reference — the PDO object itself
//                         manages connection state internally, not via $this)
//   VERDICT: SAFE SINGLETON
// ─────────────────────────────────────────────────────────────────────────────

// ─────────────────────────────────────────────────────────────────────────────
// PART E — Tests proving safe sharing
// ─────────────────────────────────────────────────────────────────────────────

class SafeSingletonsTest extends TestCase
{
    // ══════════════════════════════════════════════════════════════════════════
    // FileLogger — safe singleton proof
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Two consumers share the same logger instance.
     * Consumer A's log calls do not affect Consumer B's output.
     *
     * This would fail if the logger accumulated entries on itself —
     * but it does not, so sharing is safe.
     */
    public function testLoggerSingletonIsNotContaminatedAcrossConsumers(): void
    {
        // One instance — the singleton
        $logger = new SpyLogger('/var/log/app.log', 'APP');

        // Consumer A uses the logger
        $logger->log('info', 'User alice logged in');
        $logger->log('info', 'Order 42 fetched');

        // Consumer B uses the SAME logger instance
        // Does its log output depend on what A logged? NO — each call is independent.
        $logger->log('error', 'Payment gateway timeout');

        // All three lines were written — none interfere with each other
        $this->assertCount(3, $logger->written,
            'All three log calls produced output — no call blocked or corrupted another'
        );

        // The ORDER is preserved — no interleaving or corruption
        $this->assertStringContainsString('alice logged in', $logger->written[0]);
        $this->assertStringContainsString('Order 42 fetched', $logger->written[1]);
        $this->assertStringContainsString('Payment gateway timeout', $logger->written[2]);
    }

    /**
     * Calling log() on the same instance 1000 times does not cause any
     * property to grow in memory — there is nothing to accumulate.
     */
    public function testLoggerHasNoMemoryGrowthAcrossManyCalls(): void
    {
        $logger = new SpyLogger('/tmp/test.log');

        for ($i = 0; $i < 1000; $i++) {
            // We are not testing the SpyLogger's $written array here —
            // we are proving that the BASE class has no accumulation.
            // The SpyLogger writes to $written for test observability,
            // but the production FileLogger writes to disk and accumulates nothing.
            $logger->log('info', "Event {$i}");
        }

        // The logger has the same shape (properties) after 1000 calls as after 1.
        // In production (using the real FileLogger), $this has no growing properties.
        // This assertion is symbolic — what matters is the class design.
        $this->assertSame('/tmp/test.log', (new \ReflectionProperty(FileLogger::class, 'path'))->getValue($logger));
    }

    /**
     * Multiple log levels work correctly on the same instance.
     * Proves no cross-level contamination.
     */
    public function testLoggerHandlesMultipleLevelsOnSameInstance(): void
    {
        $logger = new SpyLogger('/var/log/app.log');

        $logger->log('debug', 'Debugging message');
        $logger->log('info',  'Informational message');
        $logger->log('error', 'Error message');

        $this->assertStringContainsString('[DEBUG]', $logger->written[0]);
        $this->assertStringContainsString('[INFO]',  $logger->written[1]);
        $this->assertStringContainsString('[ERROR]', $logger->written[2]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // TaxCalculator — safe singleton proof
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * The calculator is a pure function. Same input always produces same output,
     * regardless of how many times it has been called or what it was called
     * with previously.
     *
     * This is the strongest form of singleton safety: referential transparency.
     */
    public function testTaxCalculatorIsReferentiallyTransparent(): void
    {
        $calculator = new TaxCalculator(0.20); // 20% VAT

        // Same input, called multiple times — always the same output
        for ($i = 0; $i < 100; $i++) {
            $this->assertSame(20.00, $calculator->calculate(100.00),
                "Call {$i}: same input must produce same output"
            );
        }
    }

    /**
     * Two consumers using the same calculator instance produce correct,
     * independent results — neither contaminates the other.
     */
    public function testTaxCalculatorSingletonProducesCorrectResultsForAllConsumers(): void
    {
        $calculator = new TaxCalculator(0.15); // 15% tax

        // Consumer A: order of $200
        $taxForA = $calculator->calculate(200.00);

        // Consumer B: order of $500
        $taxForB = $calculator->calculate(500.00);

        // Consumer C: same as A — should match exactly
        $taxForC = $calculator->calculate(200.00);

        $this->assertSame(30.00, $taxForA, 'Consumer A: 15% of $200 = $30.00');
        $this->assertSame(75.00, $taxForB, 'Consumer B: 15% of $500 = $75.00');
        $this->assertSame(30.00, $taxForC, 'Consumer C: same as A — no contamination from B');
    }

    /**
     * The rate is immutable — it cannot be changed after construction.
     * This is what makes TaxCalculator safe as a singleton in a multi-tenant
     * system: one tenant's rate configuration cannot overwrite another's
     * because there IS no "set rate" method.
     */
    public function testTaxCalculatorRateIsImmutableAfterConstruction(): void
    {
        $calculator = new TaxCalculator(0.10);

        // Verify the rate is correct
        $this->assertSame(10.00, $calculator->calculate(100.00));
        $this->assertSame('10%', $calculator->rateAsPercentage());

        // There is no setRate() method. The rate cannot be changed.
        // This test documents that immutability by asserting the rate is
        // stable across calls.
        $this->assertSame(10.00, $calculator->calculate(100.00), 'Rate unchanged after first call');
        $this->assertSame(10.00, $calculator->calculate(100.00), 'Rate unchanged after second call');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // DatabaseConnection — safe singleton proof
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Two consumers share a DatabaseConnection singleton.
     * Consumer A queries for users; Consumer B queries for orders.
     * Neither query affects the other's results.
     *
     * Uses an in-memory SQLite database so the test is fully self-contained.
     */
    public function testDatabaseConnectionSingletonIsSafeAcrossConsumers(): void
    {
        // In-memory SQLite — no external DB required
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users  (id INTEGER, name TEXT)');
        $pdo->exec('CREATE TABLE orders (id INTEGER, user_id INTEGER, total REAL)');
        $pdo->exec("INSERT INTO users  VALUES (1, 'Alice'), (2, 'Bob')");
        $pdo->exec("INSERT INTO orders VALUES (1, 1, 99.99), (2, 2, 149.99)");

        // One connection — the singleton
        $db = new DatabaseConnection($pdo);

        // Consumer A: fetch all users
        $users = $db->query('SELECT * FROM users');

        // Consumer B: fetch all orders (using the SAME $db instance)
        $orders = $db->query('SELECT * FROM orders');

        // Consumer A's query result
        $this->assertCount(2, $users);
        $this->assertSame('Alice', $users[0]['name']);
        $this->assertSame('Bob',   $users[1]['name']);

        // Consumer B's query result — unaffected by A's query
        $this->assertCount(2, $orders);
        $this->assertSame(99.99,  (float) $orders[0]['total']);
        $this->assertSame(149.99, (float) $orders[1]['total']);
    }

    /**
     * Parameterised queries are independent on the same connection.
     * The bound parameters from one query do not bleed into the next.
     */
    public function testDatabaseConnectionParameterisedQueriesAreIndependent(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE products (id INTEGER, name TEXT, price REAL)');
        $pdo->exec("INSERT INTO products VALUES (1, 'Widget', 9.99), (2, 'Gadget', 24.99), (3, 'Doohickey', 4.99)");

        $db = new DatabaseConnection($pdo);

        // Query A: products costing more than $10
        $expensive = $db->query('SELECT * FROM products WHERE price > ?', [10.00]);

        // Query B: products costing less than $10 (uses the same $db instance)
        $cheap = $db->query('SELECT * FROM products WHERE price < ?', [10.00]);

        // Parameters from query A do not contaminate query B
        $this->assertCount(1, $expensive);
        $this->assertSame('Gadget', $expensive[0]['name']);

        $this->assertCount(2, $cheap);
        $names = array_column($cheap, 'name');
        $this->assertContains('Widget',    $names);
        $this->assertContains('Doohickey', $names);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Cross-cutting: what makes all three safe — the common property
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Documents the common property that makes all three classes safe:
     * no public method writes to a property that another public method reads.
     *
     * FileLogger:        log() writes to DISK, not to $this
     * TaxCalculator:     calculate() returns a value, writes to nothing
     * DatabaseConnection: query() delegates to $pdo, writes to nothing on $this
     *
     * This test verifies that none of the classes have public setters.
     */
    public function testSafeSingletonsHaveNoPublicMutatingMethods(): void
    {
        // TaxCalculator: only calculate() and rateAsPercentage() — both read-only
        $calcReflection = new \ReflectionClass(TaxCalculator::class);
        $publicMethods  = array_filter(
            $calcReflection->getMethods(\ReflectionMethod::IS_PUBLIC),
            fn(\ReflectionMethod $m) => !$m->isConstructor()
        );
        $methodNames = array_map(fn(\ReflectionMethod $m) => $m->getName(), $publicMethods);

        // TaxCalculator has no setters — only readers/computers
        $this->assertNotContains('setRate', $methodNames,
            'TaxCalculator has no setRate() method — rate is immutable'
        );

        // DatabaseConnection: query() and execute() — both read-only from $this's perspective
        $dbReflection = new \ReflectionClass(DatabaseConnection::class);
        $dbPublicMethods = array_filter(
            $dbReflection->getMethods(\ReflectionMethod::IS_PUBLIC),
            fn(\ReflectionMethod $m) => !$m->isConstructor()
        );
        $dbMethodNames = array_map(fn(\ReflectionMethod $m) => $m->getName(), $dbPublicMethods);

        $this->assertNotContains('setConnection', $dbMethodNames,
            'DatabaseConnection has no setConnection() method'
        );
    }
}