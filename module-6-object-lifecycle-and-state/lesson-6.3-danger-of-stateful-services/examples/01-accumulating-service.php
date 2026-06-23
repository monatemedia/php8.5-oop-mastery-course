<?php
declare(strict_types=1);

/**
 * Example 01 — The Accumulating Service
 * ----------------------------------------
 * Run via PHPUnit:
 *   ./vendor/bin/phpunit module-6-object-lifecycle-and-state/lesson-6.3-danger-of-stateful-services/examples/01-accumulating-service.php
 *
 * Anti-pattern #1: a service with a private array that is appended to by a
 * public method. Safe per-request (array freed with the object). Dangerous as
 * a singleton — the array grows without bound, and every consumer reads the
 * entire accumulated history.
 *
 * This file shows three progressively realistic versions of the pattern:
 *
 *   VERSION A — bare minimum: private array + addResult() + getResults()
 *   VERSION B — realistic: an invoice line collector used across a checkout flow
 *   VERSION C — insidious: the array is read by a method that derives a value
 *               from it, so the accumulation bug corrupts calculated output
 *               (not just raw data)
 *
 * Structure:
 *   PART A — Version A: minimal pattern, minimal tests
 *   PART B — Version B: InvoiceBuilder used across two checkout sessions
 *   PART C — Version C: PricingEngine whose total() is corrupted by accumulation
 *   PART D — The reset() trap: why it is not a real fix
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// PART A — Version A: the minimal pattern
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Collects results for the current reporting operation.
 *
 * ANTI-PATTERN: private array $results with public addResult().
 * As a singleton, $results accumulates across all operations ever run
 * on this worker. getResults() returns everything, not just this operation's.
 */
class ReportService
{
    // THE DANGER: this array is never automatically cleared.
    // Under share-nothing (FPM), it IS cleared — the object is freed.
    // Under persistent worker (Swoole / FrankenPHP), it grows forever.
    private array $results = [];

    public function addResult(array $row): void
    {
        $this->results[] = $row;
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public function getCount(): int
    {
        return count($this->results);
    }

    // NOTE: clear() exists but is NOT called automatically.
    // Forgetting to call it is how the bug manifests.
    public function clear(): void
    {
        $this->results = [];
    }
}

class AccumulatingServiceVersionATest extends TestCase
{
    /**
     * Single-operation use: correct.
     * This is how the developer tested it and why the bug was not caught.
     */
    public function testSingleOperationWorksCorrectly(): void
    {
        $service = new ReportService();

        $service->addResult(['user' => 'Alice', 'score' => 95]);
        $service->addResult(['user' => 'Bob',   'score' => 82]);

        $this->assertCount(2, $service->getResults());
        $this->assertSame(2, $service->getCount());
    }

    /**
     * BUG: Two operations on the same singleton instance.
     *
     * Operation 1 adds 2 results. Operation 2 adds 1 result.
     * Operation 2 then calls getResults() — it should see 1 result,
     * but it sees 3: 2 leaked from operation 1.
     */
    public function testAccumulationAcrossOperations(): void
    {
        // ONE instance — simulates persistent-worker singleton
        $service = new ReportService();

        // ── Operation 1 (e.g. a monthly sales report) ────────────────────────
        $service->addResult(['user' => 'Alice', 'score' => 95]);
        $service->addResult(['user' => 'Bob',   'score' => 82]);
        $this->assertSame(2, $service->getCount(), 'Operation 1: 2 results');

        // ── Operation 2 (e.g. a quarterly forecast report) ───────────────────
        // No clear() called — simulates the common omission
        $service->addResult(['user' => 'Charlie', 'score' => 71]);

        // BUG: 3 results — operation 1's 2 entries are still present
        $this->assertSame(3, $service->getCount(),
            'BUG: 3 results total — 2 leaked from operation 1'
        );

        $results = $service->getResults();
        $this->assertSame('Alice', $results[0]['user'],
            'BUG: Alice\'s result (from operation 1) is in operation 2\'s output'
        );
    }

