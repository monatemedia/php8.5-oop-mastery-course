<?php
declare(strict_types=1);

/**
 * Example 04 — Building a Manual IoC Container from Scratch
 * -----------------------------------------------------------
 * This is the capstone example for Module 3.
 *
 * We build a complete manual IoC wiring system in three stages:
 *
 *   Stage 1: The flat wiring function — the simplest form of IoC
 *   Stage 2: Adding the pain of scale — what breaks at 20+ services
 *   Stage 3: A minimal reflection-based container — what Module 4 automates fully
 *
 * By the end of this example you will understand exactly what PHP-DI does
 * internally — so Module 4 is a natural extension, not a magic black box.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Building a Manual IoC Container from Scratch      ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// The application we will wire (same throughout all three stages)
// ─────────────────────────────────────────────────────────────────────────────

// Interfaces
interface DatabaseInterface {
    public function query(string $sql, array $params = []): array;
    public function execute(string $sql, array $params = []): bool;
}

interface CacheInterface {
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, int $ttl = 300): void;
}

interface LoggerInterface {
    public function log(string $level, string $message): void;
}

interface MailerInterface {
    public function send(string $to, string $subject, string $body): bool;
}

// Concrete implementations
class InMemoryDatabase implements DatabaseInterface {
    private array $products = [
        1 => ['id' => 1, 'sku' => 'WDG-001', 'name' => 'Widget Pro', 'price' => 29999],
        2 => ['id' => 2, 'sku' => 'WDG-002', 'name' => 'Widget Lite', 'price' => 14999],
    ];
    private array $orders = [];

    public function query(string $sql, array $params = []): array {
        if (str_contains($sql, 'products') && !empty($params)) {
            return isset($this->products[$params[0]]) ? [$this->products[$params[0]]] : [];
        }
        return array_values($this->products);
    }
    public function execute(string $sql, array $params = []): bool {
        if (str_contains($sql, 'orders')) {
            $this->orders[] = $params;
            echo "  [DB] Order saved\n";
        }
        return true;
    }
}

class SimpleCache implements CacheInterface {
    private array $store = [];
    public function get(string $key): mixed {
        echo "  [CACHE] " . (isset($this->store[$key]) ? 'HIT' : 'MISS') . ": {$key}\n";
        return $this->store[$key] ?? null;
    }
    public function set(string $key, mixed $value, int $ttl = 300): void {
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

// Application services
class ProductRepository {
    public function __construct(
        private DatabaseInterface $db,
        private CacheInterface    $cache,
        private LoggerInterface   $logger
    ) {}

    public function findById(int $id): ?array {
        $key    = "product:{$id}";
        $cached = $this->cache->get($key);
        if ($cached !== null) return $cached;

        $this->logger->log('INFO', "DB fetch: product #{$id}");
        $rows = $this->db->query('SELECT * FROM products WHERE id = ?', [$id]);
        $product = $rows[0] ?? null;
        if ($product) $this->cache->set($key, $product);
        return $product;
    }
}

class OrderService {
    public function __construct(
        private ProductRepository $products,
        private DatabaseInterface $db,
        private MailerInterface   $mailer,
        private LoggerInterface   $logger
    ) {}

    public function placeOrder(array $cart, string $email): array {
        $this->logger->log('INFO', "Order started for {$email}");
        $total = 0;
        $lines = [];

        foreach ($cart as $item) {
            $product = $this->products->findById($item['id']);
            if (!$product) {
                return ['success' => false, 'error' => "Product {$item['id']} not found"];
            }
            $subtotal = $product['price'] * $item['qty'];
            $total   += $subtotal;
            $lines[]  = ['name' => $product['name'], 'qty' => $item['qty'], 'subtotal' => $subtotal];
        }

        $orderId = rand(10000, 99999);
        $this->db->execute('INSERT INTO orders (id, total, email) VALUES (?,?,?)',
            [$orderId, $total, $email]);
        $this->mailer->send($email, "Order #{$orderId} Confirmed",
            "Total: R" . number_format($total / 100, 2));
        $this->logger->log('INFO', "Order #{$orderId} placed. Total: R" . ($total / 100));

        return ['success' => true, 'order_id' => $orderId, 'total' => $total, 'lines' => $lines];
    }
}

class OrderController {
    public function __construct(
        private OrderService    $orderService,
        private LoggerInterface $logger
    ) {}

    public function handleCheckout(array $request): string {
        $this->logger->log('INFO', "Checkout request received");
        $result = $this->orderService->placeOrder(
            $request['cart']  ?? [],
            $request['email'] ?? 'guest@example.com'
        );
        return json_encode($result, JSON_PRETTY_PRINT);
    }
}


// ═══════════════════════════════════════════════════════════
// STAGE 1 — Flat wiring function (the simplest IoC)
// ═══════════════════════════════════════════════════════════

echo "── Stage 1: Flat wiring function ────────────────────\n\n";

function buildApp(): OrderController {
    // ── All `new` calls live here and ONLY here ────────────
    // Infrastructure (no dependencies of their own)
    $db     = new InMemoryDatabase();
    $cache  = new SimpleCache();
    $logger = new ConsoleLogger();
    $mailer = new ConsoleMailer();

    // Repositories (depend on infrastructure)
    $products = new ProductRepository($db, $cache, $logger);

    // Services (depend on repositories + infrastructure)
    $orderService = new OrderService($products, $db, $mailer, $logger);

    // HTTP layer (depends on services)
    return new OrderController($orderService, $logger);
}

$controller = buildApp();
echo "Checkout result:\n";
$result = $controller->handleCheckout([
    'cart'  => [['id' => 1, 'qty' => 2], ['id' => 2, 'qty' => 1]],
    'email' => 'alice@example.com',
]);
echo "\n" . $result . "\n\n";

echo "Why this IS IoC:\n";
echo "  ✓ OrderController, OrderService, ProductRepository never call `new`\n";
echo "  ✓ All wiring is at the entry point (the function above)\n";
echo "  ✓ Swap InMemoryDatabase for MySQLDatabase: edit ONE line\n";
echo "  ✓ Swap ConsoleMailer for SmtpMailer: edit ONE line\n\n";


// ═══════════════════════════════════════════════════════════
// STAGE 2 — The pain at scale
// ═══════════════════════════════════════════════════════════

echo "── Stage 2: The pain at scale ───────────────────────\n\n";

echo "With 5 services: manageable (the buildApp() above).\n\n";
echo "With 50 services:\n";
echo "  \$db           = new InMemoryDatabase();\n";
echo "  \$cache        = new SimpleCache();\n";
echo "  \$logger       = new ConsoleLogger();\n";
echo "  \$mailer       = new ConsoleMailer();\n";
echo "  \$userRepo     = new UserRepository(\$db, \$cache, \$logger);\n";
echo "  \$productRepo  = new ProductRepository(\$db, \$cache, \$logger);\n";
echo "  \$orderRepo    = new OrderRepository(\$db, \$logger);\n";
echo "  \$inventoryRepo = new InventoryRepository(\$db, \$cache, \$logger);\n";
echo "  \$authService  = new AuthService(\$userRepo, \$logger);\n";
echo "  \$orderService = new OrderService(\$productRepo, \$orderRepo,\n";
echo "                      \$inventoryRepo, \$gateway, \$mailer, \$logger);\n";
echo "  ... (40 more lines)\n\n";

echo "Problems at 50+ services:\n";
echo "  ✗ Every new service requires a new line — and the right ORDER\n";
echo "  ✗ \$logger appears 40 times — should it be shared? (currently: YES)\n";
echo "  ✗ Add a parameter to ProductRepository? Update buildApp() manually\n";
echo "  ✗ The wiring file becomes the hardest file in the codebase to maintain\n\n";


// ═══════════════════════════════════════════════════════════
// STAGE 3 — A Reflection-based container (Module 4 preview)
// ═══════════════════════════════════════════════════════════

echo "── Stage 3: Reflection-based container ──────────────\n\n";

/**
 * ManualContainer — a production-preview container in ~60 lines.
 *
 * What it does:
 *   1. Stores bindings: interface name → concrete class name
 *   2. On make(ClassName): uses Reflection to read constructor params
 *   3. Resolves each param recursively (checks bindings first)
 *   4. Caches instances as singletons
 *
 * This is exactly what PHP-DI does internally.
 * Module 4 replaces this with the full library, which adds:
 *   - Factory definitions (for classes needing env vars or primitives)
 *   - Transient vs singleton scopes
 *   - Lazy proxies
 *   - Compilation for production performance
 */
