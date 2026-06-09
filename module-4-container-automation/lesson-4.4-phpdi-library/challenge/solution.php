<?php
declare(strict_types=1);

/**
 * CHALLENGE SOLUTION — Lesson 4.4: PHP-DI Library
 * ──────────────────────────────────────────────────
 * ⚠️  Only open this file after completing starter.php yourself.
 *
 * Key things to compare in your solution:
 *   1. getDefinitions() uses autowire() and factory() correctly
 *   2. At least one factory() present — demonstrates the pattern
 *   3. Container resolves CheckoutController via auto-wiring
 *   4. Singleton sharing: same DB and same controller across resolutions
 *   5. Test definitions: anonymous class stubs replace real infrastructure
 *   6. All four assertions pass
 *   7. getenv() calls live ONLY inside definitions — never in service classes
 */

require __DIR__ . '/../../../../vendor/autoload.php';

use DI\ContainerBuilder;
use function DI\autowire;
use function DI\factory;

// ─────────────────────────────────────────────────────────────────────────────
// Full checkout system — unchanged from starter
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
    private array $products  = [
        1 => ['id' => 1, 'sku' => 'WDG-001', 'name' => 'Widget Pro',  'price' => 29999],
        2 => ['id' => 2, 'sku' => 'WDG-002', 'name' => 'Widget Lite', 'price' => 14999],
    ];
    private array $inventory = ['WDG-001' => 50, 'WDG-002' => 5];
    private string $id;

    public function __construct() { $this->id = substr(md5(uniqid()), 0, 6); }
    public function getInstanceId(): string { return $this->id; }

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
    public function set(string $key, mixed $value, int $ttl = 300): void {
        $this->store[$key] = $value;
        echo "  [CACHE] SET: {$key}\n";
    }
}

class ConsoleLogger implements LoggerInterface {
    private string $id;
    public function __construct() { $this->id = substr(md5(uniqid()), 0, 6); }
    public function log(string $level, string $message): void { echo "  [{$level}] {$message}\n"; }
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
    public function getDb(): DatabaseInterface { return $this->db; }

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

