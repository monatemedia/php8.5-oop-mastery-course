<?php
declare(strict_types=1);

/**
 * Example 02 — Transient Factories
 * -----------------------------------
 * Run via PHPUnit:
 *   ./vendor/bin/phpunit module-6-object-lifecycle-and-state/lesson-6.5-factory-definitions/examples/02-transient-factories.php
 *
 * This file demonstrates transient scope via factory() for two canonical use cases:
 *
 *   USE CASE A — ShoppingCart
 *     Stateful per-session object. Every resolution must return a fresh instance.
 *     The factory is factory(fn() => new ShoppingCart()) — zero dependencies.
 *
 *   USE CASE B — RequestContext
 *     Constructed from runtime data (incoming request + auth service).
 *     Immutable once created. Must be fresh per request.
 *     The factory takes typed dependencies from the container and reads runtime data.
 *
 * This example also shows:
 *   - How to register a transient alongside a singleton in the same container
 *   - The assertNotSame verification pattern for transient scope
 *   - Why transient + stateful is different from stateless (Lesson 6.4):
 *     the class still has mutable state — we just guarantee a fresh instance
 *
 * Structure:
 *   PART A — Classes used in this example
 *   PART B — Container with transient and singleton bindings
 *   PART C — Tests
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// PART A — Classes
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Shopping cart: stateful, per-session.
 * Must be transient — a singleton would share state across all users.
 */
class ShoppingCart
{
    private array $items = [];

    public function add(string $sku, int $qty, float $price): void
    {
        $this->items[] = ['sku' => $sku, 'qty' => $qty, 'price' => $price];
    }

    public function getItems(): array { return $this->items; }
    public function count(): int      { return count($this->items); }
    public function isEmpty(): bool   { return empty($this->items); }

    public function total(): float
    {
        return array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $this->items));
    }
}

/**
 * Auth service: stateless singleton.
 * Resolves a token to a user ID. Does not store any state.
 */
class AuthService
{
    public function resolveToken(string $token): ?string
    {
        // Simplified: base64-decode the token as the user ID
        $decoded = base64_decode($token, strict: true);
        return $decoded ?: null;
    }
}

/**
 * Immutable request context: per-request, transient.
 * Carries the authenticated user ID and request metadata.
 */
final class RequestContext
{
    private function __construct(
        public readonly ?string $userId,
        public readonly string  $requestId,
        public readonly string  $path,
        public readonly string  $method,
    ) {}

    public static function authenticated(string $userId, string $requestId, string $path, string $method = 'GET'): self
    {
        return new self($userId, $requestId, $path, $method);
    }

    public static function anonymous(string $requestId, string $path, string $method = 'GET'): self
    {
        return new self(null, $requestId, $path, $method);
    }

    public function isAuthenticated(): bool { return $this->userId !== null; }

    public function requireAuthentication(): void
    {
        if (!$this->isAuthenticated()) {
            throw new \RuntimeException('Authentication required');
        }
    }
}

/**
 * Simulates an incoming HTTP request for test purposes.
 * In production this would be a PSR-7 ServerRequestInterface.
 */
final class SimulatedRequest
{
    public function __construct(
        public readonly string $path,
        public readonly string $method,
        public readonly string $authToken,
    ) {}
}

// ─────────────────────────────────────────────────────────────────────────────
// PART B — Container implementation
//
// Extends Example 01's SimulatedContainer to explicitly support transient
// scope — factory(fn, transient: true) means no caching between calls.
// ─────────────────────────────────────────────────────────────────────────────

class Container
{
    private array $definitions = [];
    private array $singletons  = [];

    /**
     * Register a singleton: factory called once, instance reused.
     * Equivalent to PHP-DI's autowire() or create().
     */
    public function singleton(string $id, callable $factory): void
    {
        $this->definitions[$id] = ['factory' => $factory, 'transient' => false];
    }

    /**
     * Register a transient: factory called on every get().
     * Equivalent to PHP-DI's factory(fn() => new ClassName()).
     */
    public function transient(string $id, callable $factory): void
    {
        $this->definitions[$id] = ['factory' => $factory, 'transient' => true];
    }

    public function get(string $id): object
    {
        if (!isset($this->definitions[$id])) {
            throw new \RuntimeException("No definition for: {$id}");
        }

        $def = $this->definitions[$id];

        if (!$def['transient'] && isset($this->singletons[$id])) {
            return $this->singletons[$id];
        }

        $instance = $this->invoke($def['factory']);

        if (!$def['transient']) {
            $this->singletons[$id] = $instance;
        }

        return $instance;
    }

