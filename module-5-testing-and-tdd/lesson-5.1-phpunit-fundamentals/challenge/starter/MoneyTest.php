<?php
declare(strict_types=1);

/**
 * CHALLENGE STARTER — Lesson 5.1: PHPUnit Fundamentals
 * ──────────────────────────────────────────────────────
 * Read CHALLENGE.md before touching this file.
 *
 * Write a complete test suite for the Money value object.
 * The Money class is in challenge/Money.php.
 *
 * Rules:
 *  - Do NOT look at solution/MoneyTest.php until you have made a genuine attempt.
 *  - All tests must pass with ./vendor/bin/phpunit
 *  - Focus on BEHAVIOURS — what Money does — not on its internals
 */

// Adjust the path if your autoloader doesn't cover this file:
require_once __DIR__ . '/../Money.php';

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

class MoneyTest extends TestCase
{
    // TODO Task 1: Use setUp() to create a reusable Money instance
    // private Money $price;
    //
    // protected function setUp(): void
    // {
    //     $this->price = new Money(29999, 'ZAR');
    // }


    // ─────────────────────────────────────────────────────────────────────────
    // TODO Task 1 — Constructor: valid inputs
    // ─────────────────────────────────────────────────────────────────────────

    // public function testConstructorCreatesMoneyWithValidAmountAndCurrency(): void
    // {
    //     ...
    // }


    // ─────────────────────────────────────────────────────────────────────────
    // TODO Task 2 — Constructor: invalid inputs
    // ─────────────────────────────────────────────────────────────────────────

    // public function testNegativeAmountThrowsInvalidArgumentException(): void
    // {
    //     ...
    // }


    // ─────────────────────────────────────────────────────────────────────────
    // TODO Task 3 — add()
    // ─────────────────────────────────────────────────────────────────────────


    // ─────────────────────────────────────────────────────────────────────────
    // TODO Task 4 — subtract()
    // ─────────────────────────────────────────────────────────────────────────


    // ─────────────────────────────────────────────────────────────────────────
    // TODO Task 5 — multiplyBy()
    // ─────────────────────────────────────────────────────────────────────────


    // ─────────────────────────────────────────────────────────────────────────
    // TODO Task 6 — Comparison methods
    // ─────────────────────────────────────────────────────────────────────────


    // ─────────────────────────────────────────────────────────────────────────
    // TODO Task 7 — format()
    // ─────────────────────────────────────────────────────────────────────────


    // ─────────────────────────────────────────────────────────────────────────
    // TODO Task 8 — Immutability
    // ─────────────────────────────────────────────────────────────────────────


    // ─────────────────────────────────────────────────────────────────────────
    // TODO: Add at least one DataProvider
    // ─────────────────────────────────────────────────────────────────────────
}