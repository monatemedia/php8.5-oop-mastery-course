<?php
declare(strict_types=1);

/**
 * Example 01 — Inheritance vs Composition
 * -----------------------------------------
 * The SAME notification system built twice:
 *   APPROACH A: inheritance — NotificationService extends DatabaseService
 *   APPROACH B: composition — NotificationService has a DatabaseInterface
 *
 * Both produce identical output. The difference is entirely in:
 *   - How testable each approach is
 *   - How swappable each dependency is
 *   - How clearly each class expresses its own responsibility
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Inheritance vs Composition — Same Problem, Two Ways║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Shared infrastructure (both approaches use these)
// ─────────────────────────────────────────────────────────────────────────────

interface DatabaseInterface {
    public function query(string $sql, array $params = []): array;
    public function execute(string $sql, array $params = []): bool;
}

interface MailerInterface {
    public function send(string $to, string $subject, string $body): bool;
}

class InMemoryDatabase implements DatabaseInterface {
    private array $users = [
        1 => ['id' => 1, 'email' => 'alice@example.com', 'name' => 'Alice'],
        2 => ['id' => 2, 'email' => 'bob@example.com',   'name' => 'Bob'],
    ];
    private array $notifications = [];

    public function query(string $sql, array $params = []): array {
        if (str_contains($sql, 'users') && !empty($params)) {
            return isset($this->users[$params[0]]) ? [$this->users[$params[0]]] : [];
        }
        return [];
    }

    public function execute(string $sql, array $params = []): bool {
        if (str_contains($sql, 'notifications')) {
            $this->notifications[] = $params;
            echo "  [DB] Notification logged for user #{$params[0]}\n";
        }
        return true;
    }
}

class ConsoleMailer implements MailerInterface {
    public function send(string $to, string $subject, string $body): bool {
        echo "  [MAIL] To: {$to} | {$subject}\n";
        return true;
    }
}


// ═══════════════════════════════════════════════════════════
// APPROACH A — Inheritance
// NotificationService IS a DatabaseService (extends it)
// ═══════════════════════════════════════════════════════════

echo "── Approach A: Inheritance ───────────────────────────\n\n";

class DatabaseService {
    // Concrete class — hardwired dependency
    protected InMemoryDatabase $db;

    public function __construct() {
        // ❌ NotificationService inherits this — and all its coupling
        $this->db = new InMemoryDatabase();
    }

    protected function findUser(int $id): ?array {
        $rows = $this->db->query('SELECT * FROM users WHERE id = ?', [$id]);
        return $rows[0] ?? null;
    }

    protected function logNotification(int $userId, string $message): void {
        $this->db->execute(
            'INSERT INTO notifications (user_id, message) VALUES (?, ?)',
            [$userId, $message]
        );
    }
}

class InheritedNotificationService extends DatabaseService {
    // ❌ Cannot inject a different database — it is hardwired in parent
    // ❌ Cannot inject a different mailer — it is created here
    // ❌ To test this class, you must also bring up DatabaseService's dependencies
    private ConsoleMailer $mailer;

    public function __construct() {
        parent::__construct(); // ← triggers InMemoryDatabase creation
        $this->mailer = new ConsoleMailer(); // ← another hardwired dependency
    }

    public function notifyUser(int $userId, string $message): bool {
        $user = $this->findUser($userId); // inherited
        if ($user === null) return false;

        $sent = $this->mailer->send($user['email'], 'Notification', $message);
        if ($sent) {
            $this->logNotification($userId, $message); // inherited
        }
        return $sent;
    }
}

$inheritedService = new InheritedNotificationService();
echo "notifyUser(1, 'Your order shipped'):\n";
$inheritedService->notifyUser(1, 'Your order shipped');

echo "\nnotifyUser(2, 'Password changed'):\n";
$inheritedService->notifyUser(2, 'Password changed');

echo "\nProblems with Approach A:\n";
echo "  ✗ Cannot test without a real database (InMemoryDatabase is hardwired in parent)\n";
echo "  ✗ Cannot swap to a different mailer without editing the class\n";
echo "  ✗ Inheriting from DatabaseService implies NotificationService IS a DatabaseService\n";
echo "    — that is not true. It just USES a database.\n";
echo "  ✗ Changing DatabaseService's constructor affects ALL subclasses\n\n";


// ═══════════════════════════════════════════════════════════
// APPROACH B — Composition
// NotificationService HAS a database and a mailer (injected)
// ═══════════════════════════════════════════════════════════

echo "── Approach B: Composition ───────────────────────────\n\n";

class ComposedNotificationService {
    // ✅ No parent class — no inherited coupling
    // ✅ Both dependencies are interfaces — any implementation works
    public function __construct(
        private DatabaseInterface $db,
        private MailerInterface   $mailer
    ) {}

    public function notifyUser(int $userId, string $message): bool {
        $rows = $this->db->query('SELECT * FROM users WHERE id = ?', [$userId]);
        $user = $rows[0] ?? null;

        if ($user === null) return false;

        $sent = $this->mailer->send($user['email'], 'Notification', $message);
        if ($sent) {
            $this->db->execute(
                'INSERT INTO notifications (user_id, message) VALUES (?, ?)',
                [$userId, $message]
            );
        }
        return $sent;
    }
}

// Composition root — caller decides which implementations to use
$db     = new InMemoryDatabase();
$mailer = new ConsoleMailer();
$service = new ComposedNotificationService($db, $mailer);

echo "notifyUser(1, 'Your order shipped'):\n";
$service->notifyUser(1, 'Your order shipped');

echo "\nnotifyUser(2, 'Password changed'):\n";
$service->notifyUser(2, 'Password changed');

echo "\nAdvantages of Approach B:\n";
echo "  ✓ Testable: inject a fake database and a spy mailer — no real infra needed\n";
echo "  ✓ Swappable: change to a real MySQL database without touching the service\n";
echo "  ✓ Accurate: NotificationService USES a database, it is not a DatabaseService\n";
echo "  ✓ Clean constructor: all dependencies are visible and explicit\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Proof: Approach B is testable, Approach A is not
// ─────────────────────────────────────────────────────────────────────────────

echo "── Proof: Approach B is testable ─────────────────────\n\n";

// Spy mailer — records what was sent
$spyMailer = new class implements MailerInterface {
    public array $sent = [];
    public function send(string $to, string $subject, string $body): bool {
        $this->sent[] = compact('to', 'subject', 'body');
        return true;
    }
};

// Fake database — returns controlled data, no disk/network
$fakeDb = new class implements DatabaseInterface {
    public function query(string $sql, array $params = []): array {
        return [['id' => 99, 'email' => 'test@example.com', 'name' => 'Test User']];
    }
    public function execute(string $sql, array $params = []): bool { return true; }
};

$testService = new ComposedNotificationService($fakeDb, $spyMailer);
$result      = $testService->notifyUser(99, 'Test notification');

echo "Test assertions:\n";
echo "  notifyUser returned: " . ($result ? 'true ✓' : 'false ✗') . "\n";
echo "  Mailer called once:  " . (count($spyMailer->sent) === 1 ? 'true ✓' : 'false ✗') . "\n";
echo "  Email sent to:       {$spyMailer->sent[0]['to']}\n";
echo "  (No real database, no real mailer — pure logic test)\n\n";

echo "  InheritedNotificationService: impossible to test the same way.\n";
echo "  Its constructor ALWAYS creates InMemoryDatabase and ConsoleMailer.\n";
echo "  You cannot substitute fakes without modifying the source.\n";

echo "\n--- Recap ---\n";
echo "Inheritance says: 'I AM a kind of this thing'\n";
echo "Composition says: 'I USE this thing'\n";
echo "If you can replace 'extends X' with 'private X \$x injected via constructor'\n";
echo "→ composition is almost certainly the better choice.\n";