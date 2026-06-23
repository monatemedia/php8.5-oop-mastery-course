<?php
declare(strict_types=1);

/**
 * Example 02 — Authentication State on a Singleton
 * --------------------------------------------------
 * Run via PHPUnit:
 *   ./vendor/bin/phpunit module-6-object-lifecycle-and-state/lesson-6.3-danger-of-stateful-services/examples/02-auth-state-leak.php
 *
 * Anti-pattern #2: a service stores the "current user" (or "current tenant",
 * "current session") as a nullable property that is set by a login/authenticate
 * method. As a singleton in a persistent worker, the previous request's user
 * is still present at the start of the next request.
 *
 * This file examines the pattern through an escalating lens:
 *
 *   SCENARIO A — Simple identity leak: Alice's name is shown to Bob
 *   SCENARIO B — Permission leak: Alice's admin role is granted to Bob's request
 *   SCENARIO C — Multi-tenant data leak: Tenant A's data is served to Tenant B
 *   SCENARIO D — The race condition variant: concurrent requests interleave
 *
 * Structure:
 *   PART A — User and AuthService classes
 *   PART B — Scenario A: identity leak
 *   PART C — Scenario B: permission leak
 *   PART D — Scenario C: multi-tenant data leak
 *   PART E — Scenario D: the timing/ordering variant
 *   PART F — What a correct test looks like (proving the bug, not working around it)
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// PART A — Domain classes
// ─────────────────────────────────────────────────────────────────────────────

final class User
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $tenantId,
        /** @var string[] */
        public readonly array  $roles = ['user'],
    ) {}

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }
}

/**
 * ANTI-PATTERN: stores the currently authenticated user as instance state.
 *
 * Identifying markers:
 *   private ?User $currentUser = null    ← nullable, starts null
 *   public function login(User $user)    ← public setter
 *   public function getUser(): ?User     ← public reader of that property
 */
class AuthService
{
    private ?User $currentUser = null;

    public function login(User $user): void
    {
        $this->currentUser = $user;
    }

    public function logout(): void
    {
        $this->currentUser = null;
    }

    public function getUser(): ?User
    {
        return $this->currentUser;
    }

    public function isAuthenticated(): bool
    {
        return $this->currentUser !== null;
    }

    public function requireRole(string $role): void
    {
        if ($this->currentUser === null) {
            throw new \RuntimeException('Not authenticated');
        }
        if (!$this->currentUser->hasRole($role)) {
            throw new \RuntimeException("Insufficient permissions — requires role: {$role}");
        }
    }
}

/**
 * A service that uses AuthService to enforce access control.
 * Its security depends entirely on AuthService returning the correct current user.
 */
class AdminDashboardService
{
    public function __construct(private readonly AuthService $auth) {}

    public function getAdminData(): array
    {
        $this->auth->requireRole('admin');

        return [
            'message'      => 'Sensitive admin data',
            'accessedBy'   => $this->auth->getUser()?->name,
            'accessedById' => $this->auth->getUser()?->id,
        ];
    }
}

/**
 * A service that uses AuthService for multi-tenant data isolation.
 */
class TenantDataService
{
    // In-memory store keyed by tenantId — simulates a database
    private array $data = [
        'tenant-acme'   => ['revenue' => 150000, 'users' => 42],
        'tenant-globex' => ['revenue' => 89000,  'users' => 17],
        'tenant-initec' => ['revenue' => 210000, 'users' => 63],
    ];

    public function __construct(private readonly AuthService $auth) {}