    private function invoke(callable $factory): object
    {
        $rf   = new \ReflectionFunction(
            $factory instanceof \Closure ? $factory : \Closure::fromCallable($factory)
        );
        $args = [];
        foreach ($rf->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $args[] = $this->get($type->getName());
            }
        }
        return $factory(...$args);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PART C — Tests
// ─────────────────────────────────────────────────────────────────────────────

class TransientFactoriesTest extends TestCase
{
    // ══════════════════════════════════════════════════════════════════════════
    // USE CASE A — ShoppingCart
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * ShoppingCart registered as transient: every get() returns a new instance.
     * The canonical proof: assertNotSame($a, $b).
     */
    public function testShoppingCartTransientReturnsDifferentInstanceEachTime(): void
    {
        $container = new Container();

        // PHP-DI equivalent: factory(fn() => new ShoppingCart())
        $container->transient(ShoppingCart::class, fn() => new ShoppingCart());

        $cartA = $container->get(ShoppingCart::class);
        $cartB = $container->get(ShoppingCart::class);
        $cartC = $container->get(ShoppingCart::class);

        $this->assertNotSame($cartA, $cartB, 'Different instances: A vs B');
        $this->assertNotSame($cartB, $cartC, 'Different instances: B vs C');
        $this->assertNotSame($cartA, $cartC, 'Different instances: A vs C');
    }

    /**
     * Each fresh cart starts empty — the invariant transient scope guarantees.
     */
    public function testTransientCartAlwaysStartsEmpty(): void
    {
        $container = new Container();
        $container->transient(ShoppingCart::class, fn() => new ShoppingCart());

        // Simulate 5 users each getting a cart
        for ($i = 1; $i <= 5; $i++) {
            $cart = $container->get(ShoppingCart::class);
            $this->assertTrue($cart->isEmpty(), "Cart {$i} starts empty");
            $this->assertSame(0, $cart->count(), "Cart {$i} has 0 items");
        }
    }

    /**
     * Items added to one cart do not appear in another.
     * This is the cross-user contamination bug from Lesson 6.3 — confirmed fixed.
     */
    public function testTransientCartPreventsUserContamination(): void
    {
        $container = new Container();
        $container->transient(ShoppingCart::class, fn() => new ShoppingCart());

        // User A: adds two items to their cart
        $cartA = $container->get(ShoppingCart::class);
        $cartA->add('LAPTOP-001', 1, 999.99);
        $cartA->add('MOUSE-007',  1, 29.99);
        $this->assertSame(2, $cartA->count());

        // User B: gets a fresh cart — should see zero items
        $cartB = $container->get(ShoppingCart::class);
        $cartB->add('KEYBOARD-003', 1, 49.99);

        $this->assertSame(1, $cartB->count(),
            'User B has 1 item — no contamination from User A'
        );
        $this->assertSame(49.99, $cartB->total(),
            'User B total: £49.99 (their item only)'
        );

        $bSkus = array_column($cartB->getItems(), 'sku');
        $this->assertNotContains('LAPTOP-001', $bSkus,
            "User A's laptop is not in User B's cart"
        );

        // User A's cart is unaffected
        $this->assertSame(2,       $cartA->count());
        $this->assertSame(1029.98, $cartA->total());
    }

