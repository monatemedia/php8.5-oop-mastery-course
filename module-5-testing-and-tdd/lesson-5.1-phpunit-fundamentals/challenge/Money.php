<?php
declare(strict_types=1);

/**
 * Money — Immutable value object for monetary amounts.
 *
 * This is the class under test for the Lesson 5.1 challenge.
 * Read it carefully before writing any tests.
 *
 * Rules:
 *   - Amount is stored as integer cents (R10.00 = 1000 cents)
 *   - Currency is a 3-letter ISO 4217 code ('ZAR', 'USD', 'EUR')
 *   - All operations return a NEW Money instance — this object is immutable
 *   - Cross-currency arithmetic throws InvalidArgumentException
 *   - Negative amounts are rejected at construction time
 */
readonly class Money
{
    public function __construct(
        public int    $amountCents,
        public string $currency
    ) {
        if ($amountCents < 0) {
            throw new \InvalidArgumentException(
                "Amount must be non-negative, got {$amountCents}"
            );
        }

        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new \InvalidArgumentException(
                "Currency must be a 3-letter ISO code, got '{$currency}'"
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Arithmetic — all return a new Money instance
    // ─────────────────────────────────────────────────────────────────────────

    public function add(Money $other): static
    {
        $this->requireSameCurrency($other, 'add');
        return new static($this->amountCents + $other->amountCents, $this->currency);
    }

    public function subtract(Money $other): static
    {
        $this->requireSameCurrency($other, 'subtract');

        if ($other->amountCents > $this->amountCents) {
            throw new \InvalidArgumentException(
                "Cannot subtract {$other->amountCents} from {$this->amountCents}: result would be negative"
            );
        }

        return new static($this->amountCents - $other->amountCents, $this->currency);
    }

    public function multiplyBy(float $factor): static
    {
        if ($factor < 0) {
            throw new \InvalidArgumentException(
                "Factor must be non-negative, got {$factor}"
            );
        }

        return new static((int) round($this->amountCents * $factor), $this->currency);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Comparison
    // ─────────────────────────────────────────────────────────────────────────

    public function equals(Money $other): bool
    {
        return $this->amountCents === $other->amountCents
            && $this->currency    === $other->currency;
    }

    public function isGreaterThan(Money $other): bool
    {
        $this->requireSameCurrency($other, 'compare');
        return $this->amountCents > $other->amountCents;
    }

    public function isLessThan(Money $other): bool
    {
        $this->requireSameCurrency($other, 'compare');
        return $this->amountCents < $other->amountCents;
    }

    public function isZero(): bool
    {
        return $this->amountCents === 0;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Formatting
    // ─────────────────────────────────────────────────────────────────────────

    public function format(): string
    {
        return $this->currency . ' ' . number_format($this->amountCents / 100, 2);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function requireSameCurrency(Money $other, string $operation): void
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException(
                "Cannot {$operation} {$this->currency} and {$other->currency}"
            );
        }
    }
}