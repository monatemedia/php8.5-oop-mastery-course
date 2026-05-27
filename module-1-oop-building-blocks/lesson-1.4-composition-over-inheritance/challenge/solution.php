<?php
declare(strict_types=1);

/**
 * CHALLENGE SOLUTION — Lesson 1.4: Composition over Inheritance
 * ───────────────────────────────────────────────────────────────
 * ⚠️  Only open this file after completing starter.php yourself.
 *
 * Key things to compare in your solution:
 *   1. Zero `extends` in the domain layer — ContentItem and PublishableContent are gone
 *   2. BlogPost and VideoPost implement ContentInterface only
 *   3. ContentPublisher is a standalone injectable class
 *   4. Both classes have their OWN validate() — no shared parent validation
 *   5. VideoPost::validate() correctly validates the URL — LSP violation fixed
 *   6. Composition root wires all dependencies explicitly
 *   7. Test wiring uses anonymous class stubs — no real infrastructure
 */


// ─────────────────────────────────────────────────────────────────────────────
// Task 1 — Interfaces
// ─────────────────────────────────────────────────────────────────────────────

interface ContentInterface {
    public function getId(): string;
    public function getTitle(): string;
    public function validate(): bool;
    public function publish(): bool;
}

interface StorageInterface {
    public function save(string $id, array $data): bool;
    public function find(string $id): ?array;
}

interface LoggerInterface {
    public function log(string $level, string $message): void;
}

interface PublisherInterface {
    public function publish(string $contentId, array $metadata): bool;
}


// ─────────────────────────────────────────────────────────────────────────────
// Task 2 — Null Object + concrete implementations
// ─────────────────────────────────────────────────────────────────────────────

class NullLogger implements LoggerInterface {
    public function log(string $level, string $message): void {}
}

