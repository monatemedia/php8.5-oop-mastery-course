<?php
declare(strict_types=1);

/**
 * Example 02 — Reading Constructor Parameters
 * ---------------------------------------------
 * This is the most important Reflection skill for container auto-wiring.
 * A container needs exactly one thing from each constructor parameter:
 * the fully-qualified type hint name.
 *
 * This example shows:
 *   A. Reading parameter names and types
 *   B. Distinguishing interface/class types from built-in scalars
 *   C. Building the exact function a container uses: getConstructorDeps()
 *   D. Proving the function works on the Module 3 checkout system
 *
 * Course Philosophy Rule 3: The type system is a security layer.
 * Constructor type hints are the interface between your classes and the container.
 * The stricter your types, the more the container can resolve automatically.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Reading Constructor Parameters                     ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Sample classes with various constructor signatures
// ─────────────────────────────────────────────────────────────────────────────

interface DatabaseInterface     { public function query(string $sql): array; }
interface LoggerInterface       { public function log(string $m): void; }
interface MailerInterface       { public function send(string $to): bool; }
interface CacheInterface        { public function get(string $k): mixed; }
interface PaymentGatewayInterface { public function charge(float $a): bool; }

// Class with only interface-typed params — fully auto-wirable
class OrderService {
    public function __construct(
        private DatabaseInterface       $db,
        private MailerInterface         $mailer,
        private LoggerInterface         $logger
    ) {}
}

// Class with a mix — partially auto-wirable
class MySQLDatabase {
    public function __construct(
        private string $dsn,              // ← built-in — cannot auto-wire
        private string $username,         // ← built-in — cannot auto-wire
        private int    $port = 3306,      // ← built-in with default
        private LoggerInterface $logger = null // ← interface with null default
    ) {}
    public function query(string $sql): array { return []; }
}

// Class with no constructor — trivially instantiable
class NullLogger implements LoggerInterface {
    public function log(string $m): void {}
}

// Class extending another — only its OWN constructor params matter
class ProductRepository {
    public function __construct(
        private DatabaseInterface $db,
        private CacheInterface    $cache
    ) {}
}

class CheckoutService {
    public function __construct(
        private OrderService         $orders,    // concrete class — also auto-wirable
        private ProductRepository    $products,  // concrete class
        private PaymentGatewayInterface $gateway
    ) {}
}


// ═══════════════════════════════════════════════════════════
// PART A — Reading parameter names and types
// ═══════════════════════════════════════════════════════════

echo "── Part A: Reading parameter names and types ─────────\n\n";

$ref  = new ReflectionClass(OrderService::class);
$ctor = $ref->getConstructor();

echo "OrderService constructor parameters:\n\n";
foreach ($ctor->getParameters() as $param) {
    $type = $param->getType();

    echo "  Parameter: \${$param->getName()}\n";

    if ($type === null) {
        echo "    Type: NONE (no type hint)\n";
    } elseif ($type instanceof ReflectionNamedType) {
        echo "    Type class: ReflectionNamedType\n";
        echo "    getName():  {$type->getName()}\n";
        echo "    isBuiltin(): " . ($type->isBuiltin() ? 'true (scalar)' : 'false (class/interface)') . "\n";
        echo "    allowsNull(): " . ($type->allowsNull() ? 'true' : 'false') . "\n";
    }
    echo "    isOptional(): " . ($param->isOptional() ? 'true' : 'false') . "\n";
    echo "    hasDefaultValue(): " . ($param->hasDefaultValue() ? 'true' : 'false') . "\n\n";
}


// ═══════════════════════════════════════════════════════════
// PART B — isBuiltin(): the auto-wiring boundary
// ═══════════════════════════════════════════════════════════

echo "── Part B: isBuiltin() — the auto-wiring boundary ───\n\n";

echo "isBuiltin() returns TRUE for PHP scalar types:\n";
$builtins = ['string', 'int', 'float', 'bool', 'array', 'callable', 'void', 'null', 'mixed', 'never'];
foreach ($builtins as $b) {
    echo "  {$b}\n";
}

echo "\nisBuiltin() returns FALSE for class/interface names:\n";
$interfaces = [
    DatabaseInterface::class,
    LoggerInterface::class,
    MailerInterface::class,
    'Countable',
    'Throwable',
    'DateTimeInterface',
];
foreach ($interfaces as $i) {
    $short = class_basename($i);
    echo "  {$short}\n";
}

echo "\nThe auto-wiring rule:\n";
echo "  isBuiltin() = false → resolve from container (can auto-wire)\n";
echo "  isBuiltin() = true  → cannot auto-wire (needs explicit binding)\n\n";

// Live demonstration
$refDb  = new ReflectionClass(MySQLDatabase::class);
$ctorDb = $refDb->getConstructor();
echo "MySQLDatabase constructor — what CAN and CANNOT be auto-wired:\n";
foreach ($ctorDb->getParameters() as $param) {
    $type = $param->getType();
    if ($type instanceof ReflectionNamedType) {
        $canWire = !$type->isBuiltin() ? '✓ auto-wirable' : '✗ needs explicit binding';
        echo "  \${$param->getName()}: {$type->getName()} — {$canWire}\n";
    }
}


// ═══════════════════════════════════════════════════════════
// PART C — getConstructorDeps(): the exact function a container uses
// ═══════════════════════════════════════════════════════════

echo "\n── Part C: getConstructorDeps() — the container function\n\n";

/**
 * Returns the list of class/interface names that a class's constructor
 * requires, for use in auto-wiring.
 *
 * @return array{name: string, type: string, optional: bool, builtin: bool}[]
 */
