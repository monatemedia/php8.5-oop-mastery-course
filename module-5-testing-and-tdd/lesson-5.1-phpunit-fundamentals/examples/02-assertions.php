<?php
declare(strict_types=1);

/**
 * Example 02 — Core Assertions
 * ------------------------------
 * Run via PHPUnit:
 *   ./vendor/bin/phpunit module-5-testing-and-tdd/lesson-5.1-phpunit-fundamentals/examples/02-assertions.php
 *
 * This example demonstrates every assertion you will use in 95% of tests.
 * Each test method is a self-contained demonstration of one assertion family.
 *
 * The class under test is a simple Order value object — concrete enough
 * to need real assertions, simple enough to understand immediately.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

// ─────────────────────────────────────────────────────────────────────────────
// The class under test
// ─────────────────────────────────────────────────────────────────────────────

class Order
{
    private array  $items  = [];
    private string $status = 'pending';
    private string $id;

    public function __construct(public readonly string $customerEmail)
    {
        $this->id = 'ORD-' . strtoupper(substr(md5(uniqid()), 0, 8));
    }

    public function addItem(string $name, int $priceCents, int $qty = 1): void
    {
        $this->items[] = ['name' => $name, 'price' => $priceCents, 'qty' => $qty];
    }

    public function confirm(): void
    {
        $this->status = 'confirmed';
    }

    public function cancel(): void
    {
        $this->status = 'cancelled';
    }

    public function getId(): string      { return $this->id; }
    public function getStatus(): string  { return $this->status; }
    public function getItems(): array    { return $this->items; }
    public function itemCount(): int     { return count($this->items); }
    public function isEmpty(): bool      { return empty($this->items); }

    public function totalCents(): int
    {
        return array_sum(array_map(
            fn($i) => $i['price'] * $i['qty'],
            $this->items
        ));
    }

    public function getNote(): ?string   { return null; }   // always null for this demo
}


// ─────────────────────────────────────────────────────────────────────────────
// The test class
// ─────────────────────────────────────────────────────────────────────────────

class AssertionsExampleTest extends TestCase
{
    // ═══════════════════════════════════════════════════════════
    // assertSame vs assertEquals
    // ═══════════════════════════════════════════════════════════

    /**
     * assertSame uses === (strict equality: same type AND same value).
     * This is your default choice — it catches type bugs that assertEquals misses.
     */
    public function testAssertSameUsesStrictEquality(): void
    {
        $order = new Order('alice@example.com');
        $order->addItem('Widget', 29999);

        // assertSame: 1 === 1  ✓
        $this->assertSame(1, $order->itemCount());

        // assertSame: 'pending' === 'pending'  ✓
        $this->assertSame('pending', $order->getStatus());

        // assertSame would FAIL if PHPUnit returned '1' (string) instead of 1 (int)
        // assertEquals would pass either way — which hides the bug
    }

    /**
     * assertEquals uses == (loose equality: type coercion allowed).
     * Mainly useful for floating-point comparisons via assertEqualsWithDelta.
     */
    public function testAssertEqualsAllowsTypeCoercion(): void
    {
        $order = new Order('alice@example.com');
        $order->addItem('Widget', 29999);

        // assertEquals: 1 == '1'  — passes (but prefer assertSame)
        $this->assertEquals(1, $order->itemCount());

        // For floats, always use assertEqualsWithDelta to handle precision
        $total  = $order->totalCents() / 100;   // 299.99
        $this->assertEqualsWithDelta(299.99, $total, delta: 0.001);
    }

    // ═══════════════════════════════════════════════════════════
    // Booleans and null
    // ═══════════════════════════════════════════════════════════

    public function testBooleanAssertions(): void
    {
        $emptyOrder = new Order('bob@example.com');
        $filledOrder = new Order('carol@example.com');
        $filledOrder->addItem('Widget', 100);

        $this->assertTrue($emptyOrder->isEmpty());
        $this->assertFalse($filledOrder->isEmpty());

        $emptyOrder->confirm();
        $this->assertSame('confirmed', $emptyOrder->getStatus());
        $this->assertTrue($emptyOrder->getStatus() === 'confirmed');
    }

    public function testNullAssertions(): void
    {
        $order = new Order('alice@example.com');

        $this->assertNull($order->getNote());
        $this->assertNotNull($order->getId());
        $this->assertNotNull($order->getStatus());
    }

    // ═══════════════════════════════════════════════════════════
    // Counts and emptiness
    // ═══════════════════════════════════════════════════════════

    public function testCountAssertions(): void
    {
        $order = new Order('alice@example.com');

        // Empty order
        $this->assertCount(0, $order->getItems());
        $this->assertEmpty($order->getItems());

        // After adding items
        $order->addItem('Widget Pro',  29999, 2);
        $order->addItem('Widget Lite', 14999, 1);

        $this->assertCount(2, $order->getItems());
        $this->assertNotEmpty($order->getItems());
        $this->assertSame(2, $order->itemCount());
    }

    // ═══════════════════════════════════════════════════════════
    // Type assertions
    // ═══════════════════════════════════════════════════════════

    public function testTypeAssertions(): void
    {
        $order = new Order('alice@example.com');
        $order->addItem('Widget', 29999, 3);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertIsString($order->getId());
        $this->assertIsString($order->getStatus());
        $this->assertIsArray($order->getItems());
        $this->assertIsInt($order->totalCents());
        $this->assertIsBool($order->isEmpty());
    }

    // ═══════════════════════════════════════════════════════════
    // String assertions
    // ═══════════════════════════════════════════════════════════

    public function testStringAssertions(): void
    {
        $order = new Order('alice@example.com');

        // The ID starts with 'ORD-'
        $this->assertStringStartsWith('ORD-', $order->getId());

        // The customer email contains '@'
        $this->assertStringContainsString('@', $order->customerEmail);

        // The email ends with '.com'
        $this->assertStringEndsWith('.com', $order->customerEmail);

        // The ID matches the generated format: ORD- followed by 8 uppercase hex chars
        $this->assertMatchesRegularExpression('/^ORD-[A-F0-9]{8}$/', $order->getId());
    }

    // ═══════════════════════════════════════════════════════════
    // Array assertions
    // ═══════════════════════════════════════════════════════════

    public function testArrayAssertions(): void
    {
        $order = new Order('alice@example.com');
        $order->addItem('Widget Pro', 29999, 2);

        $items = $order->getItems();
        $item  = $items[0];

        // Check a key exists
        $this->assertArrayHasKey('name',  $item);
        $this->assertArrayHasKey('price', $item);
        $this->assertArrayHasKey('qty',   $item);

        // Check specific values
        $this->assertSame('Widget Pro', $item['name']);
        $this->assertSame(29999, $item['price']);
        $this->assertSame(2, $item['qty']);

        // Check the array contains a specific value (assertContains for scalar values)
        $names = array_column($items, 'name');
        $this->assertContains('Widget Pro', $names);
    }

    // ═══════════════════════════════════════════════════════════
    // Numeric assertions
    // ═══════════════════════════════════════════════════════════

    public function testNumericAssertions(): void
    {
        $order = new Order('alice@example.com');
        $order->addItem('Widget Pro',  29999, 2);  // 59998
        $order->addItem('Widget Lite', 14999, 1);  // 14999
        // Total: 74997 cents = R749.97

        $total = $order->totalCents();

        $this->assertGreaterThan(0, $total);
        $this->assertGreaterThanOrEqual(74997, $total);
        $this->assertLessThan(100000, $total);
        $this->assertSame(74997, $total);
    }

    // ═══════════════════════════════════════════════════════════
    // Combining assertions to verify one behaviour
    // ═══════════════════════════════════════════════════════════

    /**
     * It is fine to have several assertions in one test when they ALL
     * verify the SAME behaviour from different angles.
     *
     * Here, all three assertions verify "confirm() transitions to confirmed state":
     */
    public function testConfirmTransitionsStatusToConfirmed(): void
    {
        $order = new Order('alice@example.com');
        $this->assertSame('pending', $order->getStatus());  // pre-condition

        $order->confirm();

        $this->assertSame('confirmed', $order->getStatus());  // post-condition
        $this->assertNotSame('pending', $order->getStatus()); // not the old state
    }

    // ═══════════════════════════════════════════════════════════
    // The assertSame trap with objects
    // ═══════════════════════════════════════════════════════════

    /**
     * assertSame on objects checks IDENTITY (same instance in memory).
     * assertEquals on objects checks EQUALITY (same property values).
     * Use whichever matches your intent.
     */
    public function testObjectIdentityVsEquality(): void
    {
        $order1 = new Order('alice@example.com');
        $order2 = new Order('alice@example.com');

        // Different objects, same email — NOT the same instance
        $this->assertNotSame($order1, $order2);

        // Same reference
        $order3 = $order1;
        $this->assertSame($order1, $order3);
    }
}