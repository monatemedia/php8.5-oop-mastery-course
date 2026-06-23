<?php
declare(strict_types=1);

/**
 * Challenge Support Files — Lesson 5.2
 * ──────────────────────────────────────
 * This file defines the interfaces and OrderService used in the challenge.
 * Read it carefully before writing any tests.
 *
 * OrderService depends on three collaborators injected via the constructor:
 *   - ProductRepositoryInterface  — looks up products by ID
 *   - PaymentGatewayInterface     — charges a payment token
 *   - MailerInterface             — sends the order confirmation email
 *
 * Your task: replace all three with test doubles and verify every path.
 */

// ─────────────────────────────────────────────────────────────────────────────
// Contracts
// ─────────────────────────────────────────────────────────────────────────────

interface ProductRepositoryInterface
{
    /**
     * Returns the product array or null if not found.
     * @return array{id: int, name: string, price: int, sku: string}|null
     */
    public function findById(int $id): ?array;
}

interface PaymentGatewayInterface
{
    /**
     * Attempts to charge amountCents against the payment token.
     *
     * @return bool  true = charged successfully, false = declined
     * @throws \RuntimeException  on infrastructure failure (network, timeout)
     */
    public function charge(int $amountCents, string $token): bool;
}

interface MailerInterface
{
    /**
     * Sends an email. Returns true on success, false on delivery failure.
     */
    public function send(string $to, string $subject, string $body): bool;
}


// ─────────────────────────────────────────────────────────────────────────────
// The class under test
// ─────────────────────────────────────────────────────────────────────────────

class OrderService
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private PaymentGatewayInterface    $gateway,
        private MailerInterface            $mailer
    ) {}

    /**
     * Places an order for a single product.
     *
     * Success path:
     *   1. Look up the product
     *   2. Charge the payment token for (product.price × qty) cents
     *   3. Send a confirmation email to $customerEmail
     *   4. Return a success result with order_id and total_cents
     *
     * Failure paths:
     *   - Product not found     → throws \DomainException
     *   - Payment declined      → returns ['success' => false, 'error' => 'Payment declined']
     *   - Gateway throws        → RuntimeException propagates to the caller
     *
     * @return array{
     *   success: bool,
     *   order_id: int|null,
     *   total_cents: int|null,
     *   error: string|null
     * }
     *
     * @throws \DomainException   when product is not found
     * @throws \RuntimeException  when the payment gateway throws
     */
    public function placeOrder(int $productId, int $qty, string $paymentToken, string $customerEmail): array
    {
        // ── Step 1: Find the product ─────────────────────────────────────────
        $product = $this->products->findById($productId);

        if ($product === null) {
            throw new \DomainException(
                "Cannot place order: product {$productId} does not exist"
            );
        }

        // ── Step 2: Charge the gateway ───────────────────────────────────────
        $totalCents = $product['price'] * $qty;
        $charged    = $this->gateway->charge($totalCents, $paymentToken);
        // Note: if the gateway throws a RuntimeException, it propagates — we do not catch it

        if (!$charged) {
            return [
                'success'     => false,
                'order_id'    => null,
                'total_cents' => null,
                'error'       => 'Payment declined',
            ];
        }

        // ── Step 3: Send confirmation email ──────────────────────────────────
        $this->mailer->send(
            to:      $customerEmail,
            subject: "Order confirmed — {$product['name']}",
            body:    $this->buildEmailBody($product, $qty, $totalCents)
        );

        // ── Step 4: Return success ────────────────────────────────────────────
        return [
            'success'     => true,
            'order_id'    => random_int(100000, 999999),
            'total_cents' => $totalCents,
            'error'       => null,
        ];
    }

    /**
     * Returns the order total in cents without placing the order.
     * Useful for price previews.
     *
     * @throws \DomainException when product is not found
     */
    public function calculateTotal(int $productId, int $qty): int
    {
        $product = $this->products->findById($productId);

        if ($product === null) {
            throw new \DomainException("Product {$productId} not found");
        }

        return $product['price'] * $qty;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function buildEmailBody(array $product, int $qty, int $totalCents): string
    {
        $total = number_format($totalCents / 100, 2);

        return implode("\n", [
            "Thank you for your order!",
            "",
            "Product : {$product['name']} (SKU: {$product['sku']})",
            "Quantity: {$qty}",
            "Total   : R{$total}",
            "",
            "Your order will be processed within 1-2 business days.",
        ]);
    }
}