<?php
declare(strict_types=1);

/**
 * CHALLENGE SOLUTION — Lesson 5.3: Test-Driven Development
 * ──────────────────────────────────────────────────────────
 * ⚠️  Only open this file after completing all 5 TDD cycles yourself.
 *
 * This file shows the complete PasswordResetService as it emerged from TDD.
 * Read the comments — they document WHY each design decision was made and
 * WHICH test forced it.
 *
 * Key things to compare with your solution:
 *   1. The ClockInterface — did your tests force you to inject it?
 *   2. The fake repository — does it store and check expiry correctly?
 *   3. The isTokenValid() logic — did you handle all three false cases?
 *   4. The invalidateToken() implementation — a 'used' flag in the record
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// Interfaces — emerged from what the anonymous class doubles needed
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Emerged from Cycle 2 test: storeToken() calls repository->store().
 * The spy's method signature defined this interface.
 *
 * find() returns an array with:
 *   'token'      => string
 *   'expires_at' => \DateTimeImmutable
 *   'used'       => bool       ← emerged from Cycle 5 (invalidation)
 */
interface TokenRepositoryInterface
{
    public function store(string $email, string $token, \DateTimeImmutable $expiresAt): void;
    public function find(string $email): ?array;
    public function invalidate(string $email): void;
}

/**
 * Emerged from Cycle 2 test: "expiry is in the future relative to the clock".
 * Writing that assertion with a real clock is impossible — it always returns NOW.
 * The test forced ClockInterface to be injected.
 */
interface ClockInterface
{
    public function now(): \DateTimeImmutable;
}


// ─────────────────────────────────────────────────────────────────────────────
// PasswordResetService — built cycle by cycle
// ─────────────────────────────────────────────────────────────────────────────

class PasswordResetService
{
    // Token expiry: 1 hour.
    // Emerged as a constant during Cycle 2 refactor — was originally the magic
    // literal 3600 in the implementation, extracted when it appeared twice.
    private const TOKEN_EXPIRY_SECONDS = 3600;

    // Token length: 32 bytes = 64 hex characters.
    // Cycle 1 GREEN: the test demanded exactly 64 chars, bin2hex(random_bytes(32))
    // delivers that. The constant emerged during Cycle 1 refactor.
    private const TOKEN_BYTES = 32;

    /**
     * Constructor signature emerged from Cycle 2 (repository) and Cycle 2's
     * expiry test (clock). Cycle 1 did not need the constructor at all —
     * generateToken() needed no dependencies, so the constructor was empty.
     * It grew as tests demanded more dependencies.
     */
    public function __construct(
        private TokenRepositoryInterface $repository,
        private ClockInterface           $clock
    ) {}

    // ── Cycle 1: generateToken() ──────────────────────────────────────────────
    //
    // Test 1: assertIsString($token)               → method must exist, return string
    // Test 2: assertSame(64, strlen($token))       → must be 64 chars
    // Test 3: assertNotSame($t1, $t2)              → must not be hardcoded
    //
    // GREEN (naive for test 1): return str_repeat('a', 64);
    // GREEN (test 3 broke it):  return bin2hex(random_bytes(32));
    // REFACTOR: extract TOKEN_BYTES constant
    //
    public function generateToken(string $email): string
    {
        return bin2hex(random_bytes(self::TOKEN_BYTES));
    }

    // ── Cycle 2: storeToken() ────────────────────────────────────────────────
    //
    // Test 4: spy asserts store() was called        → must call repository
    // Test 5: spy asserts correct email and token   → must pass args through
    // Test 6: spy asserts expiry is in the future   → forced ClockInterface injection
    //         (expiry > clock->now() + TOKEN_EXPIRY_SECONDS)
    //
    // GREEN: call repository->store() with clock->now() + 3600 seconds
    // REFACTOR: extract TOKEN_EXPIRY_SECONDS constant
    //
    public function storeToken(string $email, string $token): void
    {
        $expiresAt = $this->clock->now()->modify('+' . self::TOKEN_EXPIRY_SECONDS . ' seconds');
        $this->repository->store($email, $token, $expiresAt);
    }

    // ── Cycle 3 + 4: isTokenValid() ──────────────────────────────────────────
    //
    // Test 7:  valid token, not expired  → return true
    // GREEN:   return true;              → passes test 7
    //
    // Test 8:  wrong token               → return false
    // GREEN:   return $record['token'] === $token;
    //
    // Test 9:  no record in repo         → return false
    // GREEN:   add null check: if ($record === null) return false;
    //
    // Test 10: expired token             → return false
    // GREEN:   add expiry check: $record['expires_at'] > $this->clock->now()
    //
    // Test 11: used/invalidated token    → return false  (discovered in Cycle 5)
    // GREEN:   add used check: !$record['used']
    //
    // REFACTOR: nothing structural — the three conditions read cleanly as-is
    //
    public function isTokenValid(string $email, string $token): bool
    {
        $record = $this->repository->find($email);

        if ($record === null) {
            return false;
        }

        if ($record['token'] !== $token) {
            return false;
        }

        if ($record['expires_at'] <= $this->clock->now()) {
            return false;
        }

        if ($record['used']) {
            return false;
        }

        return true;
    }

