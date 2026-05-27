<?php
declare(strict_types=1);

/**
 * Example 05 — The Bridge to Dependency Injection
 * -------------------------------------------------
 * This is the final example of Module 1. It connects everything learned
 * about composition to the problem that Modules 3 and 4 solve.
 *
 * The core insight:
 *   When you compose a class using constructor injection, you have already
 *   declared its dependency graph. The container in Module 4 reads that
 *   exact constructor signature and wires it automatically.
 *
 *   Inheritance buries dependencies. Composition exposes them.
 *   Exposed dependencies are what containers resolve.
 *
 * This example builds the same order processing system three ways:
 *   A. Pure inheritance   — container cannot wire it
 *   B. Manual composition — container COULD wire it, but we do it by hand
 *   C. Container wiring   — a minimal container reads constructors and wires automatically
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Bridge to Dependency Injection                     ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// The interfaces and implementations used throughout
// ─────────────────────────────────────────────────────────────────────────────

interface PaymentGatewayInterface {
    public function charge(float $amount, string $token): bool;
}

interface MailerInterface {
    public function send(string $to, string $subject, string $body): bool;
}

interface LoggerInterface {
    public function log(string $level, string $message): void;
}

interface DatabaseInterface {
    public function execute(string $sql, array $params = []): bool;
    public function query(string $sql, array $params = []): array;
}

class FakeGateway implements PaymentGatewayInterface {
    public function charge(float $amount, string $token): bool {
        echo "  [GATEWAY] Charged R{$amount} token={$token}\n";
        return true;
    }
}

class ConsoleMailer implements MailerInterface {
    public function send(string $to, string $subject, string $body): bool {
        echo "  [MAILER] To: {$to} | {$subject}\n";
        return true;
    }
}

class ConsoleLogger implements LoggerInterface {
    public function log(string $level, string $message): void {
        echo "  [{$level}] {$message}\n";
    }
}

class InMemoryDatabase implements DatabaseInterface {
    private array $orders = [];
    public function execute(string $sql, array $params = []): bool {
        if (str_contains($sql, 'INSERT') && !empty($params)) {
            $this->orders[$params[0]] = ['id' => $params[0], 'total' => $params[1]];
            echo "  [DB] Order #{$params[0]} saved (total: R{$params[1]})\n";
        }
        return true;
    }
    public function query(string $sql, array $params = []): array {
        return !empty($params) && isset($this->orders[$params[0]])
            ? [$this->orders[$params[0]]] : [];
    }
}


// ═══════════════════════════════════════════════════════════
// APPROACH A — Pure inheritance
// The container cannot wire this — dependencies are buried
// ═══════════════════════════════════════════════════════════

echo "── Approach A: Inheritance — invisible to a container ─\n\n";

class BaseOrderService {
    protected FakeGateway  $gateway;
    protected InMemoryDatabase $db;

    public function __construct() {
        // Dependencies created here — invisible from outside
        $this->gateway = new FakeGateway();
        $this->db      = new InMemoryDatabase();
    }
}

class InheritedOrderService extends BaseOrderService {
    private ConsoleMailer  $mailer;
    private ConsoleLogger  $logger;

    public function __construct() {
        parent::__construct(); // triggers BaseOrderService wiring
        $this->mailer = new ConsoleMailer();
        $this->logger = new ConsoleLogger();
    }

    public function placeOrder(array $order): bool {
        $this->logger->log('INFO', "Placing order #{$order['id']}");
        $charged = $this->gateway->charge($order['total'], $order['token']);
        if ($charged) {
            $this->db->execute('INSERT INTO orders (id, total) VALUES (?,?)',
                [$order['id'], $order['total']]);
            $this->mailer->send($order['email'], 'Order confirmed', 'Thanks!');
        }
        return $charged;
    }
}

echo "InheritedOrderService — constructor hides all deps:\n";
$inheritedService = new InheritedOrderService();
$inheritedService->placeOrder([
    'id' => 1001, 'total' => 599.98, 'token' => 'tok_abc', 'email' => 'alice@example.com'
]);

echo "\nWhat a container sees:\n";
$ref   = new ReflectionClass(InheritedOrderService::class);
$ctor  = $ref->getConstructor();
$params = $ctor ? $ctor->getParameters() : [];
echo "  Constructor parameters: " . (empty($params) ? 'NONE — container cannot auto-wire' : count($params)) . "\n\n";

echo "A container reads the constructor signature. This one has no parameters.\n";
echo "There is nothing to resolve. The class is OPAQUE to a container.\n\n";


// ═══════════════════════════════════════════════════════════
// APPROACH B — Manual composition
// All dependencies visible as constructor parameters
// ═══════════════════════════════════════════════════════════

echo "── Approach B: Composition — transparent to a container\n\n";

class ComposedOrderService {
    // ✅ Every dependency is a constructor parameter — fully visible
    public function __construct(
        private PaymentGatewayInterface $gateway,
        private DatabaseInterface       $db,
        private MailerInterface         $mailer,
        private LoggerInterface         $logger
    ) {}

    public function placeOrder(array $order): bool {
        $this->logger->log('INFO', "Placing order #{$order['id']}");
        $charged = $this->gateway->charge($order['total'], $order['token']);
        if ($charged) {
            $this->db->execute('INSERT INTO orders (id, total) VALUES (?,?)',
                [$order['id'], $order['total']]);
            $this->mailer->send($order['email'], 'Order confirmed', 'Thanks!');
        }
        return $charged;
    }
}

echo "ComposedOrderService — all deps visible as constructor parameters:\n";
$ref    = new ReflectionClass(ComposedOrderService::class);
$ctor   = $ref->getConstructor();
$params = $ctor->getParameters();
echo "  Constructor parameters: " . count($params) . "\n";
foreach ($params as $param) {
    $type = $param->getType();
    $typeName = $type instanceof ReflectionNamedType ? $type->getName() : 'unknown';
    echo "    - \${$param->getName()}: {$typeName}\n";
}

echo "\nManual wiring (composition root):\n";
$composedService = new ComposedOrderService(
    new FakeGateway(),
    new InMemoryDatabase(),
    new ConsoleMailer(),
    new ConsoleLogger()
);
$composedService->placeOrder([
    'id' => 1002, 'total' => 899.97, 'token' => 'tok_def', 'email' => 'bob@example.com'
]);


// ═══════════════════════════════════════════════════════════
// APPROACH C — Container wiring
// A minimal container uses Reflection to wire automatically
// (This is a preview of what Module 4 builds in depth)
// ═══════════════════════════════════════════════════════════

echo "\n── Approach C: Container auto-wiring (Module 4 preview) \n\n";

/**
 * MiniContainer — a 40-line preview of Module 4.
 * It reads constructor type hints using Reflection and resolves
 * the dependency graph automatically.
 *
 * In Module 4 you will build this from scratch and then replace it
 * with the full PHP-DI library.
 */
