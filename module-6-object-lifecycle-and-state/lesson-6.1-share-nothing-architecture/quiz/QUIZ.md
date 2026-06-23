<?php
declare(strict_types=1);

/**
 * CHALLENGE SOLUTION — Lesson 6.1: PHP's Share-Nothing Architecture
 * ──────────────────────────────────────────────────────────────────
 * ⚠️  Only open this file after completing all nine tests yourself.
 *
 * Each test follows the same three-act pattern:
 *   Act 1 — Simulate "request 1" / "operation 1" using the singleton
 *   Act 2 — Simulate "request 2" / "operation 2" using the SAME instance
 *   Act 3 — Assert that request 2 sees contamination from request 1
 *
 * Key things to compare with your solution:
 *   1. Did you use ONE service instance for both "requests"? (that is the point)
 *   2. Are your fix proposals pointing at the right layer? (scope vs design)
 *   3. Are the assertions explicit enough that the bug is unmistakable?
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// Pre-defined service classes (copied from starter — identical, not modified)
// ─────────────────────────────────────────────────────────────────────────────

class BasketService
{
    private array $items = [];

    public function addItem(string $sku, int $quantity): void
    {
        $this->items[] = ['sku' => $sku, 'quantity' => $quantity];
    }

    public function getItems(): array   { return $this->items; }
    public function getItemCount(): int { return count($this->items); }
    public function clear(): void       { $this->items = []; }
}

class AuditLogger
{
    private array $entries  = [];
    private string $context = '';

    public function setContext(string $context): void { $this->context = $context; }

    public function log(string $event): void
    {
        $this->entries[] = "[{$this->context}] {$event}";
    }

    public function getEntries(): array  { return $this->entries; }

    public function getSummary(): string
    {
        $count = count($this->entries);
        return "Audit complete: {$count} event(s) recorded";
    }
}

class UserSessionService
{
    private ?string $currentUser = null;

    public function login(string $username): void  { $this->currentUser = $username; }
    public function logout(): void                 { $this->currentUser = null; }
    public function getCurrentUser(): ?string      { return $this->currentUser; }
    public function isAuthenticated(): bool        { return $this->currentUser !== null; }
}

class RateLimiter
{
    private const LIMIT = 3;
    private array $hits = [];

    public function hit(string $key): void
    {
        $this->hits[$key] = ($this->hits[$key] ?? 0) + 1;
    }

    public function isAllowed(string $key): bool { return ($this->hits[$key] ?? 0) < self::LIMIT; }
    public function getCount(string $key): int   { return $this->hits[$key] ?? 0; }
}

class ReportBuilder
{
    private array  $rows  = [];
    private string $title = '';

    public function setTitle(string $title): void { $this->title = $title; }

    public function addRow(array $row): void { $this->rows[] = $row; }

    public function getRowCount(): int { return count($this->rows); }

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
// Solution tests
// ─────────────────────────────────────────────────────────────────────────────

class ShareNothingAuditTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────────────────
    // Service 1 — BasketService
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * BUG DEMONSTRATION: items added in "request 1" are still present in "request 2".
     *
     * In a real e-commerce site under FrankenPHP worker mode, this means:
     * Customer A's cart items leak into Customer B's session — potentially
     * allowing Customer B to check out with items they never added.
     *
     * Design note: ONE instance is created before both "requests".
     * This mirrors what a PHP-DI singleton would do in a persistent worker.
     */
    public function testBasketAccumulatesItemsAcrossRequests(): void
    {
        // ONE instance — the persistent singleton
        $basket = new BasketService();

        // ── Request 1 (Customer A's session) ─────────────────────────────────
        $basket->addItem('WIDGET-001', 2);
        $basket->addItem('GADGET-007', 1);
        $this->assertSame(2, $basket->getItemCount(), 'Request 1: 2 items added');

        // Worker serves the next request — the instance is NOT recreated

        // ── Request 2 (Customer B's session) ─────────────────────────────────
        $basket->addItem('THINGAMAJIG-42', 1); // Customer B adds ONE item

        // BUG: Customer B's basket shows 3 items — two from Customer A's session
        $this->assertSame(3, $basket->getItemCount(),
            'BUG: Request 2 basket has 3 items — 2 leaked from request 1'
        );

        // Customer B's items list contains Customer A's items
        $skus = array_column($basket->getItems(), 'sku');
        $this->assertContains('WIDGET-001', $skus,
            'BUG: Customer A\'s WIDGET-001 appears in Customer B\'s basket'
        );
    }

    /**
     * CORRECT BEHAVIOUR DOCUMENTED: a fresh instance always starts with 0 items.
     *
     * Under share-nothing (FPM), each request gets a new BasketService instance.
     * Each customer's basket starts empty. This test documents that invariant.
     */
    public function testBasketCountIsAlwaysOneForFreshRequests(): void
    {
        // Under share-nothing, a new instance is created per request
        $basket1 = new BasketService(); // request 1
        $basket1->addItem('WIDGET-001', 2);
        $basket1->addItem('GADGET-007', 1);

        $basket2 = new BasketService(); // request 2 — FRESH instance
        $basket2->addItem('THINGAMAJIG-42', 1);

        // Each basket is isolated — request 2 has exactly 1 item
        $this->assertSame(1, $basket2->getItemCount(),
            'Fresh instance: request 2 basket has only its own 1 item'
        );
    }

    /*
     * FIX PROPOSAL for BasketService:
     * Use transient scope in the DI container so the basket is recreated for each
     * request, or redesign it as a stateless service that accepts and returns
     * item arrays as method parameters without storing them on the object.
     */

    // ─────────────────────────────────────────────────────────────────────────
    // Service 2 — AuditLogger
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * BUG DEMONSTRATION: audit entries from operation 1 appear in operation 2's log.
     *
     * In a compliance context, this is serious: the audit trail for a "transfer funds"
     * operation (operation 2) contains entries from a completely unrelated
     * "view account" operation (operation 1). Auditors see a false record.
     */
    public function testAuditLoggerAccumulatesEntriesAcrossOperations(): void
    {
        $logger = new AuditLogger(); // persistent singleton

        // ── Operation 1: view account ─────────────────────────────────────────
        $logger->setContext('view-account');
        $logger->log('Account viewed by user alice');
        $logger->log('Balance retrieved');
        $this->assertCount(2, $logger->getEntries(), 'Operation 1: 2 entries');

        // Worker picks up operation 2 — setContext() is called but entries are not cleared

        // ── Operation 2: transfer funds ───────────────────────────────────────
        $logger->setContext('transfer-funds');
        $logger->log('Transfer of $500 initiated');

        // BUG: 3 entries — operation 1's entries are still present
        $entries = $logger->getEntries();
        $this->assertCount(3, $entries,
            'BUG: 3 entries total — 2 leaked from operation 1'
        );

        // The context has changed to 'transfer-funds' but old entries still have old context
        $this->assertStringContainsString('[view-account]', $entries[0],
            'BUG: Old context "view-account" still present in operation 2\'s log'
        );
    }

    /**
     * BUG DEMONSTRATION: the summary count is wrong for operation 2.
     *
     * getSummary() reports the TOTAL accumulated count, not the count for the
     * current operation. A compliance report generated after operation 2 would
     * show "3 event(s)" instead of "1 event(s)".
     */
    public function testAuditLoggerSummaryIsCorruptedByPreviousEntries(): void
    {
        $logger = new AuditLogger();

        // Operation 1: 2 entries
        $logger->setContext('login');
        $logger->log('Login attempt');
        $logger->log('MFA challenge passed');

        // Operation 2: 1 entry — summary SHOULD say "1 event(s)"
        $logger->setContext('api-call');
        $logger->log('GET /api/orders');

        // BUG: summary says "3 event(s)" — includes operation 1's entries
        $this->assertSame('Audit complete: 3 event(s) recorded', $logger->getSummary(),
            'BUG: Summary reports 3 events instead of 1 — inflated by previous operations'
        );
        // Correct expectation would be: 'Audit complete: 1 event(s) recorded'
    }

    /*
     * FIX PROPOSAL for AuditLogger:
     * Make the logger stateless by accepting a log collector (array or value object)
     * as a parameter to each log() call and returning it, so callers own the
     * accumulation and each operation starts with a fresh collector.
     */

    // ─────────────────────────────────────────────────────────────────────────
    // Service 3 — UserSessionService
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * BUG DEMONSTRATION: Alice is still "logged in" during request 2 even though
     * request 2 has no authenticated user.
     *
     * In production: Alice makes a request, logs in, gets her data.
     * The next request to the same worker (from a completely different user,
     * or an unauthenticated health-check) finds Alice as the current user and
     * potentially returns her data to the wrong caller.
     *
     * This is the most dangerous bug in this set — it is a direct data breach vector.
     */
    public function testUserSessionLeaksAcrossRequests(): void
    {
        $session = new UserSessionService(); // persistent singleton

        // ── Request 1: Alice logs in ──────────────────────────────────────────
        $session->login('alice');
        $this->assertSame('alice', $session->getCurrentUser(), 'Request 1: Alice is logged in');
        $this->assertTrue($session->isAuthenticated());

        // Worker serves request 2 — session is NOT reset
        // (logout() was not called — Alice's browser closed, session expired, etc.)

        // ── Request 2: unauthenticated health-check or Bob's anonymous request ──
        // No login() call. The service SHOULD return null.

        // BUG: Alice is still the "current user"
        $this->assertSame('alice', $session->getCurrentUser(),
            'BUG: Alice is still the current user in request 2 — session leaked'
        );
        $this->assertTrue($session->isAuthenticated(),
            'BUG: isAuthenticated() returns true for an unauthenticated request'
        );
    }

    /**
     * CORRECT BEHAVIOUR DOCUMENTED: an unauthenticated request has no current user.
     *
     * Under share-nothing, a fresh UserSessionService always starts with
     * currentUser = null. This is the invariant the bug violates.
     */
    public function testUnauthenticatedRequestShouldReturnNullUser(): void
    {
        // Fresh instance — simulates share-nothing
        $session = new UserSessionService();

        // No login() call — this is an unauthenticated request
        $this->assertNull($session->getCurrentUser(),
            'A fresh UserSessionService must have no current user'
        );
        $this->assertFalse($session->isAuthenticated(),
            'A fresh UserSessionService must report unauthenticated'
        );
    }

    /*
     * FIX PROPOSAL for UserSessionService:
     * Replace the singleton UserSessionService with a per-request RequestContext
     * value object (readonly, immutable) created fresh from the request headers/cookies
     * at the start of each request and injected via a transient-scoped factory.
     */

    // ─────────────────────────────────────────────────────────────────────────
    // Service 4 — RateLimiter
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * BUG DEMONSTRATION: hits from request 1 push the count over the limit
     * for request 2, even though request 2 made only one hit.
     *
     * In production: the first request that hits a downstream API 2 times leaves
     * count[key] = 2. The next request makes 1 hit — count becomes 3 — and is
     * immediately rate-limited, even though it only made a single API call.
     *
     * The rate limiter was designed to enforce "max 3 API calls per request".
     * As a persistent singleton, it enforces "max 3 API calls ever" — a
     * progressively tightening ceiling that eventually blocks everyone.
     */
    public function testRateLimiterCountsAccumulateAcrossRequests(): void
    {
        $limiter = new RateLimiter(); // persistent singleton, LIMIT = 3

        // ── Request 1: makes 2 hits (fine — under the limit) ─────────────────
        $limiter->hit('downstream-api');
        $limiter->hit('downstream-api');
        $this->assertTrue($limiter->isAllowed('downstream-api'), 'Request 1: 2 hits, still allowed');
        $this->assertSame(2, $limiter->getCount('downstream-api'));

        // Worker handles request 2 — RateLimiter is NOT recreated

        // ── Request 2: makes only 1 hit — should be allowed ──────────────────
        $limiter->hit('downstream-api'); // count → 3

        // BUG: request 2 is rate-limited after a single hit
        $this->assertFalse($limiter->isAllowed('downstream-api'),
            'BUG: Request 2 is rate-limited after only 1 hit of its own — count carried over from request 1'
        );
        $this->assertSame(3, $limiter->getCount('downstream-api'),
            'Count is 3 — 2 from request 1 + 1 from request 2'
        );
    }

    /*
     * FIX PROPOSAL for RateLimiter:
     * If the intent is per-request rate limiting, use transient scope in the DI
     * container so a fresh RateLimiter is created for each request; if the intent
     * is global rate limiting across all requests, move the counter to an external
     * store (Redis/Memcached) with a TTL-based sliding window.
     */

    // ─────────────────────────────────────────────────────────────────────────
    // Service 5 — ReportBuilder
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * BUG DEMONSTRATION: rows added during report 1 appear when report 2 is rendered.
     *
     * In production: a monthly sales report (report 1) and a quarterly forecast
     * report (report 2) are both generated by the same worker. Report 2's output
     * includes sales data rows from report 1 — a completely wrong document is
     * delivered to the finance team.
     */
    public function testReportBuilderIncludesRowsFromPreviousRequests(): void
    {
        $builder = new ReportBuilder(); // persistent singleton

        // ── Report 1: monthly sales ───────────────────────────────────────────
        $builder->setTitle('Monthly Sales');
        $builder->addRow(['product' => 'Widget', 'qty' => 100, 'revenue' => '5000']);
        $builder->addRow(['product' => 'Gadget', 'qty' => 50,  'revenue' => '7500']);

        $report1 = $builder->render();
        $this->assertStringContainsString('Monthly Sales', $report1);

        // Worker handles the next report request — builder is NOT reset

        // ── Report 2: quarterly forecast ──────────────────────────────────────
        $builder->setTitle('Q4 Forecast');
        $builder->addRow(['product' => 'New Product', 'qty' => 200, 'revenue' => '20000']);

        $report2 = $builder->render();

        // BUG: report 2 contains rows from report 1
        $this->assertStringContainsString('Widget', $report2,
            'BUG: Report 2 (Q4 Forecast) contains "Widget" from the Monthly Sales report'
        );
        $this->assertStringContainsString('Gadget', $report2,
            'BUG: Report 2 (Q4 Forecast) contains "Gadget" from the Monthly Sales report'
        );
    }

    /**
     * BUG DEMONSTRATION: the row count is wrong for report 2.
     *
     * Report 2 adds 1 row. The count should be 1.
     * The actual count is 3 — 2 rows leaked from report 1.
     * An explicit count assertion makes the bug unmistakable.
     */
    public function testReportBuilderRowCountIsWrongForSecondRequest(): void
    {
        $builder = new ReportBuilder();

        // Report 1: 2 rows
        $builder->setTitle('Report One');
        $builder->addRow(['col' => 'row1-data']);
        $builder->addRow(['col' => 'row2-data']);
        $this->assertSame(2, $builder->getRowCount(), 'Report 1: 2 rows');

        // Report 2: 1 row — SHOULD have count of 1
        $builder->setTitle('Report Two');
        $builder->addRow(['col' => 'report2-only-row']);

        // BUG: 3 rows — 2 leaked from report 1
        $this->assertSame(3, $builder->getRowCount(),
            'BUG: Row count is 3 — expected 1 for report 2, got 2 leaked from report 1'
        );
        // Correct expectation: $this->assertSame(1, $builder->getRowCount());
    }

    /*
     * FIX PROPOSAL for ReportBuilder:
     * Redesign as a stateless builder: accept the rows array as a parameter to
     * render(array $rows, string $title): string so the caller owns the data and
     * the builder holds no state between calls.
     */
}