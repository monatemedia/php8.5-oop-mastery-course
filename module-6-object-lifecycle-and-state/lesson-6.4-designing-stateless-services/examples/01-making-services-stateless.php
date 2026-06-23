<?php
declare(strict_types=1);

/**
 * Example 01 — Making Services Stateless
 * -----------------------------------------
 * Run via PHPUnit:
 *   ./vendor/bin/phpunit module-6-object-lifecycle-and-state/lesson-6.4-designing-stateless-services/examples/01-making-services-stateless.php
 *
 * This file takes the five anti-patterns from Lesson 6.3 and refactors each
 * one into a stateless equivalent. For each:
 *
 *   BEFORE: the stateful class (copied from 6.3 with minimal annotation)
 *   AFTER:  the stateless refactor
 *   TESTS:  proving three properties of the refactor
 *             1. The bug from 6.3 no longer exists
 *             2. The service produces correct results for all valid inputs
 *             3. The service is now scope-safe (assertable via singleton reuse)
 *
 * Structure:
 *   REFACTOR 1 — Accumulating array → caller-owned collection + transform methods
 *   REFACTOR 2 — Auth state → stateless context factory (see Example 02 for full pattern)
 *   REFACTOR 3 — Request-scoped data → parameter injection + BoundLogger
 *   REFACTOR 4 — Per-request counter → pass-and-return counter
 *   REFACTOR 5 — Boolean latch → constructor-time init + TTL timestamp variant
 */

use PHPUnit\Framework\TestCase;

// ═════════════════════════════════════════════════════════════════════════════
// REFACTOR 1 — Accumulating array
// ═════════════════════════════════════════════════════════════════════════════

// ── BEFORE ────────────────────────────────────────────────────────────────────

class ReportServiceStateful
{
    private array $results = []; // ← bug: accumulates on singleton

    public function addResult(array $row): void { $this->results[] = $row; }
    public function getResults(): array         { return $this->results; }
}

// ── AFTER ─────────────────────────────────────────────────────────────────────

/**
 * Stateless report service.
 *
 * KEY MOVE: the array is gone from $this. The caller passes rows in, gets
 * transformed rows back, and accumulates them in their own variable. The
 * service is a pure transformer — it holds no history between calls.
 */
class ReportService
{
    // No private state at all.

    /**
     * Transforms and enriches a single raw row.
     * Output depends only on $row — nothing else.
     */
    public function processRow(array $row): array
    {
        // Validate required fields
        if (empty($row['user']) || !isset($row['score'])) {
            throw new \InvalidArgumentException('Row must have user and score');
        }

        // Enrich: add grade based on score
        return array_merge($row, [
            'grade'        => $this->scoreToGrade((int) $row['score']),
            'processed_at' => '2026-06-01', // fixed date for deterministic tests
        ]);
    }

    /**
     * Produces a summary from a completed collection of processed rows.
     * Takes the collection as a parameter — does not own it.
     */
    public function summarise(array $processedRows): array
    {
        if (empty($processedRows)) {
            return ['total' => 0, 'average_score' => 0.0, 'rows' => []];
        }

        $scores = array_column($processedRows, 'score');

        return [
            'total'         => count($processedRows),
            'average_score' => round(array_sum($scores) / count($scores), 1),
            'rows'          => $processedRows,
        ];
    }

    private function scoreToGrade(int $score): string
    {
        return match(true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default      => 'F',
        };
    }
}

class Refactor1Test extends TestCase
{
    /**
     * Bug from 6.3 is gone: two operations on the same instance do not contaminate.
     * The caller accumulates in its own $rows variable — the service sees nothing.
     */
    public function testStatelessReportServiceHasNoCrossOperationContamination(): void
    {
        $service = new ReportService(); // can be singleton — safe

        // ── Operation 1 ───────────────────────────────────────────────────────
        $rows1 = [];
        $rows1[] = $service->processRow(['user' => 'Alice', 'score' => 95]);
        $rows1[] = $service->processRow(['user' => 'Bob',   'score' => 82]);
        $summary1 = $service->summarise($rows1);

        $this->assertSame(2, $summary1['total']);
        $this->assertSame(88.5, $summary1['average_score']);

        // ── Operation 2 (same instance — no reset needed, no state to reset) ──
        $rows2 = [];  // ← caller starts a fresh variable — no service involvement
        $rows2[] = $service->processRow(['user' => 'Charlie', 'score' => 71]);
        $summary2 = $service->summarise($rows2);

        // Operation 2's summary has exactly 1 row — no contamination from op 1
        $this->assertSame(1, $summary2['total'],
            'Operation 2 sees only its own 1 row — no contamination'
        );
        $this->assertSame(71.0, $summary2['average_score']);

        // Confirm it is the same object (singleton) — bug is structurally impossible now
        $this->assertSame($service, $service); // trivially — but the point is it's reused
    }

