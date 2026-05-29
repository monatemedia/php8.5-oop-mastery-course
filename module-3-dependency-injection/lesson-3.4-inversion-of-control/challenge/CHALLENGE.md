# Code Challenge — Lesson 3.4: Inversion of Control (IoC)

> **Fully invert a multi-class application's dependencies**

---

## The Brief

You have a small blog publishing system where every class creates its own dependencies — a chain of tight coupling running from the controller all the way down to the database. Your job is to fully apply IoC: invert every dependency, write a proper wiring function at the entry point, and then replace that wiring function with a minimal Reflection-based container.

---

## What the Starter Code Has

Open `starter.php`. You will find a four-class system:

```
BlogController
    └── creates → BlogPostService
                        └── creates → BlogPostRepository
                                            └── creates → InMemoryDatabase
                                                        → ConsoleLogger
                        └── creates → ConsoleLogger
                        └── creates → ConsoleMailer
    └── creates → ConsoleLogger
```

Every class creates its own dependencies. The `BlogController` constructor fires a chain of `new` calls that reaches four levels deep before a single piece of business logic runs.

---

## Your Tasks

Work in `starter.php`. Do NOT look at `solution.php` until you have made a genuine attempt.

### Task 1 — Define the interfaces

Define these four interfaces (they are already partially sketched in the starter):
- `DatabaseInterface` — `query()` and `execute()`
- `LoggerInterface` — `log()`
- `MailerInterface` — `send()`
- `BlogRepositoryInterface` — `findAll()`, `findById()`, `save()`

### Task 2 — Make concrete classes implement interfaces

Update `InMemoryDatabase`, `ConsoleLogger`, and `ConsoleMailer` to implement their respective interfaces.

### Task 3 — Invert `BlogPostRepository`

Remove all `new` calls from its constructor. Accept:
- `DatabaseInterface $db`
- `LoggerInterface $logger`

### Task 4 — Invert `BlogPostService`

Remove all `new` calls from its constructor. Accept:
- `BlogRepositoryInterface $repository`
- `MailerInterface $mailer`
- `LoggerInterface $logger`

### Task 5 — Invert `BlogController`

Remove all `new` calls from its constructor. Accept:
- `BlogPostService $service`
- `LoggerInterface $logger`

### Task 6 — Write a flat IoC wiring function

Replace the current `new BlogController()` call at the bottom with a `function buildBlogApp(): BlogController` that wires the entire graph. All `new` calls for services must live inside this function only.

### Task 7 — Replace the wiring function with a container

After Task 6 works, add a `MiniContainer` class (you can copy the pattern from `examples/04-manual-ioc-container.php`) and rewire the application using it instead of the flat function. Bind all four interfaces to their concrete classes.

### Task 8 — Verify testability

At the bottom, add a test wiring section that uses anonymous class stubs for all four interfaces. Call `handleRequest()` and assert the response contains `"success": true`.

---

## Acceptance Criteria

- [ ] Four interfaces defined
- [ ] All three concrete classes implement the correct interface
- [ ] `BlogPostRepository`, `BlogPostService`, `BlogController` have zero `new` calls on services
- [ ] Flat wiring function produces correct output
- [ ] `MiniContainer` resolves the full graph automatically
- [ ] Container wiring produces identical output to the flat function
- [ ] Test wiring with anonymous stubs: no real infrastructure, assertion passes
- [ ] Total `new` calls inside the four classes: **0**

---

## Expected Output

```
=== Flat IoC wiring ===
[INFO] Handling request: listPosts
[INFO] Fetching all posts
[DB] Query: SELECT * FROM blog_posts
[INFO] Returning 3 posts
Response: {"success":true,"posts":[...]}

=== Container auto-wiring ===
[INFO] Handling request: listPosts
[INFO] Fetching all posts
[DB] Query: SELECT * FROM blog_posts
[INFO] Returning 3 posts
Response: {"success":true,"posts":[...]}

=== Test wiring (anonymous stubs) ===
Assertion: response contains "success":true → PASSED
```