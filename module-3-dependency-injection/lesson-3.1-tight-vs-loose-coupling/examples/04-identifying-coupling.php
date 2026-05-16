<?php
declare(strict_types=1);

/**
 * Example 04 — Identifying Coupling in Real Code
 * ------------------------------------------------
 * A systematic walkthrough of a realistic codebase.
 * We read each class, find every coupling violation, name it, and count it.
 *
 * This is the skill you will use in the challenge: read code, spot the smells,
 * label them. Later lessons will fix them — for now, the goal is recognition.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Identifying Coupling in Real Code                  ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// THE CODEBASE TO AUDIT
// A small blog system — read each class carefully before the audit below
// ─────────────────────────────────────────────────────────────────────────────

// ── Infrastructure classes (these exist as concrete implementations) ──────────

class MysqlConnection {
    public static ?MysqlConnection $instance = null;
    private array $queryLog = [];

    public static function getInstance(): static {
        if (self::$instance === null) {
            self::$instance = new static();
            echo "  [MySQL] Singleton created\n";
        }
        return self::$instance;
    }

    public function query(string $sql, array $params = []): array {
        $this->queryLog[] = $sql;
        echo "  [MySQL] " . substr($sql, 0, 60) . "\n";
        // Simulate results
        return match(true) {
            str_contains($sql, 'SELECT') => [['id' => 1, 'title' => 'Hello', 'content' => 'World', 'author_id' => 1]],
            default => []
        };
    }
}

class DiskCache {
    public function __construct(private string $cacheDir = '/tmp/cache') {}

    public function get(string $key): mixed {
        echo "  [CACHE] GET {$key}\n";
        return null; // Simulate cache miss
    }

    public function set(string $key, mixed $value, int $ttl = 300): void {
        echo "  [CACHE] SET {$key} (ttl={$ttl}s)\n";
    }
}

class SmtpEmailSender {
    public function __construct(
        private string $host  = 'smtp.example.com',
        private int    $port  = 587
    ) {}

    public function send(string $to, string $subject, string $body): bool {
        echo "  [SMTP] To: {$to} | Subject: {$subject}\n";
        return true;
    }
}

class FileSystemLogger {
    public function write(string $level, string $message): void {
        echo "  [LOG:{$level}] {$message}\n";
    }
}

// ── The classes under audit ────────────────────────────────────────────────────

/**
 * BlogPostRepository — fetches blog posts from the database
 */
class BlogPostRepository {
    // ❶ Concrete type property — not an interface
    private MysqlConnection $db;
    // ❷ DiskCache is a concrete class — not an interface
    private DiskCache $cache;

    public function __construct() {
        // ❸ Singleton access — hidden dependency on global state
        $this->db    = MysqlConnection::getInstance();
        // ❹ new inside constructor — takes creation responsibility
        $this->cache = new DiskCache('/var/cache/blog');
    }

    public function findById(int $id): ?array {
        // ❺ Cache key format is hardcoded — knowledge of storage internals
        $cacheKey = "blog_post_{$id}";
        $cached   = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        // ❻ Raw SQL string — MySQL-specific, cannot switch to another DB driver
        $rows = $this->db->query(
            "SELECT id, title, content, author_id FROM blog_posts WHERE id = ? AND deleted_at IS NULL",
            [$id]
        );

        $post = $rows[0] ?? null;
        if ($post) {
            $this->cache->set($cacheKey, $post, 300);
        }

        return $post;
    }

    public function findAll(): array {
        return $this->db->query("SELECT id, title, content, author_id FROM blog_posts");
    }
}

/**
 * BlogPostService — business logic for blog posts
 */
class BlogPostService {
    // ❼ Concrete property types — BlogPostRepository, not an interface
    private BlogPostRepository $repository;
    // ❽ SmtpEmailSender — concrete, not an interface
    private SmtpEmailSender    $emailSender;
    // ❾ FileSystemLogger — concrete, not an interface
    private FileSystemLogger   $logger;

    public function __construct() {
        // ❿ new BlogPostRepository — service creates its own repository
        $this->repository  = new BlogPostRepository();
        // ⓫ new SmtpEmailSender — hardwired to SMTP
        $this->emailSender = new SmtpEmailSender('mail.example.com', 587);
        // ⓬ new FileSystemLogger — hardwired to file logging
        $this->logger      = new FileSystemLogger();
    }

    public function getPost(int $id): ?array {
        $this->logger->write('INFO', "Fetching post #{$id}");
        $post = $this->repository->findById($id);

        if ($post === null) {
            $this->logger->write('WARN', "Post #{$id} not found");
            return null;
        }

        return $post;
    }

    public function publishPost(int $id, string $authorEmail): bool {
        $this->logger->write('INFO', "Publishing post #{$id}");
        $post = $this->repository->findById($id);

        if ($post === null) {
            return false;
        }

        // ⓭ Magic number — what is 1? What does it mean?
        $this->db_update_status($id, 1);

        // ⓮ Direct email send — cannot swap to SMS, Slack, etc.
        $this->emailSender->send(
            $authorEmail,
            "Your post '{$post['title']}' is live!",
            "Congratulations — your post has been published."
        );

        $this->logger->write('INFO', "Post #{$id} published");
        return true;
    }

