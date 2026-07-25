<?php
declare(strict_types=1);

/**
 * CHALLENGE STARTER — Lesson 1.1: Interfaces
 * ─────────────────────────────────────────────
 * Read CHALLENGE.md before touching this file.
 *
 * This file currently WORKS — but for the wrong reasons.
 * InvoiceService is tightly coupled to StripeGateway and PdfReceiptPrinter.
 *
 * YOUR JOB: Refactor this file so InvoiceService depends on interfaces,
 * not concrete classes. See CHALLENGE.md for full instructions.
 *
 * Rules:
 *  - Do NOT change the process() method's internal logic.
 *  - Do NOT change what gets printed to the screen.
 *  - Do NOT look at solution.php until you have made a genuine attempt.
 */

// ─────────────────────────────────────────────────────────────────────────────
// TASK 1: Define your two interfaces here
// PaymentGateway  → charge(float $amount, string $currency, string $token): bool
// ReceiptPrinter  → print(array $invoiceData): void
// ─────────────────────────────────────────────────────────────────────────────

// TODO: Define interface PaymentGateway

// TODO: Define interface ReceiptPrinter


// ─────────────────────────────────────────────────────────────────────────────
// TASK 2: Update these classes to implement your interfaces
// ─────────────────────────────────────────────────────────────────────────────

class StripeGateway {    // TODO: add  implements PaymentGateway
    public function charge(float $amount, string $currency, string $token): bool {
        echo "[STRIPE] Charging R" . number_format($amount, 2, '.', '') . " {$currency} on token {$token}... OK\n";
        return true;
    }
}

class PdfReceiptPrinter {    // TODO: add  implements ReceiptPrinter
    public function print(array $invoiceData): void {
        $id    = $invoiceData['id'];
        $name  = $invoiceData['customer_name'];
        $total = $invoiceData['total'];
        echo "[PDF RECEIPT] Printing invoice #{$id} for {$name} (R" . number_format($total, 2, '.', '') . ")\n";
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// TASK 3: Add two NEW implementations here
// ─────────────────────────────────────────────────────────────────────────────

// TODO: Create class PayFastGateway implements PaymentGateway
// Output format: [PAYFAST] Initiating payment of R{amount} {currency} (token: {token})... OK


// TODO: Create class EmailReceiptPrinter implements ReceiptPrinter
// Output format: [EMAIL RECEIPT] Sending invoice #{id} to {customer_email} (R{total})


// ─────────────────────────────────────────────────────────────────────────────
// TASK 4: Refactor InvoiceService
// ─────────────────────────────────────────────────────────────────────────────

class InvoiceService {
    // ❌ PROBLEM: InvoiceService creates its own dependencies.
    // It is impossible to swap them without editing this class.
    private StripeGateway    $gateway;
    private PdfReceiptPrinter $printer;

    public function __construct() {
        // ❌ These two lines are the problem. Remove them.
        $this->gateway = new StripeGateway();
        $this->printer = new PdfReceiptPrinter();

        // TODO: Replace the constructor above so it ACCEPTS
        //       a PaymentGateway and a ReceiptPrinter as parameters.
        //       Type-hint them against your interfaces.
    }

    // DO NOT CHANGE THIS METHOD'S LOGIC — only change how dependencies arrive.
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
// TASK 5: Wire up all four combinations below
// ─────────────────────────────────────────────────────────────────────────────

$invoice = [
    'id'            => 'INV-001',
    'customer_name'  => 'Alice Smith',
    'customer_email' => 'alice@example.com',
    'total'          => 1500.00,
    'currency'       => 'ZAR',
    'payment_token'  => 'tok_abc123',
];

echo "=== Stripe + PDF ===\n";
// TODO: Create InvoiceService with StripeGateway + PdfReceiptPrinter, then process($invoice)

echo "\n=== Stripe + Email ===\n";
// TODO: Create InvoiceService with StripeGateway + EmailReceiptPrinter, then process($invoice)

echo "\n=== PayFast + PDF ===\n";
// TODO: Create InvoiceService with PayFastGateway + PdfReceiptPrinter, then process($invoice)

echo "\n=== PayFast + Email ===\n";
// TODO: Create InvoiceService with PayFastGateway + EmailReceiptPrinter, then process($invoice)