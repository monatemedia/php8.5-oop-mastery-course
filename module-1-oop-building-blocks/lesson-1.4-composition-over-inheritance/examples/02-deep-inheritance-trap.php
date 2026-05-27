<?php
declare(strict_types=1);

/**
 * Example 02 — The Deep Inheritance Trap
 * ----------------------------------------
 * This example builds a realistic five-level inheritance chain,
 * then demonstrates each of the four problems it creates:
 *
 *   Problem 1: Fragile base class — change a parent, break every child
 *   Problem 2: LSP violations become inevitable
 *   Problem 3: Constructor coupling chains (useless params forced through)
 *   Problem 4: Impossible to test in isolation
 *
 * Then it shows the same system refactored to avoid the trap.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  The Deep Inheritance Trap                          ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 1 — The five-level chain (the trap)
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 1: The five-level chain ─────────────────────\n\n";

/**
 * Level 1 — AbstractEntity
 * Requires a database connection just to exist.
 */
abstract class AbstractEntity {
    protected array $attributes = [];

    public function __construct(
        protected string $tableName,
        protected array  $dbConnection  // ← Every subclass must pass this down
    ) {
        echo "  [AbstractEntity] Initialised with table={$tableName}\n";
    }

    protected function persist(): bool {
        echo "  [AbstractEntity] Persisting to {$this->tableName}\n";
        return true;
    }

    abstract public function validate(): bool;
}

/**
 * Level 2 — BaseModel
 * Adds timestamp tracking.
 */
abstract class BaseModel extends AbstractEntity {
    protected string $createdAt;
    protected string $updatedAt;

    public function __construct(string $tableName, array $dbConnection) {
        parent::__construct($tableName, $dbConnection); // must pass db up
        $this->createdAt = date('Y-m-d H:i:s');
        $this->updatedAt = date('Y-m-d H:i:s');
        echo "  [BaseModel] Timestamps set\n";
    }

    public function touch(): void {
        $this->updatedAt = date('Y-m-d H:i:s');
    }
}

/**
 * Level 3 — AuditableModel
 * Adds change tracking.
 */
abstract class AuditableModel extends BaseModel {
    protected array $changeLog = [];

    public function __construct(string $tableName, array $dbConnection) {
        parent::__construct($tableName, $dbConnection);
        echo "  [AuditableModel] Change log ready\n";
    }

    protected function recordChange(string $field, mixed $oldVal, mixed $newVal): void {
        $this->changeLog[] = compact('field', 'oldVal', 'newVal');
    }
}

/**
 * Level 4 — UserModel
 * The actual User entity.
 */
class UserModel extends AuditableModel {
    private string $email = '';
    private string $role  = 'user';

    public function __construct(array $dbConnection) {
        parent::__construct('users', $dbConnection); // still carrying db down
        echo "  [UserModel] Ready\n";
    }

    public function setEmail(string $email): void {
        $this->recordChange('email', $this->email, $email);
        $this->email = $email;
        $this->touch();
    }

