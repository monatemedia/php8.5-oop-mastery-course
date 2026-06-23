<?php
declare(strict_types=1);

/**
 * Example 02 — Long-Running Worker: State Accumulation
 * ------------------------------------------------------
 * Run via PHPUnit:
 *   ./vendor/bin/phpunit module-6-object-lifecycle-and-state/lesson-6.1-share-nothing-architecture/examples/02-long-running-worker.php
 *
 * This file simulates a queue worker that runs indefinitely, processing
 * jobs one at a time. It demonstrates three types of state accumulation
 * that commonly appear in real codebases:
 *
 *   1. ERROR ACCUMULATION — an error collector that grows forever
 *   2. CONTEXT LEAKAGE   — job-specific data (current user, tenant, ID)
 *                          left on a service from the previous job
 *   3. CACHE POISONING   — a cached value from job N is used by job N+1
 *                          even though it should have been recalculated
 *
 * In each case you will see:
 *   - The buggy service (stateful singleton)
 *   - A simulated worker loop running multiple jobs through it
 *   - A test that catches the bug by simulating the worker reuse
 *
 * Structure:
 *   PART A — Error accumulation
 *   PART B — Context leakage
 *   PART C — Cache poisoning
 *   PART D — How each would behave under share-nothing (for comparison)
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// PART A — Error Accumulation
//
// An ImportService that collects errors during processing. Fine for a single
// request or job. Dangerous as a singleton across many jobs — errors from
// job 1 are still present when job 2 runs.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Processes import rows and collects any validation errors.
 *
 * Designed for single-request use. Dangerous as a long-lived singleton.
 */
class ImportService
{
    private array $errors = []; // ← mutable state: grows without bound

    public function processRow(array $row): bool
    {
        if (empty($row['email'])) {
            $this->errors[] = "Row missing email: " . json_encode($row);
            return false;
        }

        if (!filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $this->errors[] = "Invalid email: {$row['email']}";
            return false;
        }

        return true;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    /**
     * NOTE: In a correct design you would call reset() between jobs, but:
     *   1. Easy to forget
     *   2. Requires callers to know about internal state
     *   3. Still wrong — test doubles cannot enforce this
     * The RIGHT fix is to make the service stateless (Lesson 6.4).
     */
    public function reset(): void
    {
        $this->errors = [];
    }
}

class ErrorAccumulationTest extends TestCase
{
    /**
     * Single-job use: works correctly.
     * Job 1 processes rows, some fail, errors are collected. Clean.
     */
    public function testImportServiceCollectsErrorsForOneJob(): void
    {
        $service = new ImportService(); // fresh instance — safe here

        $service->processRow(['email' => 'alice@example.com']); // ok
        $service->processRow(['email' => 'not-an-email']);       // error
        $service->processRow(['email' => '']);                   // error

        $this->assertCount(2, $service->getErrors());
        $this->assertTrue($service->hasErrors());
    }

    /**
     * Simulated persistent worker — two jobs, same ImportService instance.
     *
     * BUG: Job 2's error report contains errors from job 1.
     * An operations team investigating "why did job 2 report an error?"
     * will see errors that actually belong to job 1.
     */
    public function testImportServiceAccumulatesErrorsAcrossJobs(): void
    {
        // In a persistent worker, this is created at bootstrap — NOT per job
        $service = new ImportService();

        // ── Job 1 (e.g. importing the "marketing" CSV) ────────────────────────
        $service->processRow(['email' => 'alice@example.com']); // ok
        $service->processRow(['email' => 'bad-email']);          // error: 1 error total

        $this->assertCount(1, $service->getErrors(), 'Job 1: 1 error');

        // Worker pops the next job — does NOT reset the service (easy to forget)

        // ── Job 2 (e.g. importing the "sales" CSV) ────────────────────────────
        $service->processRow(['email' => 'bob@example.com']);  // ok
        $service->processRow(['email' => 'also-bad']);          // error: should be 1 error

        // BUG: 2 errors — the error from job 1 is still present
        $this->assertCount(2, $service->getErrors(),
            'Job 2 sees 2 errors — one leaked from job 1. This is the bug.'
        );
    }

