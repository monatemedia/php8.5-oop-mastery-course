<?php
declare(strict_types=1);

/**
 * Example 01 — The Problem Property Hooks Solve
 * ------------------------------------------------
 * PHP 8.5. Run: php examples/01-the-problem-they-solve.php
 *
 * This example shows the SAME class written twice:
 *   BEFORE — traditional getters/setters (verbose, repetitive)
 *   AFTER  — PHP 8.4 property hooks (concise, direct property access)
 *
 * The output of both versions is identical. The difference is entirely
 * in how much code you have to write and maintain.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  The Problem Property Hooks Solve (PHP 8.4)        ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ═══════════════════════════════════════════════════════════
// BEFORE — Traditional getter/setter pattern
// Six properties = twelve methods = lots of boilerplate
// ═══════════════════════════════════════════════════════════

echo "── BEFORE: Traditional getters/setters ──────────────\n\n";

class UserProfileBefore {
    private string $email      = '';
    private string $firstName  = '';
    private string $lastName   = '';
    private int    $age        = 0;
    private string $username   = '';

    // Getter 1
    public function getEmail(): string { return $this->email; }
    // Setter 1 — validation + normalisation
    public function setEmail(string $email): void {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email: {$email}");
        }
        $this->email = strtolower(trim($email));
    }

    // Getter 2
    public function getFirstName(): string { return $this->firstName; }
    // Setter 2 — trimming
    public function setFirstName(string $v): void {
        $this->firstName = trim($v);
    }

    // Getter 3
    public function getLastName(): string { return $this->lastName; }
    // Setter 3 — trimming
    public function setLastName(string $v): void {
        $this->lastName = trim($v);
    }

    // Getter 4
    public function getAge(): int { return $this->age; }
    // Setter 4 — validation
    public function setAge(int $age): void {
        if ($age < 0 || $age > 150) {
            throw new \InvalidArgumentException("Age must be 0-150, got {$age}");
        }
        $this->age = $age;
    }

    // Getter 5
    public function getUsername(): string { return $this->username; }
    // Setter 5 — normalisation
    public function setUsername(string $v): void {
        $this->username = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $v));
    }

    // Computed property — requires its own method (cannot be a property)
    public function getFullName(): string {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    // Display method
    public function display(): void {
        echo "  Email:     {$this->getEmail()}\n";
        echo "  Name:      {$this->getFullName()}\n";
        echo "  Age:       {$this->getAge()}\n";
        echo "  Username:  {$this->getUsername()}\n";
    }
}

$before = new UserProfileBefore();
$before->setEmail('  Alice@Example.COM  ');
$before->setFirstName('  Alice  ');
$before->setLastName('  Smith  ');
$before->setAge(30);
$before->setUsername('Alice Smith 2024!');
$before->display();

echo "\nLine count for UserProfileBefore: ~50 lines just for 5 properties.\n";
echo "Every property needs get + set = 10 methods + computed = 11 total.\n";


// ═══════════════════════════════════════════════════════════
// AFTER — PHP 8.4 Property Hooks
// Same logic, less code, direct property access syntax
// ═══════════════════════════════════════════════════════════

echo "\n── AFTER: PHP 8.4 Property Hooks ───────────────────\n\n";

class UserProfileAfter {
    // Property with get + set hooks — validation and normalisation inline
    public string $email = '' {
        set(string $value) {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException("Invalid email: {$value}");
            }
            $this->email = strtolower(trim($value));
        }
    }

    // Set-only hook — trim on write, raw read
    public string $firstName = '' {
        set(string $v) => $this->firstName = trim($v);
    }

    public string $lastName = '' {
        set(string $v) => $this->lastName = trim($v);
    }

    // Both hooks — validate on set, read directly
    public int $age = 0 {
        set(int $value) {
            if ($value < 0 || $value > 150) {
                throw new \InvalidArgumentException("Age must be 0-150, got {$value}");
            }
            $this->age = $value;
        }
    }

    // Set hook normalises the username
    public string $username = '' {
        set(string $v) => $this->username = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $v));
    }

    // Virtual property — computed from other properties, no storage
    public string $fullName {
        get => trim($this->firstName . ' ' . $this->lastName);
    }

    public function display(): void {
        // Direct property access — no get() calls needed
        echo "  Email:     {$this->email}\n";
        echo "  Name:      {$this->fullName}\n";
        echo "  Age:       {$this->age}\n";
        echo "  Username:  {$this->username}\n";
    }
}

$after = new UserProfileAfter();
// Direct assignment — hooks run transparently
$after->email     = '  Alice@Example.COM  ';
$after->firstName = '  Alice  ';
$after->lastName  = '  Smith  ';
$after->age       = 30;
$after->username  = 'Alice Smith 2024!';
$after->display();

echo "\nLine count for UserProfileAfter: ~30 lines for the same 5 properties.\n";
echo "fullName is a virtual property — no method needed, accessed as \$user->fullName.\n";


// ═══════════════════════════════════════════════════════════
// Side-by-side: API comparison
// ═══════════════════════════════════════════════════════════

echo "\n── API comparison ────────────────────────────────────\n\n";

echo "BEFORE (getter/setter):\n";
echo "  \$user->setEmail('alice@example.com');\n";
echo "  echo \$user->getEmail();\n";
echo "  echo \$user->getFullName();\n\n";

echo "AFTER (property hooks):\n";
echo "  \$user->email = 'alice@example.com';  ← hook runs transparently\n";
echo "  echo \$user->email;                    ← hook runs transparently\n";
echo "  echo \$user->fullName;                 ← virtual property, computed\n\n";

echo "Both enforce the same rules. The hook version:\n";
echo "  ✓ Less code to write and read\n";
echo "  ✓ Properties feel like properties (direct access)\n";
echo "  ✓ Virtual properties replace computed getter methods\n";
echo "  ✓ Validation and transformation are co-located with the property\n";


// ═══════════════════════════════════════════════════════════
// Validation still works — hooks throw just like setters did
// ═══════════════════════════════════════════════════════════

echo "\n── Validation still works ───────────────────────────\n\n";

$user = new UserProfileAfter();

try {
    $user->email = 'not-an-email';
} catch (\InvalidArgumentException $e) {
    echo "Email validation: " . $e->getMessage() . "\n";
}

try {
    $user->age = 200;
} catch (\InvalidArgumentException $e) {
    echo "Age validation: " . $e->getMessage() . "\n";
}

try {
    // Virtual property — cannot be assigned
    $user->fullName = 'Direct Assignment'; // Fatal error
} catch (\Error $e) {
    echo "Virtual property: " . $e->getMessage() . "\n";
}

echo "\n--- Recap ---\n";
echo "Hooks replace getter/setter boilerplate with inline get{} and set{} blocks.\n";
echo "Properties are still accessed directly — \$obj->prop, not \$obj->getProp().\n";
echo "Virtual properties (get only, no default) replace computed getter methods.\n";
echo "Validation and transformation are co-located with the property declaration.\n";