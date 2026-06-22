<?php
declare(strict_types=1);

/**
 * Example 04 — setUp() and tearDown() Lifecycle
 * -----------------------------------------------
 * Run via PHPUnit:
 *   ./vendor/bin/phpunit module-5-testing-and-tdd/lesson-5.1-phpunit-fundamentals/examples/04-setup-and-teardown.php
 *
 * This example covers:
 *   A. Using setUp() to create fresh objects before every test
 *   B. Why isolation matters — dirty state causes order-dependent failures
 *   C. tearDown() for cleanup
 *   D. setUpBeforeClass() / tearDownAfterClass() for one-time setup
 *   E. Comparing setUp() vs inline creation — when to use each
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

// ─────────────────────────────────────────────────────────────────────────────
// The class under test
// ─────────────────────────────────────────────────────────────────────────────

class ShoppingCart
{
    private array $items = [];

    public function add(string $name, int $priceCents, int $qty = 1): void
    {
        $existing = $this->findItem($name);
        if ($existing !== null) {
            $this->items[$existing]['qty'] += $qty;
        } else {
            $this->items[] = compact('name', 'priceCents', 'qty');
        }
    }

    public function remove(string $name): void
    {
        foreach ($this->items as $i => $item) {
            if ($item['name'] === $name) {
                unset($this->items[$i]);
                $this->items = array_values($this->items);
                return;
            }
        }
    }

    public function clear(): void       { $this->items = []; }
    public function isEmpty(): bool     { return empty($this->items); }
    public function count(): int        { return count($this->items); }
    public function getItems(): array   { return $this->items; }

    public function totalCents(): int
    {
        return array_sum(array_map(fn($i) => $i['priceCents'] * $i['qty'], $this->items));
    }

    private function findItem(string $name): ?int
    {
        foreach ($this->items as $i => $item) {
            if ($item['name'] === $name) return $i;
        }
        return null;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// PART A — setUp() creates fresh objects: the standard pattern
// ─────────────────────────────────────────────────────────────────────────────

/**
 * This is the recommended pattern for most test classes.
 * setUp() runs BEFORE EVERY test method.
 * Every test therefore starts with a fresh, clean ShoppingCart.
 */
class ShoppingCartTest extends TestCase
{
    // Declare the subject as a property
    private ShoppingCart $cart;

    protected function setUp(): void
    {
        // This runs before EVERY test method in this class.
        // A brand-new cart is created for each test — no shared state.
        $this->cart = new ShoppingCart();
    }

    // ── Tests can now use $this->cart without any setup boilerplate ──────────

    public function testNewCartIsEmpty(): void
    {
        $this->assertTrue($this->cart->isEmpty());
        $this->assertSame(0, $this->cart->count());
        $this->assertSame(0, $this->cart->totalCents());
    }

    public function testAddItemIncreasesCount(): void
    {
        $this->cart->add('Widget Pro', 29999);

        $this->assertSame(1, $this->cart->count());
        $this->assertFalse($this->cart->isEmpty());
    }

    public function testAddSameItemTwiceAccumulatesQuantity(): void
    {
        $this->cart->add('Widget Pro', 29999, 1);
        $this->cart->add('Widget Pro', 29999, 2);

        // Should be ONE item entry with qty=3, not two separate entries
        $this->assertSame(1, $this->cart->count());
        $this->assertSame(3, $this->cart->getItems()[0]['qty']);
    }

    public function testTotalIsCorrect(): void
    {
        $this->cart->add('Widget Pro',  29999, 2);  // 59998
        $this->cart->add('Widget Lite', 14999, 1);  // 14999

        $this->assertSame(74997, $this->cart->totalCents());
    }

    public function testRemoveItemDecreasesCount(): void
    {
        $this->cart->add('Widget Pro',  29999);
        $this->cart->add('Widget Lite', 14999);

        $this->cart->remove('Widget Pro');

        $this->assertSame(1, $this->cart->count());
    }

    public function testClearEmptiesCart(): void
    {
        $this->cart->add('Widget Pro',  29999);
        $this->cart->add('Widget Lite', 14999);
        $this->assertFalse($this->cart->isEmpty());

        $this->cart->clear();

        $this->assertTrue($this->cart->isEmpty());
        $this->assertSame(0, $this->cart->totalCents());
    }

    /**
     * KEY POINT: Each of the tests above starts with a FRESH empty cart.
     * Even though testClearEmptiesCart() adds items and then clears, the next
     * test that runs gets its own brand-new cart from setUp().
     *
     * Without setUp(), you would need this boilerplate in every test:
     *   $cart = new ShoppingCart();
     * With setUp(), it is written once.
     */
}


// ─────────────────────────────────────────────────────────────────────────────
// PART B — Why isolation matters: the dirty-state anti-pattern
// ─────────────────────────────────────────────────────────────────────────────

/**
 * This class demonstrates the BUG that occurs when tests share state.
 * The static $cart is shared between all test methods — this is WRONG.
 *
 * If testAddItem() runs before testNewCartIsEmpty(), the cart already
 * has an item, and testNewCartIsEmpty() fails — even though the code is correct.
 * Test failures caused by test ordering are a sign of missing isolation.
 *
 * ⚠️  DO NOT write tests like this. This class exists only to show the problem.
 */
class DirtyStateAntiPatternTest extends TestCase
{
    // ❌ Shared state — all tests see the same cart instance
    private static ShoppingCart $sharedCart;

