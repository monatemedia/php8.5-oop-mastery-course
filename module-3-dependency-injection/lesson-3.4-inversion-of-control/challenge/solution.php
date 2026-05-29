<?php
declare(strict_types=1);

/**
 * CHALLENGE STARTER — Lesson 3.4: Inversion of Control
 * ──────────────────────────────────────────────────────
 * Read CHALLENGE.md before touching this file.
 *
 * This blog system has a four-level coupling chain:
 *   BlogController → BlogPostService → BlogPostRepository → InMemoryDatabase
 *
 * Every class creates its own dependencies.
 * Your job: fully invert every dependency using IoC.
 *
 * Do NOT look at solution.php until you have made a genuine attempt.
 */


// ─────────────────────────────────────────────────────────────────────────────
// TODO Task 1: Define four interfaces
// DatabaseInterface, LoggerInterface, MailerInterface, BlogRepositoryInterface
// ─────────────────────────────────────────────────────────────────────────────


// ─────────────────────────────────────────────────────────────────────────────
// INFRASTRUCTURE — update these to implement your interfaces (Task 2)
// ─────────────────────────────────────────────────────────────────────────────

class InMemoryDatabase {  // TODO: implement DatabaseInterface
    private array $posts = [
        1 => ['id' => 1, 'title' => 'Hello PHP 8.5',     'status' => 'published', 'author' => 'alice@example.com'],
        2 => ['id' => 2, 'title' => 'IoC in Practice',   'status' => 'published', 'author' => 'bob@example.com'],
        3 => ['id' => 3, 'title' => 'DI vs DIP',         'status' => 'draft',     'author' => 'alice@example.com'],
    ];

    public function query(string $sql, array $params = []): array {
        echo "  [DB] Query: " . substr($sql, 0, 50) . "\n";
        if (!empty($params) && is_int($params[0])) {
            return isset($this->posts[$params[0]]) ? [$this->posts[$params[0]]] : [];
        }
        return array_values($this->posts);
    }

    public function execute(string $sql, array $params = []): bool {
        echo "  [DB] Execute: " . substr($sql, 0, 50) . "\n";
        if (str_contains($sql, 'INSERT') && !empty($params)) {
            $this->posts[$params[0]] = [
                'id' => $params[0], 'title' => $params[1],
                'status' => 'draft', 'author' => $params[2] ?? 'unknown'
            ];
        }
        return true;
    }
}

class ConsoleLogger {  // TODO: implement LoggerInterface
    public function log(string $level, string $message): void {
        echo "  [{$level}] {$message}\n";
    }
}

