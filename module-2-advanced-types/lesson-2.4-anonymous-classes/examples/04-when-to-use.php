<?php
declare(strict_types=1);

/**
 * Example 04 — When to Use Anonymous Classes vs Named Classes vs Closures
 * -------------------------------------------------------------------------
 * The same problem solved three different ways, so the trade-offs
 * become concrete rather than abstract.
 *
 * Decision guide:
 *   Named class   → reusable in multiple places, worth a name
 *   Anonymous class → one-off multi-method object, used once
 *   Closure         → single callable, no state (or simple state via `use`)
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  When to Use: Anonymous vs Named vs Closure         ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// The interface everything revolves around
// ─────────────────────────────────────────────────────────────────────────────

interface Transformer {
    public function transform(string $input): string;
    public function getDescription(): string;
}


// ═══════════════════════════════════════════════════════════
// SCENARIO 1 — Named class
// Use when: reusable, referenced by name in multiple places
// ═══════════════════════════════════════════════════════════

echo "── Scenario 1: Named Class ──────────────────────────\n\n";

// Named class: registered in the DI container, used in 10+ places across the app
class SlugTransformer implements Transformer {
    public function transform(string $input): string {
        return strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $input), '-'));
    }

    public function getDescription(): string {
        return "Converts text to URL-safe slug";
    }
}

class TrimTransformer implements Transformer {
    public function transform(string $input): string {
        return trim($input);
    }

    public function getDescription(): string {
        return "Removes leading and trailing whitespace";
    }
}

// Named classes: registered, injected, reused everywhere
$slug = new SlugTransformer();
$trim = new TrimTransformer();

echo "SlugTransformer: " . $slug->transform('Hello World! PHP 8.4') . "\n";
echo "TrimTransformer: '" . $trim->transform('  hello  ') . "'\n\n";

echo "Use named classes when:\n";
echo "  ✓ Used in more than one place\n";
echo "  ✓ Registered in a DI container\n";
echo "  ✓ Has a meaningful name that aids readability\n";
echo "  ✓ Complex enough to warrant its own file\n\n";


// ═══════════════════════════════════════════════════════════
// SCENARIO 2 — Anonymous class
// Use when: one-off multi-method object, defined where used
// ═══════════════════════════════════════════════════════════

echo "── Scenario 2: Anonymous Class ──────────────────────\n\n";

// Used exactly once — in this pipeline, nowhere else
// Has multiple methods + state = anonymous class is right
$pipeline = [
    new class implements Transformer {
        public function transform(string $input): string {
            return strtolower($input);
        }
        public function getDescription(): string { return "Lowercase"; }
    },
    new class implements Transformer {
        public function transform(string $input): string {
            return str_replace([' ', '\t'], '_', $input);
        }
        public function getDescription(): string { return "Spaces to underscores"; }
    },
    new class implements Transformer {
        private int $callCount = 0; // State — tracks how many times called

        public function transform(string $input): string {
            $this->callCount++;
            return preg_replace('/[^a-z0-9_]/', '', $input);
        }
        public function getDescription(): string {
            return "Strip non-alphanumeric (called {$this->callCount} times)";
        }
    },
];

$input = '  Hello World! PHP_8.4  ';
echo "Input: '{$input}'\n";
foreach ($pipeline as $transformer) {
    $input = $transformer->transform($input);
    echo "After {$transformer->getDescription()}: '{$input}'\n";
}

echo "\nUse anonymous classes when:\n";
echo "  ✓ Used in exactly one place\n";
echo "  ✓ Has multiple methods\n";
echo "  ✓ Holds state across method calls\n";
echo "  ✓ Creating a named class file would be overkill\n\n";


// ═══════════════════════════════════════════════════════════
// SCENARIO 3 — Closure
// Use when: single callable, no or simple state
// ═══════════════════════════════════════════════════════════

echo "── Scenario 3: Closure ──────────────────────────────\n\n";

$prefix    = 'PROC';
$separator = '_';

// A single operation, no state, captures outer scope
$addPrefix = fn(string $input): string => $prefix . $separator . $input;

// Simple transformation pipeline using closures
$transforms = [
    fn(string $s): string => strtoupper(trim($s)),
    fn(string $s): string => preg_replace('/\s+/', '_', $s),
    $addPrefix,
];

$input2 = '  hello world  ';
echo "Input: '{$input2}'\n";
foreach ($transforms as $i => $fn) {
    $input2 = $fn($input2);
    echo "Step " . ($i + 1) . ": '{$input2}'\n";
}

echo "\nUse closures when:\n";
echo "  ✓ Single operation — one callable\n";
echo "  ✓ No state, or simple state captured via `use`\n";
echo "  ✓ Passed to array_map, array_filter, usort, etc.\n";
echo "  ✓ Throwaway logic in a single expression\n\n";


// ═══════════════════════════════════════════════════════════
// SIDE-BY-SIDE: the same problem three ways
// Problem: format a number with currency and rounding
// ═══════════════════════════════════════════════════════════

echo "── Side-by-side: Currency formatting ────────────────\n\n";

// 1. Named class — because formatters are reused throughout the billing module
class ZarFormatter implements Transformer {
    public function __construct(private int $decimals = 2) {}

    public function transform(string $input): string {
        return 'R' . number_format((float) $input, $this->decimals);
    }

    public function getDescription(): string { return "ZAR currency formatter"; }
}

// 2. Anonymous class — a one-off EUR formatter just for this report
$eurFormatter = new class implements Transformer {
    public function transform(string $input): string {
        return '€' . number_format((float) $input, 2, ',', '.');
    }

    public function getDescription(): string { return "EUR one-off formatter"; }
};

// 3. Closure — the simplest option when you just need to format once
$usdFormat = fn(float $n): string => '$' . number_format($n, 2);

$amount = '15000';
echo "Named ZarFormatter:    " . (new ZarFormatter())->transform($amount) . "\n";
echo "Anonymous EurFormatter: " . $eurFormatter->transform($amount) . "\n";
echo "Closure UsdFormat:      " . $usdFormat((float) $amount) . "\n";


// ═══════════════════════════════════════════════════════════
// THE DECISION FLOWCHART
// ═══════════════════════════════════════════════════════════

echo "\n── Decision flowchart ───────────────────────────────\n\n";

echo "Q1: Is this used in more than one place?\n";
echo "    YES → Named class\n";
echo "    NO  → Continue\n\n";

echo "Q2: Does it need multiple methods, or hold state across calls?\n";
echo "    YES → Anonymous class\n";
echo "    NO  → Closure (fn() => ...)\n\n";

echo "Q3: Is it a test double that lives inside one test function?\n";
echo "    YES → Anonymous class (no separate file needed)\n\n";

echo "Q4: Does it need to capture outer scope variables?\n";
echo "    YES + single callable → Closure with `use (\$var)`\n";
echo "    YES + multiple methods → Anonymous class with constructor injection\n";


// ═══════════════════════════════════════════════════════════
// ANTI-PATTERN: when anonymous classes are overused
// ═══════════════════════════════════════════════════════════

echo "\n── Anti-pattern: overusing anonymous classes ────────\n\n";

echo "Bad: Defining the same anonymous class in 5 different places\n";
echo "  \$logger1 = new class implements Logger { ... }; // file A\n";
echo "  \$logger2 = new class implements Logger { ... }; // file B — same code\n";
echo "  → Extract to a named class: class ConsoleLogger implements Logger\n\n";

echo "Bad: Using an anonymous class when a closure is cleaner\n";
echo "  \$double = new class { public function run(int \$n): int { return \$n * 2; } };\n";
echo "  → Better: \$double = fn(int \$n): int => \$n * 2;\n\n";

echo "Good use cases:\n";
echo "  ✓ Test stubs and spies — defined in the test, not in a separate file\n";
echo "  ✓ One-off interface implementations that are immediately obvious\n";
echo "  ✓ Quick abstract class fulfilment (e.g. a one-off report generator)\n";
echo "  ✓ Null Object pattern — a silent do-nothing implementation\n";

echo "\n--- Recap ---\n";
echo "Named class:     reusable, referenced by name, complex enough for its own file.\n";
echo "Anonymous class: one-off, multiple methods or state, defined at point of use.\n";
echo "Closure:         single callable, no or simple state, captured via `use`.\n";
echo "Rule:            if you copy-paste an anonymous class twice → extract it to a name.\n";