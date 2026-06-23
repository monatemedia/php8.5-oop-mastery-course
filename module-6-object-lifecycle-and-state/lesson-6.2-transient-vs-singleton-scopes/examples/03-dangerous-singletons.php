<?php
declare(strict_types=1);

/**
 * Example 03 — Dangerous Singletons
 * ------------------------------------
 * Run via PHPUnit:
 *   ./vendor/bin/phpunit module-6-object-lifecycle-and-state/lesson-6.2-transient-vs-singleton-scopes/examples/03-dangerous-singletons.php
 *
 * This file works through two service classes that are dangerous as singletons
 * and shows exactly how transient scope eliminates the danger — without changing
 * the service class code at all.
 *
 * The key lesson: the scope decision belongs in the CONTAINER DEFINITION,
 * not in the service class. You do not need to rewrite ShoppingCart to make
 * it safe. You register it as transient and the container handles the rest.
 *
 * Structure:
 *   PART A — ShoppingCart: the classic stateful-singleton mistake
 *   PART B — The same container, switching to transient scope — bug disappears
 *   PART C — AuthContext: the authenticated-user leak
 *   PART D — The same auth context, switching to transient — bug disappears
 *   PART E — Recognising the pattern: how to audit your container definitions
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// PART A — ShoppingCart
// ─────────────────────────────────────────────────────────────────────────────

/**
 * A shopping cart for one user's session.
 *
 * Has mutable state ($items) → dangerous as a singleton.
 * The class code is NOT wrong — it is correct for its purpose.
 * The problem is always scope: how long does the instance live?
 */
class ShoppingCart
{
    private array $items = [];

    public function add(string $sku, int $qty, float $price): void
    {
        // In a real cart, you might merge duplicate SKUs.
        // For this example, we just append.
        $this->items[] = ['sku' => $sku, 'qty' => $qty, 'price' => $price];
    }

    public function remove(string $sku): void
    {
        $this->items = array_values(
            array_filter($this->items, fn($item) => $item['sku'] !== $sku)
        );
    }

    public function getItems(): array { return $this->items; }

    public function getItemCount(): int { return count($this->items); }

    public function getTotal(): float
    {
        return array_sum(
            array_map(fn($item) => $item['price'] * $item['qty'], $this->items)
        );
    }

    public function isEmpty(): bool { return empty($this->items); }
}

/**
 * A simplified container that mimics PHP-DI's singleton and transient behaviour.
 * Used here so the example is self-contained without requiring php-di.
 */
class SimpleContainer
{
    private array $definitions = [];
    private array $singletons  = [];

    /**
     * Register a singleton: factory called once, instance reused.
     */
    public function singleton(string $id, callable $factory): void
    {
        $this->definitions[$id] = ['factory' => $factory, 'transient' => false];
    }

    /**
     * Register a transient: factory called every time.
     * This mirrors: factory(fn() => new ClassName()) in PHP-DI.
     */
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
            // Transient: always call the factory
            return ($def['factory'])();
        }

        // Singleton: call factory once, store and reuse
        if (!isset($this->singletons[$id])) {
            $this->singletons[$id] = ($def['factory'])();
        }
        return $this->singletons[$id];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PART B — ShoppingCart scope comparison
// ─────────────────────────────────────────────────────────────────────────────

class ShoppingCartScopeTest extends TestCase
{
    /**
     * BUG: ShoppingCart as SINGLETON — User B sees User A's items.
     *
     * In a persistent worker (FrankenPHP, Swoole), the same ShoppingCart
     * instance handles every user's request. User A adds items, the request
     * ends, User B's request begins — and User B's cart already contains
     * User A's items.
     *
     * In an e-commerce context, User B can check out with User A's items
     * at User A's prices. A serious data corruption and potential fraud vector.
     */
    public function testShoppingCartAsSingletonCrossContaminatesUsers(): void
    {
        $container = new SimpleContainer();

        // ❌ WRONG: ShoppingCart registered as singleton
        $container->singleton(ShoppingCart::class, fn() => new ShoppingCart());

        // ── User A's request ────────────────────────────────────────────────
        $cartA = $container->get(ShoppingCart::class);
        $cartA->add('LAPTOP-001', 1, 999.99);
        $cartA->add('MOUSE-007',  1, 29.99);

        $this->assertSame(2, $cartA->getItemCount(), 'User A: 2 items in cart');
        $this->assertSame(1029.98, $cartA->getTotal());

        // Worker handles User B's request — cart is NOT recreated

        // ── User B's request ────────────────────────────────────────────────
        $cartB = $container->get(ShoppingCart::class); // same instance!
        $cartB->add('KEYBOARD-003', 1, 49.99);

        // BUG: User B's cart has 3 items — User A's laptop and mouse leaked in
        $this->assertSame(3, $cartB->getItemCount(),
            'BUG: User B sees 3 items — 2 from User A, 1 of their own'
        );
        $this->assertSame(1079.97, $cartB->getTotal(),
            'BUG: User B\'s total includes User A\'s laptop and mouse'
        );

        // Confirm it is the same object
        $this->assertSame($cartA, $cartB, 'Proof: cartA and cartB are the same instance');

        $skus = array_column($cartB->getItems(), 'sku');
        $this->assertContains('LAPTOP-001', $skus,
            'BUG: User A\'s laptop appears in User B\'s cart'
        );
    }

