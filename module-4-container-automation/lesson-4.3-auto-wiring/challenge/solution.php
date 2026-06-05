<?php
declare(strict_types=1);

/**
 * CHALLENGE SOLUTION — Lesson 4.3: Auto-wiring
 * ────────────────────────────────────────────────
 * ⚠️  Only open this file after completing starter.php yourself.
 *
 * Key things to compare in your solution:
 *   1. autowire() uses ReflectionClass to resolve constructor params recursively
 *   2. $resolving[] tracks classes currently being built (circular detection)
 *   3. finally{} always unmarks the class — even on exception
 *   4. Auto-wired results are cached as singletons
 *   5. Explicit string binding: calls autowire() on the concrete class
 *   6. Checkout system: only 6 interface bindings — zero service-class bindings
 *   7. All four assertions pass
 */


class CircularDependencyException extends \RuntimeException {}

// ─────────────────────────────────────────────────────────────────────────────
// Full checkout system — unchanged from starter
// ─────────────────────────────────────────────────────────────────────────────

interface DatabaseInterface {
    public function query(string $sql, array $params = []): array;
    public function execute(string $sql, array $params = []): bool;
}
interface CacheInterface {
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, int $ttl = 120): void;
}
interface LoggerInterface    { public function log(string $level, string $message): void; }
interface MailerInterface    { public function send(string $to, string $subject, string $body): bool; }
interface ProductRepositoryInterface {
    public function findById(int $id): ?array;
    public function findBySku(string $sku): ?array;
}
interface InventoryInterface {
    public function isAvailable(string $sku, int $quantity): bool;
    public function reserve(string $sku, int $quantity): bool;
}

class InMemoryDatabase implements DatabaseInterface {
    private array $products  = [
        1 => ['id' => 1, 'sku' => 'WDG-001', 'name' => 'Widget Pro',  'price' => 29999, 'stock' => 50],
        2 => ['id' => 2, 'sku' => 'WDG-002', 'name' => 'Widget Lite', 'price' => 14999, 'stock' => 5],
    ];
    private array $inventory = ['WDG-001' => 50, 'WDG-002' => 5];
    private string $instanceId;

    public function __construct() { $this->instanceId = substr(md5(uniqid()), 0, 6); }
    public function getInstanceId(): string { return $this->instanceId; }

    public function query(string $sql, array $params = []): array {
        if (str_contains($sql, 'products') && !empty($params)) {
            if (is_int($params[0])) return isset($this->products[$params[0]]) ? [$this->products[$params[0]]] : [];
            foreach ($this->products as $p) { if ($p['sku'] === $params[0]) return [$p]; }
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
        $v = $this->store[$key] ?? null;
        echo "  [CACHE] " . ($v ? 'HIT' : 'MISS') . ": {$key}\n";
        return $v;
    }
    public function set(string $key, mixed $value, int $ttl = 120): void {
        $this->store[$key] = $value;
        echo "  [CACHE] SET: {$key}\n";
    }
}
class ConsoleLogger implements LoggerInterface {
    public function log(string $level, string $message): void { echo "  [{$level}] {$message}\n"; }
}
class ConsoleMailer implements MailerInterface {
    public function send(string $to, string $subject, string $body): bool {
        echo "  [MAIL] To: {$to} | {$subject}\n"; return true;
    }
}

class ProductCatalog implements ProductRepositoryInterface {
    public function __construct(
        private DatabaseInterface $db,
        private CacheInterface    $cache,
        private LoggerInterface   $logger
    ) {}
    public function getDb(): DatabaseInterface { return $this->db; }
    public function findById(int $id): ?array {
        $key = "product_{$id}";
        $cached = $this->cache->get($key);
        if ($cached !== null) return $cached;
        $this->logger->log('INFO', "DB fetch: product #{$id}");
        $rows = $this->db->query('SELECT * FROM products WHERE id = ?', [$id]);
        $p = $rows[0] ?? null;
        if ($p) $this->cache->set($key, $p);
        return $p;
    }
    public function findBySku(string $sku): ?array {
        $rows = $this->db->query('SELECT * FROM products WHERE sku = ?', [$sku]);
        return $rows[0] ?? null;
    }
}

class InventoryChecker implements InventoryInterface {
    public function __construct(private DatabaseInterface $db) {}
    public function getDb(): DatabaseInterface { return $this->db; }
    public function isAvailable(string $sku, int $quantity): bool {
        echo "  [INVENTORY] Checking {$sku} × {$quantity}\n";
        $rows = $this->db->query('SELECT quantity FROM inventory WHERE sku = ?', [$sku]);
        return ($rows[0]['quantity'] ?? 0) >= $quantity;
    }
    public function reserve(string $sku, int $quantity): bool {
        echo "  [INVENTORY] Reserving {$sku} × {$quantity}\n";
        return $this->db->execute('UPDATE inventory SET quantity = quantity - ? WHERE sku = ?', [$quantity, $sku]);
    }
}

class CheckoutService {
    public function __construct(
        private ProductRepositoryInterface $catalog,
        private InventoryInterface         $inventory,
        private MailerInterface            $mailer,
        private LoggerInterface            $logger
    ) {}
    public function checkout(array $cart, string $email): array {
        $total = 0; $items = [];
        foreach ($cart as $item) {
            $product = $this->catalog->findById($item['product_id']);
            if (!$product) return ['success' => false, 'error' => 'Not found'];
            if (!$this->inventory->isAvailable($product['sku'], $item['quantity'])) {
                return ['success' => false, 'error' => 'Out of stock'];
            }
            $this->inventory->reserve($product['sku'], $item['quantity']);
            $total += $product['price'] * $item['quantity'];
            $items[] = ['name' => $product['name'], 'qty' => $item['quantity']];
        }
        $orderId = rand(10000, 99999);
        $this->mailer->send($email, "Order Confirmed #{$orderId}", "Total: R" . ($total / 100));
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
        $result = $this->service->checkout($request['cart'] ?? [], $request['email'] ?? 'guest');
        return json_encode($result, JSON_PRETTY_PRINT);
    }
}

class CircularA { public function __construct(private CircularB $b) {} }
class CircularB { public function __construct(private CircularA $a) {} }


// ─────────────────────────────────────────────────────────────────────────────
// Task 1 — SimpleContainer with auto-wiring + circular detection
// ─────────────────────────────────────────────────────────────────────────────

class SimpleContainer {
    private array $bindings   = [];
    private array $singletons = [];
    private array $instances  = [];
    /** @var array<string, bool> classes currently being resolved */
    private array $resolving  = [];

