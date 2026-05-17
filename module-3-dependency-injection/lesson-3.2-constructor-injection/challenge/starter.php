<?php
declare(strict_types=1);

/**
 * CHALLENGE STARTER — Lesson 3.2: Constructor Injection
 * ───────────────────────────────────────────────────────
 * Read CHALLENGE.md before touching this file.
 *
 * This is the same checkout system from Lesson 3.1's audit — 14 violations.
 * Your job: fix every violation using constructor injection.
 *
 * Do NOT look at solution.php until you have made a genuine attempt.
 *
 * Steps:
 *   1. Define interfaces (DatabaseInterface, CacheInterface, etc.)
 *   2. Make existing classes implement them
 *   3. Refactor each class to accept dependencies via constructor
 *   4. Wire at the composition root
 *   5. Add a test wiring with anonymous class stubs
 */


// ─────────────────────────────────────────────────────────────────────────────
// TODO Task 1: Define your interfaces here
// DatabaseInterface, CacheInterface, ProductRepositoryInterface,
// InventoryInterface, MailerInterface, LoggerInterface
// ─────────────────────────────────────────────────────────────────────────────


// ─────────────────────────────────────────────────────────────────────────────
// INFRASTRUCTURE (these are the concrete implementations — update to implement
// your interfaces, but do NOT change their internal logic)
// ─────────────────────────────────────────────────────────────────────────────

class PostgresDb {
    private static ?PostgresDb $instance = null;

    private function __construct(private string $dsn) {}

    public static function getInstance(string $dsn = ''): static {
        if (self::$instance === null) {
            self::$instance = new static($dsn ?: 'pgsql:host=localhost;dbname=shop');
        }
        return self::$instance;
    }

    public function query(string $sql, array $params = []): array {
        echo "  [DB] " . substr($sql, 0, 60) . "\n";
        return match(true) {
            str_contains($sql, 'products') && !empty($params) => [
                ['id' => $params[0], 'sku' => 'WDG-001', 'name' => 'Widget Pro',
                 'price' => 29999, 'stock' => 50]
            ],
            str_contains($sql, 'inventory') => [['sku' => 'WDG-001', 'quantity' => 50]],
            default => []
        };
    }

    public function execute(string $sql, array $params = []): bool {
        echo "  [DB] EXEC: " . substr($sql, 0, 60) . "\n";
        return true;
    }
}

class RedisCache {
    private array $store = [];

    public function __construct(private string $host, private int $port) {}

    public function get(string $key): mixed {
        $val = $this->store[$key] ?? null;
        echo "  [CACHE] " . ($val ? "HIT" : "MISS") . ": {$key}\n";
        return $val;
    }

    public function set(string $key, mixed $value, int $ttl = 60): void {
        $this->store[$key] = $value;
        echo "  [CACHE] SET: {$key}\n";
    }
}

class SendGridMailer {  // TODO: implement MailerInterface
    public function __construct(private string $apiKey) {}

    public function send(string $to, string $subject, string $body): bool {
        echo "  [MAIL] To: {$to} | Subject: {$subject}\n";
        return true;
    }
}

