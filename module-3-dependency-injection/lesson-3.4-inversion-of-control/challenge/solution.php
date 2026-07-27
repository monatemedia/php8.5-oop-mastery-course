<?php
declare(strict_types=1);

/**
 * CHALLENGE SOLUTION — Lesson 3.4: Inversion of Control
 * ───────────────────────────────────────────────────────
 * ⚠️  Only open this file after completing starter.php yourself.
 *
 * The starter had a four-level coupling chain in which every class built its
 * own collaborators:
 *
 *     BlogController → BlogPostService → BlogPostRepository → InMemoryDatabase
 *
 * Nothing in that chain could be tested, and swapping the database meant
 * editing a class three levels down. This solution inverts all of it.
 *
 * The three wirings at the bottom are the point of the lesson. Each builds the
 * SAME object graph a different way, and no class changes between them:
 *
 *   Task 6 — a flat wiring function     (explicit, obvious, verbose)
 *   Task 7 — a MiniContainer            (reflection does the wiring for you)
 *   Task 8 — a test wiring with stubs   (no database, no mailer, no output)
 *
 * That is Inversion of Control in one sentence: the classes stopped deciding
 * what they depend on, so the composition root can decide instead.
 */


// ─────────────────────────────────────────────────────────────────────────────
// TASK 1 SOLUTION — the four abstractions
//
// These are the contracts both sides depend on. High-level classes (service,
// controller) depend on them; low-level classes (database, mailer) implement
// them. Neither depends on the other. That is the Dependency Inversion
// Principle, and these interfaces are where it actually happens.
// ─────────────────────────────────────────────────────────────────────────────

interface DatabaseInterface {
    public function query(string $sql, array $params = []): array;
    public function execute(string $sql, array $params = []): bool;
}

interface LoggerInterface {
    public function log(string $level, string $message): void;
}

interface MailerInterface {
    public function send(string $to, string $subject, string $body): bool;
}

interface BlogRepositoryInterface {
    public function findAll(): array;
    public function findById(int $id): ?array;
    public function save(array $post): bool;
}


// ─────────────────────────────────────────────────────────────────────────────
// TASK 2 SOLUTION — infrastructure implements the contracts
//
// Note the direction of the arrow. InMemoryDatabase now points AT
// DatabaseInterface. Before, BlogPostRepository pointed at InMemoryDatabase.
// The dependency has been inverted: the concrete class is now the one doing
// the depending.
// ─────────────────────────────────────────────────────────────────────────────

