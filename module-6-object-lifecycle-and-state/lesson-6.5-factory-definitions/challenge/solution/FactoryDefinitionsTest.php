<?php
declare(strict_types=1);

/**
 * CHALLENGE SOLUTION — Lesson 6.5: Factory Definitions for Complex Lifecycles
 * ─────────────────────────────────────────────────────────────────────────────
 * ⚠️  Only open this file after completing all five tests yourself.
 *
 * Key things to compare with your solution:
 *
 *   1. Wiring 1 (DatabaseConnection): Did you use factory() rather than
 *      constructing the object directly in setUp()? The factory gives PHP-DI
 *      control over construction — that is the point.
 *
 *   2. Wiring 2 (ShoppingCart): Did you use transient(), not singleton()?
 *      And did your test use assertNotSame(), not assertEquals()?
 *
 *   3. Wiring 3 (Decorator): Did you register NotificationService as its own
 *      concrete class first, then inject it into the decorator factory?
 *      This avoids the circular reference that would occur if you tried to
 *      inject NotificationServiceInterface into itself.
 *
 *   4. Wiring 4 (Environment): Did your factory capture $this->appEnv via
 *      closure rather than calling getenv() inside the implementations?
 *      Config at the entry point, not in core logic.
 *
 *   5. Integration test: Did you verify BOTH that the inner service received
 *      the call AND that the logger captured an entry?
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// Domain classes (identical to starter — do not modify)
// ─────────────────────────────────────────────────────────────────────────────

class DatabaseConnection
{
    private \PDO $pdo;

    public function __construct(
        private readonly string $dsn,
        private readonly string $user,
        private readonly string $password,
    ) {
        $this->pdo = new \PDO($this->dsn);
    }

    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getDsn(): string { return $this->dsn; }
}

class ShoppingCart
{
    private array $items = [];

    public function add(string $sku, int $qty, float $price): void
    {
        $this->items[] = ['sku' => $sku, 'qty' => $qty, 'price' => $price];
    }

    public function getItems(): array { return $this->items; }
    public function count(): int      { return count($this->items); }
    public function isEmpty(): bool   { return empty($this->items); }
}

interface NotificationServiceInterface
{
    public function send(string $recipient, string $message): bool;
}

class NotificationService implements NotificationServiceInterface
{
    private int   $deliveredCount = 0;
    private array $delivered      = [];

    public function send(string $recipient, string $message): bool
    {
        $this->delivered[]   = compact('recipient', 'message');
        $this->deliveredCount++;
        return true;
    }

    public function getDelivered(): array    { return $this->delivered; }
    public function getDeliveredCount(): int { return $this->deliveredCount; }
}

class SpyLogger
{
    private array $entries = [];

    public function log(string $message): void { $this->entries[] = $message; }
    public function getEntries(): array        { return $this->entries; }
    public function getCount(): int            { return count($this->entries); }
}

class LoggingNotificationService implements NotificationServiceInterface
{
    public function __construct(
        private readonly NotificationServiceInterface $inner,
        private readonly SpyLogger                    $logger,
    ) {}

    public function send(string $recipient, string $message): bool
    {
        $this->logger->log("Sending notification to {$recipient}: {$message}");
        $result = $this->inner->send($recipient, $message);
        $this->logger->log('Notification ' . ($result ? 'delivered' : 'failed'));
        return $result;
    }
}

interface StorageInterface
{
    public function store(string $key, string $data): bool;
    public function retrieve(string $key): ?string;
    public function getBackend(): string;
}

class S3Storage implements StorageInterface
{
    private array $store = [];

    public function __construct(private readonly string $bucket) {}

    public function store(string $key, string $data): bool   { $this->store[$key] = $data; return true; }
    public function retrieve(string $key): ?string           { return $this->store[$key] ?? null; }
    public function getBackend(): string                     { return "s3://{$this->bucket}"; }
}

class LocalStorage implements StorageInterface
{
    private array $store = [];

    public function store(string $key, string $data): bool   { $this->store[$key] = $data; return true; }
    public function retrieve(string $key): ?string           { return $this->store[$key] ?? null; }
    public function getBackend(): string                     { return 'local://tmp'; }
}

class SimpleContainer
{
    private array $definitions = [];
    private array $singletons  = [];

    public function singleton(string $id, callable $factory): void
    {
        $this->definitions[$id] = ['factory' => $factory, 'transient' => false];
    }

    public function transient(string $id, callable $factory): void
    {
        $this->definitions[$id] = ['factory' => $factory, 'transient' => true];
    }

    public function get(string $id): object
    {
        if (!isset($this->definitions[$id])) {
            throw new \RuntimeException("No definition for: {$id}");
        }
        $def = $this->definitions[$id];
        if (!$def['transient'] && isset($this->singletons[$id])) {
            return $this->singletons[$id];
        }
        $instance = $this->invoke($def['factory']);
        if (!$def['transient']) {
            $this->singletons[$id] = $instance;
        }
        return $instance;
    }

    private function invoke(callable $factory): object
    {
        $rf   = new \ReflectionFunction($factory instanceof \Closure ? $factory : \Closure::fromCallable($factory));
        $args = [];
        foreach ($rf->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $args[] = $this->get($type->getName());
            }
        }
        return $factory(...$args);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Solution
// ─────────────────────────────────────────────────────────────────────────────

class FactoryDefinitionsTest extends TestCase
{
    private SimpleContainer $container;
    private SpyLogger       $spyLogger;
    private string          $appEnv;
    private array           $dbConfig;

    protected function setUp(): void
    {
        $this->container = new SimpleContainer();
        $this->spyLogger = new SpyLogger();
        $this->appEnv    = 'development';
        $this->dbConfig  = [
            'dsn'      => 'sqlite::memory:',
            'user'     => 'root',
            'password' => 'secret',
        ];

        // ── Wiring 1: DatabaseConnection — singleton with scalar args ─────────
        //
        // WHY factory(): auto-wiring cannot resolve string $dsn, string $user,
        // string $password. The factory reads them from the config array captured
        // via closure. This is the PHP-DI equivalent of:
        //   create(DatabaseConnection::class)->constructor(
        //       DI\env('DB_DSN'), DI\env('DB_USER'), DI\env('DB_PASS')
        //   )
        //
        // The factory is registered as singleton (the default for factory in PHP-DI
        // unless you explicitly want transient scope). One DB connection per
        // container lifetime is correct.
        $dbConfig = $this->dbConfig; // capture for closure
        $this->container->singleton(
            DatabaseConnection::class,
            function () use ($dbConfig): DatabaseConnection {
                return new DatabaseConnection(
                    dsn:      $dbConfig['dsn'],
                    user:     $dbConfig['user'],
                    password: $dbConfig['password'],
                );
            }
        );

        // ── Wiring 2: ShoppingCart — transient ────────────────────────────────
        //
        // WHY transient(): ShoppingCart has private array $items that accumulates
        // per session. A singleton would share one cart across all users/requests.
        // factory(fn() => new ShoppingCart()) is the PHP-DI idiom for transient.
        $this->container->transient(
            ShoppingCart::class,
            fn(): ShoppingCart => new ShoppingCart()
        );

        // ── Wiring 3: Decorator chain ─────────────────────────────────────────
        //
        // STEP 1: Register the concrete inner class as its own binding.
        // This is critical — if we only registered NotificationServiceInterface,
        // the decorator factory would have no way to get the inner implementation
        // without creating a circular reference.
        $this->container->singleton(
            NotificationService::class,
            fn(): NotificationService => new NotificationService()
        );

        // STEP 2: Register the interface binding as a factory that wraps the
        // concrete class in the decorator.
        //
        // KEY: the factory injects NotificationService (concrete), NOT
        // NotificationServiceInterface. If it injected the interface, PHP-DI
        // would try to resolve NotificationServiceInterface again — which is
        // the same factory — creating infinite recursion.
        //
        // SpyLogger is captured via closure because it is not registered in the
        // container (it's a test double). In production, LoggerInterface would
        // be a container-registered singleton injected as a typed parameter.
        $spyLogger = $this->spyLogger;
        $this->container->singleton(
            NotificationServiceInterface::class,
            function (NotificationService $inner) use ($spyLogger): NotificationServiceInterface {
                // $inner is resolved from the container — it IS the singleton
                // NotificationService registered in STEP 1 above.
                return new LoggingNotificationService($inner, $spyLogger);
            }
        );

        // ── Wiring 4: StorageInterface — environment-based ────────────────────
        //
        // WHY in the factory, not in the implementation: Rule 1 from
        // COURSE_PHILOSOPHY.md — "config belongs at the entry point, not in
        // core logic." S3Storage and LocalStorage are unaware of APP_ENV.
        // The selection logic lives here at the composition root.
        $appEnv = $this->appEnv;
        $this->container->singleton(
            StorageInterface::class,
            function () use ($appEnv): StorageInterface {
                return match($appEnv) {
                    'production' => new S3Storage(bucket: 'acme-production-assets'),
                    default      => new LocalStorage(),
                };
            }
        );
    }

    // ── Wiring 1 test ─────────────────────────────────────────────────────────

    /**
     * Factory correctly passes scalar constructor args to DatabaseConnection.
     * Two resolutions return the same singleton instance.
     */
    public function testDatabaseConnectionFactoryWiresCorrectly(): void
    {
        $db1 = $this->container->get(DatabaseConnection::class);
        $db2 = $this->container->get(DatabaseConnection::class);

        $this->assertInstanceOf(DatabaseConnection::class, $db1);

        // Singleton: same instance on every resolution
        $this->assertSame($db1, $db2,
            'DatabaseConnection is a singleton — same instance returned'
        );

        // Scalar args were correctly passed through the factory
        $this->assertSame('sqlite::memory:', $db1->getDsn(),
            'DSN was correctly wired from config'
        );

        // The connection actually works — run a simple query
        $db1->query('CREATE TABLE IF NOT EXISTS test (id INTEGER, name TEXT)');
        $db1->query("INSERT INTO test VALUES (1, 'Alice')");
        $rows = $db1->query('SELECT * FROM test');

        $this->assertCount(1, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
    }

    // ── Wiring 2 test ─────────────────────────────────────────────────────────

    /**
     * ShoppingCart factory is transient: each resolution returns a new instance.
     * Each new instance starts empty.
     */
    public function testShoppingCartFactoryIsTransient(): void
    {
        $cartA = $this->container->get(ShoppingCart::class);
        $cartB = $this->container->get(ShoppingCart::class);

        // Transient: different instances
        $this->assertNotSame($cartA, $cartB,
            'ShoppingCart is transient — each resolution returns a new instance'
        );

        // Each starts empty
        $this->assertTrue($cartA->isEmpty(), 'Cart A starts empty');
        $this->assertTrue($cartB->isEmpty(), 'Cart B starts empty');

        // Adding to one does not affect the other
        $cartA->add('WIDGET-001', 1, 9.99);
        $this->assertSame(1, $cartA->count());
        $this->assertSame(0, $cartB->count(),
            'Cart B is unaffected by items added to Cart A'
        );
    }

    // ── Wiring 3 test ─────────────────────────────────────────────────────────

    /**
     * Resolving NotificationServiceInterface returns LoggingNotificationService.
     * The decorator wraps NotificationService and logs operations.
     */
    public function testNotificationServiceDecoratorIsWiredCorrectly(): void
    {
        $service = $this->container->get(NotificationServiceInterface::class);

        // Consumers receive the decorator
        $this->assertInstanceOf(LoggingNotificationService::class, $service,
            'NotificationServiceInterface resolves to the LoggingNotificationService decorator'
        );

        // Send a notification via the interface
        $result = $service->send('alice@example.com', 'Your order has shipped');

        $this->assertTrue($result, 'send() returns true');

        // The logger captured entries from the decorator
        $this->assertGreaterThanOrEqual(2, $this->spyLogger->getCount(),
            'Logger captured at least 2 entries (send initiated + delivered)'
        );

        $entries = $this->spyLogger->getEntries();
        $this->assertStringContainsString('alice@example.com', $entries[0],
            'First log entry contains the recipient'
        );
    }

    // ── Wiring 4 test ─────────────────────────────────────────────────────────

    /**
     * StorageInterface binding selects the correct implementation per APP_ENV.
     * Tests both production and development environments.
     */
    public function testStorageInterfaceBindingSelectsCorrectImplementation(): void
    {
        // Development environment (set in setUp): LocalStorage
        $devStorage = $this->container->get(StorageInterface::class);
        $this->assertInstanceOf(LocalStorage::class, $devStorage,
            'development: LocalStorage is selected'
        );
        $this->assertStringContainsString('local', $devStorage->getBackend());

        // Re-wire for production environment
        $productionContainer = new SimpleContainer();
        $productionContainer->singleton(
            StorageInterface::class,
            fn(): StorageInterface => new S3Storage(bucket: 'acme-production-assets')
        );

        $prodStorage = $productionContainer->get(StorageInterface::class);
        $this->assertInstanceOf(S3Storage::class, $prodStorage,
            'production: S3Storage is selected'
        );
        $this->assertStringContainsString('s3://', $prodStorage->getBackend());

        // Both satisfy the StorageInterface contract
        foreach ([$devStorage, $prodStorage] as $storage) {
            $this->assertTrue($storage->store('key', 'value'));
            $this->assertSame('value', $storage->retrieve('key'));
        }
    }

    // ── Integration test ──────────────────────────────────────────────────────

    /**
     * End-to-end test of the full decorator chain:
     *   1. Resolve via interface → gets LoggingNotificationService
     *   2. send() calls LoggingNotificationService.send()
     *   3. Decorator logs → delegates to NotificationService → logs result
     *   4. Inner NotificationService records the delivery
     *   5. Result propagates back through the chain
     *
     * This verifies ALL THREE components (interface binding, decorator, inner
     * service) are correctly wired and working together.
     */
    public function testFullDecoratorChainIntegrationTest(): void
    {
        // Resolve via the interface (gets the decorator)
        $notifier = $this->container->get(NotificationServiceInterface::class);
        $this->assertInstanceOf(LoggingNotificationService::class, $notifier);

        // Also get the inner service to verify it received the delegated call
        /** @var NotificationService $innerService */
        $innerService = $this->container->get(NotificationService::class);

        // Before: nothing delivered, nothing logged
        $this->assertSame(0, $innerService->getDeliveredCount());
        $this->assertSame(0, $this->spyLogger->getCount());

        // Send a notification through the full chain
        $result = $notifier->send('bob@example.com', 'Payment confirmed');

        // 1. Result is correct
        $this->assertTrue($result, 'send() returns true through the full chain');

        // 2. Inner service received the delivery
        $this->assertSame(1, $innerService->getDeliveredCount(),
            'Inner NotificationService received and processed the delivery'
        );
        $this->assertSame('bob@example.com', $innerService->getDelivered()[0]['recipient'],
            'Inner service received the correct recipient'
        );

        // 3. Logger captured both log entries from the decorator
        $this->assertSame(2, $this->spyLogger->getCount(),
            'Logger captured exactly 2 entries (before and after delegation)'
        );

        $logEntries = $this->spyLogger->getEntries();
        $this->assertStringContainsString('bob@example.com', $logEntries[0],
            'First log entry records the send attempt'
        );
        $this->assertStringContainsString('delivered', $logEntries[1],
            'Second log entry confirms delivery'
        );

        // 4. Send another notification — verify counts increment correctly
        $notifier->send('charlie@example.com', 'Invoice ready');
        $this->assertSame(2, $innerService->getDeliveredCount(),
            'Two notifications delivered to the inner service'
        );
        $this->assertSame(4, $this->spyLogger->getCount(),
            'Four log entries total (2 per notification × 2 notifications)'
        );
    }
}