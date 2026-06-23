<?php
declare(strict_types=1);

/**
 * CHALLENGE SOLUTION — Lesson 5.2: Unit Testing with Fakes and Stubs
 * ────────────────────────────────────────────────────────────────────
 * ⚠️  Only open this file after completing starter/OrderServiceTest.php yourself.
 *
 * Key things to compare with your solution:
 *   1. setUp() wires all four double types into the service
 *   2. Failure-path tests create inline stubs that override the default gateway
 *   3. The spy is inspected AFTER running the service — not before
 *   4. expectException() always comes BEFORE the throwing call
 *   5. Null Object helpers (nullLogger etc.) reduce boilerplate cleanly
 */

require_once __DIR__ . '/../OrderService.php';

use PHPUnit\Framework\TestCase;

class OrderServiceTest extends TestCase
{
    // ── Shared state ─────────────────────────────────────────────────────────

    private OrderService $service;

    /** Spy — inspect $this->spyMailer->sent after running the service */
    private object $spyMailer;

    /** Fake — returns product ID 1 from an in-memory store */
    private object $fakeProducts;

    // ─────────────────────────────────────────────────────────────────────────
    // Task 1 — setUp: fresh doubles + fresh service before every test
    // ─────────────────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        // ── Fake product repository ───────────────────────────────────────────
        // Returns the seeded product for ID 1; null for everything else.
        // A fake has real internal logic — the in-memory store.
        $this->fakeProducts = new class implements ProductRepositoryInterface {
            private array $store = [
                1 => ['id' => 1, 'name' => 'Widget Pro', 'price' => 29999, 'sku' => 'WDG-001'],
            ];

            public function findById(int $id): ?array {
                return $this->store[$id] ?? null;
            }
        };

        // ── Spy mailer ────────────────────────────────────────────────────────
        // Records every send() call. Tests read $this->spyMailer->sent.
        $this->spyMailer = new class implements MailerInterface {
            public array $sent = [];

            public function send(string $to, string $subject, string $body): bool {
                $this->sent[] = compact('to', 'subject', 'body');
                return true;
            }
        };

        // ── Stub gateway (happy path) ─────────────────────────────────────────
        // Always succeeds. Tests that need failure create their own inline stub.
        $stubGateway = new class implements PaymentGatewayInterface {
            public function charge(int $amountCents, string $token): bool {
                return true;
            }
        };

