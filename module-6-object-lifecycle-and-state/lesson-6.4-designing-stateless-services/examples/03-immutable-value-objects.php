<?php
declare(strict_types=1);

/**
 * Example 03 — Immutable Value Objects
 * ---------------------------------------
 * Run via PHPUnit:
 *   ./vendor/bin/phpunit module-6-object-lifecycle-and-state/lesson-6.4-designing-stateless-services/examples/03-immutable-value-objects.php
 *
 * Stateless services do not have instance state. But not every PHP class
 * should be stateless. Value objects SHOULD hold state — that is their purpose.
 * The difference is HOW they hold it:
 *
 *   Stateless service: no instance state at all
 *   Immutable value object: instance state set ONCE at construction, never changed
 *
 * This file covers:
 *   PART A — Money: the canonical value object example
 *   PART B — PHP 8.5's `clone with` syntax for copy-on-write updates
 *   PART C — DateRange: a more complex value object with invariant enforcement
 *   PART D — The difference between a value object and a stateful service
 *   PART E — Tests proving immutability and correctness
 *
 * PHP 8.5 feature: `clone with` allows clean copy-on-write semantics
 * for readonly properties without verbose workaround patterns.
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// PART A — Money: the canonical value object
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Represents an amount of money in a specific currency.
 *
 * CORRECT use of instance state:
 *   - $cents and $currency are set once at construction
 *   - All properties are readonly — PHP enforces immutability
 *   - "Mutation" methods (add, subtract, multiply) return NEW Money objects
 *   - $this is never modified after construction
 *
 * Safe as a singleton? Irrelevant — value objects are not singletons.
 * Each piece of domain logic creates fresh Money objects as needed.
 * Value objects are transient by nature.
 */
final class Money
{
    public function __construct(
        public readonly int    $cents,    // amounts stored as cents to avoid float imprecision
        public readonly string $currency,
    ) {
        if ($this->cents < 0) {
            throw new \InvalidArgumentException('Money cannot be negative');
        }
        if (empty($this->currency)) {
            throw new \InvalidArgumentException('Currency is required');
        }
    }

    // ── Factory methods ───────────────────────────────────────────────────────

    public static function of(float $amount, string $currency): self
    {
        return new self((int) round($amount * 100), strtoupper($currency));
    }

    public static function zero(string $currency): self
    {
        return new self(0, strtoupper($currency));
    }

    // ── Copy-on-write operations — each returns a NEW Money ──────────────────

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->cents + $other->cents, $this->currency);
    }

    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);
        if ($other->cents > $this->cents) {
            throw new \InvalidArgumentException('Cannot subtract more than available');
        }
        return new self($this->cents - $other->cents, $this->currency);
    }

    public function multiply(float $factor): self
    {
        return new self((int) round($this->cents * $factor), $this->currency);
    }

    public function discountBy(float $percentage): self
    {
        if ($percentage < 0 || $percentage > 100) {
            throw new \InvalidArgumentException('Percentage must be 0–100');
        }
        return $this->multiply(1 - ($percentage / 100));
    }

    // ── Comparison ────────────────────────────────────────────────────────────

    public function equals(Money $other): bool
    {
        return $this->cents === $other->cents && $this->currency === $other->currency;
    }

    public function isGreaterThan(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->cents > $other->cents;
    }

    // ── Formatting ────────────────────────────────────────────────────────────

    public function format(): string
    {
        return sprintf('%s %.2f', $this->currency, $this->cents / 100);
    }

    public function amount(): float
    {
        return $this->cents / 100;
    }

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException(
                "Currency mismatch: {$this->currency} vs {$other->currency}"
            );
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PART B — PHP 8.5 `clone with` syntax
//
// PHP 8.5 introduces `clone with` as a clean way to create a copy of an object
// with specific readonly properties changed. This replaces the verbose
// manual-clone-and-reassign pattern from PHP 8.1.
//
// PHP 8.1 (verbose):
//   $updated = clone $original;
//   // Cannot do: $updated->status = 'shipped'; — readonly!
//   // Must use Reflection or a withStatus() method that does:
//   return new self($this->id, $this->customerId, 'shipped', $this->total);
//
// PHP 8.5 (clean):
//   $updated = clone $original with { status: 'shipped' };
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Represents an order as an immutable value object.
 * Uses PHP 8.5 `clone with` for state transitions.
 */
