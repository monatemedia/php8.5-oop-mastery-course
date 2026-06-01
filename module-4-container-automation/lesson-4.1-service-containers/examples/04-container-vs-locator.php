<?php
declare(strict_types=1);

/**
 * Example 04 — Container vs Service Locator
 * --------------------------------------------
 * A container and a Service Locator use identical technology.
 * The difference is entirely in WHERE get() is called.
 *
 * CONTAINER (correct):
 *   get() is called ONLY at the entry point / composition root.
 *   Business classes receive fully-wired dependencies via constructor.
 *   They never touch the container.
 *
 * SERVICE LOCATOR (anti-pattern):
 *   Business classes call get() on the container directly.
 *   Dependencies are hidden inside methods, invisible from the constructor.
 *   The class is coupled to the container itself.
 *
 * This example builds the same OrderService THREE ways:
 *   A. Container (correct) — wired at entry point
 *   B. Service Locator (anti-pattern) — business class calls get()
 *   C. Disguised Service Locator — using a static facade (still wrong)
 *
 * Then it proves why B and C are anti-patterns by attempting to test them.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Container vs Service Locator                       ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Container and interfaces
// ─────────────────────────────────────────────────────────────────────────────

class Container {
    private array $bindings  = [];
    private array $singletons = [];
    private array $instances = [];

    public function singleton(string $id, callable $f): void {
        $this->bindings[$id]   = $f;
        $this->singletons[$id] = true;
    }
    public function bind(string $id, callable $f): void {
        $this->bindings[$id]   = $f;
        $this->singletons[$id] = false;
    }
    public function get(string $id): mixed {
        if (isset($this->instances[$id])) return $this->instances[$id];
        if (!isset($this->bindings[$id])) throw new \RuntimeException("Not bound: {$id}");
        $result = ($this->bindings[$id])($this);
        if ($this->singletons[$id] ?? false) $this->instances[$id] = $result;
        return $result;
    }
    public function has(string $id): bool {
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }
}

interface PaymentGatewayInterface {
    public function charge(float $amount, string $token): bool;
}
interface LoggerInterface {
    public function log(string $level, string $message): void;
}
interface DatabaseInterface {
    public function execute(string $sql, array $params = []): bool;
}

class FakeGateway implements PaymentGatewayInterface {
    public bool $shouldSucceed = true;
    public array $calls = [];
    public function charge(float $amount, string $token): bool {
        $this->calls[] = compact('amount', 'token');
        echo "  [GATEWAY] Charged R{$amount}\n";
        return $this->shouldSucceed;
    }
}
class ConsoleLogger implements LoggerInterface {
    public array $entries = [];
    public function log(string $level, string $message): void {
        $this->entries[] = compact('level', 'message');
        echo "  [{$level}] {$message}\n";
    }
}
class InMemoryDb implements DatabaseInterface {
    public array $executed = [];
    public function execute(string $sql, array $params = []): bool {
        $this->executed[] = compact('sql', 'params');
        echo "  [DB] " . substr($sql, 0, 50) . "\n";
        return true;
    }
}


// ═══════════════════════════════════════════════════════════
// APPROACH A — Container (correct)
// Business class never touches the container
// ═══════════════════════════════════════════════════════════

echo "── Approach A: Container (correct) ──────────────────\n\n";

class OrderServiceA {
    // ✅ All dependencies declared in the constructor — visible, injectable
    public function __construct(
        private PaymentGatewayInterface $gateway,
        private DatabaseInterface       $db,
        private LoggerInterface         $logger
    ) {}

    public function placeOrder(float $amount, string $token, string $email): bool {
        $this->logger->log('INFO', "Order started: {$email}");
        $charged = $this->gateway->charge($amount, $token);
        if ($charged) {
            $this->db->execute('INSERT INTO orders (email, total) VALUES (?,?)', [$email, $amount]);
            $this->logger->log('INFO', "Order placed for {$email}");
        }
        return $charged;
    }
}

// Entry point: container wires the class
$container = new Container();
$container->singleton(PaymentGatewayInterface::class, fn($c) => new FakeGateway());
$container->singleton(DatabaseInterface::class,       fn($c) => new InMemoryDb());
$container->singleton(LoggerInterface::class,          fn($c) => new ConsoleLogger());
$container->singleton(OrderServiceA::class, fn($c) => new OrderServiceA(
    $c->get(PaymentGatewayInterface::class),
    $c->get(DatabaseInterface::class),
    $c->get(LoggerInterface::class)
));

$serviceA = $container->get(OrderServiceA::class);
$serviceA->placeOrder(500.00, 'tok_abc', 'alice@example.com');

echo "\nTesting OrderServiceA (inject fakes — no container needed):\n";
$fakeGateway = new FakeGateway();
$fakeDb      = new InMemoryDb();
$fakeLogger  = new ConsoleLogger();
$testServiceA = new OrderServiceA($fakeGateway, $fakeDb, $fakeLogger);
$testServiceA->placeOrder(250.00, 'tok_test', 'test@example.com');
echo "  Gateway calls: " . count($fakeGateway->calls) . " ✓\n";
echo "  DB executions: " . count($fakeDb->executed) . " ✓\n";
echo "  Log entries:   " . count($fakeLogger->entries) . " ✓\n\n";


// ═══════════════════════════════════════════════════════════
// APPROACH B — Service Locator (anti-pattern)
// Business class calls get() on the container directly
// ═══════════════════════════════════════════════════════════

echo "── Approach B: Service Locator (anti-pattern) ────────\n\n";

class OrderServiceB {
    // ❌ Constructor takes the container — hides all real dependencies
    public function __construct(private Container $container) {}

    public function placeOrder(float $amount, string $token, string $email): bool {
        // ❌ Reaches into the container at runtime — hidden dependencies
        $gateway = $this->container->get(PaymentGatewayInterface::class);
        $db      = $this->container->get(DatabaseInterface::class);
        $logger  = $this->container->get(LoggerInterface::class);

        $logger->log('INFO', "Order started: {$email}");
        $charged = $gateway->charge($amount, $token);
        if ($charged) {
            $db->execute('INSERT INTO orders (email, total) VALUES (?,?)', [$email, $amount]);
            $logger->log('INFO', "Order placed for {$email}");
        }
        return $charged;
    }
}

$container2 = new Container();
$container2->singleton(PaymentGatewayInterface::class, fn($c) => new FakeGateway());
$container2->singleton(DatabaseInterface::class,       fn($c) => new InMemoryDb());
$container2->singleton(LoggerInterface::class,          fn($c) => new ConsoleLogger());

$serviceB = new OrderServiceB($container2);
$serviceB->placeOrder(500.00, 'tok_def', 'bob@example.com');

echo "\nProblem 1 — Constructor signature reveals nothing:\n";
$ref = new ReflectionClass(OrderServiceB::class);
echo "  Constructor params: ";
$params = array_map(fn($p) => $p->getType()->getName(), $ref->getConstructor()->getParameters());
echo implode(', ', $params) . "\n";
echo "  Only 'Container' visible — real deps (Gateway, DB, Logger) are hidden\n\n";

echo "Problem 2 — Testing requires pre-populating the container:\n";
echo "  // To test OrderServiceB, you must:\n";
echo "  \$testContainer = new Container();\n";
echo "  \$testContainer->singleton(PaymentGatewayInterface::class, fn() => new FakeGateway());\n";
echo "  \$testContainer->singleton(DatabaseInterface::class, fn() => new InMemoryDb());\n";
echo "  \$testContainer->singleton(LoggerInterface::class, fn() => new ConsoleLogger());\n";
echo "  \$service = new OrderServiceB(\$testContainer);\n";
echo "  // vs OrderServiceA: new OrderServiceA(\$fakeGateway, \$fakeDb, \$fakeLogger)\n";
echo "  // A is three lines. B requires setting up the full container first.\n\n";

echo "Problem 3 — OrderServiceB is now coupled to Container:\n";
echo "  Change Container's API? Must update OrderServiceB.\n";
echo "  Want to use a different container? Must update OrderServiceB.\n\n";


// ═══════════════════════════════════════════════════════════
// APPROACH C — Disguised Service Locator (static facade)
// Looks clean on the surface — still wrong
// ═══════════════════════════════════════════════════════════

echo "── Approach C: Disguised Service Locator (static) ───\n\n";

class App {
    private static ?Container $container = null;

    public static function setContainer(Container $c): void {
        self::$container = $c;
    }

    public static function get(string $id): mixed {
        return self::$container?->get($id)
            ?? throw new \RuntimeException("Container not initialised");
    }
}

class OrderServiceC {
    // ❌ No constructor at all — looks simpler, but all deps are hidden
    public function placeOrder(float $amount, string $token, string $email): bool {
        // ❌ Static call — global state, completely hidden coupling
        $gateway = App::get(PaymentGatewayInterface::class);
        $db      = App::get(DatabaseInterface::class);
        $logger  = App::get(LoggerInterface::class);

        $logger->log('INFO', "Order started: {$email}");
        $charged = $gateway->charge($amount, $token);
        if ($charged) {
            $db->execute('INSERT INTO orders (email, total) VALUES (?,?)', [$email, $amount]);
        }
        return $charged;
    }
}

$container3 = new Container();
$container3->singleton(PaymentGatewayInterface::class, fn($c) => new FakeGateway());
$container3->singleton(DatabaseInterface::class,       fn($c) => new InMemoryDb());
$container3->singleton(LoggerInterface::class,          fn($c) => new ConsoleLogger());

App::setContainer($container3);

$serviceC = new OrderServiceC();
$serviceC->placeOrder(500.00, 'tok_ghi', 'carol@example.com');

echo "\nWhy this is still wrong:\n";
echo "  OrderServiceC has zero constructor params — looks simpler.\n";
echo "  But: App::get() is a global static call — OrderServiceC depends on\n";
echo "       the App facade AND whatever it resolves. Completely hidden.\n";
echo "  To test: App::setContainer(\$testContainer) must be called globally.\n";
echo "       Run two tests concurrently? Race condition on the global container.\n";
echo "  Framework migration: every class that calls App::get() must be rewritten.\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// The definitive comparison
// ─────────────────────────────────────────────────────────────────────────────

echo "── The definitive comparison ────────────────────────\n\n";
echo "  Question              │ Container (A) │ Locator (B)  │ Static (C)\n";
echo "  ──────────────────────┼───────────────┼──────────────┼────────────\n";
echo "  Deps visible?         │ YES           │ NO           │ NO\n";
echo "  Test in isolation?    │ YES           │ Hard         │ Hard\n";
echo "  Inject fakes?         │ Easily        │ Full setup   │ Global state\n";
echo "  Coupled to container? │ NO            │ YES          │ YES (facade)\n";
echo "  Constructor injection?│ YES           │ NO           │ NO\n\n";

echo "Rule: \$container->get() belongs ONLY in index.php, bootstrap.php,\n";
echo "or a framework service provider. NEVER inside a business logic class.\n";

echo "\n--- Recap ---\n";
echo "Container:        get() at entry point only — classes receive, never fetch.\n";
echo "Service Locator:  get() inside business classes — hides coupling.\n";
echo "Static facade:    same anti-pattern — global state, hidden coupling.\n";
echo "The test is:      can you test the class by passing fakes to the constructor?\n";
echo "                  YES → container pattern. NO → Service Locator.\n";