# Lesson 5.4 — Integration Testing with a Real Container
> **Module 5: Automated Testing & TDD** · PHP 8.5 OOP Mastery Course

---

## 📁 Lesson Folder Structure

```
lesson-5.4-integration-testing/
├── README.md                              ← Theory (you are here)
│
├── examples/
│   ├── 01-container-in-tests.php          ← Boot PHP-DI container in setUp()
│   ├── 02-sqlite-integration-test.php     ← Test with a real in-memory SQLite database
│   └── 03-slim-route-test.php             ← Test HTTP routes via Slim request simulation
│
├── challenge/
│   ├── CHALLENGE.md
│   ├── starter/
│   │   └── ApiIntegrationTest.php
│   └── solution/
│       └── ApiIntegrationTest.php
│
└── quiz/
    └── QUIZ.md
```

---

## 1 — Unit Tests vs Integration Tests

The previous three lessons covered **unit tests**: one class under test, all dependencies replaced with doubles. Unit tests are fast, isolated, and precise. They tell you whether individual classes behave correctly.

**Integration tests** verify that multiple real components work correctly when wired together. They use real implementations — real SQL queries, a real container, a real HTTP cycle — instead of doubles.

```
Unit test:
  [OrderService] ← [FakeRepository]
                 ← [StubGateway]
                 ← [SpyMailer]
  Tests: does OrderService react correctly to what its doubles return?

Integration test:
  [Slim App] → [Container] → [OrderController] → [OrderService]
                                                → [SQLiteRepository]  ← real PDO
                                                → [NullMailer]        ← optional double
  Tests: does the real wiring produce the right HTTP response?
```

Neither is better. They answer different questions. A healthy test suite has both.

---

## 2 — The Test Pyramid

```
       ┌─────────┐
       │   E2E   │  ← few, slow, fragile (full browser / real server)
       ├─────────┤
       │  Integ  │  ← some, medium speed (real DB + container + routes)
       ├─────────┤
       │  Unit   │  ← many, fast, isolated (fakes + stubs + spies)
       └─────────┘
```

**Unit tests** form the wide base. Fast, many, targeted.
**Integration tests** cover wiring and infrastructure boundaries.
**End-to-end (E2E) tests** cover the whole system as a black box — covered in Lesson 5.5.

The rule: **test the right thing at the right level**. If a behaviour can be verified with a unit test, do not write an integration test for it. Write an integration test only when you need to verify that real components work together.

---

## 3 — When to Write Integration Tests

| Write an integration test when… | Example |
|----------------------------------|---------|
| Verifying the container wires interfaces correctly | Does `ProductRepositoryInterface` resolve to `SqliteProductRepository`? |
| Testing real SQL queries against a real schema | Does `findAll()` return the seeded rows? |
| Testing HTTP route → controller → service → repository | Does `GET /products` return `200` with a JSON array? |
| Testing error handling across layers | Does `GET /products/99` return `404`? |

| Do NOT write an integration test when… | Use instead |
|----------------------------------------|-------------|
| Testing business logic inside one class | Unit test |
| Testing that a method throws for bad input | Unit test |
| Testing email content | Unit test with spy |
| Testing that a stub returns the right value | Unit test |

---

## 4 — SQLite In-Memory: The Integration Test Database

Real integration tests that touch the database need a database. But a real MySQL or PostgreSQL server is slow to set up, requires credentials, and leaves data around. **SQLite in-memory** solves all three problems:

```php
$pdo = new \PDO('sqlite::memory:');
$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
```

Properties of `sqlite::memory:`:
- **In-process** — no network connection, no server process
- **Destroyed when the connection closes** — no cleanup needed
- **Each test gets a fresh connection** — perfect isolation via `setUp()`
- **Full SQL support** — CREATE TABLE, INSERT, SELECT, JOIN, transactions
- **Runs in microseconds** — 100 integration tests complete in < 1 second

The schema is created in `setUp()`:

```php
protected function setUp(): void
{
    $this->pdo = new \PDO('sqlite::memory:');

    $this->pdo->exec('
        CREATE TABLE products (
            id    INTEGER PRIMARY KEY AUTOINCREMENT,
            name  TEXT    NOT NULL,
            price INTEGER NOT NULL,
            sku   TEXT    NOT NULL UNIQUE
        )
    ');
}
```

`tearDown()` is usually not needed — the connection closes automatically and the in-memory database is destroyed.

---

## 5 — Booting a Container in setUp()

PHP-DI can be booted entirely in `setUp()`, pointing at a test-specific configuration that swaps the real database for an in-memory SQLite one:

```php
protected function setUp(): void
{
    // 1. Create the test database and run the schema
    $this->pdo = new \PDO('sqlite::memory:');
    $this->pdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY AUTOINCREMENT, ...)');

    // 2. Build the container, overriding the PDO binding
    $this->container = (new \DI\ContainerBuilder())
        ->addDefinitions([
            // Real bindings from production config
            \App\Contracts\LoggerInterface::class => \DI\create(\App\Infrastructure\NullLogger::class),

            // Override: inject the test PDO instead of a production connection
            \PDO::class => $this->pdo,

            // Concrete repository bindings
            \App\Domain\Product\ProductRepositoryInterface::class =>
                \DI\autowire(\App\Domain\Product\SqliteProductRepository::class),
        ])
        ->build();

    // 3. Resolve the subject under test from the container
    $this->service = $this->container->get(\App\Domain\Product\ProductService::class);
}
```

This pattern tests that:
1. The container resolves all dependencies correctly
2. The real repository works with real SQL
3. The service integrates correctly with the real repository

---

## 6 — Testing HTTP Routes with Slim

