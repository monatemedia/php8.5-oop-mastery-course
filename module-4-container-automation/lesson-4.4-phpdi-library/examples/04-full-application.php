<?php
declare(strict_types=1);

/**
 * Example 04 — Full Application: Wiring the Complete Module 3 System
 * --------------------------------------------------------------------
 * This is the payoff example for Module 4. The complete checkout system
 * from Module 3 is wired using PHP-DI with a proper definitions structure.
 *
 * What this example demonstrates:
 *   A. Structured definitions file (simulated inline)
 *   B. Full graph resolution — controller through to database
 *   C. Verifying singleton sharing across the graph
 *   D. Test wiring — replacing real implementations with stubs
 *   E. The Config vs Core boundary (Rule 1) enforced throughout
 *
 * Course Philosophy Rule 1: Config at the entry point.
 * Every getenv(), DSN, and implementation decision lives in the definitions.
 * The service classes have no knowledge of which environment they run in.
 *
 * Requires: composer require php-di/php-di
 */

require __DIR__ . '/../../../../vendor/autoload.php';

use DI\ContainerBuilder;
use function DI\autowire;
use function DI\factory;

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Full Application — Module 3 System via PHP-DI      ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// The complete Module 3 checkout system (interfaces + implementations)
// ─────────────────────────────────────────────────────────────────────────────