    public function validate(): bool {
        return filter_var($this->email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

/**
 * Level 5 — AdminUserModel
 * Adds admin permissions.
 */
class AdminUserModel extends UserModel {
    private array $permissions = [];

    public function __construct(array $dbConnection) {
        parent::__construct($dbConnection); // STILL carrying the db param
        echo "  [AdminUserModel] Admin user ready\n";
    }

    public function grantPermission(string $perm): void {
        $this->permissions[] = $perm;
    }

    // ❌ PROBLEM 2 (LSP): AdminUserModel must validate differently
    // This weakens the postcondition — a violation of LSP
    public function validate(): bool {
        return true; // Admins always valid — regardless of email
    }
}

echo "Creating AdminUserModel (watch the constructor chain):\n";
$fakeDb = ['host' => 'localhost', 'db' => 'app'];
$admin = new AdminUserModel($fakeDb);
$admin->setEmail('admin@example.com');
echo "\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 2 — Demonstrating the four problems
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 2: The four problems ────────────────────────\n\n";

echo "PROBLEM 1 — Fragile base class:\n";
echo "  AbstractEntity.__construct() requires \$dbConnection.\n";
echo "  To add a \$logger parameter to AbstractEntity, you must update:\n";
echo "    BaseModel.__construct()\n";
echo "    AuditableModel.__construct()\n";
echo "    UserModel.__construct()\n";
echo "    AdminUserModel.__construct()\n";
echo "  That is 4 files changed for one addition to the base class.\n\n";

echo "PROBLEM 2 — LSP violation:\n";
echo "  AbstractEntity::validate() is a contract: 'returns true only when valid'.\n";
echo "  AdminUserModel::validate() always returns true — weakens the postcondition.\n";
echo "  Code that does: if (\$entity->validate()) persist()  — breaks for AdminUser.\n\n";

echo "PROBLEM 3 — Constructor coupling chain:\n";
echo "  AdminUserModel has NO use for \$dbConnection directly.\n";
echo "  It passes it through five constructor levels just because AbstractEntity needs it.\n";
echo "  If AbstractEntity changes its constructor signature → all five levels must update.\n\n";

echo "PROBLEM 4 — Impossible to test in isolation:\n";
echo "  To test AdminUserModel::grantPermission():\n";
echo "    → Must construct AdminUserModel\n";
echo "    → Which calls UserModel.__construct() → AuditableModel → BaseModel → AbstractEntity\n";
echo "    → AbstractEntity sets up DB, timestamps, audit log...\n";
echo "    → None of that is needed to test grantPermission()\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 3 — The refactored version: composition eliminates all four problems
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 3: Refactored — composition ─────────────────\n\n";

// Interfaces — each capability declared as a contract
interface Validatable {
    public function validate(): bool;
}

interface Persistable {
    public function save(): bool;
}

interface Auditable2 {
    public function getChangeLog(): array;
}

// Behaviours extracted to focused, injectable collaborators
class TimestampTracker {
    private string $createdAt;
    private string $updatedAt;

    public function __construct() {
        $now             = date('Y-m-d H:i:s');
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function touch(): void        { $this->updatedAt = date('Y-m-d H:i:s'); }
    public function getCreatedAt(): string { return $this->createdAt; }
    public function getUpdatedAt(): string { return $this->updatedAt; }
}

class ChangeTracker {
    private array $log = [];

    public function record(string $field, mixed $old, mixed $new): void {
        $this->log[] = compact('field', 'old', 'new');
    }

    public function getLog(): array { return $this->log; }
}

// ✅ UserEntity — no parent class, composes its collaborators
class UserEntity implements Validatable, Auditable2 {
    private string $email = '';
    private string $role  = 'user';

    public function __construct(
        private TimestampTracker $timestamps,  // injected
        private ChangeTracker    $changes      // injected
    ) {}

    public function setEmail(string $email): void {
        $this->changes->record('email', $this->email, $email);
        $this->timestamps->touch();
        $this->email = $email;
    }

    public function getEmail(): string { return $this->email; }

    public function validate(): bool {
        return filter_var($this->email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function getChangeLog(): array { return $this->changes->getLog(); }
    public function getUpdatedAt(): string { return $this->timestamps->getUpdatedAt(); }
}

// ✅ AdminUserEntity — no deep hierarchy
//    Has a UserEntity as a collaborator, adds its own behaviour
class AdminUserEntity implements Validatable {
    private array $permissions = [];

    public function __construct(
        private UserEntity $user  // HAS a user, not IS a user
    ) {}

    public function grantPermission(string $perm): void {
        $this->permissions[] = $perm;
    }

    public function getPermissions(): array { return $this->permissions; }
    public function getEmail(): string      { return $this->user->getEmail(); }

    // AdminUserEntity has its OWN validation — no LSP concern
    public function validate(): bool {
        return true; // Admins always valid
    }

    // Still honours the UserEntity's validate contract through delegation
    public function validateUserData(): bool {
        return $this->user->validate();
    }
}

echo "Creating UserEntity (no chain, no DB, no timestamps baked in):\n";
$timestamps = new TimestampTracker();
$changes    = new ChangeTracker();
$user       = new UserEntity($timestamps, $changes);
$user->setEmail('admin@example.com');

echo "  email: {$user->getEmail()}\n";
echo "  valid: " . ($user->validate() ? 'YES' : 'NO') . "\n";
echo "  changes: " . count($user->getChangeLog()) . "\n\n";

echo "Creating AdminUserEntity (delegates to UserEntity, adds permissions):\n";
$adminTimestamps = new TimestampTracker();
$adminChanges    = new ChangeTracker();
$adminUser       = new AdminUserEntity(new UserEntity($adminTimestamps, $adminChanges));
$adminUser->grantPermission('manage_users');
$adminUser->grantPermission('delete_posts');
echo "  permissions: " . implode(', ', $adminUser->getPermissions()) . "\n\n";

echo "Testing AdminUserEntity::grantPermission() in isolation:\n";
$isolated = new AdminUserEntity(
    new UserEntity(new TimestampTracker(), new ChangeTracker())
);
$isolated->grantPermission('test_perm');
echo "  ✓ No database, no abstract hierarchy, no parent constructors\n";
echo "  ✓ Test takes milliseconds — no infrastructure\n";

echo "\n--- Recap ---\n";
echo "Five-level chain: fragile base, LSP violations, constructor chains, untestable.\n";
echo "Composition fix: collaborators injected, no parent class, each piece testable alone.\n";
echo "Relationship: AdminUserEntity HAS a UserEntity — not 'is a subtype of UserEntity'.\n";
echo "Rule: if the chain is 3+ levels deep, it is almost certainly a design smell.\n";