    /**
     * FIX: ShoppingCart as TRANSIENT — each resolution gets a fresh cart.
     *
     * The service class (ShoppingCart) is IDENTICAL to the broken version above.
     * The ONLY change is the container registration: singleton → transient.
     * The bug disappears.
     *
     * In PHP-DI, this change looks like:
     *   BEFORE: autowire(ShoppingCart::class)     // singleton (default)
     *   AFTER:  factory(fn() => new ShoppingCart()) // transient
     */
    public function testShoppingCartAsTransientIsolatesUsers(): void
    {
        $container = new SimpleContainer();

        // ✅ CORRECT: ShoppingCart registered as transient
        $container->transient(ShoppingCart::class, fn() => new ShoppingCart());

        // ── User A's request ────────────────────────────────────────────────
        $cartA = $container->get(ShoppingCart::class);
        $cartA->add('LAPTOP-001', 1, 999.99);
        $cartA->add('MOUSE-007',  1, 29.99);

        $this->assertSame(2, $cartA->getItemCount(), 'User A: 2 items');

        // ── User B's request ────────────────────────────────────────────────
        $cartB = $container->get(ShoppingCart::class); // FRESH instance!
        $cartB->add('KEYBOARD-003', 1, 49.99);

        // FIXED: User B's cart has exactly 1 item — their own
        $this->assertSame(1, $cartB->getItemCount(),
            'FIXED: User B has only 1 item — their own'
        );
        $this->assertSame(49.99, $cartB->getTotal(),
            'FIXED: User B\'s total is only their keyboard'
        );

        // Confirm they are different objects
        $this->assertNotSame($cartA, $cartB, 'cartA and cartB are different instances');

        // User A's cart is unaffected by User B's additions
        $this->assertSame(2, $cartA->getItemCount(), 'User A\'s cart still has 2 items');
    }

