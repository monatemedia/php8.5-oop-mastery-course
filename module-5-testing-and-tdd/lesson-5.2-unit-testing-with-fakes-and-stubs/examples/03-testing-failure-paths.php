<?php
declare(strict_types=1);

/**
 * Example 03 — Testing Failure Paths
 * ------------------------------------
 * Run via PHPUnit:
 *   ./vendor/bin/phpunit module-5-testing-and-tdd/lesson-5.2-unit-testing-with-fakes-and-stubs/examples/03-testing-failure-paths.php
 *
 * Every class has at least two paths through it:
 *   - The success (happy) path — things work as expected
 *   - The failure path(s) — dependencies fail, data is missing, exceptions are thrown
 *
 * Stubs make failure paths trivial to test — set the stub to return a failure
 * value or throw, and verify the class under test handles it correctly.
 *
 * This example covers:
 *   A. Stubs that return failure values (false, null, empty array)
 *   B. Stubs that throw exceptions — simulating infrastructure failures
 *   C. Testing that the class under test propagates exceptions correctly
 *   D. Testing that the class under test CATCHES and WRAPS exceptions
 *   E. Testing cleanup behaviour on failure (spy + failing stub)
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// Contracts
// ─────────────────────────────────────────────────────────────────────────────

interface ProductRepositoryInterface
{
    public function findById(int $id): ?array;
}

interface PaymentGatewayInterface
{
    /** @throws \RuntimeException on network/gateway error */
    public function charge(int $amountCents, string $token): bool;
}

interface InventoryServiceInterface
{
    public function reserve(int $productId, int $qty): bool;
    public function release(int $productId, int $qty): void;
}

interface MailerInterface
{
    public function send(string $to, string $subject, string $body): bool;
}

// ─────────────────────────────────────────────────────────────────────────────
// The class under test
// CheckoutService orchestrates: find product → charge payment → reserve
// inventory → send receipt. Multiple failure modes to test.
// ─────────────────────────────────────────────────────────────────────────────

