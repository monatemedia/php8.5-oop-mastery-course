<?php
declare(strict_types=1);

/**
 * Example 04 — Explicit Fallback: Binding Overrides Auto-wiring
 * ---------------------------------------------------------------
 * Auto-wiring resolves concrete classes automatically via Reflection.
 * Explicit bindings tell the container: "when asked for THIS, return THAT instead."
 *
 * The three scenarios where explicit bindings are essential:
 *
 *   Scenario A: Interface → concrete class
 *               (container cannot guess which implementation to use)
 *
 *   Scenario B: Primitive constructor params
 *               (strings, ints, arrays — container cannot auto-wire these)
 *
 *   Scenario C: Environment-based or conditional wiring
 *               (production vs development vs test implementations)
 *
 * Course Philosophy Rule 1: Config belongs at the entry point.
 * Explicit bindings ARE the config. They live at the composition root only.
 * The services themselves never know whether they are in production or test.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Explicit Fallback — Binding Overrides Auto-wiring  ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// AutowiringContainer (complete, with circular detection)
// ─────────────────────────────────────────────────────────────────────────────

class CircularDependencyException extends \RuntimeException {}

class AutowiringContainer {
    private array $bindings  = [];
    private array $instances = [];
    private array $resolving = [];

    public function bind(string $id, string|callable $target): void {
        $this->bindings[$id] = $target;
    }
    public function instance(string $id, object $obj): void {
        $this->instances[$id] = $obj;
    }
    public function get(string $id): object {
        if (isset($this->instances[$id])) return $this->instances[$id];
        if (isset($this->bindings[$id])) {
            $binding = $this->bindings[$id];
            if (is_callable($binding)) {
                return $this->instances[$id] = $binding($this);
            }
            return $this->instances[$id] = $this->resolve($binding);
        }
        return $this->instances[$id] = $this->resolve($id);
    }
    public function has(string $id): bool {
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }
    private function resolve(string $class): object {
        if (isset($this->resolving[$class])) {
            $chain = implode(' → ', array_keys($this->resolving)) . ' → ' . $class;
            throw new CircularDependencyException("Circular dependency: {$chain}");
        }
        $ref = new ReflectionClass($class);
        if (!$ref->isInstantiable()) {
            throw new \RuntimeException("Not instantiable: {$class}. Register an explicit binding.");
        }
        $this->resolving[$class] = true;
        try {
            $ctor = $ref->getConstructor();
            if ($ctor === null || count($ctor->getParameters()) === 0) {
                return new $class();
            }
            $deps = [];
            foreach ($ctor->getParameters() as $param) {
                $type = $param->getType();
                if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                    $deps[] = $this->get($type->getName());
                } elseif ($param->isOptional()) {
                    $deps[] = $param->getDefaultValue();
                } else {
                    throw new \RuntimeException(
                        "Cannot auto-wire '\${$param->getName()}' in '{$class}': " .
                        "primitive type. Register an explicit factory or use instance()."
                    );
                }
            }
            $instance = $ref->newInstanceArgs($deps);
        } finally {
            unset($this->resolving[$class]);
        }
        return $instance;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// The service classes used throughout
// ─────────────────────────────────────────────────────────────────────────────

interface DatabaseInterface {
    public function query(string $sql, array $params = []): array;
    public function execute(string $sql, array $params = []): bool;
    public function getDriver(): string;
}
interface LoggerInterface {
    public function log(string $level, string $message): void;
    public function getName(): string;
}
interface PaymentGatewayInterface {
    public function charge(float $amount, string $token): bool;
    public function getName(): string;
}
interface MailerInterface {
    public function send(string $to, string $subject, string $body): bool;
}
interface CacheInterface {
    public function get(string $key): mixed;
    public function set(string $key, mixed $value): void;
}

// Multiple implementations of the same interface
class MySQLDatabase implements DatabaseInterface {
    public function __construct(private string $dsn, private string $user = 'root') {
        echo "  [NEW] MySQLDatabase (dsn={$dsn})\n";
    }
    public function query(string $sql, array $params = []): array { return []; }
    public function execute(string $sql, array $params = []): bool { return true; }
    public function getDriver(): string { return 'mysql'; }
}
class SQLiteDatabase implements DatabaseInterface {
    public function __construct(private string $path = ':memory:') {
        echo "  [NEW] SQLiteDatabase (path={$path})\n";
    }
    public function query(string $sql, array $params = []): array { return []; }
    public function execute(string $sql, array $params = []): bool { return true; }
    public function getDriver(): string { return 'sqlite'; }
}
class FileLogger implements LoggerInterface {
    public function __construct(private string $path) {
        echo "  [NEW] FileLogger (path={$path})\n";
    }
    public function log(string $level, string $message): void {
        echo "  [FILE:{$level}] {$message}\n";
    }
    public function getName(): string { return 'file'; }
}
class ConsoleLogger implements LoggerInterface {
    public function __construct() { echo "  [NEW] ConsoleLogger\n"; }
    public function log(string $level, string $message): void {
        echo "  [CONSOLE:{$level}] {$message}\n";
    }
    public function getName(): string { return 'console'; }
}
class NullLogger implements LoggerInterface {
    public function log(string $level, string $message): void {}
    public function getName(): string { return 'null'; }
}
class StripeGateway implements PaymentGatewayInterface {
    public function __construct(private string $apiKey) {
        echo "  [NEW] StripeGateway\n";
    }
    public function charge(float $amount, string $token): bool {
        echo "  [STRIPE] Charged R{$amount}\n";
        return true;
    }
    public function getName(): string { return 'stripe'; }
}
class FakeGateway implements PaymentGatewayInterface {
    public function charge(float $amount, string $token): bool {
        echo "  [FAKE] Charged R{$amount} (test)\n";
        return true;
    }
    public function getName(): string { return 'fake'; }
}
class ConsoleMailer implements MailerInterface {
    public function send(string $to, string $subject, string $body): bool {
        echo "  [MAIL] To: {$to} | {$subject}\n";
        return true;
    }
}
class NullMailer implements MailerInterface {
    public function send(string $to, string $subject, string $body): bool { return true; }
}
class ArrayCache implements CacheInterface {
    private array $store = [];
    public function get(string $key): mixed { return $this->store[$key] ?? null; }
    public function set(string $key, mixed $value): void { $this->store[$key] = $value; }
}

// The service (always the same — only wiring changes)
class OrderService {
    public function __construct(
        private DatabaseInterface       $db,
        private PaymentGatewayInterface $gateway,
        private MailerInterface         $mailer,
        private LoggerInterface         $logger
    ) {}

    public function placeOrder(float $amount, string $token, string $email): bool {
        $this->logger->log('INFO', "Order for {$email}: R{$amount}");
        $charged = $this->gateway->charge($amount, $token);
        if ($charged) {
            $this->db->execute('INSERT INTO orders...');
            $this->mailer->send($email, 'Order Confirmed', "Total: R{$amount}");
        }
        return $charged;
    }

    public function getDbDriver(): string        { return $this->db->getDriver(); }
    public function getGatewayName(): string     { return $this->gateway->getName(); }
    public function getLoggerName(): string      { return $this->logger->getName(); }
}


// ═══════════════════════════════════════════════════════════
// SCENARIO A — Interface → concrete binding
// ═══════════════════════════════════════════════════════════

echo "── Scenario A: Interface → concrete binding ─────────\n\n";

echo "Without interface bindings, the container cannot resolve OrderService:\n";
$containerA = new AutowiringContainer();
try {
    $containerA->get(OrderService::class);
} catch (\RuntimeException $e) {
    echo "  RuntimeException: " . $e->getMessage() . "\n\n";
}

echo "With explicit interface bindings:\n";
$containerA2 = new AutowiringContainer();
$containerA2->bind(DatabaseInterface::class,       SQLiteDatabase::class);
$containerA2->bind(PaymentGatewayInterface::class, FakeGateway::class);
$containerA2->bind(MailerInterface::class,          ConsoleMailer::class);
$containerA2->bind(LoggerInterface::class,          ConsoleLogger::class);

$serviceA = $containerA2->get(OrderService::class);
$serviceA->placeOrder(100.00, 'tok_test', 'alice@example.com');
echo "  DB driver: " . $serviceA->getDbDriver() . "\n\n";


// ═══════════════════════════════════════════════════════════
// SCENARIO B — Primitive constructor params
// ═══════════════════════════════════════════════════════════

echo "── Scenario B: Primitive constructor params ─────────\n\n";

echo "MySQLDatabase needs string \$dsn — cannot auto-wire:\n";
$containerB = new AutowiringContainer();
$containerB->bind(LoggerInterface::class,          ConsoleLogger::class);
$containerB->bind(PaymentGatewayInterface::class,  FakeGateway::class);
$containerB->bind(MailerInterface::class,           ConsoleMailer::class);
$containerB->bind(DatabaseInterface::class,        MySQLDatabase::class); // needs $dsn string

try {
    $containerB->get(OrderService::class);
} catch (\RuntimeException $e) {
    echo "  RuntimeException: " . $e->getMessage() . "\n\n";
}

echo "Fix 1: instance() with a pre-built object:\n";
$containerB2 = new AutowiringContainer();
$containerB2->instance(DatabaseInterface::class, new MySQLDatabase('mysql:host=localhost;dbname=shop'));
$containerB2->bind(LoggerInterface::class,         ConsoleLogger::class);
$containerB2->bind(PaymentGatewayInterface::class, FakeGateway::class);
$containerB2->bind(MailerInterface::class,          ConsoleMailer::class);

$serviceB = $containerB2->get(OrderService::class);
echo "  DB driver: " . $serviceB->getDbDriver() . "\n\n";

echo "Fix 2: callable factory (reads from environment):\n";
$containerB3 = new AutowiringContainer();
$containerB3->bind(DatabaseInterface::class, fn($c) => new MySQLDatabase(
    getenv('DATABASE_URL') ?: 'mysql:host=localhost;dbname=shop'
));
$containerB3->bind(LoggerInterface::class,         ConsoleLogger::class);
$containerB3->bind(PaymentGatewayInterface::class, FakeGateway::class);
$containerB3->bind(MailerInterface::class,          ConsoleMailer::class);

$serviceB2 = $containerB3->get(OrderService::class);
echo "  DB driver: " . $serviceB2->getDbDriver() . "\n\n";


// ═══════════════════════════════════════════════════════════
// SCENARIO C — Environment-based conditional wiring
// ═══════════════════════════════════════════════════════════

echo "── Scenario C: Environment-based conditional wiring ─\n\n";

// Simulated environment variable
$appEnv = 'development'; // would be getenv('APP_ENV') in real code

function buildContainer(string $env): AutowiringContainer {
    $c = new AutowiringContainer();

    // Database: SQLite for development/test, MySQL for production
    if ($env === 'production') {
        $c->instance(DatabaseInterface::class,
            new MySQLDatabase(getenv('DATABASE_URL') ?: 'mysql:host=prod-db;dbname=shop')
        );
    } else {
        $c->instance(DatabaseInterface::class, new SQLiteDatabase(':memory:'));
    }

    // Logger: FileLogger for production, ConsoleLogger for development
    if ($env === 'production') {
        $c->instance(LoggerInterface::class,
            new FileLogger(getenv('LOG_PATH') ?: '/var/log/app.log')
        );
    } else {
        $c->bind(LoggerInterface::class, ConsoleLogger::class);
    }

    // Gateway: real Stripe in production, fake in development/test
    if ($env === 'production') {
        $c->instance(PaymentGatewayInterface::class,
            new StripeGateway(getenv('STRIPE_SECRET') ?: 'sk_test_placeholder')
        );
    } else {
        $c->bind(PaymentGatewayInterface::class, FakeGateway::class);
    }

    // Mailer: real mailer in production, null (silent) in test
    if ($env === 'test') {
        $c->bind(MailerInterface::class, NullMailer::class);
    } else {
        $c->bind(MailerInterface::class, ConsoleMailer::class);
    }

    return $c;
}

foreach (['development', 'production', 'test'] as $env) {
    echo "--- Environment: {$env} ---\n";
    $c       = buildContainer($env);
    $service = $c->get(OrderService::class);
    echo "  DB={$service->getDbDriver()}, gateway={$service->getGatewayName()}, logger={$service->getLoggerName()}\n";
    $service->placeOrder(250.00, 'tok_abc', 'bob@example.com');
    echo "\n";
}

echo "Key point (Course Philosophy Rule 1):\n";
echo "  OrderService is IDENTICAL in all three environments.\n";
echo "  The wiring config (buildContainer) is at the entry point.\n";
echo "  The service never calls getenv(), never reads config files.\n";
echo "  Everything it needs comes from the constructor.\n";

echo "\n--- Recap ---\n";
echo "Interface bindings:  required — container cannot guess which concrete class.\n";
echo "Primitive params:    use instance() or callable factory — not auto-wirable.\n";
echo "Environment wiring:  conditional bind()/instance() in the composition root.\n";
echo "Explicit overrides:  take precedence over auto-wiring for the same id.\n";
echo "Rule 1 connection:   all config lives in buildContainer() — zero in the service.\n";