class MiniContainer {
    private array $bindings  = [];
    private array $instances = [];

    // Bind an interface to a concrete class
    public function bind(string $abstract, string $concrete): void {
        $this->bindings[$abstract] = $concrete;
    }

    // Resolve a class — create it with all its dependencies
    public function make(string $class): object {
        // Singleton: return existing instance if already built
        if (isset($this->instances[$class])) {
            return $this->instances[$class];
        }

        // Resolve binding (interface → concrete)
        $concrete = $this->bindings[$class] ?? $class;

        // Inspect the constructor
        $ref    = new ReflectionClass($concrete);
        $ctor   = $ref->getConstructor();

        if ($ctor === null || count($ctor->getParameters()) === 0) {
            // No constructor params — just instantiate
            return $this->instances[$class] = new $concrete();
        }

        // Resolve each constructor parameter recursively
        $deps = [];
        foreach ($ctor->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $deps[] = $this->make($type->getName()); // recursive
            }
        }

        return $this->instances[$class] = $ref->newInstanceArgs($deps);
    }
}

// ── Set up the container (composition root) ──────────────────────────────────
$container = new MiniContainer();

// Bind interfaces to concrete classes
$container->bind(PaymentGatewayInterface::class, FakeGateway::class);
$container->bind(DatabaseInterface::class,       InMemoryDatabase::class);
$container->bind(MailerInterface::class,          ConsoleMailer::class);
$container->bind(LoggerInterface::class,          ConsoleLogger::class);

// Resolve the entire graph automatically
echo "Container resolving ComposedOrderService (auto-wiring):\n";
$autoWiredService = $container->make(ComposedOrderService::class);

echo "\nUsing auto-wired service:\n";
$autoWiredService->placeOrder([
    'id' => 1003, 'total' => 299.99, 'token' => 'tok_ghi', 'email' => 'carol@example.com'
]);


// ─────────────────────────────────────────────────────────────────────────────
// The connection made explicit
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── The connection ───────────────────────────────────\n\n";

echo "Inheritance hides dependencies in constructors:\n";
echo "  → Container sees nothing → cannot wire → you must wire manually\n\n";

echo "Composition exposes dependencies as constructor parameters:\n";
echo "  → Container reads type hints via Reflection\n";
echo "  → Container resolves each dep recursively\n";
echo "  → Container wires the entire graph from four lines of binding config\n\n";

echo "This is exactly what PHP-DI does in Module 4:\n";
echo "  \$builder = new \\DI\\ContainerBuilder();\n";
echo "  \$builder->addDefinitions([\n";
echo "      PaymentGatewayInterface::class => \\DI\\autowire(StripeGateway::class),\n";
echo "      DatabaseInterface::class       => \\DI\\autowire(MySQLDatabase::class),\n";
echo "  ]);\n";
echo "  \$container = \$builder->build();\n";
echo "  \$service   = \$container->get(OrderService::class); // fully wired\n\n";

echo "Module 1 prepared you to write services that a container CAN wire.\n";
echo "Module 3 teaches you why wiring matters and how to do it correctly.\n";
echo "Module 4 automates the wiring you will do manually in Module 3.\n";

echo "\n--- Recap ---\n";
echo "Composition = dependencies are constructor parameters = visible to containers.\n";
echo "Inheritance = dependencies created internally = invisible to containers.\n";
echo "Reflection reads constructor type hints — that is how all containers work.\n";
echo "The four binding lines in a container definitions file are the composition root.\n";