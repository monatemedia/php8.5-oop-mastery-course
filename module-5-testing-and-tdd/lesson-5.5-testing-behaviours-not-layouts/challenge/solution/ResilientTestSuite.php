<?php
declare(strict_types=1);

/**
 * CHALLENGE SOLUTION — Lesson 5.5: Testing Behaviours, Not Layouts
 * ─────────────────────────────────────────────────────────────────
 * ⚠️  Only open this file after completing starter/BrittleTestSuite.php yourself.
 *
 * Key things to compare with your solution:
 *   1. Each brittle test is replaced with one that tests the OUTCOME
 *   2. No ReflectionClass, no ReflectionProperty, no createMock for log assertions
 *   3. Spy patterns replace mock expectations
 *   4. Subscription ID is tested for "non-empty string", not exact format
 *   5. The refactored SubscriptionService below has all 4 internal changes applied
 *      — all resilient tests still pass
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// Contracts (same as starter)
// ─────────────────────────────────────────────────────────────────────────────

interface SubscriptionRepositoryInterface
{
    public function save(string $email, string $plan, string $subscriptionId): void;
    public function remove(string $email): void;
    public function findByEmail(string $email): ?array;
}

interface SubscriptionLoggerInterface
{
    public function info(string $message): void;
    public function warning(string $message): void;
}

// ─────────────────────────────────────────────────────────────────────────────
// SubscriptionService — VERSION 2 (post-refactor)
//
// ALL FOUR internal changes applied:
//   1. Constructor has a 4th parameter: $defaultPlan
//   2. Private property renamed: $subscriptions → $activeSubscriptions
//   3. Log messages reworded
//   4. Subscription ID format changed: SUB-{timestamp} → SUB-{hex}
//
// Observable contract: unchanged
// ─────────────────────────────────────────────────────────────────────────────

class SubscriptionService
{
    // CHANGE 2: renamed from $subscriptions
    private array $activeSubscriptions = [];

    // CHANGE 1: 4th constructor parameter added
    public function __construct(
        private SubscriptionRepositoryInterface $repository,
        private SubscriptionLoggerInterface     $logger,
        private string                          $defaultPlan = 'free'
    ) {}

    /**
     * @throws \InvalidArgumentException for invalid email or unknown plan
     * @return array{id: string, email: string, plan: string}
     */
    public function subscribe(string $email, string $plan): array
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email: {$email}");
        }

        $validPlans = ['free', 'pro', 'enterprise'];
        if (!in_array($plan, $validPlans, true)) {
            throw new \InvalidArgumentException("Unknown plan: {$plan}");
        }

        // CHANGE 4: ID format changed
        $subscriptionId = 'SUB-' . bin2hex(random_bytes(6));

        $this->activeSubscriptions[$email] = ['id' => $subscriptionId, 'email' => $email, 'plan' => $plan];
        $this->repository->save($email, $plan, $subscriptionId);

        // CHANGE 3: log message reworded
        $this->logger->info("New subscriber: {$email}");

        return ['id' => $subscriptionId, 'email' => $email, 'plan' => $plan];
    }

    /**
     * @throws \DomainException when no subscription exists for this email
     */
    public function cancel(string $email): void
    {
        if (!isset($this->activeSubscriptions[$email])) {
            throw new \DomainException("No subscription found for {$email}");
        }

        unset($this->activeSubscriptions[$email]);
        $this->repository->remove($email);

        // CHANGE 3: log message reworded
        $this->logger->info("Subscriber removed: {$email}");
    }

    public function isActive(string $email): bool
    {
        return isset($this->activeSubscriptions[$email]);
    }

    public function getSubscription(string $email): ?array
    {
        return $this->activeSubscriptions[$email] ?? null;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Resilient test suite — all 5 brittle tests replaced with behaviour tests
// ALL pass against VERSION 2 (post-refactor) SubscriptionService
// ─────────────────────────────────────────────────────────────────────────────

class ResilientSubscriptionTests extends TestCase
{
    // ─────────────────────────────────────────────────────────────────────────
    // Shared doubles
    // ─────────────────────────────────────────────────────────────────────────

    private function nullLogger(): SubscriptionLoggerInterface
    {
        return new class implements SubscriptionLoggerInterface {
            public function info(string $m): void {}
            public function warning(string $m): void {}
        };
    }

    private function nullRepo(): SubscriptionRepositoryInterface
    {
        return new class implements SubscriptionRepositoryInterface {
            public function save(string $e, string $p, string $id): void {}
            public function remove(string $e): void {}
            public function findByEmail(string $e): ?array { return null; }
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REPLACEMENT 1 — was: testServiceHasTwoConstructorParameters
    // Anti-pattern: asserting constructor parameter count (AP-1)
    //
    // The brittle test fails when the constructor gains a 4th parameter.
    // No behaviour changed — the test punishes reasonable improvement.
    //
    // Fix: there is no useful behaviour equivalent. The correct verification
    // of "DI works" is to use the service and observe that it calls its
    // dependencies. We test that the service actually USES the repository.
    // ─────────────────────────────────────────────────────────────────────────

    // AP-1 replacement: test that the service uses its dependencies, not that it has N of them
    public function testSubscribeCallsRepositoryToStoreTheSubscription(): void
    {
        $spyRepo = new class implements SubscriptionRepositoryInterface {
            public bool $saveCalled = false;
            public function save(string $email, string $plan, string $id): void {
                $this->saveCalled = true;
            }
            public function remove(string $e): void {}
            public function findByEmail(string $e): ?array { return null; }
        };

        $service = new SubscriptionService($spyRepo, $this->nullLogger());
        $service->subscribe('alice@example.com', 'pro');

        $this->assertTrue($spyRepo->saveCalled,
            'subscribe() should persist the subscription via the repository'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REPLACEMENT 2 — was: testSubscriptionsAreStoredInSubscriptionsProperty
    // Anti-pattern: asserting on private property storage (AP-2)
    //
    // The brittle test fails when $subscriptions is renamed to $activeSubscriptions.
    // No behaviour changed — the private name is an implementation detail.
    //
    // Fix: test the OBSERVABLE behaviour that depends on the storage —
    // isActive() and getSubscription() return the right values.
    // ─────────────────────────────────────────────────────────────────────────

    // AP-2 replacement: test the public query methods that expose stored state
    public function testSubscribeStoresSubscriptionSoIsActiveReturnsTrueAfterwards(): void
    {
        $service = new SubscriptionService($this->nullRepo(), $this->nullLogger());

        $service->subscribe('alice@example.com', 'pro');

        $this->assertTrue($service->isActive('alice@example.com'));
    }

    public function testSubscribeStoresCorrectEmailAndPlanRetrievableViaGetSubscription(): void
    {
        $service = new SubscriptionService($this->nullRepo(), $this->nullLogger());

        $service->subscribe('alice@example.com', 'enterprise');

        $stored = $service->getSubscription('alice@example.com');

        $this->assertNotNull($stored);
        $this->assertSame('alice@example.com', $stored['email']);
        $this->assertSame('enterprise', $stored['plan']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REPLACEMENT 3 — was: testExactLogMessagesAreWrittenOnSubscribe
    // Anti-pattern: asserting on exact log message strings (AP-3)
    //
    // The brittle test fails when log messages are reworded.
    // Log wording is internal. Callers do not observe log messages.
    //
    // Fix: if logging is contractual (e.g. compliance requirement), test
    // only that A log entry was made and contains the key identifier.
    // If it is purely internal, skip the assertion entirely.
    // ─────────────────────────────────────────────────────────────────────────

    // AP-3 replacement: test that subscribe() returns the correct observable values,
    // not what was logged internally
    public function testSubscribeReturnsArrayWithEmailPlanAndNonEmptyId(): void
    {
        $service = new SubscriptionService($this->nullRepo(), $this->nullLogger());

        $result = $service->subscribe('alice@example.com', 'pro');

        $this->assertSame('alice@example.com', $result['email']);
        $this->assertSame('pro',               $result['plan']);
        $this->assertIsString($result['id']);
        $this->assertNotEmpty($result['id']);
    }

    // If logging MUST be verified (e.g. compliance requirement), test only
    // that a log entry exists containing the email — not the exact wording:
    public function testSubscribeWritesAtLeastOneLogEntryContainingEmail(): void
    {
        $spyLogger = new class implements SubscriptionLoggerInterface {
            public array $logged = [];
            public function info(string $message): void    { $this->logged[] = $message; }
            public function warning(string $message): void { $this->logged[] = $message; }
        };

        $service = new SubscriptionService($this->nullRepo(), $spyLogger);
        $service->subscribe('alice@example.com', 'pro');

        // ✅ A log entry exists — not zero
        $this->assertNotEmpty($spyLogger->logged);

        // ✅ At least one entry mentions the email (contractual context)
        $anyEntryMentionsEmail = array_filter(
            $spyLogger->logged,
            fn($entry) => str_contains($entry, 'alice@example.com')
        );
        $this->assertNotEmpty($anyEntryMentionsEmail,
            'A log entry should reference the subscriber email'
        );

        // ❌ NOT asserting: 'Subscription created for alice@example.com'
        //    or: 'New subscriber: alice@example.com'
        //    — the exact wording is an internal detail
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REPLACEMENT 4 — was: testRepositorySaveIsCalledExactlyOnceWithExactArguments
    // Anti-pattern: over-specified mock expectations (AP-3 / AP-4 hybrid)
    //
    // The brittle test fails when the subscription ID format changes
    // (regex /^SUB-\d+$/ no longer matches hex format).
    //
    // Fix: assert that save() is called with the correct email and plan.
    // Do NOT assert on the exact ID format — the ID is an internal detail.
    // Test the return value for "a non-empty string ID" instead.
    // ─────────────────────────────────────────────────────────────────────────

    // AP-4 replacement: spy asserts the repository received the right email and plan
    public function testSubscribeCallsRepositoryWithCorrectEmailAndPlan(): void
    {
        $spyRepo = new class implements SubscriptionRepositoryInterface {
            public ?string $savedEmail = null;
            public ?string $savedPlan  = null;
            public ?string $savedId    = null;
            public function save(string $email, string $plan, string $id): void {
                $this->savedEmail = $email;
                $this->savedPlan  = $plan;
                $this->savedId    = $id;
            }
            public function remove(string $e): void {}
            public function findByEmail(string $e): ?array { return null; }
        };

        $service = new SubscriptionService($spyRepo, $this->nullLogger());
        $service->subscribe('alice@example.com', 'pro');

        $this->assertSame('alice@example.com', $spyRepo->savedEmail);
        $this->assertSame('pro', $spyRepo->savedPlan);

        // ✅ ID is a non-empty string — not asserting format (hex, timestamp, UUID)
        $this->assertIsString($spyRepo->savedId);
        $this->assertNotEmpty($spyRepo->savedId);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REPLACEMENT 5 — was: testSubscriptionIdHasExactTimestampBasedFormat
    // Anti-pattern: asserting on internal return value format (AP-5)
    //
    // The brittle test fails when the ID format changes from
    // SUB-{timestamp} to SUB-{hex}.
    //
    // Fix: assert the CONTRACT — a non-empty, non-null string ID is returned.
    // The prefix 'SUB-' MAY be contractual if documented; the suffix format is not.
    // ─────────────────────────────────────────────────────────────────────────

    // AP-5 replacement: test the contract, not the internal format
    public function testSubscribeReturnsNonEmptyStringId(): void
    {
        $service = new SubscriptionService($this->nullRepo(), $this->nullLogger());

        $result = $service->subscribe('alice@example.com', 'pro');

        // ✅ CONTRACT: the ID is a non-empty string
        $this->assertIsString($result['id']);
        $this->assertNotEmpty($result['id']);
    }

    public function testSubscribeReturnsDifferentIdOnEachCall(): void
    {
        $service = new SubscriptionService($this->nullRepo(), $this->nullLogger());

        $r1 = $service->subscribe('alice@example.com', 'pro');
        $r2 = $service->subscribe('bob@example.com',   'pro');

        // ✅ CONTRACT: IDs are unique (no collision)
        $this->assertNotSame($r1['id'], $r2['id']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Additional behaviour tests (not replacements — genuine test coverage)
    // These were not in the brittle suite at all, which is itself a sign:
    // the brittle suite was testing implementation instead of real scenarios.
    // ─────────────────────────────────────────────────────────────────────────

    public function testSubscribeThrowsForInvalidEmail(): void
    {
        $service = new SubscriptionService($this->nullRepo(), $this->nullLogger());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email');

        $service->subscribe('not-an-email', 'pro');
    }

    public function testSubscribeThrowsForUnknownPlan(): void
    {
        $service = new SubscriptionService($this->nullRepo(), $this->nullLogger());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown plan');

        $service->subscribe('alice@example.com', 'premium');
    }

    public function testCancelMakesSubscriberInactive(): void
    {
        $service = new SubscriptionService($this->nullRepo(), $this->nullLogger());
        $service->subscribe('alice@example.com', 'pro');

        $this->assertTrue($service->isActive('alice@example.com')); // pre-condition

        $service->cancel('alice@example.com');

        $this->assertFalse($service->isActive('alice@example.com'));
    }

    public function testCancelThrowsForNonExistentSubscription(): void
    {
        $service = new SubscriptionService($this->nullRepo(), $this->nullLogger());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('No subscription found');

        $service->cancel('ghost@example.com');
    }

    public function testCancelCallsRepositoryRemove(): void
    {
        $spyRepo = new class implements SubscriptionRepositoryInterface {
            public ?string $removedEmail = null;
            public function save(string $e, string $p, string $id): void {}
            public function remove(string $email): void { $this->removedEmail = $email; }
            public function findByEmail(string $e): ?array { return null; }
        };

        $service = new SubscriptionService($spyRepo, $this->nullLogger());
        $service->subscribe('alice@example.com', 'pro');
        $service->cancel('alice@example.com');

        $this->assertSame('alice@example.com', $spyRepo->removedEmail);
    }
}