<?php
declare(strict_types=1);

/**
 * Example 04 — Environment-Based Bindings
 * ------------------------------------------
 * Run via PHPUnit:
 *   ./vendor/bin/phpunit module-6-object-lifecycle-and-state/lesson-6.5-factory-definitions/examples/04-environment-bindings.php
 *
 * The environment-based binding pattern selects which implementation to
 * wire based on an environment variable (APP_ENV). This keeps all wiring
 * logic at the composition root; the implementations themselves are
 * completely unaware of the environment.
 *
 * This file covers:
 *
 *   SCENARIO 1 — MailerInterface: three implementations for three environments
 *   SCENARIO 2 — CacheInterface: Redis in production, array in development/test
 *   SCENARIO 3 — Combined: a full mini-application wired three ways
 *   SCENARIO 4 — Tests that verify each environment produces the correct implementation
 *
 * Key design rule (from COURSE_PHILOSOPHY.md Rule 1):
 *   "Config belongs at the entry point, not in core logic."
 *   The APP_ENV check belongs HERE — in the container definition —
 *   not inside SmtpMailer, OrderService, or any business class.
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// SCENARIO 1 — MailerInterface
// ─────────────────────────────────────────────────────────────────────────────

interface MailerInterface
{
    public function send(string $to, string $subject, string $body): bool;
    public function getLastRecipient(): ?string;
}

/**
 * Production mailer: sends via SMTP (simulated).
 * In production this would call an SMTP library or a transactional email API.
 */
class SmtpMailer implements MailerInterface
{
    private ?string $lastRecipient = null;

    public function __construct(
        private readonly string $host,
        private readonly int    $port,
    ) {}

    public function send(string $to, string $subject, string $body): bool
    {
        // Simulated send — in production, calls SMTP
        $this->lastRecipient = $to;
        return true;
    }

    public function getLastRecipient(): ?string { return $this->lastRecipient; }
    public function getHost(): string           { return $this->host; }
    public function getPort(): int              { return $this->port; }
}

/**
 * Development mailer: logs emails to a file/array instead of sending.
 * Useful for local development where you want to see what would be sent.
 */
class LogMailer implements MailerInterface
{
    private array   $log           = [];
    private ?string $lastRecipient = null;

    public function send(string $to, string $subject, string $body): bool
    {
        $this->log[]         = compact('to', 'subject', 'body');
        $this->lastRecipient = $to;
        return true;
    }

    public function getLastRecipient(): ?string { return $this->lastRecipient; }
    public function getLog(): array             { return $this->log; }
    public function getLogCount(): int          { return count($this->log); }
}

/**
 * Test/CI mailer: discards all emails silently.
 * Prevents any real emails being sent during automated tests.
 */
class NullMailer implements MailerInterface
{
    private int $callCount = 0;

    public function send(string $to, string $subject, string $body): bool
    {
        $this->callCount++; // counts calls but sends nothing
        return true;
    }

    public function getLastRecipient(): ?string { return null; } // null: nothing was sent
    public function getCallCount(): int         { return $this->callCount; }
}

// ─────────────────────────────────────────────────────────────────────────────
// SCENARIO 2 — CacheInterface
// ─────────────────────────────────────────────────────────────────────────────

interface CacheInterface
{
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, int $ttl = 3600): void;
    public function has(string $key): bool;
    public function delete(string $key): void;
}

/**
 * Production cache: delegates to Redis (simulated).
 */
class RedisCache implements CacheInterface
{
    private array $store = []; // Simulates Redis in tests

    public function __construct(private readonly string $host, private readonly int $port) {}

    public function get(string $key): mixed          { return $this->store[$key] ?? null; }
    public function set(string $key, mixed $value, int $ttl = 3600): void { $this->store[$key] = $value; }
    public function has(string $key): bool           { return isset($this->store[$key]); }
    public function delete(string $key): void        { unset($this->store[$key]); }
    public function getHost(): string                { return $this->host; }
}

/**
 * Development/test cache: in-memory array, no network required.
 */
class ArrayCache implements CacheInterface
{
    private array $store = [];

    public function get(string $key): mixed          { return $this->store[$key] ?? null; }
    public function set(string $key, mixed $value, int $ttl = 3600): void { $this->store[$key] = $value; }
    public function has(string $key): bool           { return isset($this->store[$key]); }
    public function delete(string $key): void        { unset($this->store[$key]); }
}

// ─────────────────────────────────────────────────────────────────────────────
// SCENARIO 3 — The factory function that selects based on APP_ENV
// ─────────────────────────────────────────────────────────────────────────────

