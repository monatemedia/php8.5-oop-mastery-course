<?php
declare(strict_types=1);

/**
 * Example 02 — The Dependency Inversion Principle in Practice
 * -------------------------------------------------------------
 * DIP (the D in SOLID) has two rules:
 *
 *   Rule 1: High-level modules should NOT depend on low-level modules.
 *           Both should depend on abstractions.
 *
 *   Rule 2: Abstractions should NOT depend on details.
 *           Details (concrete implementations) should depend on abstractions.
 *
 * "High-level" = business logic (OrderService, ReportGenerator)
 * "Low-level"  = infrastructure (MySQLDatabase, SmtpMailer, StripeGateway)
 * "Abstraction" = interface (DatabaseInterface, MailerInterface)
 * "Detail"     = concrete class that implements the interface
 *
 * This example builds the same e-commerce system BEFORE and AFTER applying DIP,
 * then shows what the dependency arrows look like in each case.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  The Dependency Inversion Principle in Practice     ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Low-level modules (infrastructure) — used throughout
// ─────────────────────────────────────────────────────────────────────────────

class PostgresDatabase {
    public function __construct(private string $dsn) {}
    public function select(string $table, array $where = []): array {
        echo "  [POSTGRES] SELECT from {$table}\n";
        return [['id' => 1, 'sku' => 'WDG-001', 'name' => 'Widget Pro', 'price' => 29999, 'stock' => 50]];
    }
    public function insert(string $table, array $data): bool {
        echo "  [POSTGRES] INSERT into {$table}\n";
        return true;
    }
}

class MailgunMailer {
    public function __construct(private string $apiKey, private string $domain) {}
    public function send(string $to, string $subject, string $body): bool {
        echo "  [MAILGUN] To: {$to} | {$subject}\n";
        return true;
    }
}

class StripeGateway {
    public function __construct(private string $secretKey) {}
    public function charge(float $amount, string $token): bool {
        echo "  [STRIPE] Charged R{$amount} token={$token}\n";
        return true;
    }
}

class MonologLogger {
    public function info(string $msg): void  { echo "  [INFO] {$msg}\n"; }
    public function error(string $msg): void { echo "  [ERROR] {$msg}\n"; }
}


// ═══════════════════════════════════════════════════════════
// BEFORE DIP — high-level depends directly on low-level
// ═══════════════════════════════════════════════════════════

echo "── BEFORE DIP: high-level depends on low-level ──────\n\n";

/**
 * OrderServiceV1 — high-level module
 * Depends DIRECTLY on PostgresDatabase, MailgunMailer, StripeGateway, MonologLogger.
 *
 * Dependency arrows:
 *   OrderServiceV1 ──► PostgresDatabase  (low-level)
 *   OrderServiceV1 ──► MailgunMailer     (low-level)
 *   OrderServiceV1 ──► StripeGateway     (low-level)
 *   OrderServiceV1 ──► MonologLogger     (low-level)
 */
class OrderServiceV1 {
    private PostgresDatabase $db;
    private MailgunMailer    $mailer;
    private StripeGateway    $gateway;
    private MonologLogger    $logger;

    public function __construct() {
        // Every concrete class hardwired — no abstraction layer
        $this->db      = new PostgresDatabase('pgsql:host=localhost;dbname=shop');
        $this->mailer  = new MailgunMailer('mg-key-abc123', 'mg.example.com');
        $this->gateway = new StripeGateway('sk_live_xyz789');
        $this->logger  = new MonologLogger();
    }

    public function placeOrder(array $cart, string $customerEmail, string $token): bool {
        $this->logger->info("Placing order for {$customerEmail}");

        $total = 0;
        foreach ($cart as $item) {
            $rows   = $this->db->select('products', ['id' => $item['id']]);
            $total += ($rows[0]['price'] ?? 0) * $item['qty'];
        }

        $charged = $this->gateway->charge($total / 100, $token);
        if (!$charged) {
            $this->logger->error("Payment failed");
            return false;
        }

        $this->db->insert('orders', [
            'email' => $customerEmail, 'total' => $total, 'status' => 'paid'
        ]);
        $this->mailer->send($customerEmail, 'Order Confirmed', "Total: R" . ($total / 100));
        $this->logger->info("Order placed successfully");
        return true;
    }
}

