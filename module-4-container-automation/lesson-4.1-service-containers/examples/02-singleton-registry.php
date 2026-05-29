<?php
declare(strict_types=1);

/**
 * Example 02 — Singleton Registry
 * ----------------------------------
 * Example 01 built a pure factory container — every get() call created a new
 * instance. That is fine for some objects (shopping carts, per-request state)
 * but wasteful and wrong for infrastructure like database connections and loggers.
 *
 * This example adds singleton support: the first get() call creates the instance
 * and caches it. Every subsequent get() returns the same cached object.
 *
 * Three registration modes:
 *   bind()     — factory: fresh instance on every get()
 *   singleton() — factory: built once, cached, returned for every subsequent get()
 *   instance() — pre-built: store an object you already constructed
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Singleton Registry                                 ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Interfaces and implementations
// ─────────────────────────────────────────────────────────────────────────────

interface DatabaseInterface {
    public function query(string $sql, array $params = []): array;
    public function execute(string $sql, array $params = []): bool;
    public function getConnectionId(): string;
}

interface LoggerInterface {
    public function log(string $level, string $message): void;
    public function getInstanceId(): string;
}

interface CacheInterface {
    public function get(string $key): mixed;
    public function set(string $key, mixed $value): void;
}

class InMemoryDatabase implements DatabaseInterface {
    private string $connectionId;
    private array $store = [
        1 => ['id' => 1, 'name' => 'Widget Pro',  'price' => 29999],
        2 => ['id' => 2, 'name' => 'Widget Lite', 'price' => 14999],
    ];

    public function __construct() {
        $this->connectionId = substr(md5(uniqid()), 0, 8);
        echo "  [DB] New connection created: #{$this->connectionId}\n";
    }

    public function query(string $sql, array $params = []): array {
        if (!empty($params) && is_int($params[0])) {
            return isset($this->store[$params[0]]) ? [$this->store[$params[0]]] : [];
        }
        return array_values($this->store);
    }

    public function execute(string $sql, array $params = []): bool { return true; }
    public function getConnectionId(): string { return $this->connectionId; }
}

class ConsoleLogger implements LoggerInterface {
    private string $instanceId;

    public function __construct() {
        $this->instanceId = substr(md5(uniqid()), 0, 8);
        echo "  [LOGGER] New logger created: #{$this->instanceId}\n";
    }

    public function log(string $level, string $message): void {
        echo "  [{$level}] {$message}\n";
    }

    public function getInstanceId(): string { return $this->instanceId; }
}

class ArrayCache implements CacheInterface {
    private array $store = [];
    private string $instanceId;

    public function __construct() {
        $this->instanceId = substr(md5(uniqid()), 0, 8);
        echo "  [CACHE] New cache created: #{$this->instanceId}\n";
    }

    public function get(string $key): mixed  { return $this->store[$key] ?? null; }
    public function set(string $key, mixed $value): void { $this->store[$key] = $value; }
    public function getInstanceId(): string  { return $this->instanceId; }
}

class UserRepository {
    public function __construct(
        private DatabaseInterface $db,
        private LoggerInterface   $logger
    ) {}

    public function findById(int $id): array {
        $this->logger->log('INFO', "findById({$id}) on db #{$this->db->getConnectionId()}");
        return ['id' => $id, 'name' => "User#{$id}"];
    }
}

class ProductRepository {
    public function __construct(
        private DatabaseInterface $db,
        private LoggerInterface   $logger,
        private CacheInterface    $cache
    ) {}

    public function findById(int $id): ?array {
        $key    = "product:{$id}";
        $cached = $this->cache->get($key);
        if ($cached) {
            $this->logger->log('INFO', "Cache hit on db #{$this->db->getConnectionId()}");
            return $cached;
        }
        $rows = $this->db->query('SELECT * FROM products WHERE id = ?', [$id]);
        if ($rows[0] ?? null) $this->cache->set($key, $rows[0]);
        return $rows[0] ?? null;
    }
}


// ═══════════════════════════════════════════════════════════
// THE CONTAINER WITH SINGLETON SUPPORT
// ═══════════════════════════════════════════════════════════

class Container {
    private array $bindings  = [];   // id → callable (factory or singleton factory)
    private array $singletons = [];  // id → bool (is this a singleton binding?)
    private array $instances = [];   // id → built object (the singleton cache)

    /** Factory mode — fresh instance on every get() */
    public function bind(string $id, callable $factory): void {
        $this->bindings[$id]   = $factory;
        $this->singletons[$id] = false;
    }

    /** Singleton mode — built once, cached, returned for all subsequent get() calls */
    public function singleton(string $id, callable $factory): void {
        $this->bindings[$id]   = $factory;
        $this->singletons[$id] = true;
    }

    /** Pre-built instance — store an object you already constructed */
    public function instance(string $id, object $object): void {
        $this->instances[$id]  = $object;
        $this->singletons[$id] = true; // Treat as singleton — always return this instance
    }

    public function get(string $id): mixed {
        // Return cached singleton if available
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!isset($this->bindings[$id])) {
            throw new \RuntimeException("No binding found for '{$id}'");
        }

        $instance = ($this->bindings[$id])($this);

        // Cache if registered as singleton
        if ($this->singletons[$id] ?? false) {
            $this->instances[$id] = $instance;
        }

        return $instance;
    }

    public function has(string $id): bool {
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// PART 1 — Demonstrate the difference
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 1: bind() vs singleton() vs instance() ──────\n\n";

$container = new Container();

echo "Registering DatabaseInterface as SINGLETON:\n";
$container->singleton(DatabaseInterface::class, fn($c) => new InMemoryDatabase());

echo "\nRegistering LoggerInterface as SINGLETON:\n";
$container->singleton(LoggerInterface::class, fn($c) => new ConsoleLogger());

echo "\nRegistering CacheInterface as FACTORY (fresh each time):\n";
$container->bind(CacheInterface::class, fn($c) => new ArrayCache());

echo "\n── Resolving DatabaseInterface twice ────────────────\n\n";
$db1 = $container->get(DatabaseInterface::class);
$db2 = $container->get(DatabaseInterface::class);
echo "db1 connection ID: " . $db1->getConnectionId() . "\n";
echo "db2 connection ID: " . $db2->getConnectionId() . "\n";
echo "Same object? " . ($db1 === $db2 ? 'YES — singleton ✓' : 'NO — factory') . "\n\n";

echo "── Resolving CacheInterface twice ───────────────────\n\n";
$cache1 = $container->get(CacheInterface::class);
$cache2 = $container->get(CacheInterface::class);
echo "cache1 ID: " . $cache1->getInstanceId() . "\n";
echo "cache2 ID: " . $cache2->getInstanceId() . "\n";
echo "Same object? " . ($cache1 === $cache2 ? 'YES — singleton' : 'NO — factory ✓') . "\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 2 — Why singletons matter for shared state
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 2: Why shared singletons matter ─────────────\n\n";

$container2 = new Container();
$container2->singleton(DatabaseInterface::class, fn($c) => new InMemoryDatabase());
$container2->singleton(LoggerInterface::class,   fn($c) => new ConsoleLogger());
$container2->singleton(CacheInterface::class,    fn($c) => new ArrayCache());

// Register repos — they share the SAME logger and DB
$container2->singleton(UserRepository::class, fn($c) => new UserRepository(
    $c->get(DatabaseInterface::class),  // shared singleton
    $c->get(LoggerInterface::class)     // shared singleton
));

$container2->singleton(ProductRepository::class, fn($c) => new ProductRepository(
    $c->get(DatabaseInterface::class),  // same DB instance as UserRepository
    $c->get(LoggerInterface::class),    // same logger instance
    $c->get(CacheInterface::class)
));

echo "Resolving UserRepository and ProductRepository:\n\n";
$userRepo    = $container2->get(UserRepository::class);
$productRepo = $container2->get(ProductRepository::class);

$user    = $userRepo->findById(1);
$product = $productRepo->findById(1);

// Verify they share the same DB connection
$db3 = $container2->get(DatabaseInterface::class);
echo "\nVerification — all three use the same DB connection:\n";
echo "  DB singleton ID: " . $db3->getConnectionId() . "\n";
echo "  UserRepository received the same connection: db #{$db3->getConnectionId()}\n";
echo "  ProductRepository received the same connection: db #{$db3->getConnectionId()}\n\n";

echo "ONE database connection created — shared across the entire graph.\n";
echo "With factory mode, each would get its own connection — wasteful.\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 3 — instance() for pre-built objects
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 3: instance() for pre-built objects ─────────\n\n";

// Sometimes you need full control over construction — build it first, then register
$config = ['db' => 'pgsql:host=localhost;dbname=shop', 'env' => 'staging'];

$preBuiltDb = new InMemoryDatabase(); // constructed with custom config
echo "\nRegistering pre-built DB instance:\n";

$container3 = new Container();
$container3->instance(DatabaseInterface::class, $preBuiltDb);
$container3->singleton(LoggerInterface::class, fn($c) => new ConsoleLogger());

$resolvedDb = $container3->get(DatabaseInterface::class);
echo "Same as pre-built? " . ($resolvedDb === $preBuiltDb ? 'YES ✓' : 'NO') . "\n";
echo "Connection ID: " . $resolvedDb->getConnectionId() . "\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 4 — When to use each mode
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 4: When to use each mode ────────────────────\n\n";

echo "bind() — FACTORY:\n";
echo "  Use when:  object must be fresh per resolution\n";
echo "  Examples:  ShoppingCart, RequestContext, DTO builders\n";
echo "  Warning:   stateful services as factories leak state between resolutions\n\n";

echo "singleton() — SINGLETON:\n";
echo "  Use when:  object is stateless or manages its own internal state safely\n";
echo "  Examples:  DatabaseInterface, LoggerInterface, MailerInterface, Repositories\n";
echo "  Warning:   mutable singletons can bleed state between requests (Module 6)\n\n";

echo "instance() — PRE-BUILT:\n";
echo "  Use when:  you need constructor arguments not available as bindings\n";
echo "  Examples:  new MySQLDatabase(getenv('DB_DSN')) — env var in constructor\n";
echo "             new StripeGateway(getenv('STRIPE_KEY'))\n\n";

echo "--- Recap ---\n";
echo "bind():      fresh instance every get() — factory mode.\n";
echo "singleton(): built once, cached — singleton mode.\n";
echo "instance():  pre-built object stored directly.\n";
echo "Infrastructure (DB, logger, mailer): almost always singleton.\n";
echo "Per-request objects (cart, context): factory.\n";