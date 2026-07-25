<?php
declare(strict_types=1);

/**
 * CHALLENGE SOLUTION — Lesson 2.3: Enums
 * ───────────────────────────────────────
 * PHP 8.1+
 * ⚠️  Only open this file after completing starter.php yourself.
 *
 * Key things to compare in your solution:
 *   1. Three backed enums with all required methods
 *   2. Notification uses enum types for channel, priority, status
 *   3. NotificationService has zero string constants
 *   4. sendFromRequest() uses tryFrom() for both channel and priority
 *   5. All branching uses match() not if/switch on strings
 *   6. Repository filter uses enum identity (===) not string comparison
 *   7. All calling code uses enum cases, not string literals
 */


// ─────────────────────────────────────────────────────────────────────────────
// Task 1 — NotificationChannel
// ─────────────────────────────────────────────────────────────────────────────

enum NotificationChannel: string {
    case Email = 'email';
    case SMS   = 'sms';
    case Push  = 'push';
    case Slack = 'slack';

    public function label(): string {
        return match($this) {
            self::Email => 'Email',
            self::SMS   => 'SMS',
            self::Push  => 'Push Notification',
            self::Slack => 'Slack',
        };
    }

    public function supportsRichText(): bool {
        return match($this) {
            self::Email, self::Slack => true,
            self::SMS,   self::Push  => false,
        };
    }

