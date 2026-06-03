<?php
declare(strict_types=1);

/**
 * CHALLENGE SOLUTION — Lesson 4.2: PHP Reflection API
 * ──────────────────────────────────────────────────────
 * ⚠️  Only open this file after completing starter.php yourself.
 *
 * Key things to compare in your solution:
 *   1. getConstructorDependencies() handles all eight cases correctly
 *   2. ReflectionCache caches ReflectionClass instances per class name
 *   3. All assertions pass
 *   4. Cached version is measurably faster in the benchmark
 */


// ─────────────────────────────────────────────────────────────────────────────
// Interfaces and test classes — unchanged from starter
// ─────────────────────────────────────────────────────────────────────────────

interface DatabaseInterface  { public function query(string $sql): array; }
interface LoggerInterface    { public function log(string $m): void; }
interface MailerInterface    { public function send(string $to): bool; }
interface CacheInterface     { public function get(string $k): mixed; }
interface Countable2         { public function count(): int; }
interface Iterable2          { public function items(): array; }

class FullyAutoWirable {
    public function __construct(
        private DatabaseInterface $db,
        private LoggerInterface   $logger,
        private MailerInterface   $mailer
    ) {}
}
class NoConstructor  { public function doWork(): void {} }
class EmptyConstructor { public function __construct() {} }
class PrimitiveParams {
    public function __construct(
        private string $dsn,
        private int    $port,
        private LoggerInterface $logger
    ) {}
}
class OptionalLogger {
    public function __construct(
        private DatabaseInterface $db,
        private ?LoggerInterface  $logger = null
    ) {}
}
class PrimitiveWithDefault {
    public function __construct(
        private DatabaseInterface $db,
        private string            $tableName = 'default_table',
        private int               $timeout   = 30
    ) {}
}
class UnionType {
    public function __construct(
        private int|string        $id,
        private DatabaseInterface $db
    ) {}
}
class MissingTypeHint {
    public function __construct(
        $untyped,
        private LoggerInterface $logger
    ) {}
}


// ─────────────────────────────────────────────────────────────────────────────
// Task 1 — getConstructorDependencies()
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Returns an array of dependency descriptors for the given class's constructor.
 *
 * @return array<int, array{
 *   param: string,
 *   type: string,
 *   builtin: bool,
 *   optional: bool,
 *   nullable: bool,
 *   auto: bool
 * }>
 */
function getConstructorDependencies(string $className): array {
    $ref  = new ReflectionClass($className);
    $ctor = $ref->getConstructor();

    // No constructor or empty constructor — no dependencies
    if ($ctor === null || count($ctor->getParameters()) === 0) {
        return [];
    }

    $deps = [];

    foreach ($ctor->getParameters() as $param) {
        $type = $param->getType();

        // ── No type hint ──────────────────────────────────────────────────────
        if ($type === null) {
            $deps[] = [
                'param'    => $param->getName(),
                'type'     => 'none',
                'builtin'  => false,
                'optional' => $param->isOptional(),
                'nullable' => false,
                'auto'     => false,  // untyped params cannot be auto-wired
            ];
            continue;
        }

        // ── ReflectionNamedType — single type (most common case) ─────────────
        if ($type instanceof ReflectionNamedType) {
            $isBuiltin = $type->isBuiltin();
            $deps[] = [
                'param'    => $param->getName(),
                'type'     => $type->getName(),
                'builtin'  => $isBuiltin,
                'optional' => $param->isOptional(),
                'nullable' => $type->allowsNull(),
                // auto = true only for non-builtin (interface/class) types
                'auto'     => !$isBuiltin,
            ];
            continue;
        }

        // ── ReflectionUnionType — int|string, DatabaseInterface|null, etc. ───
        if ($type instanceof ReflectionUnionType) {
            $typeNames = implode('|', array_map(fn($t) => $t->getName(), $type->getTypes()));
            $deps[] = [
                'param'    => $param->getName(),
                'type'     => $typeNames,
                'builtin'  => false,
                'optional' => $param->isOptional(),
                'nullable' => in_array('null', array_map(fn($t) => $t->getName(), $type->getTypes())),
                'auto'     => false,  // union types are ambiguous — cannot auto-wire
            ];
            continue;
        }

        // ── ReflectionIntersectionType — Countable&Traversable, etc. ─────────
        if ($type instanceof ReflectionIntersectionType) {
            $typeNames = implode('&', array_map(fn($t) => $t->getName(), $type->getTypes()));
            $deps[] = [
                'param'    => $param->getName(),
                'type'     => $typeNames,
                'builtin'  => false,
                'optional' => $param->isOptional(),
                'nullable' => false,
                'auto'     => false,  // intersection types need explicit factory
            ];
            continue;
        }
    }

    return $deps;
}


