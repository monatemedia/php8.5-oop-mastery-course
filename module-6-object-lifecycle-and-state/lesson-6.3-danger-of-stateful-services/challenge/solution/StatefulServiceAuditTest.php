<?php
declare(strict_types=1);

/**
 * CHALLENGE SOLUTION — Lesson 6.3: The Danger of Stateful Services
 * ──────────────────────────────────────────────────────────────────
 * ⚠️  Only open this file after completing all five tests yourself.
 *
 * Solution summary:
 *   Service 1 (SearchIndexBuilder)      → Anti-pattern 1: Accumulating service
 *   Service 2 (CurrentOperationContext) → Anti-pattern 3: Request-scoped data on singleton
 *   Service 3 (BandwidthMonitor)        → Anti-pattern 4: Counter/statistics on singleton
 *   Service 4 (FeatureFlagService)      → Anti-pattern 5: Deferred init that never resets
 *   Service 5 (NotificationQueue)       → Anti-pattern 1: Accumulating service (variant)
 *
 * Note: services 1 and 5 are both anti-pattern 1. That is intentional —
 * in real codebases, the accumulating-array pattern is by far the most common.
 * Two instances illustrate that the same pattern can appear in very different
 * business contexts (indexing vs notification dispatch) and with different
 * real-world consequences.
 *
 * Key things to compare with your solution:
 *   1. Did your tests use ONE instance for both operations? (critical)
 *   2. Did you assert that contamination IS present, not that it is absent?
 *   3. Did your fix classifications match the service's stated purpose?
 *      (BandwidthMonitor is per-request → transient; a global byte-counter
 *      would need external store)
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// Service classes (identical to starter — do not modify)
// ─────────────────────────────────────────────────────────────────────────────

class SearchIndexBuilder
{
    private array $documentIds = [];
    private int   $errorCount  = 0;

    public function addDocument(string $id): void { $this->documentIds[] = $id; }
    public function recordError(): void           { $this->errorCount++; }
    public function getIndexedCount(): int        { return count($this->documentIds); }
    public function getErrorCount(): int          { return $this->errorCount; }
    public function getDocumentIds(): array       { return $this->documentIds; }
}

class CurrentOperationContext
{
    private ?string $operationName = null;
    private ?int    $startedAt     = null;

    public function beginOperation(string $name): void
    {
        $this->operationName = $name;
        $this->startedAt     = time();
    }

    public function endOperation(): void
    {
        $this->operationName = null;
        $this->startedAt     = null;
    }

    public function getOperationName(): ?string { return $this->operationName; }
    public function getStartedAt(): ?int        { return $this->startedAt; }
    public function isActive(): bool            { return $this->operationName !== null; }
}

class BandwidthMonitor
{
    private const LIMIT_BYTES = 1_048_576;
    private int $totalBytes = 0;

    public function recordBytes(int $bytes): void  { $this->totalBytes += $bytes; }
    public function getTotalBytes(): int           { return $this->totalBytes; }
    public function isOverLimit(): bool            { return $this->totalBytes >= self::LIMIT_BYTES; }
    public function getRemainingBytes(): int       { return max(0, self::LIMIT_BYTES - $this->totalBytes); }
}

class FeatureFlagService
{
    private bool  $booted  = false;
    private array $flags   = [];

    public function __construct(private readonly array $configSource) {}

    public function boot(): void
    {
        if ($this->booted) { return; }
        $this->flags  = $this->configSource;
        $this->booted = true;
    }

    public function isEnabled(string $flag): bool { return (bool) ($this->flags[$flag] ?? false); }
    public function isBooted(): bool              { return $this->booted; }
}

class NotificationQueue
{
    private array $pending = [];

    public function enqueue(string $type, array $payload): void
    {
        $this->pending[] = ['type' => $type, 'payload' => $payload];
    }

    public function flush(): array
    {
        $notifications = $this->pending;
        $this->pending = [];
        return $notifications;
    }

    public function getPendingCount(): int { return count($this->pending); }
    public function hasPending(): bool     { return !empty($this->pending); }
}

// ─────────────────────────────────────────────────────────────────────────────
// Solution tests
// ─────────────────────────────────────────────────────────────────────────────

class StatefulServiceAuditTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────────────────
    // Service 1 — SearchIndexBuilder
    // ANTI-PATTERN: #1 — Accumulating service
    //   private array $documentIds = []  appended by addDocument()
    //   private int   $errorCount  = 0   incremented by recordError()
    //   Both properties accumulate; neither is reset between indexing runs.
    //
    // FIX: transient scope — each indexing job is independent and short-lived;
    // the accumulated document IDs and error count are only meaningful within
    // one job and should not carry over to the next. Registering as transient
    // in PHP-DI ensures each job receives a fresh builder with empty state.
    // ─────────────────────────────────────────────────────────────────────────

    public function testSearchIndexBuilderBug(): void
    {
        // ONE instance — simulates persistent-worker singleton
        $builder = new SearchIndexBuilder();

        // ── Indexing job 1: index 3 documents, 1 error ───────────────────────
        $builder->addDocument('doc-001');
        $builder->addDocument('doc-002');
        $builder->addDocument('doc-003');
        $builder->recordError(); // one document failed to parse

        $this->assertSame(3, $builder->getIndexedCount(), 'Job 1: 3 indexed');
        $this->assertSame(1, $builder->getErrorCount(),   'Job 1: 1 error');

        // ── Indexing job 2: index 2 documents, 0 errors ──────────────────────
        // Should see: 2 documents, 0 errors
        $builder->addDocument('doc-004');
        $builder->addDocument('doc-005');

        // BUG: document count is 5 (3 leaked from job 1) — job 2 reports inflated totals
        $this->assertSame(5, $builder->getIndexedCount(),
            'ANTI-PATTERN 1 CONFIRMED: 5 documents — 3 leaked from job 1'
        );

        // BUG: error count is still 1 — job 2 reports job 1's error
        $this->assertSame(1, $builder->getErrorCount(),
            'BUG: error count 1 leaked from job 1 — job 2 had no errors'
        );

        // Job 1's document IDs appear in job 2's index
        $this->assertContains('doc-001', $builder->getDocumentIds(),
            'BUG: doc-001 (from job 1) appears in job 2\'s document list'
        );

        // The operations team sees job 2 as having 5 documents indexed and 1 error,
        // when in reality it indexed 2 documents with no errors.
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Service 2 — CurrentOperationContext
    // ANTI-PATTERN: #3 — Request-scoped data on singleton
    //   private ?string $operationName = null  set by beginOperation()
    //   private ?int    $startedAt     = null  set by beginOperation()
    //   Both are per-operation context values set by a public method.
    //   When shared as a singleton, a new operation's beginOperation() call
    //   overwrites the previous operation's context — and if beginOperation()
    //   is not called (skipped code path, async interleave), the previous
    //   operation's name is returned as the "current" operation.
    //
    // FIX: transient scope — each operation must have its own independent
    // context; the operation name and start time have no meaning beyond the
    // single operation they describe. A fresh transient instance guarantees
    // that getOperationName() returns null for any operation that has not
    // explicitly called beginOperation().
    // ─────────────────────────────────────────────────────────────────────────

    public function testCurrentOperationContextBug(): void
    {
        $context = new CurrentOperationContext(); // one instance

        // ── Operation 1: "process-payment" ───────────────────────────────────
        $context->beginOperation('process-payment');
        $this->assertSame('process-payment', $context->getOperationName());
        $this->assertTrue($context->isActive());

        // Operation 1 ends — but endOperation() is NOT called.
        // (Common omission: exception thrown, early return, missing finally block)

        // ── Operation 2: should start with no active operation ────────────────
        // A different part of the codebase reads the context for logging
        // BEFORE calling beginOperation() for this operation.

        // BUG: 'process-payment' is still the current operation
        $this->assertSame('process-payment', $context->getOperationName(),
            'ANTI-PATTERN 3 CONFIRMED: operation 2 sees "process-payment" context '
            . 'from operation 1 — endOperation() was never called'
        );
        $this->assertTrue($context->isActive(),
            'BUG: isActive() is true for a new operation that has not started yet'
        );

        // Even when beginOperation() IS called for operation 2, the previous
        // context is only gone because it was overwritten — not because it was
        // properly isolated. An operation that skips beginOperation() entirely
        // will silently inherit whatever operation ran before it.
        $context->beginOperation('send-email');
        $this->assertSame('send-email', $context->getOperationName(),
            'Operation 2\'s beginOperation() OVERWRITES operation 1\'s context — '
            . 'but this only works if beginOperation() is always called on time'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Service 3 — BandwidthMonitor
    // ANTI-PATTERN: #4 — Counter/statistics on singleton
    //   private int $totalBytes = 0  incremented by recordBytes()
    //   Intended as a per-request byte counter (limit: 1 MB per request).
    //   As a singleton, totalBytes accumulates across all requests. The 1 MB
    //   limit is hit globally, not per-request — requests are throttled after
    //   a combined total of 1 MB, regardless of individual request sizes.
    //
    // FIX: transient scope — the byte count is meaningful only within a single
    // request; the 1 MB limit is a per-request budget, not a global quota.
    // Transient scope ensures each request starts its budget at 0 bytes.
    // (If the intent were a global bandwidth quota shared across all requests,
    // the counter would need to live in Redis with a sliding window TTL.)
    // ─────────────────────────────────────────────────────────────────────────

    public function testBandwidthMonitorBug(): void
    {
        $monitor = new BandwidthMonitor(); // one instance

        // ── Request 1: transfers 600 KB (614,400 bytes) ──────────────────────
        $monitor->recordBytes(614_400); // 600 KB
        $this->assertFalse($monitor->isOverLimit(), 'Request 1: 600 KB — under 1 MB limit');
        $this->assertSame(434_176, $monitor->getRemainingBytes(),
            'Request 1: 434 KB remaining'
        );

        // ── Request 2: should have a fresh 1 MB budget ───────────────────────
        // Transfers 500 KB — under the 1 MB limit on its own
        $monitor->recordBytes(512_000); // 500 KB

        // BUG: combined total is 1,126,400 bytes (600 KB + 500 KB) — over limit
        $this->assertTrue($monitor->isOverLimit(),
            'ANTI-PATTERN 4 CONFIRMED: request 2 is over limit after 500 KB — '
            . '600 KB leaked from request 1 pushed the total over 1 MB'
        );
        $this->assertSame(1_126_400, $monitor->getTotalBytes(),
            'BUG: 1,126,400 bytes total — request 1\'s 614,400 bytes accumulated'
        );
        $this->assertSame(0, $monitor->getRemainingBytes(),
            'BUG: 0 bytes remaining — request 2\'s 500 KB transfer is throttled'
        );

        // Request 2 transferred only 500 KB but is treated as if it transferred 1.07 MB.
        // Legitimate requests are rejected; the monitor is useless for per-request enforcement.
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Service 4 — FeatureFlagService
    // ANTI-PATTERN: #5 — Deferred initialisation that never resets
    //   private bool $booted = false  — one-way latch set by boot()
    //   guard clause: if ($this->booted) return;
    //   Once booted, the feature flags loaded at worker startup are served
    //   forever — even if a deployment updates the flags mid-lifetime.
    //
    // FIX: stateless redesign — rather than loading flags once and guarding
    // with a boolean, inject an immutable FlagConfiguration value object
    // at construction time so that PHP-DI (singleton or transient) always
    // provides the correct flags from the composition root; alternatively,
    // use PHP-DI's lazy proxy (Lesson 6.5) to defer and control re-construction.
    // ─────────────────────────────────────────────────────────────────────────

    public function testFeatureFlagServiceBug(): void
    {
        // Worker starts: feature_dark_mode is OFF in the initial config
        $service = new FeatureFlagService(['feature_dark_mode' => false, 'feature_beta' => false]);
        $service->boot();

        $this->assertTrue($service->isBooted());
        $this->assertFalse($service->isEnabled('feature_dark_mode'),
            'Initial boot: dark mode is OFF'
        );

        // Deployment: a new config is pushed. feature_dark_mode is NOW ON.
        // In production, the PHP-DI container would reconstruct the object if
        // it were not a singleton — but it IS a singleton, so the constructor
        // argument (the new config source) is never used.
        //
        // Simulate: code that "knows" about the new config tries to re-boot
        $newConfigService = new FeatureFlagService(['feature_dark_mode' => true, 'feature_beta' => true]);
        // But the SAME singleton instance is what other code holds references to.
        // Re-calling boot() on the existing instance does nothing:
        $service->boot(); // ← no-op: $booted is true

        // BUG: feature_dark_mode is still OFF — the guard clause prevented re-loading
        $this->assertFalse($service->isEnabled('feature_dark_mode'),
            'ANTI-PATTERN 5 CONFIRMED: dark mode still OFF after deployment update — '
            . 'boot() was a no-op; the stale config from worker startup is served'
        );

        // The new config IS correct in a fresh instance, but that instance
        // is never what consuming code receives from the singleton container:
        $newConfigService->boot();
        $this->assertTrue($newConfigService->isEnabled('feature_dark_mode'),
            'Fresh instance with new config: dark mode IS on — but consuming code '
            . 'holds the old singleton, not this fresh instance'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Service 5 — NotificationQueue
    // ANTI-PATTERN: #1 — Accumulating service (variant: queue with flush)
    //   private array $pending = []  appended by enqueue()
    //   flush() clears the array — but only if called at the right time.
    //   As a singleton, notifications enqueued in request 1 that are NOT
    //   flushed (e.g. an exception terminated request 1 early) remain in
    //   $pending when request 2 begins. Request 2's flush() sends request 1's
    //   notifications — potentially duplicating emails/webhooks and sending
    //   them to the wrong recipients at the wrong time.
    //
    // FIX: transient scope — each request's notification batch is independent;
    // request 2 must never send request 1's notifications. Transient scope
    // guarantees $pending = [] at the start of every request. Note that flush()
    // also clears the array, so for clean code paths this works — but transient
    // scope makes the clean-state invariant unconditional, not dependent on
    // flush() being called.
    // ─────────────────────────────────────────────────────────────────────────

    public function testNotificationQueueBug(): void
    {
        $queue = new NotificationQueue(); // one instance

        // ── Request 1: enqueues 2 notifications, then crashes before flush ────
        $queue->enqueue('email', ['to' => 'alice@example.com', 'subject' => 'Invoice ready']);
        $queue->enqueue('webhook', ['url' => 'https://acme.example.com/hook', 'event' => 'order.created']);

        $this->assertSame(2, $queue->getPendingCount(), 'Request 1: 2 notifications queued');

        // Exception thrown! flush() is never called for request 1.
        // (The try/catch that would call flush() is in a catch block that was never reached,
        // or a missing finally, or an unhandled error.)

        // ── Request 2: a completely different user's request ──────────────────
        // Request 2 enqueues its own notification
        $queue->enqueue('email', ['to' => 'bob@example.com', 'subject' => 'Your order shipped']);

        // Request 2 flushes the queue at the end of its processing
        $notifications = $queue->flush();

        // BUG: flush() returns 3 notifications — 2 from request 1 that were never sent
        $this->assertCount(3, $notifications,
            'ANTI-PATTERN 1 (VARIANT) CONFIRMED: flush() returns 3 notifications — '
            . '2 from request 1 that were never flushed are now sent during request 2'
        );

        // Alice's email is sent during Bob's request — at the wrong time,
        // possibly hours after request 1 ended
        $recipients = array_map(
            fn($n) => $n['payload']['to'] ?? $n['payload']['url'] ?? 'unknown',
            $notifications
        );
        $this->assertContains('alice@example.com', $recipients,
            'BUG: Alice\'s email (from request 1) is included in request 2\'s flush'
        );
        $this->assertContains('bob@example.com', $recipients,
            'Bob\'s email is correctly present'
        );

        // Additionally, after flush(), the queue is empty — so at least flush()
        // prevents infinite resending. But request 1's notifications were sent late,
        // out of sequence, and potentially duplicated if request 1 eventually retried.
        $this->assertSame(0, $queue->getPendingCount(),
            'After flush(): queue is empty (but damage is done — stale notifications were sent)'
        );
    }
}