    // ── Cycle 5: invalidateToken() ───────────────────────────────────────────
    //
    // Test 11: valid token → invalidate → isTokenValid() returns false
    // GREEN:   call repository->invalidate($email)
    // The fake repo's invalidate() sets $record['used'] = true
    // isTokenValid() checks !$record['used'] → now returns false
    //
    public function invalidateToken(string $email): void
    {
        $this->repository->invalidate($email);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Test class
// ─────────────────────────────────────────────────────────────────────────────

class PasswordResetServiceTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────────────────
    // Shared doubles and service
    // ─────────────────────────────────────────────────────────────────────────

    private PasswordResetService $service;
    private object               $fakeRepo;
    private object               $fixedClock;

    /** Fixed point in time — makes expiry assertions deterministic */
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-06-01 12:00:00');

        // Fake repository: real in-memory store
        // Emerged Cycle 2: store() and find() first; invalidate() added in Cycle 5
        $this->fakeRepo = new class implements TokenRepositoryInterface {
            private array $records = [];

            public function store(string $email, string $token, \DateTimeImmutable $expiresAt): void {
                $this->records[$email] = [
                    'token'      => $token,
                    'expires_at' => $expiresAt,
                    'used'       => false,     // ← added in Cycle 5 refactor
                ];
            }

            public function find(string $email): ?array {
                return $this->records[$email] ?? null;
            }

            public function invalidate(string $email): void {
                if (isset($this->records[$email])) {
                    $this->records[$email]['used'] = true;
                }
            }
        };

        // Fixed clock stub: always returns $this->now
        // Emerged Cycle 2: "expiry is in the future relative to the clock"
        $now = $this->now;
        $this->fixedClock = new class($now) implements ClockInterface {
            public function __construct(private \DateTimeImmutable $time) {}
            public function now(): \DateTimeImmutable { return $this->time; }
        };

        $this->service = new PasswordResetService($this->fakeRepo, $this->fixedClock);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Cycle 1 — generateToken()
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * TDD step 1: the method must exist and return a string.
     * This was the very first test — PasswordResetService did not exist.
     */
    public function testGenerateTokenReturnsAString(): void
    {
        $token = $this->service->generateToken('alice@example.com');

        $this->assertIsString($token);
    }

    /**
     * TDD step 2: exactly 64 characters.
     * GREEN: bin2hex(random_bytes(32)) → 32 bytes × 2 hex chars = 64 chars.
     */
    public function testGenerateTokenReturnsExactly64Characters(): void
    {
        $token = $this->service->generateToken('alice@example.com');

        $this->assertSame(64, strlen($token));
    }

    /**
     * TDD step 3: breaks any hardcoded return value.
     * GREEN: random_bytes() produces different output each call.
     */
    public function testGenerateTokenReturnsDifferentTokenOnEachCall(): void
    {
        $t1 = $this->service->generateToken('alice@example.com');
        $t2 = $this->service->generateToken('alice@example.com');

        $this->assertNotSame($t1, $t2);
    }

    /**
     * Bonus: token is hexadecimal (only 0-9 a-f).
     */
    public function testGenerateTokenContainsOnlyHexCharacters(): void
    {
        $token = $this->service->generateToken('alice@example.com');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Cycle 2 — storeToken()
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * TDD step 4: storeToken() must persist via the repository.
     * Writing this spy DEFINED the store() method signature on TokenRepositoryInterface.
     */
    public function testStoreTokenCallsRepositoryStoreMethod(): void
    {
        $spyRepo = new class implements TokenRepositoryInterface {
            public bool $storeCalled = false;
            public function store(string $email, string $token, \DateTimeImmutable $expiresAt): void {
                $this->storeCalled = true;
            }
            public function find(string $email): ?array  { return null; }
            public function invalidate(string $email): void {}
        };

        $service = new PasswordResetService($spyRepo, $this->fixedClock);

        $service->storeToken('alice@example.com', 'abc123');

        $this->assertTrue($spyRepo->storeCalled);
    }

    /**
     * TDD step 5: the correct email and token are passed to store().
     */
    public function testStoreTokenStoresTheCorrectEmailAndToken(): void
    {
        $spyRepo = new class implements TokenRepositoryInterface {
            public ?string $storedEmail = null;
            public ?string $storedToken = null;
            public function store(string $email, string $token, \DateTimeImmutable $expiresAt): void {
                $this->storedEmail = $email;
                $this->storedToken = $token;
            }
            public function find(string $email): ?array  { return null; }
            public function invalidate(string $email): void {}
        };

        $service = new PasswordResetService($spyRepo, $this->fixedClock);

        $service->storeToken('alice@example.com', 'mytoken64chars00');

        $this->assertSame('alice@example.com',  $spyRepo->storedEmail);
        $this->assertSame('mytoken64chars00', $spyRepo->storedToken);
    }

    /**
     * TDD step 6: the stored expiry is in the future relative to the clock.
     *
     * THIS TEST forced ClockInterface.
     * Without a fixed clock, asserting an exact expiry datetime is impossible.
     */
    public function testStoreTokenSetsExpiryOneHourAfterClockNow(): void
    {
        $spyRepo = new class implements TokenRepositoryInterface {
            public ?\DateTimeImmutable $storedExpiry = null;
            public function store(string $email, string $token, \DateTimeImmutable $expiresAt): void {
                $this->storedExpiry = $expiresAt;
            }
            public function find(string $email): ?array  { return null; }
            public function invalidate(string $email): void {}
        };

        $service = new PasswordResetService($spyRepo, $this->fixedClock);

        $service->storeToken('alice@example.com', 'tok');

        $expectedExpiry = $this->now->modify('+3600 seconds');
        $this->assertEquals($expectedExpiry, $spyRepo->storedExpiry);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Cycle 3 — isTokenValid() — valid cases
    // ─────────────────────────────────────────────────────────────────────────

    private function seedToken(string $email, string $token, \DateTimeImmutable $expiresAt): void
    {
        $this->fakeRepo->store($email, $token, $expiresAt);
    }

    /**
     * TDD step 7: returns true for a matching, unexpired token.
     * Initial GREEN: return true; — passes this test.
     */
    public function testIsTokenValidReturnsTrueForMatchingUnexpiredToken(): void
    {
        $expiresAt = $this->now->modify('+1 hour');
        $this->seedToken('alice@example.com', 'validtoken', $expiresAt);

        $result = $this->service->isTokenValid('alice@example.com', 'validtoken');

        $this->assertTrue($result);
    }

    /**
     * TDD step 8: returns false for wrong token.
     * Breaks naive return true; — forces token comparison.
     */
    public function testIsTokenValidReturnsFalseForWrongToken(): void
    {
        $expiresAt = $this->now->modify('+1 hour');
        $this->seedToken('alice@example.com', 'correcttoken', $expiresAt);

        $result = $this->service->isTokenValid('alice@example.com', 'wrongtoken');

        $this->assertFalse($result);
    }

    /**
     * TDD step 9: returns false when no record exists.
     * Forces the null check before the token comparison.
     */
    public function testIsTokenValidReturnsFalseWhenNoRecordExists(): void
    {
        // Nothing stored for this email
        $result = $this->service->isTokenValid('ghost@example.com', 'anytoken');

        $this->assertFalse($result);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Cycle 4 — isTokenValid() — expired token
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * TDD step 10: returns false for an expired token.
     *
     * The fixed clock is "2026-06-01 12:00:00".
     * Token expired at "2026-06-01 11:00:00" — one hour BEFORE now.
     * This test forced the expiry check: expires_at <= clock->now() → false.
     */
    public function testIsTokenValidReturnsFalseForExpiredToken(): void
    {
        $expiredAt = $this->now->modify('-1 hour'); // already expired
        $this->seedToken('alice@example.com', 'expiredtoken', $expiredAt);

        $result = $this->service->isTokenValid('alice@example.com', 'expiredtoken');

        $this->assertFalse($result);
    }

    /**
     * Boundary: token that expired exactly at "now" is also invalid.
     */
    public function testIsTokenValidReturnsFalseWhenExpiryEqualsNow(): void
    {
        $this->seedToken('alice@example.com', 'tok', $this->now); // expires_at == now

        $this->assertFalse($this->service->isTokenValid('alice@example.com', 'tok'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Cycle 5 — invalidateToken()
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * TDD step 11: invalidating a valid token makes it invalid.
     *
     * This test forced the fake repo to track a 'used' flag,
     * and forced isTokenValid() to check !$record['used'].
     */
    public function testInvalidateTokenMakesPreviouslyValidTokenInvalid(): void
    {
        $expiresAt = $this->now->modify('+1 hour');
        $this->seedToken('alice@example.com', 'validtoken', $expiresAt);

        // Pre-condition: token is valid
        $this->assertTrue($this->service->isTokenValid('alice@example.com', 'validtoken'));

        // Act
        $this->service->invalidateToken('alice@example.com');

        // Post-condition: token is now invalid
        $this->assertFalse($this->service->isTokenValid('alice@example.com', 'validtoken'));
    }

    /**
     * Invalidating one email does not affect another.
     */
    public function testInvalidateTokenOnlyAffectsSpecifiedEmail(): void
    {
        $expiresAt = $this->now->modify('+1 hour');
        $this->seedToken('alice@example.com', 'alice-tok', $expiresAt);
        $this->seedToken('bob@example.com',   'bob-tok',   $expiresAt);

        $this->service->invalidateToken('alice@example.com');

        $this->assertFalse($this->service->isTokenValid('alice@example.com', 'alice-tok'));
        $this->assertTrue($this->service->isTokenValid('bob@example.com',   'bob-tok'));
    }
}