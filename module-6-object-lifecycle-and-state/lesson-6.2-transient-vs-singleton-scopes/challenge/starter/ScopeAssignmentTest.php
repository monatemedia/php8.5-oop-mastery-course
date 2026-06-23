<?php
declare(strict_types=1);

/**
 * CHALLENGE STARTER — Lesson 6.2: Transient vs Singleton Scopes
 * ──────────────────────────────────────────────────────────────
 * Read CHALLENGE.md before touching this file.
 *
 * For each of the six services:
 *   1. Apply the scope decision rule (README Section 4)
 *   2. Register the service in setUp() using $this->container
 *   3. Add a // SCOPE: comment explaining your choice
 *   4. Uncomment and complete the test method
 *
 * Scope decision rule:
 *   Does any public method write to a property after construction?
 *     YES → TRANSIENT (mutable state — must not be shared)
 *     NO  → SINGLETON (stateless — safe to share)
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// SimpleContainer — DO NOT MODIFY
// Mirrors PHP-DI's singleton (autowire/create) and transient (factory) behaviour.
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
// Service classes — DO NOT MODIFY
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Service 1: Converts amounts between currencies using a fixed rate table.
 */
class CurrencyConverter
{
    // Rates set at construction — never changed after that
    private array $rates;

    /**
     * @param array<string, float> $rates e.g. ['USD' => 1.0, 'EUR' => 0.92, 'GBP' => 0.79]
     */
    public function __construct(array $rates)
    {
        $this->rates = $rates;
    }

    public function convert(float $amount, string $from, string $to): float
    {
        if (!isset($this->rates[$from], $this->rates[$to])) {
            throw new \InvalidArgumentException("Unknown currency");
        }
        // Convert to base (USD), then to target
        $inBase = $amount / $this->rates[$from];
        return round($inBase * $this->rates[$to], 2);
    }

    public function getSupportedCurrencies(): array
    {
        return array_keys($this->rates);
    }
}

/**
 * Service 2: Accumulates line items for an order in progress.
 */
class OrderBuilder
{
    private array $lines = [];

    public function addLine(string $sku, int $qty, float $price): void
    {
        $this->lines[] = ['sku' => $sku, 'qty' => $qty, 'price' => $price];
    }

    public function getLines(): array { return $this->lines; }

    public function getLineCount(): int { return count($this->lines); }

    public function build(): array
    {
        $order = [
            'lines' => $this->lines,
            'total' => array_sum(array_map(fn($l) => $l['price'] * $l['qty'], $this->lines)),
        ];
        $this->lines = []; // clear after build
        return $order;
    }
}

/**
 * Service 3: Dispatches domain events to listeners registered at construction.
 */
class EventDispatcher
{
    private array $listeners;

    /**
     * @param array<string, callable[]> $listeners e.g. ['OrderPlaced' => [fn($e) => ...]]
     */
    public function __construct(array $listeners)
    {
        $this->listeners = $listeners;
    }

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

/**
 * Service 4: Stores the current job's metadata for the duration of job processing.
 */
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

/**
 * Service 5: Hashes and verifies passwords using bcrypt.
 */
class PasswordHasher
{
    private int $cost;

    public function __construct(int $cost = 12)
    {
        $this->cost = $cost;
    }

    public function hash(string $plaintext): string
    {
        return password_hash($plaintext, PASSWORD_BCRYPT, ['cost' => $this->cost]);
    }

    public function verify(string $plaintext, string $hash): bool
    {
        return password_verify($plaintext, $hash);
    }
}

/**
 * Service 6: Collects report rows and renders a final report string.
 */
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
// Your tests
// ─────────────────────────────────────────────────────────────────────────────

class ScopeAssignmentTest extends TestCase
{
    private SimpleContainer $container;

    protected function setUp(): void
    {
        $this->container = new SimpleContainer();

        // TODO: register all six services here with the correct scope
        // Use $this->container->singleton(...) or $this->container->transient(...)
        // Add a // SCOPE: comment after each registration

        // $this->container->???(CurrencyConverter::class, fn() => new CurrencyConverter([...]));
        // // SCOPE: ??? — TODO: your reason here

        // $this->container->???(OrderBuilder::class, fn() => new OrderBuilder());
        // // SCOPE: ??? — TODO: your reason here

        // $this->container->???(EventDispatcher::class, fn() => new EventDispatcher([...]));
        // // SCOPE: ??? — TODO: your reason here

        // $this->container->???(JobContext::class, fn() => new JobContext());
        // // SCOPE: ??? — TODO: your reason here

        // $this->container->???(PasswordHasher::class, fn() => new PasswordHasher(4));
        // // SCOPE: ??? — TODO: your reason here (cost=4 for fast tests)

        // $this->container->???(ReportAccumulator::class, fn() => new ReportAccumulator());
        // // SCOPE: ??? — TODO: your reason here
    }

    // TODO: public function testCurrencyConverterScopeIsCorrect(): void {}

    // TODO: public function testOrderBuilderScopeIsCorrect(): void {}

    // TODO: public function testEventDispatcherScopeIsCorrect(): void {}

    // TODO: public function testJobContextScopeIsCorrect(): void {}

    // TODO: public function testPasswordHasherScopeIsCorrect(): void {}

    // TODO: public function testReportAccumulatorScopeIsCorrect(): void {}
}