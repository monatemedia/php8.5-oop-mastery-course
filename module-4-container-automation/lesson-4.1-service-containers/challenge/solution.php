<?php
declare(strict_types=1);

/**
 * CHALLENGE SOLUTION — Lesson 4.1: Service Containers
 * ──────────────────────────────────────────────────────
 * ⚠️  Only open this file after completing starter.php yourself.
 *
 * Key things to compare in your solution:
 *   1. SimpleContainer implements all five methods correctly
 *   2. bind() returns fresh instance every get()
 *   3. singleton() caches after first build
 *   4. instance() always returns the pre-built object
 *   5. get() throws RuntimeException for unregistered ids
 *   6. Checkout system wired via container — no new in wiring section
 *   7. Singleton assertions both pass
 *   8. BadCheckoutController shows and explains the anti-pattern
 */


// ─────────────────────────────────────────────────────────────────────────────
// Interfaces and concrete classes — unchanged from starter
// ─────────────────────────────────────────────────────────────────────────────

interface DatabaseInterface {
    public function query(string $sql, array $params = []): array;
    public function execute(string $sql, array $params = []): bool;
}
interface CacheInterface {
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, int $ttl = 120): void;
}
interface LoggerInterface {
    public function log(string $level, string $message): void;
}
interface MailerInterface {
    public function send(string $to, string $subject, string $body): bool;
}
interface ProductRepositoryInterface {
    public function findById(int $id): ?array;
    public function findBySku(string $sku): ?array;
}
interface InventoryInterface {
    public function isAvailable(string $sku, int $quantity): bool;
    public function reserve(string $sku, int $quantity): bool;
}

class InMemoryDatabase implements DatabaseInterface {
    private array $products = [
        1 => ['id' => 1, 'sku' => 'WDG-001', 'name' => 'Widget Pro',  'price' => 29999, 'stock' => 50],
        2 => ['id' => 2, 'sku' => 'WDG-002', 'name' => 'Widget Lite', 'price' => 14999, 'stock' => 3],
    ];
    private array $inventory = ['WDG-001' => 50, 'WDG-002' => 3];
    private string $instanceId;

    public function __construct() {
        $this->instanceId = substr(md5(uniqid()), 0, 6);
    }

    public function getInstanceId(): string { return $this->instanceId; }

    public function query(string $sql, array $params = []): array {
        if (str_contains($sql, 'products') && !empty($params)) {
            if (is_int($params[0])) {
                return isset($this->products[$params[0]]) ? [$this->products[$params[0]]] : [];
            }
            foreach ($this->products as $p) {
                if ($p['sku'] === $params[0]) return [$p];
            }
            return [];
        }
        if (str_contains($sql, 'inventory') && !empty($params)) {
            return [['sku' => $params[0], 'quantity' => $this->inventory[$params[0]] ?? 0]];
        }
        return [];
    }
    public function execute(string $sql, array $params = []): bool {
        if (str_contains($sql, 'inventory') && count($params) >= 2) {
            $this->inventory[$params[1]] = max(0, ($this->inventory[$params[1]] ?? 0) - $params[0]);
        }
        return true;
    }
}

class ArrayCache implements CacheInterface {
    private array $store = [];
    public function get(string $key): mixed {
        $val = $this->store[$key] ?? null;
        echo "  [CACHE] " . ($val ? 'HIT' : 'MISS') . ": {$key}\n";
        return $val;
    }
    public function set(string $key, mixed $value, int $ttl = 120): void {
        $this->store[$key] = $value;
        echo "  [CACHE] SET: {$key}\n";
    }
}
class ConsoleLogger implements LoggerInterface {
    public function log(string $level, string $message): void {
        echo "  [{$level}] {$message}\n";
    }
}
class ConsoleMailer implements MailerInterface {
    public function send(string $to, string $subject, string $body): bool {
        echo "  [MAIL] To: {$to} | {$subject}\n";
        return true;
    }
}

