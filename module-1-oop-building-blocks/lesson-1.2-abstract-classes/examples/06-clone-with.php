<?php
declare(strict_types=1);

/**
 * Example 06 — clone with (PHP 8.5)
 * ------------------------------------
 * PHP 8.5 introduces the `clone with` syntax for producing immutable copies
 * of objects with targeted property changes in a single expression.
 *
 * Before PHP 8.5, immutable "wither" methods required manually listing every
 * property in a `new static(...)` call — verbose and fragile when properties
 * are added or removed.
 *
 * PHP 8.5+ required for this file.
 *
 * Three scenarios:
 *   A. Value objects with readonly properties
 *   B. Objects with PHP 8.4 property hooks
 *   C. Pairing clone with with #[NoDiscard] (PHP 8.5)
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  clone with Syntax (PHP 8.5)                        ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 1 — The problem before PHP 8.5
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 1: Before PHP 8.5 — verbose wither methods ──\n\n";

readonly class MoneyOld {
    public function __construct(
        public int    $amountCents,
        public string $currency,
        public string $locale = 'en-ZA'
    ) {}

    // Must list EVERY property — fragile when the class changes
    public function withAmount(int $newAmount): static {
        return new static($newAmount, $this->currency, $this->locale);
        //                 ^^^^^^^^^^  ^^^^^^^^^^^^^   ^^^^^^^^^^^
        // As properties are added, every withX() method must be updated.
        // Forget one → silent bug (wrong default used).
    }

    public function withCurrency(string $currency): static {
        return new static($this->amountCents, $currency, $this->locale);
    }

    public function withLocale(string $locale): static {
        return new static($this->amountCents, $this->currency, $locale);
    }

    public function format(): string {
        return $this->currency . ' ' . number_format($this->amountCents / 100, 2);
    }
}

$price    = new MoneyOld(29999, 'ZAR');
$adjusted = $price->withAmount(24999);
$usd      = $price->withCurrency('USD');

echo "Original: " . $price->format() . "\n";
echo "Adjusted: " . $adjusted->format() . "\n";
echo "USD:      " . $usd->format() . "\n";
echo "Original unchanged: " . $price->format() . "\n\n";

echo "Problem: withAmount() must list ALL three properties.\n";
echo "Add a 4th property → must update every wither method manually.\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 2 — PHP 8.5: clone with
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 2: PHP 8.5 — clone with ─────────────────────\n\n";

readonly class Money {
    public function __construct(
        public int    $amountCents,
        public string $currency,
        public string $locale    = 'en-ZA',
        public string $precision = 'standard' // New property added — wither methods unaffected
    ) {}

    // Only the CHANGED property appears in the with array
    #[\NoDiscard('Returns a new Money instance — the original is unchanged')]
    public function withAmount(int $newAmount): static {
        return clone $this with ['amountCents' => $newAmount];
        // All other properties (currency, locale, precision) are carried over automatically
    }

    #[\NoDiscard('Returns a new Money instance — the original is unchanged')]
    public function withCurrency(string $currency): static {
        return clone $this with ['currency' => $currency];
    }

    #[\NoDiscard('Returns a new Money instance — the original is unchanged')]
    public function withLocale(string $locale): static {
        return clone $this with ['locale' => $locale];
    }

    // Change MULTIPLE properties at once
    #[\NoDiscard('Returns a new Money instance — the original is unchanged')]
    public function withAmountAndCurrency(int $amountCents, string $currency): static {
        return clone $this with ['amountCents' => $amountCents, 'currency' => $currency];
    }

    public function format(): string {
        return $this->currency . ' ' . number_format($this->amountCents / 100, 2);
    }
}

$price    = new Money(29999, 'ZAR');
$adjusted = $price->withAmount(24999);
$usd      = $price->withCurrency('USD');
$both     = $price->withAmountAndCurrency(19999, 'EUR');

echo "Original: " . $price->format() . " (precision={$price->precision})\n";
echo "Adjusted: " . $adjusted->format() . " (precision={$adjusted->precision})\n";
echo "USD:      " . $usd->format() . "\n";
echo "Both:     " . $both->format() . "\n";
echo "Original unchanged: " . $price->format() . "\n\n";

echo "Key improvement: adding 'precision' property did NOT require updating\n";
echo "withAmount() or withCurrency() — they carry it over automatically.\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 3 — clone with on non-readonly classes
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 3: clone with on non-readonly classes ────────\n\n";

// clone with works on any class, not just readonly
class UserProfile {
    public function __construct(
        public string  $name,
        public string  $email,
        public string  $role      = 'member',
        public ?string $avatarUrl = null
    ) {}

    #[\NoDiscard('Returns a new UserProfile — the original is unchanged')]
    public function withRole(string $role): static {
        return clone $this with ['role' => $role];
    }

    #[\NoDiscard('Returns a new UserProfile — the original is unchanged')]
    public function withAvatar(string $url): static {
        return clone $this with ['avatarUrl' => $url];
    }

    public function summary(): string {
        $avatar = $this->avatarUrl ? "has avatar" : "no avatar";
        return "{$this->name} | {$this->role} | {$avatar}";
    }
}

$user  = new UserProfile('Alice', 'alice@example.com');
$admin = $user->withRole('admin');
$full  = $admin->withAvatar('https://cdn.example.com/alice.jpg');

echo "Original: " . $user->summary()  . "\n";
echo "Admin:    " . $admin->summary() . "\n";
echo "Full:     " . $full->summary()  . "\n";
echo "Original unchanged: " . $user->summary() . "\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 4 — clone with on classes with PHP 8.4 property hooks
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 4: clone with + PHP 8.4 property hooks ──────\n\n";

// When cloning a property with a set hook, the hook runs on the cloned value
class BlogPost {
    public string $title = '' {
        set(string $value) => $this->title = trim($value);
    }

    public string $author = '' {
        set(string $value) => $this->author = ucwords(strtolower(trim($value)));
    }

    // Virtual property — derived from title, no storage
    public string $slug {
        get {
            return strtolower(
                trim(preg_replace('/[^A-Za-z0-9]+/', '-', $this->title), '-')
            );
        }
    }

    public function __construct(string $title, string $author) {
        $this->title  = $title;   // set hook runs
        $this->author = $author;  // set hook runs
    }
}

$post    = new BlogPost('  Hello PHP World  ', '  alice smith  ');
// clone with — the set hook normalises the new title automatically
$updated = clone $post with ['title' => '  PHP 8.5 Is Here  '];

echo "Original title:  '{$post->title}'\n";
echo "Original slug:    {$post->slug}\n";
echo "Original author: '{$post->author}'\n\n";
echo "Updated title:   '{$updated->title}'\n";
echo "Updated slug:     {$updated->slug}\n";    // Virtual — re-computed from new title
echo "Updated author:  '{$updated->author}'\n"; // Carried over from original\n\n";

echo "Note: The set hook ran on the cloned title — it was normalised.\n";
echo "Note: The virtual slug was re-computed — it reflects the new title.\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 5 — #[NoDiscard] catches the silent discard mistake
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 5: #[NoDiscard] catches the silent mistake ──\n\n";

echo "The most common mistake with immutable objects:\n\n";
echo "  \$price = new Money(29999, 'ZAR');\n";
echo "  \$price->withAmount(24999); // ← return value discarded — \$price unchanged!\n";
echo "  echo \$price->format();     // Still R299.99 — silent bug\n\n";

echo "With #[NoDiscard] on withAmount():\n";
echo "  PHP 8.5 emits: Warning: Return value of Money::withAmount() should not be discarded\n\n";

// Correct usage — capture the return value
$price    = new Money(29999, 'ZAR');
$adjusted = $price->withAmount(24999); // ✅ captured
echo "Correct: " . $adjusted->format() . "\n";

// The incorrect call is commented out — it would trigger the NoDiscard warning:
// $price->withAmount(19999); // ← would emit warning in PHP 8.5

echo "\n--- Recap ---\n";
echo "clone with:     clone \$this with ['property' => \$newValue]\n";
echo "Multiple props: clone \$this with ['a' => 1, 'b' => 2]\n";
echo "Set hooks run:  the hook normalises the new value during cloning.\n";
echo "Virtual props:  re-computed on the clone (not in the with array).\n";
echo "Non-readonly:   clone with works on any class, not just readonly.\n";
echo "#[NoDiscard]:   prevents silent discard of the returned clone.\n";