// ─────────────────────────────────────────────────────────────────────────────
// Task 2 — ReflectionCache
// ─────────────────────────────────────────────────────────────────────────────

class ReflectionCache {
    /** @var array<string, ReflectionClass> */
    private array $classCache = [];

    /** @var array<string, ReflectionParameter[]> */
    private array $paramCache = [];

    /** @var array<string, array<string, array{type: string, optional: bool, nullable: bool}>> */
    private array $depsCache  = [];

    /**
     * Get a cached ReflectionClass — created at most once per class name.
     */
    public function getClass(string $className): ReflectionClass {
        if (!isset($this->classCache[$className])) {
            $this->classCache[$className] = new ReflectionClass($className);
        }
        return $this->classCache[$className];
    }

    /**
     * Get cached constructor parameters.
     * Returns [] if no constructor.
     *
     * @return ReflectionParameter[]
     */
    public function getConstructorParams(string $className): array {
        if (!isset($this->paramCache[$className])) {
            $ref  = $this->getClass($className);
            $ctor = $ref->getConstructor();
            $this->paramCache[$className] = $ctor ? $ctor->getParameters() : [];
        }
        return $this->paramCache[$className];
    }

    /**
     * Get only the auto-wirable dependency type names for a class.
     * Skips built-in types, union types, intersection types, and untyped params.
     *
     * @return array<string, array{type: string, optional: bool, nullable: bool}>
     */
    public function getResolvableDeps(string $className): array {
        if (!isset($this->depsCache[$className])) {
            $params = $this->getConstructorParams($className);
            $deps   = [];

            foreach ($params as $param) {
                $type = $param->getType();
                if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                    $deps[$param->getName()] = [
                        'type'     => $type->getName(),
                        'optional' => $param->isOptional(),
                        'nullable' => $type->allowsNull(),
                    ];
                }
            }

            $this->depsCache[$className] = $deps;
        }

