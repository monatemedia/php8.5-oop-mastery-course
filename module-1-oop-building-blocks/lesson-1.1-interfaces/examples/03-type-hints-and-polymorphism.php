<?php
declare(strict_types=1);

/**
 * Example 03 — Type Hints and Polymorphism
 * ------------------------------------------
 * This is the payoff for using interfaces.
 *
 * When you type-hint a parameter as an interface, PHP accepts ANY class
 * that implements it. The calling code decides which concrete class to use.
 * The receiving code knows only the contract — and that is all it needs.
 *
 * Scenario: An e-commerce OrderProcessor that must notify customers.
 * We will swap notifiers in and out without touching OrderProcessor at all.
 */

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║  SOLID CALLOUT — O: Open/Closed Principle (OCP)                         ║
// ╠══════════════════════════════════════════════════════════════════════════╣
// ║  "Open for extension, closed for modification."                         ║
// ║                                                                          ║
// ║  OrderProcessor::process() is CLOSED — it will never be edited again.  ║
// ║  Yet it is OPEN for extension: add SlackNotifier, WhatsAppNotifier,     ║
// ║  or any other channel by creating a new class — zero existing code      ║
// ║  is touched.                                                             ║
// ║                                                                          ║
// ║  The VIOLATION would be: an if/elseif chain inside process() that       ║
// ║  checks the channel type string and grows every time a new one is added.║
// ║  See lesson-1.0-solid-overview/examples/02-ocp.php for the full violation.║
// ╠══════════════════════════════════════════════════════════════════════════╣
// ║  SOLID CALLOUT — D: Dependency Inversion Principle (DIP) — PREVIEW      ║
// ╠══════════════════════════════════════════════════════════════════════════╣
// ║  OrderProcessor depends on the Notifier and Logger INTERFACES, not on  ║
// ║  EmailNotifier or ConsoleLogger. High-level logic (OrderProcessor)      ║
// ║  depends on an abstraction, not a detail. This is DIP in its simplest  ║
// ║  form. Full coverage in Modules 3 and 4.                                ║
// ╚══════════════════════════════════════════════════════════════════════════╝

// ─────────────────────────────────────────────────────────────────────────────
// The contract
// ─────────────────────────────────────────────────────────────────────────────

interface Notifier {
    public function send(string $to, string $subject, string $body): void;
}

interface Logger {
    public function log(string $level, string $message): void;
}


// ─────────────────────────────────────────────────────────────────────────────
// Concrete implementations of Notifier
// ─────────────────────────────────────────────────────────────────────────────

class EmailNotifier implements Notifier {
    public function send(string $to, string $subject, string $body): void {
        echo "[EMAIL] ──────────────────────────────\n";
        echo "  To:      {$to}\n";
        echo "  Subject: {$subject}\n";
        echo "  Body:    {$body}\n";
    }
}

class SmsNotifier implements Notifier {
    public function send(string $to, string $subject, string $body): void {
        // SMS does not use "subject" — we just send a short combined message
        $shortMessage = "[{$subject}] {$body}";
        echo "[SMS] To: {$to} | Msg: {$shortMessage}\n";
    }
}

class SlackNotifier implements Notifier {
    public function __construct(private string $channel = '#orders') {}

    public function send(string $to, string $subject, string $body): void {
        echo "[SLACK] Channel: {$this->channel} | {$subject}: {$body} (user: {$to})\n";
    }
}

// A "null" notifier — does nothing. Useful in tests or when notifications are disabled.
class NullNotifier implements Notifier {
    public function send(string $to, string $subject, string $body): void {
        // Intentionally silent — no output, no side effects.
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Concrete implementations of Logger
// ─────────────────────────────────────────────────────────────────────────────

class ConsoleLogger implements Logger {
    public function log(string $level, string $message): void {
        $timestamp = date('H:i:s');
        echo "  [{$timestamp}] [{$level}] {$message}\n";
    }
}

class SilentLogger implements Logger {
    public function log(string $level, string $message): void {
        // No output — used where logging is disabled
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// The high-level class — depends ONLY on interfaces, never concrete classes
// ─────────────────────────────────────────────────────────────────────────────

class OrderProcessor {
    // Both constructor parameters are typed against interfaces.
    // OrderProcessor does not know or care about EmailNotifier, SmsNotifier, etc.
    public function __construct(
        private Notifier $notifier,
        private Logger   $logger
    ) {}

    public function process(array $order): void {
        $this->logger->log('INFO', "Processing order #{$order['id']}");

        // Simulate some processing...
        $total = $order['quantity'] * $order['price'];

        $this->logger->log('INFO', "Order total: R{$total}");

        // Notify the customer using whatever Notifier was injected
        $this->notifier->send(
            $order['customer_email'],
            "Order Confirmed",
            "Your order #{$order['id']} for R{$total} has been confirmed."
        );

        $this->logger->log('INFO', "Notification sent for order #{$order['id']}");
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Wiring — this is where the concrete choice is made (only here, nowhere else)
// ─────────────────────────────────────────────────────────────────────────────

$order = [
    'id'             => 1042,
    'customer_email' => 'bob@example.com',
    'quantity'       => 3,
    'price'          => 299,
];

$logger = new ConsoleLogger();

echo "\n=== Run 1: Email Notifier ===\n";
$processor = new OrderProcessor(new EmailNotifier(), $logger);
$processor->process($order);

echo "\n=== Run 2: SMS Notifier ===\n";
$processor = new OrderProcessor(new SmsNotifier(), $logger);
$processor->process($order);

echo "\n=== Run 3: Slack Notifier ===\n";
$processor = new OrderProcessor(new SlackNotifier('#fulfillment'), $logger);
$processor->process($order);

echo "\n=== Run 4: Null Notifier + Silent Logger (e.g. during unit testing) ===\n";
$processor = new OrderProcessor(new NullNotifier(), new SilentLogger());
$processor->process($order);
echo "  (No output — both notifier and logger are silent)\n";


// ─────────────────────────────────────────────────────────────────────────────
// Demonstration: a standalone function that also uses type hints
// ─────────────────────────────────────────────────────────────────────────────

echo "\n=== Standalone function using Notifier type hint ===\n";

function broadcastToAll(array $notifiers, string $subject, string $body): void {
    foreach ($notifiers as $notifier) {
        // $notifier is type-hinted as Notifier — PHP guarantees send() exists
        $notifier->send('broadcast@example.com', $subject, $body);
    }
}

$allNotifiers = [
    new EmailNotifier(),
    new SmsNotifier(),
    new SlackNotifier('#announcements'),
];

broadcastToAll($allNotifiers, 'System Maintenance', 'Site will be down at 02:00.');

echo "\n--- Recap ---\n";
echo "1. Type-hint parameters with interfaces, not concrete class names.\n";
echo "2. Polymorphism: the same code path works for any implementing class.\n";
echo "3. The 'wiring' decision (which class to use) belongs at the TOP of your app.\n";
echo "4. NullObject pattern: implement the interface but do nothing — great for tests.\n";