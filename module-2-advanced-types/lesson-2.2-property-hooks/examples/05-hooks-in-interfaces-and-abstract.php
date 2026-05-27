<?php
declare(strict_types=1);

/**
 * Example 05 — Hooks in Interfaces and Abstract Classes
 * -------------------------------------------------------
 * PHP 8.5.
 *
 * Interfaces can declare PROPERTY REQUIREMENTS using hook syntax.
 * Abstract classes can declare ABSTRACT HOOK properties that subclasses must implement.
 *
 * Interface property syntax:
 *   { get; }        — readable property (read-only contract)
 *   { get; set; }   — readable AND writable property
 *   { set; }        — writable-only property (rare)
 *
 * Abstract class syntax:
 *   abstract public Type $prop { get; }   — subclass must provide a get hook
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Hooks in Interfaces and Abstract Classes (PHP 8.4)║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 1 — Hooks in Interfaces
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 1: Property contracts in interfaces ─────────\n\n";

// Interface declares what property access is required
interface HasName {
    public string $name { get; }          // Must be readable
}

interface HasEmail {
    public string $email { get; set; }    // Must be readable AND writable
}

interface HasSlug {
    public string $slug { get; }          // Must be readable (typically virtual)
}

// Composite interface
interface Entity extends HasName, HasEmail {
    public int $id { get; }              // Read-only ID
}


// Implementation 1: uses hooked properties to satisfy the interface
class UserEntity implements Entity, HasSlug {
    public int $id;

    public string $name = '' {
        set(string $v) => $this->name = trim($v);
    }

    public string $email = '' {
        set(string $v) => $this->email = strtolower(trim($v));
    }

    // Virtual — satisfies HasSlug's { get; } requirement
    public string $slug {
        get => strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($this->name)));
    }

    public function __construct(int $id, string $name, string $email) {
        $this->id    = $id;
        $this->name  = $name;
        $this->email = $email;
    }
}

// Implementation 2: a simple implementation with plain (no-hook) properties
class GuestEntity implements Entity {
    public int $id;

    // Plain properties also satisfy { get; } and { get; set; } requirements
    // because they are naturally readable and writable
    public string $name;
    public string $email;

    public function __construct(int $id, string $name, string $email) {
        $this->id    = $id;
        $this->name  = $name;
        $this->email = $email;
    }
}

// Both satisfy the Entity interface
function printEntity(Entity $entity): void {
    echo "  ID:    {$entity->id}\n";
    echo "  Name:  {$entity->name}\n";
    echo "  Email: {$entity->email}\n";
}

$user  = new UserEntity(1, '  Alice Smith  ', '  ALICE@EXAMPLE.COM  ');
$guest = new GuestEntity(2, 'Bob Jones', 'bob@example.com');

echo "UserEntity:\n";
printEntity($user);
echo "  Slug:  {$user->slug}\n";

echo "\nGuestEntity:\n";
printEntity($guest);

// Interface ensures the email is writable (get; set;)
$user->email  = 'ALICE-NEW@EXAMPLE.COM'; // Hooked — normalised
$guest->email = 'bob-new@example.com';   // Plain property

echo "\nAfter email update:\n";
echo "  User email:  {$user->email}\n";
echo "  Guest email: {$guest->email}\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 2 — Read-only contract via { get; }
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 2: Read-only contracts ─────────────────────\n\n";

interface Identifiable {
    public string $uuid { get; }   // Consumers can READ uuid — cannot write it
}

class Document implements Identifiable {
    // uuid is writable internally (the class can set it)
    // but the interface only promises readability to external consumers
    public string $uuid {
        get => $this->uuid;
        set(string $v) {
            if (!preg_match('/^[0-9a-f-]{36}$/', $v)) {
                throw new \InvalidArgumentException("Invalid UUID format.");
            }
            $this->uuid = $v;
        }
    }

    public string $title;

    public function __construct(string $title) {
        $this->uuid  = $this->generateUuid();
        $this->title = $title;
    }

    private function generateUuid(): string {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

function logDocument(Identifiable $doc): void {
    echo "  UUID: {$doc->uuid}\n";
    // $doc->uuid = 'something'; // Would be a violation — { get; } means read-only contract
}

$doc = new Document('PHP 8.4 Release Notes');
echo "Document:\n";
logDocument($doc);
echo "  Title: {$doc->title}\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 3 — Abstract hook properties in abstract classes
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 3: Abstract hooks in abstract classes ───────\n\n";

abstract class BaseModel {
    // Concrete backed property — shared across all subclasses
    public string $id = '' {
        set(string $value) {
            if (empty(trim($value))) {
                throw new \InvalidArgumentException("ID cannot be empty.");
            }
            $this->id = trim($value);
        }
    }

    public \DateTimeImmutable $createdAt;

    public function __construct(string $id) {
        $this->id        = $id;
        $this->createdAt = new \DateTimeImmutable();
    }

    // Abstract get hook — every subclass MUST provide this
    abstract public string $label { get; }

    // Abstract read-write — every subclass must provide both hooks or a plain property
    abstract public string $status { get; set; }

    // Concrete virtual property — uses the abstract $label
    public string $summary {
        get => "[{$this->id}] {$this->label} ({$this->status})";
    }
}

class InvoiceModel extends BaseModel {
    public string $customerName;
    public float  $amount;

    public function __construct(string $id, string $customerName, float $amount) {
        parent::__construct($id);
        $this->customerName = $customerName;
        $this->amount       = $amount;
    }

    // Fulfils abstract label requirement
    public string $label {
        get => "Invoice for {$this->customerName} — R" . number_format($this->amount, 2);
    }

    // Fulfils abstract status requirement — with validation
    public string $status = 'draft' {
        set(string $v) {
            $allowed = ['draft', 'sent', 'paid', 'overdue'];
            if (!in_array($v, $allowed, true)) {
                throw new \InvalidArgumentException("Invalid invoice status: {$v}");
            }
            $this->status = $v;
        }
    }
}

class TaskModel extends BaseModel {
    public function __construct(
        string $id,
        private string $title,
        private string $assignee
    ) {
        parent::__construct($id);
    }

    public string $label {
        get => "Task: {$this->title} (@{$this->assignee})";
    }

    public string $status = 'todo' {
        set(string $v) {
            $allowed = ['todo', 'in_progress', 'done', 'blocked'];
            if (!in_array($v, $allowed, true)) {
                throw new \InvalidArgumentException("Invalid task status: {$v}");
            }
            $this->status = $v;
        }
    }
}

function printModel(BaseModel $model): void {
    echo "  summary: {$model->summary}\n";
    echo "  id:      {$model->id}\n";
    echo "  status:  {$model->status}\n";
    echo "  created: " . $model->createdAt->format('H:i:s') . "\n";
}

$invoice = new InvoiceModel('INV-001', 'Alice Corp', 4500.00);
$invoice->status = 'sent';

$task = new TaskModel('TASK-007', 'Write unit tests', 'bob');
$task->status = 'in_progress';

echo "Invoice:\n";
printModel($invoice);

echo "\nTask:\n";
printModel($task);

// Validation still works
try {
    $invoice->status = 'rejected'; // Not in allowed list
} catch (\InvalidArgumentException $e) {
    echo "\nStatus error: " . $e->getMessage() . "\n";
}


// ─────────────────────────────────────────────────────────────────────────────
// PART 4 — Summary of interface and abstract hook rules
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 4: Rules summary ────────────────────────────\n\n";

echo "In an INTERFACE:\n";
echo "  { get; }      → implementing class must make this property readable\n";
echo "  { get; set; } → implementing class must make this property readable AND writable\n";
echo "  { set; }      → implementing class must make this property writable (rare)\n";
echo "  A plain property satisfies any hook requirement it covers.\n\n";

echo "In an ABSTRACT CLASS:\n";
echo "  abstract public Type \$prop { get; }       → subclass must provide get hook\n";
echo "  abstract public Type \$prop { get; set; }  → subclass must provide both\n";
echo "  Concrete hooks in abstract classes are inherited (shared implementation).\n\n";

echo "Key insight:\n";
echo "  { get; } in an interface = 'this property must be READABLE by callers'\n";
echo "  It does NOT mean the property is read-only inside the class itself.\n";
echo "  The class can still write to it internally via a set hook.\n";

echo "\n--- Recap ---\n";
echo "Interface { get; }:     read-only contract for callers.\n";
echo "Interface { get; set; }: read-write contract for callers.\n";
echo "Plain properties satisfy interface hook requirements naturally.\n";
echo "Abstract hook in abstract class: subclass MUST provide the required hooks.\n";
echo "Concrete hooks in abstract class: shared implementation, no override needed.\n";