/**
 * A container factory that builds a MailerInterface based on the APP_ENV value.
 *
 * PHP-DI equivalent:
 *
 *   MailerInterface::class => factory(function(LoggerInterface $logger): MailerInterface {
 *       return match(getenv('APP_ENV')) {
 *           'production' => new SmtpMailer(
 *               host: getenv('SMTP_HOST') ?: 'localhost',
 *               port: (int)(getenv('SMTP_PORT') ?: 587),
 *           ),
 *           'test'       => new NullMailer(),
 *           default      => new LogMailer($logger),
 *       };
 *   }),
 */
class MailerFactory
{
    public static function create(string $appEnv, string $smtpHost = 'localhost', int $smtpPort = 587): MailerInterface
    {
        return match($appEnv) {
            'production' => new SmtpMailer(host: $smtpHost, port: $smtpPort),
            'test'       => new NullMailer(),
            default      => new LogMailer(),   // development and anything else
        };
    }
}

class CacheFactory
{
    public static function create(string $appEnv, string $redisHost = '127.0.0.1', int $redisPort = 6379): CacheInterface
    {
        return match($appEnv) {
            'production' => new RedisCache(host: $redisHost, port: $redisPort),
            default      => new ArrayCache(),  // development and test
        };
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// A simple container for this example
// ─────────────────────────────────────────────────────────────────────────────

class EnvContainer
{
    private array $bindings = [];

    public function bind(string $id, callable $factory): void
    {
        $this->bindings[$id] = $factory;
    }

    public function get(string $id): object
    {
        if (!isset($this->bindings[$id])) {
            throw new \RuntimeException("No binding for: {$id}");
        }
        return ($this->bindings[$id])();
    }
}

/**
 * Bootstrap function that wires the container for a given APP_ENV.
 * This is the composition root — all environment logic lives here.
 *
 * In a real application, this function would be called once in index.php
 * or a bootstrap file, passing getenv('APP_ENV').
 */
function bootstrapContainer(
    string $appEnv,
    string $smtpHost  = 'mail.example.com',
    int    $smtpPort  = 587,
    string $redisHost = '127.0.0.1',
    int    $redisPort = 6379,
): EnvContainer {
    $container = new EnvContainer();

    // Mailer: different impl per environment — wiring logic is HERE, not in the mailer
    $container->bind(MailerInterface::class, fn() => MailerFactory::create($appEnv, $smtpHost, $smtpPort));

    // Cache: Redis in production, array everywhere else
    $container->bind(CacheInterface::class, fn() => CacheFactory::create($appEnv, $redisHost, $redisPort));

    return $container;
}

// ─────────────────────────────────────────────────────────────────────────────
// SCENARIO 4 — Tests verifying environment-based selection
// ─────────────────────────────────────────────────────────────────────────────

class EnvironmentBindingsTest extends TestCase
{
    // ── MailerInterface environment selection ─────────────────────────────────

    /**
     * production → SmtpMailer with the correct SMTP host and port.
     */
    public function testProductionEnvironmentUsesSmtpMailer(): void
    {
        $container = bootstrapContainer(
            appEnv:   'production',
            smtpHost: 'smtp.acme.com',
            smtpPort: 465,
        );

        $mailer = $container->get(MailerInterface::class);

        $this->assertInstanceOf(SmtpMailer::class, $mailer,
            'Production: SmtpMailer is selected'
        );

        /** @var SmtpMailer $mailer */
        $this->assertSame('smtp.acme.com', $mailer->getHost());
        $this->assertSame(465, $mailer->getPort());
    }

    /**
     * test → NullMailer (no emails sent in CI).
     */
    public function testTestEnvironmentUsesNullMailer(): void
    {
        $container = bootstrapContainer(appEnv: 'test');
        $mailer    = $container->get(MailerInterface::class);

        $this->assertInstanceOf(NullMailer::class, $mailer,
            'Test environment: NullMailer is selected — no real emails sent'
        );

        // NullMailer sends nothing but counts calls
        $mailer->send('alice@example.com', 'Test Subject', 'Test body');

        /** @var NullMailer $mailer */
        $this->assertSame(1, $mailer->getCallCount(), 'Call was counted');
        $this->assertNull($mailer->getLastRecipient(),  'No recipient stored — nothing sent');
    }

    /**
     * development (or any other value) → LogMailer.
     */
    public function testDevelopmentEnvironmentUsesLogMailer(): void
    {
        $container = bootstrapContainer(appEnv: 'development');
        $mailer    = $container->get(MailerInterface::class);

        $this->assertInstanceOf(LogMailer::class, $mailer,
            'Development: LogMailer is selected — emails logged, not sent'
        );

        $mailer->send('alice@example.com', 'Welcome', 'Hello Alice');
        /** @var LogMailer $mailer */
        $this->assertSame(1, $mailer->getLogCount());
        $this->assertSame('alice@example.com', $mailer->getLastRecipient());
    }

    /**
     * An unknown APP_ENV value (misconfigured deployment) falls back to LogMailer.
     * This is safer than SmtpMailer (no unintended real emails) and more
     * informative than NullMailer (emails are logged for debugging).
     */
    public function testUnknownEnvironmentFallsBackToLogMailer(): void
    {
        $container = bootstrapContainer(appEnv: 'staging'); // not explicitly handled
        $mailer    = $container->get(MailerInterface::class);

        $this->assertInstanceOf(LogMailer::class, $mailer,
            'Unknown env: defaults to LogMailer (safe fallback)'
        );
    }

    // ── CacheInterface environment selection ──────────────────────────────────

    /**
     * production → RedisCache with the configured host and port.
     */
    public function testProductionEnvironmentUsesRedisCache(): void
    {
        $container = bootstrapContainer(
            appEnv:    'production',
            redisHost: 'redis.acme.internal',
            redisPort: 6380,
        );

        $cache = $container->get(CacheInterface::class);

        $this->assertInstanceOf(RedisCache::class, $cache,
            'Production: RedisCache is selected'
        );
        /** @var RedisCache $cache */
        $this->assertSame('redis.acme.internal', $cache->getHost());
    }

    /**
     * development and test → ArrayCache (no Redis required).
     */
    public function testNonProductionEnvironmentsUseArrayCache(): void
    {
        foreach (['development', 'test', 'staging', 'local'] as $env) {
            $container = bootstrapContainer(appEnv: $env);
            $cache     = $container->get(CacheInterface::class);

            $this->assertInstanceOf(ArrayCache::class, $cache,
                "{$env}: ArrayCache is selected (no Redis required)"
            );
        }
    }

    // ── Behaviour invariant: all implementations satisfy the interface ─────────

    /**
     * Regardless of environment, the mailer behaves correctly:
     * send() returns true, and subsequent operations work.
     *
     * This proves that environment branching does not break the contract —
     * LSP is satisfied across all implementations.
     */
    public function testAllMailerImplementationsSatisfyTheInterface(): void
    {
        $environments = ['production', 'development', 'test'];

        foreach ($environments as $env) {
            $container = bootstrapContainer(appEnv: $env);
            $mailer    = $container->get(MailerInterface::class);

            $result = $mailer->send('test@example.com', 'Test', 'Body');

            $this->assertTrue($result,
                "{$env}: send() must return true"
            );
        }
    }

    /**
     * Regardless of environment, the cache satisfies the interface:
     * set/get/has/delete all work correctly.
     */
    public function testAllCacheImplementationsSatisfyTheInterface(): void
    {
        foreach (['production', 'development', 'test'] as $env) {
            $container = bootstrapContainer(appEnv: $env);
            $cache     = $container->get(CacheInterface::class);

            $this->assertFalse($cache->has('key'), "{$env}: key does not exist initially");

            $cache->set('key', 'value');
            $this->assertTrue($cache->has('key'),   "{$env}: key exists after set()");
            $this->assertSame('value', $cache->get('key'), "{$env}: get() returns correct value");

            $cache->delete('key');
            $this->assertFalse($cache->has('key'),  "{$env}: key gone after delete()");
        }
    }

    // ── The composition-root principle ────────────────────────────────────────

    /**
     * No implementation class reads APP_ENV directly.
     * The environment-branching logic lives ONLY in the factory/bootstrap.
     * This test verifies that SmtpMailer, LogMailer, NullMailer have no
     * getenv() calls — proving that Rule 1 (config at entry point) is upheld.
     */
    public function testImplementationClassesDoNotReadEnvironmentVariables(): void
    {
        // Use reflection to verify no getenv() or $_ENV calls in the implementations
        $classesToCheck = [SmtpMailer::class, LogMailer::class, NullMailer::class, RedisCache::class, ArrayCache::class];

        foreach ($classesToCheck as $class) {
            $reflection = new \ReflectionClass($class);
            $filename   = $reflection->getFileName();

            if ($filename === false) {
                continue; // internal class — skip
            }

            $source = file_get_contents($filename);

            // For this in-file test, we check the string source of each class.
            // In a real audit you would use static analysis tools.
            // Here we simply assert the design intent is honoured by checking
            // our test doubles do not call getenv().
            $this->assertStringNotContainsString(
                'getenv(',
                $this->extractClassBody($source, $class),
                "{$class} must not call getenv() — environment config belongs at the composition root"
            );
        }
    }

    /**
     * Helper: extract the source code of a specific class from a file.
     * Very simplified — sufficient for this test's purpose.
     */
    private function extractClassBody(string $source, string $className): string
    {
        $shortName = (new \ReflectionClass($className))->getShortName();
        // Find the class declaration and return content between braces (rough approximation)
        if (preg_match('/class\s+' . $shortName . '[^{]*{(.+?)^}/ms', $source, $m)) {
            return $m[1];
        }
        return '';
    }
}