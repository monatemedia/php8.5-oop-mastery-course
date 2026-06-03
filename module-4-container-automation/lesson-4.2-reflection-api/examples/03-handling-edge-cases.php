<?php
declare(strict_types=1);

/**
 * Example 03 — Handling Edge Cases
 * ----------------------------------
 * Real constructors are messier than the happy path.
 * A production-quality auto-wiring function must handle:
 *
 *   A. No constructor at all
 *   B. Constructor with no parameters
 *   C. Parameters with no type hint
 *   D. Nullable type hints (?LoggerInterface)
 *   E. Union types (int|string)
 *   F. Intersection types (Countable&Traversable)
 *   G. Parameters with default values (optional deps)
 *   H. Mixed primitive + interface params (partial auto-wire)
 *
 * For each case: show what Reflection returns and what the container should do.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Handling Reflection Edge Cases                     ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Interfaces used throughout
// ─────────────────────────────────────────────────────────────────────────────

interface LoggerInterface    { public function log(string $m): void; }
interface DatabaseInterface  { public function query(string $sql): array; }

class NullLogger implements LoggerInterface {
    public function log(string $m): void {}
}


// ─────────────────────────────────────────────────────────────────────────────
// Sample classes covering every edge case
// ─────────────────────────────────────────────────────────────────────────────

// Case A: No constructor at all
class CaseA_NoConstructor {
    public function doWork(): void {}
}

// Case B: Constructor with zero parameters
class CaseB_EmptyConstructor {
    public function __construct() {}
}

// Case C: Parameter with no type hint
class CaseC_NoTypeHint {
    public function __construct($value, LoggerInterface $logger) {} // ← $value untyped
}

// Case D: Nullable type hint
class CaseD_Nullable {
    public function __construct(
        private DatabaseInterface  $db,
        private ?LoggerInterface   $logger = null  // ← nullable, optional
    ) {}
}

// Case E: Union types
class CaseE_Union {
    public function __construct(
        private int|string          $id,        // ← union of scalars
        private DatabaseInterface   $db
    ) {}
}

// Case F: Intersection type (PHP 8.1+)
interface Countable2  { public function count(): int; }
interface Iterable2   { public function items(): array; }

class CaseF_Intersection {
    public function __construct(
        private Countable2&Iterable2 $collection  // ← intersection type
    ) {}
}

// Case G: Optional interface with default null (Null Object needed)
class CaseG_OptionalWithDefault {
    public function __construct(
        private DatabaseInterface  $db,
        private LoggerInterface    $logger = new NullLogger()  // PHP 8.1: new in initialiser
    ) {}
}

// Case H: Mixed primitive + interface params
class CaseH_Mixed {
    public function __construct(
        private DatabaseInterface  $db,             // ← auto-wirable
        private string             $tableName,      // ← primitive — needs explicit
        private int                $cacheTimeout = 300, // ← primitive with default
        private ?LoggerInterface   $logger = null  // ← optional interface
    ) {}
}


// ═══════════════════════════════════════════════════════════
// The analysis function
// ═══════════════════════════════════════════════════════════

function analyseConstructor(string $className): void {
    $ref   = new ReflectionClass($className);
    $short = $ref->getShortName();

    echo "── {$short} ──────────────────────────────────────\n";

    $ctor = $ref->getConstructor();

    if ($ctor === null) {
        echo "  No constructor defined.\n";
        echo "  Container action: call new {$short}() directly — no deps to resolve.\n\n";
        return;
    }

    $params = $ctor->getParameters();

    if (empty($params)) {
        echo "  Constructor has zero parameters.\n";
        echo "  Container action: call new {$short}() directly — no deps to resolve.\n\n";
        return;
    }

    foreach ($params as $param) {
        $type = $param->getType();
        echo "\n  Parameter: \${$param->getName()}\n";

        // ── No type hint ──────────────────────────────────
        if ($type === null) {
            echo "    Reflection type: NULL (no type hint)\n";
            if ($param->isOptional()) {
                $default = $param->hasDefaultValue()
                    ? json_encode($param->getDefaultValue())
                    : 'null';
                echo "    Optional: YES (default = {$default})\n";
                echo "    Container action: use default value, skip resolution.\n";
            } else {
                echo "    Optional: NO\n";
                echo "    Container action: ⚠ FAIL — cannot resolve untyped required param.\n";
                echo "    Fix: register an explicit binding for this class.\n";
            }
            continue;
        }

        // ── ReflectionNamedType ───────────────────────────
        if ($type instanceof ReflectionNamedType) {
            echo "    Reflection type: ReflectionNamedType\n";
            echo "    getName():       " . $type->getName() . "\n";
            echo "    isBuiltin():     " . ($type->isBuiltin() ? 'true' : 'false') . "\n";
            echo "    allowsNull():    " . ($type->allowsNull() ? 'true' : 'false') . "\n";
            echo "    isOptional():    " . ($param->isOptional() ? 'true' : 'false') . "\n";

            if (!$type->isBuiltin()) {
                // Interface or class — can auto-wire
                if ($param->isOptional()) {
                    echo "    Container action: resolve from container if binding exists,\n";
                    echo "                      else use default value (often null or NullObject).\n";
                } else {
                    echo "    Container action: ✓ resolve " . $type->getName() . " from container.\n";
                }
            } else {
                // Scalar type
                if ($param->isOptional()) {
                    $default = $param->hasDefaultValue()
                        ? json_encode($param->getDefaultValue())
                        : 'null';
                    echo "    Container action: use default value ({$default}), skip resolution.\n";
                } else {
                    echo "    Container action: ⚠ FAIL — primitive required param cannot be auto-wired.\n";
                    echo "    Fix: register an explicit factory or use \$container->instance() for this class.\n";
                }
            }
            continue;
        }

        // ── ReflectionUnionType ───────────────────────────
        if ($type instanceof ReflectionUnionType) {
            $typeNames = implode('|', array_map(fn($t) => $t->getName(), $type->getTypes()));
            echo "    Reflection type: ReflectionUnionType ({$typeNames})\n";
            echo "    Container action: ⚠ AMBIGUOUS — cannot auto-wire union types.\n";
            if ($param->isOptional()) {
                echo "                      Use default value if available.\n";
            } else {
                echo "                      Fix: register an explicit factory for this class.\n";
            }
            continue;
        }

        // ── ReflectionIntersectionType ────────────────────
        if ($type instanceof ReflectionIntersectionType) {
            $typeNames = implode('&', array_map(fn($t) => $t->getName(), $type->getTypes()));
            echo "    Reflection type: ReflectionIntersectionType ({$typeNames})\n";
            echo "    Container action: ⚠ needs explicit binding — intersection types\n";
            echo "                      are uncommon and require manual factory definition.\n";
            continue;
        }
    }

    echo "\n";
}


// ─────────────────────────────────────────────────────────────────────────────
// Run analysis on all edge case classes
// ─────────────────────────────────────────────────────────────────────────────

echo "Edge case analysis:\n\n";

analyseConstructor(CaseA_NoConstructor::class);
analyseConstructor(CaseB_EmptyConstructor::class);
analyseConstructor(CaseC_NoTypeHint::class);
analyseConstructor(CaseD_Nullable::class);
analyseConstructor(CaseE_Union::class);
analyseConstructor(CaseF_Intersection::class);
analyseConstructor(CaseG_OptionalWithDefault::class);
analyseConstructor(CaseH_Mixed::class);


// ─────────────────────────────────────────────────────────────────────────────
// Summary: what an auto-wiring container does with each case
// ─────────────────────────────────────────────────────────────────────────────

echo "── Summary: container decision for each case ─────────\n\n";
echo "  Case A — No constructor:        new ClassName() directly.\n";
echo "  Case B — Empty constructor:     new ClassName() directly.\n";
echo "  Case C — No type hint:          FAIL (required) or use default (optional).\n";
echo "  Case D — Nullable (?Type):      resolve if bound; use null if not bound + optional.\n";
echo "  Case E — Union (int|string):    AMBIGUOUS — register explicit factory.\n";
echo "  Case F — Intersection:          needs explicit factory.\n";
echo "  Case G — Optional with default: if binding exists, inject it; else use default.\n";
echo "  Case H — Mixed (interface+str): auto-wire the interface params;\n";
echo "                                  FAIL on required string — needs explicit factory.\n\n";

echo "── The key design lesson ────────────────────────────\n\n";
echo "Course Philosophy Rule 3: The type system is a security layer.\n\n";
echo "When you type all constructor params as interfaces:\n";
echo "  → getType()->isBuiltin() = false for all of them\n";
echo "  → Container can auto-wire everything\n";
echo "  → Zero manual factories needed\n\n";
echo "When you use string/int params (e.g. DSN, API key, table name):\n";
echo "  → getType()->isBuiltin() = true\n";
echo "  → Container cannot auto-wire that param\n";
echo "  → Must register a factory definition (covered in Lesson 4.4)\n\n";
echo "The solution for primitive params:\n";
echo "  1. Register a factory: \$container->factory(MySQLDatabase::class,\n";
echo "                           fn() => new MySQLDatabase(getenv('DB_DSN')));\n";
echo "  2. OR: extract a typed config object: class DbConfig { string \$dsn; }\n";
echo "         The container can then auto-wire DbConfig as a dependency.\n";

echo "\n--- Recap ---\n";
echo "No/empty constructor:  no-args instantiation.\n";
echo "Named interface type:  auto-wirable — container resolves it.\n";
echo "Built-in scalar type:  NOT auto-wirable — explicit factory needed.\n";
echo "Nullable type:         auto-wire if bound, fall back to null if optional.\n";
echo "Union/intersection:    explicit factory needed.\n";
echo "Optional with default: use default if no binding — graceful fallback.\n";