class ProductCatalog implements ProductRepositoryInterface {
    public function __construct(
        private DatabaseInterface $db,
        private CacheInterface    $cache,
        private LoggerInterface   $logger
    ) {}
    public function getDb(): DatabaseInterface { return $this->db; } // for assertion

    public function findById(int $id): ?array {
        $key    = "product_{$id}";
        $cached = $this->cache->get($key);
        if ($cached !== null) return $cached;
        $this->logger->log('INFO', "DB fetch: product #{$id}");
        $rows = $this->db->query('SELECT * FROM products WHERE id = $1', [$id]);
        $product = $rows[0] ?? null;
        if ($product) $this->cache->set($key, $product);
        return $product;
    }
    public function findBySku(string $sku): ?array {
        $rows = $this->db->query('SELECT * FROM products WHERE sku = $1', [$sku]);
        return $rows[0] ?? null;
    }
}

class InventoryChecker implements InventoryInterface {
    public function __construct(private DatabaseInterface $db) {}
    public function getDb(): DatabaseInterface { return $this->db; } // for assertion

    public function isAvailable(string $sku, int $quantity): bool {
        echo "  [INVENTORY] Checking {$sku} × {$quantity}\n";
        $rows  = $this->db->query('SELECT quantity FROM inventory WHERE sku = $1', [$sku]);
        return ($rows[0]['quantity'] ?? 0) >= $quantity;
    }
    public function reserve(string $sku, int $quantity): bool {
        echo "  [INVENTORY] Reserving {$sku} × {$quantity}\n";
        return $this->db->execute(
            'UPDATE inventory SET quantity = quantity - $1 WHERE sku = $2',
            [$quantity, $sku]
        );
    }
}

class CheckoutService {
    public function __construct(
        private ProductRepositoryInterface $catalog,
        private InventoryInterface         $inventory,
        private MailerInterface            $mailer,
        private LoggerInterface            $logger
    ) {}

    public function checkout(array $cart, string $customerEmail): array {
        $this->logger->log('INFO', "Starting checkout for {$customerEmail}");
        $total = 0; $items = [];
        foreach ($cart as $item) {
            $product = $this->catalog->findById($item['product_id']);
            if (!$product) return ['success' => false, 'error' => 'Product not found'];
            if (!$this->inventory->isAvailable($product['sku'], $item['quantity'])) {
                return ['success' => false, 'error' => 'Insufficient stock'];
            }
            $this->inventory->reserve($product['sku'], $item['quantity']);
            $lineTotal = $product['price'] * $item['quantity'];
            $total    += $lineTotal;
            $items[]   = ['name' => $product['name'], 'qty' => $item['quantity'], 'subtotal' => $lineTotal];
        }
        $orderId = rand(10000, 99999);
        $this->mailer->send($customerEmail, "Order Confirmed #{$orderId}", "Total: R" . number_format($total / 100, 2));
        $this->logger->log('INFO', "Checkout complete. Order #{$orderId}");
        return ['success' => true, 'order_id' => $orderId, 'total' => $total, 'items' => $items];
    }
}

class CheckoutController {
    public function __construct(
        private CheckoutService $service,
        private LoggerInterface $logger
    ) {}

