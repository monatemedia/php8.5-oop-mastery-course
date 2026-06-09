<?php
declare(strict_types=1);

/**
 * Example 02 — Explicit Bindings: Interface → Concrete
 * ------------------------------------------------------
 * Zero-config auto-wiring only works for concrete classes.
 * When your constructors type-hint against interfaces (which they should —
 * Course Philosophy Rule 3), you must tell PHP-DI which concrete class to use.
 *
 * This example shows:
 *   A. The autowire() function — interface mapped to concrete class
 *   B. Inline definitions vs definitions file
 *   C. Multiple bindings at once with addDefinitions()
 *   D. Verifying that the correct concrete class was injected
 *
 * Requires: composer require php-di/php-di
 */

require __DIR__ . '/../../../../vendor/autoload.php';

use DI\ContainerBuilder;
use function DI\autowire;
use function DI\create;

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Explicit Bindings — Interface → Concrete           ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Interfaces and multiple concrete implementations
// ─────────────────────────────────────────────────────────────────────────────

interface DatabaseInterface {
    public function query(string $sql, array $params = []): array;
    public function execute(string $sql, array $params = []): bool;
    public function getDriver(): string;
}

interface LoggerInterface {
    public function log(string $level, string $message): void;
    public function getChannel(): string;
}

interface CacheInterface {
    public function get(string $key): mixed;
    public function set(string $key, mixed $value): void;
}

interface MailerInterface {
    public function send(string $to, string $subject, string $body): bool;
    public function getTransport(): string;
}

// Implementation A: SQLite (development)
class SQLiteDatabase implements DatabaseInterface {
    private array $data = [
        1 => ['id' => 1, 'name' => 'Widget Pro', 'price' => 29999],
    ];
    public function query(string $sql, array $params = []): array {
        echo "  [SQLite] query\n";
        return !empty($params) && is_int($params[0])
            ? (isset($this->data[$params[0]]) ? [$this->data[$params[0]]] : [])
            : array_values($this->data);
    }
    public function execute(string $sql, array $params = []): bool {
        echo "  [SQLite] execute\n";
        return true;
    }
    public function getDriver(): string { return 'sqlite'; }
}

// Implementation B: MySQL (production-style)
class MySQLDatabase implements DatabaseInterface {
    public function __construct(private string $dsn = 'mysql:host=localhost') {}
    public function query(string $sql, array $params = []): array {
        echo "  [MySQL] query\n";
        return [];
    }
    public function execute(string $sql, array $params = []): bool {
        echo "  [MySQL] execute\n";
        return true;
    }
    public function getDriver(): string { return 'mysql'; }
}

class ConsoleLogger implements LoggerInterface {
    public function log(string $level, string $message): void {
        echo "  [{$level}] {$message}\n";
    }
    public function getChannel(): string { return 'console'; }
}

class FileLogger implements LoggerInterface {
    public function __construct(private string $path = '/tmp/app.log') {}
    public function log(string $level, string $message): void {
        echo "  [FILE:{$level}] {$message}\n";
    }
    public function getChannel(): string { return 'file'; }
}

class NullLogger implements LoggerInterface {
    public function log(string $level, string $message): void {}
    public function getChannel(): string { return 'null'; }
}

class ArrayCache implements CacheInterface {
    private array $store = [];
    public function get(string $key): mixed  { return $this->store[$key] ?? null; }
    public function set(string $key, mixed $v): void { $this->store[$key] = $v; }
}

class ConsoleMailer implements MailerInterface {
    public function send(string $to, string $subject, string $body): bool {
        echo "  [MAIL] To: {$to} | {$subject}\n";
        return true;
    }
    public function getTransport(): string { return 'console'; }
}

// Service classes
class ProductRepository {
    public function __construct(
        private DatabaseInterface $db,
        private CacheInterface    $cache,
        private LoggerInterface   $logger
    ) {}
    public function findAll(): array {
        $this->logger->log('INFO', 'ProductRepository::findAll()');
        return $this->db->query('SELECT * FROM products');
    }
    public function getDbDriver(): string     { return $this->db->getDriver(); }
    public function getLoggerChannel(): string { return $this->logger->getChannel(); }
}

class OrderService {
    public function __construct(
        private ProductRepository $products,
        private MailerInterface   $mailer,
        private LoggerInterface   $logger
    ) {}
    public function place(string $email): bool {
        $this->logger->log('INFO', "Order for {$email}");
        $this->mailer->send($email, 'Order Confirmed', 'Thank you');
        return true;
    }
    public function getMailerTransport(): string  { return $this->mailer->getTransport(); }
    public function getLoggerChannel(): string    { return $this->logger->getChannel(); }
}


// ═══════════════════════════════════════════════════════════
// PART A — autowire(): the primary binding function
// ═══════════════════════════════════════════════════════════

echo "── Part A: autowire() — interface to concrete ────────\n\n";

