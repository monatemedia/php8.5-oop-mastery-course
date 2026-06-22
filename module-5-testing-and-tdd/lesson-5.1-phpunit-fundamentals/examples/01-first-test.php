<?php
declare(strict_types=1);

/**
 * Example 01 — The Anatomy of a Test Class
 * ------------------------------------------
 * Run this file via PHPUnit (not directly with php):
 *   ./vendor/bin/phpunit module-5-testing-and-tdd/lesson-5.1-phpunit-fundamentals/examples/01-first-test.php
 *
 * What this example covers:
 *   A. The minimum structure every test class needs
 *   B. The three-part test structure: Arrange → Act → Assert
 *   C. Naming conventions for test methods
 *   D. How PHPUnit discovers and runs tests
 *   E. The #[Test] attribute as an alternative to the test prefix
 *
 * The class under test: a simple Calculator. Nothing more complex than
 * needed — this example is about the TEST structure, not the subject.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

// ─────────────────────────────────────────────────────────────────────────────
// The class under test — simple enough to understand at a glance
// ─────────────────────────────────────────────────────────────────────────────

class Calculator
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    public function subtract(int $a, int $b): int
    {
        return $a - $b;
    }

    public function multiply(int $a, int $b): int
    {
        return $a * $b;
    }

    public function divide(int $a, int $b): float
    {
        if ($b === 0) {
            throw new \DivisionByZeroError('Cannot divide by zero');
        }
        return $a / $b;
    }

    public function isPositive(int $n): bool
    {
        return $n > 0;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// The test class
// Rules:
//   1. Must extend TestCase
//   2. Class name ends with 'Test' (convention — not enforced by PHPUnit)
//   3. Test methods are public and named test* OR carry #[Test]
//   4. Each test method should assert exactly one behaviour
// ─────────────────────────────────────────────────────────────────────────────

class CalculatorTest extends TestCase
{
    // ═══════════════════════════════════════════════════════════
    // PART A — Minimum structure
    // ═══════════════════════════════════════════════════════════

    /**
     * The simplest possible test:
     *   1. Create the subject
     *   2. Call the method
     *   3. Assert the result
     *
     * PHPUnit discovers this because the method name starts with 'test'.
     */
    public function testAddReturnsSumOfTwoIntegers(): void
    {
        // Arrange — create the object under test and any inputs
        $calculator = new Calculator();

        // Act — call the method being tested
        $result = $calculator->add(2, 3);

        // Assert — verify the output matches expectations
        $this->assertSame(5, $result);
    }

    // ═══════════════════════════════════════════════════════════
    // PART B — The three-part structure (Arrange → Act → Assert)
    // ═══════════════════════════════════════════════════════════

    /**
     * Arrange, Act, Assert (AAA) is the standard test layout.
     * Blank lines between the three parts improve readability.
     * Some developers also use "Given → When → Then" language.
     */
    public function testSubtractReturnsCorrectDifference(): void
    {
        // Arrange
        $calculator = new Calculator();
        $minuend    = 10;
        $subtrahend = 4;

        // Act
        $result = $calculator->subtract($minuend, $subtrahend);

        // Assert
        $this->assertSame(6, $result);
    }

    public function testMultiplyReturnsProduct(): void
    {
        // Arrange
        $calculator = new Calculator();

        // Act
        $result = $calculator->multiply(6, 7);

        // Assert
        $this->assertSame(42, $result);
    }

    // ═══════════════════════════════════════════════════════════
    // PART C — Naming conventions
    // ═══════════════════════════════════════════════════════════

    /**
     * Pattern: test[Subject][Behaviour][Context]
     *
     * Good names tell you EXACTLY what failed when a test goes red.
     *   ❌  testDivide()              — what about divide? which case?
     *   ✅  testDivideReturnsFraction — the specific behaviour being verified
     */
    public function testDivideReturnsFractionWhenResultIsNotWhole(): void
    {
        $calculator = new Calculator();

        $result = $calculator->divide(7, 2);

        $this->assertEqualsWithDelta(3.5, $result, delta: 0.001);
    }

    public function testIsPositiveReturnsTrueForPositiveInteger(): void
    {
        $calculator = new Calculator();

        $this->assertTrue($calculator->isPositive(1));
    }

    public function testIsPositiveReturnsFalseForZero(): void
    {
        $calculator = new Calculator();

        $this->assertFalse($calculator->isPositive(0));
    }

    public function testIsPositiveReturnsFalseForNegativeInteger(): void
    {
        $calculator = new Calculator();

        $this->assertFalse($calculator->isPositive(-1));
    }

    // ═══════════════════════════════════════════════════════════
    // PART D — The #[Test] attribute
    // ═══════════════════════════════════════════════════════════

    /**
     * The #[Test] attribute (PHP 8.0+) is an alternative to the test prefix.
     * It allows natural-language method names with underscores.
     * Both styles work — pick one and stay consistent within a project.
     */
    #[Test]
    public function add_returns_zero_when_both_operands_are_zero(): void
    {
        $calculator = new Calculator();

        $result = $calculator->add(0, 0);

        $this->assertSame(0, $result);
    }

    #[Test]
    public function multiply_by_zero_always_returns_zero(): void
    {
        $calculator = new Calculator();

        $this->assertSame(0, $calculator->multiply(999, 0));
        $this->assertSame(0, $calculator->multiply(0, 999));
    }

    // ═══════════════════════════════════════════════════════════
    // PART E — One assertion per test (the ideal)
    // ═══════════════════════════════════════════════════════════

    /**
     * Each test should verify ONE behaviour.
     * If a test has many assertions across unrelated behaviours,
     * split it into separate tests.
     *
     * Multiple assertions CAN appear when they all verify the same
     * single behaviour from different angles (e.g. testing both
     * the return value and a side effect of one operation).
     *
     * This test is FINE — both assertions verify "multiply by zero":
     */
    #[Test]
    public function add_is_commutative(): void
    {
        $calc = new Calculator();

        // Both assertions verify the same property: a + b === b + a
        $this->assertSame($calc->add(3, 7), $calc->add(7, 3));
        $this->assertSame($calc->add(100, 1), $calc->add(1, 100));
    }
}