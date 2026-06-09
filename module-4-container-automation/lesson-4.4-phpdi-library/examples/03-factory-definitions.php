<?php
declare(strict_types=1);

/**
 * Example 03 — Factory Definitions
 * ----------------------------------
 * factory() is PHP-DI's solution for classes that cannot be auto-wired:
 *   - Classes with primitive constructor params (string DSN, int port, etc.)
 *   - Environment-based conditional wiring (prod vs dev vs test)
 *   - Decorator pattern (wrapping one implementation around another)
 *   - Classes that need runtime data at construction time
 *
 * Course Philosophy Rule 1: Config belongs at the entry point.
 * Every factory() call in this example would live in config/services.php —
 * the only file in the application that reads getenv() or makes env decisions.
 *
 * Requires: composer require php-di/php-di
 */

require __DIR__ . '/../../../../vendor/autoload.php';

use DI\ContainerBuilder;
use function DI\factory;
use function DI\autowire;
use function DI\create;

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Factory Definitions                                ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Interfaces and classes
// ─────────────────────────────────────────────────────────────────────────────

interface DatabaseInterface {
    public function query(string $sql): array;
    public function getDescription(): string;
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
    public function send(string $to, string $subject): bool;
    public function getName(): string;
}
interface CacheInterface {
    public function get(string $key): mixed;
    public function set(string $key, mixed $value): void;
}

// Classes with primitive constructor params
class MySQLDatabase implements DatabaseInterface {
    public function __construct(
        private string $dsn,                     // ← primitive — cannot auto-wire
        private string $username = 'root',       // ← primitive with default
        private int    $port     = 3306          // ← primitive with default
    ) {}
    public function query(string $sql): array {
        echo "  [MySQL:{$this->port}] query\n";
        return [];
    }
    public function getDescription(): string {
        return "MySQL(dsn={$this->dsn}, port={$this->port})";
    }
}

class FileLogger implements LoggerInterface {
    public function __construct(private string $path, private string $level = 'DEBUG') {}
    public function log(string $level, string $message): void {
        echo "  [FILE:{$level}] {$message}\n";
    }
    public function getName(): string { return "file({$this->path})"; }
}

class ConsoleLogger implements LoggerInterface {
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
    public function __construct(private string $apiKey, private string $mode = 'live') {}
    public function charge(float $amount, string $token): bool {
        echo "  [STRIPE:{$this->mode}] charged R{$amount}\n";
        return true;
    }
    public function getName(): string { return "stripe({$this->mode})"; }
}

class FakeGateway implements PaymentGatewayInterface {
    public function charge(float $amount, string $token): bool {
        echo "  [FAKE] charged R{$amount} (test)\n";
        return true;
    }
    public function getName(): string { return 'fake'; }
}

class SmtpMailer implements MailerInterface {
    public function __construct(private string $host, private int $port = 587) {}
    public function send(string $to, string $subject): bool {
        echo "  [SMTP:{$this->host}:{$this->port}] To: {$to}\n";
        return true;
    }
    public function getName(): string { return "smtp({$this->host})"; }
}

class LogMailer implements MailerInterface {
    public function send(string $to, string $subject): bool {
        echo "  [LOG-MAIL] To: {$to} | {$subject}\n";
        return true;
    }
    public function getName(): string { return 'log-mailer'; }
}

class ArrayCache implements CacheInterface {
    private array $store = [];
    public function get(string $key): mixed  { return $this->store[$key] ?? null; }
    public function set(string $key, mixed $v): void { $this->store[$key] = $v; }
}

// Decorator — wraps any PaymentGatewayInterface to add logging
class LoggingGateway implements PaymentGatewayInterface {
    public function __construct(
        private PaymentGatewayInterface $inner,
        private LoggerInterface         $logger
    ) {}
    public function charge(float $amount, string $token): bool {
        $this->logger->log('INFO', "Charging R{$amount} via {$this->inner->getName()}");
        $result = $this->inner->charge($amount, $token);
        $this->logger->log('INFO', "Result: " . ($result ? 'ok' : 'fail'));
        return $result;
    }
    public function getName(): string { return 'logging(' . $this->inner->getName() . ')'; }
}

// Service
class CheckoutService {
    public function __construct(
        private DatabaseInterface       $db,
        private PaymentGatewayInterface $gateway,
        private MailerInterface         $mailer,
        private LoggerInterface         $logger,
        private CacheInterface          $cache
    ) {}
    public function checkout(string $email, float $amount): bool {
        $this->logger->log('INFO', "Checkout for {$email}: R{$amount}");
        $charged = $this->gateway->charge($amount, 'tok_test');
        if ($charged) $this->mailer->send($email, 'Confirmed');
        return $charged;
    }
    public function summary(): string {
        return sprintf(
            "db=%s | gateway=%s | mailer=%s | logger=%s",
            $this->db->getDescription(),
            $this->gateway->getName(),
            $this->mailer->getName(),
            $this->logger->getName()
        );
    }
}


// ═══════════════════════════════════════════════════════════
// PART A — factory() for primitive constructor params
// ═══════════════════════════════════════════════════════════

echo "── Part A: factory() for primitive constructor params ─\n\n";

