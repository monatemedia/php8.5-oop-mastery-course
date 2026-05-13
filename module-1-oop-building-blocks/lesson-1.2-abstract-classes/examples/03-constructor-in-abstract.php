<?php
declare(strict_types=1);

/**
 * Example 03 — Constructor Logic in Abstract Classes
 * ----------------------------------------------------
 * Abstract classes can have constructors. This is one of the key advantages
 * over interfaces — shared initialisation logic runs once, not in every subclass.
 *
 * Three scenarios:
 *   A. Subclass calls parent constructor (standard pattern)
 *   B. Subclass extends the parent constructor with its own setup
 *   C. Abstract class enforces validation in the constructor
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Constructor Logic in Abstract Classes              ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ═══════════════════════════════════════════════════════════
// SCENARIO A — Standard: subclass calls parent::__construct()
// ═══════════════════════════════════════════════════════════
echo "── Scenario A: Standard parent constructor call ─────\n\n";

abstract class Connection {
    protected bool $connected = false;
    private string $connectedAt;

    public function __construct(
        protected string $host,
        protected int    $port,
        protected string $database
    ) {
        // Shared setup — runs for every connection type automatically
        $this->connectedAt = date('H:i:s');
        echo "[CONNECTION] Initialised {$host}:{$port}/{$database} at {$this->connectedAt}\n";
    }

    abstract public function connect(): bool;
    abstract public function query(string $sql): array;
    abstract public function getDriverName(): string;

    public function isConnected(): bool { return $this->connected; }

    public function getInfo(): string {
        return "{$this->getDriverName()}://{$this->host}:{$this->port}/{$this->database}";
    }
}

class MySqlConnection extends Connection {
    // No extra constructor needed — parent handles all shared setup
    public function connect(): bool {
        echo "[MYSQL] Connecting to {$this->getInfo()}...\n";
        $this->connected = true;
        return true;
    }

    public function query(string $sql): array {
        echo "[MYSQL] Running: {$sql}\n";
        return [['id' => 1, 'name' => 'Alice']];
    }

    public function getDriverName(): string { return 'mysql'; }
}

class PostgresConnection extends Connection {
    public function connect(): bool {
        echo "[POSTGRES] Connecting to {$this->getInfo()}...\n";
        $this->connected = true;
        return true;
    }

    public function query(string $sql): array {
        echo "[POSTGRES] Running: {$sql}\n";
        return [['id' => 1, 'name' => 'Bob']];
    }

    public function getDriverName(): string { return 'pgsql'; }
}

$mysql = new MySqlConnection('db.local', 3306, 'app_db');
$mysql->connect();
$rows = $mysql->query('SELECT * FROM users LIMIT 1');
echo "Result: " . json_encode($rows) . "\n";

$pg = new PostgresConnection('pg.local', 5432, 'analytics');
$pg->connect();


// ═══════════════════════════════════════════════════════════
// SCENARIO B — Extended: subclass adds its own setup
// ═══════════════════════════════════════════════════════════
echo "\n── Scenario B: Subclass extends the constructor ─────\n\n";

abstract class CacheDriver {
    protected array $store  = [];
    protected int   $hits   = 0;
    protected int   $misses = 0;

    public function __construct(
        protected string $prefix,
        protected int    $defaultTtl = 3600
    ) {
        echo "[CACHE] Driver initialised (prefix='{$prefix}', ttl={$defaultTtl}s)\n";
    }

    abstract public function get(string $key): mixed;
    abstract public function set(string $key, mixed $value, ?int $ttl = null): void;

    public function stats(): array {
        return ['hits' => $this->hits, 'misses' => $this->misses];
    }

    protected function prefixedKey(string $key): string {
        return $this->prefix . ':' . $key;
    }
}

class RedisCacheDriver extends CacheDriver {
    private string $connectionString;

    public function __construct(
        string $prefix,
        int    $defaultTtl,
        private string $host,
        private int    $port = 6379
    ) {
        parent::__construct($prefix, $defaultTtl); // ← Always first
        // Redis-specific setup after parent initialisation
        $this->connectionString = "{$host}:{$port}";
        echo "[REDIS] Connected to {$this->connectionString}\n";
    }

    public function get(string $key): mixed {
        $full = $this->prefixedKey($key);
        if (isset($this->store[$full])) {
            $this->hits++;
            echo "[REDIS] HIT  {$full}\n";
            return $this->store[$full];
        }
        $this->misses++;
        echo "[REDIS] MISS {$full}\n";
        return null;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void {
        $full = $this->prefixedKey($key);
        $this->store[$full] = $value;
        echo "[REDIS] SET  {$full} (ttl=" . ($ttl ?? $this->defaultTtl) . "s)\n";
    }
}

class ArrayCacheDriver extends CacheDriver {
    // No additional constructor needed — parent handles everything
    public function get(string $key): mixed {
        $full = $this->prefixedKey($key);
        if (array_key_exists($full, $this->store)) {
            $this->hits++;
            return $this->store[$full];
        }
        $this->misses++;
        return null;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void {
        $this->store[$this->prefixedKey($key)] = $value;
    }
}

$redis = new RedisCacheDriver('app', 1800, 'redis.local');
$redis->set('user:1', ['name' => 'Alice', 'role' => 'admin']);
$redis->get('user:1');
$redis->get('user:99');
echo "Stats: " . json_encode($redis->stats()) . "\n";


// ═══════════════════════════════════════════════════════════
// SCENARIO C — Validation in the abstract constructor
// ═══════════════════════════════════════════════════════════
echo "\n── Scenario C: Validation in the constructor ────────\n\n";

abstract class ApiClient {
    public function __construct(
        protected string $baseUrl,
        protected string $apiKey,
        protected int    $timeoutSeconds = 30
    ) {
        // Shared validation — runs before any subclass setup
        if (empty(trim($apiKey))) {
            throw new \InvalidArgumentException("API key cannot be empty.");
        }
        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException("Invalid base URL: {$baseUrl}");
        }
        if ($timeoutSeconds < 1 || $timeoutSeconds > 120) {
            throw new \InvalidArgumentException("Timeout must be between 1 and 120 seconds.");
        }
        echo "[API] Client ready: {$baseUrl} (timeout={$timeoutSeconds}s)\n";
    }

    abstract public function get(string $endpoint): array;
    abstract public function post(string $endpoint, array $body): array;

    protected function buildUrl(string $endpoint): string {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
    }
}

class StripeClient extends ApiClient {
    public function get(string $endpoint): array {
        $url = $this->buildUrl($endpoint);
        echo "[STRIPE GET] {$url}\n";
        return ['object' => 'list', 'data' => []];
    }

    public function post(string $endpoint, array $body): array {
        $url = $this->buildUrl($endpoint);
        echo "[STRIPE POST] {$url} | body=" . json_encode($body) . "\n";
        return ['id' => 'ch_' . uniqid(), 'status' => 'succeeded'];
    }
}

// Valid construction
$stripe = new StripeClient('https://api.stripe.com', 'sk_test_abc123', 10);
$stripe->get('/v1/charges');
$stripe->post('/v1/charges', ['amount' => 1500, 'currency' => 'zar']);

// Invalid — validation in abstract constructor fires before any subclass code runs
echo "\nTesting constructor validation:\n";
try {
    new StripeClient('https://api.stripe.com', '', 10); // Empty API key
} catch (\InvalidArgumentException $e) {
    echo "Caught: " . $e->getMessage() . "\n";
}

try {
    new StripeClient('not-a-url', 'sk_test_abc123', 10); // Bad URL
} catch (\InvalidArgumentException $e) {
    echo "Caught: " . $e->getMessage() . "\n";
}

try {
    new StripeClient('https://api.stripe.com', 'sk_test_abc123', 999); // Bad timeout
} catch (\InvalidArgumentException $e) {
    echo "Caught: " . $e->getMessage() . "\n";
}

echo "\n--- Recap ---\n";
echo "A. Standard: subclass calls parent::__construct() — shared init runs once.\n";
echo "B. Extended: call parent first, then add subclass-specific setup after.\n";
echo "C. Validated: the abstract constructor guards shared pre-conditions for all subclasses.\n";
echo "Rule: Always call parent::__construct() as the FIRST line of a subclass constructor.\n";