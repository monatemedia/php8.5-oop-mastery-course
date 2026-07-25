<?php
declare(strict_types=1);

/**
 * Example 01 — One Complete TDD Cycle, Annotated
 * ------------------------------------------------
 * Run via PHPUnit:
 *   ./vendor/bin/phpunit module-5-testing-and-tdd/lesson-5.3-tdd/examples/01-red-green-refactor.php
 *
 * This file shows a COMPLETE TDD session building a RateLimiter from scratch.
 * The tests, the intermediate broken implementations, and the final clean
 * implementation are all in one file so you can see the full journey.
 *
 * Structure:
 *   PART A — The subject and the story
 *   PART B — Cycle 1: generateKey()      (Red → Green → Refactor)
 *   PART C — Cycle 2: hit() increments   (Red → Green → Refactor)
 *   PART D — Cycle 3: isAllowed()        (Red → Green → Refactor)
 *   PART E — Cycle 4: reset()            (Red → Green → Refactor)
 *   PART F — The final, refactored RateLimiter
 *   PART G — Full test suite (all four cycles together)
 *
 * HOW TO READ THIS FILE:
 *   Read the test first. Then read the "RED" comment. Then read the
 *   "GREEN (naive)" implementation. Then the "GREEN (real)" implementation.
 *   Then the "REFACTOR" version. Then move to the next cycle.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

// ─────────────────────────────────────────────────────────────────────────────
// PART A — The story
// ─────────────────────────────────────────────────────────────────────────────
//
// We need a RateLimiter that:
//   1. Tracks API hits per key (e.g. "user:42" or "ip:192.168.1.1")
//   2. Allows a configurable maximum number of hits per window
//   3. isAllowed() returns false once the limit is exceeded
//   4. reset() clears the count for a key
//
// We will build it test-first. Each test is the smallest useful behaviour.
// ─────────────────────────────────────────────────────────────────────────────


// ─────────────────────────────────────────────────────────────────────────────
// PART F — The final RateLimiter (built via TDD)
// Read this AFTER the test cycles below, not before.
// ─────────────────────────────────────────────────────────────────────────────

class RateLimiter
{
    // TDD Refactor: the magic numbers became named constants
    private const DEFAULT_LIMIT = 5;

    // TDD Refactor: hits storage promoted to a named property (was $this->data)
    private array $hits = [];

    public function __construct(private int $limit = self::DEFAULT_LIMIT) {}

    // ── Cycle 1: emerged from test 1 ─────────────────────────────────────────
    public function generateKey(string $identifier): string
    {
        // TDD Green (naive): return 'user:42'   ← hardcoded, test 1 passed
        // TDD Green (real):  return $identifier ← test 2 broke the hardcode
        // TDD Refactor:      no change needed — already clean
        return $identifier;
    }

    // ── Cycle 2: emerged from test 2 ─────────────────────────────────────────
    public function hit(string $key): void
    {
        // TDD Green (naive): $this->data[$key] = 1;         ← passed test 2
        // TDD Green (real):  $this->data[$key] = ($this->data[$key] ?? 0) + 1; ← test 3 broke it
        // TDD Refactor:      renamed $data → $hits for clarity
        $this->hits[$key] = ($this->hits[$key] ?? 0) + 1;
    }

    // ── Cycle 3: emerged from test 3 ─────────────────────────────────────────
    public function getCount(string $key): int
    {
        return $this->hits[$key] ?? 0;
    }

    public function isAllowed(string $key): bool
    {
        // TDD Green (naive): return true;               ← passed test 3a
        // TDD Green (real):  return count < limit       ← test 3b broke it
        // TDD Refactor:      extract getCount() for clarity
        return $this->getCount($key) < $this->limit;
    }

    // ── Cycle 4: emerged from test 4 ─────────────────────────────────────────
    public function reset(string $key): void
    {
        // TDD Green: unset($this->hits[$key]); — that is already clean
        unset($this->hits[$key]);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// PART G — Full test suite for RateLimiter
// Read each test, then find the corresponding "cycle" story in the comments.
// ─────────────────────────────────────────────────────────────────────────────

class RateLimiterTDDCycleTest extends TestCase
{
    private RateLimiter $limiter;

    protected function setUp(): void
    {
        // Limit of 3 hits — small enough to test easily
        $this->limiter = new RateLimiter(limit: 3);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CYCLE 1 — generateKey()
    //
    // RED:    RateLimiter does not exist → PHP fatal error
    // GREEN:  Create the class + return a hardcoded string → test 1a passes
    // GREEN:  Test 1b breaks the hardcode → implement properly
    // REFACTOR: No structural change needed yet
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * TEST 1a: The simplest possible test — does generateKey() return a string?
     *
     * Why this first: before testing what the key looks like, confirm the
     * method exists and returns the right type.
     */
    public function testGenerateKeyReturnsAString(): void
    {
        $key = $this->limiter->generateKey('user:42');

        $this->assertIsString($key);
    }

    /**
     * TEST 1b: Does generateKey() return the identifier unchanged?
     *
     * The GREEN for test 1a was: return 'user:42'; (hardcoded)
     * This test BREAKS that hardcode because a different identifier is passed.
     */
    public function testGenerateKeyReturnsTheIdentifier(): void
    {
        $this->assertSame('user:42',           $this->limiter->generateKey('user:42'));
        $this->assertSame('ip:192.168.1.100',  $this->limiter->generateKey('ip:192.168.1.100'));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CYCLE 2 — hit() and getCount()
    //
    // RED:    hit() does not exist → error
    // GREEN:  $this->data[$key] = 1; → test 2a passes (but not 2b)
    // GREEN:  increment properly → test 2b passes
    // REFACTOR: rename $data → $hits; extract getCount() as helper
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * TEST 2a: First hit sets count to 1.
     */
    public function testFirstHitSetsCountToOne(): void
    {
        $this->limiter->hit('user:42');

        $this->assertSame(1, $this->limiter->getCount('user:42'));
    }

    /**
     * TEST 2b: Successive hits accumulate.
     *
     * The naive GREEN ($this->data[$key] = 1) always sets it to 1.
     * This test forces the real increment logic.
     */
    public function testSuccessiveHitsAccumulate(): void
    {
        $this->limiter->hit('user:42');
        $this->limiter->hit('user:42');
        $this->limiter->hit('user:42');

        $this->assertSame(3, $this->limiter->getCount('user:42'));
    }

    /**
     * TEST 2c: Keys are independent — hitting one key does not affect another.
     *
     * This test emerges naturally from: "what if two users both hit the API?"
     */
    public function testHitsForDifferentKeysAreIndependent(): void
    {
        $this->limiter->hit('user:42');
        $this->limiter->hit('user:42');
        $this->limiter->hit('user:99');

        $this->assertSame(2, $this->limiter->getCount('user:42'));
        $this->assertSame(1, $this->limiter->getCount('user:99'));
    }

    /**
     * TEST 2d: A key with no hits has count zero.
     *
     * Edge case discovered during the cycle: what does getCount() return
     * before any hit() calls? Force it to be 0, not null, not an error.
     */
    public function testCountIsZeroForUntouchedKey(): void
    {
        $this->assertSame(0, $this->limiter->getCount('user:new'));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CYCLE 3 — isAllowed()
    //
    // RED:    isAllowed() does not exist → error
    // GREEN:  return true; → test 3a passes
    // GREEN:  test 3b breaks return true; → implement the limit check
    // REFACTOR: extract getCount() from isAllowed() body for readability
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * TEST 3a: A fresh key (zero hits) is allowed.
     *
     * The naive GREEN is: return true; — which passes this test.
     */
    public function testIsAllowedReturnsTrueForFreshKey(): void
    {
        $this->assertTrue($this->limiter->isAllowed('user:42'));
    }

    /**
     * TEST 3b: A key below the limit is allowed.
     *
     * Still passes with naive return true; — but sets up test 3c.
     */
    public function testIsAllowedReturnsTrueWhileBelowLimit(): void
    {
        $this->limiter->hit('user:42'); // 1 of 3
        $this->limiter->hit('user:42'); // 2 of 3

        $this->assertTrue($this->limiter->isAllowed('user:42'));
    }

    /**
     * TEST 3c: A key AT the limit is no longer allowed.
     *
     * This BREAKS naive return true; — forces the real limit check.
     * RateLimiter has limit: 3. After 3 hits, isAllowed() must return false.
     */
    public function testIsAllowedReturnsFalseWhenLimitReached(): void
    {
        $this->limiter->hit('user:42'); // 1
        $this->limiter->hit('user:42'); // 2
        $this->limiter->hit('user:42'); // 3 — at the limit

        $this->assertFalse($this->limiter->isAllowed('user:42'));
    }

    /**
     * TEST 3d: Exceeding the limit remains false (not "wraps around").
     */
    public function testIsAllowedRemainsFalseAfterLimitExceeded(): void
    {
        $this->limiter->hit('user:42');
        $this->limiter->hit('user:42');
        $this->limiter->hit('user:42');
        $this->limiter->hit('user:42'); // 4 — over the limit

        $this->assertFalse($this->limiter->isAllowed('user:42'));
    }

    /**
     * TEST 3e: One key over limit does not affect another key.
     */
    public function testLimitOnOneKeyDoesNotAffectAnotherKey(): void
    {
        $this->limiter->hit('user:42');
        $this->limiter->hit('user:42');
        $this->limiter->hit('user:42'); // user:42 is now blocked

        $this->assertTrue($this->limiter->isAllowed('user:99')); // user:99 is fine
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CYCLE 4 — reset()
    //
    // RED:    reset() does not exist → error
    // GREEN:  unset($this->hits[$key]); → test 4a passes immediately
    // REFACTOR: none needed — already clean
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * TEST 4a: After reset, count returns to zero.
     */
    public function testResetClearsHitCountForKey(): void
    {
        $this->limiter->hit('user:42');
        $this->limiter->hit('user:42');
        $this->assertSame(2, $this->limiter->getCount('user:42')); // pre-condition

        $this->limiter->reset('user:42');

        $this->assertSame(0, $this->limiter->getCount('user:42'));
    }

    /**
     * TEST 4b: After reset, isAllowed() returns true again.
     *
     * The most useful consequence of reset: a blocked key becomes unblocked.
     */
    public function testResetAllowsKeyThatWasPreviouslyBlocked(): void
    {
        $this->limiter->hit('user:42');
        $this->limiter->hit('user:42');
        $this->limiter->hit('user:42');
        $this->assertFalse($this->limiter->isAllowed('user:42')); // pre-condition

        $this->limiter->reset('user:42');

        $this->assertTrue($this->limiter->isAllowed('user:42'));
    }

    /**
     * TEST 4c: Resetting one key does not affect others.
     */
    public function testResetOnlyAffectsTheSpecifiedKey(): void
    {
        $this->limiter->hit('user:42');
        $this->limiter->hit('user:99');
        $this->limiter->hit('user:99');

        $this->limiter->reset('user:42');

        $this->assertSame(0, $this->limiter->getCount('user:42')); // reset
        $this->assertSame(2, $this->limiter->getCount('user:99')); // untouched
    }

    // ══════════════════════════════════════════════════════════════════════════
    // BONUS: What TDD revealed about the design
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * The tests wrote the API for us:
     *   - generateKey(string): string
     *   - hit(string): void
     *   - getCount(string): int
     *   - isAllowed(string): bool
     *   - reset(string): void
     *
     * The limit is passed to the constructor — discovered because the tests
     * needed a predictable, small limit (3) rather than a production default.
     *
     * The in-memory $hits array was the natural data structure — the tests
     * never needed persistence, so none was added.
     *
     * This is TDD's promise: you build exactly what the tests require.
     * No more, no less.
     */
    public function testDefaultLimitIsConfigurableViaConstructor(): void
    {
        $strictLimiter  = new RateLimiter(limit: 1);
        $generousLimiter = new RateLimiter(limit: 100);

        $strictLimiter->hit('x');
        $generousLimiter->hit('x');

        $this->assertFalse($strictLimiter->isAllowed('x'));  // 1 hit = at limit
        $this->assertTrue($generousLimiter->isAllowed('x')); // 1 hit < 100
    }
}