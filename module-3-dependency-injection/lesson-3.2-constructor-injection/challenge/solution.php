<?php
declare(strict_types=1);

/**
 * CHALLENGE SOLUTION — Lesson 3.2: Constructor Injection
 * ───────────────────────────────────────────────────────
 * ⚠️  Only open this file after completing starter.php yourself.
 *
 * Key things to compare in your solution:
 *   1. Six interfaces defined — all dependency types are abstractions
 *   2. All four classes have zero `new` on services in their bodies
 *   3. All four classes have interface-typed constructor parameters
 *   4. Composition root at the bottom wires everything explicitly
 *   5. Test wiring uses anonymous class stubs — no real infrastructure
 */


// ═══════════════════════════════════════════════════════════════════════
// Task 1 — Interfaces
// ═══════════════════════════════════════════════════════════════════════

interface DatabaseInterface {
    public function query(string $sql, array $params = []): array;
    public function execute(string $sql, array $params = []): bool;
}

interface CacheInterface {
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, int $ttl = 60): void;
}

interface ProductRepositoryInterface {
    public function findById(int $id): ?array;
    public function findBySku(string $sku): ?array;
}

interface InventoryInterface {
    public function isAvailable(string $sku, int $quantity): bool;
    public function reserve(string $sku, int $quantity): bool;
}

interface MailerInterface {
    public function send(string $to, string $subject, string $body): bool;
}

interface LoggerInterface {
    public function log(string $level, string $message): void;
}


// ═══════════════════════════════════════════════════════════════════════
// Task 2 — Infrastructure: concrete implementations of the interfaces
// These classes implement the interfaces — internal logic unchanged
// ═══════════════════════════════════════════════════════════════════════

class PostgresDb implements DatabaseInterface {
    // Removed singleton — now a plain instantiable class
    // The composition root creates it with the right DSN
    public function __construct(private string $dsn) {}

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

class RedisCache implements CacheInterface {
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

class SendGridMailer implements MailerInterface {
    public function __construct(private string $apiKey) {}