        // ── Wire up the service ───────────────────────────────────────────────
        $this->service = new OrderService(
            $this->fakeProducts,
            $stubGateway,
            $this->spyMailer
        );
    }


    // ─────────────────────────────────────────────────────────────────────────
    // Task 2 — Success path
    // ─────────────────────────────────────────────────────────────────────────

    public function testPlaceOrderReturnsSuccessTrueWhenAllDependenciesSucceed(): void
    {
        $result = $this->service->placeOrder(1, 1, 'tok_visa_4242', 'alice@example.com');

        $this->assertTrue($result['success']);
    }

    public function testPlaceOrderReturnsNonNullIntegerOrderIdOnSuccess(): void
    {
        $result = $this->service->placeOrder(1, 1, 'tok_visa_4242', 'alice@example.com');

        $this->assertNotNull($result['order_id']);
        $this->assertIsInt($result['order_id']);
    }

    public function testPlaceOrderReturnsCorrectTotalCentsForQtyOne(): void
    {
        $result = $this->service->placeOrder(1, 1, 'tok_visa_4242', 'alice@example.com');

        // price = 29999, qty = 1 → total = 29999
        $this->assertSame(29999, $result['total_cents']);
    }

    public function testPlaceOrderReturnsCorrectTotalCentsForQtyTwo(): void
    {
        $result = $this->service->placeOrder(1, 2, 'tok_visa_4242', 'alice@example.com');

        // price = 29999, qty = 2 → total = 59998
        $this->assertSame(59998, $result['total_cents']);
    }

    public function testPlaceOrderErrorIsNullOnSuccess(): void
    {
        $result = $this->service->placeOrder(1, 1, 'tok_visa_4242', 'alice@example.com');

        $this->assertNull($result['error']);
    }

    public function testPlaceOrderSendsExactlyOneEmailOnSuccess(): void
    {
        $this->service->placeOrder(1, 1, 'tok_visa_4242', 'alice@example.com');

        $this->assertCount(1, $this->spyMailer->sent);
    }

    public function testPlaceOrderSendsEmailToTheCustomerEmailAddress(): void
    {
        $this->service->placeOrder(1, 1, 'tok_visa_4242', 'alice@example.com');

        $this->assertSame('alice@example.com', $this->spyMailer->sent[0]['to']);
    }

    public function testPlaceOrderEmailSubjectContainsProductName(): void
    {
        $this->service->placeOrder(1, 1, 'tok_visa_4242', 'alice@example.com');

        $this->assertStringContainsString('Widget Pro', $this->spyMailer->sent[0]['subject']);
    }


    // ─────────────────────────────────────────────────────────────────────────
    // Task 3 — Payment declined path
    // ─────────────────────────────────────────────────────────────────────────

    public function testPlaceOrderReturnsFailureWhenPaymentIsDeclined(): void
    {
        // Inline failing stub — overrides the happy-path gateway from setUp()
        $decliningGateway = new class implements PaymentGatewayInterface {
            public function charge(int $amountCents, string $token): bool {
                return false;
            }
        };

        $service = new OrderService($this->fakeProducts, $decliningGateway, $this->spyMailer);

        $result = $service->placeOrder(1, 1, 'tok_declined', 'alice@example.com');

        $this->assertFalse($result['success']);
        $this->assertSame('Payment declined', $result['error']);
        $this->assertNull($result['order_id']);
    }

    public function testNoEmailSentWhenPaymentIsDeclined(): void
    {
        $decliningGateway = new class implements PaymentGatewayInterface {
            public function charge(int $amountCents, string $token): bool { return false; }
        };

        $service = new OrderService($this->fakeProducts, $decliningGateway, $this->spyMailer);

        $service->placeOrder(1, 1, 'tok_declined', 'alice@example.com');

        $this->assertEmpty($this->spyMailer->sent);
    }


    // ─────────────────────────────────────────────────────────────────────────
    // Task 4 — Product not found path
    // ─────────────────────────────────────────────────────────────────────────

    public function testPlaceOrderThrowsDomainExceptionWhenProductNotFound(): void
    {
        $this->expectException(\DomainException::class);

        $this->service->placeOrder(productId: 999, qty: 1, paymentToken: 'tok', customerEmail: 'alice@example.com');
    }

    public function testDomainExceptionMessageContainsTheProductId(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('999');

        $this->service->placeOrder(productId: 999, qty: 1, paymentToken: 'tok', customerEmail: 'alice@example.com');
    }

    public function testNoEmailSentWhenProductIsNotFound(): void
    {
        try {
            $this->service->placeOrder(productId: 999, qty: 1, paymentToken: 'tok', customerEmail: 'alice@example.com');
        } catch (\DomainException) {
            // Expected — we only care about the spy
        }

        $this->assertEmpty($this->spyMailer->sent);
    }


    // ─────────────────────────────────────────────────────────────────────────
    // Task 5 — Gateway throws (infrastructure failure)
    // ─────────────────────────────────────────────────────────────────────────

    public function testPlaceOrderPropagatesRuntimeExceptionFromGateway(): void
    {
        $throwingGateway = new class implements PaymentGatewayInterface {
            public function charge(int $amountCents, string $token): bool {
                throw new \RuntimeException('Payment gateway unreachable');
            }
        };

        $service = new OrderService($this->fakeProducts, $throwingGateway, $this->spyMailer);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Payment gateway unreachable');

        $service->placeOrder(1, 1, 'tok', 'alice@example.com');
    }

    public function testNoEmailSentBeforeGatewayExceptionEscapes(): void
    {
        $throwingGateway = new class implements PaymentGatewayInterface {
            public function charge(int $amountCents, string $token): bool {
                throw new \RuntimeException('Network failure');
            }
        };

        $service = new OrderService($this->fakeProducts, $throwingGateway, $this->spyMailer);

        try {
            $service->placeOrder(1, 1, 'tok', 'alice@example.com');
        } catch (\RuntimeException) {
            // Expected
        }

        $this->assertEmpty($this->spyMailer->sent);
    }


    // ─────────────────────────────────────────────────────────────────────────
    // Task 6 — calculateTotal()
    // ─────────────────────────────────────────────────────────────────────────

    public function testCalculateTotalReturnsPriceTimesQtyForValidProduct(): void
    {
        $total = $this->service->calculateTotal(productId: 1, qty: 3);

        // 29999 × 3 = 89997
        $this->assertSame(89997, $total);
    }

    public function testCalculateTotalReturnsSingleUnitPriceForQtyOne(): void
    {
        $total = $this->service->calculateTotal(productId: 1, qty: 1);

        $this->assertSame(29999, $total);
    }

    public function testCalculateTotalThrowsDomainExceptionForUnknownProductId(): void
    {
        $this->expectException(\DomainException::class);

        $this->service->calculateTotal(productId: 42, qty: 1);
    }

    public function testCalculateTotalExceptionMessageContainsProductId(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('42');

        $this->service->calculateTotal(productId: 42, qty: 1);
    }


    // ─────────────────────────────────────────────────────────────────────────
    // Task 7 — Email content (via spy)
    // ─────────────────────────────────────────────────────────────────────────

    public function testEmailBodyContainsProductName(): void
    {
        $this->service->placeOrder(1, 1, 'tok', 'alice@example.com');

        $body = $this->spyMailer->sent[0]['body'];

        $this->assertStringContainsString('Widget Pro', $body);
    }

    public function testEmailBodyContainsFormattedTotalAmount(): void
    {
        $this->service->placeOrder(1, 2, 'tok', 'alice@example.com');

        $body = $this->spyMailer->sent[0]['body'];

        // total = 29999 × 2 = 59998 cents = R599.98
        $this->assertStringContainsString('599.98', $body);
    }

    public function testEmailBodyContainsQuantityOrdered(): void
    {
        $this->service->placeOrder(1, 3, 'tok', 'alice@example.com');

        $body = $this->spyMailer->sent[0]['body'];

        $this->assertStringContainsString('3', $body);
    }

    public function testEmailBodyContainsProductSku(): void
    {
        $this->service->placeOrder(1, 1, 'tok', 'alice@example.com');

        $body = $this->spyMailer->sent[0]['body'];

        $this->assertStringContainsString('WDG-001', $body);
    }
}