    /**
     * Even getCount() is wrong for operation 2 — it reports the cumulative total.
     * Code that uses getCount() > 0 to check "did this operation produce results?"
     * will always see results even on operations that produced nothing.
     */
    public function testCountIsAlwaysInflatedAfterFirstOperation(): void
    {
        $service = new ReportService();

        // Operation 1: 5 results
        for ($i = 0; $i < 5; $i++) {
            $service->addResult(['index' => $i]);
        }

        // Operation 2: 0 results (e.g. a report that found nothing)
        // hasResults() would return true incorrectly
        $this->assertSame(5, $service->getCount(),
            'BUG: Operation 2 inherited operation 1\'s 5 results — count is wrong'
        );
        // The correct count for operation 2 is 0
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PART B — Version B: InvoiceBuilder — a realistic checkout scenario
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Collects line items during a checkout session, then builds the final invoice.
 *
 * ANTI-PATTERN: $lines accumulates. As a singleton across checkout sessions,
 * session 2's invoice contains session 1's line items.
 */
class InvoiceBuilder
{
    private array $lines = [];

    public function addLine(string $sku, int $qty, float $unitPrice): void
    {
        $this->lines[] = [
            'sku'       => $sku,
            'qty'       => $qty,
            'unitPrice' => $unitPrice,
            'lineTotal' => round($qty * $unitPrice, 2),
        ];
    }

    public function getLines(): array { return $this->lines; }

    public function getLineCount(): int { return count($this->lines); }

    public function buildInvoice(string $customerId): array
    {
        return [
            'customerId' => $customerId,
            'lines'      => $this->lines,
            'total'      => array_sum(array_column($this->lines, 'lineTotal')),
            'lineCount'  => count($this->lines),
        ];
    }
}

class InvoiceBuilderTest extends TestCase
{
    /**
     * Single session: invoice is correct.
     */
    public function testSingleSessionBuildsCorrectInvoice(): void
    {
        $builder = new InvoiceBuilder();

        $builder->addLine('LAPTOP-001', 1, 999.99);
        $builder->addLine('BAG-007',    1, 49.99);

        $invoice = $builder->buildInvoice('customer-alice');

        $this->assertSame('customer-alice', $invoice['customerId']);
        $this->assertSame(2, $invoice['lineCount']);
        $this->assertSame(1049.98, $invoice['total']);
    }

    /**
     * BUG: Session 2's invoice includes session 1's items.
     *
     * Customer Alice checks out with a laptop and a bag.
     * Customer Bob then checks out with a keyboard.
     * Bob's invoice incorrectly includes Alice's laptop and bag.
     * Bob is billed for Alice's items.
     */
    public function testSessionTwoInvoiceContainsSessionOneItems(): void
    {
        $builder = new InvoiceBuilder(); // singleton

        // ── Session 1: Alice ──────────────────────────────────────────────────
        $builder->addLine('LAPTOP-001', 1, 999.99);
        $builder->addLine('BAG-007',    1, 49.99);

        $aliceInvoice = $builder->buildInvoice('customer-alice');
        $this->assertSame(2, $aliceInvoice['lineCount']);
        $this->assertSame(1049.98, $aliceInvoice['total']);

        // ── Session 2: Bob ────────────────────────────────────────────────────
        $builder->addLine('KEYBOARD-003', 1, 79.99);

        $bobInvoice = $builder->buildInvoice('customer-bob');

        // BUG: Bob's invoice has 3 lines — Alice's 2 lines leaked in
        $this->assertSame(3, $bobInvoice['lineCount'],
            'BUG: Bob\'s invoice has 3 lines (Alice\'s 2 + Bob\'s 1)'
        );
        $this->assertSame(1129.97, $bobInvoice['total'],
            'BUG: Bob is billed $1129.97 — includes Alice\'s laptop and bag'
        );

        $skus = array_column($bobInvoice['lines'], 'sku');
        $this->assertContains('LAPTOP-001', $skus,
            'BUG: Alice\'s LAPTOP appears on Bob\'s invoice'
        );

        // Bob should have been billed only $79.99 for his keyboard
    }

    /**
     * With each session getting a fresh InvoiceBuilder (simulates transient scope),
     * the bug disappears — same code, different wiring.
     */
    public function testFreshBuilderPerSessionCorrectlyIsolatesSessions(): void
    {
        // Session 1: Alice — fresh builder
        $builderAlice = new InvoiceBuilder();
        $builderAlice->addLine('LAPTOP-001', 1, 999.99);
        $builderAlice->addLine('BAG-007',    1, 49.99);
        $aliceInvoice = $builderAlice->buildInvoice('customer-alice');

        // Session 2: Bob — FRESH builder
        $builderBob = new InvoiceBuilder();
        $builderBob->addLine('KEYBOARD-003', 1, 79.99);
        $bobInvoice = $builderBob->buildInvoice('customer-bob');

        // Alice's invoice is correct
        $this->assertSame(2,       $aliceInvoice['lineCount']);
        $this->assertSame(1049.98, $aliceInvoice['total']);

        // Bob's invoice is correct — only his items
        $this->assertSame(1,     $bobInvoice['lineCount']);
        $this->assertSame(79.99, $bobInvoice['total']);

        $bobSkus = array_column($bobInvoice['lines'], 'sku');
        $this->assertNotContains('LAPTOP-001', $bobSkus,
            'Alice\'s laptop is not on Bob\'s invoice'
        );
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PART C — Version C: PricingEngine — accumulation corrupts a calculation
//
// The most insidious variant: the accumulated state is not directly returned,
// but is used in a calculation. The bug manifests as wrong totals, not raw
// leaked objects — harder to spot and diagnose.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Applies discount rules to collected items and computes a discounted total.
 *
 * ANTI-PATTERN: $items accumulates. Discount thresholds are based on order
 * quantity — contaminated $items cause wrong discount tiers.
 */
class PricingEngine
{
    private array $items = [];

    public function addItem(float $price): void
    {
        $this->items[] = $price;
    }

    public function getItemCount(): int
    {
        return count($this->items);
    }

    /**
     * Applies tiered discounts based on item count:
     *   < 5 items:  no discount
     *   5–9 items:  5% off
     *   10+ items:  15% off
     */
    public function calculateDiscountedTotal(): float
    {
        $subtotal = array_sum($this->items);
        $count    = count($this->items);

        $rate = match(true) {
            $count >= 10 => 0.15,
            $count >= 5  => 0.05,
            default      => 0.00,
        };

        return round($subtotal * (1 - $rate), 2);
    }
}

class PricingEngineTest extends TestCase
{
    /**
     * Single order: correct discounting.
     * 3 items at $10 each = $30, no discount.
     */
    public function testSingleOrderAppliesNoDiscountForSmallOrder(): void
    {
        $engine = new PricingEngine();

        $engine->addItem(10.00);
        $engine->addItem(10.00);
        $engine->addItem(10.00);

        $this->assertSame(30.00, $engine->calculateDiscountedTotal(),
            '3 items: no discount → $30.00'
        );
    }

    /**
     * BUG: Order 2 receives an inflated discount because it is treated as
     * if it contains order 1's items as well.
     *
     * Order 1: 3 items at $10 = $30, no discount → customer pays $30.
     * Order 2: 3 items at $10 = $30, should have no discount → should pay $30.
     *
     * But the engine has 6 items total (3 leaked from order 1):
     * 6 items triggers 5% discount → customer pays $28.50 instead of $30.
     *
     * The business loses $1.50 on every order 2+, silently.
     */
    public function testAccumulationCorruptsDiscountCalculation(): void
    {
        $engine = new PricingEngine(); // singleton

        // ── Order 1: 3 items at $10 ──────────────────────────────────────────
        $engine->addItem(10.00);
        $engine->addItem(10.00);
        $engine->addItem(10.00);

        $order1Total = $engine->calculateDiscountedTotal();
        $this->assertSame(30.00, $order1Total, 'Order 1: correct — $30.00');

        // ── Order 2: 3 items at $10 ──────────────────────────────────────────
        $engine->addItem(10.00);
        $engine->addItem(10.00);
        $engine->addItem(10.00);

        // Engine now has 6 items (3 leaked + 3 new) — triggers 5% discount
        $order2Total = $engine->calculateDiscountedTotal();

        // BUG: $28.50 instead of $30.00 — wrong discount tier due to accumulation
        $this->assertSame(28.50, $order2Total,
            'BUG: Order 2 gets a 5% discount it should not have earned — '
            . '6 accumulated items crossed the 5-item threshold'
        );
        // Correct total for order 2 should be $30.00

        $this->assertSame(6, $engine->getItemCount(),
            'BUG: Engine has 6 items — 3 leaked from order 1'
        );
    }

    /**
     * Documents the correct behaviour under fresh instance per order.
     */
    public function testFreshEnginePerOrderGivesCorrectDiscounts(): void
    {
        // Order 1: fresh engine
        $engine1 = new PricingEngine();
        $engine1->addItem(10.00);
        $engine1->addItem(10.00);
        $engine1->addItem(10.00);
        $this->assertSame(30.00, $engine1->calculateDiscountedTotal(), 'Order 1: $30.00 (no discount)');

        // Order 2: FRESH engine
        $engine2 = new PricingEngine();
        $engine2->addItem(10.00);
        $engine2->addItem(10.00);
        $engine2->addItem(10.00);
        $this->assertSame(30.00, $engine2->calculateDiscountedTotal(), 'Order 2: $30.00 (no discount, fresh engine)');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PART D — The reset() trap
//
// Adding a reset() or clear() method is the reflex fix for accumulation bugs.
// This section shows why it is inadequate and what test to write to prove it.
// ─────────────────────────────────────────────────────────────────────────────

class ResetTrapTest extends TestCase
{
    /**
     * reset() works ONLY if it is always called at the right time.
     * There is no type system or framework enforcement that ensures this.
     *
     * This test proves that forgetting clear() once re-introduces the bug.
     */
    public function testForgettingClearReintroducesTheBug(): void
    {
        $service = new ReportService();

        // Operation 1: developer remembers to clear before starting
        $service->clear();
        $service->addResult(['user' => 'Alice']);
        $this->assertSame(1, $service->getCount(), 'Op 1: correct after clear()');

        // Operation 2: developer forgets clear() — perhaps a new code path,
        // an exception that skips the teardown, or just a slip
        $service->addResult(['user' => 'Bob']);

        // Bug re-emerges: 2 results instead of 1
        $this->assertSame(2, $service->getCount(),
            'BUG: forgot clear() — accumulated result from operation 1 is still present'
        );
    }

    /**
     * clear() in a try/finally pattern is more robust — but still manual.
     * Still fragile if clear() itself throws, or if the service is injected
     * into a collaborator that calls it without knowing about the reset protocol.
     */
    public function testClearInFinallyIsMoreRobustButStillManual(): void
    {
        $service = new ReportService();

        // Correct pattern: always clear in finally
        try {
            $service->addResult(['user' => 'Alice']);
            $this->assertSame(1, $service->getCount());
        } finally {
            $service->clear();
        }

        // Next operation starts clean
        $service->addResult(['user' => 'Bob']);
        $this->assertSame(1, $service->getCount(),
            'After finally-clear, next operation starts clean'
        );

        // But: any collaborator that has a reference to $service and calls
        // addResult() without knowing about the clear protocol will still corrupt it.
        // The real fix: transient scope or stateless design (Lesson 6.4).
    }
}