class MonologLogger {   // TODO: implement LoggerInterface
    public function log(string $level, string $message): void {
        echo "  [{$level}] {$message}\n";
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// CLASSES TO REFACTOR
// Remove all `new` and singleton calls from each constructor.
// Replace concrete property types with interface types.
// ─────────────────────────────────────────────────────────────────────────────

class ProductCatalog {  // TODO: implement ProductRepositoryInterface
    // TODO: change property types to interfaces
    private PostgresDb  $db;
    private RedisCache  $cache;

    public function __construct() {
        // TODO: Remove these — accept $db and $cache via constructor instead
        $this->db    = PostgresDb::getInstance('pgsql:host=db.prod:5432;dbname=shop');
        $this->cache = new RedisCache('redis.prod', 6379);
    }

    // Keep this method — only change property access if needed
    public function findById(int $id): ?array {
        $key    = "product_{$id}";
        $cached = $this->cache->get($key);
        if ($cached !== null) return $cached;

        $rows = $this->db->query(
            'SELECT id, sku, name, price, stock FROM products WHERE id = $1',
            [$id]
        );
        $product = $rows[0] ?? null;
        if ($product) {
            $this->cache->set($key, $product, 120);
        }
        return $product;
    }

    public function findBySku(string $sku): ?array {
        $rows = $this->db->query(
            'SELECT id, sku, name, price, stock FROM products WHERE sku = $1',
            [$sku]
        );
        return $rows[0] ?? null;
    }
}


class InventoryChecker {    // TODO: implement InventoryInterface
    // TODO: change property type to an interface
    private PostgresDb $db;

    public function __construct() {
        // TODO: Remove — accept $db via constructor instead
        $this->db = PostgresDb::getInstance();
    }

    public function isAvailable(string $sku, int $quantity): bool {
        echo "  [INVENTORY] Checking {$sku} × {$quantity}\n";
        $rows  = $this->db->query('SELECT quantity FROM inventory WHERE sku = $1', [$sku]);
        $stock = $rows[0]['quantity'] ?? 0;
        return $stock >= $quantity;
    }

    public function reserve(string $sku, int $quantity): bool {
        echo "  [INVENTORY] Reserving {$sku} × {$quantity}\n";
        return $this->db->execute(
            'UPDATE inventory SET quantity = quantity - $1 WHERE sku = $2 AND quantity >= $1',
            [$quantity, $sku]
        );
    }
}


class CheckoutService {
    // TODO: change all property types to interfaces
    private ProductCatalog   $catalog;
    private InventoryChecker $inventory;
    private SendGridMailer   $mailer;
    private MonologLogger    $logger;

    public function __construct() {
        // TODO: Remove all four `new` calls — accept via constructor instead
        $this->catalog   = new ProductCatalog();
        $this->inventory = new InventoryChecker();
        $this->mailer    = new SendGridMailer('SG.abc123xyz789');
        $this->logger    = new MonologLogger();
    }

    public function checkout(array $cart, string $customerEmail): array {
        $this->logger->log('INFO', "Starting checkout for {$customerEmail}");
        $total     = 0;
        $lineItems = [];

        foreach ($cart as $item) {
            $product = $this->catalog->findById($item['product_id']);
            if ($product === null) {
                return ['success' => false, 'error' => 'Product not found'];
            }

            $sku = $product['sku'];
            if (!$this->inventory->isAvailable($sku, $item['quantity'])) {
                return ['success' => false, 'error' => 'Insufficient stock'];
            }

            $this->inventory->reserve($sku, $item['quantity']);
            $lineTotal   = $product['price'] * $item['quantity'];
            $total      += $lineTotal;
            $lineItems[] = ['name' => $product['name'], 'quantity' => $item['quantity'],
                            'price' => $product['price'], 'subtotal' => $lineTotal];
        }

        $orderId = rand(10000, 99999);
        $this->mailer->send(
            $customerEmail,
            "Order Confirmed #{$orderId}",
            "Total: R" . number_format($total / 100, 2)
        );
        $this->logger->log('INFO', "Checkout complete. Order #{$orderId}");

        return ['success' => true, 'order_id' => $orderId, 'total' => $total, 'items' => $lineItems];
    }
}


class CheckoutController {
    // TODO: change property types to interfaces
    private CheckoutService $service;
    private MonologLogger   $logger;

    public function __construct() {
        // TODO: Remove — accept $service and $logger via constructor instead
        $this->service = new CheckoutService();
        $this->logger  = new MonologLogger();
    }

    public function handleCheckout(array $request): string {
        $this->logger->log('INFO', "Checkout request received");
        $cart  = $request['cart']  ?? [];
        $email = $request['email'] ?? '';

        if (empty($cart)) {
            return json_encode(['error' => 'Cart is empty']);
        }

        $result = $this->service->checkout($cart, $email);
        return json_encode($result, JSON_PRETTY_PRINT);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// CURRENT (tightly coupled) usage — replace this with a composition root
// ─────────────────────────────────────────────────────────────────────────────

echo "=== Current (tightly coupled) output ===\n\n";

$controller = new CheckoutController();
$result = $controller->handleCheckout([
    'cart'  => [['product_id' => 1, 'quantity' => 2]],
    'email' => 'alice@example.com',
]);
echo "\n" . $result . "\n";


// ─────────────────────────────────────────────────────────────────────────────
// TODO Task 7: Replace the above with a composition root
// Wire all dependencies explicitly here
// ─────────────────────────────────────────────────────────────────────────────

// echo "\n=== Production wiring ===\n\n";
// $db       = new PostgresDb::getInstance('pgsql:host=db.prod:5432;dbname=shop');
// $cache    = new RedisCache('redis.prod', 6379);
// ... etc


// ─────────────────────────────────────────────────────────────────────────────
// TODO Task 8: Add a test wiring using anonymous class stubs
// ─────────────────────────────────────────────────────────────────────────────

// echo "\n=== Test wiring (anonymous class stubs) ===\n\n";
// $fakeDb    = new class implements DatabaseInterface { ... };
// $fakeCache = new class implements CacheInterface { ... };
// ... etc