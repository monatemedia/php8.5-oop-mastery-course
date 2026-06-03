<?php
declare(strict_types=1);

/**
 * CHALLENGE STARTER — Lesson 4.2: PHP Reflection API
 * ────────────────────────────────────────────────────
 * Read CHALLENGE.md before touching this file.
 *
 * Complete getConstructorDependencies() and ReflectionCache.
 * All eight assertions must pass.
 *
 * Do NOT look at solution.php until you have made a genuine attempt.
 */


// ─────────────────────────────────────────────────────────────────────────────
// Interfaces and test classes — do not modify these
// ─────────────────────────────────────────────────────────────────────────────

interface DatabaseInterface  { public function query(string $sql): array; }
interface LoggerInterface    { public function log(string $m): void; }
interface MailerInterface    { public function send(string $to): bool; }
interface CacheInterface     { public function get(string $k): mixed; }

interface Countable2  { public function count(): int; }
interface Iterable2   { public function items(): array; }

// Test class 1: All interface params — fully auto-wirable
class FullyAutoWirable {
    public function __construct(
        private DatabaseInterface $db,
        private LoggerInterface   $logger,
        private MailerInterface   $mailer
    ) {}
}

// Test class 2: No constructor
class NoConstructor {
    public function doWork(): void {}
}

// Test class 3: Empty constructor
class EmptyConstructor {
    public function __construct() {}
}

// Test class 4: Primitive required params — NOT auto-wirable
class PrimitiveParams {
    public function __construct(
        private string $dsn,
        private int    $port,
        private LoggerInterface $logger
    ) {}
}

// Test class 5: Optional interface with null default
class OptionalLogger {
    public function __construct(
        private DatabaseInterface $db,
        private ?LoggerInterface  $logger = null
    ) {}
}

// Test class 6: Primitive with default (optional)
class PrimitiveWithDefault {
    public function __construct(
        private DatabaseInterface $db,
        private string            $tableName = 'default_table',
        private int               $timeout   = 30
    ) {}
}

// Test class 7: Union type
class UnionType {
    public function __construct(
        private int|string        $id,
        private DatabaseInterface $db
    ) {}
}

// Test class 8: No type hint on one param
class MissingTypeHint {
    public function __construct(
        $untyped,                          // ← no type hint
        private LoggerInterface $logger
    ) {}
}


// ─────────────────────────────────────────────────────────────────────────────
// TODO Task 1 — Complete this function
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Returns an array of dependency descriptors for the given class's constructor.
 *
 * Each descriptor:
 * [
 *   'param'    => string,  // parameter name
 *   'type'     => string,  // type name (or 'none' / 'union' / 'intersection')
 *   'builtin'  => bool,    // true for scalar types
 *   'optional' => bool,    // true if has a default value
 *   'nullable' => bool,    // true if ?Type
 *   'auto'     => bool,    // true if container can auto-wire this param
 * ]
 *
 * Returns [] if the class has no constructor or an empty constructor.
 */
function getConstructorDependencies(string $className): array {
    // TODO: implement this function
    // Hints:
    //   $ref  = new ReflectionClass($className);
    //   $ctor = $ref->getConstructor();
    //   foreach ($ctor->getParameters() as $param) { ... }

    return []; // placeholder — remove this line
}


// ─────────────────────────────────────────────────────────────────────────────
// TODO Task 2 — Complete the ReflectionCache class
// ─────────────────────────────────────────────────────────────────────────────

class ReflectionCache {
    private array $classCache = [];
    private array $paramCache = [];
    private array $depsCache  = [];

    // TODO: implement getClass(string $className): ReflectionClass
    // Cache the ReflectionClass instance — create it at most once per class name
    public function getClass(string $className): ReflectionClass {
        throw new \RuntimeException('Not implemented');
    }

    // TODO: implement getConstructorParams(string $className): array
    // Returns ReflectionParameter[] — cached after first call
    public function getConstructorParams(string $className): array {
        throw new \RuntimeException('Not implemented');
    }

    // TODO: implement getResolvableDeps(string $className): array
    // Returns only the auto-wirable deps (non-builtin named types)
    // Format: ['paramName' => ['type' => '...', 'optional' => bool, 'nullable' => bool]]
    public function getResolvableDeps(string $className): array {
        throw new \RuntimeException('Not implemented');
    }

    // TODO: implement isInstantiable(string $className): bool
    public function isInstantiable(string $className): bool {
        throw new \RuntimeException('Not implemented');
    }