class CheckoutService
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private PaymentGatewayInterface    $gateway,
        private InventoryServiceInterface  $inventory,
        private MailerInterface            $mailer
    ) {}

    /**
     * @return array{success: bool, order_id: ?int, error: ?string}
     * @throws \DomainException    when the product does not exist
     * @throws \RuntimeException   when the gateway throws (unrecoverable)
     */
    public function checkout(int $productId, int $qty, string $paymentToken, string $email): array
    {
        // Step 1: Find product
        $product = $this->products->findById($productId);
        if ($product === null) {
            throw new \DomainException("Product {$productId} not found");
        }

        // Step 2: Charge payment
        $amountCents = $product['price'] * $qty;
        $charged = $this->gateway->charge($amountCents, $paymentToken);
        if (!$charged) {
            return ['success' => false, 'order_id' => null, 'error' => 'Payment declined'];
        }

        // Step 3: Reserve inventory — if this fails, release the payment is out of scope
        // but we still reflect the failure in the result
        $reserved = $this->inventory->reserve($productId, $qty);
        if (!$reserved) {
            return ['success' => false, 'order_id' => null, 'error' => 'Insufficient stock'];
        }

        // Step 4: Send receipt
        $this->mailer->send(
            $email,
            'Your order is confirmed',
            "You ordered {$qty}x {$product['name']} for R" . number_format($amountCents / 100, 2)
        );

        $orderId = random_int(10000, 99999);
        return ['success' => true, 'order_id' => $orderId, 'error' => null];
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// The test class
// ─────────────────────────────────────────────────────────────────────────────

class TestingFailurePathsExampleTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────────────────
    // Shared happy-path doubles — used as baseline across most tests.
    // Individual tests override the one dependency they need to fail.
    // ─────────────────────────────────────────────────────────────────────────

    private function makeProductRepo(?array $product = null): ProductRepositoryInterface
    {
        $resolved = $product ?? ['id' => 1, 'name' => 'Widget Pro', 'price' => 29999];

        return new class($resolved) implements ProductRepositoryInterface {
            public function __construct(private ?array $product) {}
            public function findById(int $id): ?array { return $this->product; }
        };
    }

    private function makeSuccessGateway(): PaymentGatewayInterface
    {
        return new class implements PaymentGatewayInterface {
            public function charge(int $amountCents, string $token): bool { return true; }
        };
    }

    private function makeSuccessInventory(): InventoryServiceInterface
    {
        return new class implements InventoryServiceInterface {
            public function reserve(int $productId, int $qty): bool { return true; }
            public function release(int $productId, int $qty): void {}
        };
    }

    private function makeNullMailer(): MailerInterface
    {
        return new class implements MailerInterface {
            public function send(string $to, string $subject, string $body): bool { return true; }
        };
    }


    // ═══════════════════════════════════════════════════════════
    // Happy path — baseline before testing failures
    // ═══════════════════════════════════════════════════════════

    public function testCheckoutSucceedsWhenAllDependenciesSucceed(): void
    {
        $service = new CheckoutService(
            $this->makeProductRepo(),
            $this->makeSuccessGateway(),
            $this->makeSuccessInventory(),
            $this->makeNullMailer()
        );

        $result = $service->checkout(
            productId:    1,
            qty:          2,
            paymentToken: 'tok_visa_4242',
            email:        'alice@example.com'
        );

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['order_id']);
        $this->assertNull($result['error']);
    }


    // ═══════════════════════════════════════════════════════════
    // PART A — Stubs that return failure values
    // ═══════════════════════════════════════════════════════════

    /**
     * Product not found: repository returns null.
     * CheckoutService should throw DomainException.
     */
    public function testCheckoutThrowsDomainExceptionWhenProductNotFound(): void
    {
        // Stub: findById returns null — product does not exist
        $notFoundRepo = new class implements ProductRepositoryInterface {
            public function findById(int $id): ?array {
                return null;  // ← failure value
            }
        };

        $service = new CheckoutService(
            $notFoundRepo,
            $this->makeSuccessGateway(),
            $this->makeSuccessInventory(),
            $this->makeNullMailer()
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Product 42 not found');

        $service->checkout(productId: 42, qty: 1, paymentToken: 'tok', email: 'alice@example.com');
    }

    /**
     * Payment declined: gateway returns false (not an exception — just declined).
     * CheckoutService should return a failure result with a meaningful error.
     */
    public function testCheckoutReturnsFailureResultWhenPaymentDeclined(): void
    {
        // Stub: gateway returns false — card declined
        $decliningGateway = new class implements PaymentGatewayInterface {
            public function charge(int $amountCents, string $token): bool {
                return false;  // ← failure value
            }
        };

        $service = new CheckoutService(
            $this->makeProductRepo(),
            $decliningGateway,
            $this->makeSuccessInventory(),
            $this->makeNullMailer()
        );

        $result = $service->checkout(productId: 1, qty: 1, paymentToken: 'tok_declined', email: 'alice@example.com');

        $this->assertFalse($result['success']);
        $this->assertNull($result['order_id']);
        $this->assertSame('Payment declined', $result['error']);
    }

    /**
     * Insufficient stock: inventory returns false.
     */
    public function testCheckoutReturnsFailureResultWhenInventoryCannotReserve(): void
    {
        $noStockInventory = new class implements InventoryServiceInterface {
            public function reserve(int $productId, int $qty): bool {
                return false;  // ← failure value — out of stock
            }
            public function release(int $productId, int $qty): void {}
        };

        $service = new CheckoutService(
            $this->makeProductRepo(),
            $this->makeSuccessGateway(),
            $noStockInventory,
            $this->makeNullMailer()
        );

        $result = $service->checkout(productId: 1, qty: 999, paymentToken: 'tok', email: 'alice@example.com');

        $this->assertFalse($result['success']);
        $this->assertSame('Insufficient stock', $result['error']);
    }


    // ═══════════════════════════════════════════════════════════
    // PART B — Stubs that throw exceptions
    // ═══════════════════════════════════════════════════════════

    /**
     * Gateway throws RuntimeException — a network or infrastructure failure.
     * Unlike a declined card (return false), this is an unrecoverable error.
     * CheckoutService lets the exception propagate.
     */
    public function testCheckoutPropagatesRuntimeExceptionFromGateway(): void
    {
        // Stub that throws — simulates a network timeout
        $throwingGateway = new class implements PaymentGatewayInterface {
            public function charge(int $amountCents, string $token): bool {
                throw new \RuntimeException('Payment gateway timeout after 30s');
            }
        };

        $service = new CheckoutService(
            $this->makeProductRepo(),
            $throwingGateway,
            $this->makeSuccessInventory(),
            $this->makeNullMailer()
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Payment gateway timeout');

        $service->checkout(productId: 1, qty: 1, paymentToken: 'tok', email: 'alice@example.com');
    }

    /**
     * Multiple failure modes with different exception types.
     * Each test isolates one failure — exactly one stub throws or returns failure.
     */
    public function testCheckoutPropagatesConnectionExceptionFromGateway(): void
    {
        $connectionFailGateway = new class implements PaymentGatewayInterface {
            public function charge(int $amountCents, string $token): bool {
                throw new \RuntimeException('Connection refused: payment-gateway:443');
            }
        };

        $service = new CheckoutService(
            $this->makeProductRepo(),
            $connectionFailGateway,
            $this->makeSuccessInventory(),
            $this->makeNullMailer()
        );

        $this->expectException(\RuntimeException::class);

        $service->checkout(productId: 1, qty: 1, paymentToken: 'tok', email: 'alice@example.com');
    }


    // ═══════════════════════════════════════════════════════════
    // PART C — Testing that the class wraps exceptions
    // ═══════════════════════════════════════════════════════════

    /**
     * Some services catch low-level exceptions and wrap them in
     * domain-meaningful ones. Here we show how to test that wrapping.
     *
     * Imagine a variant of CheckoutService that wraps gateway exceptions:
     */
    public function testWrappingExceptionPreservesOriginalMessage(): void
    {
        // This demonstrates the pattern of testing exception wrapping.
        // The class below is a local variant of CheckoutService that wraps exceptions.
        $wrappingService = new class(
            new class implements ProductRepositoryInterface {
                public function findById(int $id): ?array { return ['id' => 1, 'name' => 'Widget', 'price' => 100]; }
            },
            new class implements PaymentGatewayInterface {
                public function charge(int $amountCents, string $token): bool {
                    throw new \RuntimeException('SSL handshake failed');
                }
            }
        ) {
            public function __construct(
                private ProductRepositoryInterface $products,
                private PaymentGatewayInterface    $gateway
            ) {}

            public function checkout(int $productId, string $token): never
            {
                $product = $this->products->findById($productId);
                try {
                    $this->gateway->charge($product['price'], $token);
                } catch (\RuntimeException $e) {
                    throw new \DomainException(
                        "Payment processing failed: " . $e->getMessage(),
                        previous: $e
                    );
                }
            }
        };

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Payment processing failed: SSL handshake failed');

        $wrappingService->checkout(1, 'tok');
    }


    // ═══════════════════════════════════════════════════════════
    // PART D — Testing cleanup behaviour on failure (spy + failing stub)
    // ═══════════════════════════════════════════════════════════

    /**
     * When payment is declined, no email should be sent.
     * Combine a failing stub (gateway returns false) with a spy (mailer).
     * This test verifies TWO things:
     *   1. The result is a failure
     *   2. No email side effect occurred
     */
    public function testNoEmailSentWhenPaymentIsDeclined(): void
    {
        $decliningGateway = new class implements PaymentGatewayInterface {
            public function charge(int $amountCents, string $token): bool { return false; }
        };

        // Spy on the mailer
        $spyMailer = new class implements MailerInterface {
            public array $sent = [];
            public function send(string $to, string $subject, string $body): bool {
                $this->sent[] = compact('to', 'subject', 'body');
                return true;
            }
        };

        $service = new CheckoutService(
            $this->makeProductRepo(),
            $decliningGateway,
            $this->makeSuccessInventory(),
            $spyMailer
        );

        $result = $service->checkout(productId: 1, qty: 1, paymentToken: 'tok_fail', email: 'alice@example.com');

        // Verify the failure result
        $this->assertFalse($result['success']);

        // Verify no email was sent — the spy's array must be empty
        $this->assertEmpty($spyMailer->sent,
            'No receipt email should be sent when payment is declined'
        );
    }

    public function testNoEmailSentWhenProductNotFound(): void
    {
        $notFoundRepo = new class implements ProductRepositoryInterface {
            public function findById(int $id): ?array { return null; }
        };

        $spyMailer = new class implements MailerInterface {
            public array $sent = [];
            public function send(string $to, string $subject, string $body): bool {
                $this->sent[] = compact('to', 'subject', 'body');
                return true;
            }
        };

        $service = new CheckoutService(
            $notFoundRepo,
            $this->makeSuccessGateway(),
            $this->makeSuccessInventory(),
            $spyMailer
        );

        try {
            $service->checkout(productId: 99, qty: 1, paymentToken: 'tok', email: 'alice@example.com');
        } catch (\DomainException) {
            // Expected
        }

        $this->assertEmpty($spyMailer->sent);
    }

    public function testNoEmailSentWhenInventoryFails(): void
    {
        $noStockInventory = new class implements InventoryServiceInterface {
            public function reserve(int $productId, int $qty): bool { return false; }
            public function release(int $productId, int $qty): void {}
        };

        $spyMailer = new class implements MailerInterface {
            public array $sent = [];
            public function send(string $to, string $subject, string $body): bool {
                $this->sent[] = compact('to', 'subject', 'body');
                return true;
            }
        };

        $service = new CheckoutService(
            $this->makeProductRepo(),
            $this->makeSuccessGateway(),
            $noStockInventory,
            $spyMailer
        );

        $result = $service->checkout(productId: 1, qty: 1, paymentToken: 'tok', email: 'alice@example.com');

        $this->assertFalse($result['success']);
        $this->assertEmpty($spyMailer->sent);
    }


    // ═══════════════════════════════════════════════════════════
    // PART E — Failure path matrix
    // ═══════════════════════════════════════════════════════════

    /**
     * A data provider covering all failure modes in a single test method.
     * Each case swaps one dependency for a failing stub.
     *
     * Note: when multiple paths throw vs return a failure value, you need
     * separate test methods. Here we only cover the "return false" paths.
     */
    public function testCheckoutResultErrorMessageReflectsFailureCause(): void
    {
        // Payment declined
        $declinedResult = (new CheckoutService(
            $this->makeProductRepo(),
            new class implements PaymentGatewayInterface {
                public function charge(int $amountCents, string $token): bool { return false; }
            },
            $this->makeSuccessInventory(),
            $this->makeNullMailer()
        ))->checkout(1, 1, 'tok', 'a@b.com');

        $this->assertSame('Payment declined', $declinedResult['error']);

        // Inventory failure
        $inventoryResult = (new CheckoutService(
            $this->makeProductRepo(),
            $this->makeSuccessGateway(),
            new class implements InventoryServiceInterface {
                public function reserve(int $productId, int $qty): bool { return false; }
                public function release(int $productId, int $qty): void {}
            },
            $this->makeNullMailer()
        ))->checkout(1, 1, 'tok', 'a@b.com');

        $this->assertSame('Insufficient stock', $inventoryResult['error']);
    }
}