<?php
declare(strict_types=1);

/**
 * Example 02 — Refactoring Without Breaking Tests
 * -------------------------------------------------
 * Run via PHPUnit:
 *   ./vendor/bin/phpunit module-5-testing-and-tdd/lesson-5.5-testing-behaviours-not-layouts/examples/02-refactor-without-breaking.php
 *
 * This example shows the FULL lifecycle of a refactor:
 *
 *   VERSION 1 of NotificationService — naïve, works but has code smells
 *   VERSION 2 of NotificationService — refactored: extracted helpers, renamed internals
 *
 * The test suite is written against the VERSION 1 API.
 * After the refactor to VERSION 2, ALL tests still pass.
 * This is the hallmark of a behaviour-testing suite.
 *
 * Refactors applied (all internal — none change the public contract):
 *   A. Private $channels array renamed to $registeredChannels
 *   B. Internal helper method extracted: validateChannel()
 *   C. Constructor gains a 4th optional parameter: int $retryLimit = 3
 *   D. send() now retries on temporary failure (internal behaviour)
 *   E. Log message wording changed
 *
 * The test suite never knew about any of these. Every test passes unchanged.
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// Contracts
// ─────────────────────────────────────────────────────────────────────────────

interface ChannelInterface
{
    public function deliver(string $recipient, string $message): bool;
    public function supports(string $channelName): bool;
}

interface NotificationLogInterface
{
    public function record(string $channel, string $recipient, bool $success): void;
}

// ─────────────────────────────────────────────────────────────────────────────
// VERSION 2 of NotificationService (the post-refactor version)
//
// WHAT CHANGED (all internal):
//   - $channels array renamed to $registeredChannels
//   - private validateChannel() extracted (was inline in send())
//   - $retryLimit added to constructor (optional, defaults to 3)
//   - send() retries on temporary failure (before giving up)
//   - Log messages reworded
//
// WHAT DID NOT CHANGE (the observable contract):
//   - register() still registers a channel
//   - send() still returns true/false
//   - send() still throws for unknown channel names
//   - send() still throws for empty recipient
//   - notificationLog->record() is still called after send()
//   - The contract for what constitutes a "success" is unchanged
// ─────────────────────────────────────────────────────────────────────────────

class NotificationService
{
    // VERSION 2: renamed from $channels → $registeredChannels
    private array $registeredChannels = [];

    // VERSION 2: new optional parameter added — no breaking change
    public function __construct(
        private NotificationLogInterface $log,
        private int                      $retryLimit = 3   // ← new, but optional
    ) {}

    public function register(string $name, ChannelInterface $channel): void
    {
        $this->registeredChannels[$name] = $channel;
    }

    /**
     * @throws \InvalidArgumentException for unknown channel or empty recipient
     */
    public function send(string $channelName, string $recipient, string $message): bool
    {
        // VERSION 2: this was inline, now extracted to validateChannel()
        $this->validateChannel($channelName, $recipient);

        $channel = $this->registeredChannels[$channelName];

        // VERSION 2: retry logic — not visible to callers
        $attempt = 0;
        $success = false;

        while ($attempt < $this->retryLimit && !$success) {
            $success = $channel->deliver($recipient, $message);
            $attempt++;
        }

        // VERSION 2: log message reworded — was "Sent via {$channelName}"
        $this->log->record($channelName, $recipient, $success);

        return $success;
    }

    public function getRegisteredChannelNames(): array
    {
        return array_keys($this->registeredChannels);
    }

    // VERSION 2: extracted private helper
    private function validateChannel(string $channelName, string $recipient): void
    {
        if (!isset($this->registeredChannels[$channelName])) {
            throw new \InvalidArgumentException(
                "Unknown channel: '{$channelName}'"
            );
        }

        if (trim($recipient) === '') {
            throw new \InvalidArgumentException('Recipient cannot be empty');
        }
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Test suite — written against the observable contract
// (These tests were written for VERSION 1 and pass unchanged on VERSION 2)
// ─────────────────────────────────────────────────────────────────────────────

class RefactorWithoutBreakingExampleTest extends TestCase
{
    private NotificationLogInterface $spyLog;
    private NotificationService      $service;

    protected function setUp(): void
    {
        $this->spyLog = new class implements NotificationLogInterface {
            public array $records = [];
            public function record(string $channel, string $recipient, bool $success): void {
                $this->records[] = compact('channel', 'recipient', 'success');
            }
        };

        $this->service = new NotificationService($this->spyLog);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Behaviour: register + retrieve
    // ─────────────────────────────────────────────────────────────────────────

    public function testGetRegisteredChannelNamesReturnsAllRegisteredNames(): void
    {
        $dummyChannel = new class implements ChannelInterface {
            public function deliver(string $r, string $m): bool { return true; }
            public function supports(string $n): bool { return true; }
        };

        $this->service->register('email',  $dummyChannel);
        $this->service->register('sms',    $dummyChannel);
        $this->service->register('push',   $dummyChannel);

        $names = $this->service->getRegisteredChannelNames();

        $this->assertCount(3, $names);
        $this->assertContains('email', $names);
        $this->assertContains('sms',   $names);
        $this->assertContains('push',  $names);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Behaviour: send() return value
    // ─────────────────────────────────────────────────────────────────────────

    public function testSendReturnsTrueWhenChannelDeliversSuccessfully(): void
    {
        $successChannel = new class implements ChannelInterface {
            public function deliver(string $r, string $m): bool { return true; }
            public function supports(string $n): bool { return true; }
        };

        $this->service->register('email', $successChannel);

        $result = $this->service->send('email', 'alice@example.com', 'Hello!');

        $this->assertTrue($result);
    }

    public function testSendReturnsFalseWhenChannelFailsToPermanentlyDeliver(): void
    {
        // Channel that always fails (even after retries)
        $failingChannel = new class implements ChannelInterface {
            public function deliver(string $r, string $m): bool { return false; }
            public function supports(string $n): bool { return true; }
        };

        // Note: test uses default retryLimit=3, which is internal — not asserted on
        $this->service->register('sms', $failingChannel);

        $result = $this->service->send('sms', '+27821234567', 'Your code is 1234');

        $this->assertFalse($result);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Behaviour: exceptions for invalid input
    // ─────────────────────────────────────────────────────────────────────────

    public function testSendThrowsForUnknownChannelName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown channel: 'fax'");

        $this->service->send('fax', 'alice@example.com', 'Hello!');
    }

    public function testSendThrowsForEmptyRecipient(): void
    {
        $dummyChannel = new class implements ChannelInterface {
            public function deliver(string $r, string $m): bool { return true; }
            public function supports(string $n): bool { return true; }
        };

        $this->service->register('email', $dummyChannel);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Recipient cannot be empty');

        $this->service->send('email', '   ', 'Hello!');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Behaviour: side effect — log is called (contract)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * The notification log IS a contract: every delivery attempt must be recorded.
     * This test asserts the side effect (record was called) and the arguments
     * (channel name, recipient, success flag) — all observable from the outside.
     *
     * It does NOT assert: log message wording, how many internal steps happened,
     * or whether a private helper was invoked.
     */
    public function testSendRecordsSuccessfulDeliveryInLog(): void
    {
        $successChannel = new class implements ChannelInterface {
            public function deliver(string $r, string $m): bool { return true; }
            public function supports(string $n): bool { return true; }
        };

        $this->service->register('email', $successChannel);
        $this->service->send('email', 'alice@example.com', 'Hello!');

        $this->assertCount(1, $this->spyLog->records);
        $this->assertSame('email',             $this->spyLog->records[0]['channel']);
        $this->assertSame('alice@example.com', $this->spyLog->records[0]['recipient']);
        $this->assertTrue($this->spyLog->records[0]['success']);
    }

    public function testSendRecordsFailedDeliveryInLog(): void
    {
        $failingChannel = new class implements ChannelInterface {
            public function deliver(string $r, string $m): bool { return false; }
            public function supports(string $n): bool { return true; }
        };

        $this->service->register('push', $failingChannel);
        $this->service->send('push', 'device-token-abc', 'Alert!');

        $this->assertCount(1, $this->spyLog->records);
        $this->assertFalse($this->spyLog->records[0]['success']);
    }

    /**
     * The log is called once per send() invocation, not once per retry.
     * This is observable contract behaviour: the caller records one outcome per
     * delivery attempt, regardless of internal retry count.
     *
     * Note: we assert assertCount(1) — not "once per retry attempt".
     * The internal retry count is NOT visible to us and we do not assert on it.
     */
    public function testLogIsCalledExactlyOncePerSendInvocation(): void
    {
        $successChannel = new class implements ChannelInterface {
            public function deliver(string $r, string $m): bool { return true; }
            public function supports(string $n): bool { return true; }
        };

        $this->service->register('email', $successChannel);
        $this->service->send('email', 'a@b.com', 'msg1');
        $this->service->send('email', 'b@c.com', 'msg2');

        // One log record per send() call — regardless of retries
        $this->assertCount(2, $this->spyLog->records);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Behaviour: multiple channels are independent
    // ─────────────────────────────────────────────────────────────────────────

    public function testSendingViaOneChannelDoesNotAffectOthers(): void
    {
        $successChannel = new class implements ChannelInterface {
            public function deliver(string $r, string $m): bool { return true; }
            public function supports(string $n): bool { return true; }
        };

        $this->service->register('email', $successChannel);
        $this->service->register('sms',   $successChannel);

        // Send only via email
        $this->service->send('email', 'alice@example.com', 'Hi');

        // SMS channel was registered but not used — log has only one entry
        $this->assertCount(1, $this->spyLog->records);
        $this->assertSame('email', $this->spyLog->records[0]['channel']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VERSION 2 NEW BEHAVIOUR: retry logic produces success after initial fail
    //
    // This test is NEW — it tests the retry behaviour that VERSION 2 added.
    // It tests the OUTCOME (eventual success) not the mechanics (how many retries).
    // ─────────────────────────────────────────────────────────────────────────

    public function testSendReturnsTrueWhenChannelSucceedsOnSecondAttempt(): void
    {
        // Channel that fails once, then succeeds
        $flakeyChannel = new class implements ChannelInterface {
            private int $attempts = 0;
            public function deliver(string $r, string $m): bool {
                return ++$this->attempts >= 2; // fails on 1st, succeeds on 2nd
            }
            public function supports(string $n): bool { return true; }
        };

        $this->service->register('sms', $flakeyChannel);

        // We assert the OUTCOME: eventual success
        // We do NOT assert "it tried exactly 2 times" — that is internal
        $result = $this->service->send('sms', '+27821234567', 'Code: 4242');

        $this->assertTrue($result);
    }
}