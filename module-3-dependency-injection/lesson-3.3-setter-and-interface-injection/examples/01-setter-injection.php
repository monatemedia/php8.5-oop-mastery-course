<?php
declare(strict_types=1);

/**
 * Example 01 — Setter Injection
 * --------------------------------
 * Setter injection provides optional dependencies after construction.
 * The class works without them — but callers can opt in for extra behaviour.
 *
 * Three scenarios:
 *   A. Basic setter injection (nullable default)
 *   B. Fluent setters (method chaining with return static)
 *   C. Multiple optional dependencies via setters
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Setter Injection                                   ║\n";
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
}

interface EventDispatcherInterface {
    public function dispatch(string $event, array $payload = []): void;
}

interface DatabaseInterface {
    public function query(string $sql, array $params = []): array;
    public function execute(string $sql, array $params = []): bool;
}


// ─────────────────────────────────────────────────────────────────────────────
// Lightweight implementations for the examples
// ─────────────────────────────────────────────────────────────────────────────

class ConsoleLogger implements LoggerInterface {
    public function log(string $level, string $message): void {
        echo "  [{$level}] {$message}\n";
    }
}

class ArrayCache implements CacheInterface {
    private array $store = [];
    public function get(string $key): mixed  { return $this->store[$key] ?? null; }
    public function set(string $key, mixed $value, int $ttl = 300): void {
        $this->store[$key] = $value;
        echo "  [CACHE SET] {$key}\n";
    }
}

class SimpleDispatcher implements EventDispatcherInterface {
    public function dispatch(string $event, array $payload = []): void {
        echo "  [EVENT] {$event}\n";
    }
}

class InMemoryDb implements DatabaseInterface {
    private array $users = [
        1 => ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com', 'role' => 'admin'],
        2 => ['id' => 2, 'name' => 'Bob',   'email' => 'bob@example.com',   'role' => 'user'],
    ];

    public function query(string $sql, array $params = []): array {
        if (!empty($params) && is_int($params[0])) {
            return isset($this->users[$params[0]]) ? [$this->users[$params[0]]] : [];
        }
        return array_values($this->users);
    }

    public function execute(string $sql, array $params = []): bool { return true; }
}


// ═══════════════════════════════════════════════════════════
// SCENARIO A — Basic setter injection with nullable default
// ═══════════════════════════════════════════════════════════

echo "── Scenario A: Basic setter injection ───────────────\n\n";

class UserRepository {
    // Required dependency — must be in constructor
    private DatabaseInterface $db;

    // Optional dependency — null by default, set via setter
    private ?LoggerInterface $logger = null;

    public function __construct(DatabaseInterface $db) {
        $this->db = $db;
    }

    // Setter — caller can opt in to logging
    public function setLogger(LoggerInterface $logger): void {
        $this->logger = $logger;
    }

    public function findById(int $id): ?array {
        // Nullsafe operator — works whether logger is set or not
        $this->logger?->log('INFO', "findById({$id})");
        $rows = $this->db->query('SELECT * FROM users WHERE id = ?', [$id]);
        return $rows[0] ?? null;
    }

    public function findAll(): array {
        $this->logger?->log('INFO', "findAll()");
        return $this->db->query('SELECT * FROM users');
    }
}

$db = new InMemoryDb();

echo "Without logger (class works fine):\n";
$repo1 = new UserRepository($db);
$user  = $repo1->findById(1);
echo "  Found: {$user['name']}\n\n";

echo "With logger (caller opts in):\n";
$repo2 = new UserRepository($db);
$repo2->setLogger(new ConsoleLogger()); // Optional — injected after construction
$user2 = $repo2->findById(2);
echo "  Found: {$user2['name']}\n\n";

echo "Key points:\n";
echo "  - Constructed without logger → no null error\n";
echo "  - setLogger() can be called any time before use\n";
echo "  - ?-> nullsafe operator handles the absent logger\n";


// ═══════════════════════════════════════════════════════════
// SCENARIO B — Fluent setters (return static for chaining)
// ═══════════════════════════════════════════════════════════

echo "\n── Scenario B: Fluent setters (method chaining) ─────\n\n";

class EmailNotificationService {
    // Required
    private DatabaseInterface $db;

    // Optional — all null by default
    private ?LoggerInterface          $logger     = null;
    private ?CacheInterface           $cache      = null;
    private ?EventDispatcherInterface $dispatcher = null;
    private string                    $fromEmail  = 'noreply@example.com';
    private int                       $maxRetries = 3;

    public function __construct(DatabaseInterface $db) {
        $this->db = $db;
    }

    // Fluent setters — return static for chaining
    public function setLogger(LoggerInterface $logger): static {
        $this->logger = $logger;
        return $this;
    }

    public function setCache(CacheInterface $cache): static {
        $this->cache = $cache;
        return $this;
    }

    public function setDispatcher(EventDispatcherInterface $dispatcher): static {
        $this->dispatcher = $dispatcher;
        return $this;
    }

    public function setFrom(string $email): static {
        $this->fromEmail = $email;
        return $this;
    }

    public function setMaxRetries(int $retries): static {
        $this->maxRetries = $retries;
        return $this;
    }

    public function notify(int $userId, string $message): void {
        $this->logger?->log('INFO', "Notifying user #{$userId}");

        // Check cache for user
        $cacheKey = "user:{$userId}";
        $user     = $this->cache?->get($cacheKey);
        if ($user === null) {
            $rows = $this->db->query('SELECT * FROM users WHERE id = ?', [$userId]);
            $user = $rows[0] ?? null;
            if ($user && $this->cache) {
                $this->cache->set($cacheKey, $user);
            }
        }

        if ($user) {
            echo "  [EMAIL] From: {$this->fromEmail} → To: {$user['email']}\n";
            echo "  [EMAIL] Message: {$message} (max retries: {$this->maxRetries})\n";
            $this->dispatcher?->dispatch('notification.sent', ['user_id' => $userId]);
        }

        $this->logger?->log('INFO', "Notification complete for #{$userId}");
    }
}

echo "Minimal (only required dep):\n";
$service1 = new EmailNotificationService($db);
$service1->notify(1, 'Hello Alice!');

echo "\nFully configured (fluent chain):\n";
$service2 = (new EmailNotificationService($db))  // Required dep in constructor
    ->setLogger(new ConsoleLogger())              // Optional
    ->setCache(new ArrayCache())                  // Optional
    ->setDispatcher(new SimpleDispatcher())       // Optional
    ->setFrom('system@example.com')               // Optional config
    ->setMaxRetries(5);                           // Optional config

$service2->notify(2, 'Hello Bob!');


// ═══════════════════════════════════════════════════════════
// SCENARIO C — Setter injection after construction (deferred)
// ═══════════════════════════════════════════════════════════

echo "\n── Scenario C: Deferred injection ───────────────────\n\n";

class DataProcessor {
    private ?LoggerInterface $logger = null;

    public function __construct(private DatabaseInterface $db) {}

    public function setLogger(LoggerInterface $logger): void {
        $this->logger = $logger;
    }

    public function process(int $batchId): int {
        $this->logger?->log('INFO', "Processing batch #{$batchId}");
        $rows = $this->db->query('SELECT * FROM users');
        $this->logger?->log('INFO', "Processed " . count($rows) . " records");
        return count($rows);
    }
}

$processor = new DataProcessor($db);

echo "Phase 1: no logger yet\n";
$count = $processor->process(1);
echo "  Processed {$count} records (silent)\n\n";

// Inject logger later — perhaps once production debugging is needed
$processor->setLogger(new ConsoleLogger());
echo "Phase 2: logger added (maybe from a feature flag)\n";
$count2 = $processor->process(2);
echo "  Processed {$count2} records (with logging)\n";

echo "\n--- Recap ---\n";
echo "Setter injection: optional deps provided AFTER construction.\n";
echo "null default:     class works even if setter is never called.\n";
echo "return static:    enables fluent chaining at the composition root.\n";
echo "Deferred:         inject can happen conditionally (feature flag, env, etc.).\n";
echo "Rule:             required deps → constructor. optional deps → setter.\n";