    public function getData(): array
    {
        $user = $this->auth->getUser();
        if ($user === null) {
            throw new \RuntimeException('Not authenticated');
        }

        return $this->data[$user->tenantId] ?? [];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PART B — Scenario A: identity leak
// ─────────────────────────────────────────────────────────────────────────────

class IdentityLeakTest extends TestCase
{
    /**
     * BUG DEMONSTRATION: Alice logs in during request 1. Request 2 arrives
     * from an unauthenticated user. getUser() still returns Alice.
     *
     * In production: an unauthenticated request (health-check, preflight, probe)
     * is treated as Alice. Any code path that calls getUser() without first
     * calling login() sees Alice — possibly triggering personalised responses
     * for an anonymous caller.
     */
    public function testPreviousUserIdentityLeaksToNextRequest(): void
    {
        $auth = new AuthService(); // singleton

        // Request 1: Alice logs in
        $alice = new User('u-001', 'Alice', 'tenant-acme', ['user', 'admin']);
        $auth->login($alice);

        $this->assertSame('Alice', $auth->getUser()?->name);
        $this->assertTrue($auth->isAuthenticated());

        // Request 2: no login call — anonymous request
        // (Alice's session ended; new request has no auth headers)
        // getUser() SHOULD return null

        // BUG: returns Alice
        $this->assertSame('Alice', $auth->getUser()?->name,
            'BUG: Request 2 identity is Alice — leaked from request 1'
        );
        $this->assertTrue($auth->isAuthenticated(),
            'BUG: isAuthenticated() is true for an anonymous request'
        );
    }

    /**
     * Documents correct behaviour: a fresh instance always starts unauthenticated.
     */
    public function testFreshAuthServiceStartsUnauthenticated(): void
    {
        $auth = new AuthService();

        $this->assertNull($auth->getUser(), 'Fresh service: no user');
        $this->assertFalse($auth->isAuthenticated(), 'Fresh service: not authenticated');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PART C — Scenario B: permission leak
// ─────────────────────────────────────────────────────────────────────────────

class PermissionLeakTest extends TestCase
{
    /**
     * BUG DEMONSTRATION: privilege escalation via leaked auth state.
     *
     * Alice is an admin. Her request succeeds (correct).
     * Bob is a regular user. His request SHOULD fail the admin check.
     * But if the auth singleton still holds Alice's identity, Bob's request
     * ALSO passes the admin check — Alice's privileges are granted to Bob.
     *
     * This is a critical security vulnerability.
     */
    public function testAdminPrivilegesLeakToNonAdminRequest(): void
    {
        $auth      = new AuthService();   // singleton
        $dashboard = new AdminDashboardService($auth);

        // Request 1: Alice (admin) accesses the dashboard — correct
        $alice = new User('u-001', 'Alice', 'tenant-acme', ['user', 'admin']);
        $auth->login($alice);

        $data = $dashboard->getAdminData();
        $this->assertSame('Alice', $data['accessedBy'], 'Request 1: Alice accessed correctly');

        // Request 2: Bob (regular user) — login IS called, correctly
        // but what if login is NOT called? (e.g. an API endpoint that forgot the middleware)
        // Bob should be denied.

        // Simulate: Bob's request starts, but the auth middleware was skipped
        // (a bug in the middleware pipeline — happens in real systems)
        // No auth->login() call.

        // BUG: Alice's identity is still on the singleton — Bob "inherits" her admin role
        $this->assertDoesNotThrow(
            function () use ($dashboard): void {
                $data = $dashboard->getAdminData();
                // This should throw "Not authenticated" or "Insufficient permissions"
                // Instead, it succeeds and returns Alice's access record
            },
            'BUG: Bob\'s request passes the admin check due to Alice\'s leaked auth state'
        );

        $this->assertSame('u-001', $auth->getUser()?->id,
            'BUG: Auth service still holds Alice\'s user ID (u-001) on Bob\'s request'
        );
    }

    /**
     * Helper for assertDoesNotThrow pattern (avoiding exception assertions here).
     */
    private function assertDoesNotThrow(callable $fn, string $message = ''): void
    {
        try {
            $fn();
            $this->assertTrue(true);
        } catch (\Throwable $e) {
            $this->fail($message ?: "Expected no exception but got: " . $e->getMessage());
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PART D — Scenario C: multi-tenant data leak
// ─────────────────────────────────────────────────────────────────────────────

class MultiTenantLeakTest extends TestCase
{
    /**
     * BUG DEMONSTRATION: Tenant Acme's financial data is served to Tenant Globex.
     *
     * This is the most serious real-world consequence of auth state on a singleton.
     * In a SaaS product, this is a GDPR/data-protection incident — one tenant can
     * see another tenant's private financial data.
     */
    public function testTenantADataServedToTenantBRequest(): void
    {
        $auth        = new AuthService();       // singleton
        $tenantData  = new TenantDataService($auth);

        // Request 1: Acme user fetches their data — correct
        $acmeUser = new User('u-001', 'Alice', 'tenant-acme', ['user']);
        $auth->login($acmeUser);

        $acmeData = $tenantData->getData();
        $this->assertSame(150000, $acmeData['revenue'], 'Acme revenue: 150,000');

        // Request 2: Globex user — login is called (not forgotten this time)
        // But what if the login call happens AFTER getData() in some code path?
        // Or what if Globex's user is constructed with the wrong tenantId?

        // Simulate the common "login late" bug: some code calls getData() before
        // the auth middleware has a chance to run login() for this request.
        $globexData = $tenantData->getData(); // Called BEFORE login() for Globex

        // BUG: returns Acme's data (tenant-acme is still in the singleton)
        $this->assertSame(150000, $globexData['revenue'],
            'BUG: Globex received Acme\'s revenue data (150,000 instead of 89,000)'
        );
        $this->assertSame(42, $globexData['users'],
            'BUG: Globex received Acme\'s user count (42 instead of 17)'
        );

        // The correct data for Globex should be:
        // revenue: 89,000, users: 17
        $this->assertNotSame(89000, $globexData['revenue'],
            'Globex\'s actual revenue (89,000) was NOT served — Acme\'s data was served instead'
        );
    }

    /**
     * With fresh auth service per request, tenants always see their own data.
     */
    public function testFreshAuthServiceIsolatesTenants(): void
    {
        // Acme request — fresh auth
        $authAcme   = new AuthService();
        $dataAcme   = new TenantDataService($authAcme);
        $authAcme->login(new User('u-001', 'Alice', 'tenant-acme', ['user']));

        // Globex request — FRESH auth
        $authGlobex = new AuthService();
        $dataGlobex = new TenantDataService($authGlobex);
        $authGlobex->login(new User('u-002', 'Bob', 'tenant-globex', ['user']));

        $acmeResult   = $dataAcme->getData();
        $globexResult = $dataGlobex->getData();

        $this->assertSame(150000, $acmeResult['revenue'],   'Acme: correct revenue');
        $this->assertSame(89000,  $globexResult['revenue'], 'Globex: correct revenue');
        $this->assertSame(42, $acmeResult['users'],         'Acme: correct user count');
        $this->assertSame(17, $globexResult['users'],       'Globex: correct user count');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PART E — Scenario D: the timing / ordering variant
//
// Even when login() IS always called, the order in which it is called relative
// to the code that reads the user matters. The singleton forces a global
// ordering constraint that is invisible in the type system.
// ─────────────────────────────────────────────────────────────────────────────

class AuthOrderingTest extends TestCase
{
    /**
     * Demonstrates that with a singleton auth service, the LAST call to
     * login() before any read is what determines the identity — even if that
     * "last call" came from a completely different logical request.
     *
     * This ordering problem is subtle. In a synchronous PHP-FPM system it is
     * almost impossible to trigger. In a Swoole coroutine system where two
     * requests are interleaved, it is trivially reproducible.
     */
    public function testLastLoginWinsRegardlessOfWhichRequestLoggedIn(): void
    {
        $auth = new AuthService(); // singleton

        // Request 1 logs in as Alice
        $auth->login(new User('u-001', 'Alice', 'tenant-acme', ['user', 'admin']));

        // Request 2 (interleaved) logs in as Bob before Request 1 reads the user
        $auth->login(new User('u-002', 'Bob', 'tenant-globex', ['user']));

        // Request 1 now reads the user — expecting Alice, gets Bob
        $currentUser = $auth->getUser();

        $this->assertSame('Bob', $currentUser?->name,
            'BUG: Request 1 reads Bob\'s identity — Bob\'s login() overwrote Alice\'s'
        );
        $this->assertFalse($currentUser?->hasRole('admin'),
            'BUG: Request 1 loses admin role — Bob\'s roles replaced Alice\'s'
        );
    }
}