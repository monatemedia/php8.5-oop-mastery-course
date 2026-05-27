<?php
declare(strict_types=1);

/**
 * Example 02 — The get Hook
 * --------------------------
 * PHP 8.5.
 *
 * The get hook runs every time the property is READ.
 * It can: compute a value, transform the stored value, lazy-load data,
 * or format the output before it reaches the caller.
 *
 * Three patterns:
 *   A. Derived / computed   — value calculated from other properties
 *   B. Transformed read     — stored value modified before returning
 *   C. Lazy-loaded          — expensive computation deferred until first read
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  The get Hook (PHP 8.4)                             ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PATTERN A — Derived / computed properties
// No storage — calculated entirely from other properties on every read
// ─────────────────────────────────────────────────────────────────────────────

echo "── Pattern A: Derived properties ────────────────────\n\n";

class ShoppingCart {
    private array $items = [];

    public function addItem(string $name, float $price, int $qty): void {
        $this->items[] = ['name' => $name, 'price' => $price, 'qty' => $qty];
    }

    // Virtual — computed from items array, never stored
    public int $itemCount {
        get => array_sum(array_column($this->items, 'qty'));
    }

    public float $subtotal {
        get {
            $total = 0.0;
            foreach ($this->items as $item) {
                $total += $item['price'] * $item['qty'];
            }
            return $total;
        }
    }

    public float $tax {
        get => round($this->subtotal * 0.15, 2); // 15% VAT
    }

    public float $total {
        get => round($this->subtotal + $this->tax, 2);
    }

    public string $summary {
        get => "Items: {$this->itemCount} | "
             . "Subtotal: R{$this->subtotal} | "
             . "Tax (15%): R{$this->tax} | "
             . "Total: R{$this->total}";
    }
}

$cart = new ShoppingCart();
$cart->addItem('Widget Pro',   299.00, 2);
$cart->addItem('Gadget X',     149.00, 1);
$cart->addItem('Cable Bundle',  49.00, 3);

echo $cart->summary . "\n";
echo "item count:  {$cart->itemCount}\n";
echo "subtotal:    R{$cart->subtotal}\n";
echo "tax:         R{$cart->tax}\n";
echo "total:       R{$cart->total}\n";

// Because they're derived, they update automatically when items change
$cart->addItem('Extra Widget', 299.00, 1);
echo "\nAfter adding one more widget:\n";
echo $cart->summary . "\n";


// ─────────────────────────────────────────────────────────────────────────────
// PATTERN B — Transformed read
// Value IS stored, but the hook transforms it before returning
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Pattern B: Transformed reads ─────────────────────\n\n";

class TemperatureReading {
    // Store in Celsius internally
    public float $celsius = 0.0;

    // Derive Fahrenheit from stored Celsius — no extra storage
    public float $fahrenheit {
        get => round($this->celsius * 9/5 + 32, 2);
    }

    public float $kelvin {
        get => round($this->celsius + 273.15, 2);
    }

    public string $description {
        get => match(true) {
            $this->celsius < 0   => "Freezing",
            $this->celsius < 15  => "Cold",
            $this->celsius < 25  => "Comfortable",
            $this->celsius < 35  => "Warm",
            default              => "Hot",
        };
    }
}

$temp = new TemperatureReading();
$temp->celsius = 22.0;
echo "Celsius:     {$temp->celsius}°C\n";
echo "Fahrenheit:  {$temp->fahrenheit}°F\n";
echo "Kelvin:      {$temp->kelvin}K\n";
echo "Description: {$temp->description}\n";

$temp->celsius = -5.0;
echo "\nAfter change to -5°C:\n";
echo "Fahrenheit:  {$temp->fahrenheit}°F\n";
echo "Description: {$temp->description}\n";


// ─────────────────────────────────────────────────────────────────────────────
// PATTERN C — Lazy-loaded property
// Expensive computation deferred until first read, then cached
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Pattern C: Lazy-loaded property ──────────────────\n\n";

class UserReport {
    private ?array $cachedStats = null;
    private int    $loadCount   = 0;

    public function __construct(private int $userId) {}

    // stats is only computed when first read, then cached
    public array $stats {
        get {
            if ($this->cachedStats === null) {
                $this->cachedStats = $this->expensiveComputation();
                $this->loadCount++;
                echo "  [LAZY] stats computed for user #{$this->userId}\n";
            }
            return $this->cachedStats;
        }
    }

    // How many times was the expensive computation actually run?
    public int $loadCount {
        get => $this->loadCount;
    }

    private function expensiveComputation(): array {
        // Simulates a slow database query or API call
        return [
            'orders'    => 42,
            'revenue'   => 15420.50,
            'lastOrder' => '2024-01-15',
        ];
    }
}

$report = new UserReport(1);
echo "Before reading stats...\n";
echo "Load count: {$report->loadCount}\n";

echo "\nReading stats for the first time:\n";
$stats = $report->stats;
echo "Orders:  {$stats['orders']}\n";
echo "Revenue: R{$stats['revenue']}\n";
echo "Load count: {$report->loadCount}\n";

echo "\nReading stats again (uses cache):\n";
$stats2 = $report->stats;
echo "Orders: {$stats2['orders']}\n";
echo "Load count: {$report->loadCount} (still 1 — cached)\n";


// ─────────────────────────────────────────────────────────────────────────────
// get hook with full block syntax vs arrow syntax
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── get hook: block vs arrow syntax ──────────────────\n\n";

class StringWrapper {
    public string $raw = '';

    // Arrow syntax — single expression, implicitly returned
    public string $upper {
        get => strtoupper($this->raw);
    }

    // Arrow syntax — still single expression
    public string $slug {
        get => strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($this->raw)));
    }

    // Block syntax — multiple statements, explicit return needed
    public string $preview {
        get {
            $truncated = substr($this->raw, 0, 50);
            $suffix    = strlen($this->raw) > 50 ? '...' : '';
            return $truncated . $suffix;
        }
    }

    public int $wordCount {
        get {
            if (empty(trim($this->raw))) return 0;
            return str_word_count($this->raw);
        }
    }
}

$wrapper = new StringWrapper();
$wrapper->raw = 'Hello World! This is a PHP 8.4 property hooks example string.';
echo "raw:        {$wrapper->raw}\n";
echo "upper:      {$wrapper->upper}\n";
echo "slug:       {$wrapper->slug}\n";
echo "preview:    {$wrapper->preview}\n";
echo "word count: {$wrapper->wordCount}\n";

echo "\n--- Recap ---\n";
echo "get hook:  runs on every read — use for computed, transformed, or lazy values.\n";
echo "Virtual:   get hook with no default value = no storage, read-only from outside.\n";
echo "Arrow:     single-expression get => expr — expression is the return value.\n";
echo "Block:     multi-statement get { ... return \$val; } — explicit return required.\n";
echo "Derived properties auto-update — they reflect the current state every read.\n";