<?php
declare(strict_types=1);

/**
 * Example 03 — When to Assert on Calls: Mock vs Spy
 * ---------------------------------------------------
 * Run via PHPUnit:
 *   ./vendor/bin/phpunit module-5-testing-and-tdd/lesson-5.5-testing-behaviours-not-layouts/examples/03-when-to-assert-on-calls.php
 *
 * This example draws the line between:
 *   ✅ Legitimate call assertions — the call IS the observable behaviour
 *   ❌ Over-specified call assertions — internal mechanics dressed up as tests
 *
 * The three-question test for whether a call assertion is legitimate:
 *   1. Is the call part of the class's CONTRACT with its callers?
 *   2. Is the call VERIFIABLE from outside the class boundary?
 *   3. Would it be a BUG if this call did NOT happen?
 *
 * If the answer to all three is YES → assert on it.
 * If any answer is NO → do not.
 *
 * This example covers:
 *   A. Spy vs mock: which is safer and why
 *   B. Legitimate call assertions (email sent, payment charged, record saved)
 *   C. Illegitimate call assertions (log wording, query count, internal helpers)
 *   D. Asserting on absence of calls
 *   E. The "observable boundary" applied to each case
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// Contracts
// ─────────────────────────────────────────────────────────────────────────────

interface InvoiceRepositoryInterface
{
    public function save(array $invoice): array;
    public function findById(int $id): ?array;
}

interface PdfGeneratorInterface
{
    public function generate(array $data): string; // returns PDF bytes
}

interface MailerInterface
{
    public function send(string $to, string $subject, string $body, string $attachment = ''): bool;
}

interface AuditLogInterface
{
    public function write(string $action, array $context): void;
}

interface MetricsInterface
{
    public function increment(string $metric, int $by = 1): void;
}

// ─────────────────────────────────────────────────────────────────────────────
// The class under test
// ─────────────────────────────────────────────────────────────────────────────

class InvoiceService
{
    public function __construct(
        private InvoiceRepositoryInterface $repository,
        private PdfGeneratorInterface      $pdf,
        private MailerInterface            $mailer,
        private AuditLogInterface          $audit,
        private MetricsInterface           $metrics
    ) {}

    /**
     * Creates an invoice, generates a PDF, emails it, and logs the action.
     *
     * @throws \InvalidArgumentException for invalid inputs
     */
    public function issue(string $customerEmail, array $lineItems): array
    {
        if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email: {$customerEmail}");
        }

        if (empty($lineItems)) {
            throw new \InvalidArgumentException('Invoice must have at least one line item');
        }

        $total   = array_sum(array_column($lineItems, 'amount'));
        $invoice = $this->repository->save([
            'customer_email' => $customerEmail,
            'line_items'     => $lineItems,
            'total'          => $total,
            'status'         => 'issued',
        ]);

        // Internal: generate PDF (implementation detail — PDF format may change)
        $pdfBytes = $this->pdf->generate($invoice);

        // Contractual: send to the customer (callers care about this)
        $this->mailer->send(
            $customerEmail,
            "Invoice #{$invoice['id']}",
            "Please find your invoice attached.",
            $pdfBytes
        );

        // Internal: audit log (callers don't care about log wording)
        $this->audit->write('invoice.issued', ['id' => $invoice['id'], 'total' => $total]);

        // Internal: metrics (callers don't care about which metric was incremented)
        $this->metrics->increment('invoices.issued');

        return $invoice;
    }

    public function void(int $invoiceId): void
    {
        $invoice = $this->repository->findById($invoiceId);

        if ($invoice === null) {
            throw new \DomainException("Invoice {$invoiceId} not found");
        }

        $this->audit->write('invoice.voided', ['id' => $invoiceId]);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// The test class — drawn line between legitimate and illegitimate assertions
// ─────────────────────────────────────────────────────────────────────────────

class WhenToAssertOnCallsExampleTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────────────────
    // Shared doubles
    // ─────────────────────────────────────────────────────────────────────────

    private function makeFakeRepo(): InvoiceRepositoryInterface
    {
        return new class implements InvoiceRepositoryInterface {
            private int   $nextId = 1;
            private array $store  = [];
            public function save(array $invoice): array {
                $invoice['id']  = $this->nextId++;
                $this->store[$invoice['id']] = $invoice;
                return $invoice;
            }
            public function findById(int $id): ?array {
                return $this->store[$id] ?? null;
            }
        };
    }

    private function stubPdf(): PdfGeneratorInterface
    {
        return new class implements PdfGeneratorInterface {
            public function generate(array $data): string { return '%PDF-stub'; }
        };
    }

    private function nullAudit(): AuditLogInterface
    {
        return new class implements AuditLogInterface {
            public function write(string $action, array $context): void {}
        };
    }

    private function nullMetrics(): MetricsInterface
    {
        return new class implements MetricsInterface {
            public function increment(string $metric, int $by = 1): void {}
        };
    }

    // ═══════════════════════════════════════════════════════════
    // PART A — Spy vs mock
    // ═══════════════════════════════════════════════════════════

    /**
     * SPY approach — records calls, asserts AFTER the action.
     *
     * Advantages over mock:
     *   - Assertion appears in the test (readable)
     *   - If the call does not happen, the test fails with a clear message
     *   - No "expectation declared before action" confusion
     *   - Extra calls beyond what we assert do not cause failures
     */
    public function testSpyApproach_EmailSentToCustomer(): void
    {
        $spyMailer = new class implements MailerInterface {
            public array $sent = [];
            public function send(string $to, string $subject, string $body, string $attachment = ''): bool {
                $this->sent[] = compact('to', 'subject', 'body', 'attachment');
                return true;
            }
        };

        $service = new InvoiceService(
            $this->makeFakeRepo(), $this->stubPdf(), $spyMailer, $this->nullAudit(), $this->nullMetrics()
        );

        $service->issue('alice@example.com', [['description' => 'Consulting', 'amount' => 5000]]);

        // ✅ Assert AFTER the action — readable, explicit
        $this->assertCount(1, $spyMailer->sent);
        $this->assertSame('alice@example.com', $spyMailer->sent[0]['to']);
    }

    /**
     * MOCK approach — declares expectations BEFORE the action.
     *
     * Disadvantages:
     *   - Expectation is far from the failing assertion
     *   - ALL undeclared calls fail the test (fragile)
     *   - Test structure is inverted (expectations before arrange)
     *
     * Still valid — use when you specifically need ALL-call coverage.
     * But prefer spies for most cases.
     */
    public function testMockApproach_EmailSentOnce_ForComparison(): void
    {
        $mockMailer = $this->createMock(MailerInterface::class);

        // Mock: declares expectation BEFORE the action
        $mockMailer->expects($this->once())
            ->method('send');

        $service = new InvoiceService(
            $this->makeFakeRepo(), $this->stubPdf(), $mockMailer, $this->nullAudit(), $this->nullMetrics()
        );

        $service->issue('alice@example.com', [['description' => 'Consulting', 'amount' => 5000]]);

        // No assertion needed here — mock verifies at tearDown()
        // But this makes the test harder to read and diagnose
    }

    // ═══════════════════════════════════════════════════════════
    // PART B — Legitimate call assertions
    // ═══════════════════════════════════════════════════════════

    /**
     * ✅ LEGITIMATE: "Invoice email was sent" is a contract.
     *
     * Three-question test:
     *   1. Is it part of the contract? YES — customers must receive their invoice
     *   2. Is it verifiable externally? YES — via spy on the mailer
     *   3. Would absence be a bug? YES — unfulfilled invoice delivery is a bug
     */
    public function testIssueEmailsInvoiceToCustomer(): void
    {
        $spyMailer = new class implements MailerInterface {
            public array $sent = [];
            public function send(string $to, string $s, string $b, string $a = ''): bool {
                $this->sent[] = compact('to', 's', 'b', 'a');
                return true;
            }
        };

        $service = new InvoiceService(
            $this->makeFakeRepo(), $this->stubPdf(), $spyMailer, $this->nullAudit(), $this->nullMetrics()
        );

        $service->issue('alice@example.com', [['description' => 'Dev work', 'amount' => 10000]]);

        $this->assertCount(1, $spyMailer->sent);
        $this->assertSame('alice@example.com', $spyMailer->sent[0]['to']);
    }

    /**
     * ✅ LEGITIMATE: "Exactly one email" is a contract.
     *
     * Sending the invoice twice would be a billing error.
     * The count assertion IS the specification of correct behaviour.
     */
    public function testIssueDoesNotSendDuplicateEmails(): void
    {
        $spyMailer = new class implements MailerInterface {
            public array $sent = [];
            public function send(string $to, string $s, string $b, string $a = ''): bool {
                $this->sent[] = compact('to');
                return true;
            }
        };

        $service = new InvoiceService(
            $this->makeFakeRepo(), $this->stubPdf(), $spyMailer, $this->nullAudit(), $this->nullMetrics()
        );

        $service->issue('alice@example.com', [['description' => 'Dev work', 'amount' => 10000]]);

        $this->assertCount(1, $spyMailer->sent); // NOT 2, NOT 0
    }

    /**
     * ✅ LEGITIMATE: "PDF was attached" is verifiable from the spy.
     *
     * Attaching the PDF is part of the contract — a blank attachment would be a bug.
     * We assert the attachment is non-empty, not its exact byte content.
     */
    public function testIssueAttachesPdfToEmail(): void
    {
        $spyMailer = new class implements MailerInterface {
            public array $sent = [];
            public function send(string $to, string $s, string $b, string $attachment = ''): bool {
                $this->sent[] = compact('to', 's', 'b', 'attachment');
                return true;
            }
        };

        $service = new InvoiceService(
            $this->makeFakeRepo(), $this->stubPdf(), $spyMailer, $this->nullAudit(), $this->nullMetrics()
        );

        $service->issue('alice@example.com', [['description' => 'Dev work', 'amount' => 10000]]);

        $this->assertNotEmpty($spyMailer->sent[0]['attachment']);
    }

    /**
     * ✅ LEGITIMATE: "No email when validation fails" is a contract.
     *
     * Sending an email before the invoice is valid would be a bug.
     * The spy confirms the absence of the side effect.
     */
    public function testNoEmailSentWhenValidationFails(): void
    {
        $spyMailer = new class implements MailerInterface {
            public array $sent = [];
            public function send(string $to, string $s, string $b, string $a = ''): bool {
                $this->sent[] = compact('to');
                return true;
            }
        };

        $service = new InvoiceService(
            $this->makeFakeRepo(), $this->stubPdf(), $spyMailer, $this->nullAudit(), $this->nullMetrics()
        );

        try {
            $service->issue('bad-email', [['description' => 'Dev work', 'amount' => 10000]]);
        } catch (\InvalidArgumentException) {}

        $this->assertEmpty($spyMailer->sent);
    }

    // ═══════════════════════════════════════════════════════════
    // PART C — Illegitimate call assertions (shown as comments)
    // ═══════════════════════════════════════════════════════════

    /**
     * ❌ NOT LEGITIMATE: asserting on audit log wording
     *
     * Three-question test:
     *   1. Is it part of the contract? NO — the log is internal
     *   2. Is it verifiable externally? Technically yes (spy) but should not be
     *   3. Would absence/change be a bug? NO — 'invoice.issued' → 'invoice.created' is fine
     *
     * THIS TEST IS DELIBERATELY NOT WRITTEN because it would be brittle.
     * The correct test for "audit is called" tests only the OUTCOME
     * (that a record exists) not the exact key used.
     */
    // public function testAuditLogIsCalledWithExactAction(): void
    // {
    //     $spyAudit = new class implements AuditLogInterface { ... };
    //     ...
    //     $this->assertSame('invoice.issued', $spyAudit->records[0]['action']); // ← brittle
    // }

    /**
     * ❌ NOT LEGITIMATE: asserting on PDF generator call count
     *
     * The PDF generator is an internal detail. If we cache PDFs and call it
     * zero times on a cache hit, the test should not break. The observable
     * outcome is that the email has an attachment — not how the attachment was made.
     */
    // public function testPdfGeneratorCalledExactlyOnce(): void { ... } // ← brittle

    /**
     * ❌ NOT LEGITIMATE: asserting on metrics increments
     *
     * Metrics are observability infrastructure. Renaming 'invoices.issued' to
     * 'billing.invoices.created' for a monitoring dashboard change should not
     * fail the test suite.
     */
    // public function testMetricsIncrementCalledWithInvoicesIssued(): void { ... } // ← brittle

    // ═══════════════════════════════════════════════════════════
    // PART D — The one legitimate call assertion for audit
    // ═══════════════════════════════════════════════════════════

    /**
     * If "an audit record MUST exist" is part of the contract (e.g. for
     * compliance), test that a record WAS written, not its exact wording.
     *
     * This is the least-brittle version of an audit assertion:
     *   ✅ At least one record was written (existence)
     *   ✅ The record includes the invoice ID (contractual identifier)
     *   ❌ The record has this exact action string (internal naming)
     */
    public function testIssueWritesAnAuditRecordContainingInvoiceId(): void
    {
        $spyAudit = new class implements AuditLogInterface {
            public array $records = [];
            public function write(string $action, array $context): void {
                $this->records[] = compact('action', 'context');
            }
        };

        $service = new InvoiceService(
            $this->makeFakeRepo(), $this->stubPdf(),
            new class implements MailerInterface {
                public function send(string $t, string $s, string $b, string $a = ''): bool { return true; }
            },
            $spyAudit,
            $this->nullMetrics()
        );

        $result = $service->issue('alice@example.com', [['description' => 'Work', 'amount' => 1000]]);

        // ✅ An audit record was written
        $this->assertNotEmpty($spyAudit->records);

        // ✅ The record contains the invoice ID (contractual context)
        $contexts = array_column($spyAudit->records, 'context');
        $ids      = array_column($contexts, 'id');
        $this->assertContains($result['id'], $ids);

        // ❌ NOT asserting: the exact action string 'invoice.issued'
        // ❌ NOT asserting: the exact number of audit records
    }

    // ═══════════════════════════════════════════════════════════
    // PART E — Summary: the three questions
    // ═══════════════════════════════════════════════════════════

    /**
     * Summary of all call assertions in this file:
     *
     *   ✅ $spyMailer->sent assertCount(1)       — "one email" is contract
     *   ✅ $spyMailer->sent[0]['to']              — "correct recipient" is contract
     *   ✅ $spyMailer->sent[0]['attachment']      — "PDF attached" is contract
     *   ✅ assertEmpty($spyMailer->sent)          — "no email on failure" is contract
     *   ✅ $spyAudit records assertNotEmpty       — "audit exists" is compliance contract
     *   ✅ $spyAudit record contains invoice ID   — "ID in audit" is contractual context
     *
     *   ❌ audit action string 'invoice.issued'   — internal naming, not contract
     *   ❌ metrics metric name                     — internal naming, not contract
     *   ❌ PDF generator call count               — internal optimisation detail
     *   ❌ exact email subject wording            — unless subject IS the contract
     */
    public function testCallAssertionSummary_AllPassAfterRefactor(): void
    {
        // This test just documents: if you move InvoiceService to use a different
        // internal audit key, PDF caching, or metric naming, none of the
        // assertions above will fail. The contract is preserved.
        $this->assertTrue(true); // sentinel — remove this and the class must still work
    }
}