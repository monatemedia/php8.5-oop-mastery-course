<?php
declare(strict_types=1);

/**
 * CHALLENGE STARTER — Lesson 5.5: Testing Behaviours, Not Layouts
 * ─────────────────────────────────────────────────────────────────
 * Read CHALLENGE.md before touching this file.
 *
 * This file contains:
 *   1. SubscriptionService — the class under test
 *   2. BrittleSubscriptionTests — 5 tests, each with an anti-pattern
 *   3. TODO markers — where you write your rewritten behaviour tests
 *
 * Instructions:
 *   A. Run the brittle tests first — they all pass.
 *   B. Read each brittle test and name the anti-pattern in the TODO comment.
 *   C. Write a replacement behaviour test below each brittle one.
 *   D. Apply the refactor described at the bottom.
 *   E. Run your rewritten tests — they should still pass.
 *      The brittle tests will now fail — that is the point.
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// Contracts
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
// The class under test — SubscriptionService (VERSION 1)
// ─────────────────────────────────────────────────────────────────────────────

class SubscriptionService
{
    // AP-2 target: this property will be renamed in the refactor
    private array $subscriptions = [];

    public function __construct(
        private SubscriptionRepositoryInterface $repository,
        private SubscriptionLoggerInterface     $logger
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

        // AP-5 target: this ID format will change in the refactor
        $subscriptionId = 'SUB-' . time();

        $this->subscriptions[$email] = ['id' => $subscriptionId, 'email' => $email, 'plan' => $plan];
        $this->repository->save($email, $plan, $subscriptionId);

        // AP-3 target: these messages will be reworded in the refactor
        $this->logger->info("Subscription created for {$email}");

        return ['id' => $subscriptionId, 'email' => $email, 'plan' => $plan];
    }

    /**
     * @throws \DomainException when no subscription exists for this email
     */
    public function cancel(string $email): void
    {
        if (!isset($this->subscriptions[$email])) {
            throw new \DomainException("No subscription found for {$email}");
        }

        unset($this->subscriptions[$email]);
        $this->repository->remove($email);

        // AP-3 target: this message will be reworded in the refactor
        $this->logger->info("Subscription cancelled for {$email}");
    }

    public function isActive(string $email): bool
    {
        return isset($this->subscriptions[$email]);
    }

    public function getSubscription(string $email): ?array
    {
        return $this->subscriptions[$email] ?? null;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// BRITTLE tests — read carefully, DO NOT delete, but DO write replacements below
// ─────────────────────────────────────────────────────────────────────────────

class BrittleSubscriptionTests extends TestCase
{
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
    // BRITTLE TEST 1 — Anti-pattern: ???
    // ─────────────────────────────────────────────────────────────────────────

    public function testServiceHasTwoConstructorParameters(): void
    {
        $reflection = new \ReflectionClass(SubscriptionService::class);
        $params     = $reflection->getConstructor()->getParameters();

        $this->assertCount(2, $params);
    }

    // TODO: Name the anti-pattern above, then write a resilient replacement:
    // Anti-pattern: ___________________________________________
    //
    // public function test___(): void
    // {
    //     ...
    // }


    // ─────────────────────────────────────────────────────────────────────────
    // BRITTLE TEST 2 — Anti-pattern: ???
    // ─────────────────────────────────────────────────────────────────────────

    public function testSubscriptionsAreStoredInSubscriptionsProperty(): void
    {
        $service = new SubscriptionService($this->nullRepo(), $this->nullLogger());
        $service->subscribe('alice@example.com', 'pro');

        $prop = new \ReflectionProperty(SubscriptionService::class, 'subscriptions');
        $prop->setAccessible(true);
        $value = $prop->getValue($service);

        $this->assertArrayHasKey('alice@example.com', $value);
    }

    // TODO: Name the anti-pattern above, then write a resilient replacement:
    // Anti-pattern: ___________________________________________
    //
    // public function test___(): void
    // {
    //     ...
    // }


    // ─────────────────────────────────────────────────────────────────────────
    // BRITTLE TEST 3 — Anti-pattern: ???
    // ─────────────────────────────────────────────────────────────────────────

    public function testExactLogMessagesAreWrittenOnSubscribe(): void
    {
        $mockLogger = $this->createMock(SubscriptionLoggerInterface::class);

        $mockLogger->expects($this->exactly(1))
            ->method('info')
            ->with('Subscription created for alice@example.com');

        $service = new SubscriptionService($this->nullRepo(), $mockLogger);
        $service->subscribe('alice@example.com', 'pro');
    }

    // TODO: Name the anti-pattern above, then write a resilient replacement:
    // Anti-pattern: ___________________________________________
    //
    // public function test___(): void
    // {
    //     ...
    // }


    // ─────────────────────────────────────────────────────────────────────────
    // BRITTLE TEST 4 — Anti-pattern: ???
    // ─────────────────────────────────────────────────────────────────────────

    public function testRepositorySaveIsCalledExactlyOnceWithExactArguments(): void
    {
        $mockRepo = $this->createMock(SubscriptionRepositoryInterface::class);

        $mockRepo->expects($this->once())
            ->method('save')
            ->with(
                'alice@example.com',
                'pro',
                $this->matchesRegularExpression('/^SUB-\d+$/')
            );

        $mockRepo->expects($this->never())->method('remove');

        $service = new SubscriptionService($mockRepo, $this->nullLogger());
        $service->subscribe('alice@example.com', 'pro');
    }

    // TODO: Name the anti-pattern above, then write a resilient replacement:
    // Anti-pattern: ___________________________________________
    //
    // public function test___(): void
    // {
    //     ...
    // }


    // ─────────────────────────────────────────────────────────────────────────
    // BRITTLE TEST 5 — Anti-pattern: ???
    // ─────────────────────────────────────────────────────────────────────────

    public function testSubscriptionIdHasExactTimestampBasedFormat(): void
    {
        $service = new SubscriptionService($this->nullRepo(), $this->nullLogger());

        $result = $service->subscribe('alice@example.com', 'pro');

        // Asserts the exact internal format: 'SUB-' followed by a Unix timestamp
        $this->assertMatchesRegularExpression('/^SUB-\d{10}$/', $result['id']);
    }

    // TODO: Name the anti-pattern above, then write a resilient replacement:
    // Anti-pattern: ___________________________________________
    //
    // public function test___(): void
    // {
    //     ...
    // }
}


// ─────────────────────────────────────────────────────────────────────────────
// ── APPLY THIS REFACTOR after rewriting all 5 tests ──────────────────────────
//
// Make these changes to SubscriptionService (internal changes only):
//
//   1. Add 4th constructor parameter:
//      private string $defaultPlan = 'free'
//      (now constructor has 3 params — BRITTLE TEST 1 breaks)
//
//   2. Rename private property $subscriptions → $activeSubscriptions
//      (BRITTLE TEST 2 breaks — wrong property name)
//
//   3. Change log messages:
//      'Subscription created for {$email}'  → 'New subscriber: {$email}'
//      'Subscription cancelled for {$email}' → 'Subscriber removed: {$email}'
//      (BRITTLE TEST 3 breaks — wrong exact message)
//
//   4. Change subscription ID format from:
//      'SUB-' . time()
//      to:
//      'SUB-' . bin2hex(random_bytes(6))
//      (BRITTLE TEST 4 breaks — regex no longer matches \d{10})
//      (BRITTLE TEST 5 breaks — regex no longer matches \d{10})
//
// After the refactor:
//   BrittleSubscriptionTests: 4–5 tests fail ← expected
//   Your rewritten tests: 0 tests fail       ← your goal
// ─────────────────────────────────────────────────────────────────────────────