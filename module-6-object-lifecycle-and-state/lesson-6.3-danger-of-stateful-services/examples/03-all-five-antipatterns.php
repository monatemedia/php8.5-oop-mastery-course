<?php
declare(strict_types=1);

/**
 * Example 03 — All Five Anti-Patterns
 * --------------------------------------
 * Run via PHPUnit:
 *   ./vendor/bin/phpunit module-6-object-lifecycle-and-state/lesson-6.3-danger-of-stateful-services/examples/03-all-five-antipatterns.php
 *
 * This file is a reference sheet: all five anti-patterns in one place, each
 * with its canonical service class, the test that catches the bug, and a
 * one-line diagnosis comment that mirrors what you would write in a code review.
 *
 * Use it as a lookup when auditing unfamiliar code.
 *
 * Structure (one section per anti-pattern):
 *   PART 1 — Accumulating service
 *   PART 2 — Auth state on singleton
 *   PART 3 — Request-scoped data on singleton
 *   PART 4 — Counter/statistics on singleton
 *   PART 5 — Deferred initialisation that never resets
 *
 * Each part:
 *   a) The service class with the danger clearly annotated
 *   b) The canonical test that exposes the bug
 *   c) A DIAGNOSIS comment usable verbatim in a code review
 */

use PHPUnit\Framework\TestCase;

// ═════════════════════════════════════════════════════════════════════════════
// PART 1 — ACCUMULATING SERVICE
//
// CANONICAL MARKER: private array $x = [] with public addX() method
// ═════════════════════════════════════════════════════════════════════════════

// DIAGNOSIS: ReportCollector has `private array $rows = []` appended by
// addRow(). As a singleton, rows accumulate across all operations since
// worker startup. getRows() for operation N includes all rows from
// operations 1..N-1. Fix: transient scope or stateless redesign (L6.4).

class ReportCollector
{
    private array $rows = [];           // ← DANGER: private array accumulator

    public function addRow(array $row): void { $this->rows[] = $row; }
    public function getRows(): array         { return $this->rows; }
    public function getRowCount(): int       { return count($this->rows); }
}

