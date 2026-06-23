<?php
declare(strict_types=1);

/**
 * CHALLENGE SOLUTION — Lesson 6.4: Designing Stateless Services
 * ───────────────────────────────────────────────────────────────
 * ⚠️  Only open this file after completing all ten tests yourself.
 *
 * Each refactored service includes:
 *   - A WHY comment explaining the key design move
 *   - Before/after signature comparison in comments
 *   - Notes on what the caller must now own (the state that was removed)
 *
 * Compare with your solution:
 *   1. Did you eliminate ALL instance state from each service?
 *   2. Did your "bug is gone" tests use ONE service instance for both operations?
 *   3. Did your "correct output" tests cover boundary cases (zero bytes, empty list, etc.)?
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// Service 1 — SearchIndexBuilder (refactored)
//
// WHY: The document list belongs to the caller — they know when indexing
// starts, when it ends, and what to do with the result. The builder's job
// is to process ONE document at a time and tell the caller whether it worked.
// Accumulation is the caller's responsibility.
//
// BEFORE: addDocument(string $id): void  — appended to $this->documentIds
//         getDocumentIds(): array        — returned $this->documentIds
//         getIndexedCount(): int         — returned count($this->documentIds)
//
// AFTER:  addDocument(array $documentIds, string $id): array  — returns updated list
//         indexedCount(array $documentIds): int                — pure computation
//         documentIds stays with the CALLER
// ─────────────────────────────────────────────────────────────────────────────

class SearchIndexBuilder
{
    // No private array property. No accumulated state.

    /**
     * Adds a document ID to the provided list and returns the updated list.
     *
     * CALLER PATTERN:
     *   $docs = [];
     *   $docs = $builder->addDocument($docs, 'doc-001');
     *   $docs = $builder->addDocument($docs, 'doc-002');
     *   // $docs is the caller's variable — the builder never owns it
     */
    public function addDocument(array $documentIds, string $id): array
    {
        $documentIds[] = $id;
        return $documentIds;
    }

    /**
     * Records an error. Returns the new error count.
     * Caller owns the running error count.
     */
    public function recordError(int $currentErrorCount): int
    {
        return $currentErrorCount + 1;
    }

    /**
     * Pure computation: count of documents in the provided list.
     * Identical to count($documentIds) — provided as a named method for readability.
     */
    public function indexedCount(array $documentIds): int
    {
        return count($documentIds);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Service 2 — CurrentOperationContext (refactored)
//
// WHY: A "context" is a fact about one operation at one point in time.
// Facts do not change. The correct representation is an immutable value object,
// not a mutable object that is "configured" via setters. The caller creates a
// new context object for each operation — no shared state is possible.
//
// BEFORE: mutable properties + beginOperation() setter
// AFTER:  readonly properties set at construction — immutable value object
//
// NOTE: this service is now technically a value object, not a service.
// That is correct — "current operation context" IS a value, not a behaviour.
// ─────────────────────────────────────────────────────────────────────────────

final class CurrentOperationContext
{
    // All readonly — set once at construction, immutable thereafter.
    public function __construct(
        public readonly string $operationName,
        public readonly int    $startedAt,     // unix timestamp
    ) {}

    /**
     * Named constructor for convenience — provides the current timestamp.
     */
    public static function begin(string $name): self
    {
        return new self(operationName: $name, startedAt: time());
    }

    /**
     * Named constructor for testing — allows a fixed timestamp.
     */
    public static function beginAt(string $name, int $startedAt): self
    {
        return new self(operationName: $name, startedAt: $startedAt);
    }

    public function isActive(): bool
    {
        // An immutable context represents an active operation by its existence.
        // The caller destroys (discards) the context when the operation ends.
        return true;
    }

    public function durationSeconds(): int
    {
        return time() - $this->startedAt;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Service 3 — BandwidthMonitor (refactored)
//
// WHY: The running byte total is per-request state. The monitor's job is to
// apply the RULES (limit, remaining bytes calculation) — not to own the count.
// The caller passes in the current total and gets back the new total or a
// predicate answer. The caller owns the number; the monitor owns the rules.
//
// BEFORE: recordBytes(int $bytes): void  — mutated $this->totalBytes
//         isOverLimit(): bool            — read $this->totalBytes
//
// AFTER:  recordBytes(int $currentTotal, int $bytes): int  — returns new total
//         isOverLimit(int $total): bool                    — pure predicate
//         getRemainingBytes(int $total): int               — pure computation
// ─────────────────────────────────────────────────────────────────────────────

class BandwidthMonitor
{
    private const LIMIT_BYTES = 1_048_576; // 1 MB — the RULE, not the state

    // No $totalBytes property. The running total is owned by the caller.

    /**
     * Returns the new running total after recording $bytes more transferred.
     *
     * CALLER PATTERN:
     *   $total = 0;
     *   $total = $monitor->recordBytes($total, 512_000);
     *   $total = $monitor->recordBytes($total, 200_000);
     *   if ($monitor->isOverLimit($total)) { ... }
     */
    public function recordBytes(int $currentTotal, int $bytes): int
    {
        return $currentTotal + $bytes;
    }

    /**
     * Pure predicate: has $total exceeded the limit?
     * Same input → same output. No instance state involved.
     */
    public function isOverLimit(int $total): bool
    {
        return $total >= self::LIMIT_BYTES;
    }

    /**
     * Pure computation: how many bytes remain before the limit is hit?
     */
    public function getRemainingBytes(int $total): int
    {
        return max(0, self::LIMIT_BYTES - $total);
    }

    public function getLimit(): int
    {
        return self::LIMIT_BYTES;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Service 4 — FeatureFlagService (refactored)
//
// WHY: The boolean latch existed to prevent repeated expensive loading. The
// correct fix is to move the load into the constructor — PHP-DI calls the
// constructor once (for a singleton), so loading happens exactly once. No
// flag, no guard clause, no way for the latch to stick in the wrong state.
//
// If config must refresh during a worker's lifetime, the solution is PHP-DI's
// factory() registration with a TTL check (covered in Lesson 6.5), not a
// re-callable boot() method on the same singleton.
//
// BEFORE: $booted = false  +  boot() with guard clause
// AFTER:  constructor eagerly loads — no $booted, no boot()
// ─────────────────────────────────────────────────────────────────────────────

class FeatureFlagService
{
    // No $booted flag. No boot() method.
    private array $flags;

    public function __construct(array $configSource)
    {
        // Load eagerly at construction time.
        // PHP-DI calls this constructor once (singleton) — loading happens once.
        // No flag needed because the constructor cannot be called twice on the
        // same instance.
        $this->flags = $configSource;
    }

    public function isEnabled(string $flag): bool
    {
        return (bool) ($this->flags[$flag] ?? false);
    }

    public function all(): array
    {
        return $this->flags;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Service 5 — NotificationQueue (refactored)
//
// WHY: The pending notifications list belongs to the request — not to a
// shared service. The queue's job is to provide append and flush OPERATIONS
// (pure functions), not to store the list. The caller holds $pending in
// their own variable and passes it to enqueue() and flush().
//
// BEFORE: enqueue(string $type, array $payload): void  — appended to $this->pending
//         flush(): array                               — returned and cleared $this->pending
//
// AFTER:  enqueue(array $pending, string $type, array $payload): array  — returns updated list
//         flush(array $pending): array                                   — returns list (caller resets)
//
// CALLER PATTERN for a request:
//   $pending = [];
//   $pending = $queue->enqueue($pending, 'email', [...]);
//   $pending = $queue->enqueue($pending, 'webhook', [...]);
//   $sent    = $queue->flush($pending);
//   $pending = []; // caller resets — queue has nothing to reset
// ─────────────────────────────────────────────────────────────────────────────

class NotificationQueue
{
    // No $pending property. The pending list is owned by the caller.

    /**
     * Returns a new pending list with the notification appended.
     * Does not mutate any instance state.
     */
    public function enqueue(array $pending, string $type, array $payload): array
    {
        $pending[] = ['type' => $type, 'payload' => $payload];
        return $pending;
    }

    /**
     * Returns the notifications to be sent. The caller is responsible for
     * resetting their $pending variable after flush.
     *
     * Stateless: flush() is now a pure identity function — it just returns
     * what it receives. The "clearing" is the caller doing: $pending = []
     */
    public function flush(array $pending): array
    {
        return $pending;
    }

    /**
     * Pure computation: count of notifications in the provided list.
     */
    public function pendingCount(array $pending): int
    {
        return count($pending);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Tests
// ─────────────────────────────────────────────────────────────────────────────

class StatelessRefactorTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────────────────
    // Service 1 — SearchIndexBuilder
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Bug is gone: same instance, two indexing runs, second run starts from
     * its own empty list — not the list accumulated by run 1.
     */
    public function testSearchIndexBuilderBugIsGone(): void
    {
        $builder = new SearchIndexBuilder(); // singleton — safe now

        // ── Indexing run 1 ────────────────────────────────────────────────────
        $docs1 = [];
        $docs1 = $builder->addDocument($docs1, 'doc-001');
        $docs1 = $builder->addDocument($docs1, 'doc-002');
        $docs1 = $builder->addDocument($docs1, 'doc-003');

        $this->assertSame(3, $builder->indexedCount($docs1), 'Run 1: 3 documents');

        // ── Indexing run 2 (same builder instance) ────────────────────────────
        $docs2 = []; // ← caller starts fresh — NO connection to $docs1
        $docs2 = $builder->addDocument($docs2, 'doc-004');
        $docs2 = $builder->addDocument($docs2, 'doc-005');

        // Bug is gone: run 2 sees only its own 2 documents
        $this->assertSame(2, $builder->indexedCount($docs2),
            'Run 2: 2 documents — no contamination from run 1'
        );
        $this->assertNotContains('doc-001', $docs2,
            'Run 1\'s doc-001 is not in run 2\'s document list'
        );

        // Run 1's list is also unaffected by run 2
        $this->assertSame(3, $builder->indexedCount($docs1),
            'Run 1\'s document list unchanged after run 2'
        );
    }

    /**
     * Correct output: addDocument() correctly builds the document list.
     * recordError() correctly accumulates the error count.
     */
    public function testSearchIndexBuilderProducesCorrectOutput(): void
    {
        $builder = new SearchIndexBuilder();

        $docs       = [];
        $errorCount = 0;

        $docs = $builder->addDocument($docs, 'doc-a');
        $docs = $builder->addDocument($docs, 'doc-b');
        $docs = $builder->addDocument($docs, 'doc-c');

        $errorCount = $builder->recordError($errorCount); // 1 error

        $this->assertSame(3, $builder->indexedCount($docs));
        $this->assertSame(['doc-a', 'doc-b', 'doc-c'], $docs);
        $this->assertSame(1, $errorCount);

        // Adding more documents after an error continues correctly
        $docs = $builder->addDocument($docs, 'doc-d');
        $this->assertSame(4, $builder->indexedCount($docs));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Service 2 — CurrentOperationContext
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Bug is gone: two context objects are independent.
     * Setting up context for operation 2 does not affect operation 1's context.
     * (This was not possible before — both operations used the same mutable object.)
     */
    public function testCurrentOperationContextBugIsGone(): void
    {
        // Two operations create their own independent context objects
        $ctx1 = CurrentOperationContext::beginAt('process-payment', 1_000_000);
        $ctx2 = CurrentOperationContext::beginAt('send-email',      2_000_000);

        // Each context is independent
        $this->assertSame('process-payment', $ctx1->operationName);
        $this->assertSame('send-email',      $ctx2->operationName);
        $this->assertSame(1_000_000, $ctx1->startedAt);
        $this->assertSame(2_000_000, $ctx2->startedAt);

        // Contexts are different objects
        $this->assertNotSame($ctx1, $ctx2);

        // Creating ctx2 did not modify ctx1 (impossible — it's immutable)
        $this->assertSame('process-payment', $ctx1->operationName,
            'ctx1 is unchanged after ctx2 was created'
        );
    }

    /**
     * Context correctly carries its construction data.
     * isActive() is always true for any existing context (the object's existence IS the active state).
     */
    public function testCurrentOperationContextCarriesCorrectData(): void
    {
        $ctx = CurrentOperationContext::beginAt('generate-report', 1_748_000_000);

        $this->assertSame('generate-report', $ctx->operationName);
        $this->assertSame(1_748_000_000, $ctx->startedAt);
        $this->assertTrue($ctx->isActive());

        // Named constructor without fixed time works
        $ctx2 = CurrentOperationContext::begin('live-operation');
        $this->assertSame('live-operation', $ctx2->operationName);
        $this->assertGreaterThan(0, $ctx2->startedAt);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Service 3 — BandwidthMonitor
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Bug is gone: same monitor instance, two request simulations.
     * Request 2 starts its own $total at 0 — no carryover from request 1.
     */
    public function testBandwidthMonitorBugIsGone(): void
    {
        $monitor = new BandwidthMonitor(); // singleton — safe now

        // ── Request 1: transfers 600 KB ───────────────────────────────────────
        $total1 = 0;
        $total1 = $monitor->recordBytes($total1, 614_400); // 600 KB
        $this->assertFalse($monitor->isOverLimit($total1), 'Request 1: under limit');

        // ── Request 2: transfers 500 KB — should have fresh budget ────────────
        $total2 = 0; // ← caller's own fresh variable
        $total2 = $monitor->recordBytes($total2, 512_000); // 500 KB

        // Bug is gone: request 2 is NOT over limit
        $this->assertFalse($monitor->isOverLimit($total2),
            'Bug is gone: request 2 is under its own limit (500 KB < 1 MB)'
        );
        $this->assertSame(512_000, $total2, 'Request 2 total: 500 KB only');
        $this->assertSame(536_576, $monitor->getRemainingBytes($total2),
            'Request 2 has 524 KB remaining'
        );
    }

    /**
     * isOverLimit() and getRemainingBytes() are correct pure predicates.
     */
    public function testBandwidthMonitorEnforcesLimitCorrectly(): void
    {
        $monitor = new BandwidthMonitor();
        $limit   = $monitor->getLimit(); // 1_048_576

        $this->assertFalse($monitor->isOverLimit(0),            '0 bytes: under limit');
        $this->assertFalse($monitor->isOverLimit($limit - 1),   '1 byte under limit: still under');
        $this->assertTrue($monitor->isOverLimit($limit),        'Exactly at limit: over');
        $this->assertTrue($monitor->isOverLimit($limit + 1000), 'Over limit: over');

        $this->assertSame($limit,   $monitor->getRemainingBytes(0),          '0 bytes used: full limit remaining');
        $this->assertSame(548_576,  $monitor->getRemainingBytes(500_000),    '~476 KB remaining after 500 KB');
        $this->assertSame(0,        $monitor->getRemainingBytes($limit),     'At limit: 0 remaining');
        $this->assertSame(0,        $monitor->getRemainingBytes($limit * 2), 'Over limit: 0 remaining (not negative)');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Service 4 — FeatureFlagService
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Bug is gone: a new instance with updated config reflects the update
     * immediately — no boot() call required, no latch to get stuck.
     *
     * The mechanism for "picking up a deployment config change" is:
     *   → PHP-DI constructs a new FeatureFlagService with the new config
     *   → The constructor loads it immediately
     *   → No flag, no guard, no stale state
     */
    public function testFeatureFlagServiceBugIsGone(): void
    {
        // Initial deployment: dark mode OFF
        $service = new FeatureFlagService(['feature_dark_mode' => false]);
        $this->assertFalse($service->isEnabled('feature_dark_mode'));

        // New deployment: new instance with updated config — no boot() required
        $updatedService = new FeatureFlagService(['feature_dark_mode' => true]);
        $this->assertTrue($updatedService->isEnabled('feature_dark_mode'),
            'New instance immediately reflects updated config — no boot() needed'
        );

        // Old service is unaffected (it would be replaced in the container)
        $this->assertFalse($service->isEnabled('feature_dark_mode'),
            'Old service still has old config — isolation is correct'
        );
    }

    /**
     * isEnabled() returns correct values for all flag states.
     */
    public function testFeatureFlagServiceReturnsCorrectFlags(): void
    {
        $service = new FeatureFlagService([
            'feature_dark_mode'  => true,
            'feature_beta_users' => false,
            'feature_analytics'  => true,
        ]);

        $this->assertTrue($service->isEnabled('feature_dark_mode'));
        $this->assertFalse($service->isEnabled('feature_beta_users'));
        $this->assertTrue($service->isEnabled('feature_analytics'));

        // Absent flag defaults to false
        $this->assertFalse($service->isEnabled('feature_nonexistent'));

        // all() returns the complete flag map
        $this->assertCount(3, $service->all());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Service 5 — NotificationQueue
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Bug is gone: same queue instance, two request simulations.
     * Request 2's notifications come only from request 2 — request 1's
     * unflushed notifications cannot appear in request 2's flush.
     */
    public function testNotificationQueueBugIsGone(): void
    {
        $queue = new NotificationQueue(); // singleton — safe now

        // ── Request 1: enqueues 2 notifications, then "crashes" (no flush) ────
        $pending1 = [];
        $pending1 = $queue->enqueue($pending1, 'email',   ['to' => 'alice@example.com']);
        $pending1 = $queue->enqueue($pending1, 'webhook', ['url' => 'https://acme.example.com/hook']);

        $this->assertSame(2, $queue->pendingCount($pending1));
        // No flush called for request 1 — $pending1 is discarded (caller's variable goes out of scope)

        // ── Request 2: completely independent pending list ────────────────────
        $pending2 = []; // ← caller's own fresh variable — no connection to $pending1
        $pending2 = $queue->enqueue($pending2, 'email', ['to' => 'bob@example.com']);

        $sent2 = $queue->flush($pending2);
        $pending2 = []; // caller resets after flush

        // Bug is gone: only Bob's notification is sent — Alice's are NOT present
        $this->assertCount(1, $sent2,
            'Bug is gone: request 2 flush returns only 1 notification (Bob\'s)'
        );

        $recipients = array_map(fn($n) => $n['payload']['to'] ?? 'no-email', $sent2);
        $this->assertContains('bob@example.com', $recipients);
        $this->assertNotContains('alice@example.com', $recipients,
            'Alice\'s notification (from request 1) is not in request 2\'s flush'
        );
    }

    /**
     * Full enqueue/flush cycle: enqueue multiple notifications, flush them,
     * verify all are returned, verify the caller's pending is empty after flush.
     */
    public function testNotificationQueueEnqueueAndFlushWorkCorrectly(): void
    {
        $queue   = new NotificationQueue();
        $pending = [];

        // Enqueue three notifications
        $pending = $queue->enqueue($pending, 'email',   ['to' => 'alice@example.com', 'subject' => 'Invoice']);
        $pending = $queue->enqueue($pending, 'email',   ['to' => 'bob@example.com',   'subject' => 'Welcome']);
        $pending = $queue->enqueue($pending, 'webhook', ['url' => 'https://example.com/hook', 'event' => 'signup']);

        $this->assertSame(3, $queue->pendingCount($pending), '3 notifications pending');

        // Flush — returns all three
        $sent    = $queue->flush($pending);
        $pending = []; // caller resets

        $this->assertCount(3, $sent, 'All 3 notifications returned by flush');
        $this->assertSame(0, $queue->pendingCount($pending), 'After reset: 0 pending');

        // Verify notification content
        $this->assertSame('email',   $sent[0]['type']);
        $this->assertSame('alice@example.com', $sent[0]['payload']['to']);

        $this->assertSame('webhook', $sent[2]['type']);
        $this->assertSame('signup',  $sent[2]['payload']['event']);

        // Queue can be used again immediately — no state to reset
        $pending = $queue->enqueue($pending, 'sms', ['to' => '+44-7700-000001']);
        $this->assertSame(1, $queue->pendingCount($pending), 'New cycle: 1 notification');
    }
}