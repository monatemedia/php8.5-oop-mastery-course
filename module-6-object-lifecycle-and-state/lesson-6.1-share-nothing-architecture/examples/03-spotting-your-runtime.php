<?php
declare(strict_types=1);

/**
 * Example 03 — Spotting Your Runtime
 * -------------------------------------
 * Run via PHPUnit:
 *   ./vendor/bin/phpunit module-6-object-lifecycle-and-state/lesson-6.1-share-nothing-architecture/examples/03-spotting-your-runtime.php
 *
 * In practice you need to know — or at least be able to detect — whether
 * your PHP code is running in a share-nothing or persistent-worker context.
 * The detection matters for:
 *
 *   1. Deciding which services need to be transient (Lesson 6.2)
 *   2. Writing different bootstrap code for each context
 *   3. Adding guard assertions in critical services
 *   4. Writing tests that simulate the correct context
 *
 * This file covers:
 *   PART A — A RuntimeDetector class with all detection signals
 *   PART B — A RuntimeContext value object (safe to inject and test)
 *   PART C — How to write lifecycle-aware code
 *   PART D — Tests for the detector and the lifecycle-aware patterns
 *
 * NOTE: The detector uses real PHP signals (constants, SAPI name, env vars).
 * In the PHPUnit test environment, most of these will read as "cli / share-nothing".
 * The tests use the FakeRuntimeContext to simulate persistent-worker conditions
 * so you can write lifecycle-safe code and test it without needing Swoole installed.
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// PART A — RuntimeDetector
//
// Reads the available PHP signals and classifies the current execution context.
// Never stores mutable state — this class is always safe as a singleton.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Classifies which runtime model the current PHP process is operating under.
 *
 * All methods are pure reads against PHP globals/constants — no side effects,
 * no mutable state. Safe as a singleton in any context.
 */
class RuntimeDetector
{
    // ── Signal 1: SAPI name ───────────────────────────────────────────────────

    /**
     * The PHP SAPI (Server API) name tells you how PHP was invoked.
     *
     * Common values:
     *   'fpm-fcgi'  — PHP-FPM (the most common web SAPI)
     *   'apache2handler' — mod_php in Apache
     *   'cli'       — command-line (scripts, queue workers, batch jobs)
     *   'cli-server'— PHP built-in web server (php -S)
     *   'phpdbg'    — PHP debugger
     *
     * The SAPI name alone does NOT tell you whether the process is persistent.
     * A 'cli' SAPI can be a short-lived script OR a long-running queue worker.
     * Use it in combination with the signals below.
     */
    public function sapiName(): string
    {
        return php_sapi_name() ?: 'unknown';
    }

    public function isCommandLine(): bool
    {
        return in_array($this->sapiName(), ['cli', 'phpdbg'], true);
    }

    public function isFpm(): bool
    {
        return $this->sapiName() === 'fpm-fcgi';
    }

    // ── Signal 2: Known persistent-worker runtimes ────────────────────────────

    /**
     * Swoole defines SWOOLE_VERSION when it is loaded as an extension.
     * If this constant exists, the process is almost certainly long-running.
     */
    public function isSwoole(): bool
    {
        return defined('SWOOLE_VERSION');
    }

    /**
     * FrankenPHP defines FRANKENPHP_VERSION when the FrankenPHP SAPI is active.
     */
    public function isFrankenPhp(): bool
    {
        return defined('FRANKENPHP_VERSION');
    }

    /**
     * RoadRunner sets the RR_MODE environment variable ('http', 'jobs', etc.)
     * when it controls the PHP worker.
     */
    public function isRoadRunner(): bool
    {
        return getenv('RR_MODE') !== false;
    }

    // ── Signal 3: Explicit deployment contract ────────────────────────────────

    /**
     * The most reliable signal: your deployment config explicitly declares
     * the worker mode. Set APP_WORKER_MODE=persistent in your environment
     * for any runtime that keeps workers alive across requests.
     *
     * This is the recommended approach for production systems because it
     * does not depend on PHP extension availability at detection time.
     */
    public function isExplicitlyMarkedPersistent(): bool
    {
        return strtolower((string) getenv('APP_WORKER_MODE')) === 'persistent';
    }

    // ── Composite judgement ───────────────────────────────────────────────────

    /**
     * Returns true if ANY signal indicates a persistent-worker environment.
     *
     * In production, you should set APP_WORKER_MODE explicitly rather than
     * relying on auto-detection — auto-detection can miss edge cases.
     */
    public function isPersistentWorker(): bool
    {
        return $this->isSwoole()
            || $this->isFrankenPhp()
            || $this->isRoadRunner()
            || $this->isExplicitlyMarkedPersistent();
    }

