<?php
declare(strict_types=1);

/**
 * Example 01 — Brittle vs Resilient Tests
 * -----------------------------------------
 * Run via PHPUnit:
 *   ./vendor/bin/phpunit module-5-testing-and-tdd/lesson-5.5-testing-behaviours-not-layouts/examples/01-brittle-vs-resilient-tests.php
 *
 * This file contains the SAME feature tested TWO ways:
 *   - BrittlePaymentServiceTest   ← tests layout (internal structure)
 *   - ResilientPaymentServiceTest ← tests behaviour (observable outcomes)
 *
 * Both test suites cover PaymentService, which processes a payment and
 * sends a confirmation email.
 *
 * Run BOTH test classes. Both pass now.
 * Then apply the refactor described in the comment at the bottom.
 * The brittle tests will fail; the resilient tests will still pass.
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// Contracts
// ─────────────────────────────────────────────────────────────────────────────

interface PaymentGatewayInterface
{
    public function charge(int $amountCents, string $token): bool;
}

interface MailerInterface
{
    public function send(string $to, string $subject, string $body): bool;
}

interface LoggerInterface
{
    public function info(string $message): void;
    public function error(string $message): void;
}

// ─────────────────────────────────────────────────────────────────────────────
// The class under test
// ─────────────────────────────────────────────────────────────────────────────

class PaymentService
{
    public function __construct(
        private PaymentGatewayInterface $gateway,
        private MailerInterface         $mailer,
        private LoggerInterface         $logger
    ) {}

    /**
     * @throws \InvalidArgumentException for invalid inputs
     * @return array{success: bool, transaction_id: string|null, error: string|null}
     */
    public function processPayment(
        int    $amountCents,
        string $token,
        string $customerEmail
    ): array {
        if ($amountCents <= 0) {
            throw new \InvalidArgumentException(
                "Amount must be positive, got {$amountCents}"
            );
        }

        if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email: {$customerEmail}");
        }

        $this->logger->info('Processing payment');

        $charged = $this->gateway->charge($amountCents, $token);

        if (!$charged) {
            $this->logger->error('Payment declined');
            return ['success' => false, 'transaction_id' => null, 'error' => 'Payment declined'];
        }

        $transactionId = strtoupper(bin2hex(random_bytes(8)));

        $this->logger->info('Payment successful');

        $this->mailer->send(
            $customerEmail,
            'Payment confirmed',
            "Your payment of R" . number_format($amountCents / 100, 2) . " was successful."
        );

        $this->logger->info('Confirmation email sent');

        return ['success' => true, 'transaction_id' => $transactionId, 'error' => null];
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// BRITTLE test suite — tests layout (internal structure)
// These all pass NOW. Apply the refactor at the bottom and they will FAIL.
// ─────────────────────────────────────────────────────────────────────────────

class BrittlePaymentServiceTest extends TestCase
{
    private function nullGateway(): PaymentGatewayInterface
    {
        return new class implements PaymentGatewayInterface {
            public function charge(int $amountCents, string $token): bool { return true; }
        };
    }

