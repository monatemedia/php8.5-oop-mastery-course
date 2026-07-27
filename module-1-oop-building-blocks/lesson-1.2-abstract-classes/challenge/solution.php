<?php
declare(strict_types=1);

/**
 * CHALLENGE SOLUTION — Lesson 1.2: Abstract Classes
 * ──────────────────────────────────────────────────
 * ⚠️  Only open this file after completing starter.php yourself.
 *
 * Key things to compare in your solution:
 *   1. Is the constructor validation ONLY in PaymentProcessor?
 *   2. Is calculateFee() ONLY in PaymentProcessor?
 *   3. Is logTransaction() ONLY in PaymentProcessor — using getProcessorName()?
 *   4. Is generateReceipt() marked final in PaymentProcessor?
 *   5. Are your concrete subclasses under 30 lines each?
 */


// ─────────────────────────────────────────────────────────────────────────────
// ABSTRACT BASE CLASS — all shared logic lives here
// ─────────────────────────────────────────────────────────────────────────────

abstract class PaymentProcessor {
    public function __construct(
        protected string $apiKey,
        protected string $merchantId
    ) {
        // Shared validation — runs for every processor automatically
        if (empty(trim($apiKey))) {
            throw new \InvalidArgumentException("API key cannot be empty.");
        }
        if (!preg_match('/^MERCH-\d{6}$/', $merchantId)) {
            throw new \InvalidArgumentException("Invalid merchant ID format. Expected: MERCH-XXXXXX");
        }
    }

    // ── Abstract: each processor implements these differently ─────────────────

    abstract public function charge(float $amount, string $currency, string $token): bool;

    abstract public function getProcessorName(): string;

    /** Build the processor-specific first section of the receipt */
    abstract protected function buildReceiptHeader(float $amount, string $currency, string $token): string;

    // ── Concrete: shared across all processors ────────────────────────────────

    public function calculateFee(float $amount): float {
        return round($amount * 0.029 + 0.30, 2);
    }

    protected function logTransaction(string $type, float $amount, bool $success): void {
        $status    = $success ? 'SUCCESS' : 'FAILED';
        $formatted = number_format($amount, 2, '.', '');
        $name      = strtoupper($this->getProcessorName());
        echo "[{$name}] LOG {$type} R{$formatted} {$status}\n";
    }

    // ── Template Method: the receipt pipeline ─────────────────────────────────
    // `final` ensures no subclass can ever reorder or skip steps.