    public function bind(string $id, string|callable $factory): void {
        $this->bindings[$id]   = $factory;
        $this->singletons[$id] = false;
    }
    public function singleton(string $id, callable $factory): void {
        $this->bindings[$id]   = $factory;
        $this->singletons[$id] = true;
    }
    public function instance(string $id, object $object): void {
        $this->instances[$id]  = $object;
        $this->singletons[$id] = true;
    }

    public function get(string $id): mixed {
        // Return cached singleton / pre-built instance
        if (isset($this->instances[$id])) return $this->instances[$id];

        if (isset($this->bindings[$id])) {
            $binding = $this->bindings[$id];

            // Callable factory
            if (is_callable($binding)) {
                $result = $binding($this);
                if ($this->singletons[$id] ?? false) $this->instances[$id] = $result;
                return $result;
            }

            // String: explicit concrete class name — auto-wire it
            $result = $this->autowire($binding);
            // Cache under both the interface id AND the concrete class name
            $this->instances[$id]      = $result;
            $this->instances[$binding] = $result;
            return $result;
        }

        // No explicit binding — try auto-wiring
        return $this->autowire($id);
    }

    public function has(string $id): bool {
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }

    /**
     * Auto-wire a class by reflecting its constructor and resolving deps recursively.
     * Results are cached as singletons.
     */
    private function autowire(string $class): object {
        // Return from cache if already built
        if (isset($this->instances[$class])) return $this->instances[$class];

        // ── Circular dependency detection ─────────────────────────────────────
        if (isset($this->resolving[$class])) {
            $chain = implode(' → ', array_keys($this->resolving)) . ' → ' . $class;
            throw new CircularDependencyException(
                "Circular dependency detected: {$chain}"
            );
        }

        $ref = new ReflectionClass($class);
        if (!$ref->isInstantiable()) {
            throw new \RuntimeException(
                "Cannot auto-wire '{$class}': not instantiable (abstract class or interface). " .
                "Register an explicit binding with bind() or instance()."
            );
        }

        // Mark as currently being resolved
        $this->resolving[$class] = true;

        try {
            $ctor = $ref->getConstructor();

            // No constructor or empty constructor
            if ($ctor === null || count($ctor->getParameters()) === 0) {
                $instance = new $class();
            } else {
                $deps = [];
                foreach ($ctor->getParameters() as $param) {
                    $type = $param->getType();

                    if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                        // Class or interface — resolve recursively
                        $deps[] = $this->get($type->getName());
                    } elseif ($param->isOptional()) {
                        // Has a default value — use it
                        $deps[] = $param->getDefaultValue();
                    } else {
                        throw new \RuntimeException(
                            "Cannot auto-wire '\${$param->getName()}' in '{$class}': " .
                            "required primitive or untyped parameter. " .
                            "Register an explicit factory with bind() or instance()."
                        );
                    }
                }
                $instance = $ref->newInstanceArgs($deps);
            }
        } finally {
            // Always unmark — even if an exception was thrown mid-resolution
            unset($this->resolving[$class]);
        }