        return $this->depsCache[$className];
    }

    /**
     * True if the class can be instantiated (not abstract, not interface).
     */
    public function isInstantiable(string $className): bool {
        return $this->getClass($className)->isInstantiable();
    }

    public function stats(): array {
        return [
            'classes_cached' => count($this->classCache),
            'params_cached'  => count($this->paramCache),
        ];
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Task 3 — Assertions
// ─────────────────────────────────────────────────────────────────────────────

function assert_equal(mixed $expected, mixed $actual, string $label): void {
    $pass = $expected === $actual;
    echo ($pass ? '  ✓' : '  ✗') . " {$label}";
    if (!$pass) {
        echo "\n    Expected: " . json_encode($expected);
        echo "\n    Got:      " . json_encode($actual);
    }
    echo "\n";
}

function runAssertions(): void {
    echo "── Assertions ────────────────────────────────────────\n\n";

    // Test 1
    $deps = getConstructorDependencies(FullyAutoWirable::class);
    assert_equal(3, count($deps), 'FullyAutoWirable: 3 params');
    assert_equal(true,  $deps[0]['auto'],     'FullyAutoWirable: param[0] auto=true');
    assert_equal(false, $deps[0]['builtin'],  'FullyAutoWirable: param[0] builtin=false');
    assert_equal(false, $deps[0]['optional'], 'FullyAutoWirable: param[0] optional=false');

    // Test 2
    assert_equal([], getConstructorDependencies(NoConstructor::class), 'NoConstructor: returns []');

    // Test 3
    assert_equal([], getConstructorDependencies(EmptyConstructor::class), 'EmptyConstructor: returns []');

    // Test 4
    $deps4 = getConstructorDependencies(PrimitiveParams::class);
    assert_equal(3,     count($deps4),         'PrimitiveParams: 3 params');
    assert_equal(true,  $deps4[0]['builtin'],   'PrimitiveParams: param[0] builtin=true (string)');
    assert_equal(false, $deps4[0]['auto'],      'PrimitiveParams: param[0] auto=false');
    assert_equal(false, $deps4[2]['builtin'],   'PrimitiveParams: param[2] builtin=false (interface)');
    assert_equal(true,  $deps4[2]['auto'],      'PrimitiveParams: param[2] auto=true');

    // Test 5
    $deps5 = getConstructorDependencies(OptionalLogger::class);
    assert_equal(2,     count($deps5),          'OptionalLogger: 2 params');
    assert_equal(false, $deps5[0]['optional'],  'OptionalLogger: param[0] optional=false');
    assert_equal(true,  $deps5[1]['optional'],  'OptionalLogger: param[1] optional=true');
    assert_equal(true,  $deps5[1]['nullable'],  'OptionalLogger: param[1] nullable=true');
    assert_equal(true,  $deps5[1]['auto'],      'OptionalLogger: param[1] auto=true (interface)');

    // Test 6
    $deps6 = getConstructorDependencies(PrimitiveWithDefault::class);
    assert_equal(3,     count($deps6),          'PrimitiveWithDefault: 3 params');
    assert_equal(false, $deps6[0]['optional'],  'PrimitiveWithDefault: param[0] optional=false');
    assert_equal(true,  $deps6[1]['optional'],  'PrimitiveWithDefault: param[1] optional=true');
    assert_equal(true,  $deps6[1]['builtin'],   'PrimitiveWithDefault: param[1] builtin=true');

    // Test 7
    $deps7 = getConstructorDependencies(UnionType::class);
    assert_equal(2,     count($deps7),          'UnionType: 2 params');
    assert_equal(false, $deps7[0]['auto'],      'UnionType: param[0] auto=false (union)');
    assert_equal(true,  $deps7[1]['auto'],      'UnionType: param[1] auto=true (interface)');

    // Test 8
    $deps8 = getConstructorDependencies(MissingTypeHint::class);
    assert_equal(2,      count($deps8),         'MissingTypeHint: 2 params');
    assert_equal('none', $deps8[0]['type'],     'MissingTypeHint: param[0] type=none');
    assert_equal(false,  $deps8[0]['auto'],     'MissingTypeHint: param[0] auto=false');
    assert_equal(true,   $deps8[1]['auto'],     'MissingTypeHint: param[1] auto=true');

    echo "\n";
}

runAssertions();


// ─────────────────────────────────────────────────────────────────────────────
// Task 4 — Benchmark
// ─────────────────────────────────────────────────────────────────────────────

echo "── Benchmark ─────────────────────────────────────────\n\n";

$iterations = 1000;
$classes = [
    FullyAutoWirable::class,
    PrimitiveParams::class,
    OptionalLogger::class,
    UnionType::class,
];

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    foreach ($classes as $class) {
        getConstructorDependencies($class);
    }
}
$uncached = round((microtime(true) - $start) * 1000, 2);
echo "Without cache: {$uncached}ms\n";

$cache = new ReflectionCache();
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    foreach ($classes as $class) {
        $cache->getResolvableDeps($class);
    }
}
$cached = round((microtime(true) - $start) * 1000, 2);
echo "With cache:    {$cached}ms\n";
echo "Cache stats:   " . json_encode($cache->stats()) . "\n";

$faster = $uncached > $cached ? 'YES' : 'NO';
echo "Cached faster: {$faster}\n\n";

echo "Note: On first run, getConstructorDependencies() is comparable to ReflectionCache\n";
echo "because PHP opcache may have warmed the reflection data already.\n";
echo "The cache advantage is most visible when resolving the same class many times\n";
echo "across many requests in a long-running process.\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// SELF-REVIEW CHECKLIST
// ─────────────────────────────────────────────────────────────────────────────
echo "--- Self-review checklist ---\n";
echo "[ ] No constructor and empty constructor both return []?\n";
echo "[ ] ReflectionNamedType with isBuiltin()=false → auto=true?\n";
echo "[ ] ReflectionNamedType with isBuiltin()=true  → auto=false, builtin=true?\n";
echo "[ ] Nullable (?Type) → nullable=true, auto=true (still an interface)?\n";
echo "[ ] Union type → auto=false?\n";
echo "[ ] No type hint → type='none', auto=false?\n";
echo "[ ] Optional param → optional=true?\n";
echo "[ ] All eight assertion groups pass?\n";
echo "[ ] ReflectionCache::getClass() caches — same instance returned on second call?\n";
echo "[ ] Cache stats show classes_cached = number of distinct classes reflected?\n";