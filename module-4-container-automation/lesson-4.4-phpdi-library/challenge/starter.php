<?php
declare(strict_types=1);

/**
 * CHALLENGE STARTER — Lesson 4.4: PHP-DI Library
 * ──────────────────────────────────────────────────
 * Read CHALLENGE.md before touching this file.
 * Requires: composer require php-di/php-di
 *
 * Replace the manual flat wiring with PHP-DI.
 * Do NOT look at solution.php until you have made a genuine attempt.
 */

require __DIR__ . '/../../../../vendor/autoload.php';

use DI\ContainerBuilder;
use function DI\autowire;
use function DI\factory;

// ─────────────────────────────────────────────────────────────────────────────
// Full checkout system — do not modify
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
    public function getDb(): DatabaseInterface   { return $this->db; }

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
// Flat wiring (keep — used for comparison output)
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
// TODO Task 1 — Complete getDefinitions()
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Returns the PHP-DI definitions array.
 * This simulates what would live in config/services.php.
 * All getenv() calls and implementation decisions belong here (Rule 1).
 */
function getDefinitions(): array {
    // TODO: return an array with autowire()/factory() bindings for:
    //   DatabaseInterface::class
    //   CacheInterface::class
    //   LoggerInterface::class
    //   MailerInterface::class
    //   ProductRepositoryInterface::class
    //   InventoryInterface::class
    //
    // Use at least one factory() to demonstrate the pattern.
    return [];
}


// ─────────────────────────────────────────────────────────────────────────────
// TODO Task 2 & 3 — Build container and run checkout
// ─────────────────────────────────────────────────────────────────────────────

echo "=== PHP-DI wiring ===\n\n";

// TODO: build container, get CheckoutController, call handle()
// $builder = new ContainerBuilder();
// $builder->addDefinitions(getDefinitions());
// $container = $builder->build();
// $controller = $container->get(CheckoutController::class);
// echo $controller->handle([...]) . "\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// TODO Task 4 — Test wiring with anonymous stubs
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Returns test definitions that replace real implementations with stubs.
 */
function getTestDefinitions(): array {
    // TODO: return definitions that use anonymous class stubs for
    //   DatabaseInterface, LoggerInterface, MailerInterface
    // and the spy mailer below
    return [];
}


// ─────────────────────────────────────────────────────────────────────────────
// TODO Task 5 — Assertions (all must print ✓)
// ─────────────────────────────────────────────────────────────────────────────

echo "\n=== Assertions ===\n\n";

// TODO: assert same controller (singleton)
// TODO: assert same DB in ProductCatalog and InventoryChecker
// TODO: build test container, run checkout, assert spy mailer called once
// TODO: assert response has success=true