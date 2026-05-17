<?php
declare(strict_types=1);

/**
 * Example 02 — The Constructor Injection Pattern
 * ------------------------------------------------
 * The full pattern, step by step:
 *   1. Define interfaces (the contracts)
 *   2. Implement interfaces (the concrete classes)
 *   3. Inject via constructor (the service)
 *   4. Wire at the composition root (the entry point)
 *   5. Test with fakes (no infrastructure needed)
 *
 * Scenario: A blog post management system.
 * This directly fixes the BlogPostRepository and BlogPostService
 * from Example 04 in Lesson 3.1.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Constructor Injection Pattern — Full Walkthrough   ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ═══════════════════════════════════════════════════════════
// STEP 1 — Define the interfaces (the contracts)
// These are what services depend on — not concrete classes
// ═══════════════════════════════════════════════════════════

echo "── Step 1: Define interfaces ────────────────────────\n\n";

interface DatabaseInterface {
    public function query(string $sql, array $params = []): array;
    public function execute(string $sql, array $params = []): bool;
}

interface CacheInterface {
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, int $ttl = 300): void;
    public function delete(string $key): void;
}

interface LoggerInterface {
    public function log(string $level, string $message, array $context = []): void;
}

interface MailerInterface {
    public function send(string $to, string $subject, string $body): bool;
}

echo "  ✓ DatabaseInterface — query() and execute()\n";
echo "  ✓ CacheInterface    — get(), set(), delete()\n";
echo "  ✓ LoggerInterface   — log()\n";
echo "  ✓ MailerInterface   — send()\n\n";


// ═══════════════════════════════════════════════════════════
// STEP 2 — Implement the interfaces (concrete classes)
// These are the real implementations — only created at the composition root
// ═══════════════════════════════════════════════════════════

echo "── Step 2: Concrete implementations ─────────────────\n\n";

class InMemoryDatabase implements DatabaseInterface {
    private array $posts = [
        1 => ['id' => 1, 'title' => 'Hello PHP 8.4', 'status' => 'published', 'author_id' => 1],
        2 => ['id' => 2, 'title' => 'DI in Practice', 'status' => 'draft',     'author_id' => 2],
    ];
    private array $users = [
        1 => ['id' => 1, 'email' => 'alice@example.com', 'name' => 'Alice'],
        2 => ['id' => 2, 'email' => 'bob@example.com',   'name' => 'Bob'],
    ];

    public function query(string $sql, array $params = []): array {
        echo "  [DB] " . substr($sql, 0, 60) . "\n";
        if (str_contains($sql, 'blog_posts')) {
            if (!empty($params) && is_int($params[0])) {
                return isset($this->posts[$params[0]]) ? [$this->posts[$params[0]]] : [];
            }
            return array_values($this->posts);
        }
        if (str_contains($sql, 'users') && !empty($params)) {
            return isset($this->users[$params[0]]) ? [$this->users[$params[0]]] : [];
        }
        return [];
    }

    public function execute(string $sql, array $params = []): bool {
        echo "  [DB] EXEC: " . substr($sql, 0, 60) . "\n";
        return true;
    }
}

class ArrayCache implements CacheInterface {
    private array $store = [];

    public function get(string $key): mixed {
        $item = $this->store[$key] ?? null;
        if ($item && $item['expires'] > time()) {
            echo "  [CACHE] HIT: {$key}\n";
            return $item['value'];
        }
        echo "  [CACHE] MISS: {$key}\n";
        return null;
    }

    public function set(string $key, mixed $value, int $ttl = 300): void {
        $this->store[$key] = ['value' => $value, 'expires' => time() + $ttl];
        echo "  [CACHE] SET: {$key} (ttl={$ttl}s)\n";
    }

    public function delete(string $key): void {
        unset($this->store[$key]);
        echo "  [CACHE] DEL: {$key}\n";
    }
}

class ConsoleLogger implements LoggerInterface {
    public function log(string $level, string $message, array $context = []): void {
        $ctx = empty($context) ? '' : ' ' . json_encode($context);
        echo "  [{$level}] {$message}{$ctx}\n";
    }
}

class ConsoleMailer implements MailerInterface {
    public function send(string $to, string $subject, string $body): bool {
        echo "  [MAIL] To: {$to} | Subject: {$subject}\n";
        return true;
    }
}

echo "  ✓ InMemoryDatabase implements DatabaseInterface\n";
echo "  ✓ ArrayCache       implements CacheInterface\n";
echo "  ✓ ConsoleLogger    implements LoggerInterface\n";
echo "  ✓ ConsoleMailer    implements MailerInterface\n\n";


// ═══════════════════════════════════════════════════════════
// STEP 3 — Inject via constructor (the services)
// Zero `new` calls on services. Zero concrete property types.
// ═══════════════════════════════════════════════════════════

echo "── Step 3: Services using constructor injection ──────\n\n";

class BlogPostRepository {
    // ✅ All interface types — no concrete classes
    public function __construct(
        private DatabaseInterface $db,
        private CacheInterface    $cache,
        private LoggerInterface   $logger
    ) {}

    public function findById(int $id): ?array {
        $key    = "post:{$id}";
        $cached = $this->cache->get($key);
        if ($cached !== null) return $cached;

        $this->logger->log('INFO', "DB fetch: post #{$id}");
        $rows = $this->db->query(
            'SELECT id, title, status, author_id FROM blog_posts WHERE id = ?',
            [$id]
        );

        $post = $rows[0] ?? null;
        if ($post) {
            $this->cache->set($key, $post);
        }
        return $post;
    }

    public function findAll(): array {
        $this->logger->log('INFO', "DB fetch: all posts");
        return $this->db->query('SELECT id, title, status FROM blog_posts');
    }

    public function updateStatus(int $id, string $status): bool {
        $this->cache->delete("post:{$id}");
        $this->logger->log('INFO', "Updating post #{$id} status to {$status}");
        return $this->db->execute(
            'UPDATE blog_posts SET status = ? WHERE id = ?',
            [$status, $id]
        );
    }
}

class BlogPostService {
    // ✅ All interface types
    public function __construct(
        private BlogPostRepository $repository,  // concrete — but itself injected
        private MailerInterface    $mailer,
        private LoggerInterface    $logger
    ) {}

    public function getPost(int $id): ?array {
        $this->logger->log('INFO', "Service: getPost({$id})");
        return $this->repository->findById($id);
    }

    public function publishPost(int $id, string $authorEmail): bool {
        $this->logger->log('INFO', "Service: publishPost({$id})");
        $post = $this->repository->findById($id);

        if ($post === null || $post['status'] === 'published') {
            return false;
        }

        $this->repository->updateStatus($id, 'published');
        $this->mailer->send(
            $authorEmail,
            "Your post '{$post['title']}' is live!",
            "Congratulations — your post has been published."
        );
        $this->logger->log('INFO', "Post #{$id} published successfully");
        return true;
    }

    public function listPosts(): array {
        return $this->repository->findAll();
    }
}

echo "  ✓ BlogPostRepository — zero `new` calls, all interface types\n";
echo "  ✓ BlogPostService    — zero `new` calls, all interface types\n\n";


// ═══════════════════════════════════════════════════════════
// STEP 4 — Wire at the composition root
// This is the ONLY place where `new` is called on services
// ═══════════════════════════════════════════════════════════

echo "── Step 4: Composition root — wire everything ────────\n\n";

// Imagine this is index.php or bootstrap.php
$db     = new InMemoryDatabase();
$cache  = new ArrayCache();
$logger = new ConsoleLogger();
$mailer = new ConsoleMailer();

// Dependencies are built in order — each receives what it needs
$repository = new BlogPostRepository($db, $cache, $logger);
$service    = new BlogPostService($repository, $mailer, $logger);

echo "  ✓ All dependencies wired. No framework needed.\n\n";

echo "── Step 4a: Using the wired system ──────────────────\n\n";

echo "getPost(1):\n";
$post = $service->getPost(1);
echo "  → {$post['title']} [{$post['status']}]\n";

echo "\ngetPost(1) again (cache hit):\n";
$post2 = $service->getPost(1);
echo "  → {$post2['title']} [{$post2['status']}]\n";

echo "\npublishPost(2, 'bob@example.com'):\n";
$result = $service->publishPost(2, 'bob@example.com');
echo "  → " . ($result ? 'Published ✓' : 'Failed ✗') . "\n";

echo "\nlistPosts():\n";
foreach ($service->listPosts() as $p) {
    echo "  - #{$p['id']}: {$p['title']}\n";
}


// ═══════════════════════════════════════════════════════════
// STEP 5 — Test with fakes (no infrastructure needed)
// ═══════════════════════════════════════════════════════════

echo "\n── Step 5: Testing with fakes ───────────────────────\n\n";

// Fake DB — returns exactly what the test needs
$fakeDb = new class implements DatabaseInterface {
    public array $queryLog = [];
    public bool  $executeResult = true;

    public function query(string $sql, array $params = []): array {
        $this->queryLog[] = compact('sql', 'params');
        return [['id' => 1, 'title' => 'Test Post', 'status' => 'draft', 'author_id' => 1]];
    }
    public function execute(string $sql, array $params = []): bool {
        $this->queryLog[] = compact('sql', 'params');
        return $this->executeResult;
    }
};

// Fake cache — always misses (forces DB query, keeps test predictable)
$fakeCache = new class implements CacheInterface {
    public function get(string $key): mixed  { return null; }
    public function set(string $key, mixed $value, int $ttl = 300): void {}
    public function delete(string $key): void {}
};

// Spy logger — captures log calls for assertions
$spyLogger = new class implements LoggerInterface {
    public array $entries = [];
    public function log(string $level, string $message, array $context = []): void {
        $this->entries[] = compact('level', 'message', 'context');
    }
};

// Spy mailer — captures sends for assertions
$spyMailer = new class implements MailerInterface {
    public array $sent = [];
    public function send(string $to, string $subject, string $body): bool {
        $this->sent[] = compact('to', 'subject', 'body');
        return true;
    }
};

// Wire with fakes
$fakeRepo    = new BlogPostRepository($fakeDb, $fakeCache, $spyLogger);
$fakeService = new BlogPostService($fakeRepo, $spyMailer, $spyLogger);

// Run the test
$published = $fakeService->publishPost(1, 'alice@example.com');

// Assertions
$pass = true;
$checks = [
    'publishPost returned true'     => $published === true,
    'DB was queried'                => count($fakeDb->queryLog) >= 2,
    'Mailer was called once'        => count($spyMailer->sent) === 1,
    'Email sent to alice'           => ($spyMailer->sent[0]['to'] ?? '') === 'alice@example.com',
    'Logger captured INFO entries'  => count(array_filter(
        $spyLogger->entries, fn($e) => $e['level'] === 'INFO'
    )) >= 1,
];

foreach ($checks as $label => $result) {
    echo "  " . ($result ? '✓' : '✗') . " {$label}\n";
    if (!$result) $pass = false;
}
echo "\n" . ($pass ? "All checks PASSED — no database, no mailer, no filesystem needed.\n" : "Some checks FAILED.\n");

echo "\n--- Recap ---\n";
echo "Step 1: Define interfaces — contracts both sides agree on.\n";
echo "Step 2: Implement interfaces — concrete classes that satisfy the contracts.\n";
echo "Step 3: Services use constructor injection — zero `new` on services inside classes.\n";
echo "Step 4: Composition root — the ONE place where `new` is called on services.\n";
echo "Step 5: Tests inject fakes — business logic tested with zero infrastructure.\n";