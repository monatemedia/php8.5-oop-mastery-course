<?php
declare(strict_types=1);

/**
 * CHALLENGE STARTER — Lesson 6.5: Factory Definitions for Complex Lifecycles
 * ─────────────────────────────────────────────────────────────────────────────
 * Read CHALLENGE.md before touching this file.
 *
 * The domain classes are defined below. DO NOT modify them.
 *
 * Your tasks:
 *   1. Register each service in setUp() using $this->container
 *   2. Uncomment and complete each test method
 *
 * Container API:
 *   $this->container->singleton(id, callable)   — singleton scope
 *   $this->container->transient(id, callable)   — transient scope (new instance per get)
 *   $this->container->get(id)                   — resolve a binding
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// Domain classes — DO NOT modify
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Wiring 1: DatabaseConnection — scalar constructor args
 */
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

/**
 * Wiring 2: ShoppingCart — transient, stateful
 */
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

/**
 * Wiring 3: Notification service + decorator
 */
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

    public function getDelivered(): array { return $this->delivered; }
    public function getDeliveredCount(): int { return $this->deliveredCount; }
}

class SpyLogger
{
    private array $entries = [];

    public function log(string $message): void
    {
        $this->entries[] = $message;
    }

    public function getEntries(): array { return $this->entries; }
    public function getCount(): int     { return count($this->entries); }
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

/**
 * Wiring 4: Storage — environment-based selection
 */
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

    public function store(string $key, string $data): bool
    {
        $this->store[$key] = $data;
        return true;
    }

    public function retrieve(string $key): ?string { return $this->store[$key] ?? null; }
    public function getBackend(): string           { return "s3://{$this->bucket}"; }
}

class LocalStorage implements StorageInterface
{
    private array $store = [];

    public function store(string $key, string $data): bool
    {
        $this->store[$key] = $data;
        return true;
    }

    public function retrieve(string $key): ?string { return $this->store[$key] ?? null; }
    public function getBackend(): string           { return 'local://tmp'; }
}

// ─────────────────────────────────────────────────────────────────────────────
// SimpleContainer — DO NOT modify
// ─────────────────────────────────────────────────────────────────────────────

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
// Your tests
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
        $this->appEnv    = 'development';  // change in individual tests to test other envs
        $this->dbConfig  = [
            'dsn'      => 'sqlite::memory:',
            'user'     => 'root',
            'password' => 'secret',
        ];

        // TODO: Register all four factory definitions here
        // Use $this->container->singleton() or $this->container->transient()

        // Wiring 1: DatabaseConnection (singleton with scalar args from $this->dbConfig)
        // TODO: $this->container->singleton(DatabaseConnection::class, function() { ... });

        // Wiring 2: ShoppingCart (transient)
        // TODO: $this->container->transient(ShoppingCart::class, function() { ... });

        // Wiring 3: Decorator chain
        // TODO: Register NotificationService as its own concrete class
        // TODO: Register NotificationServiceInterface as the decorator factory
        // Remember: inject NotificationService (concrete), not NotificationServiceInterface (would be circular)

        // Wiring 4: StorageInterface (environment-based)
        // TODO: $this->container->singleton(StorageInterface::class, function() { ... });
    }

    // TODO: public function testDatabaseConnectionFactoryWiresCorrectly(): void {}

    // TODO: public function testShoppingCartFactoryIsTransient(): void {}

    // TODO: public function testNotificationServiceDecoratorIsWiredCorrectly(): void {}

    // TODO: public function testStorageInterfaceBindingSelectsCorrectImplementation(): void {}

    // TODO: public function testFullDecoratorChainIntegrationTest(): void {}
}