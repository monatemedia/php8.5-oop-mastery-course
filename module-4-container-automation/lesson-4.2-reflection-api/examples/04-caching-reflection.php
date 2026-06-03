<?php
declare(strict_types=1);

/**
 * Example 04 — Caching Reflection Results
 * -----------------------------------------
 * ReflectionClass is not free. Every call reads PHP's class metadata,
 * creates PHP objects, and touches opcache. In a high-throughput application
 * resolving the same class thousands of times per second, uncached reflection
 * becomes a measurable bottleneck.
 *
 * This example shows:
 *   A. The cost of repeated ReflectionClass calls (benchmark)
 *   B. An in-memory reflection cache (the standard approach)
 *   C. How PHP-DI's compiled container eliminates reflection entirely
 *   D. A complete ReflectionCache class ready to use in Lesson 4.3
 *
 * Course Philosophy Rule 1: Config belongs at the entry point.
 * The reflection cache belongs in the container infrastructure layer —
 * not inside business logic classes. It is an internal detail of how
 * the container works, not something services need to know about.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Caching Reflection Results                         ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Sample service graph (same as Lesson 4.1)
// ─────────────────────────────────────────────────────────────────────────────

interface DatabaseInterface   { public function query(string $sql): array; }
interface LoggerInterface     { public function log(string $m): void; }
interface MailerInterface     { public function send(string $to): bool; }
interface CacheInterface      { public function get(string $k): mixed; }

class OrderService {
    public function __construct(
        private DatabaseInterface $db,
        private MailerInterface   $mailer,
        private LoggerInterface   $logger
    ) {}
}

class ProductRepository {
    public function __construct(
        private DatabaseInterface $db,
        private CacheInterface    $cache,
        private LoggerInterface   $logger
    ) {}
}

class UserRepository {
    public function __construct(
        private DatabaseInterface $db,
        private LoggerInterface   $logger
    ) {}
}

class ReportService {
    public function __construct(
        private ProductRepository $products,
        private UserRepository    $users,
        private LoggerInterface   $logger
    ) {}
}


// ═══════════════════════════════════════════════════════════
// PART A — The cost of uncached reflection
// ═══════════════════════════════════════════════════════════

echo "── Part A: Cost of uncached reflection ──────────────\n\n";

function reflectWithoutCache(string $class): array {
    // Every call creates new ReflectionClass and ReflectionParameter objects
    $ref    = new ReflectionClass($class);
    $ctor   = $ref->getConstructor();
    $deps   = [];

    if ($ctor !== null) {
        foreach ($ctor->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $deps[] = $type->getName();
            }
        }
    }
    return $deps;
}

$iterations = 1000;
$classes    = [OrderService::class, ProductRepository::class, UserRepository::class, ReportService::class];

// Without cache
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    foreach ($classes as $class) {
        reflectWithoutCache($class);
    }
}
$uncachedMs = round((microtime(true) - $start) * 1000, 2);

echo "Reflecting {$iterations} times on " . count($classes) . " classes WITHOUT cache:\n";
echo "  Time: {$uncachedMs}ms\n\n";


// ═══════════════════════════════════════════════════════════
// PART B — In-memory reflection cache
// ═══════════════════════════════════════════════════════════

echo "── Part B: In-memory reflection cache ───────────────\n\n";

class ReflectionCache {
    /** @var array<string, ReflectionClass> */
    private array $classCache = [];

    /** @var array<string, ReflectionParameter[]> */
    private array $paramCache = [];

    /**
     * Get a cached ReflectionClass instance.
     * Created once per class name per container lifetime.
     */
    public function getClass(string $className): ReflectionClass {
        if (!isset($this->classCache[$className])) {
            $this->classCache[$className] = new ReflectionClass($className);
        }
        return $this->classCache[$className];
    }

    /**
     * Get the constructor parameters for a class.
     * Returns empty array if no constructor.
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
     * Skips built-in types and parameters with no type hint.
     *
     * @return array<string, array{type: string, optional: bool}>
     */
    public function getResolvableDeps(string $className): array {
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

        return $deps;
    }

    /**
     * Check if a class is instantiable (not abstract, not interface).
     */
    public function isInstantiable(string $className): bool {
        return $this->getClass($className)->isInstantiable();
    }

    /** Statistics for debugging */
    public function stats(): array {
        return [
            'classes_cached' => count($this->classCache),
            'params_cached'  => count($this->paramCache),
        ];
    }
}

$cache = new ReflectionCache();

// With cache — same work, but each class reflected only once
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    foreach ($classes as $class) {
        $cache->getResolvableDeps($class);
    }
}
$cachedMs = round((microtime(true) - $start) * 1000, 2);

echo "Reflecting {$iterations} times on " . count($classes) . " classes WITH cache:\n";
echo "  Time: {$cachedMs}ms\n\n";

$speedup = $uncachedMs > 0 ? round($uncachedMs / max($cachedMs, 0.01), 1) : 'N/A';
echo "Speed-up: ~{$speedup}x faster\n";
echo "Cache stats: " . json_encode($cache->stats()) . "\n\n";

// Demonstrate what was cached
echo "Cached dependency maps:\n";
foreach ($classes as $class) {
    $short = (fn($c) => end(explode('\\', $c)))($class);
    $deps  = $cache->getResolvableDeps($class);
    echo "  {$short}: " . implode(', ', array_column($deps, 'type')) . "\n";
}


// ═══════════════════════════════════════════════════════════
// PART C — How PHP-DI's compiled container works
// ═══════════════════════════════════════════════════════════

echo "\n── Part C: PHP-DI compiled container (preview) ──────\n\n";

