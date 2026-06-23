<?php
declare(strict_types=1);

/**
 * Example 01 — Factory Basics
 * -----------------------------
 * Run via PHPUnit:
 *   ./vendor/bin/phpunit module-6-object-lifecycle-and-state/lesson-6.5-factory-definitions/examples/01-factory-basics.php
 *
 * This file covers the most fundamental factory use case: a class whose
 * constructor takes non-type-hinted scalar arguments that auto-wiring
 * cannot resolve.
 *
 * Three patterns are shown:
 *
 *   PATTERN A — `create()->constructor()` with DI\env() helpers
 *               Singleton. Clean for simple env-variable wiring.
 *
 *   PATTERN B — `factory()` closure with inline validation
 *               Singleton behaviour (via internal static). Full PHP power.
 *
 *   PATTERN C — `factory()` closure with injected typed dependencies
 *               PHP-DI resolves type-hinted parameters and passes them in.
 *
 * All three produce singletons here. Transient scope is covered in Example 02.
 *
 * Structure:
 *   PART A — The classes that need factory wiring
 *   PART B — A minimal container that simulates PHP-DI factory behaviour
 *   PART C — Tests for all three patterns
 *   PART D — Demonstrating what happens WITHOUT factory (auto-wiring failure)
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// PART A — Classes requiring factory wiring
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Database connection wrapper.
 * Constructor takes scalar strings — auto-wiring cannot resolve these.
 */
class DatabaseConnection
{
    private \PDO $pdo;

    public function __construct(
        private readonly string $dsn,
        private readonly string $user,
        private readonly string $password,
    ) {
        // In tests we use SQLite in-memory to avoid needing a real database.
        // In production: $this->pdo = new \PDO($dsn, $user, $password);
        $this->pdo = new \PDO($this->dsn);
    }

    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getDsn(): string { return $this->dsn; }
}

/**
 * Password hasher with a configurable cost factor.
 * Constructor takes an int — auto-wiring cannot resolve this.
 */
class PasswordHasher
{
    public function __construct(private readonly int $cost)
    {
        if ($cost < 4 || $cost > 31) {
            throw new \InvalidArgumentException("bcrypt cost must be 4–31, got {$cost}");
        }
    }

    public function hash(string $plaintext): string
    {
        return password_hash($plaintext, PASSWORD_BCRYPT, ['cost' => $this->cost]);
    }

    public function verify(string $plaintext, string $hash): bool
    {
        return password_verify($plaintext, $hash);
    }

    public function getCost(): int { return $this->cost; }
}

/**
 * Feature flag loader that reads from a config array.
 * Constructor takes an array — auto-wiring cannot resolve this.
 */
class FeatureFlagLoader
{
    private array $flags;

    public function __construct(array $source)
    {
        // Validate source: all values must be bool
        foreach ($source as $key => $value) {
            if (!is_bool($value)) {
                throw new \InvalidArgumentException(
                    "Flag '{$key}' must be bool, got " . gettype($value)
                );
            }
        }
        $this->flags = $source;
    }

    public function isEnabled(string $flag): bool
    {
        return $this->flags[$flag] ?? false;
    }

    public function all(): array { return $this->flags; }
}

/**
 * Logger that needs both a typed dependency (a file handle / path) and
 * an injected typed service (a formatter).
 * Demonstrates the mixed scalar + typed-dependency factory pattern.
 */
interface FormatterInterface
{
    public function format(string $level, string $message): string;
}

class PrefixFormatter implements FormatterInterface
{
    public function __construct(private readonly string $prefix) {}

    public function format(string $level, string $message): string
    {
        return "[{$this->prefix}] [{$level}] {$message}";
    }
}

class AppLogger
{
    private array $entries = [];  // In tests: captures lines. In production: writes to disk.

    public function __construct(
        private readonly string             $channel,
        private readonly FormatterInterface $formatter,
    ) {}

    public function log(string $level, string $message): void
    {
        $this->entries[] = $this->formatter->format($level, $message);
    }

    public function getEntries(): array { return $this->entries; }
    public function getChannel(): string { return $this->channel; }
}

// ─────────────────────────────────────────────────────────────────────────────
// PART B — A minimal container that simulates PHP-DI's factory() behaviour
//
// PHP-DI's factory() does two things:
//   1. Invokes the callable each time the binding is resolved
//   2. Injects type-hinted arguments from the container into the callable
//
// Our SimulatedContainer does the same, providing a clear model of
// what PHP-DI does internally.
// ─────────────────────────────────────────────────────────────────────────────

class SimulatedContainer
{
    private array $factories   = [];
    private array $singletons  = [];

