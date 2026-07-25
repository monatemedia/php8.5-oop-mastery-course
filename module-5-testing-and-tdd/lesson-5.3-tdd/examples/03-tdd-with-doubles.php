<?php
declare(strict_types=1);

/**
 * Example 03 — TDD with Anonymous Class Doubles
 * -----------------------------------------------
 * Run via PHPUnit:
 *   ./vendor/bin/phpunit module-5-testing-and-tdd/lesson-5.3-tdd/examples/03-tdd-with-doubles.php
 *
 * The TDD feedback loop with anonymous class doubles:
 *
 *   1. Write the test — define the interface YOU NEED in the anonymous class
 *   2. Test fails (Red) — the service class does not exist yet
 *   3. Create the interface and the service skeleton
 *   4. Test fails differently (interface mismatch) — extract the real interface
 *   5. Implement just enough to pass
 *   6. Refactor
 *
 * Key insight: you write the anonymous class double FIRST, which defines
 * the interface. THEN you extract that interface. This means the interface
 * is shaped by what is easy to test, not by what is easy to implement.
 *
 * This example builds a SubscriptionService from scratch using this technique.
 *
 * PARTS:
 *   A — The interfaces (extracted from what the tests needed)
 *   B — The SubscriptionService implementation
 *   C — The test suite, showing TDD double-first workflow
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

// ─────────────────────────────────────────────────────────────────────────────
// PART A — Interfaces extracted from what tests needed
//
// These did NOT exist at the start of the TDD session.
// The anonymous class stubs in the tests defined the method signatures.
// Then these were extracted as formal interfaces.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Emerged from test: "subscribe() stores a subscription record"
 * The spy in that test needed store(string $email, string $plan): void
 */
interface SubscriptionRepositoryInterface
{
    public function store(string $email, string $plan, \DateTimeImmutable $startedAt): void;
    public function findByEmail(string $email): ?array;
    public function cancel(string $email): void;
}

/**
 * Emerged from test: "subscribe() sends a welcome email"
 * The spy in that test needed send(string $to, string $subject, string $body): bool
 */
interface MailerInterface
{
    public function send(string $to, string $subject, string $body): bool;
}

/**
 * Emerged from test: "the subscription start date is set to now"
 * The stub in that test needed now(): \DateTimeImmutable
 *
 * This interface is the KEY insight from TDD: without a ClockInterface,
 * you cannot control "now" in tests. TDD forces you to inject it.
 */
interface ClockInterface
{
    public function now(): \DateTimeImmutable;
}


// ─────────────────────────────────────────────────────────────────────────────
// PART B — SubscriptionService, built via TDD
// ─────────────────────────────────────────────────────────────────────────────

class SubscriptionService
{
    private const VALID_PLANS = ['free', 'pro', 'enterprise'];

    public function __construct(
        private SubscriptionRepositoryInterface $repository,
        private MailerInterface                 $mailer,
        private ClockInterface                  $clock
    ) {}

