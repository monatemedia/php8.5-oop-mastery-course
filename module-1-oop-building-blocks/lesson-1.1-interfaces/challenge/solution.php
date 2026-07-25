<?php
declare(strict_types=1);

/**
 * CHALLENGE SOLUTION — Lesson 1.1: Interfaces
 * ─────────────────────────────────────────────
 * ⚠️  Only open this file after completing starter.php yourself.
 *
 * Each task is clearly labelled so you can compare your approach section
 * by section. Do not worry if your variable names or output strings differ
 * slightly — focus on whether the STRUCTURE matches.
 */


// ─────────────────────────────────────────────────────────────────────────────
// TASK 1 SOLUTION — Two focused interfaces
// ─────────────────────────────────────────────────────────────────────────────

interface PaymentGateway {
    /**
     * Attempt to charge a payment.
     *
     * @param  float  $amount   The amount to charge.
     * @param  string $currency ISO 4217 currency code (e.g. 'ZAR', 'USD').
     * @param  string $token    Provider-specific payment token.
     * @return bool             True on success, false on failure.
     */
    public function charge(float $amount, string $currency, string $token): bool;
}

interface ReceiptPrinter {
    /**
     * Output a receipt for the given invoice data.
     *
     * @param array $invoiceData Associative array of invoice fields.
     */
    public function print(array $invoiceData): void;
}


// ─────────────────────────────────────────────────────────────────────────────
// TASK 2 SOLUTION — Existing classes now implement the interfaces
// ─────────────────────────────────────────────────────────────────────────────

class StripeGateway implements PaymentGateway {
    public function charge(float $amount, string $currency, string $token): bool {
        echo "[STRIPE] Charging R" . number_format($amount, 2) . " {$currency} on token {$token}... OK\n";
        return true;
    }
}

class PdfReceiptPrinter implements ReceiptPrinter {
    public function print(array $invoiceData): void {
        $id    = $invoiceData['id'];
        $name  = $invoiceData['customer_name'];
        $total = $invoiceData['total'];
        echo "[PDF RECEIPT] Printing invoice #{$id} for {$name} (R" . number_format($total, 2) . ")\n";
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// TASK 3 SOLUTION — Two new implementations; InvoiceService needs zero changes
// ─────────────────────────────────────────────────────────────────────────────

class PayFastGateway implements PaymentGateway {
    public function charge(float $amount, string $currency, string $token): bool {
        echo "[PAYFAST] Initiating payment of R" . number_format($amount, 2) . " {$currency} (token: {$token})... OK\n";
        return true;
    }
}

class EmailReceiptPrinter implements ReceiptPrinter {
    public function print(array $invoiceData): void {
        $id    = $invoiceData['id'];
        $email = $invoiceData['customer_email'];
        $total = $invoiceData['total'];
        echo "[EMAIL RECEIPT] Sending invoice #{$id} to {$email} (R" . number_format($total, 2) . ")\n";
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// TASK 4 SOLUTION — InvoiceService depends on interfaces only
//
// KEY CHANGES vs starter:
//   - Constructor now ACCEPTS dependencies; it does not create them.
//   - Property types are the interfaces, not the concrete classes.
//   - Zero `new` keywords inside the class body.
//   - process() is completely unchanged.
// ─────────────────────────────────────────────────────────────────────────────

class InvoiceService {
    public function __construct(
        private PaymentGateway $gateway,   // ← interface, not StripeGateway
        private ReceiptPrinter $printer    // ← interface, not PdfReceiptPrinter
    ) {}

    public function process(array $invoice): void {
        $charged = $this->gateway->charge(
            $invoice['total'],
            $invoice['currency'],
            $invoice['payment_token']
        );

        if ($charged) {
            $this->printer->print($invoice);
        }
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// TASK 5 SOLUTION — All four wiring combinations
//
// Notice: InvoiceService::process() is called identically each time.
// The ONLY difference is which concrete classes are passed to the constructor.
// InvoiceService has absolutely no knowledge of Stripe, PayFast, PDF, or Email.
// ─────────────────────────────────────────────────────────────────────────────

$invoice = [
    'id'             => 'INV-001',
    'customer_name'  => 'Alice Smith',
    'customer_email' => 'alice@example.com',
    'total'          => 1500.00,
    'currency'       => 'ZAR',
    'payment_token'  => 'tok_abc123',
];

echo "=== Stripe + PDF ===\n";
(new InvoiceService(new StripeGateway(), new PdfReceiptPrinter()))->process($invoice);

echo "\n=== Stripe + Email ===\n";
(new InvoiceService(new StripeGateway(), new EmailReceiptPrinter()))->process($invoice);

echo "\n=== PayFast + PDF ===\n";
(new InvoiceService(new PayFastGateway(), new PdfReceiptPrinter()))->process($invoice);

echo "\n=== PayFast + Email ===\n";
(new InvoiceService(new PayFastGateway(), new EmailReceiptPrinter()))->process($invoice);


// ─────────────────────────────────────────────────────────────────────────────
// BONUS — Proof that adding a 5th gateway requires zero changes to InvoiceService
// ─────────────────────────────────────────────────────────────────────────────

class PayPalGateway implements PaymentGateway {
    public function charge(float $amount, string $currency, string $token): bool {
        echo "[PAYPAL] Processing R" . number_format($amount, 2) . " {$currency} via token {$token}... OK\n";
        return true;
    }
}

echo "\n=== BONUS: PayPal + Email (InvoiceService untouched) ===\n";
(new InvoiceService(new PayPalGateway(), new EmailReceiptPrinter()))->process($invoice);


// ─────────────────────────────────────────────────────────────────────────────
// WHAT TO COMPARE IN YOUR OWN SOLUTION
// ─────────────────────────────────────────────────────────────────────────────
echo "\n--- Self-review checklist ---\n";
echo "[ ] Did your interfaces have the exact method signatures (types + return type)?\n";
echo "[ ] Did you remove 'new' from inside InvoiceService?\n";
echo "[ ] Are your constructor parameter types the INTERFACES, not the concrete classes?\n";
echo "[ ] Did process() stay completely unchanged?\n";
echo "[ ] Could you add a 6th gateway without touching InvoiceService at all?\n";