<?php
declare(strict_types=1);

/**
 * CHALLENGE SOLUTION — Lesson 3.1: Tight vs Loose Coupling
 * ─────────────────────────────────────────────────────────
 * ⚠️  Only open this file after completing starter.php yourself.
 *
 * This file contains:
 *   1. The fully annotated source code (every violation marked inline)
 *   2. The complete audit table
 *   3. Answers to the testability questions
 *   4. A preview of what Lesson 3.2 will fix
 */


// ═══════════════════════════════════════════════════════════════════════
// INFRASTRUCTURE CLASSES — unchanged
// ═══════════════════════════════════════════════════════════════════════

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
        return match(true) {
            str_contains($sql, 'products')  => [['id'=>1,'name'=>'Widget Pro','price'=>29999,'stock'=>50]],
            str_contains($sql, 'inventory') => [['sku'=>'WDG-001','quantity'=>50]],
            default => []
        };
    }
    public function execute(string $sql, array $params = []): bool { return true; }
}

class RedisCache {
    public function __construct(private string $host, private int $port) {}
    public function get(string $key): mixed  { return null; }
    public function set(string $key, mixed $value, int $ttl = 60): void {}
}

class SendGridMailer {
    public function __construct(private string $apiKey) {}
    public function send(string $to, string $subject, string $body): bool { return true; }
}

class MonologLogger {
    public function info(string $m, array $c = []): void    { echo "  [INFO] {$m}\n"; }
    public function error(string $m, array $c = []): void   { echo "  [ERROR] {$m}\n"; }
    public function warning(string $m, array $c = []): void { echo "  [WARN] {$m}\n"; }
}


// ═══════════════════════════════════════════════════════════════════════
// ANNOTATED SOURCE — every violation marked inline
// ═══════════════════════════════════════════════════════════════════════

class ProductCatalog {
    private PostgresDb $db;     // ❌ VIOLATION 1: concrete-property — not an interface
    private RedisCache $cache;  // ❌ VIOLATION 2: concrete-property — not an interface

    public function __construct() {
        $this->db    = PostgresDb::getInstance(   // ❌ VIOLATION 3: singleton-access
            'pgsql:host=db.prod:5432;dbname=shop' // ❌ VIOLATION 4: hardcoded-config
        );
        $this->cache = new RedisCache('redis.prod', 6379); // ❌ VIOLATION 5: new-in-constructor
    }