    private function nullMailer(): MailerInterface
    {
        return new class implements MailerInterface {
            public function send(string $to, string $subject, string $body): bool { return true; }
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Anti-pattern 1: asserting on constructor parameter count
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * ❌ BRITTLE: tests how many constructor parameters the class has.
     *
     * WHY IT WILL BREAK: add a 4th dependency (e.g. CurrencyConverterInterface)
     * to improve the service → this test fails. The behaviour is unchanged.
     *
     * WHY IT EXISTS: developer wanted to verify "DI is set up correctly".
     * An integration test (5.4) or a behaviour test does this properly.
     */
    public function testServiceHasThreeConstructorParameters(): void
    {
        $reflection = new \ReflectionClass(PaymentService::class);
        $params     = $reflection->getConstructor()->getParameters();

        $this->assertCount(3, $params);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Anti-pattern 2: asserting on private property storage
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * ❌ BRITTLE: verifies the injected gateway is stored in $gateway.
     *
     * WHY IT WILL BREAK: rename $gateway to $paymentGateway, or replace it
     * with a property hook → test fails. No behaviour changed.
     *
     * WHY IT EXISTS: developer wanted to verify constructor injection worked.
     * Test the BEHAVIOUR that depends on the gateway (does it charge correctly?)
     * not the plumbing that stores it.
     */
    public function testGatewayIsStoredInGatewayProperty(): void
    {
        $gateway = $this->nullGateway();
        $service = new PaymentService($gateway, $this->nullMailer(),
            new class implements LoggerInterface {
                public function info(string $m): void {}
                public function error(string $m): void {}
            }
        );

        $prop = new \ReflectionProperty(PaymentService::class, 'gateway');
        $prop->setAccessible(true);

        $this->assertSame($gateway, $prop->getValue($service));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Anti-pattern 3: asserting on exact log message strings
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * ❌ BRITTLE: asserts exact logger call sequence.
     *
     * WHY IT WILL BREAK: rename "Processing payment" to "Initiating charge",
     * merge two log calls, or add a debug() call → test fails.
     * The behaviour (payment processed, email sent) is unchanged.
     *
     * WHY IT EXISTS: developer wanted to verify "something was logged".
     * If logging is a contract, test that a log ENTRY was made,
     * not the exact wording.
     */
    public function testExactLogMessageSequence(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);

        $mockLogger->expects($this->exactly(3))
            ->method('info')
            ->withConsecutive(
                ['Processing payment'],
                ['Payment successful'],
                ['Confirmation email sent']
            );

        $service = new PaymentService($this->nullGateway(), $this->nullMailer(), $mockLogger);
        $service->processPayment(1000, 'tok_4242', 'alice@example.com');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Anti-pattern 4: asserting on parameter names via reflection
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * ❌ BRITTLE: asserts that the constructor parameter is named '$gateway'.
     *
     * WHY IT WILL BREAK: rename the parameter to '$paymentGateway' for clarity
     * (common in larger codebases) → test fails. No behaviour changed.
     */
    public function testFirstConstructorParameterIsNamedGateway(): void
    {
        $reflection = new \ReflectionClass(PaymentService::class);
        $params     = $reflection->getConstructor()->getParameters();

        $this->assertSame('gateway', $params[0]->getName());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Anti-pattern 5: asserting on transaction ID format (over-specification)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * ❌ BRITTLE: asserts the exact format of the transaction ID.
     *
     * WHY IT WILL BREAK: switch from 8-byte hex to UUID format, or add a prefix
     * → test fails. The caller only needs to know a transaction ID was produced,
     * not its internal format — unless the format is a documented contract.
     *
     * Compare: asserting a non-null string is resilient.
     * Asserting exact format is brittle unless the format IS the contract.
     */
    public function testTransactionIdIsExact16CharUppercaseHex(): void
    {
        $service = new PaymentService($this->nullGateway(), $this->nullMailer(),
            new class implements LoggerInterface {
                public function info(string $m): void {}
                public function error(string $m): void {}
            }
        );

        $result = $service->processPayment(1000, 'tok_4242', 'alice@example.com');

        // Asserts the exact implementation detail: 8 bytes → 16 hex chars, uppercased
        $this->assertMatchesRegularExpression('/^[A-F0-9]{16}$/', $result['transaction_id']);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// RESILIENT test suite — tests behaviour (observable outcomes)
// These pass NOW. Apply the refactor below and they will STILL pass.
// ─────────────────────────────────────────────────────────────────────────────

class ResilientPaymentServiceTest extends TestCase
{
    // Reusable doubles that satisfy the type system without caring about implementation
    private function nullLogger(): LoggerInterface
    {
        return new class implements LoggerInterface {
            public function info(string $m): void {}
            public function error(string $m): void {}
        };
    }

    private function stubSuccessGateway(): PaymentGatewayInterface
    {
        return new class implements PaymentGatewayInterface {
            public function charge(int $amountCents, string $token): bool { return true; }
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ✅ Test behaviour: return value on success
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Tests the observable contract: on success, processPayment() returns
     * ['success' => true] with a non-null transaction ID and null error.
     *
     * Survives: renaming properties, adding parameters, changing log messages,
     * extracting helpers, changing ID format (as long as it is non-null).
     */
    public function testProcessPaymentReturnsTrueOnSuccess(): void
    {
        $nullMailer = new class implements MailerInterface {
            public function send(string $to, string $s, string $b): bool { return true; }
        };

        $service = new PaymentService($this->stubSuccessGateway(), $nullMailer, $this->nullLogger());

        $result = $service->processPayment(1000, 'tok_4242', 'alice@example.com');

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['transaction_id']);
        $this->assertNull($result['error']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ✅ Test behaviour: return value on failure
    // ─────────────────────────────────────────────────────────────────────────

    public function testProcessPaymentReturnsFalseWhenGatewayDeclines(): void
    {
        $decliningGateway = new class implements PaymentGatewayInterface {
            public function charge(int $amountCents, string $token): bool { return false; }
        };

        $nullMailer = new class implements MailerInterface {
            public function send(string $to, string $s, string $b): bool { return true; }
        };

        $service = new PaymentService($decliningGateway, $nullMailer, $this->nullLogger());

        $result = $service->processPayment(1000, 'tok_fail', 'alice@example.com');

        $this->assertFalse($result['success']);
        $this->assertNull($result['transaction_id']);
        $this->assertSame('Payment declined', $result['error']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ✅ Test behaviour: exception for invalid input
    // ─────────────────────────────────────────────────────────────────────────

    public function testProcessPaymentThrowsForNonPositiveAmount(): void
    {
        $service = new PaymentService(
            $this->stubSuccessGateway(),
            new class implements MailerInterface {
                public function send(string $to, string $s, string $b): bool { return true; }
            },
            $this->nullLogger()
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be positive');

        $service->processPayment(0, 'tok', 'alice@example.com');
    }

    public function testProcessPaymentThrowsForInvalidEmail(): void
    {
        $service = new PaymentService(
            $this->stubSuccessGateway(),
            new class implements MailerInterface {
                public function send(string $to, string $s, string $b): bool { return true; }
            },
            $this->nullLogger()
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email');

        $service->processPayment(1000, 'tok', 'not-an-email');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ✅ Test behaviour: side effect (was the email sent?)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * "One confirmation email to the right address" IS a contract.
     * This assertion is appropriate because the caller cares that it happens.
     *
     * NOTE: we do NOT assert on the exact subject wording. That is internal.
     * We assert on the recipient — that IS derived from the input.
     */
    public function testProcessPaymentSendsOneConfirmationEmailOnSuccess(): void
    {
        $spyMailer = new class implements MailerInterface {
            public array $sent = [];
            public function send(string $to, string $subject, string $body): bool {
                $this->sent[] = compact('to', 'subject', 'body');
                return true;
            }
        };

        $service = new PaymentService($this->stubSuccessGateway(), $spyMailer, $this->nullLogger());
        $service->processPayment(1000, 'tok_4242', 'alice@example.com');

        $this->assertCount(1, $spyMailer->sent);
        $this->assertSame('alice@example.com', $spyMailer->sent[0]['to']);
    }

    public function testNoEmailSentWhenPaymentDeclined(): void
    {
        $decliningGateway = new class implements PaymentGatewayInterface {
            public function charge(int $amountCents, string $token): bool { return false; }
        };

        $spyMailer = new class implements MailerInterface {
            public array $sent = [];
            public function send(string $to, string $s, string $b): bool {
                $this->sent[] = compact('to', 's', 'b');
                return true;
            }
        };

        $service = new PaymentService($decliningGateway, $spyMailer, $this->nullLogger());
        $service->processPayment(1000, 'tok_fail', 'alice@example.com');

        $this->assertEmpty($spyMailer->sent);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ✅ Test behaviour: transaction_id is a non-empty string (not null)
    //    — not the exact format
    // ─────────────────────────────────────────────────────────────────────────

    public function testProcessPaymentReturnsNonEmptyTransactionIdOnSuccess(): void
    {
        $nullMailer = new class implements MailerInterface {
            public function send(string $to, string $s, string $b): bool { return true; }
        };

        $service = new PaymentService($this->stubSuccessGateway(), $nullMailer, $this->nullLogger());

        $result = $service->processPayment(500, 'tok', 'alice@example.com');

        // ✅ Tests the CONTRACT: a non-empty string ID exists on success
        // Does NOT assert the exact format (hex, UUID, numeric, etc.)
        $this->assertIsString($result['transaction_id']);
        $this->assertNotEmpty($result['transaction_id']);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// ── APPLY THIS REFACTOR TO SEE THE DIFFERENCE ────────────────────────────────
//
// These changes do NOT alter observable behaviour — they are all internal:
//
// 1. Rename constructor parameter $gateway → $paymentProcessor
//    (and the corresponding property)
//
// 2. Add a 4th constructor parameter: private LogLevelEnum $minLogLevel = LogLevelEnum::INFO
//    (an internal config option)
//
// 3. Change log messages:
//    'Processing payment'      → 'Initiating charge'
//    'Payment successful'      → 'Charge accepted'
//    'Confirmation email sent' → 'Receipt dispatched'
//
// 4. Change transaction ID format from bin2hex(random_bytes(8))
//    to 'TXN-' . strtoupper(substr(md5(uniqid()), 0, 12))
//
// After applying:
//   BrittlePaymentServiceTest  → 4–5 tests fail (layout changed)
//   ResilientPaymentServiceTest → 0 tests fail  (behaviour unchanged)
// ─────────────────────────────────────────────────────────────────────────────