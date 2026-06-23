<?php
declare(strict_types=1);

/**
 * Example 02 — The Spy Pattern
 * ------------------------------
 * Run via PHPUnit:
 *   ./vendor/bin/phpunit module-5-testing-and-tdd/lesson-5.2-unit-testing-with-fakes-and-stubs/examples/02-spy-pattern.php
 *
 * A stub controls what the dependency RETURNS.
 * A spy records what the class under test DOES TO the dependency.
 *
 * Use a spy when the behaviour you want to test is a side effect:
 *   - Was an email sent?
 *   - Was the right log message written?
 *   - Was the event dispatched with the correct payload?
 *   - How many times was the dependency called?
 *
 * This example covers:
 *   A. The basic spy — recording every call
 *   B. Asserting call count
 *   C. Asserting call arguments
 *   D. Asserting call ORDER
 *   E. Combining a spy with a stub in the same double
 *   F. When NOT to use a spy
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// Contracts
// ─────────────────────────────────────────────────────────────────────────────

interface EventDispatcherInterface
{
    public function dispatch(string $event, array $payload = []): void;
}

interface AuditLogInterface
{
    public function write(string $level, string $action, array $context = []): void;
}

interface NotificationServiceInterface
{
    public function notify(string $channel, string $recipient, string $message): bool;
}

// ─────────────────────────────────────────────────────────────────────────────
// The class under test
// UserRegistrationService coordinates registration:
//   1. Dispatches a "user.registered" event
//   2. Sends a welcome notification
//   3. Writes an audit log entry
// All three are side effects — perfect candidates for spy assertions.
// ─────────────────────────────────────────────────────────────────────────────

class UserRegistrationService
{
    public function __construct(
        private EventDispatcherInterface    $events,
        private NotificationServiceInterface $notifications,
        private AuditLogInterface           $audit
    ) {}

    /**
     * @throws \InvalidArgumentException for invalid email or short username
     */
    public function register(string $username, string $email): array
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email: {$email}");
        }

        if (strlen($username) < 3) {
            throw new \InvalidArgumentException("Username must be at least 3 characters");
        }

        $userId = random_int(1000, 9999); // simulate ID assignment

        // Side effect 1 — dispatch event
        $this->events->dispatch('user.registered', [
            'user_id'  => $userId,
            'username' => $username,
            'email'    => $email,
        ]);

        // Side effect 2 — send welcome notification
        $this->notifications->notify(
            channel:   'email',
            recipient: $email,
            message:   "Welcome to the platform, {$username}!"
        );

        // Side effect 3 — write audit entry
        $this->audit->write(
            level:   'info',
            action:  'user.registered',
            context: ['username' => $username, 'email' => $email]
        );

        return ['success' => true, 'user_id' => $userId, 'username' => $username];
    }

    public function registerBatch(array $users): array
    {
        $results = [];
        foreach ($users as $user) {
            $results[] = $this->register($user['username'], $user['email']);
        }
        return $results;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// The test class
// ─────────────────────────────────────────────────────────────────────────────

class SpyPatternExampleTest extends TestCase
{
    // ═══════════════════════════════════════════════════════════
    // PART A — The basic spy: recording every call
    // ═══════════════════════════════════════════════════════════

    /**
     * The spy stores every call in a public array.
     * After running the class under test, we read that array and assert on it.
     *
     * The public property is not a mistake — it is the API of the spy.
     * The test needs to read the recorded calls.
     */
    public function testRegisterDispatchesUserRegisteredEvent(): void
    {
        // ── Arrange: create the spy ───────────────────────────────────────────
        $spyEvents = new class implements EventDispatcherInterface {
            public array $dispatched = [];   // ← the recording

            public function dispatch(string $event, array $payload = []): void {
                $this->dispatched[] = ['event' => $event, 'payload' => $payload];
            }
        };

        $nullNotifications = new class implements NotificationServiceInterface {
            public function notify(string $channel, string $recipient, string $message): bool { return true; }
        };

        $nullAudit = new class implements AuditLogInterface {
            public function write(string $level, string $action, array $context = []): void {}
        };

        $service = new UserRegistrationService($spyEvents, $nullNotifications, $nullAudit);

        // ── Act ───────────────────────────────────────────────────────────────
        $service->register('alice', 'alice@example.com');

        // ── Assert: read the spy's recording ─────────────────────────────────
        $this->assertCount(1, $spyEvents->dispatched);
        $this->assertSame('user.registered', $spyEvents->dispatched[0]['event']);
    }

    // ═══════════════════════════════════════════════════════════
    // PART B — Asserting call count
    // ═══════════════════════════════════════════════════════════

    public function testRegisterSendsExactlyOneNotification(): void
    {
        $nullEvents = new class implements EventDispatcherInterface {
            public function dispatch(string $event, array $payload = []): void {}
        };

        $spyNotifications = new class implements NotificationServiceInterface {
            public array $sent = [];

            public function notify(string $channel, string $recipient, string $message): bool {
                $this->sent[] = compact('channel', 'recipient', 'message');
                return true;
            }
        };

        $nullAudit = new class implements AuditLogInterface {
            public function write(string $level, string $action, array $context = []): void {}
        };

        $service = new UserRegistrationService($nullEvents, $spyNotifications, $nullAudit);

        $service->register('alice', 'alice@example.com');

        // Exactly one notification was sent
        $this->assertCount(1, $spyNotifications->sent);
    }

    public function testRegisterBatchSendsOneNotificationPerUser(): void
    {
        $nullEvents = new class implements EventDispatcherInterface {
            public function dispatch(string $event, array $payload = []): void {}
        };

        $spyNotifications = new class implements NotificationServiceInterface {
            public array $sent = [];
            public function notify(string $channel, string $recipient, string $message): bool {
                $this->sent[] = compact('channel', 'recipient', 'message');
                return true;
            }
        };

        $nullAudit = new class implements AuditLogInterface {
            public function write(string $level, string $action, array $context = []): void {}
        };

        $service = new UserRegistrationService($nullEvents, $spyNotifications, $nullAudit);

        $service->registerBatch([
            ['username' => 'alice', 'email' => 'alice@example.com'],
            ['username' => 'bob',   'email' => 'bob@example.com'],
            ['username' => 'carol', 'email' => 'carol@example.com'],
        ]);

        $this->assertCount(3, $spyNotifications->sent);
    }

    // ═══════════════════════════════════════════════════════════
    // PART C — Asserting call arguments
    // ═══════════════════════════════════════════════════════════

    /**
     * It is not enough to know a call was made — we need to verify
     * the arguments were correct. The spy captures them.
     */
    public function testNotificationIsSentToCorrectRecipient(): void
    {
        $nullEvents = new class implements EventDispatcherInterface {
            public function dispatch(string $event, array $payload = []): void {}
        };

        $spyNotifications = new class implements NotificationServiceInterface {
            public array $sent = [];
            public function notify(string $channel, string $recipient, string $message): bool {
                $this->sent[] = compact('channel', 'recipient', 'message');
                return true;
            }
        };

        $nullAudit = new class implements AuditLogInterface {
            public function write(string $level, string $action, array $context = []): void {}
        };

        $service = new UserRegistrationService($nullEvents, $spyNotifications, $nullAudit);

        $service->register('alice', 'alice@example.com');

        $notification = $spyNotifications->sent[0];

        // Verify every argument
        $this->assertSame('email',             $notification['channel']);
        $this->assertSame('alice@example.com', $notification['recipient']);
        $this->assertStringContainsString('alice', $notification['message']);
        $this->assertStringContainsString('Welcome', $notification['message']);
    }

    public function testEventPayloadContainsUsernameAndEmail(): void
    {
        $spyEvents = new class implements EventDispatcherInterface {
            public array $dispatched = [];
            public function dispatch(string $event, array $payload = []): void {
                $this->dispatched[] = ['event' => $event, 'payload' => $payload];
            }
        };

        $nullNotifications = new class implements NotificationServiceInterface {
            public function notify(string $channel, string $recipient, string $message): bool { return true; }
        };

        $nullAudit = new class implements AuditLogInterface {
            public function write(string $level, string $action, array $context = []): void {}
        };

        $service = new UserRegistrationService($spyEvents, $nullNotifications, $nullAudit);

        $service->register('bob', 'bob@example.com');

        $payload = $spyEvents->dispatched[0]['payload'];

        $this->assertSame('bob',             $payload['username']);
        $this->assertSame('bob@example.com', $payload['email']);
        $this->assertArrayHasKey('user_id',  $payload);
        $this->assertIsInt($payload['user_id']);
    }

    // ═══════════════════════════════════════════════════════════
    // PART D — Asserting call ORDER
    // ═══════════════════════════════════════════════════════════

    /**
     * Sometimes the ORDER of side effects matters.
     * A combined spy on all three dependencies lets us verify sequence.
     */
    public function testRegistrationSideEffectsHappenInCorrectOrder(): void
    {
        $callLog = []; // shared reference captured by all three anonymous classes

        $spyEvents = new class($callLog) implements EventDispatcherInterface {
            public function __construct(private array &$log) {}
            public function dispatch(string $event, array $payload = []): void {
                $this->log[] = 'event:' . $event;
            }
        };

        $spyNotifications = new class($callLog) implements NotificationServiceInterface {
            public function __construct(private array &$log) {}
            public function notify(string $channel, string $recipient, string $message): bool {
                $this->log[] = 'notification:' . $channel;
                return true;
            }
        };

        $spyAudit = new class($callLog) implements AuditLogInterface {
            public function __construct(private array &$log) {}
            public function write(string $level, string $action, array $context = []): void {
                $this->log[] = 'audit:' . $action;
            }
        };

        $service = new UserRegistrationService($spyEvents, $spyNotifications, $spyAudit);
        $service->register('alice', 'alice@example.com');

        // Verify the call sequence: event → notification → audit
        $this->assertSame('event:user.registered',   $callLog[0]);
        $this->assertSame('notification:email',       $callLog[1]);
        $this->assertSame('audit:user.registered',    $callLog[2]);
    }

    // ═══════════════════════════════════════════════════════════
    // PART E — Combining spy + stub in the same double
    // ═══════════════════════════════════════════════════════════

    /**
     * A single anonymous class can spy on calls AND return a specific value.
     * Here the notification spy also returns a controlled boolean — both
     * recording the call (spy behaviour) and controlling the return (stub behaviour).
     */
    public function testRegisterSucceedsWhenNotificationReturnsFalse(): void
    {
        // Spy-stub hybrid: records the call AND returns false (notification "failed")
        $spyStubNotifications = new class implements NotificationServiceInterface {
            public array $sent  = [];
            public bool  $returnValue = false; // configurable

            public function notify(string $channel, string $recipient, string $message): bool {
                $this->sent[] = compact('channel', 'recipient', 'message');
                return $this->returnValue; // ← stub behaviour
            }
        };

        $nullEvents = new class implements EventDispatcherInterface {
            public function dispatch(string $event, array $payload = []): void {}
        };

        $nullAudit = new class implements AuditLogInterface {
            public function write(string $level, string $action, array $context = []): void {}
        };

        $service = new UserRegistrationService($nullEvents, $spyStubNotifications, $nullAudit);

        // Even if the notification "fails", registration itself succeeds
        $result = $service->register('alice', 'alice@example.com');

        $this->assertTrue($result['success']);
        $this->assertCount(1, $spyStubNotifications->sent); // call was still made
    }

    // ═══════════════════════════════════════════════════════════
    // PART F — Spy verifies NO call was made
    // ═══════════════════════════════════════════════════════════

    /**
     * Spies are also useful to assert that a dependency was NOT called.
     * If registration fails validation, no notifications should be sent.
     */
    public function testNoNotificationSentWhenValidationFails(): void
    {
        $nullEvents = new class implements EventDispatcherInterface {
            public function dispatch(string $event, array $payload = []): void {}
        };

        $spyNotifications = new class implements NotificationServiceInterface {
            public array $sent = [];
            public function notify(string $channel, string $recipient, string $message): bool {
                $this->sent[] = compact('channel', 'recipient', 'message');
                return true;
            }
        };

        $nullAudit = new class implements AuditLogInterface {
            public function write(string $level, string $action, array $context = []): void {}
        };

        $service = new UserRegistrationService($nullEvents, $spyNotifications, $nullAudit);

        // Invalid email — should throw before any side effects run
        try {
            $service->register('alice', 'not-a-valid-email');
        } catch (\InvalidArgumentException) {
            // Expected
        }

        // The spy confirms no notification was sent
        $this->assertCount(0, $spyNotifications->sent);
        $this->assertEmpty($spyNotifications->sent);
    }

    public function testNoEventDispatchedWhenUsernameTooShort(): void
    {
        $spyEvents = new class implements EventDispatcherInterface {
            public array $dispatched = [];
            public function dispatch(string $event, array $payload = []): void {
                $this->dispatched[] = $event;
            }
        };

        $nullNotifications = new class implements NotificationServiceInterface {
            public function notify(string $channel, string $recipient, string $message): bool { return true; }
        };

        $nullAudit = new class implements AuditLogInterface {
            public function write(string $level, string $action, array $context = []): void {}
        };

        $service = new UserRegistrationService($spyEvents, $nullNotifications, $nullAudit);

        try {
            $service->register('ab', 'alice@example.com'); // username < 3 chars
        } catch (\InvalidArgumentException) {
            // Expected
        }

        $this->assertEmpty($spyEvents->dispatched);
    }
}