    /**
     * Creates a subscription for the given email and plan.
     *
     * @return array{email: string, plan: string, started_at: \DateTimeImmutable}
     * @throws \InvalidArgumentException for invalid email or unknown plan
     */
    public function subscribe(string $email, string $plan): array
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email: {$email}");
        }

        if (!in_array($plan, self::VALID_PLANS, true)) {
            throw new \InvalidArgumentException(
                "Unknown plan '{$plan}'. Valid plans: " . implode(', ', self::VALID_PLANS)
            );
        }

        $startedAt = $this->clock->now();

        $this->repository->store($email, $plan, $startedAt);

        $this->mailer->send(
            to:      $email,
            subject: "Welcome to the {$plan} plan!",
            body:    "Your subscription started on {$startedAt->format('Y-m-d')}."
        );

        return ['email' => $email, 'plan' => $plan, 'started_at' => $startedAt];
    }

    /**
     * Cancels an active subscription.
     *
     * @throws \DomainException when no subscription exists for this email
     */
    public function cancel(string $email): void
    {
        $subscription = $this->repository->findByEmail($email);

        if ($subscription === null) {
            throw new \DomainException("No subscription found for {$email}");
        }

        $this->repository->cancel($email);
    }

    /**
     * Returns the current subscription details, or null if not subscribed.
     */
    public function getSubscription(string $email): ?array
    {
        return $this->repository->findByEmail($email);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// PART C — Test suite, showing TDD double-first workflow
// ─────────────────────────────────────────────────────────────────────────────

class TDDWithDoublesExampleTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────────────────
    // How the doubles were DISCOVERED during TDD
    //
    // TDD Step 1: write the first test.
    //
    // You know you want:
    //   $service->subscribe('alice@example.com', 'pro');
    //
    // You do NOT know yet what SubscriptionService needs.
    // So you write the test, and as you write the anonymous class doubles,
    // you discover the interfaces.
    //
    // The anonymous class for the repository defines:
    //   - store(string $email, string $plan, \DateTimeImmutable $startedAt): void
    //   - findByEmail(string $email): ?array
    //   - cancel(string $email): void
    //
    // The anonymous class for the mailer defines:
    //   - send(string $to, string $subject, string $body): bool
    //
    // The anonymous class for the clock defines:
    //   - now(): \DateTimeImmutable
    //
    // THEN you extract these into formal PHP interfaces.
    // ─────────────────────────────────────────────────────────────────────────

    // ─────────────────────────────────────────────────────────────────────────
    // Reusable double factories
    // These are the same shapes as the anonymous classes you first wrote inline.
    // ─────────────────────────────────────────────────────────────────────────

    private function makeFakeRepo(): SubscriptionRepositoryInterface
    {
        return new class implements SubscriptionRepositoryInterface {
            public array $subscriptions = [];

            public function store(string $email, string $plan, \DateTimeImmutable $startedAt): void {
                $this->subscriptions[$email] = compact('email', 'plan', 'startedAt');
            }

            public function findByEmail(string $email): ?array {
                return $this->subscriptions[$email] ?? null;
            }

            public function cancel(string $email): void {
                unset($this->subscriptions[$email]);
            }
        };
    }

    private function makeSpyMailer(): MailerInterface
    {
        return new class implements MailerInterface {
            public array $sent = [];
            public function send(string $to, string $subject, string $body): bool {
                $this->sent[] = compact('to', 'subject', 'body');
                return true;
            }
        };
    }

    /**
     * The ClockInterface stub is the most important double in this example.
     *
     * TDD discovery moment: when writing a test for subscription start date,
     * you realise you cannot assert an exact timestamp if "now" is real.
     * The clock must be injectable. The test forces this design decision.
     */
    private function makeFixedClock(string $isoDate = '2026-01-15 10:00:00'): ClockInterface
    {
        return new class($isoDate) implements ClockInterface {
            public function __construct(private string $date) {}
            public function now(): \DateTimeImmutable {
                return new \DateTimeImmutable($this->date);
            }
        };
    }

    private function nullMailer(): MailerInterface
    {
        return new class implements MailerInterface {
            public function send(string $to, string $subject, string $body): bool { return true; }
        };
    }

    // ═══════════════════════════════════════════════════════════
    // TDD Round 1: subscribe() — return value
    // ═══════════════════════════════════════════════════════════

    /**
     * TDD step 1: subscribe() must return an array with email and plan.
     *
     * This was the FIRST test written. At this point:
     *   - SubscriptionService does not exist
     *   - The interfaces do not exist
     *   - The anonymous classes below ARE the interface definitions
     *
     * When you write:
     *   new class implements SubscriptionRepositoryInterface { ... }
     * PHP will error until you define that interface.
     * That error is the signal: extract the interface.
     */
    public function testSubscribeReturnsArrayWithEmailAndPlan(): void
    {
        $service = new SubscriptionService(
            $this->makeFakeRepo(),
            $this->nullMailer(),
            $this->makeFixedClock()
        );

        $result = $service->subscribe('alice@example.com', 'pro');

        $this->assertIsArray($result);
        $this->assertSame('alice@example.com', $result['email']);
        $this->assertSame('pro', $result['plan']);
    }

    /**
     * TDD step 2: the returned start date must match the clock's "now".
     *
     * This test is why the clock is injected. Without ClockInterface,
     * you cannot control "now", and this assertion would be flaky.
     *
     * The test reveals the design flaw BEFORE implementation.
     */
    public function testSubscribeReturnsStartDateFromClock(): void
    {
        $fixedClock = $this->makeFixedClock('2026-06-15 09:00:00');
        $service    = new SubscriptionService($this->makeFakeRepo(), $this->nullMailer(), $fixedClock);

        $result = $service->subscribe('alice@example.com', 'pro');

        $this->assertInstanceOf(\DateTimeImmutable::class, $result['started_at']);
        $this->assertSame('2026-06-15', $result['started_at']->format('Y-m-d'));
    }

    // ═══════════════════════════════════════════════════════════
    // TDD Round 2: subscribe() — persistence
    // ═══════════════════════════════════════════════════════════

    /**
     * TDD step 3: subscribe() must persist the record via the repository.
     *
     * The fake repo's $subscriptions array lets us verify persistence
     * without a real database.
     */
    public function testSubscribeStoresSubscriptionViaRepository(): void
    {
        $fakeRepo = $this->makeFakeRepo();
        $service  = new SubscriptionService($fakeRepo, $this->nullMailer(), $this->makeFixedClock());

        $service->subscribe('alice@example.com', 'pro');

        $stored = $fakeRepo->findByEmail('alice@example.com');
        $this->assertNotNull($stored);
        $this->assertSame('alice@example.com', $stored['email']);
        $this->assertSame('pro', $stored['plan']);
    }

    // ═══════════════════════════════════════════════════════════
    // TDD Round 3: subscribe() — side effect (email)
    // ═══════════════════════════════════════════════════════════

    /**
     * TDD step 4: subscribe() must send a welcome email.
     *
     * The spy mailer records the send() call.
     * This test discovered the send() method signature:
     *   send(string $to, string $subject, string $body): bool
     */
    public function testSubscribeSendsWelcomeEmailToSubscriber(): void
    {
        $spyMailer = $this->makeSpyMailer();
        $service   = new SubscriptionService($this->makeFakeRepo(), $spyMailer, $this->makeFixedClock());

        $service->subscribe('alice@example.com', 'pro');

        $this->assertCount(1, $spyMailer->sent);
        $this->assertSame('alice@example.com', $spyMailer->sent[0]['to']);
    }

    public function testSubscribeEmailSubjectContainsPlanName(): void
    {
        $spyMailer = $this->makeSpyMailer();
        $service   = new SubscriptionService($this->makeFakeRepo(), $spyMailer, $this->makeFixedClock());

        $service->subscribe('alice@example.com', 'enterprise');

        $this->assertStringContainsString('enterprise', $spyMailer->sent[0]['subject']);
    }

    /**
     * TDD step 5: the email body must contain the subscription start date.
     *
     * The fixed clock makes this assertion deterministic.
     * Without ClockInterface, this test could not exist reliably.
     */
    public function testSubscribeEmailBodyContainsStartDate(): void
    {
        $fixedClock = $this->makeFixedClock('2026-03-01 00:00:00');
        $spyMailer  = $this->makeSpyMailer();
        $service    = new SubscriptionService($this->makeFakeRepo(), $spyMailer, $fixedClock);

        $service->subscribe('alice@example.com', 'pro');

        $this->assertStringContainsString('2026-03-01', $spyMailer->sent[0]['body']);
    }

    // ═══════════════════════════════════════════════════════════
    // TDD Round 4: subscribe() — validation
    // ═══════════════════════════════════════════════════════════

    public function testSubscribeThrowsForInvalidEmail(): void
    {
        $service = new SubscriptionService(
            $this->makeFakeRepo(), $this->nullMailer(), $this->makeFixedClock()
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email');

        $service->subscribe('not-an-email', 'pro');
    }

    public function testSubscribeThrowsForUnknownPlan(): void
    {
        $service = new SubscriptionService(
            $this->makeFakeRepo(), $this->nullMailer(), $this->makeFixedClock()
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown plan 'premium'");

        $service->subscribe('alice@example.com', 'premium');
    }

    public function testNoEmailSentWhenValidationFails(): void
    {
        $spyMailer = $this->makeSpyMailer();
        $service   = new SubscriptionService($this->makeFakeRepo(), $spyMailer, $this->makeFixedClock());

        try {
            $service->subscribe('bad-email', 'pro');
        } catch (\InvalidArgumentException) {}

        $this->assertEmpty($spyMailer->sent);
    }

    // ═══════════════════════════════════════════════════════════
    // TDD Round 5: cancel()
    // ═══════════════════════════════════════════════════════════

    public function testCancelRemovesSubscriptionFromRepository(): void
    {
        $fakeRepo = $this->makeFakeRepo();
        $service  = new SubscriptionService($fakeRepo, $this->nullMailer(), $this->makeFixedClock());
        $service->subscribe('alice@example.com', 'pro');

        $service->cancel('alice@example.com');

        $this->assertNull($service->getSubscription('alice@example.com'));
    }

    public function testCancelThrowsDomainExceptionForUnknownEmail(): void
    {
        $service = new SubscriptionService(
            $this->makeFakeRepo(), $this->nullMailer(), $this->makeFixedClock()
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('No subscription found');

        $service->cancel('ghost@example.com');
    }

    // ═══════════════════════════════════════════════════════════
    // TDD Round 6: getSubscription()
    // ═══════════════════════════════════════════════════════════

    public function testGetSubscriptionReturnsNullForUnsubscribedEmail(): void
    {
        $service = new SubscriptionService(
            $this->makeFakeRepo(), $this->nullMailer(), $this->makeFixedClock()
        );

        $this->assertNull($service->getSubscription('nobody@example.com'));
    }

    public function testGetSubscriptionReturnsRecordAfterSubscribe(): void
    {
        $service = new SubscriptionService(
            $this->makeFakeRepo(), $this->nullMailer(), $this->makeFixedClock()
        );
        $service->subscribe('alice@example.com', 'free');

        $sub = $service->getSubscription('alice@example.com');

        $this->assertNotNull($sub);
        $this->assertSame('alice@example.com', $sub['email']);
        $this->assertSame('free', $sub['plan']);
    }

    // ═══════════════════════════════════════════════════════════
    // What TDD with doubles revealed about this design:
    //
    // 1. ClockInterface was not in the original spec.
    //    Writing the test for "start date equals now" FORCED it.
    //    Without TDD, you would have used new \DateTimeImmutable()
    //    inline and the test would be flaky or absent.
    //
    // 2. MailerInterface was discovered by asking "how do I verify
    //    an email was sent?" — the spy double defined the interface.
    //
    // 3. SubscriptionRepositoryInterface's three methods were discovered
    //    one by one as each test needed a new capability.
    //    store() came from the persistence test.
    //    findByEmail() came from the getSubscription() test.
    //    cancel() came from the cancel() test.
    //
    // 4. The test for cancel() immediately revealed it needs to check
    //    if a subscription exists first (guard clause → DomainException).
    //    This was NOT designed upfront — the test revealed it.
    // ═══════════════════════════════════════════════════════════
}