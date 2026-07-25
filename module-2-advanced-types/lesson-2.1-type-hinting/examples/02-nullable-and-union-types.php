<?php
declare(strict_types=1);

/**
 * Example 02 — Nullable Types and Union Types
 * ---------------------------------------------
 * Nullable (?Type):  accepts the declared type OR null
 * Union (A|B|C):     accepts any one of the listed types
 *
 * Both are essential for modelling real-world APIs where values are not
 * always guaranteed to exist or always the same shape.
 *
 * Scenario: A user profile system with optional fields and a flexible
 * search function that accepts multiple identifier types.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Nullable Types and Union Types                     ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 1 — Nullable types: ?Type
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 1: Nullable Types (?Type) ───────────────────\n\n";

class UserProfile {
    public function __construct(
        private int     $id,
        private string  $email,
        private ?string $displayName,   // Optional — may be null
        private ?string $avatarUrl,     // Optional — may be null
        private ?int    $age            // Optional — may be null
    ) {}

    public function getId(): int          { return $this->id; }
    public function getEmail(): string    { return $this->email; }
    public function getDisplayName(): ?string { return $this->displayName; }
    public function getAvatarUrl(): ?string   { return $this->avatarUrl; }
    public function getAge(): ?int            { return $this->age; }

    // Nullable parameter: pass null to clear the display name
    public function setDisplayName(?string $name): void {
        $this->displayName = $name;
    }

    public function getLabel(): string {
        // Nullsafe operator (??) handles nullable values cleanly
        return $this->displayName ?? $this->email;
    }

    public function getSummary(): string {
        $age    = $this->age    !== null ? "age {$this->age}" : "age unknown";
        $avatar = $this->avatarUrl !== null ? "has avatar" : "no avatar";
        return "{$this->getLabel()} | {$age} | {$avatar}";
    }
}

// Fully populated profile
$alice = new UserProfile(1, 'alice@example.com', 'Alice Smith', 'https://cdn.example.com/alice.jpg', 30);
echo "Full profile: " . $alice->getSummary() . "\n";

// Profile with nulls — valid and common
$bob = new UserProfile(2, 'bob@example.com', null, null, null);
echo "Minimal profile: " . $bob->getSummary() . "\n";

// Setting a nullable field
$bob->setDisplayName('Bobby B');
echo "After setDisplayName: " . $bob->getLabel() . "\n";

$bob->setDisplayName(null); // Clearing it — null is a valid argument
echo "After clearing: " . $bob->getLabel() . "\n";


// ─────────────────────────────────────────────────────────────────────────────
// Nullable return types — ?Type in practice
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Nullable return: finding records ─────────────────\n\n";

class UserRepository {
    /** @var array<int, UserProfile> */
    private array $users;

    public function __construct() {
        // NOTE: these cannot be a property default. PHP's "new in initializers"
        // (8.1) covers parameter defaults, static variables, global constants and
        // attribute arguments — but NOT class property defaults. Writing
        //     private array $users = [1 => new UserProfile(...)];
        // is a fatal "New expressions are not supported in this context" on every
        // PHP version, 8.5 included. Object graphs get built in the constructor.
        $this->users = [
            1 => new UserProfile(1, 'alice@example.com', 'Alice', null, 30),
            2 => new UserProfile(2, 'bob@example.com',   'Bob',   null, 25),
        ];
    }

    // Returns UserProfile if found, null if not — honest about the possibility
    public function findById(int $id): ?UserProfile {
        return $this->users[$id] ?? null;
    }

    // Returns the email string or null if user not found
    public function getEmailById(int $id): ?string {
        return $this->findById($id)?->getEmail(); // Nullsafe chaining
    }
}

$repo = new UserRepository();

$user = $repo->findById(1);
if ($user !== null) {
    echo "Found: " . $user->getSummary() . "\n";
}

$user = $repo->findById(99);
echo "findById(99): " . var_export($user, true) . "\n";