class InMemoryStorage implements StorageInterface {
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

class ConsoleLogger implements LoggerInterface {
    public function log(string $level, string $message): void {
        echo "  [{$level}] {$message}\n";
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Task 5 — ContentPublisher: publishing workflow extracted to a standalone class
// ─────────────────────────────────────────────────────────────────────────────

class ContentPublisher implements PublisherInterface {
    public function __construct(
        private StorageInterface $storage,
        private LoggerInterface  $logger
    ) {}

    public function publish(string $contentId, array $metadata): bool {
        $this->logger->log('INFO', "Publishing content: {$contentId}");
        $result = $this->storage->save($contentId, array_merge(
            ['id' => $contentId, 'published_at' => date('Y-m-d H:i:s'), 'status' => 'published'],
            $metadata
        ));
        return $result;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Task 3 — BlogPost: no inheritance, implements ContentInterface
// ─────────────────────────────────────────────────────────────────────────────

class BlogPost implements ContentInterface {
    private LoggerInterface $logger;

    public function __construct(
        private string             $id,
        private string             $title,
        private string             $body,
        private string             $authorEmail,
        private StorageInterface   $storage,
        private PublisherInterface $publisher,
        ?LoggerInterface           $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->logger->log('INFO', "Creating blog post: {$title}");
    }

    public function getId(): string    { return $this->id; }
    public function getTitle(): string { return $this->title; }

    // BlogPost's OWN validation — independent, specific to blog posts
    public function validate(): bool {
        $valid = strlen($this->title) >= 5
              && strlen($this->body)  >= 20
              && filter_var($this->authorEmail, FILTER_VALIDATE_EMAIL) !== false;

        if ($valid) {
            $this->logger->log('INFO', "BlogPost validated: {$this->title}");
        } else {
            $this->logger->log('ERROR', "BlogPost validation failed: {$this->title}");
        }
        return $valid;
    }

    public function publish(): bool {
        if (!$this->validate()) return false;

        $published = $this->publisher->publish($this->id, [
            'title'        => $this->title,
            'author_email' => $this->authorEmail,
            'body_length'  => strlen($this->body),
        ]);

        if ($published) {
            $this->logger->log('INFO', "BlogPost published successfully");
        }
        return $published;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Task 4 — VideoPost: no inheritance, implements ContentInterface
// LSP violation fixed — validate() now properly checks the URL
// ─────────────────────────────────────────────────────────────────────────────

class VideoPost implements ContentInterface {
    private LoggerInterface $logger;

    public function __construct(
        private string             $id,
        private string             $title,
        private string             $videoUrl,
        private StorageInterface   $storage,
        private PublisherInterface $publisher,
        ?LoggerInterface           $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->logger->log('INFO', "Creating video post: {$title}");
    }

    public function getId(): string    { return $this->id; }
    public function getTitle(): string { return $this->title; }

    // VideoPost's OWN validation — specific to video posts, LSP-safe
    public function validate(): bool {
        $validTitle = strlen($this->title) >= 5;
        $validUrl   = filter_var($this->videoUrl, FILTER_VALIDATE_URL) !== false
                   && (str_contains($this->videoUrl, 'youtube.com')
                    || str_contains($this->videoUrl, 'vimeo.com'));

        $valid = $validTitle && $validUrl;

        if ($valid) {
            $this->logger->log('INFO', "VideoPost validated: {$this->title}");
        } else {
            $this->logger->log('ERROR', "VideoPost validation failed: {$this->title}");
        }
        return $valid;
    }

    public function publish(): bool {
        if (!$this->validate()) return false;

        $published = $this->publisher->publish($this->id, [
            'title'     => $this->title,
            'video_url' => $this->videoUrl,
        ]);

        if ($published) {
            $this->logger->log('INFO', "VideoPost published successfully");
        }
        return $published;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Task 6 — Production composition root
// ─────────────────────────────────────────────────────────────────────────────

echo "=== Production wiring ===\n\n";

$storage   = new InMemoryStorage();
$logger    = new ConsoleLogger();
$publisher = new ContentPublisher($storage, $logger);

$blog = new BlogPost(
    'blog-001',
    'PHP 8.5 Features',
    'This is a detailed post about the new features in PHP 8.5 and how to use them.',
    'alice@example.com',
    $storage,
    $publisher,
    $logger
);

$video = new VideoPost(
    'video-001',
    'PHP 8.5 Demo',
    'https://youtube.com/watch?v=abc123',
    $storage,
    $publisher,
    $logger
);

$blog->publish();
echo "\n";
$video->publish();


// ─────────────────────────────────────────────────────────────────────────────
// Task 7 — Test wiring with anonymous class stubs
// ─────────────────────────────────────────────────────────────────────────────

echo "\n=== Test wiring (anonymous stubs) ===\n\n";

// Spy logger — records all calls
$spyLogger = new class implements LoggerInterface {
    public array $entries = [];
    public function log(string $level, string $message): void {
        $this->entries[] = compact('level', 'message');
    }
    public function hasEntry(string $level, string $partial): bool {
        foreach ($this->entries as $e) {
            if ($e['level'] === $level && str_contains($e['message'], $partial)) return true;
        }
        return false;
    }
};

// Fake storage — in-memory, no side effects
$fakeStorage = new class implements StorageInterface {
    public array $saved = [];
    public function save(string $id, array $data): bool {
        $this->saved[$id] = $data;
        return true;
    }
    public function find(string $id): ?array { return $this->saved[$id] ?? null; }
};

// Fake publisher — always succeeds
$fakePublisher = new class implements PublisherInterface {
    public array $published = [];
    public function publish(string $contentId, array $metadata): bool {
        $this->published[] = $contentId;
        return true;
    }
};

// Wire with stubs
$testBlog = new BlogPost(
    'blog-test',
    'Test Title',
    'This body is long enough to pass the minimum length validation requirement.',
    'test@example.com',
    $fakeStorage,
    $fakePublisher,
    $spyLogger
);

$testVideo = new VideoPost(
    'video-test',
    'Test Video',
    'https://youtube.com/watch?v=test123',
    $fakeStorage,
    $fakePublisher,
    $spyLogger
);

$blogValid  = $testBlog->validate();
$videoValid = $testVideo->validate();
$testBlog->publish();
$testVideo->publish();

// Assertions
$assertions = [
    'BlogPost validates correctly'   => $blogValid === true,
    'VideoPost validates correctly'  => $videoValid === true,
    'Logger captured 6+ entries'     => count($spyLogger->entries) >= 6,
    'Blog publish logged'            => $spyLogger->hasEntry('INFO', 'BlogPost published'),
    'Video publish logged'           => $spyLogger->hasEntry('INFO', 'VideoPost published'),
    'Blog was saved in fake storage' => isset($fakeStorage->saved['blog-test']),
    'Video was published'            => in_array('video-test', $fakePublisher->published, true),
];

echo "Spy logger entries: " . count($spyLogger->entries) . "\n";
echo "validate results: BlogPost={$blogValid}, VideoPost={$videoValid}\n\n";

$allPassed = true;
foreach ($assertions as $label => $result) {
    echo "  " . ($result ? '✓' : '✗') . " {$label}\n";
    if (!$result) $allPassed = false;
}
echo "\n" . ($allPassed ? "All assertions PASSED" : "Some assertions FAILED") . "\n";


// ─────────────────────────────────────────────────────────────────────────────
// SELF-REVIEW CHECKLIST
// ─────────────────────────────────────────────────────────────────────────────
echo "\n--- Self-review checklist ---\n";
echo "[ ] ContentItem and PublishableContent are gone from the domain layer?\n";
echo "[ ] BlogPost and VideoPost have no 'extends' keyword?\n";
echo "[ ] Both implement ContentInterface?\n";
echo "[ ] ContentPublisher is a standalone class, injected into both content types?\n";
echo "[ ] BlogPost::validate() checks title length, body length, AND email format?\n";
echo "[ ] VideoPost::validate() checks title length AND URL format (LSP fixed)?\n";
echo "[ ] NullLogger used as the default when no logger is injected?\n";
echo "[ ] Production wiring uses explicit composition root?\n";
echo "[ ] Test wiring uses anonymous stubs — no real storage, no real logger?\n";
echo "[ ] All seven test assertions pass?\n";