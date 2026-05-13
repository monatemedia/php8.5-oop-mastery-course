<?php
declare(strict_types=1);

/**
 * Example 05 — Choosing the Right Tool
 * ---------------------------------------
 * Trait vs Interface vs Abstract Class — side-by-side on the same problem.
 *
 * This example builds a notification system three times, then combines all
 * three tools in the way a real application would actually use them.
 *
 * The goal is to make the decision table in the README feel obvious
 * by the time you finish reading this file.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Choosing the Right Tool                            ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ═══════════════════════════════════════════════════════════
// APPROACH 1 — Interface only
// ✓ Type contract  ✗ No shared code  ✗ Every class must repeat the same logging
// ═══════════════════════════════════════════════════════════
echo "── Approach 1: Interface Only ────────────────────────\n\n";

interface NotifiableV1 {
    public function send(string $to, string $message): bool;
    public function getChannelName(): string;
}

class EmailV1 implements NotifiableV1 {
    public function send(string $to, string $message): bool {
        // ❗ Each class must write its own delivery + logging boilerplate
        echo "[EMAIL] Sending to {$to}: {$message}\n";
        echo "[EMAIL] Logged delivery attempt.\n"; // ← repeated in every class
        return true;
    }
    public function getChannelName(): string { return 'email'; }
}

class SmsV1 implements NotifiableV1 {
    public function send(string $to, string $message): bool {
        echo "[SMS] Sending to {$to}: {$message}\n";
        echo "[SMS] Logged delivery attempt.\n"; // ← same repeated boilerplate
        return true;
    }
    public function getChannelName(): string { return 'sms'; }
}

function dispatchV1(NotifiableV1 $notifier, string $to, string $msg): void {
    $notifier->send($to, $msg); // ✅ Type-safe
}

dispatchV1(new EmailV1(), 'alice@example.com', 'Hello');
dispatchV1(new SmsV1(),   '+27821234567',       'Hello');
echo "\n✓ Type-safe  ✗ Logging boilerplate repeated in every class\n";


// ═══════════════════════════════════════════════════════════
// APPROACH 2 — Abstract class only
// ✓ Shared implementation  ✓ Type contract  ✗ Single hierarchy only
// ═══════════════════════════════════════════════════════════
echo "\n── Approach 2: Abstract Class Only ──────────────────\n\n";

abstract class NotifiableV2 {
    // Shared: logging lives once here
    final public function send(string $to, string $message): bool {
        $channel = strtoupper($this->getChannelName());
        echo "[{$channel}] Sending to {$to}: {$message}\n";
        $result = $this->deliver($to, $message); // Subclass fills this in
        echo "[{$channel}] Logged: " . ($result ? 'SUCCESS' : 'FAIL') . "\n";
        return $result;
    }

    abstract protected function deliver(string $to, string $message): bool;
    abstract public function getChannelName(): string;
}

class EmailV2 extends NotifiableV2 {
    protected function deliver(string $to, string $message): bool {
        echo "  → SMTP dispatch\n";
        return true;
    }
    public function getChannelName(): string { return 'email'; }
}

class SmsV2 extends NotifiableV2 {
    protected function deliver(string $to, string $message): bool {
        echo "  → SMS gateway dispatch\n";
        return true;
    }
    public function getChannelName(): string { return 'sms'; }
}

function dispatchV2(NotifiableV2 $notifier, string $to, string $msg): void {
    $notifier->send($to, $msg); // ✅ Type-safe
}

dispatchV2(new EmailV2(), 'alice@example.com', 'Hello');
dispatchV2(new SmsV2(),   '+27821234567',       'Hello');
echo "\n✓ Type-safe  ✓ Shared logging  ✗ Works only in one inheritance chain\n";

// Problem: what if I have a completely different class (e.g. a Queue job)
// that also needs to send notifications but cannot extend NotifiableV2
// because it already extends a JobBase abstract class?


// ═══════════════════════════════════════════════════════════
// APPROACH 3 — Trait only
// ✓ Cross-hierarchy reuse  ✓ Shared code  ✗ NOT a type — no type-hint
// ═══════════════════════════════════════════════════════════
echo "\n── Approach 3: Trait Only ────────────────────────────\n\n";

trait NotifiableTrait {
    public function send(string $to, string $message): bool {
        $channel = strtoupper($this->getChannelName());
        echo "[{$channel}] Sending to {$to}: {$message}\n";
        echo "[{$channel}] Logged: SUCCESS\n";
        return true;
    }
    // Abstract — host class must tell us the channel name
    abstract public function getChannelName(): string;
}

abstract class JobBase {
    abstract public function handle(): void;
}

// EmailJob extends JobBase AND uses the trait — cross-hierarchy reuse ✅
class EmailJob extends JobBase {
    use NotifiableTrait;

    public function handle(): void {
        $this->send('alice@example.com', 'Job notification');
    }

    public function getChannelName(): string { return 'email'; }
}

$job = new EmailJob();
$job->handle();

// But we cannot type-hint against the trait:
function dispatchV3(/* NotifiableTrait */ object $notifier, string $to, string $msg): void {
    // ❌ Cannot type-hint with a trait name — must use `object` or a duck-type check
    if (method_exists($notifier, 'send')) {
        $notifier->send($to, $msg);
    }
}

