<?php
declare(strict_types=1);

/**
 * Example 01 — Reflection Basics
 * ---------------------------------
 * The Reflection API lets you inspect class structure at runtime without
 * instantiating the class. This example covers:
 *
 *   A. ReflectionClass — class metadata
 *   B. ReflectionMethod — method metadata
 *   C. ReflectionProperty — property metadata
 *   D. Practical: building a class inspector function
 *
 * Course Philosophy Rule 3: The type system is a security layer.
 * Reflection is how containers READ that type system at runtime.
 * Well-typed code is not just documentation — it is executable metadata.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Reflection Basics                                  ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// The classes we will inspect
// ─────────────────────────────────────────────────────────────────────────────

interface LoggerInterface {
    public function log(string $level, string $message): void;
}

interface StorableInterface {
    public function save(): bool;
    public function getId(): ?int;
}

abstract class BaseService {
    protected string $name = 'base';
    abstract public function execute(): void;
}

class OrderService extends BaseService implements StorableInterface {
    private static int  $instanceCount = 0;
    private ?int        $id            = null;

    public function __construct(
        private LoggerInterface $logger,
        private string          $currency = 'ZAR'
    ) {
        self::$instanceCount++;
    }

    public function execute(): void {
        $this->logger->log('INFO', 'OrderService executing');
    }

    public function save(): bool {
        $this->id = rand(1000, 9999);
        return true;
    }

    public function getId(): ?int     { return $this->id; }
    public function getCurrency(): string { return $this->currency; }
    public static function getCount(): int { return self::$instanceCount; }
}


// ═══════════════════════════════════════════════════════════
// PART A — ReflectionClass: class metadata
// ═══════════════════════════════════════════════════════════

echo "── Part A: ReflectionClass metadata ─────────────────\n\n";

$ref = new ReflectionClass(OrderService::class);

echo "Class name:         " . $ref->getName() . "\n";
echo "Short name:         " . $ref->getShortName() . "\n";
echo "Is abstract?        " . ($ref->isAbstract()  ? 'YES' : 'NO')  . "\n";
echo "Is interface?       " . ($ref->isInterface() ? 'YES' : 'NO')  . "\n";
echo "Is final?           " . ($ref->isFinal()     ? 'YES' : 'NO')  . "\n";
echo "Is instantiable?    " . ($ref->isInstantiable() ? 'YES' : 'NO') . "\n";
echo "Parent class:       " . ($ref->getParentClass() ? $ref->getParentClass()->getName() : 'none') . "\n";

echo "\nImplemented interfaces:\n";
foreach ($ref->getInterfaceNames() as $iface) {
    echo "  - {$iface}\n";
}

echo "\nAll ancestor interfaces (including parent's):\n";
$parent = $ref->getParentClass();
if ($parent) {
    foreach ($parent->getInterfaceNames() as $iface) {
        echo "  - {$iface} (from " . $parent->getShortName() . ")\n";
    }
}

// Check instanceof without creating the object
echo "\ninstanceof checks (without instantiation):\n";
echo "  implements StorableInterface? " .
    ($ref->implementsInterface(StorableInterface::class) ? 'YES' : 'NO') . "\n";
echo "  implements LoggerInterface?   " .
    ($ref->implementsInterface(LoggerInterface::class)   ? 'YES' : 'NO') . "\n";
echo "  extends BaseService?          " .
    ($ref->isSubclassOf(BaseService::class) ? 'YES' : 'NO') . "\n";


// ═══════════════════════════════════════════════════════════
// PART B — ReflectionMethod: method metadata
// ═══════════════════════════════════════════════════════════

echo "\n── Part B: ReflectionMethod metadata ────────────────\n\n";

$methods = $ref->getMethods(ReflectionMethod::IS_PUBLIC);
echo "Public methods (" . count($methods) . "):\n";
foreach ($methods as $method) {
    $flags = [];
    if ($method->isStatic())   $flags[] = 'static';
    if ($method->isAbstract()) $flags[] = 'abstract';
    $flagStr = empty($flags) ? '' : ' [' . implode(', ', $flags) . ']';
    echo "  {$method->getName()}(){$flagStr}\n";
}

// Constructor specifically
$ctor = $ref->getConstructor();
echo "\nConstructor: " . ($ctor ? $ctor->getName() : 'none') . "\n";
echo "Constructor param count: " . ($ctor ? count($ctor->getParameters()) : 0) . "\n";


// ═══════════════════════════════════════════════════════════
// PART C — ReflectionProperty: property metadata
// ═══════════════════════════════════════════════════════════

echo "\n── Part C: ReflectionProperty metadata ──────────────\n\n";

$properties = $ref->getProperties();
echo "All properties (" . count($properties) . "):\n";
foreach ($properties as $prop) {
    $visibility = match(true) {
        $prop->isPublic()    => 'public',
        $prop->isProtected() => 'protected',
        default              => 'private',
    };
    $static = $prop->isStatic() ? ' static' : '';
    $type   = $prop->getType() ? ': ' . $prop->getType()->getName() : '';
    echo "  {$visibility}{$static} \${$prop->getName()}{$type}\n";
}


// ═══════════════════════════════════════════════════════════
// PART D — Practical: a class inspector function
// ═══════════════════════════════════════════════════════════

echo "\n── Part D: Class inspector ──────────────────────────\n\n";

function inspectClass(string $class): void {
    $ref = new ReflectionClass($class);

    echo "=== " . $ref->getShortName() . " ===\n";
    echo "Instantiable: " . ($ref->isInstantiable() ? 'YES' : 'NO') . "\n";

    $parent = $ref->getParentClass();
    if ($parent) echo "Extends: " . $parent->getShortName() . "\n";

    $ifaces = $ref->getInterfaceNames();
    if ($ifaces) echo "Implements: " . implode(', ', array_map(fn($i) => class_basename($i), $ifaces)) . "\n";

    $ctor = $ref->getConstructor();
    if ($ctor) {
        $params = $ctor->getParameters();
        echo "Constructor params: " . count($params) . "\n";
        foreach ($params as $p) {
            $type = $p->getType();
            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : 'none';
            $optional = $p->isOptional() ? ' [optional]' : '';
            echo "  - \${$p->getName()}: {$typeName}{$optional}\n";
        }
    } else {
        echo "Constructor: none\n";
    }
    echo "\n";
}

inspectClass(OrderService::class);
inspectClass(BaseService::class);

// Inspect a class without declaring it first — just a class name string
$classes = [LoggerInterface::class, StorableInterface::class];
foreach ($classes as $class) {
    $r = new ReflectionClass($class);
    echo $r->getShortName() . ": interface=" . ($r->isInterface() ? 'YES' : 'NO') .
         ", abstract=" . ($r->isAbstract() ? 'YES' : 'NO') . "\n";
}


// ─────────────────────────────────────────────────────────────────────────────
// Key insight: Reflection reads WITHOUT instantiating
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Key insight: zero instantiation ──────────────────\n\n";
echo "OrderService::getCount() before any ReflectionClass usage: 0\n";
echo "(We reflected on OrderService multiple times — no instances created)\n";
echo "Actual count: " . OrderService::getCount() . "\n";
echo "\nReflection reads CLASS METADATA — it never runs the constructor.\n";
echo "This is how containers plan the wiring graph without triggering side effects.\n";

echo "\n--- Recap ---\n";
echo "ReflectionClass:    inspect class metadata, parent, interfaces, methods, properties.\n";
echo "isInstantiable():   false for abstract classes and interfaces.\n";
echo "getConstructor():   returns null if there is no constructor.\n";
echo "No side effects:    Reflection never calls the constructor — pure metadata read.\n";
echo "Rule 3 connection:  well-typed code produces rich metadata that containers can read.\n";

function class_basename(string $class): string {
    return end(explode('\\', $class));
}