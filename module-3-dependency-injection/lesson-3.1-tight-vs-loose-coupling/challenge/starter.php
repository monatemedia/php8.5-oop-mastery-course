<?php
declare(strict_types=1);

/**
 * CHALLENGE STARTER — Lesson 3.1: Tight vs Loose Coupling
 * ─────────────────────────────────────────────────────────
 * Read CHALLENGE.md before touching this file.
 *
 * YOUR JOB: Read each class below and mark every coupling violation.
 * Do NOT write any fix code — audit only.
 * Do NOT look at solution.php until you have completed your audit.
 *
 * INSTRUCTIONS:
 *   1. Add a comment to each violation line: // ❌ [violation-type]: description
 *   2. Fill in the AUDIT TABLE at the bottom of this file
 *   3. Answer the TESTABILITY QUESTIONS at the bottom of this file
 *   4. Count the total violations (there are exactly 14)
 */


// ═══════════════════════════════════════════════════════════════════════
// INFRASTRUCTURE CLASSES (do not audit these — they ARE the dependencies)
// ═══════════════════════════════════════════════════════════════════════

class PostgresDb {
    private static ?PostgresDb $instance = null;

    private function __construct(private string $dsn) {
        echo "  [POSTGRES] Connected to {$dsn}\n";
    }

    public static function getInstance(string $dsn = ''): static {
        if (self::$instance === null) {
            self::$instance = new static($dsn ?: 'pgsql:host=localhost;dbname=shop');
        }
        return self::$instance;
    }

    public function query(string $sql, array $params = []): array {
        echo "  [POSTGRES] " . substr($sql, 0, 70) . "\n";
        return match(true) {
            str_contains($sql, 'products') => [
                ['id' => 1, 'name' => 'Widget Pro', 'price' => 29999, 'stock' => 50]
            ],
            str_contains($sql, 'inventory') => [['sku' => 'WDG-001', 'quantity' => 50]],
            default => []
        };
    }

    public function execute(string $sql, array $params = []): bool {
        echo "  [POSTGRES] EXEC: " . substr($sql, 0, 70) . "\n";
        return true;
    }
}

class RedisCache {
    public function __construct(private string $host, private int $port) {
        echo "  [REDIS] Connected to {$host}:{$port}\n";
    }

    public function get(string $key): mixed {
        echo "  [REDIS] GET {$key}\n";
        return null;
    }

    public function set(string $key, mixed $value, int $ttl = 60): void {
        echo "  [REDIS] SET {$key} (ttl={$ttl})\n";
    }
}

class SendGridMailer {
    public function __construct(private string $apiKey) {
        echo "  [SENDGRID] Authenticated\n";
    }

    public function send(string $to, string $subject, string $body): bool {
        echo "  [SENDGRID] To: {$to} | Subject: {$subject}\n";
        return true;
    }
}

class MonologLogger {
    public function info(string $message, array $context = []): void {
        echo "  [LOG:INFO] {$message}\n";
    }
    public function error(string $message, array $context = []): void {
        echo "  [LOG:ERROR] {$message}\n";
    }
    public function warning(string $message, array $context = []): void {
        echo "  [LOG:WARN] {$message}\n";
    }
}


// ═══════════════════════════════════════════════════════════════════════
// CLASSES TO AUDIT — find and annotate every coupling violation below
// ═══════════════════════════════════════════════════════════════════════

/**
 * ProductCatalog — fetches product data
 * Audit this class: mark every violation with a comment
 */
class ProductCatalog {
    private PostgresDb  $db;
    private RedisCache  $cache;

    public function __construct() {
        $this->db    = PostgresDb::getInstance('pgsql:host=db.prod:5432;dbname=shop');
        $this->cache = new RedisCache('redis.prod', 6379);
    }

    public function findById(int $id): ?array {
        $key    = "product_{$id}";
        $cached = $this->cache->get($key);
        if ($cached !== null) return $cached;

        $rows = $this->db->query(
            'SELECT id, name, price, stock FROM products WHERE id = $1 AND active = true',
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
            'SELECT id, name, price, stock FROM products WHERE sku = $1',
            [$sku]
        );
        return $rows[0] ?? null;
    }
}


/**
 * InventoryChecker — checks stock availability
 * Audit this class: mark every violation with a comment
 */
class InventoryChecker {
    private PostgresDb $db;

    public function __construct() {
        $this->db = PostgresDb::getInstance();
    }

    public function isAvailable(string $sku, int $quantity): bool {
        $rows = $this->db->query(
            'SELECT quantity FROM inventory WHERE sku = $1',
            [$sku]
        );
        $stock = $rows[0]['quantity'] ?? 0;
        return $stock >= $quantity;
    }

    public function reserve(string $sku, int $quantity): bool {
        return $this->db->execute(
            'UPDATE inventory SET quantity = quantity - $1 WHERE sku = $2 AND quantity >= $1',
            [$quantity, $sku]
        );
    }
}


/**
 * CheckoutService — orchestrates the checkout process
 * Audit this class: mark every violation with a comment
 */
class CheckoutService {
    private ProductCatalog  $catalog;
    private InventoryChecker $inventory;
    private SendGridMailer  $mailer;
    private MonologLogger   $logger;

    public function __construct() {
        $this->catalog   = new ProductCatalog();
        $this->inventory = new InventoryChecker();
        $this->mailer    = new SendGridMailer('SG.abc123xyz789');
        $this->logger    = new MonologLogger();
    }