    /**
     * Returns a human-readable description of the detected runtime.
     * Useful for logging at bootstrap time.
     */
    public function describe(): string
    {
        $parts = ["SAPI: {$this->sapiName()}"];

        if ($this->isSwoole())                    { $parts[] = 'Swoole'; }
        if ($this->isFrankenPhp())                { $parts[] = 'FrankenPHP'; }
        if ($this->isRoadRunner())                { $parts[] = 'RoadRunner'; }
        if ($this->isExplicitlyMarkedPersistent()) { $parts[] = 'APP_WORKER_MODE=persistent'; }

        $mode = $this->isPersistentWorker() ? 'PERSISTENT WORKER' : 'share-nothing';

        return implode(', ', $parts) . " → {$mode}";
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PART B — RuntimeContext value object
//
// Instead of calling RuntimeDetector in every service (which would be a
// violation of Rule 1 from COURSE_PHILOSOPHY.md — "config belongs at the
// entry point"), we resolve the runtime once at bootstrap and inject a
// RuntimeContext value object wherever it is needed.
//
// This is also what makes the code testable: tests construct a
// FakeRuntimeContext rather than trying to fake PHP constants.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Immutable value object representing the resolved runtime context.
 * Created once at bootstrap; injected wherever lifecycle decisions are needed.
 */
final class RuntimeContext
{
    public function __construct(
        public readonly bool   $isPersistentWorker,
        public readonly string $sapiName,
        public readonly string $description,
    ) {}

    /**
     * Factory method: create from a RuntimeDetector.
     * Called once at application bootstrap.
     */
    public static function fromDetector(RuntimeDetector $detector): self
    {
        return new self(
            isPersistentWorker: $detector->isPersistentWorker(),
            sapiName:           $detector->sapiName(),
            description:        $detector->describe(),
        );
    }

    /**
     * Factory for use in tests — simulates a share-nothing context.
     */
    public static function shareNothing(): self
    {
        return new self(
            isPersistentWorker: false,
            sapiName:           'fpm-fcgi',
            description:        'SAPI: fpm-fcgi → share-nothing',
        );
    }

    /**
     * Factory for use in tests — simulates a Swoole persistent-worker context.
     */
    public static function persistentWorker(string $runtime = 'swoole'): self
    {
        return new self(
            isPersistentWorker: true,
            sapiName:           'cli',
            description:        "SAPI: cli, {$runtime} → PERSISTENT WORKER",
        );
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PART C — Lifecycle-aware code
//
// A service that behaves differently depending on the runtime context.
// In a real application, this pattern is used sparingly — most lifecycle
// decisions are made at the container level, not inside services.
//
// The pattern shown here is useful for:
//   - Logging a warning when a stateful operation is detected in persistent mode
//   - Choosing between in-memory and external (Redis) caching
//   - Deciding whether to warm a cache eagerly or lazily
// ─────────────────────────────────────────────────────────────────────────────

/**
 * A cache warmer that behaves differently in persistent vs share-nothing contexts.
 *
 * Under share-nothing: warm once per request (cheap, happens on every request anyway)
 * Under persistent worker: warm once at worker startup, then verify before each request
 */
class CacheWarmer
{
    private bool $isWarmed = false;
    private array $log     = [];

    public function __construct(private readonly RuntimeContext $context) {}

    /**
     * Warms the cache. In a persistent worker, warns if called more than once.
     */
    public function warm(): void
    {
        if ($this->isWarmed && $this->context->isPersistentWorker) {
            // In a persistent worker, warm() being called again is suspicious.
            // It might mean the caller does not realise the cache is already warm,
            // or the cache was invalidated and needs to be refreshed.
            $this->log[] = 'WARNING: warm() called again in persistent worker — cache was already warm';
        }

        // Simulate warming work
        $this->log[] = "Cache warmed [{$this->context->sapiName}]";
        $this->isWarmed = true;
    }

    public function isWarmed(): bool
    {
        return $this->isWarmed;
    }

    public function getLog(): array
    {
        return $this->log;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PART D — Tests
// ─────────────────────────────────────────────────────────────────────────────

class RuntimeDetectorTest extends TestCase
{
    // ── RuntimeDetector tests ─────────────────────────────────────────────────

    /**
     * The detector runs in the PHPUnit test environment — a CLI process.
     * Swoole, FrankenPHP, and RoadRunner are not present.
     * APP_WORKER_MODE is not set (unless your test env sets it).
     * So the detector should report: NOT a persistent worker.
     */
    public function testDetectorReturnsCliSapiInTestEnvironment(): void
    {
        $detector = new RuntimeDetector();

        $this->assertSame('cli', $detector->sapiName(),
            'PHPUnit runs under the CLI SAPI'
        );
        $this->assertTrue($detector->isCommandLine());
        $this->assertFalse($detector->isFpm());
    }

    public function testDetectorReportsFalseForPersistentWorkerInTestEnv(): void
    {
        $detector = new RuntimeDetector();

        // In the test environment, none of the persistent-worker signals are present
        $this->assertFalse($detector->isSwoole(),
            'Swoole constant not defined in test environment'
        );
        $this->assertFalse($detector->isFrankenPhp(),
            'FrankenPHP constant not defined in test environment'
        );
        $this->assertFalse($detector->isRoadRunner(),
            'RR_MODE env var not set in test environment'
        );
    }

    public function testDetectorDescribeReturnsAString(): void
    {
        $detector = new RuntimeDetector();
        $description = $detector->describe();

        $this->assertIsString($description);
        $this->assertStringContainsString('SAPI:', $description);
    }

    // ── RuntimeContext tests ──────────────────────────────────────────────────

    public function testShareNothingContextIsNotPersistentWorker(): void
    {
        $ctx = RuntimeContext::shareNothing();

        $this->assertFalse($ctx->isPersistentWorker);
        $this->assertSame('fpm-fcgi', $ctx->sapiName);
    }

    public function testPersistentWorkerContextIsPersistentWorker(): void
    {
        $ctx = RuntimeContext::persistentWorker('swoole');

        $this->assertTrue($ctx->isPersistentWorker);
    }

    public function testRuntimeContextIsImmutable(): void
    {
        $ctx = RuntimeContext::shareNothing();

        // readonly properties cannot be reassigned — this is enforced by PHP 8.1+
        // Attempting: $ctx->isPersistentWorker = true;
        // would throw: Cannot modify readonly property RuntimeContext::$isPersistentWorker

        // We verify the value is as constructed (no mutation possible)
        $this->assertFalse($ctx->isPersistentWorker);
    }

    public function testFromDetectorCreatesContextFromRealDetector(): void
    {
        $detector = new RuntimeDetector();
        $ctx      = RuntimeContext::fromDetector($detector);

        $this->assertInstanceOf(RuntimeContext::class, $ctx);
        $this->assertSame($detector->sapiName(), $ctx->sapiName);
        $this->assertSame($detector->isPersistentWorker(), $ctx->isPersistentWorker);
    }

    // ── CacheWarmer tests — lifecycle-aware behaviour ─────────────────────────

    /**
     * Under share-nothing, calling warm() twice is normal — each request
     * warms the cache fresh, so no warning is emitted.
     */
    public function testCacheWarmerLogsNoWarningUnderShareNothing(): void
    {
        $warmer = new CacheWarmer(RuntimeContext::shareNothing());

        $warmer->warm(); // "request 1"
        $warmer->warm(); // "request 2" (in share-nothing, same object would not exist
                         //              across requests — this simulates bad wiring)

        $warnings = array_filter($warmer->getLog(), fn($e) => str_contains($e, 'WARNING'));
        // Under share-nothing context, no warning is emitted even on second call
        $this->assertEmpty($warnings, 'No warning under share-nothing context');
    }

    /**
     * Under persistent worker, calling warm() twice emits a warning because
     * the object should already be warm from the previous request.
     */
    public function testCacheWarmerLogsWarningOnSecondWarmInPersistentContext(): void
    {
        $warmer = new CacheWarmer(RuntimeContext::persistentWorker());

        $warmer->warm(); // worker startup — first warm, no warning
        $warmer->warm(); // second call — suspicious, warning emitted

        $warnings = array_filter($warmer->getLog(), fn($e) => str_contains($e, 'WARNING'));
        $this->assertNotEmpty($warnings,
            'Warning should be emitted when warm() is called again in a persistent worker'
        );
    }

    public function testCacheWarmerIsWarmedAfterFirstCall(): void
    {
        $warmer = new CacheWarmer(RuntimeContext::shareNothing());

        $this->assertFalse($warmer->isWarmed(), 'Not warmed before warm() is called');

        $warmer->warm();

        $this->assertTrue($warmer->isWarmed(), 'Warmed after warm() is called');
    }

    // ── Demonstrating the value of injection over detection-in-place ──────────

    /**
     * This test demonstrates why injecting RuntimeContext is better than
     * calling RuntimeDetector::isPersistentWorker() inside services directly.
     *
     * With injection: trivial to test both contexts without any real PHP signals.
     * Without injection: you would need to set Swoole constants or env vars in tests.
     */
    public function testCanSimulateBothContextsWithoutRealRuntimeSignals(): void
    {
        $shareNothing = new CacheWarmer(RuntimeContext::shareNothing());
        $persistent   = new CacheWarmer(RuntimeContext::persistentWorker());

        // Warm both twice
        $shareNothing->warm();
        $shareNothing->warm();

        $persistent->warm();
        $persistent->warm();

        $snWarnings = array_filter($shareNothing->getLog(), fn($e) => str_contains($e, 'WARNING'));
        $pwWarnings = array_filter($persistent->getLog(),   fn($e) => str_contains($e, 'WARNING'));

        $this->assertEmpty($snWarnings,    'Share-nothing: no warning on double warm');
        $this->assertNotEmpty($pwWarnings, 'Persistent worker: warning on double warm');

        // No Swoole extension needed. No env vars. Just injected context objects.
    }
}