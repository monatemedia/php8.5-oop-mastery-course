<?php
declare(strict_types=1);

/**
 * Example 01 — Basic Auto-wiring
 * ---------------------------------
 * Combining Lesson 4.1 (container) + Lesson 4.2 (reflection) into a working
 * auto-wiring container that resolves a 2-level dependency chain without any
 * manual factory definitions for service classes.
 *
 * Course Philosophy Rule 3: The type system is a security layer.
 * Auto-wiring ONLY works because constructor params are typed as interfaces.
 * If a param were typed as 'string' or left untyped, the container would fail.
 * Well-typed code is the prerequisite for automated wiring.
 *
 * Course Philosophy Rule 1: Config at the entry point.
 * The explicit bindings (interface → concrete) are the "config" for this container.
 * They live at the composition root — not inside the service classes.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Basic Auto-wiring                                  ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Interfaces and concrete classes (2-level graph)
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

// Concrete classes — implement the interfaces
class InMemoryDatabase implements DatabaseInterface {
    private array $data = [
        1 => ['id' => 1, 'name' => 'Widget Pro',  'price' => 29999],
        2 => ['id' => 2, 'name' => 'Widget Lite', 'price' => 14999],
    ];
    public function query(string $sql, array $params = []): array {
        echo "  [DB] query: " . substr($sql, 0, 40) . "\n";
        if (!empty($params) && is_int($params[0])) {
            return isset($this->data[$params[0]]) ? [$this->data[$params[0]]] : [];
        }
        return array_values($this->data);
    }
    public function execute(string $sql, array $params = []): bool {
        echo "  [DB] execute\n";
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

// Level 1: ProductRepository depends on DatabaseInterface + LoggerInterface
class ProductRepository {
    public function __construct(
        private DatabaseInterface $db,
        private LoggerInterface   $logger
    ) {}

    public function findAll(): array {
        $this->logger->log('INFO', 'Fetching all products');
        return $this->db->query('SELECT * FROM products');
    }
}

// Level 2: OrderService depends on ProductRepository + MailerInterface + LoggerInterface
class OrderService {
    public function __construct(
        private ProductRepository $products,   // concrete class — auto-wirable
        private MailerInterface   $mailer,     // interface — needs binding
        private LoggerInterface   $logger      // interface — needs binding
    ) {}

    public function placeOrder(int $productId, string $email): bool {
        $this->logger->log('INFO', "Order for product #{$productId}");
        $this->mailer->send($email, 'Order Confirmed', 'Your order is placed');
        return true;
    }
}


// ═══════════════════════════════════════════════════════════
// The AutowiringContainer
// ═══════════════════════════════════════════════════════════

class AutowiringContainer {
    /** @var array<string, string|callable> explicit bindings */
    private array $bindings  = [];
    /** @var array<string, object> singleton cache */
    private array $instances = [];

    /**
     * Bind an interface to a concrete class name OR a factory callable.
     *
     * @param string|callable $target  concrete class name string, or a callable factory
     */
    public function bind(string $id, string|callable $target): void {
        $this->bindings[$id] = $target;
    }

    /**
     * Store a pre-built instance (for classes needing primitive constructor args).
     */
    public function instance(string $id, object $object): void {
        $this->instances[$id] = $object;
    }

    /**
     * Resolve a class or interface.
     * Uses explicit binding if registered, otherwise auto-wires via Reflection.
     */
    public function get(string $id): object {
        // 1. Return cached singleton
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // 2. Use explicit binding if it exists
        if (isset($this->bindings[$id])) {
            $binding = $this->bindings[$id];

            // Callable factory
            if (is_callable($binding)) {
                $instance = $binding($this);
                return $this->instances[$id] = $instance;
            }

            // String: concrete class name — resolve THAT class instead
            return $this->instances[$id] = $this->resolve($binding);
        }

        // 3. Auto-wire: reflect and resolve
        return $this->instances[$id] = $this->resolve($id);
    }

    public function has(string $id): bool {
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }

    /**
     * Resolve a concrete class by reading its constructor via Reflection.
     * Throws if the class is not instantiable or has unresolvable params.
     */
    private function resolve(string $class): object {
        $ref = new ReflectionClass($class);

        if (!$ref->isInstantiable()) {
            throw new \RuntimeException(
                "Cannot auto-wire '{$class}': not instantiable. " .
                "Register an explicit binding with bind()."
            );
        }

        $ctor = $ref->getConstructor();

        // No constructor — instantiate directly
        if ($ctor === null || count($ctor->getParameters()) === 0) {
            return new $class();
        }

        // Resolve each constructor parameter
        $deps = [];
        foreach ($ctor->getParameters() as $param) {
            $type = $param->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                // Interface or class — resolve recursively
                $deps[] = $this->get($type->getName());
            } elseif ($param->isOptional()) {
                // Has a default value — use it
                $deps[] = $param->getDefaultValue();
            } else {
                throw new \RuntimeException(
                    "Cannot auto-wire '\${$param->getName()}' in '{$class}': " .
                    "primitive type or missing type hint. Register an explicit factory."
                );
            }
        }

        return $ref->newInstanceArgs($deps);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// PART 1 — Manual wiring (for comparison)
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 1: Manual wiring (for comparison) ───────────\n\n";