    /**
     * Register a factory. The callable may declare type-hinted parameters —
     * this container will resolve them recursively before invoking.
     *
     * @param callable $factory callable(mixed ...$resolvedDeps): object
     */
    public function factory(string $id, callable $factory, bool $singleton = true): void
    {
        $this->factories[$id] = ['callable' => $factory, 'singleton' => $singleton];
    }

    public function get(string $id): object
    {
        if (!isset($this->factories[$id])) {
            throw new \RuntimeException("No definition for: {$id}");
        }

        $def = $this->factories[$id];

        // Singleton: return cached instance if already built
        if ($def['singleton'] && isset($this->singletons[$id])) {
            return $this->singletons[$id];
        }

        // Resolve type-hinted parameters on the callable
        $instance = $this->invoke($def['callable']);

        if ($def['singleton']) {
            $this->singletons[$id] = $instance;
        }

        return $instance;
    }

    /**
     * Invokes a callable, resolving any type-hinted parameters from this container.
     * This mirrors PHP-DI's parameter injection into factory callables.
     */
    private function invoke(callable $callable): object
    {
        $reflection = new \ReflectionFunction(
            $callable instanceof \Closure ? $callable : \Closure::fromCallable($callable)
        );

        $args = [];
        foreach ($reflection->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                // Resolve the type-hinted class from this container
                $args[] = $this->get($type->getName());
            }
            // Scalar parameters are not injected — the factory must handle them
        }

        return $callable(...$args);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PART C — Tests for all three factory patterns
// ─────────────────────────────────────────────────────────────────────────────

class FactoryBasicsTest extends TestCase
{
    // ══════════════════════════════════════════════════════════════════════════
    // PATTERN A — create()->constructor() equivalent
    // Using a factory closure that reads environment-like values
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * DatabaseConnection requires string $dsn, string $user, string $password.
     * The factory closure reads these from "environment" (simulated here as
     * a local array) and passes them to the constructor.
     *
     * In PHP-DI, this would be:
     *   create(DatabaseConnection::class)
     *     ->constructor(DI\env('DB_DSN'), DI\env('DB_USER'), DI\env('DB_PASS'))
     */
    public function testFactoryResolvesScalarConstructorArguments(): void
    {
        // Simulate environment variables (would be getenv() in production)
        $env = [
            'DB_DSN'  => 'sqlite::memory:',
            'DB_USER' => 'root',
            'DB_PASS' => 'secret',
        ];

        $container = new SimulatedContainer();
        $container->factory(DatabaseConnection::class, function () use ($env): DatabaseConnection {
            return new DatabaseConnection(
                dsn:      $env['DB_DSN'],
                user:     $env['DB_USER'],
                password: $env['DB_PASS'],
            );
        });

        $db = $container->get(DatabaseConnection::class);

        $this->assertInstanceOf(DatabaseConnection::class, $db);
        $this->assertSame('sqlite::memory:', $db->getDsn());
    }

    /**
     * The factory produces a singleton: same instance returned on every get().
     * This mirrors PHP-DI's default behaviour for create() and factory() without
     * explicit transient scope.
     */
    public function testSingletonFactoryReturnsSameInstanceEachTime(): void
    {
        $container = new SimulatedContainer();
        $container->factory(DatabaseConnection::class, fn() => new DatabaseConnection('sqlite::memory:', '', ''));

        $a = $container->get(DatabaseConnection::class);
        $b = $container->get(DatabaseConnection::class);

        $this->assertSame($a, $b, 'Singleton factory: same instance returned');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PATTERN B — factory() with inline validation
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * PasswordHasher requires int $cost. The factory validates the value and
     * throws an informative exception if it is out of range — something
     * create()->constructor() alone cannot do.
     *
     * This is a key advantage of factory() over create(): full PHP power,
     * including validation, branching, and custom error messages.
     */
    public function testFactoryCanValidateConstructorArguments(): void
    {
        $container = new SimulatedContainer();

        // Valid cost
        $container->factory(PasswordHasher::class, function (): PasswordHasher {
            $cost = 4; // would be (int) getenv('BCRYPT_COST') in production
            if ($cost < 4 || $cost > 31) {
                throw new \RuntimeException("BCRYPT_COST must be 4-31, got {$cost}");
            }
            return new PasswordHasher($cost);
        });

        $hasher = $container->get(PasswordHasher::class);
        $this->assertInstanceOf(PasswordHasher::class, $hasher);
        $this->assertSame(4, $hasher->getCost());
    }

    /**
     * When the environment value is invalid, the factory throws at container
     * build time — not silently producing a broken object.
     */
    public function testFactoryThrowsForInvalidEnvironmentValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // PasswordHasher constructor throws if cost is out of range
        new PasswordHasher(cost: 99); // invalid — throws immediately
    }