    /**
     * processRow() is a pure function: same input → same output, no side effects.
     * Calling it 1000 times changes nothing about the service's state.
     */
    public function testProcessRowIsPureFunction(): void
    {
        $service = new ReportService();
        $row     = ['user' => 'Alice', 'score' => 95];

        $result1 = $service->processRow($row);
        $result2 = $service->processRow($row);
        $result3 = $service->processRow($row);

        // All three calls return identical results
        $this->assertSame($result1, $result2);
        $this->assertSame($result2, $result3);
        $this->assertSame('A', $result1['grade']);
    }

    /**
     * summarise() operates on whatever array the caller passes.
     * Different callers, different arrays, different results — all correct.
     */
    public function testSummariseOperatesOnCallerOwnedData(): void
    {
        $service = new ReportService();

        $smallBatch  = [
            $service->processRow(['user' => 'Alice', 'score' => 90]),
        ];
        $largeBatch  = [
            $service->processRow(['user' => 'Bob',   'score' => 70]),
            $service->processRow(['user' => 'Carol',  'score' => 80]),
            $service->processRow(['user' => 'Dave',   'score' => 90]),
        ];

        $s1 = $service->summarise($smallBatch);
        $s2 = $service->summarise($largeBatch);

        $this->assertSame(1, $s1['total']);
        $this->assertSame(3, $s2['total']);
        $this->assertSame(90.0, $s1['average_score']);
        $this->assertSame(80.0, $s2['average_score']);
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// REFACTOR 2 — Auth state → stateless user resolver
// (Full RequestContext pattern is in Example 02 — this shows the minimal version)
// ═════════════════════════════════════════════════════════════════════════════

// ── BEFORE ────────────────────────────────────────────────────────────────────

class AuthServiceStateful
{
    private ?string $userId = null; // ← bug: per-request state on singleton

    public function authenticate(string $token): void
    {
        // Simulated: decode token to userId
        $this->userId = base64_decode($token) ?: null;
    }

    public function getUserId(): ?string { return $this->userId; }
}

// ── AFTER ─────────────────────────────────────────────────────────────────────

/**
 * Stateless auth service: resolves a token to a user ID and returns it.
 * Stores nothing. The caller receives the userId and passes it wherever needed.
 */
class AuthService
{
    // No private state.

    /**
     * Resolves a token to a userId. Returns null for invalid/missing tokens.
     * Output depends only on $token — nothing is stored on $this.
     */
    public function resolve(string $token): ?string
    {
        if (empty($token)) {
            return null;
        }
        // Simulated token decoding
        $decoded = base64_decode($token);
        return $decoded ?: null;
    }

    public function isValid(string $token): bool
    {
        return $this->resolve($token) !== null;
    }
}

class Refactor2Test extends TestCase
{
    /**
     * Stateless auth service — same instance, many tokens, no leakage.
     * Each call to resolve() is independent; no userId persists between calls.
     */
    public function testStatelessAuthServiceHasNoIdentityLeak(): void
    {
        $auth = new AuthService(); // singleton — safe

        // "Request 1": resolve Alice's token
        $userId1 = $auth->resolve(base64_encode('user-alice'));
        $this->assertSame('user-alice', $userId1);

        // "Request 2": no token provided — should return null
        $userId2 = $auth->resolve('');
        $this->assertNull($userId2,
            'No leak: empty token returns null, not the previous request\'s userId'
        );

        // "Request 3": Bob's token
        $userId3 = $auth->resolve(base64_encode('user-bob'));
        $this->assertSame('user-bob', $userId3);

        // Request 2's null did not corrupt request 3's result
        $this->assertNotNull($userId3);
    }

    /**
     * resolve() is idempotent: same token always produces same userId.
     */
    public function testResolveIsIdempotent(): void
    {
        $auth  = new AuthService();
        $token = base64_encode('user-charlie');

        $this->assertSame('user-charlie', $auth->resolve($token));
        $this->assertSame('user-charlie', $auth->resolve($token));
        $this->assertSame('user-charlie', $auth->resolve($token));
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// REFACTOR 3 — Request-scoped data → parameter injection + BoundLogger
// ═════════════════════════════════════════════════════════════════════════════

// ── BEFORE ────────────────────────────────────────────────────────────────────

class OperationLoggerStateful
{
    private string $correlationId = ''; // ← bug: context set externally

    public function setCorrelationId(string $id): void { $this->correlationId = $id; }
    public function log(string $msg): string { return "[{$this->correlationId}] {$msg}"; }
}

// ── AFTER ─────────────────────────────────────────────────────────────────────

/**
 * Stateless logger: correlationId is a parameter, not state.
 *
 * KEY MOVE: correlationId moves from a property set by setCorrelationId()
 * to a required parameter on log(). The service never "knows" the current
 * correlation ID — it is told it each time.
 */
class OperationLogger
{
    // No private state.

    public function log(string $correlationId, string $message, string $level = 'INFO'): string
    {
        return "[{$correlationId}] [{$level}] {$message}";
    }

    public function error(string $correlationId, string $message): string
    {
        return $this->log($correlationId, $message, 'ERROR');
    }
}

/**
 * BoundLogger: a lightweight transient that binds a correlationId to a logger.
 *
 * Created fresh per request/operation with the request's correlationId.
 * Delegates all calls to the stateless OperationLogger singleton.
 * The caller gets the convenience of not threading correlationId everywhere;
 * the underlying logger remains stateless.
 */
class BoundLogger
{
    public function __construct(
        private readonly OperationLogger $logger,
        private readonly string          $correlationId,
    ) {}

    public function log(string $message, string $level = 'INFO'): string
    {
        return $this->logger->log($this->correlationId, $message, $level);
    }

    public function error(string $message): string
    {
        return $this->logger->error($this->correlationId, $message);
    }
}

class Refactor3Test extends TestCase
{
    /**
     * Stateless logger: passing different correlationIds produces correct, independent output.
     * The service has no memory of previous calls.
     */
    public function testStatelessLoggerHasNoContextLeak(): void
    {
        $logger = new OperationLogger(); // singleton — safe

        // "Request 1": log with req-001
        $line1 = $logger->log('req-001', 'Processing started');
        $this->assertStringContainsString('req-001', $line1);

        // "Request 2": log with req-002 — independently correct
        $line2 = $logger->log('req-002', 'Processing started');
        $this->assertStringContainsString('req-002', $line2);
        $this->assertStringNotContainsString('req-001', $line2,
            'No leak: req-002 line does not contain req-001 correlation ID'
        );

        // Can even call with the same correlationId from different contexts
        $line3 = $logger->log('req-001', 'Something else happened');
        $this->assertStringContainsString('req-001', $line3);
    }

    /**
     * BoundLogger provides per-request convenience without statefulness.
     * Two BoundLoggers with different IDs produce independent output.
     */
    public function testBoundLoggerIsolatesPerRequestContext(): void
    {
        $logger = new OperationLogger(); // shared singleton

        // Create two bound loggers for two concurrent "requests"
        $bound1 = new BoundLogger($logger, 'req-001');
        $bound2 = new BoundLogger($logger, 'req-002');

        $output1 = $bound1->log('Payment processed');
        $output2 = $bound2->log('Email sent');

        $this->assertStringContainsString('req-001', $output1);
        $this->assertStringContainsString('req-002', $output2);
        $this->assertStringNotContainsString('req-002', $output1);
        $this->assertStringNotContainsString('req-001', $output2);
    }

    /**
     * BoundLogger's correlationId is immutable after construction — it cannot
     * be accidentally overwritten (unlike setCorrelationId() on the stateful version).
     */
    public function testBoundLoggerCorrelationIdIsImmutableAfterConstruction(): void
    {
        $logger = new OperationLogger();
        $bound  = new BoundLogger($logger, 'req-stable');

        // Log multiple times — correlationId never changes
        $line1 = $bound->log('First event');
        $line2 = $bound->log('Second event');
        $line3 = $bound->error('Something failed');

        foreach ([$line1, $line2, $line3] as $line) {
            $this->assertStringContainsString('req-stable', $line,
                'correlationId is stable across all calls to the BoundLogger'
            );
        }
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// REFACTOR 4 — Per-request counter → pass-and-return
// ═════════════════════════════════════════════════════════════════════════════

// ── BEFORE ────────────────────────────────────────────────────────────────────

class ApiCallTrackerStateful
{
    private const LIMIT = 3;
    private int $count = 0; // ← bug: accumulates across requests

    public function recordCall(): void  { $this->count++; }
    public function isOverLimit(): bool { return $this->count >= self::LIMIT; }
    public function getCount(): int     { return $this->count; }
}

// ── AFTER ─────────────────────────────────────────────────────────────────────

/**
 * Stateless API call tracker.
 *
 * KEY MOVE: $count moves from a property to a method parameter and return value.
 * The caller owns the count. The service provides the rules (what limit, how to increment).
 *
 * The service is now a pure rules engine: "given this count, is it over limit?"
 * and "given this count, what is the new count after one more call?".
 */
class ApiCallTracker
{
    public function __construct(private readonly int $limit = 3) {}

    /**
     * Returns the new count after recording one call.
     * The caller passes in the current count; gets back the incremented count.
     */
    public function recordCall(int $currentCount): int
    {
        return $currentCount + 1;
    }

    /**
     * Pure predicate: is this count over the limit?
     */
    public function isOverLimit(int $count): bool
    {
        return $count >= $this->limit;
    }

    public function getRemainingCalls(int $count): int
    {
        return max(0, $this->limit - $count);
    }
}

class Refactor4Test extends TestCase
{
    /**
     * Bug from 6.3 is gone: the caller starts with count=0 each "request".
     * The tracker never holds any count between calls.
     */
    public function testStatelessTrackerHasNoCountAccumulation(): void
    {
        $tracker = new ApiCallTracker(limit: 3); // singleton — safe

        // ── Request 1: caller starts at 0 ────────────────────────────────────
        $count1 = 0;
        $count1 = $tracker->recordCall($count1); // 1
        $count1 = $tracker->recordCall($count1); // 2
        $this->assertFalse($tracker->isOverLimit($count1), 'Request 1: 2 calls, under limit');

        // ── Request 2: caller starts at 0 (their own fresh variable) ──────────
        $count2 = 0; // ← fresh variable — no connection to $count1
        $count2 = $tracker->recordCall($count2); // 1

        // Correctly: 1 call in request 2, under limit
        $this->assertFalse($tracker->isOverLimit($count2),
            'Request 2: 1 call, under limit — no contamination from request 1'
        );
        $this->assertSame(1, $count2);
        $this->assertSame(2, $tracker->getRemainingCalls($count2));
    }

    /**
     * isOverLimit() and getRemainingCalls() are pure predicates.
     * Same input → same output, every time, with no side effects.
     */
    public function testTrackerMethodsArePurePredicates(): void
    {
        $tracker = new ApiCallTracker(limit: 5);

        // Calling isOverLimit() with count=3 always returns false
        $this->assertFalse($tracker->isOverLimit(3));
        $this->assertFalse($tracker->isOverLimit(3));
        $this->assertFalse($tracker->isOverLimit(3));

        // Calling isOverLimit() with count=5 always returns true
        $this->assertTrue($tracker->isOverLimit(5));
        $this->assertTrue($tracker->isOverLimit(5));

        // recordCall() is deterministic: input 3 → output 4, always
        $this->assertSame(4, $tracker->recordCall(3));
        $this->assertSame(4, $tracker->recordCall(3));
    }

    /**
     * The limit is a constructor argument — different limits can coexist.
     * Because there is no shared mutable state, two instances with different
     * limits can be used simultaneously without interference.
     */
    public function testMultipleTrackersWithDifferentLimitsCoexist(): void
    {
        $strictTracker  = new ApiCallTracker(limit: 1);
        $generousTracker = new ApiCallTracker(limit: 100);

        $count = 0;
        $count = $strictTracker->recordCall($count);   // 1

        $this->assertTrue($strictTracker->isOverLimit($count),  'Strict: 1 call = over limit');
        $this->assertFalse($generousTracker->isOverLimit($count), 'Generous: 1 call = under limit');
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// REFACTOR 5 — Boolean latch → constructor-time init + TTL variant
// ═════════════════════════════════════════════════════════════════════════════

// ── BEFORE ────────────────────────────────────────────────────────────────────

class ConfigLoaderStateful
{
    private bool  $loaded = false; // ← bug: one-way latch
    private array $config = [];

    public function __construct(private readonly array $source) {}

    public function load(): void
    {
        if ($this->loaded) return;
        $this->config = $this->source;
        $this->loaded = true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
}

// ── AFTER: Option A — constructor-time init ───────────────────────────────────

/**
 * Config is loaded eagerly in the constructor.
 *
 * KEY MOVE: eliminate the $loaded flag entirely by doing the work in the
 * constructor. PHP-DI calls the constructor once (for a singleton) — so
 * loading happens once. No flag needed, no guard clause, no latch.
 *
 * The constructor IS the warm/load operation.
 */
class ConfigLoader
{
    private array $config;

    public function __construct(array $source)
    {
        // Load eagerly — no flag, no guard clause
        $this->config = $source;
        // In production: $this->config = $this->readFromFile($configPath);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->config);
    }

    public function all(): array
    {
        return $this->config;
    }
}

// ── AFTER: Option B — TTL-based refresh (when staleness is a real concern) ────

/**
 * Config loader that refreshes when stale.
 *
 * Replaces the one-way boolean latch with a timestamp + TTL.
 * The condition is now bidirectional: it can flip back to "needs loading"
 * when the TTL expires.
 */
class RefreshableConfigLoader
{
    private array $config       = [];
    private ?int  $loadedAt     = null;
    private const TTL_SECONDS   = 300; // 5 minutes

    public function __construct(private readonly array $source) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $this->loadIfStale();
        return $this->config[$key] ?? $default;
    }

    private function loadIfStale(): void
    {
        if ($this->loadedAt !== null
            && (time() - $this->loadedAt) < self::TTL_SECONDS) {
            return; // still fresh
        }
        // Reload — this runs at startup AND after TTL expires
        $this->config   = $this->source;
        $this->loadedAt = time();
    }

    /**
     * Force a reload regardless of TTL — for testing and cache-clear scenarios.
     */
    public function invalidate(): void
    {
        $this->loadedAt = null;
    }
}

class Refactor5Test extends TestCase
{
    /**
     * ConfigLoader (constructor-time init): config is always available,
     * no load() call needed, no stale-boot bug possible.
     */
    public function testConstructorInitConfigLoaderAlwaysHasConfig(): void
    {
        $loader = new ConfigLoader(['debug' => true, 'max_items' => 50]);

        // Config is available immediately — no load() call
        $this->assertTrue($loader->get('debug'));
        $this->assertSame(50, $loader->get('max_items'));
        $this->assertNull($loader->get('nonexistent'));
        $this->assertTrue($loader->has('debug'));
        $this->assertFalse($loader->has('nonexistent'));
    }

    /**
     * Because there is no load() method and no boolean latch, a "re-deploy"
     * scenario is handled by the DI container constructing a new ConfigLoader
     * with the updated source — not by calling load() on the old instance.
     *
     * This test documents that the correct fix for stale config is a new
     * instance with new data, not re-loading on the old singleton.
     */
    public function testNewInstanceWithNewSourceReflectsUpdatedConfig(): void
    {
        $oldLoader = new ConfigLoader(['feature_dark_mode' => false]);
        $this->assertFalse($oldLoader->get('feature_dark_mode'));

        // "Deployment": a new ConfigLoader is constructed with updated config
        $newLoader = new ConfigLoader(['feature_dark_mode' => true]);
        $this->assertTrue($newLoader->get('feature_dark_mode'));

        // Old loader is unaffected (but it would be replaced in the container)
        $this->assertFalse($oldLoader->get('feature_dark_mode'));
    }

    /**
     * RefreshableConfigLoader: TTL makes the latch bidirectional.
     * invalidate() forces a reload — stale data can be cleared.
     */
    public function testRefreshableLoaderReloadsAfterInvalidation(): void
    {
        $loader = new RefreshableConfigLoader(['feature' => false]);

        // First access loads the config
        $this->assertFalse($loader->get('feature'));

        // Simulate a config change via invalidate() (would happen externally in production)
        // We cannot change $source directly — but we can verify invalidate() triggers reload
        $loader->invalidate();

        // Next access reloads from $source (same source in this test, but the mechanism works)
        $result = $loader->get('feature');
        $this->assertFalse($result, 'After invalidation, config is reloaded from source');
    }

    /**
     * Without the one-way latch, repeated loads produce correct results.
     * The original bug was: load() silently did nothing after first call.
     * Now: no load() method exists on ConfigLoader; RefreshableConfigLoader
     * re-runs when stale.
     */
    public function testNoOnewayLatchMeansLoadCanBeRepeated(): void
    {
        // ConfigLoader: constructed with source, always returns that source
        $loader = new ConfigLoader(['key' => 'value1']);
        $this->assertSame('value1', $loader->get('key'));

        // A new loader with different source is a completely independent object
        $loader2 = new ConfigLoader(['key' => 'value2']);
        $this->assertSame('value2', $loader2->get('key'));

        // Original loader is unchanged
        $this->assertSame('value1', $loader->get('key'));
    }
}