    public static function setUpBeforeClass(): void
    {
        // setUpBeforeClass runs ONCE before the first test in the class
        self::$sharedCart = new ShoppingCart();
    }

    public function testA_CartStartsEmpty(): void
    {
        // This passes IF this test runs first
        $this->assertTrue(self::$sharedCart->isEmpty());
    }

    public function testB_AddingItemIncreasesCount(): void
    {
        self::$sharedCart->add('Widget', 100);
        $this->assertSame(1, self::$sharedCart->count());
    }

    public function testC_CartIsNoLongerEmpty(): void
    {
        // This depends on testB having run first — ORDER DEPENDENT!
        // If PHPUnit runs this before testB, it fails.
        $this->assertFalse(self::$sharedCart->isEmpty());
    }

    /**
     * To fix: replace the static property with a fresh instance in setUp().
     * Never rely on test execution order.
     */
}


// ─────────────────────────────────────────────────────────────────────────────
// PART C — tearDown() for cleanup
// ─────────────────────────────────────────────────────────────────────────────

/**
 * tearDown() runs AFTER every test method.
 * Most in-memory tests do not need it — PHP cleans up automatically.
 * Use tearDown() for: temp files, open connections, global state.
 */
class TearDownExampleTest extends TestCase
{
    private ShoppingCart $cart;
    private string       $tempFile;

    protected function setUp(): void
    {
        $this->cart     = new ShoppingCart();
        $this->tempFile = sys_get_temp_dir() . '/cart-test-' . uniqid() . '.json';
    }

    protected function tearDown(): void
    {
        // Clean up the temp file after every test — even if the test fails
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testCartCanBeSerializedToFile(): void
    {
        $this->cart->add('Widget Pro', 29999);

        // Write to the temp file
        file_put_contents($this->tempFile, json_encode($this->cart->getItems()));

        // Verify the file was written
        $this->assertFileExists($this->tempFile);

        $loaded = json_decode(file_get_contents($this->tempFile), true);
        $this->assertCount(1, $loaded);
        $this->assertSame('Widget Pro', $loaded[0]['name']);

        // tearDown() will delete $this->tempFile after this test — even if it fails
    }

    public function testEmptyCartSerializesToEmptyArray(): void
    {
        file_put_contents($this->tempFile, json_encode($this->cart->getItems()));

        $loaded = json_decode(file_get_contents($this->tempFile), true);
        $this->assertSame([], $loaded);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// PART D — setUpBeforeClass() / tearDownAfterClass(): use sparingly
// ─────────────────────────────────────────────────────────────────────────────

/**
 * These static methods run ONCE per test class — not once per test method.
 * Useful for expensive shared resources (e.g. a database connection, a test server).
 *
 * Risk: shared state between tests. Only use when setUp() is genuinely too slow
 * and the shared resource is truly read-only across all tests.
 */
class ClassLevelSetupTest extends TestCase
{
    private static int $setUpCount   = 0;
    private static int $tearDownCount = 0;

    private ShoppingCart $cart;

    public static function setUpBeforeClass(): void
    {
        self::$setUpCount++;
        // Runs ONCE before all tests in this class
    }

    public static function tearDownAfterClass(): void
    {
        self::$tearDownCount++;
        // Runs ONCE after all tests in this class
    }

    protected function setUp(): void
    {
        // Still creating a fresh cart for each test — class-level setup
        // is for the expensive shared resource; instance-level is for the subject
        $this->cart = new ShoppingCart();
    }

    public function testSetupBeforeClassRunsOnce(): void
    {
        $this->assertSame(1, self::$setUpCount);
    }

    public function testSetupBeforeClassStillOneAfterSecondTest(): void
    {
        // setUpBeforeClass has still only run once, even though setUp() ran again
        $this->assertSame(1, self::$setUpCount);
    }

    public function testCartIsStillFreshPerTest(): void
    {
        // Each test gets its own cart from setUp() — not shared
        $this->assertTrue($this->cart->isEmpty());
        $this->cart->add('Widget', 100);
        $this->assertSame(1, $this->cart->count());
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// PART E — setUp() vs inline creation: when to choose each
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Rule of thumb:
 *   - If MOST tests use the same setup: put it in setUp()
 *   - If a few tests need different setup: create inline in those tests
 *   - Never mix both for the SAME object in the same class — it is confusing
 */
class SetupChoiceExampleTest extends TestCase
{
    // setUp() for the common case: a standard cart
    private ShoppingCart $cart;

    protected function setUp(): void
    {
        $this->cart = new ShoppingCart();
        // Pre-load common items that most tests need
        $this->cart->add('Widget Pro', 29999);
    }

    public function testTotalWithOneItem(): void
    {
        // Uses the pre-loaded cart from setUp()
        $this->assertSame(29999, $this->cart->totalCents());
    }

    public function testAddingSecondItem(): void
    {
        // Adds to the setUp() cart — that is intentional and clear
        $this->cart->add('Widget Lite', 14999);
        $this->assertSame(44998, $this->cart->totalCents());
    }

    public function testEmptyCartBehaviour(): void
    {
        // This test needs a different state — create inline
        $emptyCart = new ShoppingCart();  // ← inline, not $this->cart

        $this->assertTrue($emptyCart->isEmpty());
        $this->assertSame(0, $emptyCart->totalCents());
    }
}