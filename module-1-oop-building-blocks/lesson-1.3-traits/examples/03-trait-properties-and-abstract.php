<?php
declare(strict_types=1);

/**
 * Example 03 — Trait Properties and Abstract Trait Methods
 * ----------------------------------------------------------
 * Traits can do more than just inject methods:
 *   - They can declare PROPERTIES that are injected into the host class
 *   - They can declare ABSTRACT methods that the host class MUST implement
 *   - They can even use CONSTANTS (PHP 8.2+)
 *
 * Properties bring shared state. Abstract methods let a trait express
 * a dependency: "I need you to tell me X before I can do my work."
 *
 * Scenario: An ORM-style model system with timestamp, change-tracking,
 * and serialisation traits — each demonstrating properties and abstracts.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Trait Properties and Abstract Methods             ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 1 — Trait Properties
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 1: Trait Properties ─────────────────────────\n\n";

trait Timestampable {
    // These properties are injected into every class that uses this trait
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    public function initTimestamps(): void {
        $now             = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function touchUpdatedAt(): void {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function formatTimestamps(): string {
        return "Created: " . $this->createdAt->format('Y-m-d H:i:s')
             . " | Updated: " . $this->updatedAt->format('Y-m-d H:i:s');
    }
}

trait SoftDeletable {
    private ?string $deletedAt = null; // Null = not deleted

    public function softDelete(): void {
        $this->deletedAt = date('Y-m-d H:i:s');
    }

    public function restore(): void {
        $this->deletedAt = null;
    }

    public function isTrashed(): bool {
        return $this->deletedAt !== null;
    }
}

class UserModel {
    use Timestampable, SoftDeletable;

    private string $email;

    public function __construct(string $email) {
        $this->email = $email;
        $this->initTimestamps(); // Trait method — initialises trait properties
    }

    public function updateEmail(string $email): void {
        $this->email = $email;
        $this->touchUpdatedAt(); // Trait method — updates $updatedAt property
    }
}

$user = new UserModel('alice@example.com');
echo "New user timestamps:\n";
echo "  " . $user->formatTimestamps() . "\n";

sleep(0); // In real code you'd wait — skip for demo speed
$user->updateEmail('alice-updated@example.com');
echo "After email update:\n";
echo "  " . $user->formatTimestamps() . "\n";

echo "Trashed? " . ($user->isTrashed() ? 'YES' : 'NO') . "\n";
$user->softDelete();
echo "Trashed? " . ($user->isTrashed() ? 'YES' : 'NO') . "\n";
$user->restore();
echo "Trashed after restore? " . ($user->isTrashed() ? 'YES' : 'NO') . "\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 2 — Property Conflict Rule
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 2: Property conflict rule ──────────────────\n\n";

trait HasStatus {
    protected string $status = 'draft'; // Trait declares $status

    public function getStatus(): string  { return $this->status; }
    public function setStatus(string $s): void { $this->status = $s; }
}

class Article {
    use HasStatus;
    // ✅ No conflict — Article does not also declare $status
}

$article = new Article();
echo "Default status: " . $article->getStatus() . "\n";
$article->setStatus('published');
echo "Updated status: " . $article->getStatus() . "\n";

/*
// ❌ Conflict — class re-declares $status with a different default
class BadArticle {
    use HasStatus;
    protected string $status = 'active'; // Different default — Fatal error!
    // Fatal error: BadArticle and HasStatus define the same property ($status)
    // in the composition of BadArticle. However, the definition differs
    // in its default value.
}
*/

echo "\n(See commented-out BadArticle for property conflict fatal error)\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 3 — Abstract Trait Methods
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 3: Abstract Trait Methods ──────────────────\n\n";

/**
 * This trait provides change-tracking, but it needs to know:
 *   1. What fields to track (getTrackableFields)
 *   2. What the record's identifier is (getIdentifier)
 *
 * Both are declared abstract — the host class MUST provide them.
 */
trait ChangeTracker {
    private array $originalValues = [];
    private array $dirtyFields    = [];

    /** @return string[] List of field names this model tracks for changes */
    abstract protected function getTrackableFields(): array;

    /** Return a string identifier for log messages (e.g. "User#42") */
    abstract protected function getIdentifier(): string;

