<?php
declare(strict_types=1);

/**
 * Example 02 — The Null Object Default Pattern
 * -----------------------------------------------
 * When using setter injection for optional dependencies, you have two choices:
 *   A. Store null and use the nullsafe operator (?->) everywhere
 *   B. Default to a Null Object — an implementation that does nothing
 *
 * Option B (Null Object) is almost always better. It eliminates null checks,
 * makes the code cleaner, and prevents "forgot to set the logger" crashes.
 *
 * This example shows both approaches side by side, then demonstrates why
 * the Null Object pattern is the preferred default for optional deps.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  The Null Object Default Pattern                    ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Interfaces
// ─────────────────────────────────────────────────────────────────────────────

interface LoggerInterface {
    public function log(string $level, string $message): void;
}

interface CacheInterface {
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, int $ttl = 300): void;
    public function delete(string $key): void;
    public function has(string $key): bool;
}

interface EventDispatcherInterface {
    public function dispatch(string $event, array $payload = []): void;
}

interface DatabaseInterface {
    public function query(string $sql, array $params = []): array;
}


// ─────────────────────────────────────────────────────────────────────────────
// Null Object implementations — do nothing, return safe defaults
// ─────────────────────────────────────────────────────────────────────────────

class NullLogger implements LoggerInterface {
    public function log(string $level, string $message): void {
        // Intentionally silent — no output, no side effects
    }
}

class NullCache implements CacheInterface {
    // Always misses — as if the cache does not exist
    public function get(string $key): mixed  { return null; }
    public function set(string $key, mixed $value, int $ttl = 300): void {}
    public function delete(string $key): void {}
    public function has(string $key): bool   { return false; }
}

class NullDispatcher implements EventDispatcherInterface {
    public function dispatch(string $event, array $payload = []): void {
        // Intentionally silent
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Real implementations (for comparison)
// ─────────────────────────────────────────────────────────────────────────────

class ConsoleLogger implements LoggerInterface {
    public function log(string $level, string $message): void {
        echo "  [{$level}] {$message}\n";
    }
}

class ArrayCache implements CacheInterface {
    private array $store = [];
    public function get(string $key): mixed {
        echo "  [CACHE] " . (isset($this->store[$key]) ? 'HIT' : 'MISS') . ": {$key}\n";
        return $this->store[$key] ?? null;
    }
    public function set(string $key, mixed $value, int $ttl = 300): void {
        $this->store[$key] = $value;
        echo "  [CACHE] SET: {$key}\n";
    }
    public function delete(string $key): void  { unset($this->store[$key]); }
    public function has(string $key): bool     { return isset($this->store[$key]); }
}

class SimpleDispatcher implements EventDispatcherInterface {
    public function dispatch(string $event, array $payload = []): void {
        echo "  [EVENT] {$event}: " . json_encode($payload) . "\n";
    }
}

class InMemoryDb implements DatabaseInterface {
    private array $products = [
        1 => ['id' => 1, 'name' => 'Widget', 'price' => 29999],
        2 => ['id' => 2, 'name' => 'Gadget', 'price' => 14999],
    ];
    public function query(string $sql, array $params = []): array {
        if (!empty($params) && is_int($params[0])) {
            return isset($this->products[$params[0]]) ? [$this->products[$params[0]]] : [];
        }
        return array_values($this->products);
    }
}


// ═══════════════════════════════════════════════════════════
// APPROACH 1 — Nullable default + nullsafe operator
// ═══════════════════════════════════════════════════════════

echo "── Approach 1: Nullable default (?->) ───────────────\n\n";

class ProductServiceV1 {
    private ?LoggerInterface          $logger     = null;
    private ?CacheInterface           $cache      = null;
    private ?EventDispatcherInterface $dispatcher = null;

    public function __construct(private DatabaseInterface $db) {}

    public function setLogger(LoggerInterface $logger): static {
        $this->logger = $logger; return $this;
    }
    public function setCache(CacheInterface $cache): static {
        $this->cache = $cache; return $this;
    }
    public function setDispatcher(EventDispatcherInterface $dispatcher): static {
        $this->dispatcher = $dispatcher; return $this;
    }

    public function findById(int $id): ?array {
        // ❌ Nullsafe operators scattered everywhere — verbose and easy to forget
        $this->logger?->log('INFO', "findById({$id})");

        $key    = "product:{$id}";
        $cached = $this->cache?->get($key);
        if ($cached !== null) return $cached;

        $rows    = $this->db->query('SELECT * FROM products WHERE id = ?', [$id]);
        $product = $rows[0] ?? null;

        if ($product) {
            $this->cache?->set($key, $product);
            $this->dispatcher?->dispatch('product.viewed', ['id' => $id]);
        }

        $this->logger?->log('INFO', "findById complete: " . ($product ? $product['name'] : 'not found'));
        return $product;
    }
}

echo "V1 without optional deps (no crash — nullsafe handles it):\n";
$v1 = new ProductServiceV1(new InMemoryDb());
$p  = $v1->findById(1);
echo "  Found: {$p['name']}\n\n";

echo "V1 with all optional deps:\n";
$v1full = (new ProductServiceV1(new InMemoryDb()))
    ->setLogger(new ConsoleLogger())
    ->setCache(new ArrayCache())
    ->setDispatcher(new SimpleDispatcher());
$p2 = $v1full->findById(1);
echo "  Found: {$p2['name']}\n\n";

echo "Drawbacks of Approach 1:\n";
echo "  - ?-> everywhere — clutters the code\n";
echo "  - Easy to forget one ?-> and get a null reference error\n";
echo "  - Developers must remember to add ?-> every time they add a new call\n";


// ═══════════════════════════════════════════════════════════
// APPROACH 2 — Null Object default (cleaner, recommended)
// ═══════════════════════════════════════════════════════════

echo "\n── Approach 2: Null Object default (recommended) ────\n\n";

class ProductServiceV2 {
    // Always-valid Null Objects — no null checks needed anywhere
    private LoggerInterface          $logger;
    private CacheInterface           $cache;
    private EventDispatcherInterface $dispatcher;

    public function __construct(private DatabaseInterface $db) {
        // Safe defaults — these never fail, never produce output, never throw
        $this->logger     = new NullLogger();
        $this->cache      = new NullCache();
        $this->dispatcher = new NullDispatcher();
    }

    // Setters replace the null object with a real one
    public function setLogger(LoggerInterface $logger): static {
        $this->logger = $logger; return $this;
    }
    public function setCache(CacheInterface $cache): static {
        $this->cache = $cache; return $this;
    }
    public function setDispatcher(EventDispatcherInterface $dispatcher): static {
        $this->dispatcher = $dispatcher; return $this;
    }

    public function findById(int $id): ?array {
        // ✅ No nullsafe operators — always safe to call directly
        $this->logger->log('INFO', "findById({$id})");

        $key    = "product:{$id}";
        $cached = $this->cache->get($key);
        if ($cached !== null) return $cached;

        $rows    = $this->db->query('SELECT * FROM products WHERE id = ?', [$id]);
        $product = $rows[0] ?? null;

        if ($product) {
            $this->cache->set($key, $product);
            $this->dispatcher->dispatch('product.viewed', ['id' => $id]);
        }

        $this->logger->log('INFO', "findById complete: " . ($product ? $product['name'] : 'not found'));
        return $product;
    }
}

echo "V2 without optional deps (silent — NullObjects absorb all calls):\n";
$v2 = new ProductServiceV2(new InMemoryDb());
$p  = $v2->findById(1);
echo "  Found: {$p['name']} (no log, cache, or event output)\n\n";

echo "V2 with all optional deps (behaviour added by replacing null objects):\n";
$v2full = (new ProductServiceV2(new InMemoryDb()))
    ->setLogger(new ConsoleLogger())
    ->setCache(new ArrayCache())
    ->setDispatcher(new SimpleDispatcher());
$p2 = $v2full->findById(1);
echo "\n  Found: {$p2['name']}\n";

echo "\n  Same product again (cache hit):\n";
$p3 = $v2full->findById(1);
echo "  Found: {$p3['name']}\n";


// ─────────────────────────────────────────────────────────────────────────────
// Why Null Objects work: they follow the interface contract
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Why Null Objects work ────────────────────────────\n\n";

echo "NullLogger::log()     — same signature as LoggerInterface::log()\n";
echo "NullCache::get()      — same signature as CacheInterface::get()\n";
echo "NullDispatcher::dispatch() — same signature as EventDispatcherInterface::dispatch()\n\n";

echo "The class always calls \$this->logger->log() — never checks if it is null.\n";
echo "The type system guarantees a valid object is always there.\n";
echo "Null Objects are the 'do nothing' implementation of an interface.\n\n";

$nullLogger = new NullLogger();
var_dump($nullLogger instanceof LoggerInterface); // true — it IS a logger


// ─────────────────────────────────────────────────────────────────────────────
// Null Object in tests
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Null Objects in tests ────────────────────────────\n\n";

echo "In tests: pass NullObjects for deps you don't need to assert on.\n";
echo "Pass a SpyLogger only when you need to assert what was logged.\n\n";

// Only spy on cache — logger and dispatcher don't matter for this test
$spyCache = new class implements CacheInterface {
    public array $setLog = [];
    public function get(string $key): mixed  { return null; }
    public function set(string $key, mixed $value, int $ttl = 300): void {
        $this->setLog[] = $key;
    }
    public function delete(string $key): void {}
    public function has(string $key): bool   { return false; }
};

$testService = (new ProductServiceV2(new InMemoryDb()))
    ->setCache($spyCache);
// Logger and dispatcher stay as NullObjects — we don't care about them

$testService->findById(1);
echo "Cache spy recorded SET calls: " . implode(', ', $spyCache->setLog) . "\n";

echo "\n--- Recap ---\n";
echo "Null Object: implements the interface but does nothing — the 'off' state.\n";
echo "Default to Null Objects in the constructor for all optional deps.\n";
echo "Setters replace Null Objects with real implementations.\n";
echo "No ?-> operators needed — the Null Object is always a valid call target.\n";
echo "Tests: NullObjects for irrelevant deps, Spy objects for asserted deps.\n";