    public function stats(): array {
        return [
            'classes_cached' => count($this->classCache),
            'params_cached'  => count($this->paramCache),
        ];
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Task 3 — Assertions (all eight must pass)
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

    // Test 1: FullyAutoWirable — three interface deps, all auto = true
    $deps = getConstructorDependencies(FullyAutoWirable::class);
    assert_equal(3, count($deps), 'FullyAutoWirable: 3 params');
    assert_equal(true, $deps[0]['auto'] ?? false, 'FullyAutoWirable: param[0] auto=true');
    assert_equal(false, $deps[0]['builtin'] ?? true, 'FullyAutoWirable: param[0] builtin=false');
    assert_equal(false, $deps[0]['optional'] ?? true, 'FullyAutoWirable: param[0] optional=false');

    // Test 2: NoConstructor — returns []
    $deps2 = getConstructorDependencies(NoConstructor::class);
    assert_equal([], $deps2, 'NoConstructor: returns []');

    // Test 3: EmptyConstructor — returns []
    $deps3 = getConstructorDependencies(EmptyConstructor::class);
    assert_equal([], $deps3, 'EmptyConstructor: returns []');

    // Test 4: PrimitiveParams — first two are builtin/not-auto
    $deps4 = getConstructorDependencies(PrimitiveParams::class);
    assert_equal(3, count($deps4), 'PrimitiveParams: 3 params');
    assert_equal(true, $deps4[0]['builtin'] ?? false, 'PrimitiveParams: param[0] builtin=true (string)');
    assert_equal(false, $deps4[0]['auto'] ?? true, 'PrimitiveParams: param[0] auto=false');
    assert_equal(false, $deps4[2]['builtin'] ?? true, 'PrimitiveParams: param[2] builtin=false (interface)');
    assert_equal(true, $deps4[2]['auto'] ?? false, 'PrimitiveParams: param[2] auto=true');

    // Test 5: OptionalLogger — second param optional and nullable
    $deps5 = getConstructorDependencies(OptionalLogger::class);
    assert_equal(2, count($deps5), 'OptionalLogger: 2 params');
    assert_equal(false, $deps5[0]['optional'] ?? true, 'OptionalLogger: param[0] optional=false');
    assert_equal(true, $deps5[1]['optional'] ?? false, 'OptionalLogger: param[1] optional=true');
    assert_equal(true, $deps5[1]['nullable'] ?? false, 'OptionalLogger: param[1] nullable=true');
    assert_equal(true, $deps5[1]['auto'] ?? false, 'OptionalLogger: param[1] auto=true (interface)');

    // Test 6: PrimitiveWithDefault — primitive params with defaults are optional
    $deps6 = getConstructorDependencies(PrimitiveWithDefault::class);
    assert_equal(3, count($deps6), 'PrimitiveWithDefault: 3 params');
    assert_equal(false, $deps6[0]['optional'] ?? true, 'PrimitiveWithDefault: param[0] optional=false');
    assert_equal(true, $deps6[1]['optional'] ?? false, 'PrimitiveWithDefault: param[1] optional=true');
    assert_equal(true, $deps6[1]['builtin'] ?? false, 'PrimitiveWithDefault: param[1] builtin=true');

    // Test 7: UnionType — union param has auto=false
    $deps7 = getConstructorDependencies(UnionType::class);
    assert_equal(2, count($deps7), 'UnionType: 2 params');
    assert_equal(false, $deps7[0]['auto'] ?? true, 'UnionType: param[0] auto=false (union)');
    assert_equal(true, $deps7[1]['auto'] ?? false, 'UnionType: param[1] auto=true (interface)');

    // Test 8: MissingTypeHint — untyped param has auto=false
    $deps8 = getConstructorDependencies(MissingTypeHint::class);
    assert_equal(2, count($deps8), 'MissingTypeHint: 2 params');
    assert_equal('none', $deps8[0]['type'] ?? '', 'MissingTypeHint: param[0] type=none');
    assert_equal(false, $deps8[0]['auto'] ?? true, 'MissingTypeHint: param[0] auto=false');
    assert_equal(true, $deps8[1]['auto'] ?? false, 'MissingTypeHint: param[1] auto=true');

    echo "\n";
}

runAssertions();


// ─────────────────────────────────────────────────────────────────────────────
// Task 4 — Benchmark: with vs without cache
// ─────────────────────────────────────────────────────────────────────────────

echo "── Benchmark ─────────────────────────────────────────\n\n";

$iterations = 1000;
$classes = [
    FullyAutoWirable::class,
    PrimitiveParams::class,
    OptionalLogger::class,
    UnionType::class,
];

// Without cache
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    foreach ($classes as $class) {
        getConstructorDependencies($class);
    }
}
$uncached = round((microtime(true) - $start) * 1000, 2);
echo "Without cache: {$uncached}ms\n";

// With ReflectionCache
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
echo "(Cached version should reflect each class only once)\n";