    public function snapshot(array $currentValues): void {
        foreach ($this->getTrackableFields() as $field) {
            $this->originalValues[$field] = $currentValues[$field] ?? null;
        }
        $this->dirtyFields = [];
    }

    public function markDirty(string $field, mixed $newValue): void {
        $original = $this->originalValues[$field] ?? null;
        if ($original !== $newValue) {
            $this->dirtyFields[$field] = [
                'from' => $original,
                'to'   => $newValue,
            ];
        }
    }

    public function isDirty(): bool { return !empty($this->dirtyFields); }

    public function getChanges(): array { return $this->dirtyFields; }

    public function logChanges(): void {
        if (!$this->isDirty()) {
            echo "  [TRACK] {$this->getIdentifier()}: no changes.\n";
            return;
        }
        echo "  [TRACK] {$this->getIdentifier()} changed:\n";
        foreach ($this->dirtyFields as $field => $change) {
            $from = json_encode($change['from']);
            $to   = json_encode($change['to']);
            echo "    {$field}: {$from} → {$to}\n";
        }
    }
}

class InvoiceModel {
    use ChangeTracker;

    private int    $id;
    private float  $amount;
    private string $status;
    private string $recipient;

    public function __construct(int $id, float $amount, string $status, string $recipient) {
        $this->id        = $id;
        $this->amount    = $amount;
        $this->status    = $status;
        $this->recipient = $recipient;

        // Take a snapshot of initial state so we can track changes from here
        $this->snapshot($this->currentValues());
    }

    // ✅ Required by ChangeTracker — what fields to watch
    protected function getTrackableFields(): array {
        return ['amount', 'status', 'recipient'];
    }

    // ✅ Required by ChangeTracker — identifier for log messages
    protected function getIdentifier(): string {
        return "Invoice#{$this->id}";
    }

    public function updateAmount(float $amount): void {
        $this->markDirty('amount', $amount);
        $this->amount = $amount;
    }

    public function markPaid(): void {
        $this->markDirty('status', 'paid');
        $this->status = 'paid';
    }

    private function currentValues(): array {
        return [
            'amount'    => $this->amount,
            'status'    => $this->status,
            'recipient' => $this->recipient,
        ];
    }
}

$invoice = new InvoiceModel(1042, 1500.00, 'pending', 'alice@example.com');
echo "Fresh invoice:\n";
$invoice->logChanges();

$invoice->updateAmount(1750.00);
$invoice->markPaid();
echo "\nAfter updates:\n";
$invoice->logChanges();
echo "isDirty? " . ($invoice->isDirty() ? 'YES' : 'NO') . "\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 4 — Trait constants (PHP 8.2+)
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 4: Trait Constants (PHP 8.2+) ──────────────\n\n";

trait HasPriority {
    const PRIORITY_LOW    = 1;
    const PRIORITY_MEDIUM = 5;
    const PRIORITY_HIGH   = 10;

    private int $priority = self::PRIORITY_MEDIUM;

    public function setPriority(int $priority): void {
        if (!in_array($priority, [self::PRIORITY_LOW, self::PRIORITY_MEDIUM, self::PRIORITY_HIGH])) {
            throw new \InvalidArgumentException("Invalid priority: {$priority}");
        }
        $this->priority = $priority;
    }

    public function getPriority(): int     { return $this->priority; }
    public function isHighPriority(): bool { return $this->priority === self::PRIORITY_HIGH; }
}

class SupportTicket {
    use HasPriority;

    public function __construct(public readonly string $subject) {}
}

$ticket = new SupportTicket('Server is down');
echo "Default priority: {$ticket->getPriority()}\n";
$ticket->setPriority(SupportTicket::PRIORITY_HIGH);
echo "Updated priority: {$ticket->getPriority()}\n";
echo "High priority? " . ($ticket->isHighPriority() ? 'YES' : 'NO') . "\n";

echo "\n--- Recap ---\n";
echo "Trait properties: injected into host class — beware re-declaration conflicts.\n";
echo "Abstract in trait: host class MUST implement these methods.\n";
echo "Abstract in trait = trait expressing a dependency on the host class.\n";
echo "Trait constants (PHP 8.2+): accessible via ClassName::CONST or self::CONST.\n";