    public function handleCheckout(array $request): string {
        $this->logger->log('INFO', "Checkout request received");
        $result = $this->service->checkout($request['cart'] ?? [], $request['email'] ?? 'guest@example.com');
        return json_encode($result, JSON_PRETTY_PRINT);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Flat wiring — kept for comparison output
// ─────────────────────────────────────────────────────────────────────────────

function buildApp(): CheckoutController {
    $db        = new InMemoryDatabase();
    $cache     = new ArrayCache();
    $logger    = new ConsoleLogger();
    $mailer    = new ConsoleMailer();
    $catalog   = new ProductCatalog($db, $cache, $logger);
    $inventory = new InventoryChecker($db);
    $service   = new CheckoutService($catalog, $inventory, $mailer, $logger);
    return new CheckoutController($service, $logger);
}

echo "=== Flat wiring (buildApp) ===\n\n";
$flatController = buildApp();
echo $flatController->handleCheckout([
    'cart'  => [['product_id' => 1, 'quantity' => 2]],
    'email' => 'alice@example.com',
]) . "\n";


// ─────────────────────────────────────────────────────────────────────────────
// Task 1 — SimpleContainer: all five methods implemented
// ─────────────────────────────────────────────────────────────────────────────

class SimpleContainer {
    private array $bindings   = [];
    private array $singletons = [];
    private array $instances  = [];

    /** Factory — fresh instance on every get() */
    public function bind(string $id, callable $factory): void {
        $this->bindings[$id]   = $factory;
        $this->singletons[$id] = false;
    }

    /** Singleton — built once, cached for all subsequent get() */
    public function singleton(string $id, callable $factory): void {
        $this->bindings[$id]   = $factory;
        $this->singletons[$id] = true;
    }

    /** Pre-built instance — always return this exact object */
    public function instance(string $id, object $object): void {
        $this->instances[$id]  = $object;
        $this->singletons[$id] = true;
    }

    /** Resolve binding — throws RuntimeException if not found */
    public function get(string $id): mixed {
        // Return cached singleton / pre-built instance
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!isset($this->bindings[$id])) {
            throw new \RuntimeException(
                "No binding found for '{$id}'. Register it with bind(), singleton(), or instance()."
            );
        }

        // Call the factory — pass $this so factories can call get() recursively
        $result = ($this->bindings[$id])($this);

        // Cache if singleton
        if ($this->singletons[$id] ?? false) {
            $this->instances[$id] = $result;
        }

        return $result;
    }

    /** True if a binding or instance exists */
    public function has(string $id): bool {
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Tasks 2 & 3 — Wire and use the checkout system via SimpleContainer
// ─────────────────────────────────────────────────────────────────────────────

echo "\n=== Container wiring ===\n\n";

$container = new SimpleContainer();

// Layer 1: infrastructure (singleton — shared across the graph)
$container->singleton(DatabaseInterface::class,  fn($c) => new InMemoryDatabase());
$container->singleton(CacheInterface::class,     fn($c) => new ArrayCache());
$container->singleton(LoggerInterface::class,    fn($c) => new ConsoleLogger());
$container->singleton(MailerInterface::class,    fn($c) => new ConsoleMailer());

// Layer 2: repositories (singleton — depend on infrastructure)
$container->singleton(ProductRepositoryInterface::class, fn($c) => new ProductCatalog(
    $c->get(DatabaseInterface::class),
    $c->get(CacheInterface::class),
    $c->get(LoggerInterface::class)
));
$container->singleton(InventoryInterface::class, fn($c) => new InventoryChecker(
    $c->get(DatabaseInterface::class)
));

// Layer 3: service (singleton — depends on repositories + infrastructure)
$container->singleton(CheckoutService::class, fn($c) => new CheckoutService(
    $c->get(ProductRepositoryInterface::class),
    $c->get(InventoryInterface::class),
    $c->get(MailerInterface::class),
    $c->get(LoggerInterface::class)
));

// Layer 4: controller (singleton — depends on service)
$container->singleton(CheckoutController::class, fn($c) => new CheckoutController(
    $c->get(CheckoutService::class),
    $c->get(LoggerInterface::class)
));

$controller = $container->get(CheckoutController::class);
echo $controller->handleCheckout([
    'cart'  => [['product_id' => 1, 'quantity' => 2]],
    'email' => 'alice@example.com',
]) . "\n";


// ─────────────────────────────────────────────────────────────────────────────
// Task 4 — Singleton assertions
// ─────────────────────────────────────────────────────────────────────────────

echo "\n=== Singleton assertions ===\n\n";

$controller2 = $container->get(CheckoutController::class);
echo "Same controller instance? " . ($controller === $controller2 ? 'YES ✓' : 'NO ✗') . "\n";

// Verify shared DB: resolve catalog and inventory, compare their DB instances
$catalog   = $container->get(ProductRepositoryInterface::class);
$inventory = $container->get(InventoryInterface::class);

// Both should use the same singleton DatabaseInterface
$catalogDb   = $catalog->getDb();
$inventoryDb = $inventory->getDb();
echo "Same DB in ProductCatalog and InventoryChecker? " .
    ($catalogDb === $inventoryDb ? 'YES ✓' : 'NO ✗') . "\n";
echo "DB instance ID: " . $catalogDb->getInstanceId() . "\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Task 5 — Service Locator anti-pattern (BadCheckoutController)
// ─────────────────────────────────────────────────────────────────────────────

echo "=== Service Locator anti-pattern (BadCheckoutController) ===\n\n";

/**
 * BAD EXAMPLE — Do NOT write controllers like this.
 *
 * BadCheckoutController stores the container and calls get() inside its
 * methods. This is the Service Locator anti-pattern. Problems:
 *
 *   1. Hidden dependencies: The constructor shows only `SimpleContainer`.
 *      The real dependencies (CheckoutService, LoggerInterface) are invisible
 *      until you read every line of every method.
 *
 *   2. Coupled to the container: BadCheckoutController now depends on
 *      SimpleContainer itself. Change the container class name or API →
 *      must update every class that uses it as a locator.
 *
 *   3. Hard to test: To unit-test this class you must pre-populate a real
 *      SimpleContainer with the correct bindings. With CheckoutController (correct),
 *      you just pass fakes to the constructor.
 *
 *   4. Global state: If SimpleContainer were a static facade, concurrent tests
 *      could interfere with each other.
 */
class BadCheckoutController {
    // ❌ Container stored as a dependency
    public function __construct(private SimpleContainer $container) {}

    public function handleCheckout(array $request): string {
        // ❌ Reaching into the container at runtime — hidden coupling
        $logger  = $this->container->get(LoggerInterface::class);
        $service = $this->container->get(CheckoutService::class);

        $logger->log('INFO', "Request received (bad pattern — Service Locator)");
        $result = $service->checkout($request['cart'] ?? [], $request['email'] ?? 'guest@example.com');
        return json_encode($result, JSON_PRETTY_PRINT);
    }
}

$badController = new BadCheckoutController($container);
echo $badController->handleCheckout([
    'cart'  => [['product_id' => 2, 'quantity' => 1]],
    'email' => 'bob@example.com',
]) . "\n\n";

echo "Why BadCheckoutController is wrong:\n";
echo "  ✗ Constructor signature reveals nothing about real dependencies\n";
echo "  ✗ To test: must pre-populate a full container — not just pass fakes\n";
echo "  ✗ Coupled to SimpleContainer class name and API\n";
echo "  ✗ If the container were static, concurrent tests would share state\n\n";
echo "The correct version (CheckoutController) has:\n";
echo "  __construct(CheckoutService \$service, LoggerInterface \$logger)\n";
echo "  — both dependencies visible, injectable with fakes in one line\n";


// ─────────────────────────────────────────────────────────────────────────────
// SELF-REVIEW CHECKLIST
// ─────────────────────────────────────────────────────────────────────────────
echo "\n--- Self-review checklist ---\n";
echo "[ ] SimpleContainer::bind() returns fresh instance every get()?\n";
echo "[ ] SimpleContainer::singleton() returns same instance every get()?\n";
echo "[ ] SimpleContainer::instance() always returns the pre-built object?\n";
echo "[ ] SimpleContainer::get() throws RuntimeException for unknown ids?\n";
echo "[ ] SimpleContainer::has() returns correct boolean?\n";
echo "[ ] Container wiring uses no manual `new` outside container factories?\n";
echo "[ ] Container output matches flat wiring output?\n";
echo "[ ] Same controller assertion: controller === controller2?\n";
echo "[ ] Same DB assertion: catalogDb === inventoryDb?\n";
echo "[ ] BadCheckoutController example written and commented?\n";