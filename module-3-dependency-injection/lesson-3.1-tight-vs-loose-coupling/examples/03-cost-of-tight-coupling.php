<?php
declare(strict_types=1);

/**
 * Example 03 — The Cost of Tight Coupling
 * -----------------------------------------
 * This example makes the three costs of tight coupling concrete and visible.
 * We build the SAME feature twice — once tightly coupled, once loosely coupled.
 * The tight version fails at three specific tasks. The loose version passes all three.
 *
 * Cost 1: Untestability — cannot test without real infrastructure
 * Cost 2: Inflexibility — cannot change a dependency without editing the class
 * Cost 3: Hard to swap  — cannot use different implementations in different contexts
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  The Cost of Tight Coupling                        ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Setup: simulated infrastructure (we pretend these connect to real systems)
// ─────────────────────────────────────────────────────────────────────────────

class StripeGateway {
    public function __construct(private string $apiKey) {
        if ($apiKey === '') throw new \RuntimeException("Cannot use Stripe without an API key.");
        echo "  [STRIPE] Connected with key " . substr($apiKey, 0, 4) . "****\n";
    }
    public function charge(float $amount, string $token): bool {
        echo "  [STRIPE] Charged R{$amount} on token {$token}\n";
        return true;
    }
}

class MySqlOrderDb {
    public function __construct(private string $dsn) {
        if (!str_starts_with($dsn, 'mysql:')) {
            throw new \RuntimeException("Bad DSN: {$dsn}");
        }
        echo "  [MYSQL] Connected to {$dsn}\n";
    }
    public function saveOrder(array $order): int {
        echo "  [MYSQL] Saved order #{$order['id']}\n";
        return $order['id'];
    }
    public function getOrder(int $id): array {
        return ['id' => $id, 'status' => 'pending', 'amount' => 500.00];
    }
}

class FileAuditLogger {
    public function __construct(private string $path) {
        echo "  [FILE] Opened log: {$path}\n";
    }
    public function log(string $event, array $context): void {
        echo "  [FILE] {$event}: " . json_encode($context) . "\n";
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// ═══ TIGHTLY COUPLED VERSION ═══
// ─────────────────────────────────────────────────────────────────────────────

class TightOrderService {
    private StripeGateway  $gateway;
    private MySqlOrderDb   $db;
    private FileAuditLogger $logger;

    public function __construct() {
        // All three dependencies are hardwired here
        $this->gateway = new StripeGateway('sk_live_abc123');
        $this->db      = new MySqlOrderDb('mysql:host=db.prod.internal;dbname=orders');
        $this->logger  = new FileAuditLogger('/var/log/orders/audit.log');
    }

    public function placeOrder(array $order): bool {
        $this->logger->log('order.placing', ['id' => $order['id']]);
        $charged = $this->gateway->charge($order['amount'], $order['token']);
        if ($charged) {
            $this->db->saveOrder($order);
            $this->logger->log('order.placed', ['id' => $order['id']]);
        }
        return $charged;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// COST 1 — Untestability
// ─────────────────────────────────────────────────────────────────────────────

echo "── Cost 1: Untestability ────────────────────────────\n\n";

echo "Attempting to create TightOrderService for testing:\n";
try {
    // In a real test environment: no Stripe key, no MySQL, no /var/log write access
    // Here we simulate: the constructor IMMEDIATELY tries to connect to everything
    $service = new TightOrderService();
    echo "Created — but we just triggered 3 real infrastructure connections.\n";
    echo "In a test: no Stripe key → exception. No MySQL → exception. No /var/log → exception.\n";
} catch (\RuntimeException $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
    echo "Cannot even instantiate the service without infrastructure.\n";
}

echo "\nThe business logic in placeOrder() CANNOT be tested in isolation.\n";
echo "We cannot test: 'what happens if the payment fails?' without a real Stripe failure.\n";
echo "We cannot test: 'what happens if the DB save fails?' without MySQL.\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// COST 2 — Inflexibility
// ─────────────────────────────────────────────────────────────────────────────

echo "── Cost 2: Inflexibility ────────────────────────────\n\n";

echo "Business requirement: Switch payment provider from Stripe to PayFast.\n\n";

echo "With TightOrderService:\n";
echo "  1. Open TightOrderService.php (working, production code)\n";
echo "  2. Change line: \$this->gateway = new StripeGateway(...)\n";
echo "                to: \$this->gateway = new PayFastGateway(...)\n";
echo "  3. Add the PayFast constructor args (different from Stripe)\n";
echo "  4. Risk: you just edited working, deployed code — regression possible\n";
echo "  5. If 10 other services also hardwire StripeGateway: edit all 10\n\n";

echo "Root cause: the decision of WHICH gateway to use lives inside the class\n";
echo "            that USES it. These are two different responsibilities.\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// COST 3 — Hard to swap (cannot use different implementations per context)
// ─────────────────────────────────────────────────────────────────────────────

echo "── Cost 3: Hard to swap ─────────────────────────────\n\n";

echo "Three contexts need THREE different gateway behaviours:\n";
echo "  Production: Stripe live mode  (real charges)\n";
echo "  Staging:    Stripe test mode  (fake charges, real API)\n";
echo "  Testing:    Fake gateway       (no network, instant, controllable)\n\n";

echo "With TightOrderService: only ONE behaviour is possible.\n";
echo "The constructor hardwires `new StripeGateway('sk_live_abc123')`.\n";
echo "To get test behaviour: edit the source file. That changes production too.\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// ═══ LOOSELY COUPLED VERSION — solves all three costs ═══
// ─────────────────────────────────────────────────────────────────────────────

echo "── The fix: loose coupling ──────────────────────────\n\n";

// Interfaces — the contracts both sides agree on
interface PaymentGateway {
    public function charge(float $amount, string $token): bool;
}

interface OrderDatabase {
    public function saveOrder(array $order): int;
    public function getOrder(int $id): array;
}

interface AuditLogger {
    public function log(string $event, array $context): void;
}

// Named implementations — concrete, injected from outside
class StripePaymentGateway implements PaymentGateway {
    public function __construct(private string $apiKey) {}
    public function charge(float $amount, string $token): bool {
        echo "  [STRIPE-LIVE] Charged R{$amount} on {$token}\n";
        return true;
    }
}

class PayFastGateway implements PaymentGateway {
    public function __construct(private string $merchantId) {}
    public function charge(float $amount, string $token): bool {
        echo "  [PAYFAST] Initiated R{$amount} via {$token}\n";
        return true;
    }
}

// Loosely coupled service — depends on interfaces, knows nothing about Stripe/PayFast
class LooseOrderService {
    public function __construct(
        private PaymentGateway $gateway,   // interface
        private OrderDatabase  $db,        // interface
        private AuditLogger    $logger     // interface
    ) {}

    public function placeOrder(array $order): bool {
        $this->logger->log('order.placing', ['id' => $order['id']]);
        $charged = $this->gateway->charge($order['amount'], $order['token']);
        if ($charged) {
            $this->db->saveOrder($order);
            $this->logger->log('order.placed', ['id' => $order['id']]);
        }
        return $charged;
    }
}

// ── COST 1 FIX: fully testable with fakes ──

$fakeGateway = new class implements PaymentGateway {
    public bool $shouldSucceed = true;
    public array $calls        = [];
    public function charge(float $amount, string $token): bool {
        $this->calls[] = compact('amount', 'token');
        return $this->shouldSucceed;
    }
};

$fakeDb = new class implements OrderDatabase {
    public array $saved = [];
    public function saveOrder(array $order): int {
        $this->saved[] = $order;
        return $order['id'];
    }
    public function getOrder(int $id): array { return ['id' => $id]; }
};

$fakeLogger = new class implements AuditLogger {
    public array $entries = [];
    public function log(string $event, array $context): void {
        $this->entries[] = compact('event', 'context');
    }
};

echo "COST 1 FIX — Testing with fakes (no infrastructure needed):\n";
$looseService = new LooseOrderService($fakeGateway, $fakeDb, $fakeLogger);
$result = $looseService->placeOrder(['id' => 1, 'amount' => 500.00, 'token' => 'tok_test']);

echo "  placeOrder returned: " . ($result ? 'true' : 'false') . "\n";
echo "  Gateway was called: " . count($fakeGateway->calls) . " time(s)\n";
echo "  Orders saved: " . count($fakeDb->saved) . "\n";
echo "  Log entries: " . count($fakeLogger->entries) . "\n";

// Test the failure path — impossible with TightOrderService
$fakeGateway->shouldSucceed = false;
$result2 = $looseService->placeOrder(['id' => 2, 'amount' => 250.00, 'token' => 'tok_fail']);
echo "  Failed payment test: " . ($result2 ? 'charged (wrong!)' : 'not charged ✓') . "\n";

// ── COST 2 FIX: switch provider without touching LooseOrderService ──

echo "\nCOST 2 FIX — Switching provider: zero edits to LooseOrderService:\n";
$payfastService = new LooseOrderService(
    new PayFastGateway('MERCH-001234'), // ← Only this line changes
    $fakeDb,
    $fakeLogger
);
$payfastService->placeOrder(['id' => 3, 'amount' => 750.00, 'token' => 'pf_tok_abc']);

// ── COST 3 FIX: different implementations per context ──

echo "\nCOST 3 FIX — Different implementations per context:\n";
echo "  Test context:      new LooseOrderService(FakeGateway, FakeDb, FakeLogger)\n";
echo "  Staging context:   new LooseOrderService(StripeTestGateway, RealDb, ConsoleLogger)\n";
echo "  Production context:new LooseOrderService(StripeGateway, MySqlDb, FileLogger)\n";
echo "  LooseOrderService code is IDENTICAL in all three — only wiring changes.\n";

echo "\n--- Recap ---\n";
echo "Cost 1 (Untestability):  fixed by injecting fakes — no infrastructure needed.\n";
echo "Cost 2 (Inflexibility):  fixed by wiring at composition root — no class edits.\n";
echo "Cost 3 (Hard to swap):   fixed by interfaces — any implementation works.\n";
echo "All three costs have the same root cause: `new` inside the class.\n";
echo "All three are fixed by the same solution: inject via constructor.\n";