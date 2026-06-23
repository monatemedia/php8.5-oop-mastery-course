<?php
declare(strict_types=1);

/**
 * Example 02 — Request Context Injection
 * -----------------------------------------
 * Run via PHPUnit:
 *   ./vendor/bin/phpunit module-6-object-lifecycle-and-state/lesson-6.4-designing-stateless-services/examples/02-request-context-injection.php
 *
 * The RequestContext pattern is the canonical solution for Anti-pattern 2
 * (auth state) and Anti-pattern 3 (request-scoped data on a singleton).
 *
 * Core idea: instead of a mutable singleton that is told "set current user
 * to X" at the start of each request, create an IMMUTABLE value object at the
 * start of each request that carries all per-request data. Inject it into
 * services that need it. The value object is transient — a new one is created
 * per request. The services that consume it are stateless singletons.
 *
 * This file builds the pattern in three layers:
 *
 *   LAYER 1 — The RequestContext value object
 *   LAYER 2 — Stateless services that consume RequestContext
 *   LAYER 3 — How the context is constructed (composition root / factory)
 *   LAYER 4 — Tests proving isolation, immutability, and service correctness
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// LAYER 1 — RequestContext value object
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
 * Immutable value object carrying all per-request facts.
 *
 * Created ONCE at the start of each request (by a factory at the composition
 * root). Injected into every service that needs to know about the current
 * request. Readonly properties prevent any mutation after construction.
 *
 * KEY INSIGHT: because RequestContext is immutable and created fresh per request,
 * it can be a singleton within a request but must not survive across requests.
 * In PHP-DI, register it as factory() so a new instance is produced per resolution.
 */
final class RequestContext
{
    private function __construct(
        public readonly ?User  $user,
        public readonly string $requestId,
        public readonly string $path,
        public readonly string $method,
        public readonly float  $startedAt,
    ) {}

    // ── Named constructors ────────────────────────────────────────────────────

    public static function authenticated(
        User   $user,
        string $requestId,
        string $path,
        string $method = 'GET',
    ): self {
        return new self(
            user:      $user,
            requestId: $requestId,
            path:      $path,
            method:    $method,
            startedAt: microtime(true),
        );
    }

    public static function anonymous(
        string $requestId,
        string $path,
        string $method = 'GET',
    ): self {
        return new self(
            user:      null,
            requestId: $requestId,
            path:      $path,
            method:    $method,
            startedAt: microtime(true),
        );
    }

    // ── Queries ────────────────────────────────────────────────────────────────

    public function isAuthenticated(): bool { return $this->user !== null; }

    public function requireAuthentication(): void
    {
        if (!$this->isAuthenticated()) {
            throw new \RuntimeException('Authentication required');
        }
    }

    public function requireRole(string $role): void
    {
        $this->requireAuthentication();
        if (!$this->user->hasRole($role)) {
            throw new \RuntimeException("Role required: {$role}");
        }
    }

