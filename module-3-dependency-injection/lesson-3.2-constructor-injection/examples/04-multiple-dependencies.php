<?php
declare(strict_types=1);

/**
 * Example 04 — Injecting Multiple Dependencies Cleanly
 * -------------------------------------------------------
 * Real classes need several dependencies. This example shows:
 *   A. How to structure a constructor with multiple injected deps
 *   B. The SRP signal: when too many deps means the class is too big
 *   C. Constructor property promotion — the cleanest PHP 8 syntax
 *   D. A realistic multi-layer system wired at the composition root
 *
 * This also directly fixes the CheckoutService from Lesson 3.1's
 * audit (14 violations → 0 violations).
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Injecting Multiple Dependencies Cleanly            ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Interfaces — every dependency is typed against one of these
// ─────────────────────────────────────────────────────────────────────────────

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

interface EventDispatcherInterface {
    public function dispatch(string $event, array $payload = []): void;
}


// ─────────────────────────────────────────────────────────────────────────────
// Lightweight in-memory implementations (for the composition root below)
// ─────────────────────────────────────────────────────────────────────────────

class InMemoryDb implements DatabaseInterface {
    private array $products = [
        1 => ['id' => 1, 'sku' => 'WDG-001', 'name' => 'Widget Pro', 'price' => 29999, 'stock' => 50],
        2 => ['id' => 2, 'sku' => 'WDG-002', 'name' => 'Widget Lite', 'price' => 14999, 'stock' => 3],
    ];

    public function query(string $sql, array $params = []): array {
        if (str_contains($sql, 'products') && !empty($params)) {
            $id = $params[0];
            return isset($this->products[$id]) ? [$this->products[$id]] : [];
        }
        return array_values($this->products);
    }

    public function execute(string $sql, array $params = []): bool {
        echo "  [DB] " . substr($sql, 0, 60) . "\n";
        return true;
    }
}

class ArrayCache implements CacheInterface {
    private array $store = [];
    public function get(string $key): mixed {
        echo "  [CACHE] GET {$key} → " . ($this->store[$key] ?? 'null') . "\n";
        return $this->store[$key] ?? null;
    }
    public function set(string $key, mixed $value, int $ttl = 300): void {
        $this->store[$key] = $value;
        echo "  [CACHE] SET {$key}\n";
    }
}

class ConsoleLogger implements LoggerInterface {
    public function log(string $level, string $message): void {
        echo "  [{$level}] {$message}\n";
    }
}

class ConsoleMailer implements MailerInterface {
    public function send(string $to, string $subject, string $body): bool {
        echo "  [MAIL] {$to}: {$subject}\n";
        return true;
    }
}

class SimpleEventDispatcher implements EventDispatcherInterface {
    private array $listeners = [];

    public function listen(string $event, callable $handler): void {
        $this->listeners[$event][] = $handler;
    }

    public function dispatch(string $event, array $payload = []): void {
        echo "  [EVENT] {$event}\n";
        foreach ($this->listeners[$event] ?? [] as $handler) {
            $handler($payload);
        }
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// PART A — Multiple dependencies with constructor property promotion
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part A: Constructor property promotion syntax ─────\n\n";

// Pre-PHP 8.0 style — verbose, four lines of boilerplate per property
class ProductRepositoryOldStyle {
    private DatabaseInterface $db;
    private CacheInterface    $cache;
    private LoggerInterface   $logger;

    public function __construct(
        DatabaseInterface $db,
        CacheInterface    $cache,
        LoggerInterface   $logger
    ) {
        $this->db     = $db;
        $this->cache  = $cache;
        $this->logger = $logger;
    }
}

// PHP 8.0+ constructor property promotion — declare and assign in one line
class ProductRepository {
    public function __construct(
        private DatabaseInterface $db,
        private CacheInterface    $cache,
        private LoggerInterface   $logger
    ) {}  // ← empty body: promotion handles everything

    public function findById(int $id): ?array {
        $key = "product:{$id}";
        if (($cached = $this->cache->get($key)) !== null) {
            return $cached;
        }

        $this->logger->log('INFO', "DB fetch: product #{$id}");
        $rows    = $this->db->query('SELECT * FROM products WHERE id = ?', [$id]);
        $product = $rows[0] ?? null;

        if ($product) {
            $this->cache->set($key, $product);
        }
        return $product;
    }

    public function findAll(): array {
        return $this->db->query('SELECT * FROM products');
    }
}

echo "ProductRepositoryOldStyle: 12 lines for 3 properties\n";
echo "ProductRepository (promotion): 5 lines for 3 properties\n\n";
echo "Both are identical at runtime — promotion is purely a syntax shortcut.\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART B — The SRP signal: too many dependencies = too many responsibilities
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part B: SRP signal from dependency count ─────────\n\n";

// 4 dependencies — a reasonable service
class CheckoutService {
    public function __construct(
        private ProductRepository       $products,   // 1
        private DatabaseInterface       $db,         // 2
        private MailerInterface         $mailer,     // 3
        private LoggerInterface         $logger      // 4
    ) {}

    public function checkout(array $cart, string $email): array {
        $this->logger->log('INFO', "Checkout started for {$email}");
        $total = 0;

        foreach ($cart as $item) {
            $product = $this->products->findById($item['id']);
            if (!$product) {
                return ['success' => false, 'error' => "Product {$item['id']} not found"];
            }
            $total += $product['price'] * $item['qty'];
        }

        $orderId = rand(10000, 99999);
        $this->db->execute(
            'INSERT INTO orders (id, email, total) VALUES (?, ?, ?)',
            [$orderId, $email, $total]
        );

        $this->mailer->send(
            $email,
            "Order #{$orderId} Confirmed",
            "Total: R" . number_format($total / 100, 2)
        );

        $this->logger->log('INFO', "Order #{$orderId} placed. Total: R" . ($total / 100));
        return ['success' => true, 'order_id' => $orderId, 'total' => $total];
    }
}

echo "CheckoutService has 4 dependencies — reasonable for a checkout orchestrator.\n\n";

// 7+ dependencies — a warning sign
echo "A class with 7+ injected dependencies is a design signal:\n";
echo "  class GodService {\n";
echo "      public function __construct(\n";
echo "          private DatabaseInterface       \$db,\n";
echo "          private CacheInterface          \$cache,\n";
echo "          private LoggerInterface         \$logger,\n";
echo "          private MailerInterface         \$mailer,\n";
echo "          private EventDispatcherInterface \$events,\n";
echo "          private ProductRepository       \$products,\n";
echo "          private UserRepository          \$users,\n";
echo "          private PaymentGatewayInterface \$gateway\n";
echo "      ) {}\n";
echo "  }\n\n";
echo "Question: Does one class really do all 8 of these things?\n";
echo "Likely answer: It should be split into 2-3 smaller services.\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART C — A realistic multi-layer system wired at the composition root
// This directly fixes the Lesson 3.1 checkout audit (14 violations → 0)
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part C: Multi-layer system — composition root ────\n\n";

// ─── Composition root (would be index.php or bootstrap.php) ───

// Layer 1: infrastructure
$db         = new InMemoryDb();
$cache      = new ArrayCache();
$logger     = new ConsoleLogger();
$mailer     = new ConsoleMailer();
$dispatcher = new SimpleEventDispatcher();

// Register event listeners
$dispatcher->listen('order.placed', function (array $payload): void {
    echo "  [LISTENER] Order placed event: #{$payload['order_id']} for {$payload['email']}\n";
});

// Layer 2: repositories (depend on infrastructure)
$products = new ProductRepository($db, $cache, $logger);

// Layer 3: services (depend on repositories and infrastructure)
$checkout = new CheckoutService($products, $db, $mailer, $logger);

echo "Composition root: all dependencies wired. No `new` in any business class.\n\n";

// ─── Use the system ───

echo "findById(1):\n";
$p = $products->findById(1);
echo "  → {$p['name']} (R" . number_format($p['price'] / 100, 2) . ")\n";

echo "\nfindById(1) again (cache hit):\n";
$p2 = $products->findById(1);
echo "  → {$p2['name']} (from cache)\n";

echo "\ncheckout():\n";
$result = $checkout->checkout(
    [['id' => 1, 'qty' => 2], ['id' => 2, 'qty' => 1]],
    'alice@example.com'
);
echo "  → " . ($result['success'] ? "Order #{$result['order_id']} placed" : "Failed") . "\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART D — Counting violations: before vs after
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part D: Before vs after violation count ──────────\n\n";

echo "Lesson 3.1 CheckoutService (BEFORE):\n";
echo "  concrete-property violations:   4\n";
echo "  new-in-constructor violations:  3\n";
echo "  hardcoded-config violations:    1\n";
echo "  Total:                          8 violations in CheckoutService alone\n\n";

echo "This lesson's CheckoutService (AFTER):\n";
echo "  concrete-property violations:   0\n";
echo "  new-in-constructor violations:  0\n";
echo "  hardcoded-config violations:    0\n";
echo "  Total:                          0 violations\n\n";

echo "The business logic (checkout flow) is IDENTICAL.\n";
echo "Only the wiring changed — and the wiring is now in the composition root.\n";

echo "\n--- Recap ---\n";
echo "Constructor property promotion: declare and assign in one line — clean syntax.\n";
echo "3–5 dependencies: typical for a well-scoped service.\n";
echo "6+ dependencies: SRP signal — consider splitting the class.\n";
echo "Composition root: the only place with `new` calls on services.\n";
echo "Before vs after: same business logic, zero coupling violations.\n";