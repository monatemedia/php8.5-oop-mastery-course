<?php
declare(strict_types=1);

/**
 * CHALLENGE STARTER — Lesson 2.4: Anonymous Classes
 * ─────────────────────────────────────────────────
 * Read CHALLENGE.md before touching this file.
 *
 * PRODUCTION CODE (do not change):
 *   PaymentStatus enum, PaymentGateway, Logger, AuditStore interfaces,
 *   and PaymentProcessor class.
 *
 * YOUR JOB:
 *   1. Delete / comment out FakeGateway, FakeLogger, FakeAuditStore below.
 *   2. Rewrite all five test functions using ANONYMOUS CLASS stubs instead.
 *   3. All five tests must print PASS.
 *
 * Do NOT look at solution.php until you have made a genuine attempt.
 */


// ═══════════════════════════════════════════════════════════════════════
// PRODUCTION CODE — do not modify anything in this section
// ═══════════════════════════════════════════════════════════════════════

enum PaymentStatus: string {
    case Pending  = 'pending';
    case Success  = 'success';
    case Failed   = 'failed';
    case Refunded = 'refunded';
}

interface PaymentGateway {
    /**
     * Attempt to charge the given amount.
     * @throws \InvalidArgumentException if token is empty
     */
    public function charge(float $amount, string $currency, string $token): PaymentStatus;

    public function refund(string $transactionId): PaymentStatus;
}

interface Logger {
    public function log(string $level, string $message): void;
}

interface AuditStore {
    public function record(string $event, array $context = []): void;
    /** @return array<array{event: string, context: array, at: string}> */
    public function getEntries(): array;
}

class PaymentProcessor {
    public function __construct(
        private PaymentGateway $gateway,
        private Logger         $logger,
        private AuditStore     $audit
    ) {}

    public function charge(float $amount, string $currency, string $token): PaymentStatus {
        $this->logger->log('INFO', "Charging {$currency} {$amount} with token {$token}");

        try {
            $status = $this->gateway->charge($amount, $currency, $token);
        } catch (\InvalidArgumentException $e) {
            $this->logger->log('ERROR', "Charge failed: " . $e->getMessage());
            return PaymentStatus::Failed;
        }

        $this->logger->log('INFO', "Charge result: {$status->value}");
        $this->audit->record('payment.charged', [
            'amount'   => $amount,
            'currency' => $currency,
            'status'   => $status->value,
        ]);

        return $status;
    }

    public function refund(string $transactionId): PaymentStatus {
        $this->logger->log('INFO', "Refunding transaction {$transactionId}");
        $status = $this->gateway->refund($transactionId);
        $this->logger->log('INFO', "Refund result: {$status->value}");
        $this->audit->record('payment.refunded', [
            'transaction_id' => $transactionId,
            'status'         => $status->value,
        ]);
        return $status;
    }
}


// ═══════════════════════════════════════════════════════════════════════
// NAMED TEST DOUBLES — delete / comment these out (Task 1)
// Replace each with an anonymous class defined inside each test function
// ═══════════════════════════════════════════════════════════════════════

class FakeGateway implements PaymentGateway {
    public PaymentStatus $chargeResult = PaymentStatus::Success;
    public PaymentStatus $refundResult = PaymentStatus::Refunded;

    public function charge(float $amount, string $currency, string $token): PaymentStatus {
        if (empty($token)) {
            throw new \InvalidArgumentException("Token cannot be empty.");
        }
        return $this->chargeResult;
    }

    public function refund(string $transactionId): PaymentStatus {
        return $this->refundResult;
    }
}

class FakeLogger implements Logger {
    public array $entries = [];

    public function log(string $level, string $message): void {
        $this->entries[] = compact('level', 'message');
    }
}

class FakeAuditStore implements AuditStore {
    public array $events = [];

    public function record(string $event, array $context = []): void {
        $this->events[] = ['event' => $event, 'context' => $context, 'at' => date('H:i:s')];
    }

    public function getEntries(): array { return $this->events; }
}


// ═══════════════════════════════════════════════════════════════════════
// TEST HELPERS
// ═══════════════════════════════════════════════════════════════════════

function assertThat(bool $condition, string $description): void {
    if (!$condition) {
        throw new \AssertionError("FAIL: {$description}");
    }
}