final class Order
{
    public function __construct(
        public readonly string $id,
        public readonly string $customerId,
        public readonly string $status,    // 'pending' | 'paid' | 'shipped' | 'cancelled'
        public readonly Money  $total,
        public readonly ?string $trackingNumber = null,
    ) {}

    /**
     * Returns a new Order with status 'paid'.
     * Uses PHP 8.5 `clone with` — no manual constructor call needed.
     */
    public function markPaid(): self
    {
        if ($this->status !== 'pending') {
            throw new \RuntimeException("Cannot pay an order in status: {$this->status}");
        }
        // PHP 8.5: clone with copies all properties and overrides the listed ones
        return clone $this with { status: 'paid' };
    }

    /**
     * Returns a new Order with status 'shipped' and a tracking number.
     */
    public function markShipped(string $trackingNumber): self
    {
        if ($this->status !== 'paid') {
            throw new \RuntimeException("Cannot ship an order in status: {$this->status}");
        }
        return clone $this with { status: 'shipped', trackingNumber: $trackingNumber };
    }

    /**
     * Returns a new Order with status 'cancelled'.
     */
    public function cancel(): self
    {
        if (in_array($this->status, ['shipped', 'cancelled'], true)) {
            throw new \RuntimeException("Cannot cancel an order in status: {$this->status}");
        }
        return clone $this with { status: 'cancelled' };
    }

    public function isPending(): bool   { return $this->status === 'pending'; }
    public function isPaid(): bool      { return $this->status === 'paid'; }
    public function isShipped(): bool   { return $this->status === 'shipped'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }
}

// ─────────────────────────────────────────────────────────────────────────────
// PART C — DateRange: invariant enforcement in a value object
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Represents an inclusive date range with business-rule enforcement.
 *
 * Demonstrates: value objects can have complex validation in the constructor.
 * The invariant (start <= end) is enforced at construction time — any
 * DateRange that exists is guaranteed to be valid.
 */
final class DateRange
{
    public function __construct(
        public readonly \DateTimeImmutable $start,
        public readonly \DateTimeImmutable $end,
    ) {
        if ($this->end < $this->start) {
            throw new \InvalidArgumentException(
                "End date must be on or after start date"
            );
        }
    }

    public function contains(\DateTimeImmutable $date): bool
    {
        return $date >= $this->start && $date <= $this->end;
    }

    public function overlaps(DateRange $other): bool
    {
        return $this->start <= $other->end && $this->end >= $other->start;
    }

    public function durationInDays(): int
    {
        return (int) $this->start->diff($this->end)->days;
    }