    /**
     * With transient scope, every resolution returns a completely empty cart.
     * This is the invariant that transient scope guarantees.
     */
    public function testTransientCartAlwaysStartsEmpty(): void
    {
        $container = new SimpleContainer();
        $container->transient(ShoppingCart::class, fn() => new ShoppingCart());

        // Simulate 5 user sessions
        for ($i = 1; $i <= 5; $i++) {
            $cart = $container->get(ShoppingCart::class);
            // Add items to simulate this user's session
            $cart->add("ITEM-{$i}", 1, (float) ($i * 10));

            // Each cart starts empty — only this user's items
            $this->assertSame(1, $cart->getItemCount(),
                "User {$i}: cart has exactly 1 item (their own)"
            );
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PART C — AuthContext: the authenticated-user leak
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Holds the authenticated user for the current request.
 * Set once at the start of each request via authenticate().
 *
 * Has mutable state ($userId) → dangerous as a singleton.
 */
class AuthContext
{
    private ?string $userId = null;
    private array   $roles  = [];

    public function authenticate(string $userId, array $roles = []): void
    {
        $this->userId = $userId;
        $this->roles  = $roles;
    }

    public function logout(): void
    {
        $this->userId = null;
        $this->roles  = [];
    }

    public function getUserId(): ?string     { return $this->userId; }
    public function getRoles(): array        { return $this->roles; }
    public function isAuthenticated(): bool  { return $this->userId !== null; }
    public function hasRole(string $role): bool { return in_array($role, $this->roles, true); }
}

// ─────────────────────────────────────────────────────────────────────────────
// PART D — AuthContext scope comparison
// ─────────────────────────────────────────────────────────────────────────────

class AuthContextScopeTest extends TestCase
{
    /**
     * BUG: AuthContext as SINGLETON — Alice's identity leaks to the next request.
     *
     * Scenario: Alice (admin) logs in and does her work. Her session ends.
     * The next request is from an anonymous user (health-check, API probe,
     * or simply a user who hasn't logged in yet). The system sees them as Alice.
     *
     * This is a direct privilege escalation vulnerability.
     */
    public function testAuthContextAsSingletonLeaksIdentityBetweenRequests(): void
    {
        $container = new SimpleContainer();

        // ❌ WRONG: AuthContext registered as singleton
        $container->singleton(AuthContext::class, fn() => new AuthContext());

        // ── Request 1: Alice (admin) logs in ────────────────────────────────
        $authR1 = $container->get(AuthContext::class);
        $authR1->authenticate('alice', ['user', 'admin']);

        $this->assertSame('alice', $authR1->getUserId());
        $this->assertTrue($authR1->hasRole('admin'));

        // Alice's request completes. No explicit logout call.
        // (In practice, Alice's browser closes, the session token expires server-side,
        // but the PHP object on the worker is never reset.)

        // ── Request 2: anonymous user or different user ──────────────────────
        $authR2 = $container->get(AuthContext::class); // same instance!

        // BUG: Alice is still "authenticated"
        $this->assertTrue($authR2->isAuthenticated(),
            'BUG: Request 2 sees an authenticated user — Alice\'s identity leaked'
        );
        $this->assertSame('alice', $authR2->getUserId(),
            'BUG: Request 2 identity is Alice'
        );
        $this->assertTrue($authR2->hasRole('admin'),
            'BUG: Request 2 has admin role — privilege escalation'
        );
    }

    /**
     * FIX: AuthContext as TRANSIENT — each request gets a fresh (unauthenticated) context.
     *
     * The PHP-DI change:
     *   BEFORE: autowire(AuthContext::class)          // singleton
     *   AFTER:  factory(fn() => new AuthContext())    // transient
     */
    public function testAuthContextAsTransientIsolatesIdentityBetweenRequests(): void
    {
        $container = new SimpleContainer();

        // ✅ CORRECT: AuthContext registered as transient
        $container->transient(AuthContext::class, fn() => new AuthContext());

        // ── Request 1: Alice logs in ─────────────────────────────────────────
        $authR1 = $container->get(AuthContext::class);
        $authR1->authenticate('alice', ['user', 'admin']);

        $this->assertSame('alice', $authR1->getUserId());
        $this->assertTrue($authR1->hasRole('admin'));

        // ── Request 2: anonymous ─────────────────────────────────────────────
        $authR2 = $container->get(AuthContext::class); // FRESH instance

        // FIXED: Request 2 is unauthenticated
        $this->assertFalse($authR2->isAuthenticated(),
            'FIXED: Request 2 is unauthenticated — no leaked identity'
        );
        $this->assertNull($authR2->getUserId(),
            'FIXED: Request 2 has no user ID'
        );
        $this->assertFalse($authR2->hasRole('admin'),
            'FIXED: Request 2 has no admin role'
        );

        // Request 1's context is unaffected
        $this->assertSame('alice', $authR1->getUserId(),
            'Request 1\'s auth context is still valid and unaffected'
        );
    }

    /**
     * Each transient resolution starts in the correct initial state:
     * userId = null, roles = [], isAuthenticated = false.
     */
    public function testTransientAuthContextAlwaysStartsUnauthenticated(): void
    {
        $container = new SimpleContainer();
        $container->transient(AuthContext::class, fn() => new AuthContext());

        // Simulate 3 users — some authenticated, some not
        $auth1 = $container->get(AuthContext::class);
        $auth1->authenticate('alice', ['user']);

        $auth2 = $container->get(AuthContext::class); // fresh — unauthenticated

        $auth3 = $container->get(AuthContext::class);
        $auth3->authenticate('charlie', ['user', 'moderator']);

        $auth4 = $container->get(AuthContext::class); // fresh — unauthenticated

        $this->assertTrue($auth1->isAuthenticated());
        $this->assertFalse($auth2->isAuthenticated(), 'auth2: fresh, unauthenticated');
        $this->assertTrue($auth3->isAuthenticated());
        $this->assertFalse($auth4->isAuthenticated(), 'auth4: fresh, unauthenticated');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PART E — Auditing your container: the pattern to spot
//
// When reviewing a PHP-DI definitions file, look for these patterns and ask
// whether the class has mutable state:
//
// ❌ Potentially dangerous (if class has mutable state):
//   SomeService::class => autowire(SomeService::class)
//   SomeService::class => create(SomeService::class)
//
// ✅ Safe for mutable-state classes:
//   SomeService::class => factory(fn() => new SomeService())
//
// ✅ Safe for stateless classes (either scope is fine, but singleton is efficient):
//   SomeService::class => autowire(SomeService::class)
//
// The audit process:
//   1. Find all autowire() and create() definitions
//   2. Open the class — look for private properties with public setters/appenders
//   3. If any mutable state is found, switch to factory() (transient)
//   4. Write a test that simulates worker reuse to confirm the fix
// ─────────────────────────────────────────────────────────────────────────────

class ContainerAuditPatternTest extends TestCase
{
    /**
     * Demonstrates the audit pattern: two registrations of the same class,
     * one singleton (wrong for stateful class), one transient (correct).
     * Shows exactly which container call to change and what the effect is.
     */
    public function testAuditPatternShowsExactContainerLineToChange(): void
    {
        $wrongContainer   = new SimpleContainer();
        $correctContainer = new SimpleContainer();

        // This is what you see in the definitions file you are reviewing:
        $wrongContainer->singleton(  ShoppingCart::class, fn() => new ShoppingCart()); // ❌
        $correctContainer->transient(ShoppingCart::class, fn() => new ShoppingCart()); // ✅

        // Wrong container — same instance
        $a = $wrongContainer->get(ShoppingCart::class);
        $b = $wrongContainer->get(ShoppingCart::class);
        $this->assertSame($a, $b, 'Wrong: same instance (singleton)');

        // Correct container — different instances
        $c = $correctContainer->get(ShoppingCart::class);
        $d = $correctContainer->get(ShoppingCart::class);
        $this->assertNotSame($c, $d, 'Correct: different instances (transient)');
    }
}