$builder = new ContainerBuilder();
$builder->addDefinitions([
    // MySQLDatabase needs string $dsn — factory reads it from env
    // Course Philosophy Rule 1: getenv() lives HERE, not in MySQLDatabase
    DatabaseInterface::class => factory(function () {
        $dsn  = getenv('DATABASE_URL') ?: 'mysql:host=localhost;dbname=shop';
        $user = getenv('DB_USER')      ?: 'root';
        $port = (int)(getenv('DB_PORT') ?: 3306);
        return new MySQLDatabase($dsn, $user, $port);
    }),

    LoggerInterface::class   => factory(function () {
        $path  = getenv('LOG_PATH')  ?: '/tmp/app.log';
        $level = getenv('LOG_LEVEL') ?: 'INFO';
        return new FileLogger($path, $level);
    }),

    MailerInterface::class   => factory(function () {
        $host = getenv('SMTP_HOST') ?: 'localhost';
        $port = (int)(getenv('SMTP_PORT') ?: 587);
        return new SmtpMailer($host, $port);
    }),

    CacheInterface::class    => autowire(ArrayCache::class),

    PaymentGatewayInterface::class => factory(function () {
        return new FakeGateway(); // swap to StripeGateway in production
    }),
]);

$container = $builder->build();
$service   = $container->get(CheckoutService::class);

echo "Summary: " . $service->summary() . "\n\n";
$service->checkout('alice@example.com', 500.00);


// ═══════════════════════════════════════════════════════════
// PART B — Environment-based conditional wiring
// ═══════════════════════════════════════════════════════════

echo "\n── Part B: Environment-based conditional wiring ──────\n\n";

// The factory receives the container as first arg — can call get() for sub-deps
$appEnv = getenv('APP_ENV') ?: 'development';
echo "APP_ENV = '{$appEnv}'\n\n";

$builder2 = new ContainerBuilder();
$builder2->addDefinitions([
    DatabaseInterface::class => factory(function () {
        $env = getenv('APP_ENV') ?: 'development';
        return $env === 'production'
            ? new MySQLDatabase(getenv('DATABASE_URL') ?: 'mysql:host=prod-db')
            : new MySQLDatabase('sqlite::memory:');
    }),

    LoggerInterface::class => factory(function () {
        $env = getenv('APP_ENV') ?: 'development';
        return match($env) {
            'production'  => new FileLogger(getenv('LOG_PATH') ?: '/var/log/app.log'),
            'test'        => new NullLogger(),
            default       => new ConsoleLogger(),
        };
    }),

    PaymentGatewayInterface::class => factory(function () {
        $env = getenv('APP_ENV') ?: 'development';
        return $env === 'production'
            ? new StripeGateway(getenv('STRIPE_KEY') ?: 'sk_placeholder', 'live')
            : new FakeGateway();
    }),

    MailerInterface::class => factory(function () {
        $env = getenv('APP_ENV') ?: 'development';
        return $env === 'test' ? new LogMailer() : new SmtpMailer('localhost');
    }),

    CacheInterface::class => autowire(ArrayCache::class),
]);

$container2 = $builder2->build();
$service2   = $container2->get(CheckoutService::class);
echo "Wired for '{$appEnv}':\n";
echo "  " . $service2->summary() . "\n\n";
$service2->checkout('bob@example.com', 250.00);


// ═══════════════════════════════════════════════════════════
// PART C — Decorator pattern using factory() with container arg
// ═══════════════════════════════════════════════════════════

echo "\n── Part C: Decorator pattern ─────────────────────────\n\n";

$builder3 = new ContainerBuilder();
$builder3->addDefinitions([
    DatabaseInterface::class => factory(function () {
        return new MySQLDatabase('mysql:host=localhost');
    }),
    LoggerInterface::class   => autowire(ConsoleLogger::class),
    CacheInterface::class    => autowire(ArrayCache::class),
    MailerInterface::class   => autowire(LogMailer::class),

    // LoggingGateway wraps FakeGateway — factory gets the container to resolve deps
    PaymentGatewayInterface::class => factory(function (\Psr\Container\ContainerInterface $c) {
        return new LoggingGateway(
            new FakeGateway(),
            $c->get(LoggerInterface::class)  // resolved from container
        );
    }),
]);

$container3 = $builder3->build();
$service3   = $container3->get(CheckoutService::class);

echo "Gateway with logging decorator:\n";
echo "  " . $service3->summary() . "\n\n";
$service3->checkout('carol@example.com', 750.00);

echo "\nKey point: LoggingGateway wraps FakeGateway without modifying either class.\n";
echo "The factory() call is the ONLY place that knows about the decorator pattern.\n";
echo "CheckoutService just receives a PaymentGatewayInterface — no awareness of wrapping.\n\n";

echo "--- Recap ---\n";
echo "factory(fn()): for classes with primitive params — reads getenv() inside.\n";
echo "factory(fn(ContainerInterface \$c)): factory that resolves other services.\n";
echo "Environment conditionals: match/if inside factory — one place, clear logic.\n";
echo "Decorator: factory wraps one binding around another.\n";
echo "Rule 1: ALL getenv() calls live in factory definitions — never in service classes.\n";