class ManualContainer {
    private array $bindings  = [];  // interface → concrete class name
    private array $instances = [];  // class name → singleton instance

    public function bind(string $abstract, string $concrete): void {
        $this->bindings[$abstract] = $concrete;
    }

    public function singleton(string $abstract, object $instance): void {
        $this->instances[$abstract] = $instance;
    }

    public function make(string $abstract): object {
        // 1. Already built? Return the singleton.
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // 2. Resolve binding (interface → concrete)
        $concrete = $this->bindings[$abstract] ?? $abstract;

        // 3. Also check if the concrete itself was cached
        if (isset($this->instances[$concrete])) {
            return $this->instances[$concrete];
        }

        // 4. Reflect on the concrete class
        $ref  = new ReflectionClass($concrete);
        $ctor = $ref->getConstructor();

        if ($ctor === null || count($ctor->getParameters()) === 0) {
            $instance = new $concrete();
            return $this->instances[$abstract] = $this->instances[$concrete] = $instance;
        }

        // 5. Recursively resolve each constructor parameter
        $deps = [];
        foreach ($ctor->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $deps[] = $this->make($type->getName());
            } else {
                throw new \RuntimeException(
                    "Cannot auto-resolve parameter '\${$param->getName()}' " .
                    "in {$concrete} — no type hint or primitive type. " .
                    "Use singleton() to register this manually."
                );
            }
        }