    public function handle(array $request): string {
        $this->logger->log('INFO', "Request received");
        $result = $this->service->checkout($request['cart'] ?? [], $request['email'] ?? 'guest');
        return json_encode($result, JSON_PRETTY_PRINT);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Flat wiring — kept for comparison
// ─────────────────────────────────────────────────────────────────────────────

function buildApp(): CheckoutController {
    $db        = new InMemoryDatabase();
    $cache     = new ArrayCache();
    $logger    = new ConsoleLogger();
    $mailer    = new ConsoleMailer();
    $catalog   = new ProductCatalog($db, $cache, $logger);
    $inventory = new InventoryChecker($db);
    $service   = new CheckoutService($catalog, $inventory, $mailer, $logger);
    return new CheckoutController($service, $logger);
}

echo "=== Flat wiring (buildApp) ===\n\n";
$flat = buildApp();
echo $flat->handle([
    'cart'  => [['product_id' => 1, 'quantity' => 2]],
    'email' => 'alice@example.com',
]) . "\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Task 1 — getDefinitions(): the PHP-DI composition root
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Production definitions — this is what would live in config/services.php.
 *
 * Course Philosophy Rule 1: ALL getenv() calls, DSNs, and implementation
 * decisions live HERE. Service classes never call getenv() directly.
 */
function getDefinitions(): array {
    return [
        // autowire(): interface → concrete class, constructor auto-wired by PHP-DI
        DatabaseInterface::class => autowire(InMemoryDatabase::class),
        CacheInterface::class    => autowire(ArrayCache::class),
        LoggerInterface::class   => autowire(ConsoleLogger::class),

        // factory(): demonstrates the pattern for primitive params or env logic.
        // In a real app, SmtpMailer would need $host and $port from getenv().
        // Here we return ConsoleMailer but via factory() to show the syntax.
        MailerInterface::class => factory(function () {
            // Real app: return new SmtpMailer(getenv('SMTP_HOST'), (int)getenv('SMTP_PORT'));
            // All env config lives here — ConsoleMailer has no primitive params
            return new ConsoleMailer();
        }),

        // Interface → concrete for repository/service layer
        ProductRepositoryInterface::class => autowire(ProductCatalog::class),

        // factory() demonstrating environment-based logic
        InventoryInterface::class => factory(function (\Psr\Container\ContainerInterface $c) {
            // Real app: could return different implementations based on APP_ENV
            // $env = getenv('APP_ENV') ?: 'development';
            // All of that logic lives here — InventoryChecker stays clean
            return new InventoryChecker($c->get(DatabaseInterface::class));
        }),

        // CheckoutService and CheckoutController: not in definitions — auto-wired
    ];
}


// ─────────────────────────────────────────────────────────────────────────────
// Tasks 2 & 3 — Build container and run checkout
// ─────────────────────────────────────────────────────────────────────────────

echo "=== PHP-DI wiring ===\n\n";

$builder = new ContainerBuilder();
$builder->addDefinitions(getDefinitions());
$container = $builder->build();

$controller = $container->get(CheckoutController::class);
$response   = $controller->handle([
    'cart'  => [['product_id' => 1, 'quantity' => 2]],
    'email' => 'alice@example.com',
]);
echo $response . "\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Task 4 — Test wiring with anonymous stubs
// ─────────────────────────────────────────────────────────────────────────────

// Spy mailer — defined outside so assertions can inspect it
$spyMailer = new class implements MailerInterface {
    public array $sent = [];
    public function send(string $to, string $subject, string $body): bool {
        $this->sent[] = compact('to', 'subject');
        return true;
    }
};

function getTestDefinitions(MailerInterface $spyMailer): array {
    return [
        // Fake DB — returns controlled data, no disk/network
        DatabaseInterface::class => factory(function () {
            return new class implements DatabaseInterface {
                private array $products  = [1 => ['id' => 1, 'sku' => 'TST', 'name' => 'Test', 'price' => 10000]];
                private array $inventory = ['TST' => 99];
                public function query(string $sql, array $params = []): array {
                    if (str_contains($sql, 'products') && !empty($params)) {
                        return isset($this->products[$params[0]]) ? [$this->products[$params[0]]] : [];
                    }
                    if (str_contains($sql, 'inventory') && !empty($params)) {
                        return [['sku' => $params[0], 'quantity' => $this->inventory[$params[0]] ?? 0]];
                    }
                    return [];
                }
                public function execute(string $sql, array $params = []): bool { return true; }
                public function getInstanceId(): string { return 'fake-db'; }
            };
        }),

        CacheInterface::class => autowire(ArrayCache::class),

        // Null logger — silent
        LoggerInterface::class => factory(function () {
            return new class implements LoggerInterface {
                public function log(string $l, string $m): void {}
                public function getInstanceId(): string { return 'null'; }
            };
        }),

        // Spy mailer — injected from outside so assertions can inspect it
        MailerInterface::class => factory(fn() => $spyMailer),

        ProductRepositoryInterface::class => autowire(ProductCatalog::class),
        InventoryInterface::class         => autowire(InventoryChecker::class),
    ];
}

$testBuilder = new ContainerBuilder();
$testBuilder->addDefinitions(getTestDefinitions($spyMailer));
$testContainer = $testBuilder->build();

$testController = $testContainer->get(CheckoutController::class);
$testResponse   = json_decode(
    $testController->handle([
        'cart'  => [['product_id' => 1, 'quantity' => 1]],
        'email' => 'test@example.com',
    ]),
    true
);


// ─────────────────────────────────────────────────────────────────────────────
// Task 5 — Assertions
// ─────────────────────────────────────────────────────────────────────────────

echo "\n=== Assertions ===\n\n";

$allPass = true;
function assertThat(bool $cond, string $label, bool &$all): void {
    echo ($cond ? '  ✓' : '  ✗') . " {$label}\n";
    if (!$cond) $all = false;
}

// Same controller instance (singleton)
$ctrl1 = $container->get(CheckoutController::class);
$ctrl2 = $container->get(CheckoutController::class);
assertThat($ctrl1 === $ctrl2, 'Same controller (singleton)', $allPass);

// Shared DB: ProductCatalog and InventoryChecker use same DatabaseInterface instance
$catalog   = $container->get(ProductRepositoryInterface::class);
$inventory = $container->get(InventoryInterface::class);
assertThat(
    $catalog->getDb() === $inventory->getDb(),
    'Same DB in ProductCatalog and InventoryChecker',
    $allPass
);

// Spy mailer called exactly once
assertThat(count($spyMailer->sent) === 1, 'Spy mailer called once', $allPass);

// Response has success=true
assertThat($testResponse['success'] === true, 'Checkout response has success=true', $allPass);

echo "\n" . ($allPass ? 'All assertions PASSED ✓' : 'Some assertions FAILED ✗') . "\n";


// ─────────────────────────────────────────────────────────────────────────────
// SELF-REVIEW CHECKLIST
// ─────────────────────────────────────────────────────────────────────────────
echo "\n--- Self-review checklist ---\n";
echo "[ ] getDefinitions() uses autowire() for interface → concrete bindings?\n";
echo "[ ] getDefinitions() uses at least one factory() call?\n";
echo "[ ] All getenv() calls (if any) are inside definitions only — never in services?\n";
echo "[ ] ContainerBuilder::addDefinitions() used to load the array?\n";
echo "[ ] \$container->get(CheckoutController::class) resolves without error?\n";
echo "[ ] Output matches flat buildApp() structure?\n";
echo "[ ] Same controller singleton assertion passes?\n";
echo "[ ] Same DB assertion passes?\n";
echo "[ ] Test definitions use anonymous class stubs — no real infrastructure?\n";
echo "[ ] Spy mailer assertion passes?\n";
echo "[ ] Response success=true assertion passes?\n";