$builder = new ContainerBuilder();
$builder->addDefinitions([
    // Map each interface to a concrete class name
    // PHP-DI auto-wires the concrete class's own constructor
    DatabaseInterface::class => autowire(SQLiteDatabase::class),
    LoggerInterface::class   => autowire(ConsoleLogger::class),
    CacheInterface::class    => autowire(ArrayCache::class),
    MailerInterface::class   => autowire(ConsoleMailer::class),
]);
$container = $builder->build();

echo "Resolving OrderService:\n";
$service = $container->get(OrderService::class);
echo "\nVerifying injected implementations:\n";
echo "  DB driver:       " . $container->get(ProductRepository::class)->getDbDriver() . "\n";
echo "  Logger channel:  " . $service->getLoggerChannel() . "\n";
echo "  Mailer transport: " . $service->getMailerTransport() . "\n\n";
$service->place('alice@example.com');


// ═══════════════════════════════════════════════════════════
// PART B — Swapping implementations (no service code changes)
// ═══════════════════════════════════════════════════════════

echo "\n── Part B: Swapping implementations ─────────────────\n\n";

// Only the bindings change — OrderService, ProductRepository unchanged
$builder2 = new ContainerBuilder();
$builder2->addDefinitions([
    DatabaseInterface::class => autowire(MySQLDatabase::class),
    LoggerInterface::class   => autowire(FileLogger::class),
    CacheInterface::class    => autowire(ArrayCache::class),
    MailerInterface::class   => autowire(ConsoleMailer::class),
]);
$container2 = $builder2->build();

$service2 = $container2->get(OrderService::class);
echo "Verifying new implementations:\n";
echo "  DB driver:      " . $container2->get(ProductRepository::class)->getDbDriver() . "\n";
echo "  Logger channel: " . $service2->getLoggerChannel() . "\n\n";
$service2->place('bob@example.com');

echo "\nKey point: OrderService and ProductRepository were NOT modified.\n";
echo "Only the definitions changed. This is DIP + composition in action.\n\n";


// ═══════════════════════════════════════════════════════════
// PART C — Inline definitions vs definitions file
// ═══════════════════════════════════════════════════════════

echo "── Part C: Definitions file pattern ─────────────────\n\n";

// In production, definitions live in a separate file:
// config/services.php  ← returns the definitions array

// Simulating a definitions file inline for this example:
$definitions = [
    DatabaseInterface::class => autowire(SQLiteDatabase::class),
    LoggerInterface::class   => autowire(ConsoleLogger::class),
    CacheInterface::class    => autowire(ArrayCache::class),
    MailerInterface::class   => autowire(ConsoleMailer::class),
];

// In your entry point (index.php), you would write:
// $builder->addDefinitions(__DIR__ . '/../config/services.php');
// The file returns the array above.

$builder3   = new ContainerBuilder();
$builder3->addDefinitions($definitions);
$container3 = $builder3->build();
$service3   = $container3->get(OrderService::class);
$service3->place('carol@example.com');

echo "\nDefinitions file pattern:\n";
echo "  index.php:          \$builder->addDefinitions(__DIR__ . '/config/services.php');\n";
echo "  config/services.php: return [ DatabaseInterface::class => autowire(...), ... ];\n\n";
echo "  Rule 1: ALL getenv(), DSNs, and env-specific logic live in services.php.\n";
echo "  Application classes never call getenv() directly.\n\n";


// ═══════════════════════════════════════════════════════════
// PART D — create() for constructor parameter overrides
// ═══════════════════════════════════════════════════════════

echo "── Part D: create() with constructor param override ─\n\n";

// FileLogger needs a $path string — autowire() cannot resolve it.
// create() lets us pass explicit constructor args.

$builder4 = new ContainerBuilder();
$builder4->addDefinitions([
    DatabaseInterface::class => autowire(SQLiteDatabase::class),
    CacheInterface::class    => autowire(ArrayCache::class),
    MailerInterface::class   => autowire(ConsoleMailer::class),

    // FileLogger has constructor: __construct(private string $path = '/tmp/app.log')
    // The default value means autowire() would work here too,
    // but create()->constructor() lets us override it explicitly:
    LoggerInterface::class => create(FileLogger::class)
        ->constructor('/var/log/orders.log'),
]);
$container4 = $builder4->build();

$repo = $container4->get(ProductRepository::class);
echo "Logger channel: " . $repo->getLoggerChannel() . "\n";
echo "(FileLogger injected with path=/var/log/orders.log)\n\n";

echo "--- Recap ---\n";
echo "autowire(Concrete::class):    resolve Concrete with auto-wiring.\n";
echo "create(Concrete::class):      resolve Concrete with optional explicit args.\n";
echo "  ->constructor(arg1, arg2):  override constructor parameters.\n";
echo "Both map an interface key to a concrete implementation.\n";
echo "addDefinitions([...]):         pass a definitions array or file path.\n";
echo "Rule 1: all config (env vars, paths, keys) lives in the definitions file.\n";