    public function send(string $to, string $subject, string $body): bool {
        echo "  [MAIL] To: {$to} | Subject: {$subject}\n";
        return true;
    }
}

class MonologLogger implements LoggerInterface {
    public function log(string $level, string $message): void {
        echo "  [{$level}] {$message}\n";
    }
}


// ═══════════════════════════════════════════════════════════════════════
// Tasks 3 & 4 — Refactored ProductCatalog and InventoryChecker
// Zero `new` calls. Zero concrete property types. Zero singleton access.
// ═══════════════════════════════════════════════════════════════════════

class ProductCatalog implements ProductRepositoryInterface {
    // ✅ All interface types
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

class InventoryChecker implements InventoryInterface {
    // ✅ Interface type
    public function __construct(
        private DatabaseInterface $db
    ) {}

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


// ═══════════════════════════════════════════════════════════════════════
// Task 5 — Refactored CheckoutService
// ═══════════════════════════════════════════════════════════════════════

class CheckoutService {
    // ✅ All interface types — zero concrete class names
    public function __construct(
        private ProductRepositoryInterface $catalog,
        private InventoryInterface         $inventory,
        private MailerInterface            $mailer,
        private LoggerInterface            $logger
    ) {}

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


// ═══════════════════════════════════════════════════════════════════════
// Task 6 — Refactored CheckoutController
// ═══════════════════════════════════════════════════════════════════════

class CheckoutController {
    // ✅ Interface-typed logger. CheckoutService is concrete but itself fully injected.
    public function __construct(
        private CheckoutService $service,
        private LoggerInterface $logger
    ) {}

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


// ═══════════════════════════════════════════════════════════════════════
// Task 7 — Composition root (production wiring)
// This is the ONLY place where `new` is called on services.
// All coupling violations are resolved — 14 → 0.
// ═══════════════════════════════════════════════════════════════════════

echo "=== Production wiring ===\n\n";

// Layer 1: infrastructure (concrete implementations chosen here)
$db     = new PostgresDb('pgsql:host=db.prod:5432;dbname=shop');
$cache  = new RedisCache('redis.prod', 6379);
$mailer = new SendGridMailer('SG.abc123xyz789');
$logger = new MonologLogger();

// Layer 2: repositories (inject infrastructure)
$catalog   = new ProductCatalog($db, $cache, $logger);
$inventory = new InventoryChecker($db);

// Layer 3: services (inject repositories + infrastructure)
$service = new CheckoutService($catalog, $inventory, $mailer, $logger);

// Layer 4: HTTP layer (inject services)
$controller = new CheckoutController($service, $logger);

$result = $controller->handleCheckout([
    'cart'  => [['product_id' => 1, 'quantity' => 2]],
    'email' => 'alice@example.com',
]);
echo "\n" . $result . "\n";


// ═══════════════════════════════════════════════════════════════════════
// Task 8 — Test wiring (anonymous class stubs, zero infrastructure)
// ═══════════════════════════════════════════════════════════════════════

echo "\n=== Test wiring (anonymous class stubs — no infrastructure) ===\n\n";

// Spy logger — captures all entries
$testLogger = new class implements LoggerInterface {
    public array $entries = [];
    public function log(string $level, string $message): void {
        $this->entries[] = compact('level', 'message');
        echo "  [TEST] Logger: {$level} — {$message}\n";
    }
};

// Fake DB — returns controlled data
$fakeDb = new class implements DatabaseInterface {
    public function query(string $sql, array $params = []): array {
        return match(true) {
            str_contains($sql, 'products') => [
                ['id' => 1, 'sku' => 'WDG-001', 'name' => 'Widget Pro', 'price' => 29999, 'stock' => 50]
            ],
            str_contains($sql, 'inventory') => [['sku' => 'WDG-001', 'quantity' => 50]],
            default => []
        };
    }
    public function execute(string $sql, array $params = []): bool { return true; }
};

// Null cache — always misses, keeps test predictable
$fakeCache = new class implements CacheInterface {
    public function get(string $key): mixed  { return null; }
    public function set(string $key, mixed $value, int $ttl = 60): void {}
};

// Spy mailer — records sends
$testMailer = new class implements MailerInterface {
    public array $sent = [];
    public function send(string $to, string $subject, string $body): bool {
        $this->sent[] = compact('to', 'subject', 'body');
        return true;
    }
};

// Fake inventory — always available
$fakeInventory = new class implements InventoryInterface {
    public function isAvailable(string $sku, int $quantity): bool  { return true; }
    public function reserve(string $sku, int $quantity): bool      { return true; }
};

// Wire with fakes
$testCatalog    = new ProductCatalog($fakeDb, $fakeCache, $testLogger);
$testService    = new CheckoutService($testCatalog, $fakeInventory, $testMailer, $testLogger);
$testController = new CheckoutController($testService, $testLogger);

$testResult = $testController->handleCheckout([
    'cart'  => [['product_id' => 1, 'quantity' => 2]],
    'email' => 'alice@example.com',
]);

echo "\nTest result:\n" . $testResult . "\n";

// Assertions
$decoded = json_decode($testResult, true);
$checks  = [
    'checkout succeeded'          => $decoded['success'] === true,
    'mailer was called once'      => count($testMailer->sent) === 1,
    'email sent to alice'         => $testMailer->sent[0]['to'] === 'alice@example.com',
    'logger captured INFO entries'=> count(array_filter(
        $testLogger->entries, fn($e) => $e['level'] === 'INFO'
    )) >= 1,
];

echo "\nTest assertions:\n";
foreach ($checks as $label => $pass) {
    echo "  " . ($pass ? '✓' : '✗') . " {$label}\n";
}

// ─────────────────────────────────────────────────────────────────────────────
// SELF-REVIEW CHECKLIST
// ─────────────────────────────────────────────────────────────────────────────
echo "\n--- Self-review checklist ---\n";
echo "[ ] Six interfaces defined (DatabaseInterface, CacheInterface, + 4 domain ones)?\n";
echo "[ ] ProductCatalog takes DatabaseInterface, CacheInterface, LoggerInterface?\n";
echo "[ ] InventoryChecker takes DatabaseInterface only?\n";
echo "[ ] CheckoutService takes four interface-typed parameters?\n";
echo "[ ] CheckoutController takes CheckoutService and LoggerInterface?\n";
echo "[ ] Composition root is the only place with `new` calls on services?\n";
echo "[ ] Test wiring uses anonymous class stubs — no real infrastructure?\n";
echo "[ ] Total coupling violations in the four classes: 0?\n";