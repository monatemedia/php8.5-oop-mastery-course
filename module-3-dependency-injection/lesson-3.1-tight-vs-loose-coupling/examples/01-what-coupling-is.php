<?php
declare(strict_types=1);

/**
 * Example 01 — What Coupling Is
 * --------------------------------
 * Coupling is not a binary thing. It exists on a spectrum.
 * This example defines the vocabulary, shows the spectrum,
 * and gives you a concrete way to measure coupling in any class.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  What Coupling Is                                   ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 1 — The coupling spectrum with real PHP examples
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 1: The coupling spectrum ────────────────────\n\n";

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// LEVEL 1 (WORST): Content coupling
// Class A directly accesses private internals of Class B
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

echo "LEVEL 1 — Content coupling (accessing internals):\n";

class UserRepository {
    // This should be private — but content coupling forces it public
    public array $users = [
        1 => ['name' => 'Alice', 'email' => 'alice@example.com'],
        2 => ['name' => 'Bob',   'email' => 'bob@example.com'],
    ];
}

class UserReport {
    public function __construct(private UserRepository $repo) {}

    public function generate(): string {
        // ❌ CONTENT COUPLING: directly reading internal array structure
        // If UserRepository changes how it stores users, this breaks
        $output = '';
        foreach ($this->repo->users as $id => $user) {
            $output .= "  #{$id}: {$user['name']} <{$user['email']}>\n";
        }
        return $output;
    }
}

$report = new UserReport(new UserRepository());
echo $report->generate();
echo "Problem: UserReport knows the internal array structure of UserRepository.\n";
echo "         Change the structure → UserReport breaks.\n\n";


// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// LEVEL 2: Common coupling (shared global state)
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

echo "LEVEL 2 — Common coupling (global/static state):\n";

class AppConfig {
    private static array $data = [];

    public static function set(string $key, mixed $value): void {
        self::$data[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed {
        return self::$data[$key] ?? $default;
    }
}

class EmailService {
    public function send(string $to, string $message): void {
        // ❌ COMMON COUPLING: depends on global static state
        $from = AppConfig::get('email.from', 'no-reply@example.com');
        echo "  [EMAIL] From: {$from} → To: {$to} | {$message}\n";
    }
}

AppConfig::set('email.from', 'system@example.com');
$emailService = new EmailService();
$emailService->send('alice@example.com', 'Hello');
echo "Problem: EmailService depends on global state. Tests cannot isolate it.\n\n";


// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// LEVEL 3: Control coupling (boolean flags)
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

echo "LEVEL 3 — Control coupling (flags controlling behaviour):\n";

class DataExporter {
    // ❌ CONTROL COUPLING: caller must know what `true` means
    public function export(array $data, bool $asJson): string {
        if ($asJson) {
            return json_encode($data);
        }
        // Produce CSV
        $lines = [implode(',', array_keys(reset($data)))];
        foreach ($data as $row) {
            $lines[] = implode(',', $row);
        }
        return implode("\n", $lines);
    }
}

$exporter = new DataExporter();
$data = [['name' => 'Alice', 'score' => 95]];
echo "  JSON: " . $exporter->export($data, true)  . "\n";
echo "  CSV:  " . $exporter->export($data, false) . "\n";
echo "Problem: What does `true` mean? Caller must know the semantics.\n\n";


// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// LEVEL 4 (ACCEPTABLE): Data coupling
// Classes share only what is needed via clear parameters
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

echo "LEVEL 4 — Data coupling (passing only what is needed):\n";

class TaxCalculator {
    // ✅ DATA COUPLING: receives only the data it needs
    public function calculate(float $amount, float $rate): float {
        return round($amount * $rate, 2);
    }
}

$calc = new TaxCalculator();
echo "  Tax on R1000 at 15%: R" . $calc->calculate(1000.00, 0.15) . "\n";
echo "OK: TaxCalculator knows nothing about Order, User, or any other object.\n\n";


// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// LEVEL 5 (BEST): Message coupling via interfaces
// Classes communicate only through interface contracts
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

echo "LEVEL 5 — Message coupling (interface contract only):\n";

interface PaymentGateway {
    public function charge(float $amount, string $currency): bool;
}

class OrderService {
    // ✅ MESSAGE COUPLING: only knows the interface, not the implementation
    public function __construct(private PaymentGateway $gateway) {}

    public function process(float $amount): bool {
        return $this->gateway->charge($amount, 'ZAR');
    }
}

$fakeGateway = new class implements PaymentGateway {
    public function charge(float $amount, string $currency): bool {
        echo "  [FAKE] Charged R{$amount} {$currency}\n";
        return true;
    }
};

$service = new OrderService($fakeGateway);
$service->process(500.00);
echo "BEST: OrderService knows nothing about Stripe, PayFast, or any concrete gateway.\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 2 — How to count coupling points in a class
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 2: Counting coupling points ─────────────────\n\n";

// A class with multiple coupling points — count them:
class BadInvoiceService {
    // Coupling point 1: concrete property type
    private \PDO $pdo;

    public function __construct() {
        // Coupling point 2: new concrete class
        $this->pdo = new \PDO('mysql:host=localhost;dbname=invoices', 'root', 'pass');
    }

    public function generate(int $orderId): string {
        // Coupling point 3: SQL tied to MySQL syntax
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Coupling point 4: static/global call
        $config = AppConfig::get('invoice.prefix', 'INV');

        // Coupling point 5: concrete class for PDF generation
        // $pdf = new PDFGenerator(); // (commented — would be a 5th coupling point)

        return "{$config}-" . str_pad((string)$orderId, 6, '0', STR_PAD_LEFT);
    }
}

echo "BadInvoiceService coupling audit:\n";
echo "  Point 1: private PDO \$pdo           — concrete type, not an interface\n";
echo "  Point 2: new PDO(...) in constructor  — creates its own DB connection\n";
echo "  Point 3: MySQL-specific SQL           — cannot switch to PostgreSQL\n";
echo "  Point 4: AppConfig::get()             — global state dependency\n";
echo "  (Point 5 commented out: new PDFGenerator() would be a 5th)\n";
echo "\n  Total coupling points: 4+\n";
echo "  To test: need MySQL running + right schema + correct AppConfig state\n";

echo "\n--- Recap ---\n";
echo "Coupling = how much a class knows about the internals of its dependencies.\n";
echo "Spectrum: content (worst) → common → control → stamp → data → message (best).\n";
echo "Count: every `new`, static call, global access, and concrete type is a coupling point.\n";
echo "Goal: depend on interfaces (message coupling), not implementations.\n";