// Nullsafe operator — no null check needed
echo "Email for 2: "  . ($repo->getEmailById(2)  ?? 'not found') . "\n";
echo "Email for 99: " . ($repo->getEmailById(99) ?? 'not found') . "\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 2 — Union types: A|B
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 2: Union Types (A|B) ────────────────────────\n\n";

// A function that genuinely accepts two different types
function formatId(int|string $id): string {
    return is_int($id)
        ? str_pad((string) $id, 6, '0', STR_PAD_LEFT)
        : strtoupper(trim($id));
}

echo formatId(42)        . "\n"; // 000042
echo formatId('abc-001') . "\n"; // ABC-001
echo formatId(1000)      . "\n"; // 001000

// Union return type
function parseNumber(string $input): int|float {
    if (str_contains($input, '.')) {
        return (float) $input;
    }
    return (int) $input;
}

$results = ['42', '3.14', '100', '2.718'];
foreach ($results as $input) {
    $parsed = parseNumber($input);
    echo "  '{$input}' → " . gettype($parsed) . "({$parsed})\n";
}


// ─────────────────────────────────────────────────────────────────────────────
// Union with null — int|null is identical to ?int
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Union with null: int|null vs ?int ────────────────\n\n";

function getScore(): int|null  { return null; } // Same as ?int
function getLevel(): ?int      { return null; } // Same as int|null

echo "int|null and ?int are equivalent in PHP 8+.\n";
echo "Use ?int for simple nullable — use int|null when part of a longer union.\n";

// Longer union with null — cannot use ? shorthand here
function getIdentifier(): int|string|null {
    return null; // Could be int, string, or null
}

echo "int|string|null is the correct form for 3-way union with null.\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 3 — Practical union types in a real class
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 3: Union types in a real context ────────────\n\n";

class CacheStore {
    private array $data = [];

    // Accepts string keys or integer keys — both are valid cache identifiers
    public function set(string|int $key, mixed $value, int $ttl = 3600): void {
        $this->data[$key] = [
            'value'   => $value,
            'expires' => time() + $ttl,
        ];
        echo "[CACHE SET] key={$key} ttl={$ttl}s\n";
    }

    // Returns the cached value or null if not found/expired
    public function get(string|int $key): mixed {
        if (!isset($this->data[$key])) {
            return null;
        }
        if ($this->data[$key]['expires'] < time()) {
            unset($this->data[$key]);
            return null;
        }
        return $this->data[$key]['value'];
    }

    // Returns how many items are in the cache — always int
    public function count(): int {
        return count($this->data);
    }
}

$cache = new CacheStore();
$cache->set('user:1', ['name' => 'Alice', 'role' => 'admin']);
$cache->set(42, 'some-data');
$cache->set('config', ['debug' => false], ttl: 60);

echo "Cached items: " . $cache->count() . "\n";
echo "user:1 → " . json_encode($cache->get('user:1')) . "\n";
echo "42 → "     . var_export($cache->get(42), true)   . "\n";
echo "missing → " . var_export($cache->get('missing'), true) . "\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 4 — Type narrowing with is_* inside union-typed functions
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 4: Type narrowing inside union functions ────\n\n";

function describeInput(int|float|string|bool|null $value): string {
    // PHP does not know which type it is — use is_* to narrow
    if ($value === null)        return "null";
    if (is_bool($value))        return "bool(" . ($value ? 'true' : 'false') . ")";
    if (is_int($value))         return "int({$value})";
    if (is_float($value))       return "float({$value})";
    if (is_string($value))      return "string(\"{$value}\")";
    return "unknown";
}

$inputs = [null, true, false, 42, 3.14, 'hello', 0, ''];
foreach ($inputs as $input) {
    echo "  " . describeInput($input) . "\n";
}

echo "\n--- Recap ---\n";
echo "?Type:          accepts Type or null — shorthand for Type|null.\n";
echo "A|B:            accepts either type — use is_*() to narrow inside the function.\n";
echo "int|string|null: longer union with null — cannot use ? shorthand.\n";
echo "Null return:    signal 'not found' clearly — callers must check for null.\n";
echo "?? operator:    the cleanest way to provide a fallback for nullable values.\n";