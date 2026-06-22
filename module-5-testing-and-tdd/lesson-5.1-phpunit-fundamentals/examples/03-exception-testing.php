<?php
declare(strict_types=1);

/**
 * Example 03 — Exception Testing
 * --------------------------------
 * Run via PHPUnit:
 *   ./vendor/bin/phpunit module-5-testing-and-tdd/lesson-5.1-phpunit-fundamentals/examples/03-exception-testing.php
 *
 * Testing exceptions is one of the most important testing skills.
 * A class that accepts bad input silently is a bug waiting to happen.
 * Testing that exceptions are thrown verifies that input guards work.
 *
 * This example covers:
 *   A. expectException() — assert the right exception type is thrown
 *   B. expectExceptionMessage() — assert the message contains meaningful text
 *   C. expectExceptionCode() — assert the right error code
 *   D. The ordering rule — expectations BEFORE the throwing call
 *   E. Testing that NO exception is thrown (the success path)
 *   F. Common mistakes to avoid
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

// ─────────────────────────────────────────────────────────────────────────────
// The classes under test
// ─────────────────────────────────────────────────────────────────────────────

class BankAccount
{
    private int $balanceCents;

    public function __construct(int $initialBalanceCents = 0)
    {
        if ($initialBalanceCents < 0) {
            throw new \InvalidArgumentException(
                "Initial balance cannot be negative, got {$initialBalanceCents}",
                code: 1001
            );
        }
        $this->balanceCents = $initialBalanceCents;
    }

    public function deposit(int $amountCents): void
    {
        if ($amountCents <= 0) {
            throw new \InvalidArgumentException(
                "Deposit amount must be positive, got {$amountCents}",
                code: 1002
            );
        }
        $this->balanceCents += $amountCents;
    }

    public function withdraw(int $amountCents): void
    {
        if ($amountCents <= 0) {
            throw new \InvalidArgumentException(
                "Withdrawal amount must be positive, got {$amountCents}",
                code: 1003
            );
        }
        if ($amountCents > $this->balanceCents) {
            throw new \RuntimeException(
                "Insufficient funds: balance is {$this->balanceCents}, requested {$amountCents}",
                code: 1004
            );
        }
        $this->balanceCents -= $amountCents;
    }

    public function getBalanceCents(): int { return $this->balanceCents; }
}

class AgeValidator
{
    public function validate(int $age): void
    {
        if ($age < 0) {
            throw new \RangeException("Age cannot be negative: {$age}");
        }
        if ($age > 150) {
            throw new \RangeException("Age is implausibly large: {$age}");
        }
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// The test class
// ─────────────────────────────────────────────────────────────────────────────

class ExceptionTestingExampleTest extends TestCase
{
    // ═══════════════════════════════════════════════════════════
    // PART A — expectException()
    // ═══════════════════════════════════════════════════════════

    /**
     * The basic pattern:
     *   1. Call $this->expectException(ExceptionClass::class)
     *   2. Then call the code that should throw
     *
     * PHPUnit marks the test FAILED if the exception is NOT thrown.
     * PHPUnit marks the test FAILED if a DIFFERENT exception is thrown.
     * PHPUnit marks the test PASSED if the expected exception IS thrown.
     */
    public function testWithdrawThrowsRuntimeExceptionForInsufficientFunds(): void
    {
        $account = new BankAccount(initialBalanceCents: 5000); // R50.00

        // Declare BEFORE the throwing call
        $this->expectException(\RuntimeException::class);

        $account->withdraw(10000); // R100.00 — more than balance
    }

    public function testNegativeInitialBalanceThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new BankAccount(initialBalanceCents: -1);
    }

    public function testDepositOfZeroThrowsInvalidArgumentException(): void
    {
        $account = new BankAccount();

        $this->expectException(\InvalidArgumentException::class);

        $account->deposit(0);
    }

    // ═══════════════════════════════════════════════════════════
    // PART B — expectExceptionMessage()
    // ═══════════════════════════════════════════════════════════

    /**
     * expectExceptionMessage() checks that the exception message CONTAINS
     * the given string. You do not need to match the full message exactly
     * — just enough to confirm it is meaningful.
     *
     * Good messages help developers diagnose failures quickly.
     * Testing the message verifies that the error communication is correct.
     */
    public function testWithdrawMessageIncludesBalanceAndRequestedAmount(): void
    {
        $account = new BankAccount(initialBalanceCents: 5000);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient funds');

        $account->withdraw(10000);
    }

    public function testNegativeDepositMessageIncludesAmount(): void
    {
        $account = new BankAccount();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be positive');

        $account->deposit(-100);
    }

    /**
     * You can stack expectException() and expectExceptionMessage() —
     * both must be satisfied for the test to pass.
     */
    public function testNegativeInitialBalanceMessageAndType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be negative');

        new BankAccount(-500);
    }

    // ═══════════════════════════════════════════════════════════
    // PART C — expectExceptionCode()
    // ═══════════════════════════════════════════════════════════

    /**
     * Exception codes are useful when callers need to programmatically
     * distinguish between error types (e.g. in an API error handler).
     * Testing the code verifies the contract for API consumers.
     */
    public function testInsufficientFundsExceptionCode(): void
    {
        $account = new BankAccount(5000);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1004);

        $account->withdraw(10000);
    }

    public function testNegativeInitialBalanceExceptionCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1001);

        new BankAccount(-1);
    }

    // ═══════════════════════════════════════════════════════════
    // PART D — The ordering rule
    // ═══════════════════════════════════════════════════════════

    /**
     * IMPORTANT: expectException() must be called BEFORE the code that throws.
     *
     * Why? PHPUnit registers a handler that catches the exception when it is
     * thrown. If you call expectException() AFTER the throw, the exception
     * is already gone and PHPUnit never sees it.
     *
     * This is the most common exception-testing mistake:
     *
     *   ❌ WRONG:
     *   $account->withdraw(10000);               // throws here
     *   $this->expectException(\RuntimeException::class); // too late!
     *
     *   ✅ CORRECT:
     *   $this->expectException(\RuntimeException::class); // must be first
     *   $account->withdraw(10000);               // then the throwing call
     */
    public function testOrderingRuleIsCorrect(): void
    {
        $account = new BankAccount(1000);

        // ✅ expectException BEFORE the call
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient funds');
        $this->expectExceptionCode(1004);

        $account->withdraw(5000);  // throws — all three expectations checked
    }

    // ═══════════════════════════════════════════════════════════
    // PART E — Testing that NO exception is thrown
    // ═══════════════════════════════════════════════════════════

    /**
     * Sometimes you need to verify that a valid path does NOT throw.
     * Simply call the method without expectException() — if it throws,
     * PHPUnit marks the test as Error (unexpected exception).
     *
     * If you want to be explicit, wrap in try/catch and use fail():
     */
    public function testValidDepositDoesNotThrow(): void
    {
        $account = new BankAccount(1000);

        // No expectException() — any thrown exception fails the test
        $account->deposit(500);

        // Verify the happy-path result
        $this->assertSame(1500, $account->getBalanceCents());
    }

    public function testWithdrawWithinBalanceDoesNotThrow(): void
    {
        $account = new BankAccount(10000);

        $account->withdraw(5000);

        $this->assertSame(5000, $account->getBalanceCents());
    }

    /**
     * Using try/catch + fail() makes "should not throw" tests explicit.
     * This is optional but makes intent clearer in some cases.
     */
    public function testValidAccountCreationDoesNotThrow(): void
    {
        try {
            $account = new BankAccount(0);
            $this->assertSame(0, $account->getBalanceCents());
        } catch (\Exception $e) {
            $this->fail("Expected no exception, but caught: " . get_class($e) . ': ' . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════
    // PART F — Data provider with exception testing
    // ═══════════════════════════════════════════════════════════

    /**
     * Data providers combine well with exception testing when the same
     * exception is expected for multiple invalid inputs.
     */
    #[DataProvider('invalidDepositAmounts')]
    public function testDepositThrowsForInvalidAmount(int $amount): void
    {
        $account = new BankAccount();

        $this->expectException(\InvalidArgumentException::class);

        $account->deposit($amount);
    }

    public static function invalidDepositAmounts(): array
    {
        return [
            'zero'     => [0],
            'negative' => [-1],
            'very negative' => [-99999],
        ];
    }

    #[DataProvider('invalidAges')]
    public function testAgeValidatorThrowsForOutOfRangeAge(int $age): void
    {
        $validator = new AgeValidator();

        $this->expectException(\RangeException::class);

        $validator->validate($age);
    }

    public static function invalidAges(): array
    {
        return [
            'negative'        => [-1],
            'very negative'   => [-100],
            'implausibly old' => [151],
            'absurd'          => [999],
        ];
    }

    // ═══════════════════════════════════════════════════════════
    // PART G — Testing exception hierarchy
    // ═══════════════════════════════════════════════════════════

    /**
     * expectException() also passes for subclass exceptions.
     * \InvalidArgumentException extends \LogicException extends \Exception.
     * This test passes because InvalidArgumentException IS a LogicException.
     *
     * Prefer using the most specific exception class in your assertions.
     */
    public function testExceptionHierarchy(): void
    {
        $this->expectException(\LogicException::class); // parent class

        new BankAccount(-1); // throws InvalidArgumentException (a LogicException)
    }
}