class AntiPattern1Test extends TestCase
{
    /**
     * Test that exposes Anti-Pattern 1.
     *
     * Pattern: create ONE instance, run TWO operations, assert that operation 2
     * sees data from operation 1.
     */
    public function testAntiPattern1AccumulatingService(): void
    {
        $collector = new ReportCollector(); // one instance — simulates singleton

        // Operation 1: add 2 rows
        $collector->addRow(['id' => 1, 'value' => 'alpha']);
        $collector->addRow(['id' => 2, 'value' => 'beta']);
        $this->assertSame(2, $collector->getRowCount(), 'Op 1: 2 rows');

        // Operation 2: add 1 row — should see 1 row total
        $collector->addRow(['id' => 3, 'value' => 'gamma']);

        // BUG: 3 rows — 2 accumulated from operation 1
        $this->assertSame(3, $collector->getRowCount(),
            'ANTI-PATTERN 1 CONFIRMED: 3 rows (2 from op 1 + 1 from op 2)'
        );
        $this->assertSame('alpha', $collector->getRows()[0]['value'],
            'Op 1\'s first row is visible to op 2'
        );
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// PART 2 — AUTH STATE ON SINGLETON
//
// CANONICAL MARKER: private ?SomeType $current = null with public login/set method
// ═════════════════════════════════════════════════════════════════════════════

// DIAGNOSIS: SessionManager has `private ?string $userId = null` set by
// startSession(). As a singleton, the userId from request N is still set
// at the start of request N+1. Any code that reads getUserId() before the
// next startSession() call receives the previous request's user identity.
// Fix: transient scope or immutable RequestContext (L6.4).

class SessionManager
{
    private ?string $userId   = null;   // ← DANGER: nullable, set per-request
    private ?string $tenantId = null;

    public function startSession(string $userId, string $tenantId): void
    {
        $this->userId   = $userId;
        $this->tenantId = $tenantId;
    }

    public function endSession(): void
    {
        $this->userId   = null;
        $this->tenantId = null;
    }

    public function getUserId(): ?string   { return $this->userId; }
    public function getTenantId(): ?string { return $this->tenantId; }
    public function hasSession(): bool     { return $this->userId !== null; }
}

class AntiPattern2Test extends TestCase
{
    /**
     * Test that exposes Anti-Pattern 2.
     *
     * Pattern: set user on one instance, then check that the SAME instance
     * is unauthenticated without an explicit logout/reset.
     */
    public function testAntiPattern2AuthStateOnSingleton(): void
    {
        $session = new SessionManager(); // one instance — simulates singleton

        // Request 1: user logs in
        $session->startSession('user-alice', 'tenant-acme');
        $this->assertSame('user-alice', $session->getUserId());
        $this->assertTrue($session->hasSession());

        // Request 2: no startSession() call — anonymous request
        // BUG: Alice's session is still present
        $this->assertSame('user-alice', $session->getUserId(),
            'ANTI-PATTERN 2 CONFIRMED: request 2 sees Alice\'s userId'
        );
        $this->assertSame('tenant-acme', $session->getTenantId(),
            'Request 2 also sees Alice\'s tenantId — data isolation broken'
        );
        $this->assertTrue($session->hasSession(),
            'hasSession() returns true for an unauthenticated request'
        );
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// PART 3 — REQUEST-SCOPED DATA ON SINGLETON
//
// CANONICAL MARKER: private string/int $context = '' with public setContext()
// ═════════════════════════════════════════════════════════════════════════════

// DIAGNOSIS: OperationLogger has `private string $correlationId = ''` set by
// setCorrelationId(). As a singleton shared between concurrent coroutines,
// coroutine B's setCorrelationId() overwrites coroutine A's value before A
// finishes logging — all of A's subsequent log lines carry B's correlation ID.
// Even sequentially, if setCorrelationId() is called late or skipped, logs
// carry the previous request's ID. Fix: pass correlationId as a log() parameter.

class OperationLogger
{
    private string $correlationId = '';  // ← DANGER: context set externally

    public function setCorrelationId(string $id): void
    {
        $this->correlationId = $id;
    }

    public function getCorrelationId(): string
    {
        return $this->correlationId;
    }

    public function log(string $message): string
    {
        // Returns the formatted log line (for test observability)
        return "[{$this->correlationId}] {$message}";
    }
}

class AntiPattern3Test extends TestCase
{
    /**
     * Test that exposes Anti-Pattern 3.
     *
     * Pattern: set context for operation A, then set context for operation B
     * using the SAME instance. Verify that operation A's context is gone.
     * Then verify that a log line produced before B's setContext() carries
     * the wrong ID.
     */
    public function testAntiPattern3RequestScopedDataOnSingleton(): void
    {
        $logger = new OperationLogger(); // one instance — simulates singleton

        // Request 1: sets correlation ID and logs
        $logger->setCorrelationId('req-001');
        $line1 = $logger->log('Operation started');
        $this->assertStringContainsString('req-001', $line1, 'First log: correct ID');

        // Request 2: sets ITS OWN correlation ID
        $logger->setCorrelationId('req-002');

        // BUG: if request 1 holds a reference to $logger and logs again,
        // it now produces lines with req-002's ID
        $line2 = $logger->log('Operation completed');
        // This line is attributed to req-002, but was meant to belong to req-001
        $this->assertStringContainsString('req-002', $line2,
            'ANTI-PATTERN 3 CONFIRMED: req-001\'s second log line carries req-002\'s ID'
        );

        // Request 1's correlation ID is gone — overwritten by request 2
        $this->assertSame('req-002', $logger->getCorrelationId(),
            'setCorrelationId() for req-002 overwrote req-001\'s ID on the shared singleton'
        );
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// PART 4 — COUNTER/STATISTICS ON SINGLETON
//
// CANONICAL MARKER: private int $count = 0 with public increment() method
// ═════════════════════════════════════════════════════════════════════════════

// DIAGNOSIS: ApiCallTracker has `private int $callCount = 0` incremented by
// recordCall(). The intended use is to enforce a per-request limit of 3 API
// calls. As a singleton, callCount accumulates across all requests. The
// per-request limit is hit after just 3 total calls since worker startup —
// every subsequent request is immediately over-limit. Fix: transient scope
// for per-request counting, or Redis INCR with a TTL for global rate limiting.

class ApiCallTracker
{
    private const MAX_CALLS = 3;
    private int $callCount = 0;         // ← DANGER: per-request counter on singleton

    public function recordCall(): void { $this->callCount++; }
    public function getCallCount(): int { return $this->callCount; }
    public function isOverLimit(): bool { return $this->callCount >= self::MAX_CALLS; }
    public function getRemainingCalls(): int { return max(0, self::MAX_CALLS - $this->callCount); }
}

class AntiPattern4Test extends TestCase
{
    /**
     * Test that exposes Anti-Pattern 4.
     *
     * Pattern: use the counter in operation 1, then simulate a fresh operation
     * on the same instance. The counter from operation 1 causes operation 2 to
     * start "already counted" — breaking the per-request invariant.
     */
    public function testAntiPattern4CounterOnSingleton(): void
    {
        $tracker = new ApiCallTracker(); // one instance — simulates singleton

        // Request 1: 2 API calls (under limit)
        $tracker->recordCall(); // 1
        $tracker->recordCall(); // 2
        $this->assertFalse($tracker->isOverLimit(), 'Request 1: 2 calls, under limit');
        $this->assertSame(1, $tracker->getRemainingCalls(), 'Request 1: 1 call remaining');

        // Request 2: 1 API call — should have 3 remaining
        $tracker->recordCall(); // 3 total (but this is request 2's first call!)

        // BUG: request 2 is over-limit after its first call
        $this->assertTrue($tracker->isOverLimit(),
            'ANTI-PATTERN 4 CONFIRMED: request 2 is over-limit after only 1 call — '
            . '2 calls leaked from request 1'
        );
        $this->assertSame(0, $tracker->getRemainingCalls(),
            'No remaining calls — request 2 is immediately throttled'
        );
        $this->assertSame(3, $tracker->getCallCount(),
            'Counter shows 3 total — 2 from request 1 + 1 from request 2'
        );
    }

    /**
     * Variant B: the counter was INTENDED to be global (for monitoring).
     * The bug is different — not over-limiting but loss of data on worker restart.
     */
    public function testAntiPattern4VariantBCounterResetsOnWorkerRestart(): void
    {
        // Simulates: counter has been running for a while
        $tracker = new ApiCallTracker();
        for ($i = 0; $i < 100; $i++) {
            $tracker->recordCall();
        }
        $this->assertSame(100, $tracker->getCallCount(), '100 calls recorded (wrong — over limit after 3)');

        // Simulates worker restart: new instance
        $freshTracker = new ApiCallTracker();
        $this->assertSame(0, $freshTracker->getCallCount(),
            'Worker restart resets count to 0 — in-memory statistics are lost'
        );
        // For durable global statistics, the count must live in Redis or a database
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// PART 5 — DEFERRED INITIALISATION THAT NEVER RESETS
//
// CANONICAL MARKER: private bool $x = false with guard clause in init method
// ═════════════════════════════════════════════════════════════════════════════

// DIAGNOSIS: ConfigLoader has `private bool $loaded = false` checked by a
// guard clause in load(). As a singleton, load() is a no-op after the first
// call. If the configuration source changes (deployment, feature flag update,
// A/B test change), the singleton never picks up the new config — every
// request sees the stale values from the initial load. Fix: PHP-DI lazy proxy
// or factory with TTL-based cache validation (L6.5).

class ConfigLoader
{
    private bool  $loaded = false;      // ← DANGER: one-way latch
    private array $config = [];
    private int   $loadedAt = 0;

    public function __construct(private readonly array $source) {}

    public function load(): void
    {
        if ($this->loaded) {
            return;                     // ← guard clause: never re-executes
        }

        $this->config   = $this->source;
        $this->loaded   = true;
        $this->loadedAt = time();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function isLoaded(): bool { return $this->loaded; }
    public function getLoadedAt(): int { return $this->loadedAt; }
}

/**
 * Simulates what happens when the config source is updated (deployment).
 * The existing singleton has $loaded = true and will never re-read the source.
 */
class AntiPattern5Test extends TestCase
{
    /**
     * Test that exposes Anti-Pattern 5.
     *
     * Pattern: load once, then change the underlying data source, then call
     * load() again. The guard clause means the new data is never read.
     */
    public function testAntiPattern5DeferredInitialisationNeverResets(): void
    {
        // Initial config: feature flag OFF
        $loader = new ConfigLoader(['feature_new_ui' => false, 'max_items' => 10]);

        // Worker startup: load is called once
        $loader->load();
        $this->assertTrue($loader->isLoaded());
        $this->assertFalse($loader->get('feature_new_ui'), 'Initial load: feature OFF');

        // Deployment: config source is updated (feature flag turned ON)
        // In production, this would be an environment variable or a config file change.
        // The new ConfigLoader instance would have the new source — but the singleton
        // is the OLD instance with $loaded = true and $config from the old source.

        // Someone calls load() again — maybe re-initialisation middleware, maybe a cache clear
        $loader->load(); // ← no-op: $this->loaded is true

        // BUG: feature flag is still OFF — new config was never read
        $this->assertFalse($loader->get('feature_new_ui'),
            'ANTI-PATTERN 5 CONFIRMED: feature flag still OFF after re-load attempt — '
            . 'guard clause prevented re-initialisation'
        );

        // To demonstrate the correct value: create a fresh instance with the new source
        $newLoader = new ConfigLoader(['feature_new_ui' => true, 'max_items' => 25]);
        $newLoader->load();
        $this->assertTrue($newLoader->get('feature_new_ui'),
            'Fresh instance picks up the new config correctly'
        );
    }

    /**
     * The flag is a one-way latch: once true, it can never be false again
     * (without direct property mutation, which violates encapsulation).
     */
    public function testLoadedFlagIsOneWayLatch(): void
    {
        $loader = new ConfigLoader(['key' => 'value']);

        $this->assertFalse($loader->isLoaded(), 'Before load(): not loaded');
        $loader->load();
        $this->assertTrue($loader->isLoaded(), 'After load(): loaded');

        // No public method exists to reset $loaded to false.
        // Once the latch flips, it stays flipped for the lifetime of the object.
        // In a persistent worker, that lifetime is the entire worker lifetime.

        // The guard clause in load() means the second call is silently ignored:
        $loader->load(); // no-op
        $loader->load(); // no-op
        $loader->load(); // no-op
        $this->assertTrue($loader->isLoaded(), 'Still loaded — no way to reset');
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// REFERENCE SUMMARY — all five canonical test patterns
//
// When auditing code, use these test structures as templates.
// The class names will change; the assertion logic is always the same.
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Reference class showing the canonical test structure for each pattern.
 * Not executed — provided as a template.
 *
 * For each pattern:
 *   1. Create one instance (simulates singleton)
 *   2. Run "operation 1" — establish state
 *   3. Run "operation 2" — check for contamination
 *   4. Assert contamination IS present (proving the bug exists)
 */
class CanonicalTestStructuresReference
{
    // Pattern 1 — Accumulating service
    // assertCount(N_TOTAL, $service->getItems()) where N_TOTAL > N_OP2

    // Pattern 2 — Auth state
    // assertSame($previousUser, $service->getCurrentUser()) on fresh "request"

    // Pattern 3 — Request-scoped data
    // setContext('A'), setContext('B'), assertSame('B', getContext()) — A is gone

    // Pattern 4 — Counter
    // increment N times in "op 1", increment 1 time in "op 2",
    // assertSame(N+1, getCount()) — not 1

    // Pattern 5 — Deferred init
    // init(), changeSource(), init() again, assertSame(OLD_VALUE, get(key))
}