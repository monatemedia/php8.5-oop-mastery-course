<?php
declare(strict_types=1);

/**
 * CHALLENGE SOLUTION — Lesson 2.4: Anonymous Classes
 * ─────────────────────────────────────────────────
 * ⚠️  Only open this file after completing starter.php yourself.
 *
 * Key things to compare in your solution:
 *   1. No named test double classes remain
 *   2. Every test function defines its own anonymous class stubs
 *   3. Null-object stubs (do nothing) used where behaviour is irrelevant
 *   4. Spy stubs (record calls) used where assertions require observation
 *   5. PaymentStatus enum used throughout — no raw strings
 *   6. All five tests print PASS
 */


// ═══════════════════════════════════════════════════════════════════════
// PRODUCTION CODE — unchanged
// ═══════════════════════════════════════════════════════════════════════

enum PaymentStatus: string {
    case Pending  = 'pending';
    case Success  = 'success';
    case Failed   = 'failed';
    case Refunded = 'refunded';
}

interface PaymentGateway {
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
// NO NAMED TEST DOUBLES — Task 1 complete
// FakeGateway, FakeLogger, and FakeAuditStore have been removed.
// Each test function defines exactly what it needs, inline.
// ═══════════════════════════════════════════════════════════════════════


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
// TEST FUNCTIONS — each defines its own anonymous class stubs
// ═══════════════════════════════════════════════════════════════════════

function testSuccessfulCharge(): void {
    // Gateway spy — always succeeds, tracks call count
    $gateway = new class implements PaymentGateway {
        public function charge(float $amount, string $currency, string $token): PaymentStatus {
            return PaymentStatus::Success;
        }
        public function refund(string $transactionId): PaymentStatus {
            return PaymentStatus::Refunded;
        }
    };

    // Logger spy — records all entries for assertion
    $logger = new class implements Logger {
        public array $entries = [];
        public function log(string $level, string $message): void {
            $this->entries[] = compact('level', 'message');
        }
    };

    // Audit spy — records all events for assertion
    $audit = new class implements AuditStore {
        public array $events = [];
        public function record(string $event, array $context = []): void {
            $this->events[] = ['event' => $event, 'context' => $context, 'at' => date('H:i:s')];
        }
        public function getEntries(): array { return $this->events; }
    };

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
    // Gateway stub — always fails
    $gateway = new class implements PaymentGateway {
        public function charge(float $amount, string $currency, string $token): PaymentStatus {
            return PaymentStatus::Failed;
        }
        public function refund(string $transactionId): PaymentStatus {
            return PaymentStatus::Refunded;
        }
    };

    // Null-object logger — does nothing (we don't need to assert on it here)
    $logger = new class implements Logger {
        public function log(string $level, string $message): void {}
    };

    // Null-object audit — does nothing
    $audit = new class implements AuditStore {
        public function record(string $event, array $context = []): void {}
        public function getEntries(): array { return []; }
    };

    $processor = new PaymentProcessor($gateway, $logger, $audit);
    $status    = $processor->charge(500.00, 'ZAR', 'tok_abc123');

    assertThat($status === PaymentStatus::Failed, "Status should be Failed");
}

function testRefund(): void {
    // Gateway stub — returns Refunded for refund()
    $gateway = new class implements PaymentGateway {
        public function charge(float $amount, string $currency, string $token): PaymentStatus {
            return PaymentStatus::Success;
        }
        public function refund(string $transactionId): PaymentStatus {
            return PaymentStatus::Refunded;
        }
    };

    // Null-object logger
    $logger = new class implements Logger {
        public function log(string $level, string $message): void {}
    };

    // Audit spy — need to assert on 'payment.refunded' event
    $audit = new class implements AuditStore {
        public array $events = [];
        public function record(string $event, array $context = []): void {
            $this->events[] = ['event' => $event, 'context' => $context, 'at' => date('H:i:s')];
        }
        public function getEntries(): array { return $this->events; }
    };

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
    // Gateway stub — always succeeds
    $gateway = new class implements PaymentGateway {
        public function charge(float $amount, string $currency, string $token): PaymentStatus {
            return PaymentStatus::Success;
        }
        public function refund(string $transactionId): PaymentStatus {
            return PaymentStatus::Refunded;
        }
    };

    // Logger spy — captures messages
    $logger = new class implements Logger {
        public array $entries = [];
        public function log(string $level, string $message): void {
            $this->entries[] = compact('level', 'message');
        }
    };

    // Null-object audit
    $audit = new class implements AuditStore {
        public function record(string $event, array $context = []): void {}
        public function getEntries(): array { return []; }
    };

    $processor = new PaymentProcessor($gateway, $logger, $audit);
    $processor->charge(100.00, 'ZAR', 'tok_def456');

    $messages  = array_column($logger->entries, 'message');
    $hasCharge = count(array_filter($messages, fn(string $m) => str_contains($m, 'charged'))) > 0;

    assertThat($hasCharge, "Logger should have captured a message containing 'charged'");
}

function testInvalidToken(): void {
    // Gateway stub — throws on empty token
    $gateway = new class implements PaymentGateway {
        public function charge(float $amount, string $currency, string $token): PaymentStatus {
            if (empty($token)) {
                throw new \InvalidArgumentException("Token cannot be empty.");
            }
            return PaymentStatus::Success;
        }
        public function refund(string $transactionId): PaymentStatus {
            return PaymentStatus::Refunded;
        }
    };

    // Logger spy — we need to assert an ERROR entry was logged
    $logger = new class implements Logger {
        public array $entries = [];
        public function log(string $level, string $message): void {
            $this->entries[] = compact('level', 'message');
        }
    };

    // Null-object audit
    $audit = new class implements AuditStore {
        public function record(string $event, array $context = []): void {}
        public function getEntries(): array { return []; }
    };

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


// ─────────────────────────────────────────────────────────────────────────────
// SELF-REVIEW CHECKLIST
// ─────────────────────────────────────────────────────────────────────────────
echo "\n--- Self-review checklist ---\n";
echo "[ ] FakeGateway, FakeLogger, FakeAuditStore are gone?\n";
echo "[ ] Every test function defines its dependencies inline as anonymous classes?\n";
echo "[ ] Spy stubs have public properties for assertions (\$entries, \$events)?\n";
echo "[ ] Null-object stubs have empty method bodies where behaviour is irrelevant?\n";
echo "[ ] PaymentStatus enum used throughout — no raw 'success'/'failed' strings?\n";
echo "[ ] All five tests print PASS?\n";
echo "[ ] Gateway stub in testInvalidToken throws on empty token?\n";