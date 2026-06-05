<?php
declare(strict_types=1);

/**
 * Example 02 — Recursive Resolution
 * ------------------------------------
 * Auto-wiring works recursively. When the container resolves OrderService,
 * it needs ProductRepository, which needs DatabaseInterface, which maps to
 * InMemoryDatabase — and the container resolves each level automatically.
 *
 * This example:
 *   A. Builds a realistic 4-level dependency graph
 *   B. Shows the container resolving it with only interface bindings
 *   C. Traces the resolution order step by step
 *   D. Demonstrates that singletons are shared across the whole graph
 *
 * Course Philosophy Rule 5: Objects either hold state or perform work.
 * All services here are stateless — they perform work, hold no mutable state.
 * This makes them safe as singletons, shared across the entire resolved graph.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Recursive Resolution — 4-Level Dependency Graph   ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Interfaces
// ─────────────────────────────────────────────────────────────────────────────

interface DatabaseInterface {
    public function query(string $sql, array $params = []): array;
    public function execute(string $sql, array $params = []): bool;
}
interface CacheInterface {
    public function get(string $key): mixed;
    public function set(string $key, mixed $value): void;
}
interface LoggerInterface {
    public function log(string $level, string $message): void;
    public function getInstanceId(): string;
}
interface MailerInterface {
    public function send(string $to, string $subject, string $body): bool;
}
interface EventDispatcherInterface {
    public function dispatch(string $event, array $payload = []): void;
}

// Implementations with unique IDs so we can track singleton sharing
class InMemoryDatabase implements DatabaseInterface {
    private string $id;
    private array $products = [
        1 => ['id' => 1, 'sku' => 'WDG-001', 'name' => 'Widget Pro',  'price' => 29999],
        2 => ['id' => 2, 'sku' => 'WDG-002', 'name' => 'Widget Lite', 'price' => 14999],
    ];
    private array $inventory = ['WDG-001' => 50, 'WDG-002' => 5];
    private array $orders = [];

    public function __construct() {
        $this->id = substr(md5(uniqid()), 0, 6);
        echo "  [NEW] InMemoryDatabase #{$this->id}\n";
    }
    public function getId(): string { return $this->id; }
    public function query(string $sql, array $params = []): array {
        echo "  [DB#{$this->id}] query\n";
        if (str_contains($sql, 'products') && !empty($params)) {
            return isset($this->products[$params[0]]) ? [$this->products[$params[0]]] : [];
        }
        if (str_contains($sql, 'inventory') && !empty($params)) {
            return [['sku' => $params[0], 'quantity' => $this->inventory[$params[0]] ?? 0]];
        }
        return array_values($this->products);
    }
    public function execute(string $sql, array $params = []): bool {
        echo "  [DB#{$this->id}] execute\n";
        return true;
    }
}

class ArrayCache implements CacheInterface {
    private array $store = [];
    private string $id;
    public function __construct() {
        $this->id = substr(md5(uniqid()), 0, 6);
        echo "  [NEW] ArrayCache #{$this->id}\n";
    }
    public function get(string $key): mixed  { return $this->store[$key] ?? null; }
    public function set(string $key, mixed $value): void { $this->store[$key] = $value; }
}

class ConsoleLogger implements LoggerInterface {
    private string $id;
    public function __construct() {
        $this->id = substr(md5(uniqid()), 0, 6);
        echo "  [NEW] ConsoleLogger #{$this->id}\n";
    }
    public function log(string $level, string $message): void {
        echo "  [{$level}] (logger #{$this->id}) {$message}\n";
    }
    public function getInstanceId(): string { return $this->id; }
}

class ConsoleMailer implements MailerInterface {
    public function __construct() { echo "  [NEW] ConsoleMailer\n"; }
    public function send(string $to, string $subject, string $body): bool {
        echo "  [MAIL] To: {$to} | {$subject}\n";
        return true;
    }
}

class SimpleDispatcher implements EventDispatcherInterface {
    public function __construct() { echo "  [NEW] SimpleDispatcher\n"; }
    public function dispatch(string $event, array $payload = []): void {
        echo "  [EVENT] {$event}\n";
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// 4-level dependency graph
// ─────────────────────────────────────────────────────────────────────────────

// Level 1 (deepest) — depends only on interfaces
class ProductRepository {
    public function __construct(
        private DatabaseInterface $db,
        private CacheInterface    $cache,
        private LoggerInterface   $logger
    ) { echo "  [NEW] ProductRepository\n"; }

    public function findById(int $id): ?array {
        $key    = "product:{$id}";
        $cached = $this->cache->get($key);
        if ($cached) return $cached;
        $rows = $this->db->query('SELECT * FROM products WHERE id = ?', [$id]);
        $p = $rows[0] ?? null;
        if ($p) $this->cache->set($key, $p);
        return $p;
    }
    public function getLogger(): LoggerInterface { return $this->logger; }
}

class InventoryService {
    public function __construct(
        private DatabaseInterface $db,
        private LoggerInterface   $logger
    ) { echo "  [NEW] InventoryService\n"; }

    public function reserve(string $sku, int $qty): bool {
        $rows  = $this->db->query('SELECT quantity FROM inventory WHERE sku = ?', [$sku]);
        $stock = $rows[0]['quantity'] ?? 0;
        if ($stock < $qty) return false;
        $this->db->execute('UPDATE inventory SET quantity = quantity - ? WHERE sku = ?', [$qty, $sku]);
        $this->logger->log('INFO', "Reserved {$qty} × {$sku}");
        return true;
    }
    public function getLogger(): LoggerInterface { return $this->logger; }
}

// Level 2 — depends on Level 1 + interfaces
class CheckoutService {
    public function __construct(
        private ProductRepository    $products,
        private InventoryService     $inventory,
        private MailerInterface      $mailer,
        private LoggerInterface      $logger
    ) { echo "  [NEW] CheckoutService\n"; }

    public function checkout(int $productId, string $email): bool {
        $this->logger->log('INFO', "Checkout: product #{$productId} for {$email}");
        $product = $this->products->findById($productId);
        if (!$product) return false;
        $reserved = $this->inventory->reserve($product['sku'], 1);
        if (!$reserved) return false;
        $this->mailer->send($email, "Order Confirmed", "Total: R" . ($product['price'] / 100));
        return true;
    }
    public function getLogger(): LoggerInterface { return $this->logger; }
}

// Level 3 — depends on Level 2 + interfaces
class OrderController {
    public function __construct(
        private CheckoutService       $checkout,
        private EventDispatcherInterface $dispatcher,
        private LoggerInterface       $logger
    ) { echo "  [NEW] OrderController\n"; }

    public function handle(array $request): string {
        $this->logger->log('INFO', "Request received");
        $ok = $this->checkout->checkout(
            $request['product_id'] ?? 1,
            $request['email'] ?? 'guest@example.com'
        );
        if ($ok) {
            $this->dispatcher->dispatch('order.placed', ['product' => $request['product_id']]);
        }
        return json_encode(['success' => $ok]);
    }
    public function getLogger(): LoggerInterface { return $this->logger; }
}


// ═══════════════════════════════════════════════════════════
// AutowiringContainer (from Example 01, with resolution tracing)
// ═══════════════════════════════════════════════════════════

class AutowiringContainer {
    private array $bindings  = [];
    private array $instances = [];
    private int   $depth     = 0;

    public function bind(string $id, string|callable $target): void {
        $this->bindings[$id] = $target;
    }
    public function instance(string $id, object $obj): void {
        $this->instances[$id] = $obj;
    }

    public function get(string $id): object {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }
        if (isset($this->bindings[$id])) {
            $binding = $this->bindings[$id];
            if (is_callable($binding)) {
                return $this->instances[$id] = $binding($this);
            }
            return $this->instances[$id] = $this->resolve($binding);
        }
        return $this->instances[$id] = $this->resolve($id);
    }

    public function has(string $id): bool {
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }

    private function resolve(string $class): object {
        $ref = new ReflectionClass($class);
        if (!$ref->isInstantiable()) {
            throw new \RuntimeException("Not instantiable: {$class}");
        }
        $ctor = $ref->getConstructor();
        if ($ctor === null || count($ctor->getParameters()) === 0) {
            return new $class();
        }
        $deps = [];
        foreach ($ctor->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $deps[] = $this->get($type->getName());
            } elseif ($param->isOptional()) {
                $deps[] = $param->getDefaultValue();
            } else {
                throw new \RuntimeException(
                    "Cannot auto-wire '\${$param->getName()}' in '{$class}'"
                );
            }
        }
        return $ref->newInstanceArgs($deps);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Wire and run
// ─────────────────────────────────────────────────────────────────────────────

echo "── Building the container (only interface bindings) ─\n\n";

$container = new AutowiringContainer();
$container->bind(DatabaseInterface::class,       InMemoryDatabase::class);
$container->bind(CacheInterface::class,          ArrayCache::class);
$container->bind(LoggerInterface::class,         ConsoleLogger::class);
$container->bind(MailerInterface::class,         ConsoleMailer::class);
$container->bind(EventDispatcherInterface::class, SimpleDispatcher::class);

echo "\nExplicit bindings: 5 (one per interface)\n";
echo "Auto-wired classes: ProductRepository, InventoryService, CheckoutService, OrderController\n\n";

echo "── Resolving OrderController (watch construction order) ─\n\n";
$controller = $container->get(OrderController::class);

echo "\n── Using the resolved controller ─────────────────────\n\n";
$response = $controller->handle(['product_id' => 1, 'email' => 'alice@example.com']);
echo "Response: {$response}\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Verify singleton sharing across the graph
// ─────────────────────────────────────────────────────────────────────────────

echo "── Singleton sharing across the graph ───────────────\n\n";

// All four classes should share the SAME LoggerInterface instance
$checkout   = $container->get(CheckoutService::class);
$products   = $container->get(ProductRepository::class);
$inventory  = $container->get(InventoryService::class);
$sharedLogger = $container->get(LoggerInterface::class);

$loggers = [
    'LoggerInterface'   => $sharedLogger,
    'OrderController'   => $controller->getLogger(),
    'CheckoutService'   => $checkout->getLogger(),
    'ProductRepository' => $products->getLogger(),
    'InventoryService'  => $inventory->getLogger(),
];

$firstId = $sharedLogger->getInstanceId();
echo "All classes use the same LoggerInterface instance (ID #{$firstId}):\n";
foreach ($loggers as $name => $logger) {
    $same = $logger->getInstanceId() === $firstId;
    echo "  {$name}: #{$logger->getInstanceId()} " . ($same ? '✓' : '✗') . "\n";
}

echo "\nConclusion:\n";
echo "  ONE ConsoleLogger created — shared by all five resolved classes.\n";
echo "  ONE InMemoryDatabase created — shared by ProductRepository and InventoryService.\n";
echo "  Singletons eliminate waste and ensure consistent shared state where desired.\n";

echo "\n--- Recap ---\n";
echo "Recursive resolution: the container walks the full dep tree automatically.\n";
echo "Construction order:   leaves first (InMemoryDatabase), root last (OrderController).\n";
echo "Singleton sharing:    one logger instance across the entire 4-level graph.\n";
echo "Explicit bindings:    5 (interfaces only) — zero bindings for service classes.\n";