    public function checkout(array $cart, string $customerEmail): array {
        $this->logger->info("Starting checkout for {$customerEmail}");

        $lineItems = [];
        $total     = 0;

        foreach ($cart as $item) {
            $product = $this->catalog->findById($item['product_id']);

            if ($product === null) {
                $this->logger->warning("Product {$item['product_id']} not found");
                return ['success' => false, 'error' => 'Product not found'];
            }

            $sku = 'WDG-' . str_pad((string)$item['product_id'], 3, '0', STR_PAD_LEFT);
            if (!$this->inventory->isAvailable($sku, $item['quantity'])) {
                $this->logger->warning("Insufficient stock for SKU {$sku}");
                return ['success' => false, 'error' => 'Insufficient stock'];
            }

            $this->inventory->reserve($sku, $item['quantity']);
            $lineTotal  = $product['price'] * $item['quantity'];
            $total     += $lineTotal;
            $lineItems[] = [
                'name'     => $product['name'],
                'quantity' => $item['quantity'],
                'price'    => $product['price'],
                'subtotal' => $lineTotal,
            ];
        }

        $orderId = rand(10000, 99999);

        $this->mailer->send(
            $customerEmail,
            'Order Confirmed #' . $orderId,
            "Thank you! Your order total is R" . number_format($total / 100, 2)
        );

        $this->logger->info("Checkout complete. Order #{$orderId}, Total: R" . ($total / 100));

        return [
            'success'   => true,
            'order_id'  => $orderId,
            'total'     => $total,
            'line_items'=> $lineItems,
        ];
    }

    public function getProductDetails(array $product): string {
        return "{$product['name']} — R" . number_format($product['price'] / 100, 2);
    }
}


/**
 * CheckoutController — HTTP layer
 * Audit this class: mark every violation with a comment
 */
class CheckoutController {
    private CheckoutService $service;
    private MonologLogger   $logger;

    public function __construct() {
        $this->service = new CheckoutService();
        $this->logger  = new MonologLogger();
    }

    public function handleCheckout(array $request): string {
        $this->logger->info("Checkout request received");

        $cart  = $request['cart']  ?? [];
        $email = $request['email'] ?? 'guest@example.com';

        if (empty($cart)) {
            return json_encode(['error' => 'Cart is empty']);
        }

        $result = $this->service->checkout($cart, $email);
        return json_encode($result, JSON_PRETTY_PRINT);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// RUN THE CODE (shows what happens when you instantiate the controller)
// ─────────────────────────────────────────────────────────────────────────────

echo "Creating CheckoutController:\n";
$controller = new CheckoutController();

echo "\nHandling checkout request:\n";
$result = $controller->handleCheckout([
    'cart'  => [['product_id' => 1, 'quantity' => 2]],
    'email' => 'alice@example.com',
]);
echo "\nResponse:\n" . $result . "\n";


// ─────────────────────────────────────────────────────────────────────────────
// ╔══════════════════════════════════════════════════════════════════════╗
// ║  YOUR AUDIT — fill this in (Task 1)                                ║
// ╚══════════════════════════════════════════════════════════════════════╝
//
// Fill in the table below. Violation types:
//   new-in-constructor | new-in-method | concrete-property | singleton-access
//   static-call | hardcoded-config | magic-value | god-parameter
//
// AUDIT TABLE:
// ┌──────────────────────┬──────────┬───────────────────────┬──────────────────────────────────┐
// │ Class                │ ~Line    │ Violation type        │ Description                      │
// ├──────────────────────┼──────────┼───────────────────────┼──────────────────────────────────┤
// │ ProductCatalog       │          │                       │                                  │
// │ ProductCatalog       │          │                       │                                  │
// │ ProductCatalog       │          │                       │                                  │
// │ ProductCatalog       │          │                       │                                  │
// │ InventoryChecker     │          │                       │                                  │
// │ InventoryChecker     │          │                       │                                  │
// │ CheckoutService      │          │                       │                                  │
// │ CheckoutService      │          │                       │                                  │
// │ CheckoutService      │          │                       │                                  │
// │ CheckoutService      │          │                       │                                  │
// │ CheckoutService      │          │                       │                                  │
// │ CheckoutController   │          │                       │                                  │
// │ CheckoutController   │          │                       │                                  │
// │ CheckoutController   │          │                       │                                  │
// └──────────────────────┴──────────┴───────────────────────┴──────────────────────────────────┘
//
// TESTABILITY QUESTIONS (Task 2):
//
// ProductCatalog:
//   Q1 Can it be instantiated without real infrastructure? YES / NO
//   Q2 Can findById() be tested without Redis/Postgres?   YES / NO
//   Q3 Lines to edit to switch from Postgres to MySQL:    ___
//
// InventoryChecker:
//   Q1 Can it be instantiated without real infrastructure? YES / NO
//   Q2 Can isAvailable() be tested without Postgres?       YES / NO
//   Q3 Lines to edit to switch from Postgres to MySQL:     ___
//
// CheckoutService:
//   Q1 Can it be instantiated without real infrastructure? YES / NO
//   Q2 Can checkout() be tested without network/DB?        YES / NO
//   Q3 Lines to edit to switch from SendGrid to Mailgun:   ___
//
// CheckoutController:
//   Q1 Can it be instantiated without real infrastructure? YES / NO
//   Q2 Can handleCheckout() be tested in isolation?        YES / NO
//
// TOTAL VIOLATIONS (Task 3): ___  (should be 14)
// ─────────────────────────────────────────────────────────────────────────────