interface DatabaseInterface {
    public function query(string $sql, array $params = []): array;
    public function execute(string $sql, array $params = []): bool;
    public function getInstanceId(): string;
}
interface CacheInterface {
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, int $ttl = 300): void;
}
interface LoggerInterface {
    public function log(string $level, string $message): void;
    public function getInstanceId(): string;
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
        1 => ['id' => 1, 'sku' => 'WDG-001', 'name' => 'Widget Pro',  'price' => 29999],
        2 => ['id' => 2, 'sku' => 'WDG-002', 'name' => 'Widget Lite', 'price' => 14999],
    ];
    private array $inventory = ['WDG-001' => 50, 'WDG-002' => 5];
    private array $orders    = [];
    private string $id;

    public function __construct() {
        $this->id = substr(md5(uniqid()), 0, 6);
        echo "  [NEW DB #{$this->id}]\n";
    }
    public function getInstanceId(): string { return $this->id; }
    public function query(string $sql, array $params = []): array {
        echo "  [DB#{$this->id}] query\n";
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
        echo "  [DB#{$this->id}] execute\n";
        if (str_contains($sql, 'inventory') && count($params) >= 2) {
            $this->inventory[$params[1]] = max(0, ($this->inventory[$params[1]] ?? 0) - $params[0]);
        }
        if (str_contains($sql, 'orders')) {
            $this->orders[] = $params;
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
    public function set(string $key, mixed $value, int $ttl = 300): void {
        $this->store[$key] = $value;
        echo "  [CACHE] SET: {$key}\n";
    }
}

class ConsoleLogger implements LoggerInterface {
    private string $id;
    public function __construct() {
        $this->id = substr(md5(uniqid()), 0, 6);
        echo "  [NEW LOGGER #{$this->id}]\n";
    }
    public function log(string $level, string $message): void {
        echo "  [{$level}] {$message}\n";
    }
    public function getInstanceId(): string { return $this->id; }
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
    public function getDb(): DatabaseInterface     { return $this->db; }
    public function getLogger(): LoggerInterface   { return $this->logger; }

    public function findById(int $id): ?array {
        $key    = "product_{$id}";
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
        $rows  = $this->db->query('SELECT quantity FROM inventory WHERE sku = ?', [$sku]);
        return ($rows[0]['quantity'] ?? 0) >= $quantity;
    }
    public function reserve(string $sku, int $quantity): bool {
        echo "  [INVENTORY] Reserving {$sku} × {$quantity}\n";
        return $this->db->execute(
            'UPDATE inventory SET quantity = quantity - ? WHERE sku = ?',
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
    public function getLogger(): LoggerInterface { return $this->logger; }

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
        $this->mailer->send($email, "Order #{$orderId} Confirmed", "Total: R" . ($total / 100));
        $this->logger->log('INFO', "Order #{$orderId} placed");
        return ['success' => true, 'order_id' => $orderId, 'total' => $total, 'items' => $items];
    }
}

class CheckoutController {
    public function __construct(
        private CheckoutService $service,
        private LoggerInterface $logger
    ) {}
    public function getLogger(): LoggerInterface { return $this->logger; }

    public function handle(array $request): string {
        $this->logger->log('INFO', "Request received");
        $result = $this->service->checkout($request['cart'] ?? [], $request['email'] ?? 'guest');
        return json_encode($result, JSON_PRETTY_PRINT);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// PART A — Production definitions (simulating config/services.php)
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part A: Production wiring ─────────────────────────\n\n";

// This array would live in config/services.php and be loaded with:
// $builder->addDefinitions(__DIR__ . '/../config/services.php');
$productionDefinitions = [
    // Interface → concrete (auto-wired constructors)
    DatabaseInterface::class         => autowire(InMemoryDatabase::class),
    CacheInterface::class            => autowire(ArrayCache::class),
    LoggerInterface::class           => autowire(ConsoleLogger::class),
    MailerInterface::class           => autowire(ConsoleMailer::class),
    ProductRepositoryInterface::class => autowire(ProductCatalog::class),
    InventoryInterface::class        => autowire(InventoryChecker::class),
    // CheckoutService and CheckoutController: auto-wired with no explicit entry needed
];

$builder = new ContainerBuilder();
$builder->addDefinitions($productionDefinitions);
$container = $builder->build();

$controller = $container->get(CheckoutController::class);
echo "\nRunning checkout:\n";
$response = $controller->handle([
    'cart'  => [['product_id' => 1, 'quantity' => 2], ['product_id' => 2, 'quantity' => 1]],
    'email' => 'alice@example.com',
]);
echo "\n" . $response . "\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART B — Singleton sharing verification
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part B: Singleton sharing ─────────────────────────\n\n";

$catalog   = $container->get(ProductRepositoryInterface::class);
$inventory = $container->get(InventoryInterface::class);
$checkout  = $container->get(CheckoutService::class);

$dbId     = $container->get(DatabaseInterface::class)->getInstanceId();
$loggerId = $container->get(LoggerInterface::class)->getInstanceId();

echo "All classes share ONE database (ID #{$dbId}):\n";
echo "  ProductCatalog DB:   #{$catalog->getDb()->getInstanceId()}\n";
echo "  InventoryChecker DB: #{$inventory->getDb()->getInstanceId()}\n";
echo "  Match: " . ($catalog->getDb() === $inventory->getDb() ? 'YES ✓' : 'NO ✗') . "\n\n";

echo "All classes share ONE logger (ID #{$loggerId}):\n";
echo "  CheckoutService logger:  #{$checkout->getLogger()->getInstanceId()}\n";
echo "  CheckoutController logger: #{$controller->getLogger()->getInstanceId()}\n";
echo "  ProductCatalog logger:   #{$catalog->getLogger()->getInstanceId()}\n";
echo "  Match: " . ($checkout->getLogger() === $controller->getLogger() ? 'YES ✓' : 'NO ✗') . "\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART C — Test wiring: replace real implementations with stubs
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part C: Test wiring with anonymous stubs ──────────\n\n";

// Spy mailer — records calls
$spyMailer = new class implements MailerInterface {
    public array $sent = [];
    public function send(string $to, string $subject, string $body): bool {
        $this->sent[] = compact('to', 'subject');
        return true;
    }
};

// Fake database — controlled data
$fakeDb = new class implements DatabaseInterface {
    public function query(string $sql, array $params = []): array {
        return [['id' => 1, 'sku' => 'TST-001', 'name' => 'Test Widget', 'price' => 10000, 'quantity' => 50]];
    }
    public function execute(string $sql, array $params = []): bool { return true; }
    public function getInstanceId(): string { return 'fake-db'; }
};

// Test definitions — override only what needs to differ
$testDefinitions = [
    DatabaseInterface::class          => factory(fn() => $fakeDb),
    CacheInterface::class             => autowire(ArrayCache::class),
    LoggerInterface::class            => factory(fn() => new class implements LoggerInterface {
        public function log(string $l, string $m): void {} // silent
        public function getInstanceId(): string { return 'null-logger'; }
    }),
    MailerInterface::class            => factory(fn() => $spyMailer),
    ProductRepositoryInterface::class => autowire(ProductCatalog::class),
    InventoryInterface::class         => autowire(InventoryChecker::class),
];

$testBuilder = new ContainerBuilder();
$testBuilder->addDefinitions($testDefinitions);
$testContainer = $testBuilder->build();

$testController = $testContainer->get(CheckoutController::class);
$testResponse   = json_decode(
    $testController->handle([
        'cart'  => [['product_id' => 1, 'quantity' => 1]],
        'email' => 'test@example.com',
    ]),
    true
);

echo "Test wiring assertions:\n";
echo "  Checkout succeeded:   " . ($testResponse['success'] ? 'YES ✓' : 'NO ✗') . "\n";
echo "  Mailer called once:   " . (count($spyMailer->sent) === 1 ? 'YES ✓' : 'NO ✗') . "\n";
echo "  Email to:             " . $spyMailer->sent[0]['to'] . "\n";
echo "  No real DB, no real logger, no real mailer — pure logic test ✓\n\n";

echo "--- Recap ---\n";
echo "Definitions file = composition root = ALL config lives there.\n";
echo "autowire():       interface → concrete, constructor auto-wired.\n";
echo "factory():        for primitives, env logic, or decorators.\n";
echo "Test wiring:      same container, different definitions — no service changes.\n";
echo "Rule 1:           getenv() only in definitions, never in service classes.\n";
echo "PSR-11:           \$container->get() only at entry point or test setup.\n";