<?php
declare(strict_types=1);

/**
 * CHALLENGE STARTER — Lesson 6.1: PHP's Share-Nothing Architecture
 * ─────────────────────────────────────────────────────────────────
 * Read CHALLENGE.md before touching this file.
 *
 * The five service classes are defined below. DO NOT modify them.
 * Your job: write tests that prove each one is lifecycle-unsafe when used
 * as a persistent singleton, then add a fix proposal comment.
 *
 * Pattern for each test:
 *   1. Create ONE service instance (simulates singleton in persistent worker)
 *   2. Call it as "request 1" / "operation 1"
 *   3. Call it again as "request 2" / "operation 2" WITHOUT recreating it
 *   4. Assert that request 2 sees contamination from request 1
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// Pre-defined service classes — DO NOT modify these
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Service 1: Shopping basket that accumulates items.
 */
class BasketService
{
    private array $items = [];

    public function addItem(string $sku, int $quantity): void
    {
        $this->items[] = ['sku' => $sku, 'quantity' => $quantity];
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function getItemCount(): int
    {
        return count($this->items);
    }

    public function clear(): void
    {
        $this->items = [];
    }
}

/**
 * Service 2: Audit logger that records events per operation.
 */
class AuditLogger
{
    private array $entries  = [];
    private string $context = '';

    public function setContext(string $context): void
    {
        $this->context = $context;
    }

    public function log(string $event): void
    {
        $this->entries[] = "[{$this->context}] {$event}";
    }

    public function getEntries(): array
    {
        return $this->entries;
    }

    public function getSummary(): string
    {
        $count = count($this->entries);
        return "Audit complete: {$count} event(s) recorded";
    }
}

/**
 * Service 3: Stores the authenticated user for the current request.
 */
class UserSessionService
{
    private ?string $currentUser = null;

    public function login(string $username): void
    {
        $this->currentUser = $username;
    }

    public function logout(): void
    {
        $this->currentUser = null;
    }

    public function getCurrentUser(): ?string
    {
        return $this->currentUser;
    }

    public function isAuthenticated(): bool
    {
        return $this->currentUser !== null;
    }
}

/**
 * Service 4: Rate limiter — counts hits per key within the current "window".
 * Intended as a per-request counter with a max of 3 hits per key.
 */
class RateLimiter
{
    private const LIMIT = 3;
    private array $hits = [];

    public function hit(string $key): void
    {
        $this->hits[$key] = ($this->hits[$key] ?? 0) + 1;
    }

    public function isAllowed(string $key): bool
    {
        return ($this->hits[$key] ?? 0) < self::LIMIT;
    }

    public function getCount(string $key): int
    {
        return $this->hits[$key] ?? 0;
    }
}

/**
 * Service 5: Collects report rows and renders them.
 */
class ReportBuilder
{
    private array  $rows  = [];
    private string $title = '';

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function addRow(array $row): void
    {
        $this->rows[] = $row;
    }

    public function getRowCount(): int
    {
        return count($this->rows);
    }

    public function render(): string
    {
        $lines = ["=== {$this->title} ==="];
        foreach ($this->rows as $row) {
            $lines[] = implode(', ', $row);
        }
        return implode("\n", $lines);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Your tests go here
// ─────────────────────────────────────────────────────────────────────────────

class ShareNothingAuditTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────────────────
    // Service 1 — BasketService
    // ─────────────────────────────────────────────────────────────────────────

    // TODO: public function testBasketAccumulatesItemsAcrossRequests(): void {}

    // TODO: public function testBasketCountIsAlwaysOneForFreshRequests(): void {}

    /*
     * FIX PROPOSAL for BasketService:
     * TODO: your one-sentence fix proposal here
     */

    // ─────────────────────────────────────────────────────────────────────────
    // Service 2 — AuditLogger
    // ─────────────────────────────────────────────────────────────────────────

    // TODO: public function testAuditLoggerAccumulatesEntriesAcrossOperations(): void {}

    // TODO: public function testAuditLoggerSummaryIsCorruptedByPreviousEntries(): void {}

    /*
     * FIX PROPOSAL for AuditLogger:
     * TODO: your one-sentence fix proposal here
     */

    // ─────────────────────────────────────────────────────────────────────────
    // Service 3 — UserSessionService
    // ─────────────────────────────────────────────────────────────────────────

    // TODO: public function testUserSessionLeaksAcrossRequests(): void {}

    // TODO: public function testUnauthenticatedRequestShouldReturnNullUser(): void {}

    /*
     * FIX PROPOSAL for UserSessionService:
     * TODO: your one-sentence fix proposal here
     */

    // ─────────────────────────────────────────────────────────────────────────
    // Service 4 — RateLimiter
    // ─────────────────────────────────────────────────────────────────────────

    // TODO: public function testRateLimiterCountsAccumulateAcrossRequests(): void {}

    /*
     * FIX PROPOSAL for RateLimiter:
     * TODO: your one-sentence fix proposal here
     */

    // ─────────────────────────────────────────────────────────────────────────
    // Service 5 — ReportBuilder
    // ─────────────────────────────────────────────────────────────────────────

    // TODO: public function testReportBuilderIncludesRowsFromPreviousRequests(): void {}

    // TODO: public function testReportBuilderRowCountIsWrongForSecondRequest(): void {}

    /*
     * FIX PROPOSAL for ReportBuilder:
     * TODO: your one-sentence fix proposal here
     */
}