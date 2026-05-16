<?php
declare(strict_types=1);

/**
 * Example 01 — Syntax and Instantiation
 * ----------------------------------------
 * Everything you need to know about the basic syntax of anonymous classes.
 * This file covers every syntactic variation so nothing surprises you later.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Anonymous Class Syntax and Instantiation           ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 1 — The minimal form
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 1: Minimal form ─────────────────────────────\n\n";

// An empty anonymous class — valid, rarely useful on its own
$empty = new class {};
echo "Empty anonymous class created.\n";
echo "get_class: " . get_class($empty) . "\n"; // Internal generated name

// With properties and methods — still no name
$counter = new class {
    private int $count = 0;

    public function increment(): void { $this->count++; }
    public function decrement(): void { $this->count--; }
    public function getCount(): int   { return $this->count; }
    public function reset(): void     { $this->count = 0; }
};

$counter->increment();
$counter->increment();
$counter->increment();
$counter->decrement();
echo "Counter: " . $counter->getCount() . "\n"; // 2


// ─────────────────────────────────────────────────────────────────────────────
// PART 2 — Constructor arguments
// Arguments go between `class` and `{`
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 2: Constructor arguments ───────────────────\n\n";

// Arguments passed BEFORE the class body
$greeter = new class('Alice', 'en') {
    public function __construct(
        private string $name,
        private string $locale
    ) {}

    public function greet(): string {
        return match($this->locale) {
            'en' => "Hello, {$this->name}!",
            'fr' => "Bonjour, {$this->name} !",
            'af' => "Hallo, {$this->name}!",
            default => "Hi, {$this->name}!",
        };
    }
};

echo $greeter->greet() . "\n";

// With promotion — constructor property promotion works inside anonymous classes too
$config = new class(['debug' => true, 'version' => '1.0', 'env' => 'prod']) {
    public function __construct(private array $settings) {}

    public function get(string $key, mixed $default = null): mixed {
        return $this->settings[$key] ?? $default;
    }

    public function all(): array { return $this->settings; }
};

echo "Debug: " . ($config->get('debug') ? 'true' : 'false') . "\n";
echo "Env:   " . $config->get('env') . "\n";
echo "Port:  " . $config->get('port', 8080) . "\n"; // Uses default


// ─────────────────────────────────────────────────────────────────────────────
// PART 3 — Outer scope: anonymous classes vs closures
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 3: Outer scope access ───────────────────────\n\n";

$taxRate = 0.15;
$prefix  = 'R';

// ❌ Anonymous class CANNOT access outer variables directly
// (unlike closures which use `use ($var)`)

// ✅ Pass via constructor
$formatter = new class($prefix, $taxRate) {
    public function __construct(
        private string $prefix,
        private float  $taxRate
    ) {}

    public function format(float $amount): string {
        return $this->prefix . number_format($amount, 2);
    }

    public function withTax(float $amount): string {
        return $this->format($amount + ($amount * $this->taxRate));
    }
};

echo "Base price: "      . $formatter->format(299.00)    . "\n";
echo "With 15% VAT: "   . $formatter->withTax(299.00)   . "\n";

// Compare with a closure that DOES capture outer scope:
$formatClosure = fn(float $amount): string => $prefix . number_format($amount, 2);
echo "Closure result: " . $formatClosure(299.00) . "\n";
echo "(Closures use `use` — anonymous classes use constructor injection)\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 4 — The internal name PHP generates
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 4: PHP's internal naming ────────────────────\n\n";

$a = new class { public string $x = 'A'; };
$b = new class { public string $x = 'B'; };

echo "Class of \$a: " . get_class($a) . "\n";
echo "Class of \$b: " . get_class($b) . "\n";
echo "Are they the same class? " . (get_class($a) === get_class($b) ? 'YES' : 'NO') . "\n\n";

// Two instances of the SAME anonymous class definition
$obj1 = new class { public int $n = 0; };
$obj2 = new class { public int $n = 0; };
echo "obj1 class: " . get_class($obj1) . "\n";
echo "obj2 class: " . get_class($obj2) . "\n";
echo "Same class? " . (get_class($obj1) === get_class($obj2) ? 'YES' : 'NO') . "\n";
echo "(Different `new class` expressions = different internal names)\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 5 — Properties, methods, constants, and static members
// Anonymous classes support everything named classes support
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 5: Full feature set ─────────────────────────\n\n";

$richClass = new class(42) {
    private static int $instanceCount = 0;
    public const VERSION = '1.0';

    private int $value;

    public function __construct(int $value) {
        $this->value = $value;
        self::$instanceCount++;
    }

    public function getValue(): int          { return $this->value; }
    public static function getCount(): int   { return self::$instanceCount; }

    // Static factory
    public static function fromString(string $s): static {
        return new static((int) $s);
    }

    public function __toString(): string {
        return "AnonymousClass({$this->value})";
    }
};

echo "Value:   " . $richClass->getValue()          . "\n";
echo "Count:   " . $richClass::getCount()           . "\n";
echo "Version: " . $richClass::VERSION              . "\n";
echo "String:  " . (string) $richClass              . "\n";

$second = $richClass::fromString('99');
echo "Second:  " . (string) $second                 . "\n";
echo "Count:   " . $richClass::getCount()           . "\n"; // Still 2 (static)


// ─────────────────────────────────────────────────────────────────────────────
// PART 6 — Using traits inside anonymous classes
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 6: Traits in anonymous classes ─────────────\n\n";

trait Timestamps {
    private string $createdAt;

    public function initTimestamps(): void {
        $this->createdAt = date('Y-m-d H:i:s');
    }

    public function getCreatedAt(): string { return $this->createdAt; }
}

$model = new class('Alice') {
    use Timestamps;

    public function __construct(public string $name) {
        $this->initTimestamps();
    }
};

echo "Name:      {$model->name}\n";
echo "Created:   {$model->getCreatedAt()}\n";

echo "\n--- Recap ---\n";
echo "Syntax:     new class(args) extends Base implements Iface { ... }\n";
echo "No name:    get_class() returns an internal generated string.\n";
echo "Outer scope: pass via constructor — anonymous classes cannot `use` like closures.\n";
echo "Full support: properties, methods, constants, static members, traits all work.\n";