function getConstructorDeps(string $className): array {
    $ref  = new ReflectionClass($className);
    $ctor = $ref->getConstructor();

    if ($ctor === null) {
        return []; // No constructor — no dependencies
    }

    $deps = [];
    foreach ($ctor->getParameters() as $param) {
        $type = $param->getType();

        if ($type === null) {
            $deps[] = [
                'name'     => $param->getName(),
                'type'     => 'none',
                'optional' => $param->isOptional(),
                'builtin'  => false,
                'auto'     => false,
                'default'  => $param->isOptional() ? $param->getDefaultValue() : null,
            ];
        } elseif ($type instanceof ReflectionNamedType) {
            $deps[] = [
                'name'     => $param->getName(),
                'type'     => $type->getName(),
                'optional' => $param->isOptional(),
                'builtin'  => $type->isBuiltin(),
                'auto'     => !$type->isBuiltin(),  // can auto-wire if not scalar
                'default'  => $param->isOptional() && $param->hasDefaultValue()
                                ? $param->getDefaultValue()
                                : null,
            ];
        } elseif ($type instanceof ReflectionUnionType) {
            $deps[] = [
                'name'     => $param->getName(),
                'type'     => implode('|', array_map(fn($t) => $t->getName(), $type->getTypes())),
                'optional' => $param->isOptional(),
                'builtin'  => false,
                'auto'     => false,  // union types need explicit handling
                'default'  => null,
            ];
        }
    }

    return $deps;
}

// Test on various classes
$classes = [OrderService::class, MySQLDatabase::class, NullLogger::class, ProductRepository::class];

foreach ($classes as $class) {
    $short = class_basename($class);
    $deps  = getConstructorDeps($class);
    echo "{$short}:\n";
    if (empty($deps)) {
        echo "  (no constructor dependencies)\n";
    }
    foreach ($deps as $dep) {
        $auto = $dep['auto'] ? '✓ auto' : '✗ manual';
        echo "  \${$dep['name']}: {$dep['type']} [{$auto}]\n";
    }
    echo "\n";
}


// ═══════════════════════════════════════════════════════════
// PART D — Proving it on the Module 3 checkout system
// ═══════════════════════════════════════════════════════════

echo "── Part D: Module 3 checkout system — full dep scan ─\n\n";

$checkoutClasses = [
    CheckoutService::class,
    OrderService::class,
    ProductRepository::class,
];

$autoWirable = 0;
$manualNeeded = 0;

foreach ($checkoutClasses as $class) {
    $short = class_basename($class);
    $deps  = getConstructorDeps($class);
    echo "{$short}:\n";
    foreach ($deps as $dep) {
        if ($dep['auto']) {
            echo "  ✓ \${$dep['name']}: {$dep['type']}\n";
            $autoWirable++;
        } else {
            echo "  ✗ \${$dep['name']}: {$dep['type']} (needs explicit binding)\n";
            $manualNeeded++;
        }
    }
    echo "\n";
}

echo "Summary:\n";
echo "  Auto-wirable parameters:         {$autoWirable}\n";
echo "  Parameters needing explicit def: {$manualNeeded}\n\n";

echo "Conclusion:\n";
echo "  Every parameter in the checkout system is typed as an interface.\n";
echo "  isBuiltin() = false for all of them.\n";
echo "  A container with the right interface→concrete bindings can wire\n";
echo "  this entire graph automatically — zero manual factories needed.\n";
echo "  This is what Lesson 4.3 builds.\n\n";

echo "Rule 3 connection:\n";
echo "  Typing constructor params as interfaces (not 'string' or 'mixed')\n";
echo "  is not just good design — it literally enables auto-wiring.\n";
echo "  Untyped or scalar params break the auto-wiring chain.\n";

function class_basename(string $class): string {
    $parts = explode('\\', $class);
    return end($parts);
}