    final public function generateReceipt(float $amount, string $currency, string $token): string {
        $fee = $this->calculateFee($amount);
        $net = round($amount - $fee, 2);

        // STEP 1: processor-specific header (abstract — filled by subclass)
        $header = $this->buildReceiptHeader($amount, $currency, $token);

        // STEP 2: shared body (concrete — identical for all processors)
        // Money is always two decimals. Interpolating the raw float prints
        // "14.8", not "14.80" — PHP drops the trailing zero.
        $body = "Fee: R" . number_format($fee, 2, '.', '')
              . " | Net: R" . number_format($net, 2, '.', '');

        // STEP 3: shared footer (concrete — identical for all processors)
        $footer = "Merchant: {$this->merchantId}\nProcessed at: " . date('Y-m-d H:i:s');

        return "--- RECEIPT ---\n{$header}\n---\n{$body}\n---\n{$footer}\n--- END ---";
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// CONCRETE SUBCLASSES — only the three unique methods remain
// Each class is now well under 30 lines.
// ─────────────────────────────────────────────────────────────────────────────

class StripeProcessor extends PaymentProcessor {
    public function charge(float $amount, string $currency, string $token): bool {
        echo "[STRIPE] Charging R" . number_format($amount, 2, '.', '') . " {$currency} on token {$token}\n";
        $success = true;
        $this->logTransaction('charge', $amount, $success);
        return $success;
    }

    public function getProcessorName(): string { return 'Stripe'; }

    protected function buildReceiptHeader(float $amount, string $currency, string $token): string {
        return "Stripe Payment Receipt\nTransaction: {$token} | R" . number_format($amount, 2, '.', '') . " {$currency}";
    }
}

class PayFastProcessor extends PaymentProcessor {
    public function charge(float $amount, string $currency, string $token): bool {
        echo "[PAYFAST] Initiating R" . number_format($amount, 2, '.', '') . " {$currency} via token {$token}\n";
        $success = true;
        $this->logTransaction('charge', $amount, $success);
        return $success;
    }

    public function getProcessorName(): string { return 'PayFast'; }

    protected function buildReceiptHeader(float $amount, string $currency, string $token): string {
        return "PayFast Payment Confirmation\nTransaction: {$token} | R" . number_format($amount, 2, '.', '') . " {$currency}";
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// USAGE — output is identical to the starter file
// ─────────────────────────────────────────────────────────────────────────────

$stripe  = new StripeProcessor('sk_test_abc123', 'MERCH-001234');
$payfast = new PayFastProcessor('pf_key_xyz789', 'MERCH-001234');

echo "=== Stripe ===\n";
$stripe->charge(500.00, 'ZAR', 'tok_abc123');
echo "Fee: R" . number_format($stripe->calculateFee(500.00), 2, '.', '') . "\n\n";
echo $stripe->generateReceipt(500.00, 'ZAR', 'tok_abc123') . "\n";

echo "\n=== PayFast ===\n";
$payfast->charge(500.00, 'ZAR', 'tok_pf456');
echo "Fee: R" . number_format($payfast->calculateFee(500.00), 2, '.', '') . "\n\n";
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


// ─────────────────────────────────────────────────────────────────────────────
// BONUS: Adding a third processor requires NO changes to existing code (OCP)
// ─────────────────────────────────────────────────────────────────────────────

class PayPalProcessor extends PaymentProcessor {
    public function charge(float $amount, string $currency, string $token): bool {
        echo "[PAYPAL] Processing R" . number_format($amount, 2, '.', '') . " {$currency} token {$token}\n";
        $success = true;
        $this->logTransaction('charge', $amount, $success);
        return $success;
    }

    public function getProcessorName(): string { return 'PayPal'; }

    protected function buildReceiptHeader(float $amount, string $currency, string $token): string {
        return "PayPal Transaction Record\nRef: {$token} | R" . number_format($amount, 2, '.', '') . " {$currency}";
    }
}

echo "\n=== BONUS: PayPal (zero changes to existing code) ===\n";
$paypal = new PayPalProcessor('pp_live_def456', 'MERCH-001234');
$paypal->charge(500.00, 'ZAR', 'tok_pp789');
echo "Fee: R" . number_format($paypal->calculateFee(500.00), 2, '.', '') . "\n\n";
echo $paypal->generateReceipt(500.00, 'ZAR', 'tok_pp789') . "\n";


// ─────────────────────────────────────────────────────────────────────────────
// SELF-REVIEW CHECKLIST
// ─────────────────────────────────────────────────────────────────────────────
echo "\n--- Self-review checklist ---\n";
echo "[ ] Constructor validation lives ONLY in PaymentProcessor?\n";
echo "[ ] calculateFee() lives ONLY in PaymentProcessor?\n";
echo "[ ] logTransaction() lives ONLY in PaymentProcessor, uses getProcessorName()?\n";
echo "[ ] generateReceipt() is marked final in PaymentProcessor?\n";
echo "[ ] Each concrete subclass has exactly 3 methods and is under 30 lines?\n";
echo "[ ] Adding PayPalProcessor required zero edits to StripeProcessor or PayFastProcessor?\n";

// ═══════════════════════════════════════════════════════════════════════
// ACCEPTANCE CHECKS
// ───────────────────────────────────────────────────────────────────────
// This challenge is a REFACTOR: the program prints the same thing before
// and after you do the work, so comparing output cannot tell whether you
// have done it. These checks inspect the STRUCTURE instead.
//
// They fail on the untouched starter and pass once the refactor is
// complete. `check.php` marks the lesson done on the ACCEPTANCE line.
// ═══════════════════════════════════════════════════════════════════════

echo "\n--- Acceptance ---\n";

$acceptance = [
    'PaymentProcessor exists and is abstract'
        => class_exists('PaymentProcessor')
           && (new ReflectionClass('PaymentProcessor'))->isAbstract(),

    'StripeProcessor extends PaymentProcessor'
        => class_exists('StripeProcessor')
           && is_subclass_of('StripeProcessor', 'PaymentProcessor'),

    'PayFastProcessor extends PaymentProcessor'
        => class_exists('PayFastProcessor')
           && is_subclass_of('PayFastProcessor', 'PaymentProcessor'),

    'PaymentProcessor declares at least one abstract method'
        => class_exists('PaymentProcessor')
           && count((new ReflectionClass('PaymentProcessor'))->getMethods(ReflectionMethod::IS_ABSTRACT)) > 0,

    'generateReceipt() is concrete and shared (template method)'
        => class_exists('PaymentProcessor')
           && (new ReflectionClass('PaymentProcessor'))->hasMethod('generateReceipt')
           && !(new ReflectionMethod('PaymentProcessor', 'generateReceipt'))->isAbstract(),
];

$allPassed = true;
foreach ($acceptance as $label => $passed) {
    echo '  ' . ($passed ? 'PASS' : 'FAIL') . '  ' . $label . "\n";
    $allPassed = $allPassed && $passed;
}
echo $allPassed
    ? "ACCEPTANCE: all checks passed\n"
    : "ACCEPTANCE: not yet complete\n";