Slim 4 supports request simulation — you can create a real `ServerRequest` object and pass it through the application without a real HTTP server:

```php
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

// Boot the app (real container, real routes, test DB)
$app = AppFactory::createFromContainer($this->container);
(require __DIR__ . '/../config/routes.php')($app);
$app->addErrorMiddleware(false, false, false);

// Simulate a GET request
$request  = (new ServerRequestFactory())->createServerRequest('GET', '/products');
$response = $app->handle($request);

$this->assertSame(200, $response->getStatusCode());

$body = json_decode((string) $response->getBody(), true);
$this->assertIsArray($body);
```

For POST requests with a JSON body:

```php
$request = (new ServerRequestFactory())
    ->createServerRequest('POST', '/orders')
    ->withHeader('Content-Type', 'application/json')
    ->withBody(/* stream containing JSON */);
```

This tests the entire stack: routing → middleware → controller → service → repository → database → response serialisation. **No real HTTP server needed.**

---

## 7 — State Management Between Tests

Each integration test must start with a clean database. Three strategies:

### Strategy 1 — Fresh connection per test (recommended for SQLite)
Create a new `PDO('sqlite::memory:')` in `setUp()`. The in-memory database is destroyed and recreated each time.

```php
protected function setUp(): void
{
    $this->pdo = new \PDO('sqlite::memory:');  // fresh DB every test
    $this->runMigrations($this->pdo);
}
```

### Strategy 2 — Transactions (for shared databases)
Wrap each test in a transaction and roll back in `tearDown()`. Works for PostgreSQL/MySQL when all tests share one connection.

```php
protected function setUp(): void    { $this->pdo->beginTransaction(); }
protected function tearDown(): void { $this->pdo->rollBack(); }
```

### Strategy 3 — Seed helpers
Provide `seedProduct()`, `seedOrder()` helper methods in the test class to insert known data quickly:

```php
private function seedProduct(string $name, int $price, string $sku): int
{
    $stmt = $this->pdo->prepare('INSERT INTO products (name, price, sku) VALUES (?, ?, ?)');
    $stmt->execute([$name, $price, $sku]);
    return (int) $this->pdo->lastInsertId();
}
```

---

## 8 — What Integration Tests Assert

Unit tests assert on return values and spy recordings. Integration tests assert on:

```php
// HTTP status codes
$this->assertSame(200, $response->getStatusCode());
$this->assertSame(201, $response->getStatusCode());
$this->assertSame(404, $response->getStatusCode());
$this->assertSame(422, $response->getStatusCode());

// Response body structure
$body = json_decode((string) $response->getBody(), true);
$this->assertIsArray($body);
$this->assertArrayHasKey('id', $body);
$this->assertSame('Widget Pro', $body['name']);

// Database state after a write operation
$row = $this->pdo->query('SELECT * FROM orders WHERE id = 1')->fetch();
$this->assertNotFalse($row);
$this->assertSame('confirmed', $row['status']);

// Response headers
$this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
```

---

## 9 — The Seam Between Unit and Integration Tests

The same DI container that powers integration tests is what makes unit tests possible. The lesson flow across Module 5:

```
5.0 — Why DI makes testing possible (the concept)
5.1 — PHPUnit fundamentals (the tool)
5.2 — Unit tests with fakes (no container, no real infrastructure)
5.3 — TDD (design via tests)
5.4 — Integration tests (real container, real SQL, real routes)  ← you are here
```

Unit and integration tests are complementary, not competing. Unit tests tell you *what* broke; integration tests tell you *whether the wiring works*.

---

## 10 — Quick Reference

```php
// ── SQLite in-memory setup ────────────────────────────────────────────────
$pdo = new \PDO('sqlite::memory:');
$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
$pdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY AUTOINCREMENT, ...)');

// ── PHP-DI container in setUp() ──────────────────────────────────────────
$container = (new \DI\ContainerBuilder())
    ->addDefinitions([
        \PDO::class => $this->pdo,
        SomeInterface::class => \DI\autowire(SomeConcreteClass::class),
    ])
    ->build();

$service = $container->get(SomeService::class);

// ── Slim request simulation ──────────────────────────────────────────────
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

$app     = AppFactory::createFromContainer($container);
$request = (new ServerRequestFactory())->createServerRequest('GET', '/route');
$response = $app->handle($request);

$this->assertSame(200, $response->getStatusCode());
$body = json_decode((string) $response->getBody(), true);

// ── Seed helper pattern ──────────────────────────────────────────────────
private function seedProduct(string $name, int $price, string $sku): int
{
    $stmt = $this->pdo->prepare('INSERT INTO products (name, price, sku) VALUES (?, ?, ?)');
    $stmt->execute([$name, $price, $sku]);
    return (int) $this->pdo->lastInsertId();
}
```

---

## ✅ Lesson Checklist

- [ ] Read this README fully — Sections 2 (pyramid), 3 (when to write), 4 (SQLite), and 6 (Slim simulation) are key
- [ ] Run `examples/01-container-in-tests.php` — observe the container booting in setUp()
- [ ] Run `examples/02-sqlite-integration-test.php` — observe real SQL assertions
- [ ] Run `examples/03-slim-route-test.php` — observe HTTP route simulation
- [ ] Read `challenge/CHALLENGE.md` and complete `challenge/starter/ApiIntegrationTest.php`
- [ ] Check your work against `challenge/solution/ApiIntegrationTest.php`
- [ ] Complete `quiz/QUIZ.md` cold

---

*Next lesson: **5.5 — The Testing Pyramid in Practice** — balancing unit, integration, and end-to-end tests in a real project.*