    // ⓯ Private method does direct database access — bypasses repository pattern
    private function db_update_status(int $id, int $status): void {
        MysqlConnection::getInstance()->query(
            "UPDATE blog_posts SET status = ? WHERE id = ?",
            [$status, $id]
        );
    }
}

/**
 * BlogController — handles HTTP requests
 */
class BlogController {
    // ⓰ Creates its own service — controller responsible for wiring
    private BlogPostService $service;

    public function __construct() {
        // ⓱ new BlogPostService — cascading dependency chain created here
        $this->service = new BlogPostService();
    }

    public function show(int $id): string {
        $post = $this->service->getPost($id);
        if ($post === null) {
            return json_encode(['error' => 'Not found'], JSON_PRETTY_PRINT);
        }
        return json_encode($post, JSON_PRETTY_PRINT);
    }

    public function publish(int $id): string {
        // ⓲ Hard-coded admin email — should come from config or request
        $success = $this->service->publishPost($id, 'admin@example.com');
        return json_encode(['success' => $success]);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// RUN THE CODE to see the coupling cascade in action
// ─────────────────────────────────────────────────────────────────────────────

echo "── Running the tightly coupled system ───────────────\n\n";

echo "Creating BlogController (watch what happens):\n";
$controller = new BlogController();
// ↑ This single `new` triggers:
//   new BlogPostService()
//     new BlogPostRepository()
//       MysqlConnection::getInstance() ← global singleton created
//       new DiskCache('/var/cache/blog') ← disk cache opened
//     new SmtpEmailSender('mail.example.com', 587) ← SMTP connection
//     new FileSystemLogger() ← file logger

echo "\nCalling show(1):\n";
echo $controller->show(1) . "\n";

echo "\nCalling publish(1):\n";
echo $controller->publish(1) . "\n";


// ─────────────────────────────────────────────────────────────────────────────
// THE AUDIT — every coupling violation labelled and counted
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Coupling Audit Results ───────────────────────────\n\n";

echo "BlogPostRepository — violations:\n";
echo "  ❶ private MysqlConnection \$db          → concrete type (not interface)\n";
echo "  ❷ private DiskCache \$cache              → concrete type (not interface)\n";
echo "  ❸ MysqlConnection::getInstance()         → singleton / global state access\n";
echo "  ❹ new DiskCache('/var/cache/blog')       → new inside constructor\n";
echo "  ❺ hardcoded cache key format             → knowledge of storage internals\n";
echo "  ❻ MySQL-specific SQL                     → cannot swap database driver\n";
echo "  Subtotal: 6 violations\n\n";

echo "BlogPostService — violations:\n";
echo "  ❼ private BlogPostRepository \$repository → concrete type (not interface)\n";
echo "  ❽ private SmtpEmailSender \$emailSender   → concrete type (not interface)\n";
echo "  ❾ private FileSystemLogger \$logger        → concrete type (not interface)\n";
echo "  ❿ new BlogPostRepository()               → new inside constructor\n";
echo "  ⓫ new SmtpEmailSender(...)              → new inside constructor\n";
echo "  ⓬ new FileSystemLogger()                → new inside constructor\n";
echo "  ⓭ magic number `1` for status            → control coupling\n";
echo "  ⓮ direct SMTP send — cannot swap channel → concrete dependency\n";
echo "  ⓯ private db_update_status() bypasses repo → breaks repository pattern\n";
echo "  Subtotal: 9 violations\n\n";

echo "BlogController — violations:\n";
echo "  ⓰ private BlogPostService \$service     → concrete type (not interface)\n";
echo "  ⓱ new BlogPostService()                 → new inside constructor (cascades all deps)\n";
echo "  ⓲ hardcoded 'admin@example.com'         → should come from config/request\n";
echo "  Subtotal: 3 violations\n\n";

echo "────────────────────────────────────────────────\n";
echo "TOTAL coupling violations across 3 classes: 18\n";
echo "Classes that can be tested in isolation:      0\n";
echo "Infrastructure connections on first `new`:    3 (MySQL singleton, DiskCache, SMTP)\n";
echo "Lines that must be edited to switch DB:       4+ (spread across 2 classes)\n";
echo "Lines that must be edited to switch mailer:   3+ (spread across 2 classes)\n\n";

echo "── What good looks like ────────────────────────────\n\n";
echo "Each class should accept its dependencies via constructor parameters,\n";
echo "typed against INTERFACES — not concrete class names.\n";
echo "A class with zero `new` calls on services/repositories/gateways\n";
echo "and zero concrete types in property declarations is well-decoupled.\n";
echo "We will achieve this in Lesson 3.2 — Constructor Injection.\n";

echo "\n--- Recap ---\n";
echo "Audit approach:\n";
echo "  1. List every property — is it typed against an interface or a concrete class?\n";
echo "  2. List every `new` call — is it a value object (OK) or a service (❌)?\n";
echo "  3. List every static call — is it global state (❌) or a pure utility (OK)?\n";
echo "  4. List every hardcoded string/number that belongs in config or a parameter.\n";
echo "  5. Count total violations. Goal: zero.\n";