class InMemoryDatabase implements DatabaseInterface {
    private array $posts = [
        1 => ['id' => 1, 'title' => 'Hello PHP 8.5',   'status' => 'published', 'author' => 'alice@example.com'],
        2 => ['id' => 2, 'title' => 'IoC in Practice', 'status' => 'published', 'author' => 'bob@example.com'],
        3 => ['id' => 3, 'title' => 'DI vs DIP',       'status' => 'draft',     'author' => 'alice@example.com'],
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

class ConsoleLogger implements LoggerInterface {
    public function log(string $level, string $message): void {
        echo "  [{$level}] {$message}\n";
    }
}

class ConsoleMailer implements MailerInterface {
    public function send(string $to, string $subject, string $body): bool {
        echo "  [MAIL] To: {$to} | {$subject}\n";
        return true;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// TASKS 3, 4, 5 SOLUTION — every dependency arrives through the constructor
//
// KEY CHANGES vs the starter:
//   - Zero `new` keywords inside any of these three classes
//   - Every property is typed against an INTERFACE, never a concrete class
//   - Constructor property promotion (PHP 8.0) removes the assignment
//     boilerplate entirely
//   - The method bodies are UNCHANGED. Inverting dependencies changes how a
//     class is built, never what it does.
// ─────────────────────────────────────────────────────────────────────────────

class BlogPostRepository implements BlogRepositoryInterface {
    public function __construct(
        private DatabaseInterface $db,
        private LoggerInterface   $logger
    ) {}

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
    public function __construct(
        private BlogRepositoryInterface $repository,
        private MailerInterface         $mailer,
        private LoggerInterface         $logger
    ) {}

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
    // BlogPostService stays a concrete type deliberately. There will only ever
    // be one of it, and it holds no infrastructure — its own dependencies are
    // already inverted. A BlogPostServiceInterface with exactly one
    // implementation would be indirection for its own sake.
    public function __construct(
        private BlogPostService $service,
        private LoggerInterface $logger
    ) {}

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
// TASK 7 SOLUTION — a minimal auto-wiring container
//
// The flat function below works perfectly well without this. It exists to show
// that a container is not magic: read the constructor with Reflection, resolve
// each parameter's type, recurse. That is the whole idea.
//
// Module 4 rebuilds this properly, then replaces it with PHP-DI.
// ─────────────────────────────────────────────────────────────────────────────

final class MiniContainer {
    /** @var array<string,string> abstract (interface) => concrete class */
    private array $bindings = [];

    /** @var array<string,object> instances reused within one object graph */
    private array $instances = [];

    public function bind(string $abstract, string $concrete): void {
        $this->bindings[$abstract] = $concrete;
    }

    public function make(string $id): object {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // An interface resolves to whatever was bound to it. A concrete class
        // resolves to itself — which is why BlogController and BlogPostService
        // need no binding at all.
        $concrete = $this->bindings[$id] ?? $id;

        if (!class_exists($concrete)) {
            throw new \RuntimeException(
                "Cannot resolve '{$id}': no binding registered and no such class."
            );
        }

        $ref = new \ReflectionClass($concrete);
        if (!$ref->isInstantiable()) {
            throw new \RuntimeException("'{$concrete}' cannot be instantiated.");
        }

        $args = [];
        $ctor = $ref->getConstructor();

        if ($ctor !== null) {
            foreach ($ctor->getParameters() as $param) {
                $type = $param->getType();

                // Scalars cannot be auto-wired. Fall back to a default if one
                // exists; otherwise this genuinely cannot be resolved.
                if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                    if ($param->isDefaultValueAvailable()) {
                        $args[] = $param->getDefaultValue();
                        continue;
                    }
                    throw new \RuntimeException(
                        "Cannot auto-wire \${$param->getName()} of {$concrete}: not a class type."
                    );
                }

                $args[] = $this->make($type->getName());   // ← the recursion
            }
        }

        $object = $ref->newInstanceArgs($args);
        $this->instances[$id] = $object;

        return $object;
    }
}


// ═════════════════════════════════════════════════════════════════════════════
// THE THREE WIRINGS
//
// Every class above is now finished. Nothing below modifies any of them — each
// section assembles the same graph a different way. That is the payoff.
// ═════════════════════════════════════════════════════════════════════════════


// ─────────────────────────────────────────────────────────────────────────────
// TASK 6 SOLUTION — flat wiring function (the composition root)
// ─────────────────────────────────────────────────────────────────────────────

function buildBlogApp(): BlogController {
    $db         = new InMemoryDatabase();
    $logger     = new ConsoleLogger();
    $mailer     = new ConsoleMailer();
    $repository = new BlogPostRepository($db, $logger);
    $service    = new BlogPostService($repository, $mailer, $logger);

    return new BlogController($service, $logger);
}

echo "=== Flat IoC wiring ===\n\n";
$flatController = buildBlogApp();
echo $flatController->handleRequest('listPosts') . "\n";


// ─────────────────────────────────────────────────────────────────────────────
// TASK 7 SOLUTION — the same graph, assembled by the container
// ─────────────────────────────────────────────────────────────────────────────

echo "\n=== Container auto-wiring ===\n\n";

$container = new MiniContainer();
$container->bind(DatabaseInterface::class,       InMemoryDatabase::class);
$container->bind(LoggerInterface::class,         ConsoleLogger::class);
$container->bind(MailerInterface::class,         ConsoleMailer::class);
$container->bind(BlogRepositoryInterface::class, BlogPostRepository::class);

// One line. The container reads every constructor and works out the rest.
$containerController = $container->make(BlogController::class);
echo $containerController->handleRequest('listPosts') . "\n";


// ─────────────────────────────────────────────────────────────────────────────
// TASK 8 SOLUTION — test wiring with anonymous stubs
//
// No database. No mailer. No log output. This is the wiring a unit test would
// use, and it is only possible because nothing constructs its own
// collaborators any more.
// ─────────────────────────────────────────────────────────────────────────────

echo "\n=== Test wiring (anonymous stubs) ===\n\n";

$fakeRepo = new class implements BlogRepositoryInterface {
    public array $saved = [];

    public function findAll(): array {
        return [
            ['id' => 99, 'title' => 'Stubbed Post', 'status' => 'published', 'author' => 'test@example.com'],
        ];
    }

    public function findById(int $id): ?array {
        return $id === 99
            ? ['id' => 99, 'title' => 'Stubbed Post', 'status' => 'published', 'author' => 'test@example.com']
            : null;
    }

    public function save(array $post): bool {
        $this->saved[] = $post;
        return true;
    }
};

// Spy — records what it was asked to send, so a test can assert on it.
$spyMailer = new class implements MailerInterface {
    public array $sent = [];

    public function send(string $to, string $subject, string $body): bool {
        $this->sent[] = ['to' => $to, 'subject' => $subject, 'body' => $body];
        return true;
    }
};

// Null object — satisfies the contract, does nothing, prints nothing.
$nullLogger = new class implements LoggerInterface {
    public function log(string $level, string $message): void {}
};

$testController = new BlogController(
    new BlogPostService($fakeRepo, $spyMailer, $nullLogger),
    $nullLogger
);

$response = $testController->handleRequest('listPosts');
echo "Response reports success             — " . (str_contains($response, '"success": true') ? 'PASS' : 'FAIL') . "\n";
echo "Response contains the stubbed post   — " . (str_contains($response, 'Stubbed Post') ? 'PASS' : 'FAIL') . "\n";
echo "No database was touched              — PASS (InMemoryDatabase never constructed)\n";

// Publishing should notify the author. Assert on the spy, not on stdout.
$testController->handleRequest('publishPost', ['id' => 99]);
echo "Mailer received exactly one message  — " . (count($spyMailer->sent) === 1 ? 'PASS' : 'FAIL') . "\n";
echo "Message went to the post's author    — "
   . (($spyMailer->sent[0]['to'] ?? null) === 'test@example.com' ? 'PASS' : 'FAIL') . "\n";


// ─────────────────────────────────────────────────────────────────────────────
// WHAT TO COMPARE IN YOUR OWN SOLUTION
// ─────────────────────────────────────────────────────────────────────────────

echo "\n--- Self-review checklist ---\n";
echo "[ ] Are all four interfaces defined, with the methods the classes actually use?\n";
echo "[ ] Do InMemoryDatabase, ConsoleLogger, ConsoleMailer and BlogPostRepository implement them?\n";
echo "[ ] Is there a single 'new' left inside any of the three refactored classes?\n";
echo "[ ] Are the property types INTERFACES rather than concrete class names?\n";
echo "[ ] Did any method body change? (It should not have — only construction changed.)\n";
echo "[ ] Does your container recurse into constructor parameters it cannot resolve directly?\n";
echo "[ ] Could you swap InMemoryDatabase for PostgresDatabase by editing one line?\n";

// ═══════════════════════════════════════════════════════════════════════
// ACCEPTANCE CHECKS
// ───────────────────────────────────────────────────────────────────────
// This challenge is a REFACTOR: the program prints the same thing before
// and after you do the work, so comparing output cannot tell whether you
// have done it. These checks inspect the STRUCTURE instead.
//
// They fail on the untouched starter and pass once the refactor is
// complete. `check.php` marks the lesson done on the ACCEPTANCE line.
// ═══════════════════════════════════════════════════════════════════════

echo "\n--- Acceptance ---\n";

$acceptance = [
    'All four interfaces are defined'
        => interface_exists('DatabaseInterface')
           && interface_exists('LoggerInterface')
           && interface_exists('MailerInterface')
           && interface_exists('BlogRepositoryInterface'),

    'Concrete classes implement their interfaces'
        => is_a('InMemoryDatabase', 'DatabaseInterface', true)
           && is_a('ConsoleLogger', 'LoggerInterface', true)
           && is_a('ConsoleMailer', 'MailerInterface', true)
           && is_a('BlogPostRepository', 'BlogRepositoryInterface', true),

    'BlogPostRepository takes its dependencies via the constructor'
        => class_exists('BlogPostRepository')
           && (new ReflectionClass('BlogPostRepository'))->getConstructor() !== null
           && count((new ReflectionClass('BlogPostRepository'))->getConstructor()->getParameters()) >= 2,

    'BlogPostRepository type-hints against interfaces, not concretions'
        => class_exists('BlogPostRepository')
           && (function (): bool {
                  $ctor = (new ReflectionClass('BlogPostRepository'))->getConstructor();
                  if ($ctor === null) { return false; }
                  foreach ($ctor->getParameters() as $p) {
                      $t = $p->getType();
                      if ($t instanceof ReflectionNamedType && !$t->isBuiltin()
                          && !interface_exists($t->getName())) {
                          return false;
                      }
                  }
                  return true;
              })(),

    'No class creates its own collaborators with new inside the constructor'
        => !preg_match(
               '/__construct\([^)]*\)\s*\{[^}]*\bnew\s+(?:InMemoryDatabase|ConsoleLogger|ConsoleMailer|BlogPostRepository)\b/s',
               (string) file_get_contents(__FILE__)
           ),
];

$allPassed = true;
foreach ($acceptance as $label => $passed) {
    echo '  ' . ($passed ? 'PASS' : 'FAIL') . '  ' . $label . "\n";
    $allPassed = $allPassed && $passed;
}
echo $allPassed
    ? "ACCEPTANCE: all checks passed\n"
    : "ACCEPTANCE: not yet complete\n";