echo "OrderServiceV1 — hardwired to Postgres, Mailgun, Stripe, Monolog:\n";
$v1 = new OrderServiceV1();
$v1->placeOrder([['id' => 1, 'qty' => 2]], 'alice@example.com', 'tok_abc');

echo "\nProblems with BEFORE DIP:\n";
echo "  ✗ Switch from Mailgun to SendGrid → must edit OrderServiceV1\n";
echo "  ✗ Switch from Stripe to PayFast   → must edit OrderServiceV1\n";
echo "  ✗ Switch from Postgres to MySQL   → must edit OrderServiceV1\n";
echo "  ✗ Test without real infrastructure → impossible\n";
echo "  ✗ High-level business logic is fragile — it knows too much about infrastructure\n\n";

echo "Dependency arrows point DOWNWARD (high-level → low-level):\n";
echo "  OrderServiceV1\n";
echo "    └──► PostgresDatabase\n";
echo "    └──► MailgunMailer\n";
echo "    └──► StripeGateway\n";
echo "    └──► MonologLogger\n\n";


// ═══════════════════════════════════════════════════════════
// AFTER DIP — both layers depend on abstractions
// ═══════════════════════════════════════════════════════════

echo "── AFTER DIP: both layers depend on abstractions ────\n\n";

// Step 1: Define the abstractions (interfaces owned by the high-level module)
interface OrderDatabaseInterface {
    public function selectProducts(array $ids): array;
    public function insertOrder(array $data): bool;
}

interface OrderMailerInterface {
    public function sendConfirmation(string $to, float $total): bool;
}

interface PaymentInterface {
    public function charge(float $amount, string $token): bool;
}

interface OrderLoggerInterface {
    public function info(string $message): void;
    public function error(string $message): void;
}

// Step 2: Low-level modules implement the abstractions
// (Details depend on abstractions — DIP Rule 2)

class PostgresDatabaseV2 implements OrderDatabaseInterface {
    public function __construct(private string $dsn) {}
    public function selectProducts(array $ids): array {
        echo "  [POSTGRES] Selecting products: " . implode(',', $ids) . "\n";
        return [['id' => 1, 'price' => 29999]];
    }
    public function insertOrder(array $data): bool {
        echo "  [POSTGRES] Inserting order\n";
        return true;
    }
}

class MailgunMailerV2 implements OrderMailerInterface {
    public function __construct(private string $apiKey) {}
    public function sendConfirmation(string $to, float $total): bool {
        echo "  [MAILGUN] Confirmation to {$to} — R{$total}\n";
        return true;
    }
}

class StripeGatewayV2 implements PaymentInterface {
    public function __construct(private string $secretKey) {}
    public function charge(float $amount, string $token): bool {
        echo "  [STRIPE] Charged R{$amount}\n";
        return true;
    }
}

class MonologLoggerV2 implements OrderLoggerInterface {
    public function info(string $msg): void  { echo "  [INFO] {$msg}\n"; }
    public function error(string $msg): void { echo "  [ERROR] {$msg}\n"; }
}

// Step 3: High-level module depends on abstractions ONLY
// (High-level depends on abstraction — DIP Rule 1)

class OrderServiceV2 {
    public function __construct(
        private OrderDatabaseInterface $db,      // abstraction ✅
        private OrderMailerInterface   $mailer,  // abstraction ✅
        private PaymentInterface       $gateway, // abstraction ✅
        private OrderLoggerInterface   $logger   // abstraction ✅
    ) {}

    public function placeOrder(array $cart, string $customerEmail, string $token): bool {
        $this->logger->info("Placing order for {$customerEmail}");

        $ids     = array_column($cart, 'id');
        $rows    = $this->db->selectProducts($ids);
        $total   = array_sum(array_map(fn($r) => $r['price'], $rows)) / 100;

        $charged = $this->gateway->charge($total, $token);
        if (!$charged) {
            $this->logger->error("Payment failed");
            return false;
        }

        $this->db->insertOrder(['email' => $customerEmail, 'total' => $total]);
        $this->mailer->sendConfirmation($customerEmail, $total);
        $this->logger->info("Order placed successfully");
        return true;
    }
}