echo "In-memory cache:         reflection runs once per container lifetime.\n";
echo "  Resets on every request (PHP-FPM) — run again next request.\n\n";

echo "PHP-DI compiled container: reflection runs ONCE during compilation.\n";
echo "  Result: a plain PHP file with zero reflection calls.\n\n";

echo "What a compiled binding looks like:\n\n";
echo "  // Generated by PHP-DI compilation — no Reflection at runtime:\n";
echo "  class CompiledContainer {\n";
echo "      public function getOrderService(): OrderService {\n";
echo "          return \$this->orderService ?? \$this->orderService = new OrderService(\n";
echo "              \$this->getDatabaseInterface(),\n";
echo "              \$this->getMailerInterface(),\n";
echo "              \$this->getLoggerInterface()\n";
echo "          );\n";
echo "      }\n";
echo "      // ... one method per service\n";
echo "  }\n\n";

echo "Production recommendation:\n";
echo "  Development: use ContainerBuilder without compilation\n";
echo "               (reflects fresh each request — picks up changes immediately)\n\n";
echo "  Production:  use ContainerBuilder::enableCompilation('/var/cache')\n";
echo "               (compiles once on deploy — zero reflection overhead)\n\n";
echo "  Lesson 4.4 covers ContainerBuilder::enableCompilation() in detail.\n\n";


// ═══════════════════════════════════════════════════════════
// PART D — ReflectionCache integrated with a container sketch
// ═══════════════════════════════════════════════════════════

echo "── Part D: ReflectionCache inside a container ────────\n\n";

class ContainerWithCache {
    private array          $bindings   = [];
    private array          $singletons = [];
    private array          $instances  = [];
    private ReflectionCache $refCache;

    public function __construct() {
        $this->refCache = new ReflectionCache();
    }

    public function singleton(string $id, callable $factory): void {
        $this->bindings[$id]   = $factory;
        $this->singletons[$id] = true;
    }

    public function get(string $id): mixed {
        if (isset($this->instances[$id])) return $this->instances[$id];
        if (!isset($this->bindings[$id])) throw new \RuntimeException("Not bound: {$id}");
        $result = ($this->bindings[$id])($this);
        if ($this->singletons[$id] ?? false) $this->instances[$id] = $result;
        return $result;
    }

    public function has(string $id): bool {
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }

    /**
     * Attempt to auto-resolve a class using the reflection cache.
     * Falls back to explicit binding if not registered.
     */
    public function autoResolve(string $class): object {
        if ($this->has($class)) return $this->get($class);
        if (!$this->refCache->isInstantiable($class)) {
            throw new \RuntimeException("{$class} is not instantiable — register an explicit binding.");
        }

        $deps     = $this->refCache->getResolvableDeps($class);
        $resolved = [];
        foreach ($deps as $paramName => $info) {
            if ($this->has($info['type'])) {
                $resolved[] = $this->get($info['type']);
            } elseif ($info['optional']) {
                $resolved[] = null;
            } else {
                throw new \RuntimeException(
                    "Cannot resolve '{$info['type']}' for \${$paramName} in {$class}. " .
                    "Register a binding with \$container->singleton('{$info['type']}', ...)."
                );
            }
        }

        $ref = $this->refCache->getClass($class);
        return $ref->newInstanceArgs($resolved);
    }

    public function cacheStats(): array {
        return $this->refCache->stats();
    }
}

// Build a container with a few concrete implementations
class InMemDb implements DatabaseInterface {
    public function query(string $sql): array { return []; }
}
class ConsoleLogger implements LoggerInterface {
    public function log(string $m): void { echo "  [LOG] {$m}\n"; }
}
class ConsoleMailer implements MailerInterface {
    public function send(string $to): bool {
        echo "  [MAIL] To: {$to}\n";
        return true;
    }
}
class ArrayCache implements CacheInterface {
    public function get(string $k): mixed { return null; }
}

$cachedContainer = new ContainerWithCache();
$cachedContainer->singleton(DatabaseInterface::class, fn($c) => new InMemDb());
$cachedContainer->singleton(LoggerInterface::class,   fn($c) => new ConsoleLogger());
$cachedContainer->singleton(MailerInterface::class,   fn($c) => new ConsoleMailer());
$cachedContainer->singleton(CacheInterface::class,    fn($c) => new ArrayCache());

echo "Auto-resolving OrderService using cached reflection:\n";
$orderService = $cachedContainer->autoResolve(OrderService::class);
echo "  Resolved: " . get_class($orderService) . "\n\n";

echo "Auto-resolving ProductRepository:\n";
$productRepo = $cachedContainer->autoResolve(ProductRepository::class);
echo "  Resolved: " . get_class($productRepo) . "\n\n";

echo "Cache stats after two resolutions:\n";
echo "  " . json_encode($cachedContainer->cacheStats()) . "\n\n";

echo "Auto-resolving ReportService (4-level graph):\n";
// First we need to register the repos since they are concrete classes resolved inline
$report = $cachedContainer->autoResolve(ReportService::class);
echo "  Resolved: " . get_class($report) . "\n";

echo "\n--- Recap ---\n";
echo "Uncached reflection: new ReflectionClass() every resolution — adds up at scale.\n";
echo "In-memory cache: reflect once per container lifetime — standard approach.\n";
echo "Compiled container: reflect once on deploy — zero overhead in production.\n";
echo "ReflectionCache class: the component Lesson 4.3 builds into the auto-wiring container.\n";
echo "Rule 1 connection: caching is infrastructure — it lives inside the container, not in services.\n";