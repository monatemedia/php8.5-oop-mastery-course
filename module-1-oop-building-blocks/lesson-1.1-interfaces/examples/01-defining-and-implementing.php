<?php
declare(strict_types=1);

/**
 * Example 01 — Defining and Implementing a Single Interface
 * ---------------------------------------------------------
 * Run from the course root:
 *   php module-1-oop-building-blocks/lesson-1.1-interfaces/examples/01-defining-and-implementing.php
 *
 * Every example in this course is a CLI script. Do not open them in a browser —
 * the box-drawing output and \n line breaks only render correctly in a terminal.
 *
 * What you will see:
 *  - How to declare an interface
 *  - How to implement it in a concrete class
 *  - What happens when a class breaks the contract (commented out — uncomment to test)
 */

// ─────────────────────────────────────────────────────────────────────────────
// STEP 1: Define the interface (the contract)
// ─────────────────────────────────────────────────────────────────────────────

interface Notification {
    /**
     * Send a message to a recipient.
     * Every class that implements Notification MUST provide this method
     * with this exact signature.
     */
    public function send(string $recipient, string $message): bool;
}


// ─────────────────────────────────────────────────────────────────────────────
// STEP 2: Implement the interface (fulfil the contract)
// ─────────────────────────────────────────────────────────────────────────────

class EmailNotification implements Notification {
    public function send(string $recipient, string $message): bool {
        // In a real app this would call a mail API.
        // For now, we just simulate it.
        echo "[EMAIL] To: {$recipient} | Message: {$message}\n";
        return true;
    }
}

class SmsNotification implements Notification {
    public function send(string $recipient, string $message): bool {
        echo "[SMS]   To: {$recipient} | Message: {$message}\n";
        return true;
    }
}

class PushNotification implements Notification {
    public function send(string $recipient, string $message): bool {
        echo "[PUSH]  To: {$recipient} | Message: {$message}\n";
        return true;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// STEP 3: Use the concrete classes
// ─────────────────────────────────────────────────────────────────────────────

echo "=== Sending notifications ===\n\n";

$email = new EmailNotification();
$email->send("alice@example.com", "Your invoice is ready.");

$sms = new SmsNotification();
$sms->send("+27821234567", "Your OTP is 482910.");

$push = new PushNotification();
$push->send("device-token-abc123", "You have a new message.");


// ─────────────────────────────────────────────────────────────────────────────
// STEP 4: instanceof — check if an object fulfils a contract at runtime
// ─────────────────────────────────────────────────────────────────────────────

echo "\n=== instanceof checks ===\n\n";

$notifiers = [
    new EmailNotification(),
    new SmsNotification(),
    new PushNotification(),
];

foreach ($notifiers as $notifier) {
    $className = get_class($notifier);
    $result    = $notifier instanceof Notification ? 'YES' : 'NO';
    echo "{$className} implements Notification? {$result}\n";
}


// ─────────────────────────────────────────────────────────────────────────────
// EXPERIMENT: Uncomment the block below to see PHP enforce the contract.
// A class that does NOT implement all interface methods causes a fatal error.
// ─────────────────────────────────────────────────────────────────────────────

/*
class BrokenNotification implements Notification {
    // send() is missing — PHP will throw:
    // Fatal error: Class BrokenNotification contains 1 abstract method
    // and must therefore be declared abstract or implement the remaining methods
}

$broken = new BrokenNotification();
*/


// ─────────────────────────────────────────────────────────────────────────────
// RECAP
// ─────────────────────────────────────────────────────────────────────────────
echo "\n--- Recap ---\n";
echo "1. 'interface' declares a contract — method names, params, return types.\n";
echo "2. 'implements' tells PHP this class agrees to the contract.\n";
echo "3. PHP enforces the contract at CLASS LOAD TIME, not at runtime.\n";
echo "4. A class can be checked against an interface with 'instanceof'.\n";