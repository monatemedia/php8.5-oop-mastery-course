<?php
declare(strict_types=1);

/**
 * CHALLENGE SOLUTION — Lesson 6.2: Transient vs Singleton Scopes
 * ───────────────────────────────────────────────────────────────
 * ⚠️  Only open this file after completing all six tests yourself.
 *
 * The scope decision for each service is shown with:
 *   1. The registration line (singleton or transient)
 *   2. A // SCOPE comment explaining the reasoning
 *   3. A test with both the identity assertion and the no-contamination
 *      or clean-initial-state assertion
 *
 * Score card:
 *   Service 1 (CurrencyConverter)  → SINGLETON  (immutable rates, pure computation)
 *   Service 2 (OrderBuilder)       → TRANSIENT  (accumulates lines — must be fresh per order)
 *   Service 3 (EventDispatcher)    → SINGLETON  (listeners fixed at construction, dispatch() is stateless)
 *   Service 4 (JobContext)         → TRANSIENT  (stores per-job metadata — must be fresh per job)
 *   Service 5 (PasswordHasher)     → SINGLETON  (cost fixed at construction, hash/verify are pure)
 *   Service 6 (ReportAccumulator)  → TRANSIENT  (accumulates rows — must be fresh per report)
 *
 * Key comparison to check against your solution:
 *   Did you spot that EventDispatcher is a singleton despite taking listeners
 *   in its constructor? The listeners array is SET AT CONSTRUCTION and never
 *   changed after — dispatch() reads $this->listeners but never writes to it.
 *   That makes it safe.
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// SimpleContainer (identical to starter — do not modify)
// ─────────────────────────────────────────────────────────────────────────────

class SimpleContainer
{
    private array $definitions = [];
    private array $singletons  = [];

    public function singleton(string $id, callable $factory): void
    {
        $this->definitions[$id] = ['factory' => $factory, 'transient' => false];
    }

    public function transient(string $id, callable $factory): void
    {
        $this->definitions[$id] = ['factory' => $factory, 'transient' => true];
    }

    public function get(string $id): object
    {
        if (!isset($this->definitions[$id])) {
            throw new \RuntimeException("No definition for {$id}");
        }
        $def = $this->definitions[$id];
        if ($def['transient']) {
            return ($def['factory'])();
        }
        if (!isset($this->singletons[$id])) {
            $this->singletons[$id] = ($def['factory'])();
        }
        return $this->singletons[$id];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Service classes (identical to starter — do not modify)
// ─────────────────────────────────────────────────────────────────────────────

class CurrencyConverter
{
    private array $rates;
    public function __construct(array $rates) { $this->rates = $rates; }
    public function convert(float $amount, string $from, string $to): float
    {
        if (!isset($this->rates[$from], $this->rates[$to])) {
            throw new \InvalidArgumentException("Unknown currency");
        }
        return round(($amount / $this->rates[$from]) * $this->rates[$to], 2);
    }
    public function getSupportedCurrencies(): array { return array_keys($this->rates); }
}

class OrderBuilder
{
    private array $lines = [];
    public function addLine(string $sku, int $qty, float $price): void
    {
        $this->lines[] = ['sku' => $sku, 'qty' => $qty, 'price' => $price];
    }
    public function getLines(): array  { return $this->lines; }
    public function getLineCount(): int { return count($this->lines); }
    public function build(): array
    {
        $order = ['lines' => $this->lines, 'total' => array_sum(array_map(fn($l) => $l['price'] * $l['qty'], $this->lines))];
        $this->lines = [];
        return $order;
    }
}

class EventDispatcher
{
    private array $listeners;
    public function __construct(array $listeners) { $this->listeners = $listeners; }
    public function dispatch(string $eventName, array $payload = []): int
    {
        $dispatched = 0;
        foreach ($this->listeners[$eventName] ?? [] as $listener) {
            $listener($payload);
            $dispatched++;
        }
        return $dispatched;
    }
    public function hasListeners(string $eventName): bool
    {
        return !empty($this->listeners[$eventName]);
    }
}

class JobContext
{
    private ?string $jobId    = null;
    private ?string $tenantId = null;
    private string  $priority = 'normal';
    public function initialise(string $jobId, string $tenantId, string $priority = 'normal'): void
    {
        $this->jobId    = $jobId;
        $this->tenantId = $tenantId;
        $this->priority = $priority;
    }
    public function getJobId(): ?string    { return $this->jobId; }
    public function getTenantId(): ?string { return $this->tenantId; }
    public function getPriority(): string  { return $this->priority; }
    public function isInitialised(): bool  { return $this->jobId !== null; }
}

class PasswordHasher
{
    private int $cost;
    public function __construct(int $cost = 12) { $this->cost = $cost; }
    public function hash(string $plaintext): string
    {
        return password_hash($plaintext, PASSWORD_BCRYPT, ['cost' => $this->cost]);
    }
    public function verify(string $plaintext, string $hash): bool
    {
        return password_verify($plaintext, $hash);
    }
}

class ReportAccumulator
{
    private string $title = 'Report';
    private array  $rows  = [];
    public function setTitle(string $title): void { $this->title = $title; }
    public function addRow(array $row): void { $this->rows[] = $row; }
    public function getRowCount(): int { return count($this->rows); }
    public function render(): string
    {
        $lines = ["=== {$this->title} ==="];
        foreach ($this->rows as $row) {
            $lines[] = implode(' | ', $row);
        }
        return implode("\n", $lines);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Solution tests
// ─────────────────────────────────────────────────────────────────────────────

class ScopeAssignmentTest extends TestCase
{
    private SimpleContainer $container;

    protected function setUp(): void
    {
        $this->container = new SimpleContainer();

        // ── Service 1: CurrencyConverter ──────────────────────────────────────
        $this->container->singleton(CurrencyConverter::class, fn() => new CurrencyConverter([
            'USD' => 1.00,
            'EUR' => 0.92,
            'GBP' => 0.79,
            'JPY' => 149.50,
        ]));
        // SCOPE: singleton — $rates is set at construction via readonly-equivalent
        // pattern (constructor assigns, no public setter exists). convert() is a
        // pure computation: output depends only on its parameters and the immutable
        // $rates array. Two consumers sharing this instance can never contaminate
        // each other's results.

        // ── Service 2: OrderBuilder ──────────────────────────────────────────
        $this->container->transient(OrderBuilder::class, fn() => new OrderBuilder());
        // SCOPE: transient — addLine() writes to $this->lines, which accumulates
        // between calls. If two orders were built using the same singleton instance,
        // order 2 would contain order 1's line items. Each order must start with
        // an empty $lines array, which only transient scope guarantees.

        // ── Service 3: EventDispatcher ──────────────────────────────────────
        $this->container->singleton(EventDispatcher::class, fn() => new EventDispatcher([
            'OrderPlaced'    => [fn($e) => null], // no-op listeners for tests
            'PaymentFailed'  => [fn($e) => null],
        ]));
        // SCOPE: singleton — this is the tricky one. EventDispatcher takes a
        // listeners array in its constructor, but that array is ASSIGNED ONCE and
        // never written to by any public method after construction. dispatch() reads
        // $this->listeners but does not append to it, replace it, or modify it.
        // The dispatcher is effectively immutable after construction — safe singleton.
        // (If there were an addListener() method, this would change to transient.)

        // ── Service 4: JobContext ──────────────────────────────────────────
        $this->container->transient(JobContext::class, fn() => new JobContext());
        // SCOPE: transient — initialise() writes to $this->jobId, $this->tenantId,
        // and $this->priority. This is "current job" state that is specific to one
        // job and must not bleed into the next. As a singleton, job 2 would see
        // job 1's tenant ID until initialise() overwrites it — a data isolation bug
        // identical to the UserSessionService problem in Lesson 6.1.

        // ── Service 5: PasswordHasher ──────────────────────────────────────
        $this->container->singleton(PasswordHasher::class, fn() => new PasswordHasher(cost: 4));
        // SCOPE: singleton — $cost is set at construction and never changed.
        // hash() and verify() are pure operations: they take a string and return
        // a result without modifying $this in any way. The bcrypt operation itself
        // is stateless — it does not accumulate anything between calls.
        // (cost=4 is used here for test speed; production would use cost=12+)

        // ── Service 6: ReportAccumulator ─────────────────────────────────
        $this->container->transient(ReportAccumulator::class, fn() => new ReportAccumulator());
        // SCOPE: transient — addRow() appends to $this->rows and setTitle() changes
        // $this->title. Both are written by public methods after construction.
        // Each report generation must start with empty rows and a blank title.
        // As a singleton, report 2's render() output would include report 1's rows.
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Service 1 — CurrencyConverter (SINGLETON)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Singleton proof:
     *   - Two resolutions return the same instance (assertSame)
     *   - Two consumers converting different amounts get correct, independent results
     *     (converting $100 USD→EUR does not affect the next $200 GBP→USD conversion)
     */
    public function testCurrencyConverterScopeIsCorrect(): void
    {
        $converterA = $this->container->get(CurrencyConverter::class);
        $converterB = $this->container->get(CurrencyConverter::class);

        // Identity: same singleton instance
        $this->assertSame($converterA, $converterB,
            'CurrencyConverter is a singleton — both resolutions return the same instance'
        );

        // No-contamination: Consumer A converts USD→EUR; Consumer B converts USD→GBP
        // Neither conversion affects the other
        $eurAmount = $converterA->convert(100.00, 'USD', 'EUR'); // 100 * 0.92 = 92.00
        $gbpAmount = $converterB->convert(100.00, 'USD', 'GBP'); // 100 * 0.79 = 79.00

        $this->assertSame(92.00, $eurAmount, 'USD→EUR: 100 * 0.92 = 92.00');
        $this->assertSame(79.00, $gbpAmount, 'USD→GBP: 100 * 0.79 = 79.00');

        // Confirm consumer A's conversion did not corrupt the rates
        // (if state were mutable, repeated calls might drift)
        $this->assertSame(92.00, $converterA->convert(100.00, 'USD', 'EUR'),
            'Repeated conversion returns same result — rates are immutable'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Service 2 — OrderBuilder (TRANSIENT)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Transient proof:
     *   - Two resolutions return different instances (assertNotSame)
     *   - Each resolution starts with zero lines (clean initial state)
     *   - Lines added to order 1 do not appear in order 2
     */
    public function testOrderBuilderScopeIsCorrect(): void
    {
        $builder1 = $this->container->get(OrderBuilder::class);
        $builder2 = $this->container->get(OrderBuilder::class);

        // Identity: different transient instances
        $this->assertNotSame($builder1, $builder2,
            'OrderBuilder is transient — each resolution returns a new instance'
        );

        // Clean initial state: both start empty
        $this->assertSame(0, $builder1->getLineCount(), 'builder1 starts with 0 lines');
        $this->assertSame(0, $builder2->getLineCount(), 'builder2 starts with 0 lines');

        // Build order 1: laptop + mouse
        $builder1->addLine('LAPTOP-001', 1, 999.99);
        $builder1->addLine('MOUSE-007',  1, 29.99);
        $order1 = $builder1->build();

        $this->assertSame(2, count($order1['lines']));
        $this->assertSame(1029.98, $order1['total']);

        // Build order 2: just a keyboard
        // Lines from order 1 must NOT appear in order 2
        $builder2->addLine('KEYBOARD-003', 1, 49.99);

        $this->assertSame(1, $builder2->getLineCount(),
            'Order 2 has 1 line — no contamination from order 1'
        );

        $order2 = $builder2->build();
        $this->assertSame(1, count($order2['lines']));
        $this->assertSame(49.99, $order2['total']);

        $skus = array_column($order2['lines'], 'sku');
        $this->assertNotContains('LAPTOP-001', $skus,
            'Order 2 does not contain the laptop from order 1'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Service 3 — EventDispatcher (SINGLETON)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Singleton proof:
     *   - Two resolutions return the same instance
     *   - Dispatching events through the singleton does not mutate the listeners
     *     array — both consumers can dispatch independently without interference
     *
     * This is the "tricky" singleton: it takes constructor arguments (listeners),
     * but because those arguments are never mutated after construction, it is safe.
     */
    public function testEventDispatcherScopeIsCorrect(): void
    {
        $dispatcherA = $this->container->get(EventDispatcher::class);
        $dispatcherB = $this->container->get(EventDispatcher::class);

        // Identity: same singleton instance
        $this->assertSame($dispatcherA, $dispatcherB,
            'EventDispatcher is a singleton — listeners are fixed at construction'
        );

        // No-contamination: dispatching via A does not affect B's ability to dispatch
        // Track how many times listeners are called
        $listenerCalls = [];

        // Create a fresh dispatcher with spy listeners (not from the container —
        // we want to observe call counts without modifying the container definition)
        $spyDispatcher = new EventDispatcher([
            'OrderPlaced'   => [function ($e) use (&$listenerCalls) { $listenerCalls[] = "order-{$e['id']}"; }],
            'PaymentFailed' => [function ($e) use (&$listenerCalls) { $listenerCalls[] = "payment-{$e['id']}"; }],
        ]);

        // Consumer A dispatches OrderPlaced
        $countA = $spyDispatcher->dispatch('OrderPlaced', ['id' => 42]);

        // Consumer B dispatches PaymentFailed (using the same instance)
        $countB = $spyDispatcher->dispatch('PaymentFailed', ['id' => 99]);

        $this->assertSame(1, $countA, 'OrderPlaced: 1 listener called');
        $this->assertSame(1, $countB, 'PaymentFailed: 1 listener called');

        // The listener calls are independent — A's dispatch did not block B's
        $this->assertContains('order-42',   $listenerCalls);
        $this->assertContains('payment-99', $listenerCalls);

        // No phantom events — exactly 2 calls, one per dispatch
        $this->assertCount(2, $listenerCalls);

        // Verify the registered dispatcher has its listeners intact
        $this->assertTrue($dispatcherA->hasListeners('OrderPlaced'));
        $this->assertTrue($dispatcherA->hasListeners('PaymentFailed'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Service 4 — JobContext (TRANSIENT)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Transient proof:
     *   - Two resolutions return different instances
     *   - Each starts uninitialised (isInitialised() = false)
     *   - Job 1's tenant ID does not leak into job 2's context
     */
    public function testJobContextScopeIsCorrect(): void
    {
        $contextJob1 = $this->container->get(JobContext::class);
        $contextJob2 = $this->container->get(JobContext::class);

        // Identity: different instances
        $this->assertNotSame($contextJob1, $contextJob2,
            'JobContext is transient — each job gets a fresh context'
        );

        // Clean initial state: both start uninitialised
        $this->assertFalse($contextJob1->isInitialised(), 'Job 1 context: not yet initialised');
        $this->assertFalse($contextJob2->isInitialised(), 'Job 2 context: not yet initialised');
        $this->assertNull($contextJob1->getTenantId());
        $this->assertNull($contextJob2->getTenantId());

        // Initialise job 1
        $contextJob1->initialise('job-001', 'tenant-acme', 'high');
        $this->assertSame('tenant-acme', $contextJob1->getTenantId());
        $this->assertSame('high', $contextJob1->getPriority());

        // Job 2's context is independent — tenant from job 1 did not leak
        $this->assertNull($contextJob2->getTenantId(),
            'Job 2 context has no tenant ID — job 1\'s tenant did not leak'
        );
        $this->assertFalse($contextJob2->isInitialised(),
            'Job 2 context is still uninitialised'
        );

        // Initialise job 2 with different data
        $contextJob2->initialise('job-002', 'tenant-globex', 'normal');
        $this->assertSame('tenant-globex', $contextJob2->getTenantId());

        // Job 1 is unaffected by job 2's initialisation
        $this->assertSame('tenant-acme', $contextJob1->getTenantId(),
            'Job 1 context unaffected by job 2 initialisation'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Service 5 — PasswordHasher (SINGLETON)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Singleton proof:
     *   - Two resolutions return the same instance
     *   - hash() and verify() are pure — two consumers can use the same
     *     instance without interfering with each other's results
     *
     * NOTE: bcrypt is inherently non-deterministic (random salt), so we verify
     * the round-trip (hash then verify) rather than asserting an exact hash value.
     */
    public function testPasswordHasherScopeIsCorrect(): void
    {
        $hasherA = $this->container->get(PasswordHasher::class);
        $hasherB = $this->container->get(PasswordHasher::class);

        // Identity: same singleton instance
        $this->assertSame($hasherA, $hasherB,
            'PasswordHasher is a singleton — cost is fixed at construction'
        );

        // No-contamination: Consumer A hashes Alice's password
        $hashA = $hasherA->hash('alice-secret-password');

        // Consumer B hashes Bob's password using the SAME instance
        $hashB = $hasherB->hash('bob-secret-password');

        // Each hash is correct for its respective plaintext
        $this->assertTrue($hasherA->verify('alice-secret-password', $hashA),
            'Alice\'s hash verifies correctly'
        );
        $this->assertTrue($hasherB->verify('bob-secret-password', $hashB),
            'Bob\'s hash verifies correctly'
        );

        // Cross-verify: Alice's password does NOT verify against Bob's hash
        $this->assertFalse($hasherA->verify('alice-secret-password', $hashB),
            'Alice\'s password does not match Bob\'s hash — hashes are independent'
        );

        // The hashes are different (bcrypt randomises the salt)
        $this->assertNotSame($hashA, $hashB,
            'Two different passwords produce two different hashes'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Service 6 — ReportAccumulator (TRANSIENT)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Transient proof:
     *   - Two resolutions return different instances
     *   - Each starts with zero rows and default title
     *   - Rows added to report 1 do not appear in report 2
     */
    public function testReportAccumulatorScopeIsCorrect(): void
    {
        $report1 = $this->container->get(ReportAccumulator::class);
        $report2 = $this->container->get(ReportAccumulator::class);

        // Identity: different transient instances
        $this->assertNotSame($report1, $report2,
            'ReportAccumulator is transient — each report generation gets a fresh instance'
        );

        // Clean initial state: both start with zero rows
        $this->assertSame(0, $report1->getRowCount(), 'Report 1 starts with 0 rows');
        $this->assertSame(0, $report2->getRowCount(), 'Report 2 starts with 0 rows');

        // Build report 1: monthly sales
        $report1->setTitle('Monthly Sales');
        $report1->addRow(['product' => 'Widget', 'units' => '100', 'revenue' => '$5000']);
        $report1->addRow(['product' => 'Gadget', 'units' => '50',  'revenue' => '$7500']);

        $rendered1 = $report1->render();
        $this->assertStringContainsString('Monthly Sales', $rendered1);
        $this->assertStringContainsString('Widget', $rendered1);
        $this->assertSame(2, $report1->getRowCount());

        // Build report 2: Q4 forecast
        // Must start fresh — no rows or title from report 1
        $report2->setTitle('Q4 Forecast');
        $report2->addRow(['product' => 'New Product', 'units' => '200', 'revenue' => '$20000']);

        $rendered2 = $report2->render();

        // Report 2 contains its own content
        $this->assertStringContainsString('Q4 Forecast', $rendered2);
        $this->assertStringContainsString('New Product', $rendered2);
        $this->assertSame(1, $report2->getRowCount(),
            'Report 2 has exactly 1 row — no contamination from report 1'
        );

        // Report 2 does NOT contain report 1's data
        $this->assertStringNotContainsString('Widget', $rendered2,
            'Report 2 does not contain Widget from report 1'
        );
        $this->assertStringNotContainsString('Monthly Sales', $rendered2,
            'Report 2 does not carry report 1\'s title'
        );
    }
}