    /**
     * Comparing transient vs singleton scope side by side.
     * The same class, different registrations, different contamination outcomes.
     */
    public function testTransientVsSingletonSideBySide(): void
    {
        $containerSingleton = new Container();
        $containerTransient = new Container();

        $containerSingleton->singleton( ShoppingCart::class, fn() => new ShoppingCart());
        $containerTransient->transient( ShoppingCart::class, fn() => new ShoppingCart());

        // Singleton: both resolutions return the same cart
        $singletonCart1 = $containerSingleton->get(ShoppingCart::class);
        $singletonCart1->add('ITEM-A', 1, 10.00);

        $singletonCart2 = $containerSingleton->get(ShoppingCart::class);
        $singletonCart2->add('ITEM-B', 1, 20.00);

        // Singleton: cart2 sees ITEM-A added by cart1
        $this->assertSame(2, $singletonCart2->count(),
            'Singleton: cart2 sees 2 items (ITEM-A from cart1 leaked in)'
        );
        $this->assertSame($singletonCart1, $singletonCart2);

        // Transient: each resolution is clean
        $transientCart1 = $containerTransient->get(ShoppingCart::class);
        $transientCart1->add('ITEM-A', 1, 10.00);

        $transientCart2 = $containerTransient->get(ShoppingCart::class);
        $transientCart2->add('ITEM-B', 1, 20.00);

        // Transient: cart2 sees only ITEM-B
        $this->assertSame(1, $transientCart2->count(),
            'Transient: cart2 sees only 1 item (its own ITEM-B)'
        );
        $this->assertNotSame($transientCart1, $transientCart2);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // USE CASE B — RequestContext
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * RequestContext is constructed from runtime data (the incoming request
     * + the auth service). The factory reads the request and calls AuthService
     * to resolve the user. Produces an immutable value object.
     *
     * The auth service is a singleton (stateless); the context is transient.
     * This is the correct layering: stateless singleton → transient value object.
     */
    public function testRequestContextFactoryCreatesCorrectContextFromRequest(): void
    {
        $container = new Container();

        // AuthService is a stateless singleton
        $container->singleton(AuthService::class, fn() => new AuthService());

        // RequestContext is transient — built fresh from the current request
        // In production, the factory would read from a PSR-7 request bound in the container.
        // Here we pass the simulated request via closure capture.
        $aliceToken = base64_encode('user-alice');
        $request    = new SimulatedRequest('/orders', 'GET', $aliceToken);

        $container->transient(
            RequestContext::class,
            function (AuthService $auth) use ($request): RequestContext {
                $userId = $auth->resolveToken($request->authToken);
                return $userId
                    ? RequestContext::authenticated($userId, uniqid('req-'), $request->path, $request->method)
                    : RequestContext::anonymous(uniqid('req-'), $request->path, $request->method);
            }
        );

        $ctx = $container->get(RequestContext::class);

        $this->assertTrue($ctx->isAuthenticated());
        $this->assertSame('user-alice', $ctx->userId);
        $this->assertSame('/orders', $ctx->path);
        $this->assertSame('GET', $ctx->method);
    }

    /**
     * An anonymous request (no token) produces an unauthenticated context.
     */
    public function testRequestContextFactoryProducesAnonymousContextForNoToken(): void
    {
        $container = new Container();
        $container->singleton(AuthService::class, fn() => new AuthService());

        $request = new SimulatedRequest('/health', 'GET', ''); // no token

        $container->transient(
            RequestContext::class,
            function (AuthService $auth) use ($request): RequestContext {
                $userId = $auth->resolveToken($request->authToken);
                return $userId
                    ? RequestContext::authenticated($userId, uniqid('req-'), $request->path)
                    : RequestContext::anonymous(uniqid('req-'), $request->path);
            }
        );

        $ctx = $container->get(RequestContext::class);

        $this->assertFalse($ctx->isAuthenticated());
        $this->assertNull($ctx->userId);
    }

    /**
     * Each RequestContext resolution produces a different object (transient).
     * The requestId is unique per resolution — fresh objects, fresh IDs.
     */
    public function testTransientRequestContextHasUniqueRequestIdPerResolution(): void
    {
        $container = new Container();
        $container->singleton(AuthService::class, fn() => new AuthService());

        $container->transient(
            RequestContext::class,
            fn(): RequestContext => RequestContext::anonymous(uniqid('req-'), '/path')
        );

        $ctx1 = $container->get(RequestContext::class);
        $ctx2 = $container->get(RequestContext::class);

        $this->assertNotSame($ctx1, $ctx2, 'Different RequestContext objects');
        $this->assertNotSame($ctx1->requestId, $ctx2->requestId,
            'Each context has a unique requestId'
        );
    }

    /**
     * The AuthService singleton is shared across RequestContext resolutions —
     * the singleton is constructed once; the context (transient) is constructed
     * for each resolution but uses the same AuthService instance.
     */
    public function testSingletonAuthServiceSharedAcrossTransientContextResolutions(): void
    {
        $container = new Container();

        $authConstructCallCount = 0;

        $container->singleton(AuthService::class, function () use (&$authConstructCallCount): AuthService {
            $authConstructCallCount++;
            return new AuthService();
        });

        $container->transient(
            RequestContext::class,
            fn(AuthService $auth): RequestContext => RequestContext::anonymous(uniqid(), '/')
        );

        // Resolve 5 RequestContexts
        for ($i = 0; $i < 5; $i++) {
            $container->get(RequestContext::class);
        }

        $this->assertSame(1, $authConstructCallCount,
            'AuthService constructed exactly once — reused as singleton across all 5 context resolutions'
        );
    }
}