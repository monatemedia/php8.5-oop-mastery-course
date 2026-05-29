<?php
declare(strict_types=1);

/**
 * Example 01 — Manual Container: bind() and get() from Scratch
 * --------------------------------------------------------------
 * A service container is not magic. This example builds one from ~50 lines
 * of plain PHP to show exactly what every container library does internally.
 *
 * What we build:
 *   - bind(id, factory)   — register a factory callable for an identifier
 *   - get(id)             — call the factory and return the result
 *   - has(id)             — check whether a binding exists
 *
 * This is the simplest possible container. Lesson 4.2 adds Reflection
 * (auto-wiring), and Lesson 4.3 adds singleton caching.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Manual Container — bind() and get() from Scratch  ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// The application services we will wire
// ─────────────────────────────────────────────────────────────────────────────

interface DatabaseInterface {
    public function query(string $sql, array $params = []): array;
    public function execute(string $sql, array $params = []): bool;
}

interface LoggerInterface {
    public function log(string $level, string $message): void;
}

interface MailerInterface {
    public function send(string $to, string $subject, string $body): bool;
}

class InMemoryDatabase implements DatabaseInterface {
    private array $data = [
        1 => ['id' => 1, 'name' => 'Widget Pro',  'price' => 29999],
        2 => ['id' => 2, 'name' => 'Widget Lite', 'price' => 14999],
    ];
    public function query(string $sql, array $params = []): array {
        echo "  [DB] Query: " . substr($sql, 0, 50) . "\n";
        if (!empty($params) && is_int($params[0])) {
            return isset($this->data[$params[0]]) ? [$this->data[$params[0]]] : [];
        }
        return array_values($this->data);
    }
    public function execute(string $sql, array $params = []): bool {
        echo "  [DB] Execute: " . substr($sql, 0, 40) . "\n";
        return true;
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

class ProductRepository {
    public function __construct(
        private DatabaseInterface $db,
        private LoggerInterface   $logger
    ) {}

    public function findAll(): array {
        $this->logger->log('INFO', 'Fetching all products');
        return $this->db->query('SELECT * FROM products');
    }

    public function findById(int $id): ?array {
        $this->logger->log('INFO', "Fetching product #{$id}");
        $rows = $this->db->query('SELECT * FROM products WHERE id = ?', [$id]);
        return $rows[0] ?? null;
    }
}

class OrderService {
    public function __construct(
        private ProductRepository $products,
        private DatabaseInterface $db,
        private MailerInterface   $mailer,
        private LoggerInterface   $logger
    ) {}

    public function placeOrder(int $productId, string $email): bool {
        $this->logger->log('INFO', "Placing order for product #{$productId}");
        $product = $this->products->findById($productId);
        if (!$product) return false;
        $this->db->execute('INSERT INTO orders (product_id, email) VALUES (?,?)', [$productId, $email]);
        $this->mailer->send($email, "Order Confirmed", "You ordered {$product['name']}");
        $this->logger->log('INFO', "Order placed for {$email}");
        return true;
    }
}


// ═══════════════════════════════════════════════════════════
// THE CONTAINER — ~50 lines of plain PHP
// ═══════════════════════════════════════════════════════════

class Container {
    /** @var array<string, callable> */
    private array $bindings = [];

    /**
     * Register a factory callable for a service identifier.
     * The factory is called every time get() is invoked.
     */
    public function bind(string $id, callable $factory): void {
        $this->bindings[$id] = $factory;
    }

    /**
     * Resolve a service by its identifier.
     * Calls the registered factory and returns the result.
     *
     * @throws \RuntimeException if no binding exists for $id
     */
    public function get(string $id): mixed {
        if (!$this->has($id)) {
            throw new \RuntimeException(
                "No binding found for '{$id}'. " .
                "Did you forget to call \$container->bind('{$id}', ...)?'"
            );
        }
        // Call the factory — passing $this allows factories to call get() recursively
        return ($this->bindings[$id])($this);
    }

    /**
     * Check whether a binding exists for the given identifier.
     */
    public function has(string $id): bool {
        return isset($this->bindings[$id]);
    }

    /**
     * Return all registered identifiers (useful for debugging).
     * @return string[]
     */
    public function bindings(): array {
        return array_keys($this->bindings);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// PART 1 — Register bindings
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 1: Registering bindings ─────────────────────\n\n";

$container = new Container();

// Infrastructure bindings
$container->bind(DatabaseInterface::class, fn(Container $c) => new InMemoryDatabase());
$container->bind(LoggerInterface::class,   fn(Container $c) => new ConsoleLogger());
$container->bind(MailerInterface::class,   fn(Container $c) => new ConsoleMailer());

// Repository — factory calls get() to resolve its own dependencies
$container->bind(ProductRepository::class, fn(Container $c) => new ProductRepository(
    $c->get(DatabaseInterface::class),
    $c->get(LoggerInterface::class)
));

// Service — factory calls get() for each dependency
$container->bind(OrderService::class, fn(Container $c) => new OrderService(
    $c->get(ProductRepository::class),
    $c->get(DatabaseInterface::class),
    $c->get(MailerInterface::class),
    $c->get(LoggerInterface::class)
));

echo "Registered bindings:\n";
foreach ($container->bindings() as $id) {
    echo "  " . class_basename($id) . "\n";
}


// ─────────────────────────────────────────────────────────────────────────────
// PART 2 — Resolve services
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 2: Resolving services ───────────────────────\n\n";

echo "Resolving DatabaseInterface:\n";
$db = $container->get(DatabaseInterface::class);
echo "  Got: " . get_class($db) . "\n\n";

echo "Resolving OrderService (triggers full graph):\n";
$service = $container->get(OrderService::class);
echo "  Got: " . get_class($service) . "\n\n";

echo "Using the resolved service:\n";
$service->placeOrder(1, 'alice@example.com');


// ─────────────────────────────────────────────────────────────────────────────
// PART 3 — Factory mode: new instance on every get()
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 3: Factory mode — new instance each time ────\n\n";

$db1 = $container->get(DatabaseInterface::class);
$db2 = $container->get(DatabaseInterface::class);

echo "First get():  " . spl_object_id($db1) . "\n";
echo "Second get(): " . spl_object_id($db2) . "\n";
echo "Same object?  " . ($db1 === $db2 ? 'YES' : 'NO') . "\n\n";
echo "This is FACTORY mode — every get() calls the factory and creates a new instance.\n";
echo "For stateless infrastructure this is wasteful. Lesson 4.2 adds singleton caching.\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 4 — Error handling: unregistered binding
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 4: Resolving an unregistered service ────────\n\n";

echo "Attempting to get CacheInterface (not registered):\n";
try {
    $cache = $container->get('CacheInterface');
} catch (\RuntimeException $e) {
    echo "  RuntimeException: " . $e->getMessage() . "\n\n";
}

echo "Using has() to check before get():\n";
echo "  has(DatabaseInterface)? " . ($container->has(DatabaseInterface::class) ? 'YES' : 'NO') . "\n";
echo "  has(CacheInterface)?    " . ($container->has('CacheInterface') ? 'YES' : 'NO') . "\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 5 — The container factory receives $this
// Factories can call get() to resolve their own sub-dependencies
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 5: Nested resolution — factories call get() ─\n\n";

echo "When OrderService is resolved:\n";
echo "  1. Container calls the OrderService factory\n";
echo "  2. Factory calls \$c->get(ProductRepository::class)\n";
echo "  3. ProductRepository factory calls \$c->get(DatabaseInterface::class)\n";
echo "  4. DatabaseInterface factory creates new InMemoryDatabase() and returns it\n";
echo "  5. ProductRepository factory creates new ProductRepository(\$db, \$logger)\n";
echo "  6. OrderService factory creates new OrderService(\$products, \$db, \$mailer, \$logger)\n\n";

echo "The graph resolves depth-first — leaves first, root last.\n";
echo "This is manual auto-wiring. Lesson 4.3 automates it via Reflection.\n";

echo "\n--- Recap ---\n";
echo "Container stores bindings (interface → factory callable).\n";
echo "get() calls the factory and returns the result — fresh each time (factory mode).\n";
echo "Factories receive \$container so they can call get() for sub-dependencies.\n";
echo "has() checks existence before resolving — use to avoid RuntimeException.\n";
echo "Next: Lesson 4.2 adds singleton caching so each class is only built once.\n";

function class_basename(string $class): string {
    $parts = explode('\\', $class);
    return end($parts);
}