class ConsoleMailer {  // TODO: implement MailerInterface
    public function send(string $to, string $subject, string $body): bool {
        echo "  [MAIL] To: {$to} | {$subject}\n";
        return true;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// CLASSES TO INVERT (Tasks 3, 4, 5)
// Remove all `new` calls. Accept dependencies via constructor.
// ─────────────────────────────────────────────────────────────────────────────

class BlogPostRepository {  // TODO: implement BlogRepositoryInterface
    private InMemoryDatabase $db;      // TODO: change to DatabaseInterface
    private ConsoleLogger    $logger;  // TODO: change to LoggerInterface

    public function __construct() {
        // TODO: Remove these — accept $db and $logger via constructor
        $this->db     = new InMemoryDatabase();
        $this->logger = new ConsoleLogger();
    }

    public function findAll(): array {
        $this->logger->log('INFO', "Fetching all posts");
        return $this->db->query('SELECT * FROM blog_posts');
    }

    public function findById(int $id): ?array {
        $this->logger->log('INFO', "Fetching post #{$id}");
        $rows = $this->db->query('SELECT * FROM blog_posts WHERE id = ?', [$id]);
        return $rows[0] ?? null;
    }

    public function save(array $post): bool {
        $this->logger->log('INFO', "Saving post: {$post['title']}");
        return $this->db->execute(
            'INSERT INTO blog_posts (id, title, author) VALUES (?,?,?)',
            [$post['id'], $post['title'], $post['author']]
        );
    }
}


class BlogPostService {
    private BlogPostRepository $repository;  // TODO: change to BlogRepositoryInterface
    private ConsoleMailer      $mailer;      // TODO: change to MailerInterface
    private ConsoleLogger      $logger;      // TODO: change to LoggerInterface

    public function __construct() {
        // TODO: Remove these — accept via constructor
        $this->repository = new BlogPostRepository();
        $this->mailer     = new ConsoleMailer();
        $this->logger     = new ConsoleLogger();
    }

    public function listPosts(): array {
        $posts = $this->repository->findAll();
        $this->logger->log('INFO', "Returning " . count($posts) . " posts");
        return $posts;
    }

    public function getPost(int $id): ?array {
        $post = $this->repository->findById($id);
        if ($post === null) {
            $this->logger->log('WARN', "Post #{$id} not found");
        }
        return $post;
    }

    public function publishPost(int $id): bool {
        $post = $this->repository->findById($id);
        if ($post === null) return false;

        $this->logger->log('INFO', "Publishing post #{$id}: {$post['title']}");
        $this->mailer->send(
            $post['author'],
            "Your post '{$post['title']}' is now live!",
            "Congratulations!"
        );
        return true;
    }
}


class BlogController {
    private BlogPostService $service;  // TODO: keep — it's a concrete class with its own injection
    private ConsoleLogger   $logger;   // TODO: change to LoggerInterface

    public function __construct() {
        // TODO: Remove these — accept via constructor
        $this->service = new BlogPostService();
        $this->logger  = new ConsoleLogger();
    }

    public function handleRequest(string $action, array $params = []): string {
        $this->logger->log('INFO', "Handling request: {$action}");

        return match($action) {
            'listPosts'  => json_encode([
                'success' => true,
                'posts'   => $this->service->listPosts()
            ], JSON_PRETTY_PRINT),

            'getPost'    => json_encode([
                'success' => true,
                'post'    => $this->service->getPost($params['id'] ?? 1)
            ], JSON_PRETTY_PRINT),

            'publishPost' => json_encode([
                'success' => $this->service->publishPost($params['id'] ?? 1)
            ], JSON_PRETTY_PRINT),

            default => json_encode(['error' => 'Unknown action']),
        };
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// CURRENT (tightly coupled) usage — replace with Tasks 6 and 7
// ─────────────────────────────────────────────────────────────────────────────

echo "=== Current (tightly coupled) output ===\n\n";

$controller = new BlogController();
echo $controller->handleRequest('listPosts') . "\n";


// ─────────────────────────────────────────────────────────────────────────────
// TODO Task 6: Replace above with a flat IoC wiring function
// ─────────────────────────────────────────────────────────────────────────────

// function buildBlogApp(): BlogController {
//     $db         = new InMemoryDatabase();
//     $logger     = new ConsoleLogger();
//     $mailer     = new ConsoleMailer();
//     $repository = new BlogPostRepository($db, $logger);
//     $service    = new BlogPostService($repository, $mailer, $logger);
//     return new BlogController($service, $logger);
// }
//
// echo "\n=== Flat IoC wiring ===\n\n";
// $flatController = buildBlogApp();
// echo $flatController->handleRequest('listPosts') . "\n";


// ─────────────────────────────────────────────────────────────────────────────
// TODO Task 7: Replace wiring function with a MiniContainer
// ─────────────────────────────────────────────────────────────────────────────

// class MiniContainer { ... }
//
// $container = new MiniContainer();
// $container->bind(DatabaseInterface::class,    InMemoryDatabase::class);
// $container->bind(LoggerInterface::class,      ConsoleLogger::class);
// $container->bind(MailerInterface::class,       ConsoleMailer::class);
// $container->bind(BlogRepositoryInterface::class, BlogPostRepository::class);
//
// echo "\n=== Container auto-wiring ===\n\n";
// $containerController = $container->make(BlogController::class);
// echo $containerController->handleRequest('listPosts') . "\n";


// ─────────────────────────────────────────────────────────────────────────────
// TODO Task 8: Test wiring with anonymous stubs
// ─────────────────────────────────────────────────────────────────────────────

// $fakeRepo = new class implements BlogRepositoryInterface { ... };
// $nullLogger = new class implements LoggerInterface { ... };
// $nullMailer = new class implements MailerInterface { ... };
// ...
// assert response contains "success":true