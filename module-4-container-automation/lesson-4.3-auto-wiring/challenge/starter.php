<?php
declare(strict_types=1);

/**
 * CHALLENGE STARTER — Lesson 4.3: Auto-wiring
 * ──────────────────────────────────────────────
 * Read CHALLENGE.md before touching this file.
 *
 * Extend SimpleContainer with auto-wiring + circular detection,
 * then wire the full checkout system with only interface bindings.
 *
 * Do NOT look at solution.php until you have made a genuine attempt.
 */


// ─────────────────────────────────────────────────────────────────────────────
// Exception class (do not modify)
// ─────────────────────────────────────────────────────────────────────────────

class CircularDependencyException extends \RuntimeException {}


// ─────────────────────────────────────────────────────────────────────────────
// Full checkout system from Lesson 4.1 (do not modify)
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
        1 => ['id' => 1, 'sku' => 'WDG-001', 'name' => 'Widget Pro',  'price' => 29999, 'stock' => 50],
        2 => ['id' => 2, 'sku' => 'WDG-002', 'name' => 'Widget Lite', 'price' => 14999, 'stock' => 5],
    ];
    private array $inventory = ['WDG-001' => 50, 'WDG-002' => 5];
    private string $instanceId;

    public function __construct() {
        $this->instanceId = substr(md5(uniqid()), 0, 6);
    }
    public function getInstanceId(): string { return $this->instanceId; }

    public function query(string $sql, array $params = []): array {
        if (str_contains($sql, 'products') && !empty($params)) {
            if (is_int($params[0])) {
                return isset($this->products[$params[0]]) ? [$this->products[$params[0]]] : [];
            }
            foreach ($this->products as $p) {
                if ($p['sku'] === $params[0]) return [$p];
            }
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
        $this->mailer->send($email, "Order Confirmed #{$orderId}", "Total: R" . ($total / 100));
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
        $result = $this->service->checkout($request['cart'] ?? [], $request['email'] ?? 'guest');
        return json_encode($result, JSON_PRETTY_PRINT);
    }
}

// Classes for circular dependency test (do not modify)
class CircularA {
    public function __construct(private CircularB $b) {}
}
class CircularB {
    public function __construct(private CircularA $a) {}
}


// ─────────────────────────────────────────────────────────────────────────────
// TODO: Extend SimpleContainer with auto-wiring (Tasks 1)
// ─────────────────────────────────────────────────────────────────────────────

class SimpleContainer {
    private array $bindings   = [];
    private array $singletons = [];
    private array $instances  = [];
    private array $resolving  = []; // TODO: use this for circular detection

    public function bind(string $id, string|callable $factory): void {
        $this->bindings[$id]   = $factory;
        $this->singletons[$id] = false;
    }

    public function singleton(string $id, callable $factory): void {
        $this->bindings[$id]   = $factory;
        $this->singletons[$id] = true;
    }

    public function instance(string $id, object $object): void {
        $this->instances[$id]  = $object;
        $this->singletons[$id] = true;
    }

    public function get(string $id): mixed {
        if (isset($this->instances[$id])) return $this->instances[$id];

        if (isset($this->bindings[$id])) {
            $binding = $this->bindings[$id];
            if (is_callable($binding)) {
                $result = $binding($this);
            } else {
                // Explicit class name binding — resolve that class
                // TODO: call autowire() here instead of get() to avoid infinite loop
                $result = $this->get($binding);
            }
            if ($this->singletons[$id] ?? false) {
                $this->instances[$id] = $result;
            }
            return $result;
        }

        // TODO Task 1: No explicit binding — call $this->autowire($id)
        throw new \RuntimeException(
            "No binding for '{$id}'. Add auto-wiring here."
        );
    }

    public function has(string $id): bool {
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }

    // TODO Task 1: Implement autowire()
    // private function autowire(string $class): object { ... }
}


// ─────────────────────────────────────────────────────────────────────────────
// TODO Task 2: Wire the checkout system with only interface bindings
// ─────────────────────────────────────────────────────────────────────────────

echo "=== Checkout via auto-wiring container ===\n\n";

$container = new SimpleContainer();

// Register ONLY interface → concrete bindings
// (No bind() calls for ProductCatalog, InventoryChecker, CheckoutService, CheckoutController)
// TODO: add your six interface bindings here

// TODO: resolve CheckoutController and call handleCheckout()


// ─────────────────────────────────────────────────────────────────────────────
// TODO Task 3 & 4: Assertions
// ─────────────────────────────────────────────────────────────────────────────

echo "\n=== Assertions ===\n\n";

// TODO: assert same controller (singleton)
// TODO: assert same DB instance in ProductCatalog and InventoryChecker
// TODO: assert CircularDependencyException thrown for CircularA
// TODO: assert exception message contains class names