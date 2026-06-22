<?php
declare(strict_types=1);

/**
 * CHALLENGE SOLUTION — Lesson 5.1: PHPUnit Fundamentals
 * ───────────────────────────────────────────────────────
 * ⚠️  Only open this file after completing starter/MoneyTest.php yourself.
 *
 * Key things to compare in your solution:
 *   1. setUp() creates one reusable Money instance for common tests
 *   2. Exception tests declare expectException() BEFORE the throwing call
 *   3. At least one expectExceptionMessage() is used
 *   4. At least one DataProvider is used
 *   5. Immutability tests verify the original is unchanged after operations
 *   6. Test names clearly describe behaviour, not implementation
 */

require_once __DIR__ . '/../Money.php';

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

class MoneyTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────────────────
    // Common fixture — reused by most tests
    // ─────────────────────────────────────────────────────────────────────────

    private Money $price;      // R299.99 ZAR
    private Money $small;      // R149.99 ZAR
    private Money $zero;       // R0.00   ZAR

    protected function setUp(): void
    {
        $this->price = new Money(29999, 'ZAR');
        $this->small = new Money(14999, 'ZAR');
        $this->zero  = new Money(0,     'ZAR');
    }


    // ─────────────────────────────────────────────────────────────────────────
    // Task 1 — Constructor: valid inputs
    // ─────────────────────────────────────────────────────────────────────────

    public function testConstructorCreatesMoneyWithValidAmountAndCurrency(): void
    {
        $money = new Money(29999, 'ZAR');

        $this->assertInstanceOf(Money::class, $money);
    }

    public function testConstructorStoresAmountCentsCorrectly(): void
    {
        $this->assertSame(29999, $this->price->amountCents);
    }

    public function testConstructorStoresCurrencyCorrectly(): void
    {
        $this->assertSame('ZAR', $this->price->currency);
    }

    public function testZeroCentsIsAValidAmount(): void
    {
        // Should not throw
        $money = new Money(0, 'ZAR');

        $this->assertSame(0, $money->amountCents);
        $this->assertTrue($money->isZero());
    }

    public function testLargeCentsAmountIsAccepted(): void
    {
        $money = new Money(999_999_999, 'USD');

        $this->assertSame(999_999_999, $money->amountCents);
    }

    public function testDifferentValidCurrenciesAreAccepted(): void
    {
        $usd = new Money(100, 'USD');
        $eur = new Money(100, 'EUR');
        $gbp = new Money(100, 'GBP');

        $this->assertSame('USD', $usd->currency);
        $this->assertSame('EUR', $eur->currency);
        $this->assertSame('GBP', $gbp->currency);
    }


    // ─────────────────────────────────────────────────────────────────────────
    // Task 2 — Constructor: invalid inputs
    // ─────────────────────────────────────────────────────────────────────────

    public function testNegativeAmountThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Money(-1, 'ZAR');
    }

    public function testExceptionMessageMentionsNonNegativeForNegativeAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('non-negative');

        new Money(-500, 'ZAR');
    }

    public function testExceptionMessageIncludesTheInvalidAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('-500');

        new Money(-500, 'ZAR');
    }

    #[DataProvider('invalidCurrencyCodes')]
    public function testInvalidCurrencyCodeThrowsInvalidArgumentException(string $currency): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Money(100, $currency);
    }

    public static function invalidCurrencyCodes(): array
    {
        return [
            'too short — one letter'   => ['Z'],
            'too short — two letters'  => ['ZA'],
            'too long — four letters'  => ['ZARR'],
            'lowercase'                => ['zar'],
            'mixed case'               => ['Zar'],
            'digits'                   => ['123'],
            'empty string'             => [''],
        ];
    }


    // ─────────────────────────────────────────────────────────────────────────
    // Task 3 — add()
    // ─────────────────────────────────────────────────────────────────────────

    public function testAddReturnsSumOfTwoMoneyObjectsWithSameCurrency(): void
    {
        $result = $this->price->add($this->small);

        $this->assertSame(44998, $result->amountCents);
        $this->assertSame('ZAR', $result->currency);
    }

    public function testAddReturnsNewMoneyInstance(): void
    {
        $result = $this->price->add($this->small);

        $this->assertNotSame($this->price, $result);
        $this->assertNotSame($this->small, $result);
    }

    public function testAddingZeroReturnsSameAmount(): void
    {
        $result = $this->price->add($this->zero);

        $this->assertSame(29999, $result->amountCents);
    }

    public function testAddWithDifferentCurrenciesThrowsInvalidArgumentException(): void
    {
        $usd = new Money(100, 'USD');

        $this->expectException(\InvalidArgumentException::class);

        $this->price->add($usd);
    }


    // ─────────────────────────────────────────────────────────────────────────
    // Task 4 — subtract()
    // ─────────────────────────────────────────────────────────────────────────

    public function testSubtractReturnsDifferenceOfTwoMoneyObjects(): void
    {
        $result = $this->price->subtract($this->small);

        $this->assertSame(15000, $result->amountCents); // 29999 - 14999 = 15000
    }

    public function testSubtractingEqualAmountsReturnsZero(): void
    {
        $result = $this->price->subtract($this->price);

        $this->assertSame(0, $result->amountCents);
        $this->assertTrue($result->isZero());
    }

    public function testSubtractingLargerAmountThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->small->subtract($this->price); // 14999 - 29999 = negative
    }

    public function testSubtractExceptionMessageMentionsNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('negative');

        $this->small->subtract($this->price);
    }

    public function testSubtractWithDifferentCurrenciesThrowsInvalidArgumentException(): void
    {
        $eur = new Money(100, 'EUR');

        $this->expectException(\InvalidArgumentException::class);

        $this->price->subtract($eur);
    }


    // ─────────────────────────────────────────────────────────────────────────
    // Task 5 — multiplyBy()
    // ─────────────────────────────────────────────────────────────────────────

    public function testMultiplyByTwoDoublesTheAmount(): void
    {
        $result = $this->price->multiplyBy(2.0);

        $this->assertSame(59998, $result->amountCents);
    }

    public function testMultiplyByHalfApproximatesHalfAmount(): void
    {
        $money  = new Money(10000, 'ZAR'); // R100.00 exactly
        $result = $money->multiplyBy(0.5);

        $this->assertSame(5000, $result->amountCents);
    }

    public function testMultiplyByZeroReturnsZeroCents(): void
    {
        $result = $this->price->multiplyBy(0.0);

        $this->assertSame(0, $result->amountCents);
        $this->assertTrue($result->isZero());
    }

    public function testMultiplyPreservesTheCurrency(): void
    {
        $result = $this->price->multiplyBy(3.0);

        $this->assertSame('ZAR', $result->currency);
    }

    public function testMultiplyRoundsToNearestCent(): void
    {
        $money  = new Money(3, 'ZAR'); // 3 cents
        $result = $money->multiplyBy(1.0 / 3.0); // 1 cent (rounded)

        $this->assertSame(1, $result->amountCents);
    }

    public function testNegativeFactorThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->price->multiplyBy(-1.0);
    }

    public function testNegativeFactorExceptionMessageMentionsFactor(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('non-negative');

        $this->price->multiplyBy(-0.5);
    }


    // ─────────────────────────────────────────────────────────────────────────
    // Task 6 — Comparison methods
    // ─────────────────────────────────────────────────────────────────────────

    public function testEqualsTrueForSameAmountAndCurrency(): void
    {
        $other = new Money(29999, 'ZAR');

        $this->assertTrue($this->price->equals($other));
    }

    public function testEqualsFalseWhenAmountsDiffer(): void
    {
        $this->assertFalse($this->price->equals($this->small));
    }

    public function testEqualsFalseWhenCurrenciesDifferEvenWithSameAmount(): void
    {
        $usd = new Money(29999, 'USD');

        $this->assertFalse($this->price->equals($usd));
    }

    public function testIsGreaterThanReturnsTrueWhenThisExceedsOther(): void
    {
        $this->assertTrue($this->price->isGreaterThan($this->small));
    }

    public function testIsGreaterThanReturnsFalseWhenThisIsLessThanOther(): void
    {
        $this->assertFalse($this->small->isGreaterThan($this->price));
    }

    public function testIsGreaterThanReturnsFalseForEqualAmounts(): void
    {
        $same = new Money(29999, 'ZAR');

        $this->assertFalse($this->price->isGreaterThan($same));
    }

    public function testIsLessThanReturnsTrueWhenThisIsBelowOther(): void
    {
        $this->assertTrue($this->small->isLessThan($this->price));
    }

    public function testIsLessThanReturnsFalseWhenThisExceedsOther(): void
    {
        $this->assertFalse($this->price->isLessThan($this->small));
    }

    public function testIsZeroReturnsTrueForZeroCents(): void
    {
        $this->assertTrue($this->zero->isZero());
    }

    public function testIsZeroReturnsFalseForNonZeroAmount(): void
    {
        $this->assertFalse($this->price->isZero());
    }

    public function testIsGreaterThanWithDifferentCurrenciesThrows(): void
    {
        $usd = new Money(100, 'USD');

        $this->expectException(\InvalidArgumentException::class);

        $this->price->isGreaterThan($usd);
    }

    public function testIsLessThanWithDifferentCurrenciesThrows(): void
    {
        $eur = new Money(100, 'EUR');

        $this->expectException(\InvalidArgumentException::class);

        $this->price->isLessThan($eur);
    }


    // ─────────────────────────────────────────────────────────────────────────
    // Task 7 — format()
    // ─────────────────────────────────────────────────────────────────────────

    public function testFormatReturnsCorrectStringForNonZeroAmount(): void
    {
        $this->assertSame('ZAR 299.99', $this->price->format());
    }

    public function testFormatReturnsZeroWithTwoDecimalPlaces(): void
    {
        $this->assertSame('ZAR 0.00', $this->zero->format());
    }

    public function testFormatIncludesTheCurrencyCode(): void
    {
        $eur = new Money(100000, 'EUR'); // EUR 1000.00

        $this->assertSame('EUR 1000.00', $eur->format());
    }

    #[DataProvider('formatProvider')]
    public function testFormatOutputMatchesExpected(int $cents, string $currency, string $expected): void
    {
        $money = new Money($cents, $currency);

        $this->assertSame($expected, $money->format());
    }

    public static function formatProvider(): array
    {
        return [
            'zero ZAR'            => [0,      'ZAR', 'ZAR 0.00'],
            'R299.99'             => [29999,  'ZAR', 'ZAR 299.99'],
            'R1000.00'            => [100000, 'ZAR', 'ZAR 1000.00'],
            'USD $0.01'           => [1,      'USD', 'USD 0.01'],
            'EUR €10.50'          => [1050,   'EUR', 'EUR 10.50'],
            'large amount'        => [9999999, 'GBP', 'GBP 99,999.99'],
        ];
    }


    // ─────────────────────────────────────────────────────────────────────────
    // Task 8 — Immutability
    // ─────────────────────────────────────────────────────────────────────────

    public function testAddResultIsImmutableOriginalUnchanged(): void
    {
        $originalAmount   = $this->price->amountCents;
        $originalCurrency = $this->price->currency;

        $this->price->add($this->small); // discard result

        $this->assertSame($originalAmount,   $this->price->amountCents);
        $this->assertSame($originalCurrency, $this->price->currency);
    }

    public function testSubtractResultIsImmutableOriginalUnchanged(): void
    {
        $originalAmount = $this->price->amountCents;

        $this->price->subtract($this->small);

        $this->assertSame($originalAmount, $this->price->amountCents);
    }

    public function testMultiplyResultIsImmutableOriginalUnchanged(): void
    {
        $originalAmount = $this->price->amountCents;

        $this->price->multiplyBy(3.0);

        $this->assertSame($originalAmount, $this->price->amountCents);
    }

    public function testAddReturnsDistinctNewInstance(): void
    {
        $result = $this->price->add($this->small);

        // The result is a new object, not the same reference
        $this->assertNotSame($this->price, $result);
        // And its amount is the sum
        $this->assertSame($this->price->amountCents + $this->small->amountCents, $result->amountCents);
    }
}