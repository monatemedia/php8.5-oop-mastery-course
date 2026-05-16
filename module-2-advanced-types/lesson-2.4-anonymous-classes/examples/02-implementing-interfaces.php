<?php
declare(strict_types=1);

/**
 * Example 02 — Implementing Interfaces Inline
 * ---------------------------------------------
 * Anonymous classes shine when you need a one-off implementation of an interface.
 * The most common use case: test stubs, fakes, and spy objects — inline,
 * without the overhead of a separate class file for each.
 *
 * This example shows:
 *   A. Simple inline implementations for interfaces
 *   B. Test stubs that record what was called
 *   C. Null Object pattern — a "do nothing" implementation
 *   D. Configurable anonymous class behaviour
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Implementing Interfaces Inline                     ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// The interfaces and classes used throughout this example
// ─────────────────────────────────────────────────────────────────────────────

interface Logger {
    public function log(string $level, string $message): void;
}

interface Mailer {
    public function send(string $to, string $subject, string $body): bool;
}

interface Cache {
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, int $ttl = 3600): void;
    public function delete(string $key): void;
    public function has(string $key): bool;
}

// The service under test — depends on Logger and Mailer
class UserRegistrationService {
    public function __construct(
        private Logger $logger,
        private Mailer $mailer
    ) {}

    public function register(string $email, string $password): bool {
        $this->logger->log('INFO', "Registering user: {$email}");

        // Simulate some registration logic
        if (empty($email) || !str_contains($email, '@')) {
            $this->logger->log('ERROR', "Invalid email: {$email}");
            return false;
        }

        $sent = $this->mailer->send(
            $email,
            'Welcome!',
            "Your account has been created."
        );

        if (!$sent) {
            $this->logger->log('WARN', "Welcome email failed for: {$email}");
        } else {
            $this->logger->log('INFO', "Welcome email sent to: {$email}");
        }

        return true;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// PART A — Simple inline implementation
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part A: Simple inline implementation ─────────────\n\n";

// Define exactly where you need it — no separate file
$consoleLogger = new class implements Logger {
    public function log(string $level, string $message): void {
        echo "[{$level}] {$message}\n";
    }
};

$consoleLogger->log('INFO', 'Application started');
$consoleLogger->log('WARN', 'Low memory');
$consoleLogger->log('ERROR', 'Database unreachable');

// instanceof works just like a named class
var_dump($consoleLogger instanceof Logger); // true


// ─────────────────────────────────────────────────────────────────────────────
// PART B — Spy/stub that records calls (test double pattern)
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part B: Spy / stub recording calls ───────────────\n\n";

// A spy logger — records every call so you can assert on it
$spyLogger = new class implements Logger {
    public array $entries = []; // Public so the test can read it

    public function log(string $level, string $message): void {
        $this->entries[] = ['level' => $level, 'message' => $message];
    }

    public function hasEntry(string $level, string $partial): bool {
        foreach ($this->entries as $entry) {
            if ($entry['level'] === $level && str_contains($entry['message'], $partial)) {
                return true;
            }
        }
        return false;
    }
};

// A stub mailer — always succeeds, records sent emails
$stubMailer = new class implements Mailer {
    public array $sent = [];

    public function send(string $to, string $subject, string $body): bool {
        $this->sent[] = compact('to', 'subject', 'body');
        return true;
    }
};

// Run the service with the spies
$service = new UserRegistrationService($spyLogger, $stubMailer);
$result  = $service->register('alice@example.com', 'secret123');

echo "Registration result: " . ($result ? 'SUCCESS' : 'FAIL') . "\n";

// Assertions (simulated without a test framework)
echo "\nLog entries recorded:\n";
foreach ($spyLogger->entries as $entry) {
    echo "  [{$entry['level']}] {$entry['message']}\n";
}

echo "\nEmails sent:\n";
foreach ($stubMailer->sent as $email) {
    echo "  To: {$email['to']} | Subject: {$email['subject']}\n";
}

echo "\nAssertions:\n";
$checks = [
    ['Log has INFO entry for alice@example.com',
     fn() => $spyLogger->hasEntry('INFO', 'alice@example.com')],
    ['Exactly one email was sent',
     fn() => count($stubMailer->sent) === 1],
    ['Email sent to alice@example.com',
     fn() => $stubMailer->sent[0]['to'] === 'alice@example.com'],
    ['Registration returned true',
     fn() => $result === true],
];

foreach ($checks as [$label, $check]) {
    echo "  " . ($check() ? '✓' : '✗') . " {$label}\n";
}


// ─────────────────────────────────────────────────────────────────────────────
// PART C — Failing stub and error path testing
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part C: Testing the failure path ─────────────────\n\n";

$failingMailer = new class implements Mailer {
    public function send(string $to, string $subject, string $body): bool {
        return false; // Always fails
    }
};

$spyLogger2 = new class implements Logger {
    public array $entries = [];
    public function log(string $level, string $message): void {
        $this->entries[] = compact('level', 'message');
    }
};

$service2 = new UserRegistrationService($spyLogger2, $failingMailer);
$service2->register('bob@example.com', 'pass456');

echo "Log entries (with failing mailer):\n";
foreach ($spyLogger2->entries as $entry) {
    echo "  [{$entry['level']}] {$entry['message']}\n";
}


// ─────────────────────────────────────────────────────────────────────────────
// PART D — Null Object pattern
// An implementation that intentionally does nothing — useful in production
// when a logger/mailer is optional and you don't want null checks everywhere
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part D: Null Object pattern ──────────────────────\n\n";

$nullLogger = new class implements Logger {
    public function log(string $level, string $message): void {
        // Intentionally silent — no output, no storage
    }
};

$nullMailer = new class implements Mailer {
    public function send(string $to, string $subject, string $body): bool {
        return true; // Pretends to succeed — side-effect free
    }
};

echo "Running service with null implementations (no output from service):\n";
$service3 = new UserRegistrationService($nullLogger, $nullMailer);
$result3  = $service3->register('carol@example.com', 'pass789');
echo "Result: " . ($result3 ? 'SUCCESS' : 'FAIL') . " (null implementations — no side effects)\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART E — Configurable anonymous class
// An anonymous class whose behaviour is configured via constructor
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part E: Configurable anonymous class ─────────────\n\n";

// A cache stub whose behaviour is controllable from outside
$cacheStub = new class(['user:1' => ['name' => 'Alice']]) implements Cache {
    public function __construct(private array $store = []) {}

    public function get(string $key): mixed     { return $this->store[$key] ?? null; }
    public function has(string $key): bool       { return isset($this->store[$key]); }
    public function set(string $key, mixed $value, int $ttl = 3600): void {
        $this->store[$key] = $value;
    }
    public function delete(string $key): void    { unset($this->store[$key]); }

    // Extra spy method
    public function all(): array                 { return $this->store; }
};

echo "Cache has 'user:1'? " . ($cacheStub->has('user:1') ? 'YES' : 'NO') . "\n";
$user = $cacheStub->get('user:1');
echo "User: " . json_encode($user) . "\n";

$cacheStub->set('user:2', ['name' => 'Bob']);
echo "Cache after set:\n";
foreach ($cacheStub->all() as $k => $v) {
    echo "  {$k} → " . json_encode($v) . "\n";
}

echo "\n--- Recap ---\n";
echo "Anonymous classes implement interfaces exactly like named classes.\n";
echo "Type hint against the interface — not the anonymous class itself.\n";
echo "Spies record calls; stubs return fixed values; null objects do nothing.\n";
echo "No separate file needed — define the stub exactly where you use it.\n";