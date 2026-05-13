<?php
declare(strict_types=1);

/**
 * CHALLENGE STARTER — Lesson 1.2: Abstract Classes
 * ──────────────────────────────────────────────────
 * Read CHALLENGE.md before touching this file.
 *
 * Both classes below WORK — but they share a large amount of duplicated
 * logic. Any change to the shared behaviour requires editing both files.
 *
 * YOUR JOB:
 *   1. Create an abstract PaymentProcessor base class.
 *   2. Extract all shared logic into it.
 *   3. Apply the Template Method Pattern to generateReceipt().
 *   4. Refactor StripeProcessor and PayFastProcessor to extend it.
 *
 * Rules:
 *  - Do NOT change what gets printed to the screen.
 *  - Do NOT look at solution.php until you have made a genuine attempt.
 *  - Each concrete subclass should be under 30 lines when you are done.
 */


// ─────────────────────────────────────────────────────────────────────────────
// TODO: Define abstract class PaymentProcessor here
// ─────────────────────────────────────────────────────────────────────────────

// abstract class PaymentProcessor { ... }


// ─────────────────────────────────────────────────────────────────────────────
// CURRENT: StripeProcessor — lots of duplication (see comments marked ❗)
// TODO: Refactor to extend PaymentProcessor
// ─────────────────────────────────────────────────────────────────────────────

class StripeProcessor {   // TODO: extends PaymentProcessor
    private string $apiKey;
    private string $merchantId;

    public function __construct(string $apiKey, string $merchantId) {
        // ❗ DUPLICATED validation — same in PayFastProcessor
        if (empty(trim($apiKey))) {
            throw new \InvalidArgumentException("API key cannot be empty.");
        }
        if (!preg_match('/^MERCH-\d{6}$/', $merchantId)) {
            throw new \InvalidArgumentException("Invalid merchant ID format. Expected: MERCH-XXXXXX");
        }
        $this->apiKey     = $apiKey;
        $this->merchantId = $merchantId;
    }

    public function charge(float $amount, string $currency, string $token): bool {
        $formatted = number_format($amount, 2);
        echo "[STRIPE] Charging R{$formatted} {$currency} on token {$token}\n";
        $success = true; // Simulate success
        $this->logTransaction('charge', $amount, $success); // ❗ DUPLICATED method call
        return $success;
    }

    // ❗ DUPLICATED — identical formula in PayFastProcessor
    public function calculateFee(float $amount): float {
        return round($amount * 0.029 + 0.30, 2);
    }

    // ❗ DUPLICATED — identical structure in PayFastProcessor (only prefix differs)
    protected function logTransaction(string $type, float $amount, bool $success): void {
        $status    = $success ? 'SUCCESS' : 'FAILED';
        $formatted = number_format($amount, 2);
        echo "[STRIPE] LOG {$type} R{$formatted} {$status}\n";
    }

    // ❗ DUPLICATED PIPELINE — body and footer are word-for-word identical
    public function generateReceipt(float $amount, string $currency, string $token): string {
        $fee = $this->calculateFee($amount);
        $net = round($amount - $fee, 2);

        // Header — unique to Stripe
        $header = "Stripe Payment Receipt\nTransaction: {$token} | R" . number_format($amount, 2) . " {$currency}";

        // Body — ❗ IDENTICAL in PayFastProcessor
        $body = "Fee: R{$fee} | Net: R{$net}";

        // Footer — ❗ IDENTICAL in PayFastProcessor
        $footer = "Merchant: {$this->merchantId}\nProcessed at: " . date('Y-m-d H:i:s');

        return "--- RECEIPT ---\n{$header}\n---\n{$body}\n---\n{$footer}\n--- END ---";
    }

    public function getProcessorName(): string { return 'Stripe'; }
}


// ─────────────────────────────────────────────────────────────────────────────
// CURRENT: PayFastProcessor — same duplication
// TODO: Refactor to extend PaymentProcessor
// ─────────────────────────────────────────────────────────────────────────────

class PayFastProcessor {  // TODO: extends PaymentProcessor
    private string $apiKey;
    private string $merchantId;

    public function __construct(string $apiKey, string $merchantId) {
        // ❗ DUPLICATED — same validation as StripeProcessor
        if (empty(trim($apiKey))) {
            throw new \InvalidArgumentException("API key cannot be empty.");
        }
        if (!preg_match('/^MERCH-\d{6}$/', $merchantId)) {
            throw new \InvalidArgumentException("Invalid merchant ID format. Expected: MERCH-XXXXXX");
        }
        $this->apiKey     = $apiKey;
        $this->merchantId = $merchantId;
    }

    public function charge(float $amount, string $currency, string $token): bool {
        $formatted = number_format($amount, 2);
        echo "[PAYFAST] Initiating R{$formatted} {$currency} via token {$token}\n";
        $success = true;
        $this->logTransaction('charge', $amount, $success);
        return $success;
    }

    // ❗ DUPLICATED — identical formula as StripeProcessor
    public function calculateFee(float $amount): float {
        return round($amount * 0.029 + 0.30, 2);
    }

    // ❗ DUPLICATED — identical structure (only [PAYFAST] prefix differs)
    protected function logTransaction(string $type, float $amount, bool $success): void {
        $status    = $success ? 'SUCCESS' : 'FAILED';
        $formatted = number_format($amount, 2);
        echo "[PAYFAST] LOG {$type} R{$formatted} {$status}\n";
    }

    // ❗ DUPLICATED PIPELINE — body and footer identical to StripeProcessor
    public function generateReceipt(float $amount, string $currency, string $token): string {
        $fee = $this->calculateFee($amount);
        $net = round($amount - $fee, 2);

        // Header — unique to PayFast
        $header = "PayFast Payment Confirmation\nTransaction: {$token} | R" . number_format($amount, 2) . " {$currency}";

        // Body — ❗ IDENTICAL to StripeProcessor
        $body = "Fee: R{$fee} | Net: R{$net}";

        // Footer — ❗ IDENTICAL to StripeProcessor
        $footer = "Merchant: {$this->merchantId}\nProcessed at: " . date('Y-m-d H:i:s');

        return "--- RECEIPT ---\n{$header}\n---\n{$body}\n---\n{$footer}\n--- END ---";
    }

    public function getProcessorName(): string { return 'PayFast'; }
}


// ─────────────────────────────────────────────────────────────────────────────
// CURRENT usage — this output should remain UNCHANGED after your refactor
// ─────────────────────────────────────────────────────────────────────────────

$stripe  = new StripeProcessor('sk_test_abc123', 'MERCH-001234');
$payfast = new PayFastProcessor('pf_key_xyz789', 'MERCH-001234');

echo "=== Stripe ===\n";
$stripe->charge(500.00, 'ZAR', 'tok_abc123');
echo "Fee: R" . $stripe->calculateFee(500.00) . "\n\n";
echo $stripe->generateReceipt(500.00, 'ZAR', 'tok_abc123') . "\n";

echo "\n=== PayFast ===\n";
$payfast->charge(500.00, 'ZAR', 'tok_pf456');
echo "Fee: R" . $payfast->calculateFee(500.00) . "\n\n";
echo $payfast->generateReceipt(500.00, 'ZAR', 'tok_pf456') . "\n";

echo "\n=== Constructor validation ===\n";
try {
    new StripeProcessor('', 'MERCH-001234');
} catch (\InvalidArgumentException $e) {
    echo "Caught: " . $e->getMessage() . "\n";
}
try {
    new PayFastProcessor('key123', 'BADID');
} catch (\InvalidArgumentException $e) {
    echo "Caught: " . $e->getMessage() . "\n";
}