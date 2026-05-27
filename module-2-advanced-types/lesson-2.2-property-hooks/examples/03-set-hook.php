<?php
declare(strict_types=1);

/**
 * Example 03 — The set Hook
 * --------------------------
 * PHP 8.5.
 *
 * The set hook runs every time the property is WRITTEN.
 * It intercepts the incoming value before it is stored,
 * letting you validate, transform, or normalise it.
 *
 * Three patterns:
 *   A. Validation on write   — reject invalid values with an exception
 *   B. Transformation        — normalise/clean the value before storing
 *   C. Side effects          — trigger other actions when a value changes
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  The set Hook (PHP 8.4)                             ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PATTERN A — Validation on write
// Reject values that violate business rules before they are stored
// ─────────────────────────────────────────────────────────────────────────────

echo "── Pattern A: Validation on write ───────────────────\n\n";

class Product {
    public string $sku = '' {
        set(string $value) {
            $value = strtoupper(trim($value));
            if (!preg_match('/^[A-Z]{3}-\d{4}$/', $value)) {
                throw new \InvalidArgumentException(
                    "SKU must match format ABC-1234, got: {$value}"
                );
            }
            $this->sku = $value;
        }
    }

    public float $price = 0.0 {
        set(float $value) {
            if ($value < 0) {
                throw new \InvalidArgumentException(
                    "Price cannot be negative, got: {$value}"
                );
            }
            $this->price = round($value, 2);
        }
    }

    public int $stock = 0 {
        set(int $value) {
            if ($value < 0) {
                throw new \InvalidArgumentException(
                    "Stock cannot be negative, got: {$value}"
                );
            }
            $this->stock = $value;
        }
    }
}

$product = new Product();
$product->sku   = 'wdg-0042';   // Will be normalised to WDG-0042
$product->price = 299.999;      // Will be rounded to 300.00
$product->stock = 150;

echo "SKU:   {$product->sku}\n";   // WDG-0042
echo "Price: R{$product->price}\n"; // R300.0 (rounded)
echo "Stock: {$product->stock}\n";

echo "\nValidation errors:\n";
foreach ([
    fn() => $product->sku   = 'BAD',
    fn() => $product->price = -10.0,
    fn() => $product->stock = -1,
] as $test) {
    try {
        $test();
    } catch (\InvalidArgumentException $e) {
        echo "  ✗ " . $e->getMessage() . "\n";
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// PATTERN B — Transformation on write
// Clean or normalise the value before storing it
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Pattern B: Transformation on write ───────────────\n\n";

class ContactRecord {
    // Always stored lowercase and trimmed
    public string $email = '' {
        set(string $value) => $this->email = strtolower(trim($value));
    }

    // Stored as digits only (strip spaces, dashes, brackets)
    public string $phone = '' {
        set(string $value) => $this->phone = preg_replace('/[^0-9+]/', '', $value);
    }

    // Stored as sentence-case (first letter capitalised, rest lowercased)
    public string $firstName = '' {
        set(string $v) => $this->firstName = ucfirst(strtolower(trim($v)));
    }

    public string $lastName = '' {
        set(string $v) => $this->lastName = ucfirst(strtolower(trim($v)));
    }

    // Stored as a URL-safe slug
    public string $username = '' {
        set(string $value) {
            $slug = strtolower(trim($value));
            $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
            $slug = trim($slug, '_');
            $this->username = $slug;
        }
    }
}

$contact = new ContactRecord();
$contact->email     = '  ALICE@EXAMPLE.COM  ';
$contact->phone     = '+27 (082) 123-4567';
$contact->firstName = '  aLICE  ';
$contact->lastName  = 'SMITH-JONES';
$contact->username  = '  Alice Smith 2024!  ';

echo "email:     {$contact->email}\n";
echo "phone:     {$contact->phone}\n";
echo "firstName: {$contact->firstName}\n";
echo "lastName:  {$contact->lastName}\n";
echo "username:  {$contact->username}\n";


// ─────────────────────────────────────────────────────────────────────────────
// PATTERN C — Side effects on write
// Trigger other actions when a property changes value
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Pattern C: Side effects on write ─────────────────\n\n";

class ObservableOrder {
    private array $changeLog   = [];
    private \DateTimeImmutable $lastModified;

    public function __construct(private int $id) {
        $this->lastModified = new \DateTimeImmutable();
    }

    public string $status = 'pending' {
        set(string $value) {
            $allowed = ['pending', 'confirmed', 'processing', 'shipped', 'cancelled'];
            if (!in_array($value, $allowed, true)) {
                throw new \InvalidArgumentException("Invalid status: {$value}");
            }
            // Side effect: record the change before storing
            $this->changeLog[] = [
                'field'  => 'status',
                'from'   => $this->status,
                'to'     => $value,
                'at'     => date('H:i:s'),
            ];
            // Side effect: update the modification timestamp
            $this->lastModified = new \DateTimeImmutable();
            $this->status = $value;
        }
    }

    public float $total = 0.0 {
        set(float $value) {
            if ($value < 0) {
                throw new \InvalidArgumentException("Total cannot be negative.");
            }
            $this->changeLog[] = [
                'field' => 'total',
                'from'  => $this->total,
                'to'    => $value,
                'at'    => date('H:i:s'),
            ];
            $this->lastModified = new \DateTimeImmutable();
            $this->total = round($value, 2);
        }
    }

    public function getChangeLog(): array { return $this->changeLog; }
    public function getLastModified(): \DateTimeImmutable { return $this->lastModified; }
}

$order = new ObservableOrder(1042);
$order->total  = 1500.00;
$order->status = 'confirmed';
$order->status = 'processing';
$order->status = 'shipped';

echo "Order #{$order->id} — final status: {$order->status}\n";
echo "Change log:\n";
foreach ($order->getChangeLog() as $change) {
    echo "  [{$change['at']}] {$change['field']}: "
       . json_encode($change['from']) . " → " . json_encode($change['to']) . "\n";
}

try {
    $order->status = 'refunded'; // Not in allowed list
} catch (\InvalidArgumentException $e) {
    echo "Status error: " . $e->getMessage() . "\n";
}


// ─────────────────────────────────────────────────────────────────────────────
// set hook: block vs arrow syntax
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── set hook: block vs arrow syntax ──────────────────\n\n";

class Examples {
    // Arrow: single expression — must assign $this->prop
    public string $tagArrow = '' {
        set(string $v) => $this->tagArrow = strtolower(trim($v));
    }

    // Block: multiple statements — useful for complex validation
    public int $percentage = 0 {
        set(int $value) {
            if ($value < 0 || $value > 100) {
                throw new \RangeException("Percentage must be 0-100, got {$value}");
            }
            $this->percentage = $value;
        }
    }

    // Implicit $value — arrow set without parameter declaration
    public string $code = '' {
        set => $this->code = strtoupper($value); // $value is implicit
    }
}

$ex = new Examples();
$ex->tagArrow   = '  PHP DEVELOPMENT  ';
$ex->percentage = 75;
$ex->code       = 'zar';

echo "tagArrow:   {$ex->tagArrow}\n";
echo "percentage: {$ex->percentage}\n";
echo "code:       {$ex->code}\n";

try {
    $ex->percentage = 150;
} catch (\RangeException $e) {
    echo "Range error: " . $e->getMessage() . "\n";
}

echo "\n--- Recap ---\n";
echo "set hook:    runs before the value is stored.\n";
echo "Validate:    throw exceptions for invalid values.\n";
echo "Transform:   normalise the value, then store it.\n";
echo "Side effects: log changes, update timestamps, notify observers.\n";
echo "Arrow:       single expression — set(Type \$v) => \$this->prop = transform(\$v).\n";
echo "Implicit:    set => \$this->prop = doSomething(\$value); — \$value is always available.\n";