    public function maxMessageLength(): int {
        return match($this) {
            self::Email => 10000,
            self::SMS   => 160,
            self::Push  => 256,
            self::Slack => 4000,
        };
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Task 2 — NotificationPriority
// ─────────────────────────────────────────────────────────────────────────────

enum NotificationPriority: int {
    case Low    = 1;
    case Normal = 5;
    case High   = 10;
    case Urgent = 99;

    public function label(): string {
        return match($this) {
            self::Low    => '🟢 Low',
            self::Normal => '📢 Normal',
            self::High   => '🔴 High',
            self::Urgent => '🚨 Urgent',
        };
    }

    public function shouldAlert(): bool {
        return match($this) {
            self::High, self::Urgent => true,
            self::Low,  self::Normal => false,
        };
    }

    public function retryAttempts(): int {
        return match($this) {
            self::Low    => 1,
            self::Normal => 3,
            self::High   => 5,
            self::Urgent => 10,
        };
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Task 3 — NotificationStatus
// ─────────────────────────────────────────────────────────────────────────────

enum NotificationStatus: string {
    case Pending   = 'pending';
    case Sent      = 'sent';
    case Failed    = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string {
        return match($this) {
            self::Pending   => '⏳ Pending',
            self::Sent      => '✅ Sent',
            self::Failed    => '❌ Failed',
            self::Cancelled => '🚫 Cancelled',
        };
    }

    public function isTerminal(): bool {
        return match($this) {
            self::Sent,    self::Failed,
            self::Cancelled => true,
            self::Pending   => false,
        };
    }

    public function canRetry(): bool {
        return $this === self::Failed;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Task 4 — Notification class with enum-typed properties
// ─────────────────────────────────────────────────────────────────────────────

class Notification {
    public NotificationStatus $status = NotificationStatus::Pending;

    public function __construct(
        public readonly string              $id,
        public string                       $recipient,
        public string                       $message,
        public NotificationChannel          $channel,
        public NotificationPriority         $priority
    ) {}
}


// ─────────────────────────────────────────────────────────────────────────────
// Task 5 — NotificationService: no string constants, enum params, tryFrom()
// ─────────────────────────────────────────────────────────────────────────────

class NotificationService {
    // Zero string constants — replaced by enum cases

    private array $sent = [];

    public function send(
        string                   $recipient,
        string                   $message,
        NotificationChannel      $channel,     // enum type
        NotificationPriority     $priority     // enum type
    ): Notification {
        $notification = new Notification(
            id:        uniqid('INF-'),
            recipient: $recipient,
            message:   $message,
            channel:   $channel,
            priority:  $priority
        );

        // All branching via enum methods — no raw string comparisons
        echo "[{$channel->name}] {$priority->label()}: {$message}"
           . " (retries: {$priority->retryAttempts()})\n";
        echo "  Channel: {$channel->label()}"
           . " | Rich text: " . ($channel->supportsRichText() ? 'YES' : 'NO')
           . " | Max length: {$channel->maxMessageLength()} chars\n";

        $notification->status = NotificationStatus::Sent;
        $this->sent[]         = $notification;
        return $notification;
    }

    // Task 5: safe parsing from external request using tryFrom()
    public function sendFromRequest(array $request): ?Notification {
        $rawChannel  = $request['channel']  ?? '';
        $rawPriority = (int) ($request['priority'] ?? 0);

        $channel = NotificationChannel::tryFrom($rawChannel);
        if ($channel === null) {
            $valid = implode(', ', array_map(fn($c) => $c->value, NotificationChannel::cases()));
            echo "✗ Invalid channel '{$rawChannel}' — valid options: {$valid}\n";
            return null;
        }

        $priority = NotificationPriority::tryFrom($rawPriority);
        if ($priority === null) {
            $valid = implode(', ', array_map(fn($p) => (string)$p->value, NotificationPriority::cases()));
            echo "✗ Invalid priority '{$rawPriority}' — valid options: {$valid}\n";
            return null;
        }

        echo "✓ Parsed channel: {$channel->label()}\n";
        echo "✓ Parsed priority: {$priority->label()}\n";

        return $this->send($request['recipient'] ?? 'unknown', $request['message'] ?? '', $channel, $priority);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Task 6 — NotificationRepository: enum-typed filter parameters
// ─────────────────────────────────────────────────────────────────────────────

class NotificationRepository {
    /** @var Notification[] */
    private array $records = [];

    public function add(Notification $n): void {
        $this->records[] = $n;
    }

    public function findByChannel(NotificationChannel $channel): array {
        return array_values(array_filter(
            $this->records,
            fn(Notification $n) => $n->channel === $channel  // enum identity
        ));
    }

    public function findByStatus(NotificationStatus $status): array {
        return array_values(array_filter(
            $this->records,
            fn(Notification $n) => $n->status === $status    // enum identity
        ));
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Task 7 — Calling code uses enum cases, not string literals
// ─────────────────────────────────────────────────────────────────────────────

$service = new NotificationService();
$repo    = new NotificationRepository();

echo "=== Sending notifications ===\n";

// Enum cases instead of magic strings
$n1 = $service->send('ops@example.com',  'Server is down!',         NotificationChannel::Email, NotificationPriority::Urgent);
$n2 = $service->send('+27821234567',     'Disk usage above 90%',    NotificationChannel::SMS,   NotificationPriority::High);
$n3 = $service->send('device-token-abc', 'Weekly summary is ready', NotificationChannel::Push,  NotificationPriority::Normal);
$n4 = $service->send('#ops-channel',     'Cron job completed',      NotificationChannel::Slack, NotificationPriority::Low);

$repo->add($n1);
$repo->add($n2);
$repo->add($n3);
$repo->add($n4);

echo "\n=== Status transitions ===\n";

$inf1 = new Notification('INF-001', 'alice@example.com', 'Test message', NotificationChannel::Email, NotificationPriority::Normal);
$inf2 = new Notification('INF-002', 'bob@example.com',   'Test message', NotificationChannel::SMS,   NotificationPriority::High);

echo "{$inf1->id}: {$inf1->status->label()} → ";
$inf1->status = NotificationStatus::Sent;
echo "{$inf1->status->label()}\n";

echo "{$inf2->id}: {$inf2->status->label()} → ";
$inf2->status = NotificationStatus::Failed;
echo "{$inf2->status->label()}\n";

// Enum methods instead of string comparisons
if ($inf2->status->canRetry()) {
    echo "{$inf2->id}: canRetry=YES → ";
    $inf2->status = NotificationStatus::Pending;
    echo "{$inf2->status->label()} → ";
    $inf2->status = NotificationStatus::Sent;
    echo "{$inf2->status->label()}\n";
}

echo "\n=== Repository queries ===\n";
// Enum cases as arguments — type-safe
echo "Email notifications: "  . count($repo->findByChannel(NotificationChannel::Email)) . "\n";
echo "Failed notifications: " . count($repo->findByStatus(NotificationStatus::Failed))  . "\n";

echo "\n=== Parsing from external request ===\n";

$requests = [
    ['channel' => 'email',    'priority' => '10',  'message' => 'Test', 'recipient' => 'alice@example.com'],
    ['channel' => 'telegram', 'priority' => '10',  'message' => 'Test', 'recipient' => ''],
    ['channel' => 'sms',      'priority' => '7',   'message' => 'Test', 'recipient' => ''],
];

foreach ($requests as $req) {
    $service->sendFromRequest($req);
}


// ─────────────────────────────────────────────────────────────────────────────
// SELF-REVIEW CHECKLIST
// ─────────────────────────────────────────────────────────────────────────────
echo "\n--- Self-review checklist ---\n";
echo "[ ] Three backed enums with correct cases and backing values?\n";
echo "[ ] All required methods (label, shouldAlert/supportsRichText/etc.) present?\n";
echo "[ ] Notification.\$channel is NotificationChannel, not string?\n";
echo "[ ] Notification.\$priority is NotificationPriority, not string?\n";
echo "[ ] Notification.\$status is NotificationStatus with default ::Pending?\n";
echo "[ ] NotificationService has zero CHANNEL_* / PRIORITY_* string constants?\n";
echo "[ ] sendFromRequest() uses tryFrom() for both channel and priority?\n";
echo "[ ] All branching uses match() or enum methods — no string comparisons?\n";
echo "[ ] Repository filter parameters are enum types, not strings?\n";
echo "[ ] Calling code uses enum cases (NotificationChannel::Email), not 'email'?\n";