    public function getTenantId(): string
    {
        $this->requireAuthentication();
        return $this->user->tenantId;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// LAYER 2 — Stateless services that consume RequestContext
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Stateless audit service. Writes audit entries for the current request.
 *
 * Receives RequestContext as a method parameter — not stored as instance state.
 * Can be a singleton because it holds no per-request data.
 */
class AuditService
{
    // No private state. RequestContext is passed per-call, not stored.

    public function logAccess(RequestContext $ctx, string $resource): array
    {
        return [
            'requestId' => $ctx->requestId,
            'userId'    => $ctx->user?->id,
            'resource'  => $resource,
            'action'    => 'access',
            'path'      => $ctx->path,
        ];
    }

    public function logAction(RequestContext $ctx, string $action, array $data = []): array
    {
        $ctx->requireAuthentication();

        return [
            'requestId' => $ctx->requestId,
            'userId'    => $ctx->user->id,
            'tenantId'  => $ctx->user->tenantId,
            'action'    => $action,
            'data'      => $data,
        ];
    }
}

/**
 * Stateless tenant data service. Returns tenant-specific data.
 *
 * The tenant ID comes from RequestContext — not from a setTenant() call.
 * The service is pure: same context → same data, no side effects.
 */
class TenantDataService
{
    private array $data = [
        'tenant-acme'   => ['plan' => 'enterprise', 'seats' => 100],
        'tenant-globex' => ['plan' => 'pro',         'seats' => 25],
        'tenant-initec' => ['plan' => 'starter',     'seats' => 5],
    ];

    public function getConfig(RequestContext $ctx): array
    {
        $ctx->requireAuthentication();

        return $this->data[$ctx->getTenantId()] ?? [];
    }

    public function getPlan(RequestContext $ctx): string
    {
        return $this->getConfig($ctx)['plan'] ?? 'unknown';
    }
}

/**
 * Stateless admin service. Enforces role requirements via context.
 */
class AdminService
{
    public function getAdminPanel(RequestContext $ctx): array
    {
        $ctx->requireRole('admin');

        return [
            'panel'     => 'Admin Dashboard',
            'accessedBy' => $ctx->user->name,
            'requestId' => $ctx->requestId,
        ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// LAYER 3 — How the context is constructed
//
// In production, this happens at the composition root — the bootstrap code that
// runs at the start of each request. In PHP-DI, you register a factory() so a
// new RequestContext is constructed for each request.
//
// PHP-DI definition (would live in container.php):
//
//   RequestContext::class => factory(function(
//       ServerRequestInterface $request,
//       AuthService $auth,
//   ): RequestContext {
//       $user = $auth->resolveFromRequest($request);
//       return $user
//           ? RequestContext::authenticated($user, uniqid(), $request->getUri()->getPath())
//           : RequestContext::anonymous(uniqid(), $request->getUri()->getPath());
//   }),
//
// In tests, we construct RequestContext directly — no factory needed.
// ─────────────────────────────────────────────────────────────────────────────

// ─────────────────────────────────────────────────────────────────────────────
// LAYER 4 — Tests
// ─────────────────────────────────────────────────────────────────────────────

class RequestContextTest extends TestCase
{
    // ── RequestContext value object tests ─────────────────────────────────────

    /**
     * A fresh anonymous context is unauthenticated — no user, no tenant.
     */
    public function testAnonymousContextIsUnauthenticated(): void
    {
        $ctx = RequestContext::anonymous('req-001', '/api/health');

        $this->assertFalse($ctx->isAuthenticated());
        $this->assertNull($ctx->user);
        $this->assertSame('req-001', $ctx->requestId);
        $this->assertSame('/api/health', $ctx->path);
    }

    /**
     * An authenticated context carries the user's full identity.
     */
    public function testAuthenticatedContextCarriesUserIdentity(): void
    {
        $alice = new User('u-001', 'Alice', 'tenant-acme', ['user', 'admin']);
        $ctx   = RequestContext::authenticated($alice, 'req-002', '/admin/dashboard');

        $this->assertTrue($ctx->isAuthenticated());
        $this->assertSame('Alice', $ctx->user->name);
        $this->assertSame('tenant-acme', $ctx->user->tenantId);
        $this->assertTrue($ctx->user->hasRole('admin'));
    }

    /**
     * requireAuthentication() throws for an anonymous context.
     * This is the mechanism that replaces isAuthenticated() checks in services.
     */
    public function testRequireAuthenticationThrowsForAnonymous(): void
    {
        $ctx = RequestContext::anonymous('req-003', '/protected');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Authentication required');

        $ctx->requireAuthentication();
    }

    /**
     * requireRole() throws when the user lacks the required role.
     */
    public function testRequireRoleThrowsForInsufficientRole(): void
    {
        $bob = new User('u-002', 'Bob', 'tenant-acme', ['user']); // no admin role
        $ctx = RequestContext::authenticated($bob, 'req-004', '/admin');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Role required: admin');

        $ctx->requireRole('admin');
    }

    /**
     * RequestContext is immutable — readonly properties cannot be reassigned.
     * This test documents the guarantee via reflection.
     */
    public function testRequestContextPropertiesAreReadonly(): void
    {
        $reflection = new \ReflectionClass(RequestContext::class);

        foreach ($reflection->getProperties() as $prop) {
            $this->assertTrue($prop->isReadOnly(),
                "Property {$prop->getName()} must be readonly"
            );
        }
    }

    // ── Stateless services using RequestContext ───────────────────────────────

    /**
     * AuditService singleton: two requests, same service, no cross-contamination.
     * Each logAccess() call is entirely determined by its arguments.
     */
    public function testAuditServiceSingletonHasNoCrossRequestContamination(): void
    {
        $audit = new AuditService(); // singleton — safe

        $alice = new User('u-001', 'Alice', 'tenant-acme');
        $bob   = new User('u-002', 'Bob',   'tenant-globex');

        $ctxAlice = RequestContext::authenticated($alice, 'req-alice', '/orders');
        $ctxBob   = RequestContext::authenticated($bob,   'req-bob',   '/invoices');

        $logAlice = $audit->logAccess($ctxAlice, 'orders-list');
        $logBob   = $audit->logAccess($ctxBob,   'invoices-list');

        // Each log entry correctly reflects its own context
        $this->assertSame('u-001',     $logAlice['userId']);
        $this->assertSame('req-alice', $logAlice['requestId']);

        $this->assertSame('u-002',   $logBob['userId']);
        $this->assertSame('req-bob', $logBob['requestId']);

        // No cross-contamination: Alice's requestId is not in Bob's entry
        $this->assertNotSame($logAlice['requestId'], $logBob['requestId']);
    }

    /**
     * TenantDataService: two tenants, same service, correct isolation.
     */
    public function testTenantDataServiceCorrectlyIsolatesTenants(): void
    {
        $tenantData = new TenantDataService(); // singleton — safe

        $acmeUser   = new User('u-001', 'Alice', 'tenant-acme');
        $globexUser = new User('u-002', 'Bob',   'tenant-globex');

        $ctxAcme   = RequestContext::authenticated($acmeUser,   'req-001', '/config');
        $ctxGlobex = RequestContext::authenticated($globexUser, 'req-002', '/config');

        $acmePlan   = $tenantData->getPlan($ctxAcme);
        $globexPlan = $tenantData->getPlan($ctxGlobex);

        $this->assertSame('enterprise', $acmePlan,  'Acme gets enterprise plan');
        $this->assertSame('pro',        $globexPlan, 'Globex gets pro plan');

        // Same service, same method, different contexts → different results
        // No state leaked between calls
        $this->assertNotSame($acmePlan, $globexPlan);
    }

    /**
     * AdminService: access is granted only when context contains admin role.
     * Denied for non-admin and anonymous — without any shared state.
     */
    public function testAdminServiceEnforcesRoleViaContext(): void
    {
        $admin = new AdminService(); // singleton — safe

        $alice   = new User('u-001', 'Alice', 'tenant-acme', ['user', 'admin']);
        $bob     = new User('u-002', 'Bob',   'tenant-acme', ['user']); // not admin

        $ctxAdmin    = RequestContext::authenticated($alice, 'req-001', '/admin');
        $ctxNonAdmin = RequestContext::authenticated($bob,   'req-002', '/admin');
        $ctxAnon     = RequestContext::anonymous('req-003', '/admin');

        // Alice (admin): success
        $panel = $admin->getAdminPanel($ctxAdmin);
        $this->assertSame('Alice', $panel['accessedBy']);

        // Bob (non-admin): denied
        $this->expectException(\RuntimeException::class);
        $admin->getAdminPanel($ctxNonAdmin);
    }

    /**
     * Two contexts created for the same user in different requests are
     * independent objects — modifying one does not affect the other.
     * (Not that they can be modified — readonly — but the point stands.)
     */
    public function testTwoContextsForSameUserAreIndependentObjects(): void
    {
        $alice = new User('u-001', 'Alice', 'tenant-acme');

        $ctx1 = RequestContext::authenticated($alice, 'req-001', '/path-a');
        $ctx2 = RequestContext::authenticated($alice, 'req-002', '/path-b');

        // Different request IDs and paths — same user, different request contexts
        $this->assertNotSame($ctx1->requestId, $ctx2->requestId);
        $this->assertNotSame($ctx1->path, $ctx2->path);
        $this->assertNotSame($ctx1, $ctx2);

        // But they carry the same user — that is correct
        $this->assertSame($ctx1->user->id, $ctx2->user->id);
    }
}