function runTest(string $name, callable $test): void {
    $padded = str_pad($name, 25, '.');
    try {
        $test();
        echo "{$padded} PASS\n";
    } catch (\AssertionError $e) {
        echo "{$padded} {$e->getMessage()}\n";
    } catch (\Throwable $e) {
        echo "{$padded} ERROR: " . $e->getMessage() . "\n";
    }
}


// ═══════════════════════════════════════════════════════════════════════
// TEST FUNCTIONS — rewrite each one to use anonymous class stubs
// ═══════════════════════════════════════════════════════════════════════

function testSuccessfulCharge(): void {
    // TODO: Replace these named classes with anonymous class stubs
    $gateway = new FakeGateway();
    $logger  = new FakeLogger();
    $audit   = new FakeAuditStore();

    $processor = new PaymentProcessor($gateway, $logger, $audit);
    $status    = $processor->charge(500.00, 'ZAR', 'tok_abc123');

    assertThat($status === PaymentStatus::Success, "Status should be Success");
    assertThat(count($logger->entries) === 2,      "Logger should have 2 entries");
    assertThat(count($audit->events)   === 1,      "Audit should have 1 event");
    assertThat(
        $audit->events[0]['event'] === 'payment.charged',
        "Audit event should be 'payment.charged'"
    );
}

function testFailedCharge(): void {
    // TODO: Replace with anonymous class stubs
    // Gateway should always return PaymentStatus::Failed
    // Logger and AuditStore can be null-object anonymous classes (do nothing)
    $gateway = new FakeGateway();
    $gateway->chargeResult = PaymentStatus::Failed;
    $logger  = new FakeLogger();
    $audit   = new FakeAuditStore();

    $processor = new PaymentProcessor($gateway, $logger, $audit);
    $status    = $processor->charge(500.00, 'ZAR', 'tok_abc123');

    assertThat($status === PaymentStatus::Failed, "Status should be Failed");
}

function testRefund(): void {
    // TODO: Replace with anonymous class stubs
    // Gateway should return PaymentStatus::Refunded for refund()
    // Spy on AuditStore to assert 'payment.refunded' event
    $gateway = new FakeGateway();
    $logger  = new FakeLogger();
    $audit   = new FakeAuditStore();

    $processor = new PaymentProcessor($gateway, $logger, $audit);
    $status    = $processor->refund('txn_001');

    assertThat($status === PaymentStatus::Refunded,        "Status should be Refunded");
    assertThat(count($audit->events) === 1,                "Audit should have 1 event");
    assertThat(
        $audit->events[0]['event'] === 'payment.refunded',
        "Audit event should be 'payment.refunded'"
    );
}

function testLoggerCaptures(): void {
    // TODO: Replace with anonymous class stubs
    // Spy logger should capture messages containing "charged"
    $gateway = new FakeGateway();
    $logger  = new FakeLogger();
    $audit   = new FakeAuditStore();

    $processor = new PaymentProcessor($gateway, $logger, $audit);
    $processor->charge(100.00, 'ZAR', 'tok_def456');

    $messages  = array_column($logger->entries, 'message');
    $hasCharge = count(array_filter($messages, fn(string $m) => str_contains($m, 'charged'))) > 0;

    assertThat($hasCharge, "Logger should have captured a message containing 'charged'");
}

function testInvalidToken(): void {
    // TODO: Replace with anonymous class stubs
    // Gateway should throw \InvalidArgumentException for empty token
    // Logger spy should capture the ERROR entry
    $gateway = new FakeGateway();
    $logger  = new FakeLogger();
    $audit   = new FakeAuditStore();

    $processor = new PaymentProcessor($gateway, $logger, $audit);
    $status    = $processor->charge(500.00, 'ZAR', ''); // Empty token

    assertThat($status === PaymentStatus::Failed, "Status should be Failed for empty token");

    $errorEntries = array_filter(
        $logger->entries,
        fn(array $e) => $e['level'] === 'ERROR'
    );

    assertThat(count($errorEntries) > 0, "Logger should have at least one ERROR entry");
}


// ─────────────────────────────────────────────────────────────────────────────
// Run all tests
// ─────────────────────────────────────────────────────────────────────────────

runTest('testSuccessfulCharge', 'testSuccessfulCharge');
runTest('testFailedCharge', 'testFailedCharge');
runTest('testRefund', 'testRefund');
runTest('testLoggerCaptures', 'testLoggerCaptures');
runTest('testInvalidToken', 'testInvalidToken');

echo "All 5 tests passed.\n";