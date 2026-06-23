<?php
declare(strict_types=1);

/**
 * CHALLENGE STARTER — Lesson 5.3: Test-Driven Development
 * ─────────────────────────────────────────────────────────
 * Read CHALLENGE.md before touching this file.
 *
 * The rules:
 *   1. Write a test for the next behaviour
 *   2. Run it — confirm it FAILS (red)
 *   3. Write ONLY enough code to make it pass (green)
 *   4. Run all tests — confirm they all pass
 *   5. Refactor if needed (tests must stay green)
 *   6. Repeat from step 1
 *
 * Everything goes in this one file:
 *   - Interfaces (top)
 *   - PasswordResetService class (middle)
 *   - PasswordResetServiceTest class (bottom)
 *
 * Do NOT look at solution/PasswordResetServiceTest.php until all 5
 * cycles are complete and all tests are green.
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// TODO: Define your interfaces here
// Shape them as you write the anonymous class doubles in your tests.
// ─────────────────────────────────────────────────────────────────────────────

// interface TokenRepositoryInterface { ... }
// interface ClockInterface           { ... }


// ─────────────────────────────────────────────────────────────────────────────
// TODO: Implement PasswordResetService here
// Grow this class one method at a time — add each method only when a test demands it.
// ─────────────────────────────────────────────────────────────────────────────

// class PasswordResetService { ... }


// ─────────────────────────────────────────────────────────────────────────────
// Test class — write your tests here
// ─────────────────────────────────────────────────────────────────────────────

class PasswordResetServiceTest extends TestCase
{
    // ── Shared state ─────────────────────────────────────────────────────────
    // TODO: declare typed properties for your service, repo, and clock doubles

    protected function setUp(): void
    {
        // TODO: create fresh doubles and a fresh PasswordResetService before each test
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Cycle 1 — generateToken()
    // Write: test → fail → implement → pass → refactor
    // ─────────────────────────────────────────────────────────────────────────

    // public function testGenerateTokenReturnsAString(): void {}
    // public function testGenerateTokenReturnsExactly64Characters(): void {}
    // public function testGenerateTokenReturnsDifferentTokenOnEachCall(): void {}


    // ─────────────────────────────────────────────────────────────────────────
    // Cycle 2 — storeToken()
    // ─────────────────────────────────────────────────────────────────────────

    // public function testStoreTokenCallsRepositoryStoreMethod(): void {}
    // public function testStoreTokenStoresTheCorrectEmailAndToken(): void {}
    // public function testStoreTokenSetsExpiryInTheFutureRelativeToClock(): void {}


    // ─────────────────────────────────────────────────────────────────────────
    // Cycle 3 — isTokenValid() — valid cases
    // ─────────────────────────────────────────────────────────────────────────

    // public function testIsTokenValidReturnsTrueForMatchingUnexpiredToken(): void {}
    // public function testIsTokenValidReturnsFalseForWrongToken(): void {}
    // public function testIsTokenValidReturnsFalseWhenNoRecordExists(): void {}


    // ─────────────────────────────────────────────────────────────────────────
    // Cycle 4 — isTokenValid() — expired token
    // ─────────────────────────────────────────────────────────────────────────

    // public function testIsTokenValidReturnsFalseForExpiredToken(): void {}


    // ─────────────────────────────────────────────────────────────────────────
    // Cycle 5 — invalidateToken()
    // ─────────────────────────────────────────────────────────────────────────

    // public function testInvalidateTokenMakesPreviouslyValidTokenInvalid(): void {}
}