echo "OrderServiceV2 — depends on abstractions only:\n";
$v2 = new OrderServiceV2(
    new PostgresDatabaseV2('pgsql:host=localhost;dbname=shop'),
    new MailgunMailerV2('mg-key-abc123'),
    new StripeGatewayV2('sk_live_xyz789'),
    new MonologLoggerV2()
);
$v2->placeOrder([['id' => 1, 'qty' => 2]], 'alice@example.com', 'tok_def');

echo "\nDependency arrows AFTER DIP:\n";
echo "  OrderServiceV2 ──► OrderDatabaseInterface  (abstraction)\n";
echo "  OrderServiceV2 ──► OrderMailerInterface    (abstraction)\n";
echo "  OrderServiceV2 ──► PaymentInterface        (abstraction)\n";
echo "  OrderServiceV2 ──► OrderLoggerInterface    (abstraction)\n\n";
echo "  PostgresDatabaseV2 ──► OrderDatabaseInterface (implements — detail depends on abstraction)\n";
echo "  MailgunMailerV2    ──► OrderMailerInterface\n";
echo "  StripeGatewayV2    ──► PaymentInterface\n";
echo "  MonologLoggerV2    ──► OrderLoggerInterface\n\n";

echo "The arrows from high-level to low-level have been INVERTED:\n";
echo "  BEFORE: OrderService ──► StripeGateway (high depends on low)\n";
echo "  AFTER:  OrderService ──► PaymentInterface ◄── StripeGateway\n";
echo "          (both depend on the abstraction in the middle)\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Proof: with DIP, swapping implementations requires zero changes to the service
// ─────────────────────────────────────────────────────────────────────────────

echo "── Proof: swap implementations, service unchanged ───\n\n";

// PayFast instead of Stripe — new class, same interface
class PayFastGateway implements PaymentInterface {
    public function __construct(private string $merchantId) {}
    public function charge(float $amount, string $token): bool {
        echo "  [PAYFAST] Initiated R{$amount} via {$token}\n";
        return true;
    }
}

// Test doubles — zero infrastructure needed
$fakeDb = new class implements OrderDatabaseInterface {
    public function selectProducts(array $ids): array {
        return [['id' => 1, 'price' => 19999]];
    }
    public function insertOrder(array $data): bool { return true; }
};

$spyMailer = new class implements OrderMailerInterface {
    public array $sent = [];
    public function sendConfirmation(string $to, float $total): bool {
        $this->sent[] = compact('to', 'total');
        return true;
    }
};

$nullLogger = new class implements OrderLoggerInterface {
    public function info(string $m): void  {}
    public function error(string $m): void {}
};

echo "Same OrderServiceV2, different gateway (PayFast) — zero code change to service:\n";
$v3 = new OrderServiceV2($fakeDb, $spyMailer, new PayFastGateway('MERCH-001'), $nullLogger);
$v3->placeOrder([['id' => 1, 'qty' => 1]], 'bob@example.com', 'pf_tok_abc');
echo "  Email sent to: {$spyMailer->sent[0]['to']}\n\n";

echo "Test version — no real infrastructure:\n";
$testGateway = new class implements PaymentInterface {
    public bool $shouldSucceed = true;
    public function charge(float $amount, string $token): bool { return $this->shouldSucceed; }
};
$v4 = new OrderServiceV2($fakeDb, $spyMailer, $testGateway, $nullLogger);
$result = $v4->placeOrder([['id' => 1, 'qty' => 1]], 'carol@example.com', 'tok_test');
echo "  placeOrder result: " . ($result ? 'true ✓' : 'false ✗') . "\n";

echo "\n--- Recap ---\n";
echo "DIP Rule 1: High-level modules depend on abstractions (interfaces), not low-level modules.\n";
echo "DIP Rule 2: Details (concrete classes) implement the abstractions.\n";
echo "Result:     Both layers point at the interface — the dependency is inverted.\n";
echo "Benefit:    Swap any implementation without touching high-level business logic.\n";