        // Cache as singleton
        return $this->instances[$class] = $instance;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Task 2 — Wire with only interface bindings
// ─────────────────────────────────────────────────────────────────────────────

echo "=== Checkout via auto-wiring container ===\n\n";

$container = new SimpleContainer();

// Only interface → concrete bindings needed.
// ProductCatalog, InventoryChecker, CheckoutService, CheckoutController: auto-wired.
$container->bind(DatabaseInterface::class,         InMemoryDatabase::class);
$container->bind(CacheInterface::class,             ArrayCache::class);
$container->bind(LoggerInterface::class,            ConsoleLogger::class);
$container->bind(MailerInterface::class,            ConsoleMailer::class);
$container->bind(ProductRepositoryInterface::class, ProductCatalog::class);
$container->bind(InventoryInterface::class,         InventoryChecker::class);

$controller = $container->get(CheckoutController::class);
$response   = $controller->handleCheckout([
    'cart'  => [['product_id' => 1, 'quantity' => 2]],
    'email' => 'alice@example.com',
]);
echo "\n" . $response . "\n";


// ─────────────────────────────────────────────────────────────────────────────
// Tasks 3 & 4 — Assertions
// ─────────────────────────────────────────────────────────────────────────────

echo "\n=== Assertions ===\n\n";

$pass = true;

function assertThat(bool $condition, string $label, bool &$allPassed): void {
    echo ($condition ? '  ✓' : '  ✗') . " {$label}\n";
    if (!$condition) $allPassed = false;
}

// Task 3a: Singleton — two resolutions return the same instance
$ctrl1 = $container->get(CheckoutController::class);
$ctrl2 = $container->get(CheckoutController::class);
assertThat($ctrl1 === $ctrl2, 'Same controller instance (singleton)', $pass);

// Task 3b: Shared DB — ProductCatalog and InventoryChecker share the same DatabaseInterface
$catalog   = $container->get(ProductRepositoryInterface::class);
$inventory = $container->get(InventoryInterface::class);
assertThat(
    $catalog->getDb() === $inventory->getDb(),
    'Same DB in ProductCatalog and InventoryChecker',
    $pass
);

// Task 4: Circular dependency detection
$circularThrown   = false;
$circularMessage  = '';
try {
    $container->get(CircularA::class);
} catch (CircularDependencyException $e) {
    $circularThrown  = true;
    $circularMessage = $e->getMessage();
}
assertThat($circularThrown, 'CircularDependencyException thrown', $pass);
assertThat(
    str_contains($circularMessage, 'CircularA') && str_contains($circularMessage, 'CircularB'),
    'Exception message contains CircularA and CircularB',
    $pass
);
echo "  Exception message: {$circularMessage}\n";

echo "\n" . ($pass ? 'All assertions PASSED ✓' : 'Some assertions FAILED ✗') . "\n";


// ─────────────────────────────────────────────────────────────────────────────
// SELF-REVIEW CHECKLIST
// ─────────────────────────────────────────────────────────────────────────────
echo "\n--- Self-review checklist ---\n";
echo "[ ] autowire() calls get() recursively for non-builtin type hints?\n";
echo "[ ] autowire() uses finally to always unmark \$resolving?\n";
echo "[ ] autowire() caches results in \$instances?\n";
echo "[ ] Circular detection: checks \$resolving before marking?\n";
echo "[ ] Exception message shows the full dependency chain?\n";
echo "[ ] get() calls autowire() when no binding exists?\n";
echo "[ ] Explicit string binding calls autowire() on the concrete class?\n";
echo "[ ] 6 interface bindings — zero bindings for service classes?\n";
echo "[ ] Same controller assertion passes?\n";
echo "[ ] Same DB assertion passes?\n";
echo "[ ] CircularDependencyException thrown and message contains both class names?\n";