dispatchV3($job, 'bob@example.com', 'Direct dispatch');
echo "\n✓ Cross-hierarchy  ✓ Shared code  ✗ Not type-safe — no type contract\n";


// ═══════════════════════════════════════════════════════════
// APPROACH 4 — The correct combination
// Interface (contract) + Trait (implementation) + Abstract base (shared hierarchy)
// ═══════════════════════════════════════════════════════════
echo "\n── Approach 4: The Real-World Combination ────────────\n\n";

// 1. Interface — type contract for all notification senders
interface Notifiable {
    public function send(string $to, string $message): bool;
    public function getChannelName(): string;
}

// 2. Trait — default logging implementation shared across ALL classes
trait NotificationLogging {
    public function send(string $to, string $message): bool {
        $channel = strtoupper($this->getChannelName());
        echo "[{$channel}] Sending to {$to}: {$message}\n";
        $result = $this->deliver($to, $message);
        echo "[{$channel}] Logged: " . ($result ? 'SUCCESS' : 'FAIL') . "\n";
        return $result;
    }
    abstract protected function deliver(string $to, string $message): bool;
}

// 3a. Abstract class — for the core notification hierarchy
abstract class CoreNotifier implements Notifiable {
    use NotificationLogging; // Free implementation of send() via the trait
    // Abstract: concrete classes still fill in deliver() and getChannelName()
}

class EmailNotifier extends CoreNotifier {
    protected function deliver(string $to, string $message): bool {
        echo "  → SMTP\n"; return true;
    }
    public function getChannelName(): string { return 'email'; }
}

class SlackNotifier extends CoreNotifier {
    protected function deliver(string $to, string $message): bool {
        echo "  → Slack API\n"; return true;
    }
    public function getChannelName(): string { return 'slack'; }
}

// 3b. A completely different class hierarchy — uses trait + interface
//     without extending CoreNotifier
class NotificationJob extends JobBase implements Notifiable {
    use NotificationLogging; // Same free send() implementation

    public function __construct(private string $to, private string $message) {}

    public function handle(): void {
        $this->send($this->to, $this->message);
    }

    protected function deliver(string $to, string $message): bool {
        echo "  → Queue worker dispatch\n"; return true;
    }

    public function getChannelName(): string { return 'queue'; }
}

// ✅ Type-safe — accepts any Notifiable, whether it extends CoreNotifier or not
function dispatch(Notifiable $notifier, string $to, string $message): void {
    $notifier->send($to, $message);
}

dispatch(new EmailNotifier(),    'alice@example.com', 'Hello via email');
dispatch(new SlackNotifier(),    '#general',          'Hello via Slack');
dispatch(new NotificationJob('bob@example.com', 'Hello from queue'), 'bob@example.com', 'Hello from queue');
// All three are Notifiable — type-safe ✅ Logging shared — DRY ✅ Cross-hierarchy ✅


// ─────────────────────────────────────────────────────────────────────────────
// Summary table — printed as output
// ─────────────────────────────────────────────────────────────────────────────

echo "\n═══ Decision Summary ═══════════════════════════════════\n\n";
echo "  NEED                                     TOOL\n";
echo "  ────────────────────────────────────── ─────────────────────\n";
echo "  Type for type-hints / instanceof         Interface\n";
echo "  Shared code, single hierarchy            Abstract class\n";
echo "  Shared code, multiple hierarchies        Trait\n";
echo "  Type + shared code, single hierarchy     Abstract class\n";
echo "  Type + shared code, multiple hierarchies Interface + Trait\n";
echo "  All three at once                        Abstract (interface+trait) + classes that also use trait\n\n";

echo "--- Recap ---\n";
echo "No single tool is always right — choose based on what you actually need.\n";
echo "Interface alone:           when contract matters and repetition is acceptable.\n";
echo "Abstract class alone:      when shared code + single hierarchy is enough.\n";
echo "Trait alone:               when cross-hierarchy sharing matters, type-safety does not.\n";
echo "Interface + Trait:         the standard real-world pattern — type-safe AND DRY.\n";
echo "Abstract + Interface + Trait: full combination for complex frameworks.\n";