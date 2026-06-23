<?php
declare(strict_types=1);

/**
 * CHALLENGE STARTER — Lesson 6.3: The Danger of Stateful Services
 * ─────────────────────────────────────────────────────────────────
 * Read CHALLENGE.md before touching this file.
 *
 * For each of the five services:
 *   1. Identify the anti-pattern (use the README Section 7 marker table)
 *   2. Uncomment and complete the test method
 *   3. Add // ANTI-PATTERN: comment
 *   4. Add // FIX: comment with your classification
 *
 * Test structure (same for all five):
 *   - Create ONE instance (simulates persistent singleton)
 *   - Run "operation 1" — establishes state
 *   - Run "operation 2" on the SAME instance — no reset
 *   - Assert that contamination IS present (proves the bug)
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// Pre-defined service classes — DO NOT modify these
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Service 1: Builds a search index by accumulating document IDs.
 */
class SearchIndexBuilder
{
    private array $documentIds = [];
    private int   $errorCount  = 0;

    public function addDocument(string $id): void
    {
        $this->documentIds[] = $id;
    }

    public function recordError(): void
    {
        $this->errorCount++;
    }

    public function getIndexedCount(): int
    {
        return count($this->documentIds);
    }

    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    public function getDocumentIds(): array
    {
        return $this->documentIds;
    }
}

/**
 * Service 2: Stores context about the currently executing operation.
 */
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

/**
 * Service 3: Counts bytes transferred during the current request.
 * Enforces a per-request limit of 1 MB (1,048,576 bytes).
 */
class BandwidthMonitor
{
    private const LIMIT_BYTES = 1_048_576; // 1 MB
    private int $totalBytes = 0;

    public function recordBytes(int $bytes): void
    {
        $this->totalBytes += $bytes;
    }

    public function getTotalBytes(): int { return $this->totalBytes; }

    public function isOverLimit(): bool  { return $this->totalBytes >= self::LIMIT_BYTES; }

    public function getRemainingBytes(): int
    {
        return max(0, self::LIMIT_BYTES - $this->totalBytes);
    }
}

/**
 * Service 4: Loads feature flag overrides from a config source on first boot.
 */
class FeatureFlagService
{
    private bool  $booted  = false;
    private array $flags   = [];

    public function __construct(private readonly array $configSource) {}

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->flags  = $this->configSource;
        $this->booted = true;
    }

    public function isEnabled(string $flag): bool
    {
        return (bool) ($this->flags[$flag] ?? false);
    }

    public function isBooted(): bool { return $this->booted; }
}

/**
 * Service 5: Queues outgoing notifications for dispatch at end of request.
 */
class NotificationQueue
{
    private array $pending = [];

    public function enqueue(string $type, array $payload): void
    {
        $this->pending[] = ['type' => $type, 'payload' => $payload];
    }

    public function flush(): array
    {
        $notifications  = $this->pending;
        $this->pending  = [];
        return $notifications;
    }

    public function getPendingCount(): int
    {
        return count($this->pending);
    }

    public function hasPending(): bool
    {
        return !empty($this->pending);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Your tests
// ─────────────────────────────────────────────────────────────────────────────

class StatefulServiceAuditTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────────────────
    // Service 1 — SearchIndexBuilder
    // ANTI-PATTERN: TODO — which of the five patterns is this?
    // FIX: TODO — transient scope | external store | stateless redesign + reason
    // ─────────────────────────────────────────────────────────────────────────

    // TODO: public function testSearchIndexBuilderBug(): void {}

    // ─────────────────────────────────────────────────────────────────────────
    // Service 2 — CurrentOperationContext
    // ANTI-PATTERN: TODO
    // FIX: TODO
    // ─────────────────────────────────────────────────────────────────────────

    // TODO: public function testCurrentOperationContextBug(): void {}

    // ─────────────────────────────────────────────────────────────────────────
    // Service 3 — BandwidthMonitor
    // ANTI-PATTERN: TODO
    // FIX: TODO
    // ─────────────────────────────────────────────────────────────────────────

    // TODO: public function testBandwidthMonitorBug(): void {}

    // ─────────────────────────────────────────────────────────────────────────
    // Service 4 — FeatureFlagService
    // ANTI-PATTERN: TODO
    // FIX: TODO
    // ─────────────────────────────────────────────────────────────────────────

    // TODO: public function testFeatureFlagServiceBug(): void {}

    // ─────────────────────────────────────────────────────────────────────────
    // Service 5 — NotificationQueue
    // ANTI-PATTERN: TODO
    // FIX: TODO
    // ─────────────────────────────────────────────────────────────────────────

    // TODO: public function testNotificationQueueBug(): void {}
}