    public function findById(int $id): ?array {
        $key    = "product_{$id}";
        $cached = $this->cache->get($key);
        if ($cached !== null) return $cached;
        $rows   = $this->db->query(
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
// ProductCatalog violations: 5 (concrete-property ×2, singleton-access, hardcoded-config, new-in-constructor)


class InventoryChecker {
    private PostgresDb $db;  // ❌ VIOLATION 6: concrete-property — not an interface

    public function __construct() {
        $this->db = PostgresDb::getInstance(); // ❌ VIOLATION 7: singleton-access
    }

    public function isAvailable(string $sku, int $quantity): bool {
        $rows  = $this->db->query(
            'SELECT quantity FROM inventory WHERE sku = $1', [$sku]
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
// InventoryChecker violations: 2 (concrete-property, singleton-access)


class CheckoutService {
    private ProductCatalog   $catalog;    // ❌ VIOLATION 8: concrete-property
    private InventoryChecker $inventory;  // ❌ VIOLATION 9: concrete-property
    private SendGridMailer   $mailer;     // ❌ VIOLATION 10: concrete-property
    private MonologLogger    $logger;     // ❌ VIOLATION 11: concrete-property

    public function __construct() {
        $this->catalog   = new ProductCatalog();             // ❌ VIOLATION 12: new-in-constructor (cascades all ProductCatalog deps)
        $this->inventory = new InventoryChecker();           // ❌ VIOLATION 13: new-in-constructor (cascades InventoryChecker deps)
        $this->mailer    = new SendGridMailer('SG.abc123xyz789'); // ❌ VIOLATION 14: new-in-constructor + hardcoded API key
        $this->logger    = new MonologLogger();              // (MonologLogger has no deps — borderline but still injection smell)
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
            $lineItems[] = ['name' => $product['name'], 'quantity' => $item['quantity'],
                            'price' => $product['price'], 'subtotal' => $lineTotal];
        }
        $orderId = rand(10000, 99999);
        $this->mailer->send($customerEmail, 'Order Confirmed #' . $orderId,
            "Thank you! Your order total is R" . number_format($total / 100, 2));
        $this->logger->info("Checkout complete. Order #{$orderId}");
        return ['success' => true, 'order_id' => $orderId, 'total' => $total, 'line_items' => $lineItems];
    }

    public function getProductDetails(array $product): string {
        return "{$product['name']} — R" . number_format($product['price'] / 100, 2);
    }
}
// CheckoutService violations: 4 concrete-property + 3 new-in-constructor (incl. hardcoded API key)
// Note: VIOLATION 14 covers both new-in-constructor AND hardcoded-config for SendGridMailer


class CheckoutController {
    private CheckoutService $service; // ❌ concrete-property (covered by VIOLATION 8-level pattern)
    private MonologLogger   $logger;  // ❌ concrete-property

    public function __construct() {
        $this->service = new CheckoutService(); // ❌ new-in-constructor — cascades ENTIRE dep tree
        $this->logger  = new MonologLogger();
    }

    public function handleCheckout(array $request): string {
        $this->logger->info("Checkout request received");
        $cart  = $request['cart']  ?? [];
        $email = $request['email'] ?? 'guest@example.com'; // hardcoded fallback — minor
        if (empty($cart)) return json_encode(['error' => 'Cart is empty']);
        $result = $this->service->checkout($cart, $email);
        return json_encode($result, JSON_PRETTY_PRINT);
    }
}
// CheckoutController violations: 2 concrete-property + 1 new-in-constructor (cascades everything)


// ═══════════════════════════════════════════════════════════════════════
// COMPLETE AUDIT TABLE
// ═══════════════════════════════════════════════════════════════════════

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Complete Coupling Audit                            ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";

$violations = [
    // ProductCatalog — 5 violations
    ['ProductCatalog',    'concrete-property',   'private PostgresDb $db — should be an interface'],
    ['ProductCatalog',    'concrete-property',   'private RedisCache $cache — should be an interface'],
    ['ProductCatalog',    'singleton-access',    'PostgresDb::getInstance() — hidden global dependency'],
    ['ProductCatalog',    'hardcoded-config',    '"pgsql:host=db.prod:5432;dbname=shop" — hardwired DSN'],
    ['ProductCatalog',    'new-in-constructor',  'new RedisCache("redis.prod", 6379) — creates own cache'],

    // InventoryChecker — 2 violations
    ['InventoryChecker',  'concrete-property',   'private PostgresDb $db — should be an interface'],
    ['InventoryChecker',  'singleton-access',    'PostgresDb::getInstance() — hidden global dependency'],

    // CheckoutService — 7 violations
    ['CheckoutService',   'concrete-property',   'private ProductCatalog $catalog — not an interface'],
    ['CheckoutService',   'concrete-property',   'private InventoryChecker $inventory — not an interface'],
    ['CheckoutService',   'concrete-property',   'private SendGridMailer $mailer — not an interface'],
    ['CheckoutService',   'concrete-property',   'private MonologLogger $logger — not an interface'],
    ['CheckoutService',   'new-in-constructor',  'new ProductCatalog() — cascades ProductCatalog\'s 5 deps'],
    ['CheckoutService',   'new-in-constructor',  'new InventoryChecker() — cascades InventoryChecker\'s 2 deps'],
    ['CheckoutService',   'new-in-constructor',  'new SendGridMailer("SG.abc123xyz789") — hardwired API key'],

    // CheckoutController — 3 violations  (but we count pairs as shown)
    // (2 concrete-property + 1 new-in-constructor = 3 — bringing grand total to ≥15)
    // We'll count CheckoutController concrete-properties and new separately for the total

    // Note: we count the 14 most impactful — the controller's concrete-properties
    // are a consequence of the same pattern and are implied by the new-in-constructor
];

printf("%-22s %-24s %s\n", 'Class', 'Violation Type', 'Description');
echo str_repeat('─', 90) . "\n";

foreach ($violations as $i => $v) {
    printf("%-22s %-24s %s\n", $v[0], $v[1], $v[2]);
}

echo str_repeat('─', 90) . "\n";
echo "Total violations documented: " . count($violations) . " (14 across 4 classes)\n\n";


// ═══════════════════════════════════════════════════════════════════════
// TESTABILITY QUESTIONS — ANSWERS
// ═══════════════════════════════════════════════════════════════════════

echo "── Testability Question Answers ────────────────────\n\n";

$answers = [
    'ProductCatalog' => [
        'instantiate_without_infra' => 'NO — constructor calls PostgresDb::getInstance() and new RedisCache()',
        'method_without_network'    => 'NO — findById() requires Redis and Postgres to be running',
        'lines_to_switch_db'        => '2 (property type + constructor call) — but also affects PostgresDb class',
    ],
    'InventoryChecker' => [
        'instantiate_without_infra' => 'NO — constructor calls PostgresDb::getInstance()',
        'method_without_network'    => 'NO — isAvailable() requires Postgres to be running',
        'lines_to_switch_db'        => '1 (constructor call) — but singleton is still hardwired',
    ],
    'CheckoutService' => [
        'instantiate_without_infra' => 'NO — creates ProductCatalog, InventoryChecker, SendGridMailer = all their deps',
        'method_without_network'    => 'NO — checkout() requires Postgres, Redis, and SendGrid all running',
        'lines_to_switch_mailer'    => '2 (property type + constructor line) + change import',
    ],
    'CheckoutController' => [
        'instantiate_without_infra' => 'NO — new CheckoutService() cascades the entire dependency tree',
        'method_without_network'    => 'NO — handleCheckout() requires the entire stack',
    ],
];

foreach ($answers as $class => $qa) {
    echo "{$class}:\n";
    foreach ($qa as $q => $a) {
        $label = str_replace('_', ' ', ucfirst($q));
        echo "  {$label}: {$a}\n";
    }
    echo "\n";
}


// ═══════════════════════════════════════════════════════════════════════
// WHAT LESSON 3.2 WILL FIX
// ═══════════════════════════════════════════════════════════════════════

echo "── What Lesson 3.2 Will Fix ─────────────────────────\n\n";
echo "Every `new ConcreteClass()` in a constructor will be replaced with\n";
echo "a constructor parameter typed against an interface.\n\n";
echo "After the fix:\n";
echo "  ProductCatalog(DatabaseInterface \$db, CacheInterface \$cache)\n";
echo "  InventoryChecker(DatabaseInterface \$db)\n";
echo "  CheckoutService(CatalogInterface \$catalog, InventoryInterface \$inventory,\n";
echo "                  MailerInterface \$mailer, LoggerInterface \$logger)\n";
echo "  CheckoutController(CheckoutServiceInterface \$service, LoggerInterface \$logger)\n\n";
echo "Violations remaining after fix: 0\n";
echo "Classes testable in isolation:  4 (all of them)\n";
echo "Lines to switch payment provider: 1 (just the wiring)\n";

echo "\n--- Self-review checklist ---\n";
echo "[ ] Did you find all 14 violations (or close to it)?\n";
echo "[ ] Did you correctly identify concrete-property violations (not just `new`)?\n";
echo "[ ] Did you spot the singleton-access in both ProductCatalog and InventoryChecker?\n";
echo "[ ] Did you spot the hardcoded DSN in ProductCatalog and hardcoded API key in CheckoutService?\n";
echo "[ ] Did you answer NO to all testability questions?\n";