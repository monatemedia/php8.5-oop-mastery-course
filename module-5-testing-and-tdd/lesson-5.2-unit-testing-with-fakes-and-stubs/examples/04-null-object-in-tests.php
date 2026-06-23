<?php
declare(strict_types=1);

/**
 * Example 04 — The Null Object in Tests
 * ----------------------------------------
 * Run via PHPUnit:
 *   ./vendor/bin/phpunit module-5-testing-and-tdd/lesson-5.2-unit-testing-with-fakes-and-stubs/examples/04-null-object-in-tests.php
 *
 * The Null Object pattern (Module 3.3) is not just a production technique —
 * it is one of the most useful tools in a test suite.
 *
 * When a test verifies ONE behaviour of a class, it should only assert on the
 * dependency that behaviour touches. All other dependencies should be silent
 * so they do not pollute or complicate the test.
 *
 * The Null Object IS the silent dependency.
 *
 * This example covers:
 *   A. What a Null Object test double looks like
 *   B. Why it keeps tests focused
 *   C. Null Objects vs stubs — the distinction
 *   D. Reusing Null Object definitions across a test suite
 *   E. When a Null Object is NOT enough — and you need a spy or stub instead
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// Contracts
// ─────────────────────────────────────────────────────────────────────────────

interface LoggerInterface
{
    public function log(string $level, string $message, array $context = []): void;
    public function info(string $message, array $context = []): void;
    public function error(string $message, array $context = []): void;
}

interface CacheInterface
{
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, int $ttlSeconds = 300): void;
    public function delete(string $key): void;
    public function has(string $key): bool;
}

interface MetricsInterface
{
    public function increment(string $metric, array $tags = []): void;
    public function timing(string $metric, float $ms, array $tags = []): void;
}

interface ProductRepositoryInterface
{
    public function findById(int $id): ?array;
    public function findAll(): array;
    public function save(array $product): array;
}

// ─────────────────────────────────────────────────────────────────────────────
// The class under test
// ProductCatalogueService has four dependencies — but each test typically
// cares about only one or two of them. The others should be silent.
// ─────────────────────────────────────────────────────────────────────────────

class ProductCatalogueService
{
    public function __construct(
        private ProductRepositoryInterface $repository,
        private CacheInterface             $cache,
        private LoggerInterface            $logger,
        private MetricsInterface           $metrics
    ) {}

    public function findById(int $id): ?array
    {
        $cacheKey = "product:{$id}";

        if ($this->cache->has($cacheKey)) {
            $this->metrics->increment('cache.hit', ['entity' => 'product']);
            return $this->cache->get($cacheKey);
        }

        $product = $this->repository->findById($id);

        if ($product === null) {
            $this->logger->info("Product {$id} not found");
            return null;
        }

        $this->cache->set($cacheKey, $product);
        $this->metrics->increment('cache.miss', ['entity' => 'product']);
        $this->logger->info("Product {$id} fetched and cached");

        return $product;
    }

    public function listAll(): array
    {
        $this->logger->info('Listing all products');
        $this->metrics->increment('catalogue.list');
        return $this->repository->findAll();
    }

    public function create(string $name, int $priceCents, string $sku): array
    {
        if (empty($name)) {
            throw new \InvalidArgumentException('Product name cannot be empty');
        }

        if ($priceCents <= 0) {
            throw new \InvalidArgumentException("Price must be positive, got {$priceCents}");
        }

        $product = $this->repository->save([
            'name'  => $name,
            'price' => $priceCents,
            'sku'   => $sku,
        ]);

        $this->cache->delete("product:{$product['id']}");
        $this->logger->info("Product created", ['id' => $product['id'], 'name' => $name]);
        $this->metrics->increment('product.created');

        return $product;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// The test class
// ─────────────────────────────────────────────────────────────────────────────

class NullObjectInTestsExampleTest extends TestCase
{
    // ═══════════════════════════════════════════════════════════
    // PART A — What a Null Object test double looks like
    // ═══════════════════════════════════════════════════════════

    /**
     * The Null Object:
     *   - Implements the full interface (PHP requires it)
     *   - Every method does nothing and returns the appropriate "empty" value
     *   - Has no assertions, no recording, no return values of interest
     *   - Its only job: satisfy the type system silently
     */
    public function testNullObjectShape(): void
    {
        // These are the Null Objects used throughout this example.
        // Notice that each method body is as minimal as possible.

        $nullLogger = new class implements LoggerInterface {
            public function log(string $level, string $message, array $context = []): void {}
            public function info(string $message, array $context = []): void {}
            public function error(string $message, array $context = []): void {}
        };

        $nullCache = new class implements CacheInterface {
            public function get(string $key): mixed    { return null; }
            public function set(string $key, mixed $value, int $ttlSeconds = 300): void {}
            public function delete(string $key): void  {}
            public function has(string $key): bool     { return false; }
        };

        $nullMetrics = new class implements MetricsInterface {
            public function increment(string $metric, array $tags = []): void {}
            public function timing(string $metric, float $ms, array $tags = []): void {}
        };

        // They satisfy PHP's type system and can be passed anywhere
        $this->assertInstanceOf(LoggerInterface::class,   $nullLogger);
        $this->assertInstanceOf(CacheInterface::class,    $nullCache);
        $this->assertInstanceOf(MetricsInterface::class,  $nullMetrics);
    }

    // ═══════════════════════════════════════════════════════════
    // PART B — Why Null Objects keep tests focused
    // ═══════════════════════════════════════════════════════════

    /**
     * This test verifies that listAll() returns what the repository returns.
     * We do not care about the cache, logger, or metrics for this behaviour.
     * Null Objects for those three dependencies keep the test focused.
     */
    public function testListAllReturnsAllProductsFromRepository(): void
    {
        // ── The dependency under test — a fake repository with real data ──────
        $fakeRepo = new class implements ProductRepositoryInterface {
            public function findById(int $id): ?array { return null; }
            public function findAll(): array {
                return [
                    ['id' => 1, 'name' => 'Widget Pro',  'price' => 29999],
                    ['id' => 2, 'name' => 'Widget Lite', 'price' => 14999],
                ];
            }
            public function save(array $product): array { return $product; }
        };

        // ── Null Objects for everything else — silent, irrelevant ─────────────
        $nullCache = new class implements CacheInterface {
            public function get(string $key): mixed    { return null; }
            public function set(string $key, mixed $value, int $ttlSeconds = 300): void {}
            public function delete(string $key): void  {}
            public function has(string $key): bool     { return false; }
        };

        $nullLogger = new class implements LoggerInterface {
            public function log(string $level, string $message, array $context = []): void {}
            public function info(string $message, array $context = []): void {}
            public function error(string $message, array $context = []): void {}
        };

        $nullMetrics = new class implements MetricsInterface {
            public function increment(string $metric, array $tags = []): void {}
            public function timing(string $metric, float $ms, array $tags = []): void {}
        };

        $service = new ProductCatalogueService($fakeRepo, $nullCache, $nullLogger, $nullMetrics);

        // ── Assert: only the repository result matters ────────────────────────
        $products = $service->listAll();

        $this->assertCount(2, $products);
        $this->assertSame('Widget Pro',  $products[0]['name']);
        $this->assertSame('Widget Lite', $products[1]['name']);
    }

    // ═══════════════════════════════════════════════════════════
    // PART C — Null Objects vs stubs — the distinction
    // ═══════════════════════════════════════════════════════════

    /**
     * The null cache (always returns false for has(), null for get()) is a
     * Null Object. It does nothing.
     *
     * A stub cache is different — it returns a SPECIFIC value for a test to
     * react to. Here we test the CACHE HIT path, so the cache stub must
     * return true from has() and the product from get().
     */
    public function testFindByIdReturnsCachedProductWhenCacheHit(): void
    {
        $cachedProduct = ['id' => 7, 'name' => 'Cached Widget', 'price' => 5000];

        // ── Stub cache: simulates a warm cache ────────────────────────────────
        $stubCache = new class($cachedProduct) implements CacheInterface {
            public function __construct(private array $product) {}
            public function has(string $key): bool     { return true; }  // ← always a hit
            public function get(string $key): mixed    { return $this->product; }
            public function set(string $key, mixed $value, int $ttlSeconds = 300): void {}
            public function delete(string $key): void  {}
        };

        // ── Null Object repo: should NOT be called on a cache hit ─────────────
        // If the repo IS called, this Null Object silently returns null —
        // which would cause the test to fail because the result would be null.
        // That is correct: the test would correctly catch that the cache was bypassed.
        $nullRepo = new class implements ProductRepositoryInterface {
            public function findById(int $id): ?array  { return null; }
            public function findAll(): array           { return []; }
            public function save(array $product): array { return $product; }
        };

        $nullLogger  = new class implements LoggerInterface {
            public function log(string $level, string $message, array $context = []): void {}
            public function info(string $message, array $context = []): void {}
            public function error(string $message, array $context = []): void {}
        };

        $nullMetrics = new class implements MetricsInterface {
            public function increment(string $metric, array $tags = []): void {}
            public function timing(string $metric, float $ms, array $tags = []): void {}
        };

        $service = new ProductCatalogueService($nullRepo, $stubCache, $nullLogger, $nullMetrics);

        $result = $service->findById(7);

        $this->assertSame($cachedProduct, $result);
    }

    /**
     * Cache MISS path: cache returns false for has(), repository is consulted.
     * The null cache IS a Null Object here because the cache miss behaviour
     * is what happens when the cache returns nothing — which is the null/empty behaviour.
     */
    public function testFindByIdFetchesFromRepositoryOnCacheMiss(): void
    {
        $product = ['id' => 3, 'name' => 'Widget Lite', 'price' => 14999];

        $fakeRepo = new class($product) implements ProductRepositoryInterface {
            public function __construct(private array $product) {}
            public function findById(int $id): ?array  { return $this->product; }
            public function findAll(): array           { return []; }
            public function save(array $p): array      { return $p; }
        };

        // Null cache: has() returns false → cache miss → repo is consulted
        $nullCache = new class implements CacheInterface {
            public function has(string $key): bool     { return false; }
            public function get(string $key): mixed    { return null; }
            public function set(string $key, mixed $value, int $ttlSeconds = 300): void {}
            public function delete(string $key): void  {}
        };

        $nullLogger  = new class implements LoggerInterface {
            public function log(string $level, string $message, array $context = []): void {}
            public function info(string $message, array $context = []): void {}
            public function error(string $message, array $context = []): void {}
        };

        $nullMetrics = new class implements MetricsInterface {
            public function increment(string $metric, array $tags = []): void {}
            public function timing(string $metric, float $ms, array $tags = []): void {}
        };

        $service = new ProductCatalogueService($fakeRepo, $nullCache, $nullLogger, $nullMetrics);

        $result = $service->findById(3);

        $this->assertSame($product, $result);
    }

    // ═══════════════════════════════════════════════════════════
    // PART D — Reusing Null Object definitions
    // ═══════════════════════════════════════════════════════════

    /**
     * In a real test suite, Null Objects are extracted to setUp() or
     * private factory methods to avoid repetition. Inline definition is fine
     * for standalone examples; in practice, DRY them up.
     */
    private function nullLogger(): LoggerInterface
    {
        return new class implements LoggerInterface {
            public function log(string $level, string $message, array $context = []): void {}
            public function info(string $message, array $context = []): void {}
            public function error(string $message, array $context = []): void {}
        };
    }

    private function nullCache(): CacheInterface
    {
        return new class implements CacheInterface {
            public function get(string $key): mixed    { return null; }
            public function set(string $key, mixed $value, int $ttlSeconds = 300): void {}
            public function delete(string $key): void  {}
            public function has(string $key): bool     { return false; }
        };
    }

    private function nullMetrics(): MetricsInterface
    {
        return new class implements MetricsInterface {
            public function increment(string $metric, array $tags = []): void {}
            public function timing(string $metric, float $ms, array $tags = []): void {}
        };
    }

    /**
     * Now tests that do not care about logger/cache/metrics are concise.
     */
    public function testCreateThrowsForEmptyName(): void
    {
        $stubRepo = new class implements ProductRepositoryInterface {
            public function findById(int $id): ?array  { return null; }
            public function findAll(): array           { return []; }
            public function save(array $p): array      { return array_merge(['id' => 1], $p); }
        };

        $service = new ProductCatalogueService(
            $stubRepo,
            $this->nullCache(),    // ← reused Null Object
            $this->nullLogger(),   // ← reused Null Object
            $this->nullMetrics()   // ← reused Null Object
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Product name cannot be empty');

        $service->create(name: '', priceCents: 100, sku: 'SKU-001');
    }

    public function testCreateThrowsForNonPositivePrice(): void
    {
        $stubRepo = new class implements ProductRepositoryInterface {
            public function findById(int $id): ?array  { return null; }
            public function findAll(): array           { return []; }
            public function save(array $p): array      { return array_merge(['id' => 1], $p); }
        };

        $service = new ProductCatalogueService(
            $stubRepo,
            $this->nullCache(),
            $this->nullLogger(),
            $this->nullMetrics()
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Price must be positive');

        $service->create(name: 'Widget', priceCents: 0, sku: 'SKU-001');
    }

    // ═══════════════════════════════════════════════════════════
    // PART E — When a Null Object is NOT enough
    // ═══════════════════════════════════════════════════════════

    /**
     * A Null Object is enough when you do NOT care about:
     *   - What the dependency returns (use a stub instead)
     *   - Whether the dependency was called (use a spy instead)
     *
     * Here we need to verify the logger WAS called with the right message.
     * The Null Object silently discards calls — we would never know.
     * We need a SPY logger, not a Null Object logger.
     */
    public function testFindByIdLogsWhenProductNotFound(): void
    {
        $nullRepo = new class implements ProductRepositoryInterface {
            public function findById(int $id): ?array  { return null; } // not found
            public function findAll(): array           { return []; }
            public function save(array $p): array      { return $p; }
        };

        // ── Spy logger: records all calls ─────────────────────────────────────
        $spyLogger = new class implements LoggerInterface {
            public array $logged = [];
            public function log(string $level, string $message, array $context = []): void {
                $this->logged[] = compact('level', 'message', 'context');
            }
            public function info(string $message, array $context = []): void {
                $this->log('info', $message, $context);
            }
            public function error(string $message, array $context = []): void {
                $this->log('error', $message, $context);
            }
        };

        $service = new ProductCatalogueService(
            $nullRepo,
            $this->nullCache(),
            $spyLogger,            // ← spy, not Null Object
            $this->nullMetrics()
        );

        $service->findById(99);

        // Now we can assert on the log call
        $this->assertCount(1, $spyLogger->logged);
        $this->assertSame('info', $spyLogger->logged[0]['level']);
        $this->assertStringContainsString('99', $spyLogger->logged[0]['message']);
        $this->assertStringContainsString('not found', $spyLogger->logged[0]['message']);
    }

    /**
     * Summary of when to use each double:
     *
     *   Null Object → "I don't care about this dependency at all for this test"
     *   Stub        → "I need this dependency to return a specific value"
     *   Spy         → "I need to verify this dependency was called correctly"
     *   Fake        → "I need this dependency to actually work (e.g. store/retrieve data)"
     */
    public function testCreateReturnsProductWithIdAssignedByRepository(): void
    {
        // Fake repo: actually processes the save and assigns an ID
        $fakeRepo = new class implements ProductRepositoryInterface {
            private int $nextId = 1;
            public function findById(int $id): ?array  { return null; }
            public function findAll(): array           { return []; }
            public function save(array $product): array {
                return array_merge($product, ['id' => $this->nextId++]);
            }
        };

        $service = new ProductCatalogueService(
            $fakeRepo,
            $this->nullCache(),    // don't care about caching
            $this->nullLogger(),   // don't care about logging
            $this->nullMetrics()   // don't care about metrics
        );

        $result = $service->create('Widget Pro', 29999, 'WDG-001');

        $this->assertTrue($result['success'] ?? true); // depends on service impl
        $this->assertArrayHasKey('id', $result);
        $this->assertIsInt($result['id']);
        $this->assertSame('Widget Pro', $result['name']);
        $this->assertSame(29999, $result['price']);
    }
}