        // 6. Build and cache the instance
        $instance = $ref->newInstanceArgs($deps);
        $this->instances[$abstract] = $this->instances[$concrete] = $instance;
        return $instance;
    }
}

// ── Wire using the container ──────────────────────────────────────────────────

echo "Wiring with ManualContainer:\n\n";

$container = new ManualContainer();

// Bind interfaces to concrete classes (replaces the flat wiring function)
$container->bind(DatabaseInterface::class, InMemoryDatabase::class);
$container->bind(CacheInterface::class,    SimpleCache::class);
$container->bind(LoggerInterface::class,   ConsoleLogger::class);
$container->bind(MailerInterface::class,   ConsoleMailer::class);

// Resolve the full graph — container uses Reflection to wire everything
echo "Container auto-wiring OrderController:\n";
$autoController = $container->make(OrderController::class);

echo "\nUsing auto-wired controller:\n";
$result2 = $autoController->handleCheckout([
    'cart'  => [['id' => 1, 'qty' => 1]],
    'email' => 'bob@example.com',
]);
echo "\n" . $result2 . "\n\n";

// Show what the container built
echo "What the container resolved (singletons):\n";
$ref = new ReflectionClass(ManualContainer::class);
$prop = $ref->getProperty('instances');
$prop->setAccessible(true);
$instances = $prop->getValue($container);
foreach ($instances as $key => $value) {
    // Only show the interface keys (skip duplicate concrete entries)
    if (str_ends_with($key, 'Interface') || in_array($key, [
        ProductRepository::class, OrderService::class, OrderController::class
    ], true)) {
        echo "  " . class_basename($key) . " → " . get_class($value) . "\n";
    }
}

echo "\n── What PHP-DI adds on top of this ──────────────────\n\n";
echo "ManualContainer (above): ~60 lines\n";
echo "  ✓ Auto-wiring via Reflection\n";
echo "  ✓ Singleton cache\n";
echo "  ✓ Interface → concrete bindings\n\n";
echo "PHP-DI (Module 4): full library\n";
echo "  + Factory definitions (for classes needing env vars or string params)\n";
echo "  + Transient scope (new instance every resolution — not singleton)\n";
echo "  + Lazy proxies (defer construction until first use)\n";
echo "  + Compiled container (cache Reflection results for production)\n";
echo "  + PSR-11 compliance (standard ContainerInterface)\n";
echo "  + Framework integration (Slim, Symfony, Laravel)\n\n";

echo "The ManualContainer above is NOT a replacement for PHP-DI.\n";
echo "It is the mental model that makes PHP-DI understandable.\n";
echo "Every container you will ever use does exactly what is above — just more robustly.\n";

echo "\n--- Recap ---\n";
echo "Stage 1: Flat wiring function — IoC without automation. Correct but verbose.\n";
echo "Stage 2: At 50+ services, manual wiring becomes the hardest file to maintain.\n";
echo "Stage 3: Reflection container auto-wires by reading constructor type hints.\n";
echo "Module 4: PHP-DI is Stage 3 — production-grade, with factory defs and scopes.\n";

function class_basename(string $class): string {
    $parts = explode('\\', $class);
    return end($parts);
}