    /**
     * Returns a new DateRange extended by the given number of days.
     * Uses PHP 8.5 `clone with`.
     */
    public function extendBy(int $days): self
    {
        return clone $this with {
            end: $this->end->modify("+{$days} days")
        };
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PART D — Value object vs stateful service: the key distinction
// ─────────────────────────────────────────────────────────────────────────────

/**
 * These two classes look similar but are fundamentally different.
 *
 * PriceCalculator: a SERVICE — no instance state after construction.
 *   Inputs arrive via method parameters. Outputs return as values.
 *   Safe as a singleton.
 *
 * Price: a VALUE OBJECT — all instance state, set at construction, immutable.
 *   Represents a fact (a price). Mutations return new objects.
 *   Not a singleton — created fresh for each price value.
 */

// Service — no instance state (beyond immutable config)
class PriceCalculator
{
    public function __construct(private readonly float $taxRate) {}

    public function withTax(Money $price): Money
    {
        return $price->multiply(1 + $this->taxRate);
    }

    public function withDiscount(Money $price, float $discountPct): Money
    {
        return $price->discountBy($discountPct);
    }

    public function total(Money $price, float $discountPct): Money
    {
        return $this->withTax($this->withDiscount($price, $discountPct));
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PART E — Tests
// ─────────────────────────────────────────────────────────────────────────────

class ImmutableValueObjectsTest extends TestCase
{
    // ── Money tests ──────────────────────────────────────────────────────────

    /**
     * Money is immutable: add() returns a new Money, original is unchanged.
     */
    public function testMoneyAddReturnsNewObjectLeavingOriginalUnchanged(): void
    {
        $price    = Money::of(10.00, 'USD');
        $shipping = Money::of(2.50, 'USD');

        $total = $price->add($shipping);

        // Original is unchanged
        $this->assertSame(1000, $price->cents,  'Original price unchanged');
        $this->assertSame(250,  $shipping->cents, 'Original shipping unchanged');

        // New object has the sum
        $this->assertSame(1250, $total->cents);
        $this->assertSame(12.50, $total->amount());

        // Different objects
        $this->assertNotSame($price, $total);
    }

    /**
     * A chain of operations produces the correct final value without
     * modifying any intermediate objects.
     */
    public function testMoneyOperationChainIsCorrect(): void
    {
        $subtotal  = Money::of(100.00, 'GBP');
        $discounted = $subtotal->discountBy(10);    // 90.00
        $withTax    = $discounted->multiply(1.20);  // 108.00

        $this->assertSame(10000, $subtotal->cents,   'Subtotal: £100.00 unchanged');
        $this->assertSame(9000,  $discounted->cents, 'After 10% off: £90.00');
        $this->assertSame(10800, $withTax->cents,    'After 20% tax: £108.00');

        // All three are distinct objects
        $this->assertNotSame($subtotal,  $discounted);
        $this->assertNotSame($discounted, $withTax);
    }

    /**
     * Currency mismatch throws immediately — invariant enforced by the value object.
     */
    public function testMoneyRejectsCurrencyMismatch(): void
    {
        $usd = Money::of(10.00, 'USD');
        $eur = Money::of(10.00, 'EUR');

        $this->expectException(\InvalidArgumentException::class);
        $usd->add($eur);
    }

    /**
     * Negative Money cannot be created — constructor invariant.
     */
    public function testMoneyRejectsNegativeAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Money(-1, 'USD');
    }

    // ── Order / clone with tests ──────────────────────────────────────────────

    /**
     * PHP 8.5 `clone with`: markPaid() produces a new Order with status 'paid'.
     * The original order remains 'pending' — immutability preserved.
     */
    public function testOrderMarkPaidReturnsNewOrderLeavingOriginalUnchanged(): void
    {
        $order = new Order(
            id:         'ord-001',
            customerId: 'cust-alice',
            status:     'pending',
            total:      Money::of(99.99, 'USD'),
        );

        $paidOrder = $order->markPaid();

        // Original is unchanged
        $this->assertTrue($order->isPending(),   'Original order still pending');
        $this->assertSame('pending', $order->status);

        // New object has status 'paid'
        $this->assertTrue($paidOrder->isPaid());
        $this->assertSame('paid', $paidOrder->status);

        // Other properties are carried over unchanged
        $this->assertSame('ord-001',    $paidOrder->id);
        $this->assertSame('cust-alice', $paidOrder->customerId);
        $this->assertTrue($paidOrder->total->equals(Money::of(99.99, 'USD')));

        // Different objects
        $this->assertNotSame($order, $paidOrder);
    }

    /**
     * State machine enforcement: cannot mark pending order as shipped.
     */
    public function testOrderCannotBeShippedBeforePaid(): void
    {
        $order = new Order('ord-001', 'cust-001', 'pending', Money::of(50.00, 'USD'));

        $this->expectException(\RuntimeException::class);
        $order->markShipped('TRACK-001'); // must be paid first
    }

    /**
     * Full state machine: pending → paid → shipped.
     * Each transition produces a new immutable order.
     */
    public function testOrderFullStateTransition(): void
    {
        $order    = new Order('ord-001', 'cust-001', 'pending', Money::of(50.00, 'USD'));
        $paid     = $order->markPaid();
        $shipped  = $paid->markShipped('TRACK-XYZ-001');

        $this->assertTrue($order->isPending());
        $this->assertTrue($paid->isPaid());
        $this->assertTrue($shipped->isShipped());
        $this->assertSame('TRACK-XYZ-001', $shipped->trackingNumber);
        $this->assertNull($paid->trackingNumber);
    }

    // ── DateRange tests ───────────────────────────────────────────────────────

    /**
     * DateRange enforces start <= end at construction time.
     */
    public function testDateRangeRejectsInvertedRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DateRange(
            start: new \DateTimeImmutable('2026-12-31'),
            end:   new \DateTimeImmutable('2026-01-01'), // end before start
        );
    }

    /**
     * DateRange.contains() and overlaps() are pure predicates.
     */
    public function testDateRangeContainsAndOverlaps(): void
    {
        $range = new DateRange(
            start: new \DateTimeImmutable('2026-06-01'),
            end:   new \DateTimeImmutable('2026-06-30'),
        );

        $this->assertTrue($range->contains(new \DateTimeImmutable('2026-06-15')));
        $this->assertFalse($range->contains(new \DateTimeImmutable('2026-07-01')));
        $this->assertSame(29, $range->durationInDays());

        $overlapping = new DateRange(
            new \DateTimeImmutable('2026-06-20'),
            new \DateTimeImmutable('2026-07-10'),
        );
        $this->assertTrue($range->overlaps($overlapping));

        $nonOverlapping = new DateRange(
            new \DateTimeImmutable('2026-07-01'),
            new \DateTimeImmutable('2026-07-31'),
        );
        $this->assertFalse($range->overlaps($nonOverlapping));
    }

    /**
     * extendBy() uses `clone with` to produce a new DateRange.
     * Original is unchanged.
     */
    public function testDateRangeExtendByReturnsNewRangeWithSameStart(): void
    {
        $original = new DateRange(
            new \DateTimeImmutable('2026-06-01'),
            new \DateTimeImmutable('2026-06-30'),
        );

        $extended = $original->extendBy(7);

        $this->assertSame(29, $original->durationInDays(), 'Original: 29 days, unchanged');
        $this->assertSame(36, $extended->durationInDays(), 'Extended: 36 days (29 + 7)');
        $this->assertSame(
            $original->start->format('Y-m-d'),
            $extended->start->format('Y-m-d'),
            'Start date carried over unchanged'
        );
        $this->assertSame('2026-07-07', $extended->end->format('Y-m-d'));
    }

    // ── Service vs value object distinction ──────────────────────────────────

    /**
     * PriceCalculator is a stateless singleton — same Money in, same Money out.
     * It does not accumulate any state between calls.
     */
    public function testPriceCalculatorIsStatelessSingleton(): void
    {
        $calc = new PriceCalculator(taxRate: 0.20); // singleton

        $price = Money::of(100.00, 'GBP');

        // Same inputs, same outputs — every single time
        $total1 = $calc->total($price, discountPct: 10);
        $total2 = $calc->total($price, discountPct: 10);

        $this->assertTrue($total1->equals($total2), 'Same inputs → same result');
        $this->assertSame(10800, $total1->cents, '£100 - 10% + 20% tax = £108.00');

        // Different callers, different discounts, all correct
        $totalA = $calc->total($price, discountPct: 0);   // £120.00
        $totalB = $calc->total($price, discountPct: 50);  // £60.00

        $this->assertSame(12000, $totalA->cents, '0% discount + 20% tax = £120.00');
        $this->assertSame(6000,  $totalB->cents, '50% discount + 20% tax = £60.00');

        // Original price is unchanged
        $this->assertSame(10000, $price->cents, 'Original price £100.00 unchanged');
    }
}