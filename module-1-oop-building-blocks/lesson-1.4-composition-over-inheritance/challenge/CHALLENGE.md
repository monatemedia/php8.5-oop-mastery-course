# Code Challenge — Lesson 1.4: Composition over Inheritance

> **Refactor a three-level inheritance chain to use composition**

---

## The Brief

You have inherited a content management system with a three-level inheritance chain that was built using inheritance for all the wrong reasons — code reuse, type grouping, and adding behaviour by extending a concrete class. The system works, but it has every problem the lesson described: a fragile base class, impossible isolation testing, constructor coupling chains, and a looming LSP violation.

Your job is to refactor the entire system to use composition — zero levels of inheritance in the domain layer, with interfaces providing the type contracts.

---

## What the Starter Code Has

Open `starter.php`. You will find:

```
ContentItem          ← Level 1: abstract base with DB + logger hardwired
    └── PublishableContent   ← Level 2: adds publishing workflow
            └── BlogPost     ← Level 3: the actual domain class
            └── VideoPost    ← Level 3: another domain class
```

The problems present in the starter code:
1. `ContentItem` creates `InMemoryDatabase` and `FileLogger` in its constructor — no injection possible
2. `BlogPost` and `VideoPost` cannot be tested without satisfying all three parent constructors
3. `VideoPost::validate()` weakens the postcondition of `ContentItem::validate()` — an LSP violation
4. Adding a third content type (e.g. `PodcastPost`) requires extending the chain further
5. A container cannot auto-wire any class because constructors have no parameters

---

## Your Tasks

Work in `starter.php`. Do NOT look at `solution.php` until you have made a genuine attempt.

### Task 1 — Define four interfaces

```php
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
```

### Task 2 — Create Null Object implementations
- `NullLogger implements LoggerInterface`
- Keep existing `InMemoryStorage` and `ConsoleLogger` as named implementations

### Task 3 — Refactor `BlogPost` (eliminate all inheritance)
`BlogPost` must:
- Implement `ContentInterface`
- Accept `StorageInterface`, `LoggerInterface`, and `PublisherInterface` via constructor
- Have no `extends` keyword
- Have its OWN `validate()` that checks title length and body length
- Default logger to `NullLogger`

### Task 4 — Refactor `VideoPost` (eliminate all inheritance)
`VideoPost` must:
- Implement `ContentInterface`
- Accept `StorageInterface` and `LoggerInterface` via constructor
- Have its OWN `validate()` that checks title length and URL format
- No LSP violation — `validate()` must honour the same contract as before

### Task 5 — Create `ContentPublisher implements PublisherInterface`
Extract the publishing workflow from `PublishableContent` into a standalone class that:
- Accepts `StorageInterface` and `LoggerInterface` via constructor
- Has a `publish(string $contentId, array $metadata): bool` method
- Can be injected into any content type that needs publishing

### Task 6 — Wire at the composition root
Replace the current `new BlogPost()` and `new VideoPost()` calls at the bottom of the file with an explicit composition root — inject all dependencies from outside.

### Task 7 — Add a test wiring
Add a second wiring that uses anonymous class stubs for `StorageInterface` and a spy for `LoggerInterface`. Call `validate()`, `publish()`, and assert on the spy's recorded entries.

---

## Acceptance Criteria

- [ ] `ContentItem`, `PublishableContent` classes are gone (or commented out) — zero inheritance in domain layer
- [ ] `BlogPost` and `VideoPost` implement `ContentInterface` only
- [ ] Both classes have zero `extends` keywords
- [ ] All four interfaces defined
- [ ] Constructor injection used for all dependencies
- [ ] `ContentPublisher` is a standalone injectable class
- [ ] `validate()` in both classes has its OWN logic — no shared parent
- [ ] Composition root at the bottom wires all dependencies explicitly
- [ ] Test wiring uses anonymous class stubs — no real infrastructure
- [ ] All existing output is preserved exactly

---

## Expected Output

```
=== Production wiring ===
[INFO] Creating blog post: PHP 8.5 Features
[INFO] BlogPost validated: PHP 8.5 Features
[INFO] Publishing content: blog-001
[STORAGE] Saved: blog-001
[INFO] BlogPost published successfully

[INFO] Creating video post: PHP 8.5 Demo
[INFO] VideoPost validated: PHP 8.5 Demo
[INFO] Publishing content: video-001
[STORAGE] Saved: video-001
[INFO] VideoPost published successfully

=== Test wiring (anonymous stubs) ===
Spy logger entries: 6
validate results: BlogPost=true, VideoPost=true
All assertions PASSED
```

---

## Hints

- The `ContentPublisher` is the key insight — the publishing *workflow* is a collaborator that content classes *use*, not something they inherit from.
- `validate()` must be implemented independently in each class. There is no shared validation logic — `BlogPost` and `VideoPost` have genuinely different rules.
- See `examples/03-composing-behaviour.php` Pattern 1 (constructor injection) and Pattern 2 (setter injection with NullLogger) for reference.