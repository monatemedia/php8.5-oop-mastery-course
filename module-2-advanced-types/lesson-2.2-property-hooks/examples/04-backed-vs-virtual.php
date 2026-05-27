<?php
declare(strict_types=1);

/**
 * Example 04 — Backed vs Virtual Properties
 * -------------------------------------------
 * PHP 8.5.
 *
 * BACKED property:  has actual storage in memory. May have hooks that
 *                   intercept reads/writes, but the value is stored.
 *                   Identified by having a default value OR a set hook.
 *
 * VIRTUAL property: NO storage. Computed entirely by its get hook.
 *                   Has no default value and no set hook.
 *                   Read-only from outside — assigning to it is a fatal error.
 *
 * Scenario: A geometric shape model that shows both clearly.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Backed vs Virtual Properties (PHP 8.4)            ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 1 — Clear side-by-side comparison
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 1: Side-by-side comparison ──────────────────\n\n";

class Rectangle {
    // ── BACKED properties — have storage ─────────────────────────────────────
    // Default value = backed. set hook controls what can be stored.
    public float $width = 0.0 {
        set(float $v) {
            if ($v <= 0) throw new \InvalidArgumentException("Width must be positive.");
            $this->width = $v;
        }
    }

    public float $height = 0.0 {
        set(float $v) {
            if ($v <= 0) throw new \InvalidArgumentException("Height must be positive.");
            $this->height = $v;
        }
    }

    // ── VIRTUAL properties — no storage, computed on every read ──────────────
    // No default value, no set hook = virtual.
    public float $area {
        get => $this->width * $this->height;
    }

    public float $perimeter {
        get => 2 * ($this->width + $this->height);
    }

    public float $diagonal {
        get => round(sqrt($this->width ** 2 + $this->height ** 2), 4);
    }

    public string $dimensions {
        get => "{$this->width} × {$this->height}";
    }

    public bool $isSquare {
        get => $this->width === $this->height;
    }
}

$rect = new Rectangle();
$rect->width  = 8.0;   // backed — stored in memory
$rect->height = 5.0;   // backed — stored in memory

echo "Backed properties (stored):\n";
echo "  width:     {$rect->width}\n";
echo "  height:    {$rect->height}\n";

echo "\nVirtual properties (computed on read):\n";
echo "  area:      {$rect->area}\n";
echo "  perimeter: {$rect->perimeter}\n";
echo "  diagonal:  {$rect->diagonal}\n";
echo "  dimensions:{$rect->dimensions}\n";
echo "  isSquare:  " . ($rect->isSquare ? 'YES' : 'NO') . "\n";

// Virtual properties update automatically when backed ones change
$rect->width = 5.0;
echo "\nAfter width → 5.0:\n";
echo "  isSquare:  " . ($rect->isSquare ? 'YES' : 'NO') . "\n";
echo "  area:      {$rect->area}\n";

// Virtual property — cannot be assigned
try {
    $rect->area = 100.0; // Fatal Error
} catch (\Error $e) {
    echo "\nTrying to assign virtual property:\n";
    echo "  Error: " . $e->getMessage() . "\n";
}


// ─────────────────────────────────────────────────────────────────────────────
// PART 2 — Backed property with BOTH hooks
// The get hook can transform the stored value before returning it
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 2: Backed with both get and set hooks ───────\n\n";

class PriceTag {
    // Stored internally as integer cents for precision
    // get hook returns a formatted string
    // set hook accepts a float and converts to cents
    private int $cents = 0;

    public float $amount {
        get => $this->cents / 100;
        set(float $value) {
            if ($value < 0) {
                throw new \InvalidArgumentException("Price cannot be negative.");
            }
            $this->cents = (int) round($value * 100);
        }
    }

    // Virtual — formatted string derived from stored cents
    public string $formatted {
        get => 'R' . number_format($this->amount, 2);
    }

    public string $withVat {
        get => 'R' . number_format($this->amount * 1.15, 2) . ' (incl. VAT)';
    }
}

$tag = new PriceTag();
$tag->amount = 299.99;
echo "amount:   {$tag->amount}\n";    // Returns float from cents
echo "formatted:{$tag->formatted}\n";
echo "withVat:  {$tag->withVat}\n";

$tag->amount = 149.995;              // Internally rounded to 15000 cents
echo "\nAfter 149.995:\n";
echo "amount:   {$tag->amount}\n";   // R150.00 (rounded internally)
echo "formatted:{$tag->formatted}\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 3 — When to use each
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 3: When to use each ─────────────────────────\n\n";

class OrderSummary {
    // BACKED — these are real values set from outside
    public string $id     = '';
    public float  $amount = 0.0 {
        set(float $v) {
            if ($v < 0) throw new \InvalidArgumentException("Amount cannot be negative.");
            $this->amount = round($v, 2);
        }
    }
    public string $currency = 'ZAR';
    public string $status   = 'pending' {
        set(string $v) => $this->status = strtolower($v);
    }

    // VIRTUAL — derived from the backed properties above
    public string $reference {
        get => strtoupper("{$this->currency}-{$this->id}");
    }

    public string $display {
        get => "[{$this->reference}] {$this->status} — "
             . "{$this->currency} " . number_format($this->amount, 2);
    }

    public bool $isPending {
        get => $this->status === 'pending';
    }

    public bool $isPaid {
        get => $this->status === 'paid';
    }
}

$order = new OrderSummary();
$order->id       = 'ORD-1042';
$order->amount   = 1500.00;
$order->status   = 'CONFIRMED'; // set hook lowercases it

echo "id:        {$order->id}\n";
echo "amount:    {$order->amount}\n";
echo "status:    {$order->status}\n";
echo "reference: {$order->reference}\n";
echo "display:   {$order->display}\n";
echo "isPending: " . ($order->isPending ? 'YES' : 'NO') . "\n";
echo "isPaid:    " . ($order->isPaid    ? 'YES' : 'NO') . "\n";

$order->status = 'paid';
echo "\nAfter paying:\n";
echo "display:   {$order->display}\n";
echo "isPending: " . ($order->isPending ? 'YES' : 'NO') . "\n";
echo "isPaid:    " . ($order->isPaid    ? 'YES' : 'NO') . "\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 4 — Memory and identity: backed properties persist, virtual do not
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 4: Memory behaviour ─────────────────────────\n\n";

echo "Backed properties:\n";
echo "  - Stored in the object's memory\n";
echo "  - Value persists between reads\n";
echo "  - Can have a default value\n";
echo "  - Can be assigned from outside (if set hook exists or no hooks)\n";
echo "  - Accessible via \$obj->prop and \$obj->prop = value\n\n";

echo "Virtual properties:\n";
echo "  - NO storage — recomputed on every read\n";
echo "  - Cannot have a default value\n";
echo "  - CANNOT be assigned from outside — fatal error\n";
echo "  - Read-only by design\n";
echo "  - Replace getter methods: getArea() → \$shape->area\n";

echo "\n--- Recap ---\n";
echo "Backed:  has default value or set hook → value stored in memory.\n";
echo "Virtual: no default + no set hook → computed by get hook only.\n";
echo "Virtual properties auto-update when the backed properties they depend on change.\n";
echo "Assigning to a virtual property is always a fatal Error.\n";
echo "Use virtual for: derived values, computed aggregates, formatted representations.\n";