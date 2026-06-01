<?php
declare(strict_types=1);

/**
 * CHALLENGE STARTER — Lesson 4.1: Service Containers
 * ────────────────────────────────────────────────────
 * Read CHALLENGE.md before touching this file.
 *
 * Complete the SimpleContainer skeleton, then wire the checkout system with it.
 * Do NOT look at solution.php until you have made a genuine attempt.
 */


// ─────────────────────────────────────────────────────────────────────────────
// INFRASTRUCTURE (already using constructor injection from Module 3)
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
        1 => ['id' => 1, 'sku' => 'WDG-001', 'name' => 'Widget Pro', 'price' => 29999, 'stock' => 50],
        2 => ['id' => 2, 'sku' => 'WDG-002', 'name' => 'Widget Lite', 'price' => 14999, 'stock' => 3],
    ];
    private array $inventory = [
        'WDG-001' => 50,
        'WDG-002' => 3,
    ];

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
            $qty = $this->inventory[$params[0]] ?? 0;
            return [['sku' => $params[0], 'quantity' => $qty]];
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

    public function isAvailable(string $sku, int $quantity): bool {
        echo "  [INVENTORY] Checking {$sku} × {$quantity}\n";
        $rows  = $this->db->query('SELECT quantity FROM inventory WHERE sku = $1', [$sku]);
        $stock = $rows[0]['quantity'] ?? 0;
        return $stock >= $quantity;
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
        $total = 0;
        $items = [];

        foreach ($cart as $item) {
            $product = $this->catalog->findById($item['product_id']);
            if (!$product) return ['success' => false, 'error' => 'Product not found'];
            $sku = $product['sku'];
            if (!$this->inventory->isAvailable($sku, $item['quantity'])) {
                return ['success' => false, 'error' => 'Insufficient stock'];
            }
            $this->inventory->reserve($sku, $item['quantity']);
            $lineTotal  = $product['price'] * $item['quantity'];
            $total     += $lineTotal;
            $items[]    = ['name' => $product['name'], 'qty' => $item['quantity'], 'subtotal' => $lineTotal];
        }

        $orderId = rand(10000, 99999);
        $this->mailer->send($customerEmail, "Order Confirmed #{$orderId}",
            "Total: R" . number_format($total / 100, 2));
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
        $result = $this->service->checkout(
            $request['cart']  ?? [],
            $request['email'] ?? 'guest@example.com'
        );
        return json_encode($result, JSON_PRETTY_PRINT);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// CURRENT: flat wiring function (working — keep this for comparison)
// ─────────────────────────────────────────────────────────────────────────────

function buildApp(): CheckoutController {
    $db       = new InMemoryDatabase();
    $cache    = new ArrayCache();
    $logger   = new ConsoleLogger();
    $mailer   = new ConsoleMailer();
    $catalog  = new ProductCatalog($db, $cache, $logger);
    $inventory = new InventoryChecker($db);
    $service  = new CheckoutService($catalog, $inventory, $mailer, $logger);
    return new CheckoutController($service, $logger);
}

echo "=== Flat wiring (buildApp) ===\n\n";
$flatController = buildApp();
echo $flatController->handleCheckout([
    'cart'  => [['product_id' => 1, 'quantity' => 2]],
    'email' => 'alice@example.com',
]) . "\n";


// ─────────────────────────────────────────────────────────────────────────────
// TODO Task 1: Complete the SimpleContainer class
// ─────────────────────────────────────────────────────────────────────────────

class SimpleContainer {
    private array $bindings   = [];
    private array $singletons = [];
    private array $instances  = [];

    // TODO: Implement bind()
    public function bind(string $id, callable $factory): void {
        // Register factory — fresh instance every get()
    }

    // TODO: Implement singleton()
    public function singleton(string $id, callable $factory): void {
        // Register singleton — built once, cached, returned for all subsequent get()
    }

    // TODO: Implement instance()
    public function instance(string $id, object $object): void {
        // Store a pre-built object — always return this exact object
    }

    // TODO: Implement get()
    public function get(string $id): mixed {
        // Resolve the binding — throw RuntimeException if not found
        throw new \RuntimeException("Not implemented yet");
    }

    // TODO: Implement has()
    public function has(string $id): bool {
        // Return true if a binding or instance exists for $id
        return false;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// TODO Task 2 & 3: Wire and use the checkout system via SimpleContainer
// ─────────────────────────────────────────────────────────────────────────────

echo "\n=== Container wiring ===\n\n";

// $container = new SimpleContainer();
//
// $container->singleton(DatabaseInterface::class, fn($c) => new InMemoryDatabase());
// $container->singleton(CacheInterface::class,    fn($c) => new ArrayCache());
// ... etc
//
// $controller = $container->get(CheckoutController::class);
// echo $controller->handleCheckout([...]) . "\n";


// ─────────────────────────────────────────────────────────────────────────────
// TODO Task 4: Singleton assertions
// ─────────────────────────────────────────────────────────────────────────────

echo "\n=== Singleton assertions ===\n\n";

// TODO: assert two get(CheckoutController::class) calls return ===
// TODO: assert the DB in ProductCatalog === DB in InventoryChecker


// ─────────────────────────────────────────────────────────────────────────────
// TODO Task 5: Service Locator anti-pattern (BadCheckoutController)
// ─────────────────────────────────────────────────────────────────────────────

echo "\n=== Service Locator anti-pattern (BadCheckoutController) ===\n\n";

// TODO: Write BadCheckoutController that calls $container->get() inside its methods
// TODO: Add a comment explaining why this is wrong