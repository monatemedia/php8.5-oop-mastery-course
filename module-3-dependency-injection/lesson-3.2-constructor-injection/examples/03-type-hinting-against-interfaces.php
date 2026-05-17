<?php
declare(strict_types=1);

/**
 * Example 03 — Type-Hinting Against Interfaces, Not Concrete Classes
 * --------------------------------------------------------------------
 * This example isolates the single most important decision in constructor injection:
 * the parameter TYPE in the constructor.
 *
 * It proves why `private DatabaseInterface $db` is fundamentally different
 * from `private MySQLDatabase $db` — even when the object is injected in both cases.
 *
 * The concrete type is "injection-friendly but still coupled."
 * The interface type is "truly decoupled."
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Type-Hinting Against Interfaces                    ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// The interface and two concrete implementations
// ─────────────────────────────────────────────────────────────────────────────

interface CacheInterface {
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, int $ttl = 3600): void;
    public function delete(string $key): void;
    public function has(string $key): bool;
}

class ArrayCache implements CacheInterface {
    private array $store = [];

    public function get(string $key): mixed {
        return isset($this->store[$key]) && $this->store[$key]['exp'] > time()
            ? $this->store[$key]['val']
            : null;
    }

    public function set(string $key, mixed $value, int $ttl = 3600): void {
        $this->store[$key] = ['val' => $value, 'exp' => time() + $ttl];
        echo "  [ARRAY-CACHE] SET {$key}\n";
    }

    public function delete(string $key): void {
        unset($this->store[$key]);
    }

    public function has(string $key): bool {
        return isset($this->store[$key]) && $this->store[$key]['exp'] > time();
    }
}

class NullCache implements CacheInterface {
    // Does nothing — useful in testing or when caching is disabled
    public function get(string $key): mixed     { return null; }
    public function set(string $key, mixed $value, int $ttl = 3600): void {}
    public function delete(string $key): void   {}
    public function has(string $key): bool      { return false; }
}

class SpyCache implements CacheInterface {
    public array $log = [];

    public function get(string $key): mixed {
        $this->log[] = "GET {$key}";
        return null;
    }

    public function set(string $key, mixed $value, int $ttl = 3600): void {
        $this->log[] = "SET {$key}";
    }

    public function delete(string $key): void {
        $this->log[] = "DEL {$key}";
    }

    public function has(string $key): bool {
        $this->log[] = "HAS {$key}";
        return false;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// APPROACH 1 — Injected but typed as concrete class
// Better than creating internally — but still has a problem
// ─────────────────────────────────────────────────────────────────────────────

echo "── Approach 1: Injected but typed as concrete class ──\n\n";

class CatalogServiceV1 {
    public function __construct(
        private ArrayCache $cache  // ❌ Concrete type — only ArrayCache accepted
    ) {}

    public function findProduct(int $id): array {
        $key    = "product:{$id}";
        $cached = $this->cache->get($key);
        if ($cached !== null) return $cached;

        $product = ['id' => $id, 'name' => "Product #{$id}", 'price' => 9999];
        $this->cache->set($key, $product);
        return $product;
    }
}

// Can inject a real ArrayCache ✅
$realCache = new ArrayCache();
$service1  = new CatalogServiceV1($realCache);
$service1->findProduct(1);

echo "\nProblem: What if we want to inject NullCache or SpyCache?\n";

// Cannot inject NullCache — wrong type!
try {
    $nullCache = new NullCache();
    // $service1v2 = new CatalogServiceV1($nullCache); // PHP TypeError
    echo "  CatalogServiceV1(new NullCache()) → TypeError: NullCache is not ArrayCache\n";
    echo "  (To test: we would have to use ArrayCache, even if it has side effects)\n";
} catch (\TypeError $e) {
    echo "  TypeError: " . $e->getMessage() . "\n";
}

echo "\nConclusion: Concrete type hint = one and only one implementation accepted.\n";
echo "  Still breaks testability (cannot inject a spy or null cache for tests).\n";
echo "  Still breaks flexibility (cannot swap to Redis without editing V1).\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// APPROACH 2 — Injected and typed as interface
// Truly decoupled — any conforming implementation works
// ─────────────────────────────────────────────────────────────────────────────

echo "── Approach 2: Injected and typed as interface ───────\n\n";

class CatalogServiceV2 {
    public function __construct(
        private CacheInterface $cache  // ✅ Interface — any implementation accepted
    ) {}

    public function findProduct(int $id): array {
        $key    = "product:{$id}";
        $cached = $this->cache->get($key);
        if ($cached !== null) {
            echo "  Cache hit for product:{$id}\n";
            return $cached;
        }
        $product = ['id' => $id, 'name' => "Product #{$id}", 'price' => 9999];
        $this->cache->set($key, $product);
        return $product;
    }
}

echo "All three cache implementations can be injected:\n\n";

// Production: real cache
echo "  with ArrayCache:\n";
$v2prod = new CatalogServiceV2(new ArrayCache());
$v2prod->findProduct(7);
$v2prod->findProduct(7); // Cache hit

// Testing: spy cache
echo "\n  with SpyCache:\n";
$spy  = new SpyCache();
$v2test = new CatalogServiceV2($spy);
$v2test->findProduct(42);
echo "  Spy log: " . implode(', ', $spy->log) . "\n";

// Disabled: null cache
echo "\n  with NullCache (caching disabled):\n";
$v2null = new CatalogServiceV2(new NullCache());
$v2null->findProduct(5); // No cache output
echo "  (No cache operations — NullCache is silent)\n";


// ─────────────────────────────────────────────────────────────────────────────
// Why this matters: what you get from interface types
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── What interface types give you ────────────────────\n\n";

echo "1. TESTABILITY — inject SpyCache or NullCache in tests:\n";
echo "   new CatalogServiceV2(new SpyCache())        — records all cache calls\n";
echo "   new CatalogServiceV2(new NullCache())       — silently skips caching\n\n";

echo "2. FLEXIBILITY — swap implementations without editing the service:\n";
echo "   Production:  new CatalogServiceV2(new RedisCache(...))\n";
echo "   Development: new CatalogServiceV2(new ArrayCache())\n";
echo "   Testing:     new CatalogServiceV2(new NullCache())\n\n";

echo "3. ISP COMPLIANCE — accept only what you need:\n";
echo "   If the service only reads from cache:\n";
echo "   → Type-hint against a ReadableCacheInterface (get/has only)\n";
echo "   → Even more precise — the service does not accidentally call set()\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// The naming convention: Interface suffix
// ─────────────────────────────────────────────────────────────────────────────

echo "── Naming conventions ───────────────────────────────\n\n";

echo "Two common conventions:\n\n";

echo "Convention 1: Interface suffix\n";
echo "  interface CacheInterface\n";
echo "  class ArrayCache implements CacheInterface\n";
echo "  class RedisCache implements CacheInterface\n\n";

echo "Convention 2: Concrete class for the interface name\n";
echo "  interface Cache\n";
echo "  class ArrayCache implements Cache\n";
echo "  class RedisCache implements Cache\n\n";

echo "This course uses the Interface suffix (CacheInterface, LoggerInterface)\n";
echo "because it makes the parameter type clearly visible at a glance.\n";

echo "\n── The rule in one sentence ─────────────────────────\n\n";
echo "If the parameter type in your constructor is a concrete class name,\n";
echo "you are one step better than creating it with `new` — but you are\n";
echo "still tightly coupled. Type against the INTERFACE, not the class.\n";

echo "\n--- Recap ---\n";
echo "Concrete type hint: only that one class accepted — still partially coupled.\n";
echo "Interface type hint: ANY conforming class accepted — truly decoupled.\n";
echo "Interface names: use CacheInterface, LoggerInterface, DatabaseInterface.\n";
echo "Testing: interface types let you inject fakes, spies, and null objects.\n";
echo "Flexibility: interface types let you swap implementations at the wiring point.\n";