    /**
     * Even with reset() — calling it is fragile and easy to forget.
     * This test shows that reset() "fixes" the symptom but not the design.
     *
     * A caller should not need to know about internal state to use a service.
     */
    public function testResetWorksButIsFragile(): void
    {
        $service = new ImportService();

        // Job 1
        $service->processRow(['email' => 'bad-email']);
        $this->assertCount(1, $service->getErrors());

        // Developer remembers to reset — works this time
        $service->reset();

        // Job 2
        $service->processRow(['email' => 'also-bad']);
        $this->assertCount(1, $service->getErrors(), 'Reset worked — but only because we remembered');

        // The problem: there is nothing enforcing the reset() call.
        // Forget it once and the bug is back. Lesson 6.4 shows the real fix.
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PART B — Context Leakage
//
// A service that holds "current job context" — the tenant ID, user ID,
// or job ID being processed. When the service is a singleton, the context
// from job N is still set when job N+1 starts.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Sends notifications for the "current job" (e.g. a billing run for a tenant).
 *
 * The tenant ID is set once at job start and used by all notification calls.
 * As a singleton, the tenant ID bleeds from job to job.
 */
class NotificationService
{
    private ?string $currentTenantId = null; // ← context: set per-job, never auto-reset

    public function setTenant(string $tenantId): void
    {
        $this->currentTenantId = $tenantId;
    }

    public function getCurrentTenant(): ?string
    {
        return $this->currentTenantId;
    }

    /**
     * Sends a notification to the current tenant's admin.
     *
     * @throws \LogicException if no tenant is set — but only if someone checks
     */
    public function notifyAdmin(string $message): string
    {
        // BUG: if setTenant() was not called for this job, we use last job's tenant
        $tenant = $this->currentTenantId ?? 'unknown';
        return "Notification to {$tenant}: {$message}";
    }
}

class ContextLeakageTest extends TestCase
{
    /**
     * Simulates two billing jobs running through the same NotificationService.
     *
     * Job 1: tenant = "acme" — sets the tenant, sends a notification. OK.
     * Job 2: tenant = "globex" — but FORGOT to call setTenant().
     *
     * BUG: Job 2's notification is sent to "acme" instead of "globex".
     * In production, this means Acme Corp receives Globex's billing notification.
     * A data privacy incident.
     */
    public function testContextLeaksFromJobToJob(): void
    {
        $service = new NotificationService(); // singleton — same instance across jobs

        // Job 1: billing run for Acme Corp
        $service->setTenant('acme');
        $notification1 = $service->notifyAdmin('Your invoice is ready');
        $this->assertStringContainsString('acme', $notification1);

        // Worker picks up the next job
        // The developer assumes setTenant() was called — but it is not enforced

        // Job 2: billing run for Globex — developer forgot setTenant()
        // In real code, this might be in a different code path or added by a
        // colleague who assumed the service was fresh
        $notification2 = $service->notifyAdmin('Your invoice is ready');

        // BUG: "acme" is still set — notification goes to the wrong tenant
        $this->assertStringContainsString('acme', $notification2,
            'BUG CONFIRMED: Job 2 notified Acme Corp instead of Globex'
        );
        $this->assertStringNotContainsString('globex', $notification2);
    }

    /**
     * The fix: setTenant() IS called for job 2.
     * Works — but relies on every caller remembering to call it.
     * Still fragile. Lesson 6.4 shows the stateless alternative.
     */
    public function testSettingTenantExplicitlyFixesTheBug(): void
    {
        $service = new NotificationService();

        // Job 1
        $service->setTenant('acme');
        $service->notifyAdmin('Invoice ready');

        // Job 2 — explicitly sets the tenant (correct, but fragile)
        $service->setTenant('globex');
        $notification = $service->notifyAdmin('Invoice ready');

        $this->assertStringContainsString('globex', $notification);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PART C — Cache Poisoning
//
// A service that caches an expensive computation on itself.
// Safe per-request: the cache lives for one request and is then freed.
// Dangerous as a singleton: the cached value from job 1 is returned
// to job 2 even though job 2 should compute its own value.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Calculates a discount rate for an order.
 * Caches the result to avoid recalculating on repeated calls within one request.
 *
 * As a per-request object: perfectly fine — the cache is freed with the request.
 * As a singleton across jobs: the first job's discount is returned to all jobs.
 */
class DiscountCalculator
{
    private ?float $cachedRate = null; // ← cached result — never cleared between jobs

    /**
     * Calculates (and caches) the discount rate for the given order total.
     * In real code this might involve DB lookups or external API calls.
     */
    public function calculateRate(float $orderTotal): float
    {
        if ($this->cachedRate !== null) {
            // Returns cached result — correct within a single job,
            // wrong across jobs if orders have different totals
            return $this->cachedRate;
        }

        // "Expensive calculation" — simplified here
        $this->cachedRate = $orderTotal >= 1000.0 ? 0.15 : 0.05;
        return $this->cachedRate;
    }
}

class CachePoisoningTest extends TestCase
{
    /**
     * Within one job, caching is correct — repeated calls return the same rate.
     */
    public function testCachingIsCorrectWithinOneJob(): void
    {
        $calculator = new DiscountCalculator();

        $rate1 = $calculator->calculateRate(500.0);  // 0.05 (small order)
        $rate2 = $calculator->calculateRate(500.0);  // 0.05 (cached — correct)

        $this->assertSame(0.05, $rate1);
        $this->assertSame(0.05, $rate2);
    }

    /**
     * Across two jobs, the cache poisons the second calculation.
     *
     * Job 1: order total = $500 → rate = 0.05 (small order, cached)
     * Job 2: order total = $5000 → should be 0.15 (large order)
     *        BUT gets 0.05 — the cached value from job 1
     *
     * In production, large-order customers receive an incorrect (too small)
     * discount. Finance sees the bug only when reconciling statements.
     */
    public function testCachePoisonsSecondJob(): void
    {
        $calculator = new DiscountCalculator(); // singleton — same instance

        // Job 1: small order
        $rateForJob1 = $calculator->calculateRate(500.0);
        $this->assertSame(0.05, $rateForJob1, 'Job 1: small order gets 5% discount');

        // Job 2: large order — SHOULD get 15% but gets 5% due to poisoned cache
        $rateForJob2 = $calculator->calculateRate(5000.0);

        // BUG: 0.05 instead of 0.15 — cache from job 1 is returned
        $this->assertSame(0.05, $rateForJob2,
            'BUG CONFIRMED: job 2 received the cached rate from job 1 (0.05 instead of 0.15)'
        );
        // The correct value would be:
        // $this->assertSame(0.15, $rateForJob2);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PART D — How each behaves under share-nothing (for comparison)
//
// Under PHP-FPM, each of the above services would be recreated per request.
// The tests below confirm that "fresh instance per job" eliminates all three bugs.
// This is not a real fix — it is a demonstration that the bugs are caused by
// instance reuse, not by the service code itself.
// ─────────────────────────────────────────────────────────────────────────────

class ShareNothingComparisonTest extends TestCase
{
    /**
     * Fresh ImportService per job: no error accumulation.
     */
    public function testFreshImportServicePerJobHasNoAccumulation(): void
    {
        // Job 1 — fresh service
        $service1 = new ImportService();
        $service1->processRow(['email' => 'bad-email']);
        $this->assertCount(1, $service1->getErrors());

        // Job 2 — FRESH service (simulates share-nothing)
        $service2 = new ImportService();
        $service2->processRow(['email' => 'also-bad']);
        $this->assertCount(1, $service2->getErrors(), 'Job 2 starts clean');
    }

    /**
     * Fresh NotificationService per job: no context leakage.
     */
    public function testFreshNotificationServicePerJobHasNoContextLeak(): void
    {
        // Job 1
        $service1 = new NotificationService();
        $service1->setTenant('acme');
        $service1->notifyAdmin('Invoice ready');

        // Job 2 — FRESH service: currentTenantId is null, not 'acme'
        $service2 = new NotificationService();
        $this->assertNull($service2->getCurrentTenant(), 'Job 2 starts with no tenant set');
    }

    /**
     * Fresh DiscountCalculator per job: no cache poisoning.
     */
    public function testFreshDiscountCalculatorPerJobHasNoPoison(): void
    {
        // Job 1: small order
        $calc1 = new DiscountCalculator();
        $this->assertSame(0.05, $calc1->calculateRate(500.0));

        // Job 2: FRESH calculator — large order gets the correct rate
        $calc2 = new DiscountCalculator();
        $this->assertSame(0.15, $calc2->calculateRate(5000.0), 'Fresh calculator: correct rate');
    }
}