<?php
declare(strict_types=1);

/**
 * CHALLENGE STARTER — Lesson 5.2: Unit Testing with Fakes and Stubs
 * ──────────────────────────────────────────────────────────────────
 * Read CHALLENGE.md before touching this file.
 * Read challenge/OrderService.php end-to-end before writing any test.
 *
 * Your job: write a complete unit test suite for OrderService using
 * anonymous class test doubles. No mocking framework.
 */

require_once __DIR__ . '/../OrderService.php';

use PHPUnit\Framework\TestCase;

class OrderServiceTest extends TestCase
{
    // ── Shared doubles — create fresh instances in setUp() ───────────────────

    // TODO: declare typed properties for your doubles and the service
    // private OrderService $service;
    // private object $spyMailer;
    // private object $fakeProducts;

    protected function setUp(): void
    {
        // TODO Task 1: create your fake product repo, spy mailer, stub gateway,
        // and wire them into a fresh OrderService.
        //
        // Fake repo: returns the product below for ID 1, null for anything else
        //   ['id' => 1, 'name' => 'Widget Pro', 'price' => 29999, 'sku' => 'WDG-001']
        //
        // Spy mailer: records every send() call in a public $sent array
        //
        // Stub gateway: always returns true
    }


    // ─────────────────────────────────────────────────────────────────────────
    // TODO Task 2 — Success path
    // ─────────────────────────────────────────────────────────────────────────

    // public function testPlaceOrderReturnsSuccessTrueWhenAllDependenciesSucceed(): void {}
    // public function testPlaceOrderReturnsNonNullIntegerOrderIdOnSuccess(): void {}
    // public function testPlaceOrderReturnsCorrectTotalCents(): void {}
    // public function testPlaceOrderSendsExactlyOneEmailOnSuccess(): void {}
    // public function testPlaceOrderSendsEmailToTheCustomerEmailAddress(): void {}
    // public function testPlaceOrderEmailSubjectContainsProductName(): void {}


    // ─────────────────────────────────────────────────────────────────────────
    // TODO Task 3 — Payment declined path
    // ─────────────────────────────────────────────────────────────────────────

    // public function testPlaceOrderReturnsFailureWhenPaymentIsDeclined(): void {}
    // public function testNoEmailSentWhenPaymentIsDeclined(): void {}


    // ─────────────────────────────────────────────────────────────────────────
    // TODO Task 4 — Product not found path
    // ─────────────────────────────────────────────────────────────────────────

    // public function testPlaceOrderThrowsDomainExceptionWhenProductNotFound(): void {}
    // public function testNoEmailSentWhenProductIsNotFound(): void {}


    // ─────────────────────────────────────────────────────────────────────────
    // TODO Task 5 — Gateway throws
    // ─────────────────────────────────────────────────────────────────────────

    // public function testPlaceOrderPropagatesRuntimeExceptionFromGateway(): void {}


    // ─────────────────────────────────────────────────────────────────────────
    // TODO Task 6 — calculateTotal()
    // ─────────────────────────────────────────────────────────────────────────

    // public function testCalculateTotalReturnsPriceTimesQtyForValidProduct(): void {}
    // public function testCalculateTotalThrowsDomainExceptionForUnknownProductId(): void {}


    // ─────────────────────────────────────────────────────────────────────────
    // TODO Task 7 — Email content
    // ─────────────────────────────────────────────────────────────────────────

    // public function testEmailBodyContainsProductName(): void {}
    // public function testEmailBodyContainsFormattedTotalAmount(): void {}
}