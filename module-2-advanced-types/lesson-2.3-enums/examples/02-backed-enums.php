<?php
declare(strict_types=1);

/**
 * Example 02 — Backed Enums
 * --------------------------
 * PHP 8.1+
 *
 * Backed enums attach a scalar (string or int) value to each case.
 * This enables serialisation to a database, JSON API, or config file,
 * and deserialistion back using from() and tryFrom().
 *
 * When to use string backing:  human-readable storage (DB, JSON, URLs)
 * When to use integer backing: ordered values, bitflags, legacy systems
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Backed Enums (PHP 8.1+)                           ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 1 — String-backed enums
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 1: String-backed enums ──────────────────────\n\n";

enum OrderStatus: string {
    case Pending   = 'pending';
    case Confirmed = 'confirmed';
    case Shipped   = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
}

enum HttpMethod: string {
    case Get    = 'GET';
    case Post   = 'POST';
    case Put    = 'PUT';
    case Patch  = 'PATCH';
    case Delete = 'DELETE';
}

// name (case identifier) and value (backing string)
$status = OrderStatus::Confirmed;
echo "name:  {$status->name}\n";   // "Confirmed"
echo "value: {$status->value}\n";  // "confirmed"

echo "\nAll order statuses (name → value):\n";
foreach (OrderStatus::cases() as $case) {
    echo "  {$case->name} → '{$case->value}'\n";
}

// String-backed values are typically what you store in a database
echo "\nSimulating DB row:\n";
$dbRow = ['id' => 1042, 'status' => 'shipped']; // Stored as string in DB
$status = OrderStatus::from($dbRow['status']);
echo "  Restored from DB: OrderStatus::{$status->name} (value='{$status->value}')\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 2 — Integer-backed enums
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 2: Integer-backed enums ─────────────────────\n\n";

enum Priority: int {
    case Low    = 1;
    case Medium = 5;
    case High   = 10;
    case Critical = 99;
}

enum HttpStatus: int {
    case Ok                 = 200;
    case Created            = 201;
    case NoContent          = 204;
    case BadRequest         = 400;
    case Unauthorised       = 401;
    case NotFound           = 404;
    case InternalError      = 500;
}

$priority = Priority::High;
echo "name:  {$priority->name}\n";   // "High"
echo "value: {$priority->value}\n";  // 10

// Integer backing is great for comparison and ordering
echo "\nComparing priorities by value:\n";
$taskPriority = Priority::Medium;
$threshold    = Priority::High;

if ($taskPriority->value >= $threshold->value) {
    echo "  Task is high priority — escalate!\n";
} else {
    echo "  Task priority ({$taskPriority->name}={$taskPriority->value}) "
       . "is below threshold ({$threshold->name}={$threshold->value}).\n";
}

// Sorting by value
$priorities = [Priority::Critical, Priority::Low, Priority::High, Priority::Medium];
usort($priorities, fn(Priority $a, Priority $b) => $a->value <=> $b->value);
echo "\nSorted priorities:\n";
foreach ($priorities as $p) {
    echo "  {$p->name} ({$p->value})\n";
}

echo "\nHTTP status codes:\n";
foreach (HttpStatus::cases() as $status) {
    echo "  {$status->value} {$status->name}\n";
}


// ─────────────────────────────────────────────────────────────────────────────
// PART 3 — from() and tryFrom()
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 3: from() and tryFrom() ─────────────────────\n\n";

// from() — throws ValueError if value not found
echo "from() — throws on unknown value:\n";
try {
    $known   = OrderStatus::from('pending');    // OrderStatus::Pending
    echo "  from('pending') → {$known->name}\n";

    $unknown = OrderStatus::from('refunded');   // ValueError!
} catch (\ValueError $e) {
    echo "  from('refunded') → ValueError: " . $e->getMessage() . "\n";
}

// tryFrom() — returns null if value not found
echo "\ntryFrom() — returns null on unknown value:\n";
$known   = OrderStatus::tryFrom('confirmed');  // OrderStatus::Confirmed
$unknown = OrderStatus::tryFrom('refunded');   // null

echo "  tryFrom('confirmed') → " . ($known   ? $known->name   : 'null') . "\n";
echo "  tryFrom('refunded')  → " . ($unknown ? $unknown->name : 'null') . "\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 4 — Real-world: parsing from an API payload
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 4: Safe parsing from untrusted input ────────\n\n";

enum PaymentStatus: string {
    case Pending   = 'pending';
    case Completed = 'completed';
    case Failed    = 'failed';
    case Refunded  = 'refunded';
}

// Simulate incoming API payloads (some valid, some not)
$apiPayloads = [
    ['id' => 1, 'payment_status' => 'completed'],
    ['id' => 2, 'payment_status' => 'pending'],
    ['id' => 3, 'payment_status' => 'COMPLETED'],   // wrong case
    ['id' => 4, 'payment_status' => 'processing'],  // not a valid value
    ['id' => 5, 'payment_status' => ''],            // empty
];

foreach ($apiPayloads as $payload) {
    $raw    = $payload['payment_status'];
    $status = PaymentStatus::tryFrom($raw);

    if ($status === null) {
        echo "  Payment #{$payload['id']}: INVALID status '{$raw}' — rejected\n";
    } else {
        echo "  Payment #{$payload['id']}: {$status->name} ✓\n";
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// PART 5 — Serialisation and deserialisation round-trip
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 5: Serialisation round-trip ─────────────────\n\n";

// Backed enums serialise cleanly to their backing value
$status = OrderStatus::Shipped;

// Storing: enum → scalar
$stored = $status->value;      // 'shipped' — ready for DB, JSON, cookie
echo "Stored to DB: '{$stored}'\n";

// Retrieving: scalar → enum
$loaded = OrderStatus::from($stored); // OrderStatus::Shipped
echo "Loaded from DB: OrderStatus::{$loaded->name}\n";
echo "Same case? " . ($status === $loaded ? 'YES' : 'NO') . "\n\n";

// JSON round-trip
$json = json_encode(['status' => $status->value, 'priority' => Priority::High->value]);
echo "JSON: {$json}\n";

$data           = json_decode($json, true);
$jsonStatus     = OrderStatus::from($data['status']);
$jsonPriority   = Priority::from($data['priority']);
echo "Restored: {$jsonStatus->name}, {$jsonPriority->name}\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 6 — Backed enum rules and common mistakes
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 6: Rules and mistakes ───────────────────────\n\n";

echo "Rules:\n";
echo "  ✓ Declare backing type after enum name: enum Foo: string { ... }\n";
echo "  ✓ Every case must have a value of the backing type\n";
echo "  ✓ Values must be unique within the enum\n";
echo "  ✓ Both ->name and ->value are available on backed cases\n\n";

echo "Common mistakes:\n";
echo "  ✗ Forgetting a value for a case → parse error\n";
echo "  ✗ Duplicate values → fatal error\n";
echo "  ✗ Using ->value on a pure enum → Warning: Undefined property, and you get null\n";
echo "  ✗ Calling from() on untrusted input → use tryFrom() instead\n";

// Case sensitivity note
echo "\nCase sensitivity:\n";
$result = OrderStatus::tryFrom('Pending');   // null — backing is 'pending' (lowercase)
echo "  tryFrom('Pending') → " . ($result ? $result->name : 'null') . "\n";
echo "  (String backing is case-sensitive — 'Pending' ≠ 'pending')\n";

echo "\n--- Recap ---\n";
echo "String backed: enum Foo: string { case Bar = 'bar'; }\n";
echo "Integer backed: enum Foo: int { case Bar = 1; }\n";
echo "->name:    the PHP identifier (always available)\n";
echo "->value:   the backing scalar (only backed enums)\n";
echo "from():    throws ValueError if not found — use for trusted data\n";
echo "tryFrom(): returns null if not found — use for untrusted input\n";