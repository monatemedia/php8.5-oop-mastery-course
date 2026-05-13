<?php
declare(strict_types=1);

/**
 * Example 01 — Abstract Class vs Interface
 * ------------------------------------------
 * The most important decision in Module 1. This example builds the SAME
 * notification system twice: once as an interface, once as an abstract class.
 * Then it shows the COMBINATION — which is what real applications use.
 *
 * Run this and ask yourself after each section:
 *   "What would I have to repeat if I used the other approach?"
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Abstract Class vs Interface — Side-by-Side         ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ═══════════════════════════════════════════════════════════
// APPROACH 1 — Interface only
// Good for: defining a capability contract. No shared code.
// ═══════════════════════════════════════════════════════════
echo "── Approach 1: Interface only ───────────────────────\n\n";

interface NotifierInterface {
    public function send(string $recipient, string $message): bool;
    public function getChannel(): string;
}

class EmailNotifierV1 implements NotifierInterface {
    public function __construct(private string $apiKey) {}

    public function send(string $recipient, string $message): bool {
        // Every implementor must write their own delivery logic AND their own logging
        $channel = $this->getChannel();
        echo "[{$channel}] Sending to {$recipient}: {$message}\n";
        echo "[{$channel}] API key used: " . substr($this->apiKey, 0, 4) . "****\n";
        return true;
    }

    public function getChannel(): string { return 'EMAIL'; }
}

class SmsNotifierV1 implements NotifierInterface {
    public function __construct(private string $apiKey) {}

    public function send(string $recipient, string $message): bool {
        // ❗ Identical boilerplate — channel log, API key log — repeated here
        $channel = $this->getChannel();
        echo "[{$channel}] Sending to {$recipient}: {$message}\n";
        echo "[{$channel}] API key used: " . substr($this->apiKey, 0, 4) . "****\n";
        return true;
    }

    public function getChannel(): string { return 'SMS'; }
}

(new EmailNotifierV1('email_key_abc'))->send('alice@example.com', 'Hello Alice');
(new SmsNotifierV1('sms_key_xyz'))->send('+27821234567', 'Hello Bob');
echo "\n↑ Both classes repeat the same logging boilerplate. Any change requires editing both.\n";


// ═══════════════════════════════════════════════════════════
// APPROACH 2 — Abstract class only
// Good for: sharing implementation AND enforcing a contract.
// ═══════════════════════════════════════════════════════════
echo "\n── Approach 2: Abstract class only ─────────────────\n\n";

abstract class NotifierBase {
    // Shared state — stored once, available to all subclasses
    public function __construct(protected string $apiKey) {}

    // Abstract — each channel delivers differently
    abstract protected function deliver(string $recipient, string $message): bool;

    // Abstract — each channel has a name
    abstract public function getChannel(): string;

    // Concrete — shared across ALL subclasses. Change here = change everywhere.
    final public function send(string $recipient, string $message): bool {
        $channel = $this->getChannel();
        echo "[{$channel}] API key used: " . substr($this->apiKey, 0, 4) . "****\n";
        $result = $this->deliver($recipient, $message); // Calls subclass implementation
        $status = $result ? 'OK' : 'FAILED';
        echo "[{$channel}] Delivery to {$recipient}: {$status}\n";
        return $result;
    }
}

class EmailNotifierV2 extends NotifierBase {
    // Only the unique part — no boilerplate
    protected function deliver(string $recipient, string $message): bool {
        echo "[EMAIL] Dispatching via SMTP: {$message}\n";
        return true;
    }

    public function getChannel(): string { return 'EMAIL'; }
}

class SmsNotifierV2 extends NotifierBase {
    protected function deliver(string $recipient, string $message): bool {
        echo "[SMS] Dispatching via gateway: {$message}\n";
        return true;
    }

    public function getChannel(): string { return 'SMS'; }
}

(new EmailNotifierV2('email_key_abc'))->send('alice@example.com', 'Hello Alice');
(new SmsNotifierV2('sms_key_xyz'))->send('+27821234567', 'Hello Bob');
echo "\n↑ Shared logging is written once. Subclasses only implement what is unique to them.\n";


// ═══════════════════════════════════════════════════════════
// APPROACH 3 — Abstract class + Interface (real-world pattern)
// Some classes need to be Notifiers AND Schedulable.
// Abstract class handles the shared implementation.
// Interface handles the additional "can-do" capability.
// ═══════════════════════════════════════════════════════════
echo "\n── Approach 3: Abstract class + Interface (real-world) ──\n\n";

interface Schedulable {
    public function scheduleFor(\DateTimeImmutable $at): void;
    public function cancel(): void;
}

// EmailNotifier is a NotifierBase (shared send logic)
// AND it is Schedulable (a capability it opts into)
class EmailNotifierV3 extends NotifierBase implements Schedulable {
    private ?\DateTimeImmutable $scheduledAt = null;

    protected function deliver(string $recipient, string $message): bool {
        $when = $this->scheduledAt
            ? " (scheduled for " . $this->scheduledAt->format('Y-m-d H:i') . ")"
            : "";
        echo "[EMAIL] Dispatching: {$message}{$when}\n";
        return true;
    }

    public function getChannel(): string { return 'EMAIL'; }

    public function scheduleFor(\DateTimeImmutable $at): void {
        $this->scheduledAt = $at;
        echo "[EMAIL] Scheduled for " . $at->format('Y-m-d H:i') . "\n";
    }

    public function cancel(): void {
        $this->scheduledAt = null;
        echo "[EMAIL] Schedule cancelled.\n";
    }
}

// SmsNotifier is a NotifierBase but NOT Schedulable — SMS does not need it
class SmsNotifierV3 extends NotifierBase {
    protected function deliver(string $recipient, string $message): bool {
        echo "[SMS] Dispatching: {$message}\n";
        return true;
    }

    public function getChannel(): string { return 'SMS'; }
}

$email = new EmailNotifierV3('email_key_abc');
$email->scheduleFor(new \DateTimeImmutable('+1 hour'));
$email->send('alice@example.com', 'Hello Alice');

$sms = new SmsNotifierV3('sms_key_xyz');
$sms->send('+27821234567', 'Hello Bob');
// $sms->scheduleFor(...); // PHP error — SmsNotifierV3 is not Schedulable

echo "\n── Decision summary ─────────────────────────────────\n\n";
echo "Interface alone:      Use when you have NO shared implementation to offer.\n";
echo "Abstract class alone: Use when all implementors share a meaningful 'is-a' identity.\n";
echo "Both together:        The real-world default — abstract class for shared code,\n";
echo "                      interfaces for additional opt-in capabilities.\n";