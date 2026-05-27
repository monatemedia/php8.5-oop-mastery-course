<?php
declare(strict_types=1);

/**
 * CHALLENGE STARTER — Lesson 1.4: Composition over Inheritance
 * ──────────────────────────────────────────────────────────────
 * Read CHALLENGE.md before touching this file.
 *
 * This CMS has a three-level inheritance chain with five problems:
 *   1. ContentItem hardwires InMemoryDatabase and FileLogger in its constructor
 *   2. BlogPost/VideoPost cannot be tested without all three parent constructors
 *   3. VideoPost::validate() violates LSP — weakens the parent's postcondition
 *   4. Adding new content types requires extending the chain further
 *   5. A container cannot auto-wire any class — constructors have no parameters
 *
 * Your job: eliminate all inheritance in the domain layer.
 * Replace it with interfaces + composition + constructor injection.
 *
 * Do NOT look at solution.php until you have made a genuine attempt.
 */


// ─────────────────────────────────────────────────────────────────────────────
// TODO Task 1: Define four interfaces here
// ContentInterface, StorageInterface, LoggerInterface, PublisherInterface
// ─────────────────────────────────────────────────────────────────────────────


// ─────────────────────────────────────────────────────────────────────────────
// TODO Task 2: Create NullLogger implements LoggerInterface
// ─────────────────────────────────────────────────────────────────────────────


// ─────────────────────────────────────────────────────────────────────────────
// INFRASTRUCTURE — keep these, just make them implement your interfaces
// ─────────────────────────────────────────────────────────────────────────────

class InMemoryStorage { // TODO: implement StorageInterface
    private array $store = [];

    public function save(string $id, array $data): bool {
        $this->store[$id] = $data;
        echo "  [STORAGE] Saved: {$id}\n";
        return true;
    }

    public function find(string $id): ?array {
        return $this->store[$id] ?? null;
    }
}

class ConsoleLogger { // TODO: implement LoggerInterface
    public function log(string $level, string $message): void {
        echo "  [{$level}] {$message}\n";
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// THE INHERITANCE CHAIN — refactor this away (Tasks 3–5)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Level 1 — ContentItem (abstract base)
 * ❌ Problem: hardwires DB and logger, forces all subclasses to carry DB param
 */
abstract class ContentItem {
    protected InMemoryStorage $storage;
    protected ConsoleLogger   $logger;
    protected array           $data = [];

    public function __construct(protected string $id, protected string $title) {
        // ❌ Creates own dependencies — no injection possible
        $this->storage = new InMemoryStorage();
        $this->logger  = new ConsoleLogger();
        $this->logger->log('INFO', "Creating content: {$title}");
    }

    abstract public function validate(): bool;

    public function getId(): string    { return $this->id; }
    public function getTitle(): string { return $this->title; }
}

/**
 * Level 2 — PublishableContent
 * ❌ Problem: publishing workflow buried here — not injectable or reusable
 */
abstract class PublishableContent extends ContentItem {
    private bool $published = false;

    public function publish(): bool {
        if (!$this->validate()) {
            $this->logger->log('ERROR', "Validation failed for: {$this->title}");
            return false;
        }
        $this->data['published_at'] = date('Y-m-d H:i:s');
        $this->data['status']       = 'published';
        $this->storage->save($this->id, array_merge(
            ['id' => $this->id, 'title' => $this->title],
            $this->data
        ));
        $this->logger->log('INFO', "{$this->getType()} published successfully");
        $this->published = true;
        return true;
    }

    abstract protected function getType(): string;
}

/**
 * Level 3 — BlogPost
 * ❌ Problem: cannot test in isolation without satisfying two parent constructors
 */
class BlogPost extends PublishableContent {
    public function __construct(
        string $id,
        string $title,
        private string $body,
        private string $authorEmail
    ) {
        parent::__construct($id, $title);
        $this->logger->log('INFO', "Creating blog post: {$title}");
    }

    public function validate(): bool {
        $valid = strlen($this->title) >= 5 && strlen($this->body) >= 20;
        if ($valid) {
            $this->logger->log('INFO', "BlogPost validated: {$this->title}");
        }
        return $valid;
    }

    protected function getType(): string { return 'BlogPost'; }
}

/**
 * Level 3 — VideoPost
 * ❌ Problem: validate() ALWAYS returns true — LSP violation
 *    Parent contract says validate() checks for validity.
 *    VideoPost weakens this by skipping URL validation.
 */
class VideoPost extends PublishableContent {
    public function __construct(
        string $id,
        string $title,
        private string $videoUrl
    ) {
        parent::__construct($id, $title);
        $this->logger->log('INFO', "Creating video post: {$title}");
    }

    // ❌ LSP violation — always returns true regardless of URL validity
    public function validate(): bool {
        $this->logger->log('INFO', "VideoPost validated: {$this->title}");
        return true; // silently accepts invalid URLs
    }

    protected function getType(): string { return 'VideoPost'; }
}

// TODO Task 5: Create ContentPublisher implements PublisherInterface
// Extract publish() logic from PublishableContent into this standalone class


// TODO Task 3: Refactor BlogPost — remove extends, implement ContentInterface
// TODO Task 4: Refactor VideoPost — remove extends, implement ContentInterface


// ─────────────────────────────────────────────────────────────────────────────
// CURRENT usage — replace this with a composition root (Task 6 & 7)
// ─────────────────────────────────────────────────────────────────────────────

echo "=== Current (inheritance-based) output ===\n\n";

$blog  = new BlogPost('blog-001', 'PHP 8.5 Features', 'This is a detailed post about the new features in PHP 8.5 and how to use them.', 'alice@example.com');
$video = new VideoPost('video-001', 'PHP 8.5 Demo', 'https://youtube.com/watch?v=abc123');

$blog->publish();
echo "\n";
$video->publish();


// ─────────────────────────────────────────────────────────────────────────────
// TODO Task 6: Replace the above with a production composition root
// ─────────────────────────────────────────────────────────────────────────────

// echo "\n=== Production wiring ===\n\n";
// $storage   = new InMemoryStorage();
// $logger    = new ConsoleLogger();
// $publisher = new ContentPublisher($storage, $logger);
//
// $blog  = new BlogPost('blog-001', 'PHP 8.5 Features',
//     'This is a detailed post about the new features in PHP 8.5 and how to use them.',
//     'alice@example.com', $storage, $logger, $publisher);
//
// $video = new VideoPost('video-001', 'PHP 8.5 Demo',
//     'https://youtube.com/watch?v=abc123', $storage, $logger, $publisher);
//
// $blog->publish();
// echo "\n";
// $video->publish();


// ─────────────────────────────────────────────────────────────────────────────
// TODO Task 7: Add a test wiring with anonymous class stubs
// ─────────────────────────────────────────────────────────────────────────────

// echo "\n=== Test wiring (anonymous stubs) ===\n\n";
// $spyLogger   = new class implements LoggerInterface { ... };
// $fakeStorage = new class implements StorageInterface { ... };
// ... etc