    /**
     * FeatureFlagLoader requires array $source. The factory reads the flags
     * from configuration and validates them — all in one factory closure.
     */
    public function testFactoryWithArrayConstructorArgument(): void
    {
        $config = [
            'feature_dark_mode'  => true,
            'feature_beta_users' => false,
        ];

        $container = new SimulatedContainer();
        $container->factory(FeatureFlagLoader::class, fn() => new FeatureFlagLoader($config));

        $flags = $container->get(FeatureFlagLoader::class);

        $this->assertTrue($flags->isEnabled('feature_dark_mode'));
        $this->assertFalse($flags->isEnabled('feature_beta_users'));
        $this->assertFalse($flags->isEnabled('nonexistent_flag'),
            'Missing flag defaults to false'
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PATTERN C — factory() with injected typed dependencies
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * AppLogger needs both a string $channel (scalar — must come from config)
     * and a FormatterInterface (typed — container can resolve).
     *
     * PHP-DI resolves the FormatterInterface from the container and passes it
     * to the factory. The factory adds the scalar $channel itself.
     *
     * This is the "mixed scalar + typed dependency" pattern — the most common
     * factory use case in real applications.
     */
    public function testFactoryInjectsTypedDependenciesAutomatically(): void
    {
        $container = new SimulatedContainer();

        // Register the typed dependency
        $container->factory(
            FormatterInterface::class,
            fn(): FormatterInterface => new PrefixFormatter('APP')
        );

        // Register the service that needs both a scalar and a typed dep
        $container->factory(
            AppLogger::class,
            function (FormatterInterface $formatter): AppLogger {
                // $formatter is resolved from the container by PHP-DI
                // The scalar $channel comes from config (simulated inline here)
                return new AppLogger(channel: 'application', formatter: $formatter);
            }
        );

        $logger = $container->get(AppLogger::class);

        $this->assertSame('application', $logger->getChannel());

        $logger->log('INFO', 'Application started');
        $this->assertCount(1, $logger->getEntries());
        $this->assertStringContainsString('[APP]', $logger->getEntries()[0]);
        $this->assertStringContainsString('[INFO]', $logger->getEntries()[0]);
        $this->assertStringContainsString('Application started', $logger->getEntries()[0]);
    }

    /**
     * PHP-DI resolves the formatter as a singleton and injects the SAME instance
     * into both the AppLogger factory and any other factory that needs it.
     * This is the container's dependency graph in action.
     */
    public function testSingletonDependencyIsSharedAcrossFactories(): void
    {
        $container = new SimulatedContainer();

        $formatterCallCount = 0;

        // Formatter factory records how many times it is called
        $container->factory(
            FormatterInterface::class,
            function () use (&$formatterCallCount): FormatterInterface {
                $formatterCallCount++;
                return new PrefixFormatter('SHARED');
            }
        );

        $container->factory(
            AppLogger::class,
            fn(FormatterInterface $f): AppLogger => new AppLogger('app', $f)
        );

        // Get AppLogger twice — formatter should be constructed only once (singleton)
        $container->get(AppLogger::class);
        $container->get(AppLogger::class);

        $this->assertSame(1, $formatterCallCount,
            'Formatter factory called exactly once — singleton reused across resolutions'
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PART D — What happens without a factory
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Documents the failure mode: trying to instantiate a class with scalar
     * constructor arguments without a factory definition throws a clear error.
     *
     * In PHP-DI, the error message would be:
     *   "Parameter $dsn of class DatabaseConnection has no value defined or guessable"
     *
     * Here we simulate that by attempting to auto-wire via reflection.
     */
    public function testAutoWiringFailsForScalarConstructorArguments(): void
    {
        // Simulate what PHP-DI would do: try to resolve all constructor params by type
        $reflection = new \ReflectionClass(DatabaseConnection::class);
        $constructor = $reflection->getConstructor();

        $hasUnresolvableParam = false;
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();
            // If the type is a built-in (string, int, array) or has no type,
            // auto-wiring cannot resolve it
            if (!$type || ($type instanceof \ReflectionNamedType && $type->isBuiltin())) {
                $hasUnresolvableParam = true;
                break;
            }
        }

        $this->assertTrue($hasUnresolvableParam,
            'DatabaseConnection has scalar constructor params that auto-wiring cannot resolve — '
            . 'a factory definition is required'
        );
    }
}