$db     = new InMemoryDatabase();
$logger = new ConsoleLogger();
$mailer = new ConsoleMailer();

$products       = new ProductRepository($db, $logger);
$manualService  = new OrderService($products, $mailer, $logger);

$manualService->placeOrder(1, 'alice@example.com');
$manualService->products ?? null; // suppress unused


// ─────────────────────────────────────────────────────────────────────────────
// PART 2 — Auto-wiring (same result, less config)
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 2: Auto-wiring (same result) ────────────────\n\n";

$container = new AutowiringContainer();

// Only interfaces need explicit bindings — the container cannot guess
// which concrete class to use for DatabaseInterface without being told.
$container->bind(DatabaseInterface::class, InMemoryDatabase::class);
$container->bind(LoggerInterface::class,   ConsoleLogger::class);
$container->bind(MailerInterface::class,   ConsoleMailer::class);

// ProductRepository and OrderService: NO manual bind() needed.
// The container reads their constructors and wires them automatically.
echo "Resolving OrderService (no bind() call for it or ProductRepository):\n";
$autoService = $container->get(OrderService::class);
$autoService->placeOrder(1, 'alice@example.com');


// ─────────────────────────────────────────────────────────────────────────────
// PART 3 — What the container did under the hood
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 3: What the container resolved ──────────────\n\n";

echo "To resolve OrderService, the container:\n";
echo "  1. Reflected OrderService constructor\n";
echo "     → needs ProductRepository, MailerInterface, LoggerInterface\n";
echo "  2. Resolved ProductRepository (no binding — auto-wire)\n";
echo "     → reflected ProductRepository constructor\n";
echo "     → needs DatabaseInterface, LoggerInterface\n";
echo "     → resolved DatabaseInterface → InMemoryDatabase (explicit binding)\n";
echo "     → resolved LoggerInterface   → ConsoleLogger    (explicit binding)\n";
echo "     → created ProductRepository(InMemoryDatabase, ConsoleLogger)\n";
echo "  3. Resolved MailerInterface → ConsoleMailer (explicit binding)\n";
echo "  4. Resolved LoggerInterface → ConsoleLogger (cached singleton)\n";
echo "  5. Created OrderService(ProductRepository, ConsoleMailer, ConsoleLogger)\n\n";

echo "Explicit bindings registered: 3 (the three interfaces)\n";
echo "Classes auto-wired:           2 (ProductRepository, OrderService)\n";
echo "Manual factory lines written: 0\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 4 — Singleton caching
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 4: Singleton caching ────────────────────────\n\n";

$s1 = $container->get(OrderService::class);
$s2 = $container->get(OrderService::class);
echo "Same OrderService? " . ($s1 === $s2 ? 'YES ✓' : 'NO ✗') . "\n";

$l1 = $container->get(LoggerInterface::class);
$l2 = $container->get(LoggerInterface::class);
echo "Same Logger?       " . ($l1 === $l2 ? 'YES ✓' : 'NO ✗') . "\n\n";
echo "Every resolved class is cached — never built twice.\n";
echo "Rule 5 connection: stateless services (like Logger) are safe as singletons.\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 5 — Failure case: unresolvable primitive
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 5: Failure case — primitive constructor param ─\n\n";

class MySQLDatabase implements DatabaseInterface {
    public function __construct(
        private string $dsn,   // ← primitive — container cannot auto-wire this
        private int    $port = 3306
    ) {}
    public function query(string $sql, array $params = []): array { return []; }
    public function execute(string $sql, array $params = []): bool { return true; }
}

$container2 = new AutowiringContainer();
$container2->bind(LoggerInterface::class, ConsoleLogger::class);
$container2->bind(MailerInterface::class,  ConsoleMailer::class);
$container2->bind(DatabaseInterface::class, MySQLDatabase::class); // ← MySQLDatabase needs $dsn

try {
    $container2->get(ProductRepository::class);
} catch (\RuntimeException $e) {
    echo "RuntimeException: " . $e->getMessage() . "\n\n";
}

echo "Fix: register an explicit factory or instance:\n";
echo "  \$container2->instance(DatabaseInterface::class, new MySQLDatabase(getenv('DB_DSN')));\n";
echo "  OR\n";
echo "  \$container2->bind(DatabaseInterface::class, fn(\$c) => new MySQLDatabase(getenv('DB_DSN')));\n";

echo "\n--- Recap ---\n";
echo "Auto-wiring reads constructor type hints via Reflection.\n";
echo "Interfaces need one explicit bind(Interface, Concrete).\n";
echo "Service classes (no primitives) are resolved automatically.\n";
echo "Singletons cached — each class resolved at most once.\n";
echo "Primitive params break auto-wiring → use instance() or callable factory.\n";