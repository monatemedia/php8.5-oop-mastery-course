<?php
declare(strict_types=1);

/**
 * CHALLENGE STARTER — Lesson 6.4: Designing Stateless Services
 * ─────────────────────────────────────────────────────────────
 * Read CHALLENGE.md before touching this file.
 *
 * The five stateful services from Lesson 6.3 are reproduced below.
 * Refactor each one to eliminate the instance state that causes the bug.
 * Then uncomment and complete the two test methods for each service.
 *
 * Refactoring rule (README Section 1):
 *   Move state OUT of the object and INTO method parameters and return values.
 *   The caller owns and manages the state; the service transforms it.
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// Service 1 — SearchIndexBuilder
// TODO: Remove $documentIds. Make addDocument() accept and return the doc list.
// ─────────────────────────────────────────────────────────────────────────────

class SearchIndexBuilder
{
    // TODO: remove this property
    private array $documentIds = [];
    private int   $errorCount  = 0;

    // TODO: change signature to accept array, return array
    public function addDocument(string $id): void
    {
        $this->documentIds[] = $id;
    }

    public function recordError(): void
    {
        $this->errorCount++;
    }

    // TODO: change signature to accept array, return count
    public function getIndexedCount(): int
    {
        return count($this->documentIds);
    }

    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    // TODO: change signature to accept array, return array
    public function getDocumentIds(): array
    {
        return $this->documentIds;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Service 2 — CurrentOperationContext
// TODO: Replace mutable properties + setters with readonly properties.
//       Make all fields constructor parameters. Remove beginOperation/endOperation.
// ─────────────────────────────────────────────────────────────────────────────

class CurrentOperationContext
{
    // TODO: convert to readonly constructor properties
    private ?string $operationName = null;
    private ?int    $startedAt     = null;

    // TODO: remove this method — move data to constructor
    public function beginOperation(string $name): void
    {
        $this->operationName = $name;
        $this->startedAt     = time();
    }

    // TODO: remove this method
    public function endOperation(): void
    {
        $this->operationName = null;
        $this->startedAt     = null;
    }

    public function getOperationName(): ?string { return $this->operationName; }
    public function getStartedAt(): ?int        { return $this->startedAt; }
    public function isActive(): bool            { return $this->operationName !== null; }
}

// ─────────────────────────────────────────────────────────────────────────────
// Service 3 — BandwidthMonitor
// TODO: Remove $totalBytes. Make recordBytes() accept current total, return new total.
//       Make isOverLimit() and getRemainingBytes() accept total as parameter.
// ─────────────────────────────────────────────────────────────────────────────

class BandwidthMonitor
{
    private const LIMIT_BYTES = 1_048_576; // 1 MB

    // TODO: remove this property
    private int $totalBytes = 0;

    // TODO: change to: recordBytes(int $currentTotal, int $bytes): int
    public function recordBytes(int $bytes): void
    {
        $this->totalBytes += $bytes;
    }

    // TODO: remove (caller can compare directly)
    public function getTotalBytes(): int { return $this->totalBytes; }

    // TODO: change to: isOverLimit(int $total): bool
    public function isOverLimit(): bool  { return $this->totalBytes >= self::LIMIT_BYTES; }

    // TODO: change to: getRemainingBytes(int $total): int
    public function getRemainingBytes(): int
    {
        return max(0, self::LIMIT_BYTES - $this->totalBytes);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Service 4 — FeatureFlagService
// TODO: Remove $booted flag and boot() method. Load flags in the constructor.
// ─────────────────────────────────────────────────────────────────────────────

class FeatureFlagService
{
    // TODO: remove this property
    private bool  $booted  = false;
    private array $flags   = [];

    public function __construct(private readonly array $configSource) {}

    // TODO: remove this method — do the work in the constructor
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

    // TODO: remove (no longer needed)
    public function isBooted(): bool { return $this->booted; }
}

// ─────────────────────────────────────────────────────────────────────────────
// Service 5 — NotificationQueue
// TODO: Remove $pending. Make enqueue() accept array + notification, return array.
//       Make flush() accept array, return it (caller resets their variable).
// ─────────────────────────────────────────────────────────────────────────────

class NotificationQueue
{
    // TODO: remove this property
    private array $pending = [];

    // TODO: change to: enqueue(array $pending, string $type, array $payload): array
    public function enqueue(string $type, array $payload): void
    {
        $this->pending[] = ['type' => $type, 'payload' => $payload];
    }

    // TODO: change to: flush(array $pending): array  (just returns the array — caller resets)
    public function flush(): array
    {
        $notifications  = $this->pending;
        $this->pending  = [];
        return $notifications;
    }

    // TODO: remove (caller can use count() on their own array)
    public function getPendingCount(): int
    {
        return count($this->pending);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Your tests
// ─────────────────────────────────────────────────────────────────────────────

class StatelessRefactorTest extends TestCase
{
    // ── Service 1 ────────────────────────────────────────────────────────────

    // TODO: public function testSearchIndexBuilderBugIsGone(): void {}
    // TODO: public function testSearchIndexBuilderProducesCorrectOutput(): void {}

    // ── Service 2 ────────────────────────────────────────────────────────────

    // TODO: public function testCurrentOperationContextBugIsGone(): void {}
    // TODO: public function testCurrentOperationContextCarriesCorrectData(): void {}

    // ── Service 3 ────────────────────────────────────────────────────────────

    // TODO: public function testBandwidthMonitorBugIsGone(): void {}
    // TODO: public function testBandwidthMonitorEnforcesLimitCorrectly(): void {}

    // ── Service 4 ────────────────────────────────────────────────────────────

    // TODO: public function testFeatureFlagServiceBugIsGone(): void {}
    // TODO: public function testFeatureFlagServiceReturnsCorrectFlags(): void {}

    // ── Service 5 ────────────────────────────────────────────────────────────

    // TODO: public function testNotificationQueueBugIsGone(): void {}
    // TODO: public function testNotificationQueueEnqueueAndFlushWorkCorrectly(): void {}
}