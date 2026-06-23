# Quiz — Lesson 5.4: Integration Testing with a Real Container
> Complete this quiz **without** looking at any example or solution files.
> Write your answers before checking the answer key at the bottom.

---

## Section A — Multiple Choice

**Q1.** What is the primary difference between a unit test and an integration test?

- A) Integration tests are slower; unit tests are faster.
- B) Unit tests replace all dependencies with doubles and test one class in isolation. Integration tests use real implementations to verify that multiple components work correctly when wired together.
- C) Integration tests use mocking frameworks; unit tests use anonymous classes.
- D) Unit tests cover the whole application; integration tests cover individual classes.

---

**Q2.** Why is `PDO('sqlite::memory:')` the recommended database for integration tests?

- A) SQLite is faster than all other databases for production workloads.
- B) It requires a server process and credentials — just like production.
- C) It is in-process, destroyed when the connection closes, requires no setup, and runs in microseconds.
- D) It is the only database PHP supports natively.

---

**Q3.** In the test pyramid, what sits between unit tests (the wide base) and end-to-end tests (the narrow top)?

- A) Acceptance tests
- B) Smoke tests
- C) Integration tests
- D) Performance tests

---

**Q4.** You boot a PHP-DI container in `setUp()` and override the `\PDO::class` binding with an in-memory SQLite connection. What does this allow you to test?

- A) That the container source code itself is bug-free.
- B) That the real repository classes execute correct SQL against a real (in-memory) database, while the rest of the container wiring uses real implementations.
- C) That anonymous class doubles implement the repository interfaces correctly.
- D) That unit tests and integration tests produce identical results.

---

**Q5.** You write:

```php
$request  = (new ServerRequestFactory())->createServerRequest('GET', '/products');
$response = $this->app->handle($request);
$this->assertSame(200, $response->getStatusCode());
```

What does `$this->app->handle($request)` do?

- A) Makes a real HTTP request to a local server.
- B) Processes the request entirely in-process through Slim's routing, middleware, and controller stack — no real HTTP server needed.
- C) Calls the controller's method directly, bypassing routing.
- D) Returns a mock response object pre-configured by PHPUnit.

---

**Q6.** A test seeds two products with raw PDO, then calls `GET /products` and asserts `assertCount(2, $body)`. The next test asserts the database is empty. Which pattern ensures the second test passes?

- A) Calling `$this->pdo->exec('DELETE FROM products')` at the start of every test.
- B) Creating a fresh `new PDO('sqlite::memory:')` in `setUp()` — each test gets its own connection and therefore its own empty database.
- C) Using `setUpBeforeClass()` to create the PDO once and sharing it between tests.
- D) Wrapping all tests in a database transaction.

---

**Q7.** Which of the following should be tested at the INTEGRATION level rather than the unit level?

- A) Whether `OrderService::placeOrder()` returns `['success' => false]` when the payment gateway returns `false`.
- B) Whether the container binds `ProductRepositoryInterface` to `SqliteProductRepository`.
- C) Whether the spy mailer records the correct email recipient.
- D) Whether `Money::add()` throws for mismatched currencies.

---

**Q8.** Your integration test creates a product via `POST /products` and checks the HTTP response. You also want to verify the row was persisted correctly. What should you use?

- A) Call `GET /products/{id}` and compare the response body.
- B) Assert directly on `$this->pdo` with a raw SQL query: `SELECT * FROM products WHERE sku = ?`
- C) Add a `getLastInsertedRow()` method to the repository for test use only.
- D) Trust that if the HTTP response is 201, the data must have been saved correctly.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 9  | A seed helper should use the repository class under test to insert data, so both use the same code path. | |
| 10 | Integration tests can replace the need for unit tests once you have enough of them. | |
| 11 | `$this->app->addErrorMiddleware(false, false, false)` in test setUp() ensures exceptions surface as test errors rather than being swallowed into a 500 response. | |
| 12 | A SQLite in-memory database supports JOIN queries, transactions, and AUTOINCREMENT. | |
| 13 | A container wiring test (asserting `$container->get(SomeInterface::class)` returns an instance of `SomeConcrete::class`) is a valid integration test. | |
| 14 | An integration test that tests business logic inside a single class (e.g. that `calculateTotal()` applies a 10% discount correctly) is testing at the right level. | |

---

## Section C — Short Answer

**Q15.** Explain the statement "integration tests tell you whether the wiring works; unit tests tell you what broke." Give a concrete example using `ProductService` and `SqliteProductRepository`.

*Your answer:*

---

**Q16.** A colleague argues: "We do not need seed helpers — we can just call `$this->service->create(...)` to set up data in tests." Give two reasons why using raw PDO for seeding is preferable.

*Your answer:*

---

**Q17.** Describe what `addErrorMiddleware(false, false, false)` does in a Slim integration test and why it matters. What happens if you omit it?

*Your answer:*

---

## Section D — Code Reading

**Q18.** What does the following test verify? Does it pass if the container is misconfigured (e.g. the PDO binding is missing)? What error would you see?

```php
public function testContainerResolvesProductRepositoryToSqliteClass(): void
{
    $repo = $this->container->get(ProductRepositoryInterface::class);
    $this->assertInstanceOf(SqliteProductRepository::class, $repo);
}
```

*Your answer:*

---

**Q19.** Trace the full path through the system for this test. List every class and method involved from the test assertion back to the database.

```php
public function testGetProductsReturnsAllSeededProducts(): void
{
    $this->seedProduct('Widget Pro', 29999, 'WDG-001');

    $response = $this->app->handle(
        (new ServerRequestFactory())->createServerRequest('GET', '/products')
    );

    $this->assertCount(1, $this->decodeBody($response));
    $this->assertSame('Widget Pro', $this->decodeBody($response)[0]['name']);
}
```

*Your answer:*

---

**Q20.** The following test has a subtle problem. Identify it and explain how to fix it.

```php
class ProductIntegrationTest extends TestCase
{
    private static \PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        self::$pdo = new \PDO('sqlite::memory:');
        self::$pdo->exec('CREATE TABLE products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL, price INTEGER NOT NULL, sku TEXT NOT NULL UNIQUE
        )');
    }

    public function testCreateProductPersistsRow(): void
    {
        $repo = new SqliteProductRepository(self::$pdo);
        $repo->save('Widget Pro', 29999, 'WDG-001');
        $this->assertCount(1, $repo->findAll());
    }

    public function testFindAllReturnsEmptyWhenNothingInserted(): void
    {
        $repo = new SqliteProductRepository(self::$pdo);
        $this->assertSame([], $repo->findAll());
    }
}
```

*Your answer:*

---

---

# ✅ Answer Key
*(Scroll only after completing all questions)*

&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;

---

## Section A

| Q | Answer | Explanation |
|---|--------|-------------|
| 1 | **B** | Unit tests isolate a single class with doubles. Integration tests use real implementations to verify the wiring and interactions between components. |
| 2 | **C** | `sqlite::memory:` is fully in-process (no server), ephemeral (destroyed when connection closes), requires zero configuration, and is extremely fast. |
| 3 | **C** | The test pyramid: wide base = unit tests, middle = integration tests, narrow top = end-to-end tests. |
| 4 | **B** | The container is booted with real classes — only the PDO binding is overridden with an in-memory test database. This tests real SQL execution and real container autowiring simultaneously. |
| 5 | **B** | Slim's `App::handle()` processes the request entirely in-process through the routing engine, middleware stack, controller, and response object — no real HTTP server is involved. |
| 6 | **B** | A fresh `new PDO('sqlite::memory:')` in `setUp()` creates a brand-new in-memory database before every test. When the previous connection goes out of scope, the entire database is destroyed. |
| 7 | **B** | Container wiring tests (`ProductRepositoryInterface` → `SqliteProductRepository`) can only be verified by actually building the container. Unit tests cannot catch misconfigured bindings. A–D all belong at the unit level. |
| 8 | **B** | Direct SQL assertions on `$this->pdo` verify the database state at the most fundamental level — they catch bugs where the service returns the right value but forgets to persist it. |

## Section B

| # | Answer | Explanation |
|---|--------|-------------|
| 9  | **F** | Seed helpers should use raw PDO, NOT the repository under test. If the repository has a bug in its save() method, using it to seed would hide that bug — the test would pass even though save() is broken. Raw PDO seeds are independent of the implementation being tested. |
| 10 | **F** | Integration tests cannot replace unit tests. They are slower, less precise, and do not isolate the cause of failures well. Unit tests cover individual class logic; integration tests cover wiring. Both are needed. |
| 11 | **T** | `addErrorMiddleware(false, false, false)` tells Slim NOT to catch exceptions. In tests, you want exceptions to propagate as PHPUnit errors so you can see the real stack trace, not a swallowed 500 response. |
| 12 | **T** | SQLite supports standard SQL including JOINs, transactions, subqueries, AUTOINCREMENT, UNIQUE constraints, and FOREIGN KEYS (when enabled with `PRAGMA foreign_keys = ON`). |
| 13 | **T** | Resolving an interface from the container and asserting the concrete type is a valid integration test — it verifies that the container configuration is correct, which cannot be verified by a unit test. |
| 14 | **F** | Testing business logic inside a single class belongs at the unit level. `calculateTotal()` with a 10% discount should be tested with a unit test using a fake price source. An integration test adds infrastructure cost for no benefit. |

## Section C

**Q15 — Model answer:**
A unit test for `ProductService::getAll()` uses a fake repository and verifies that the service calls `findAll()` and returns the result — it tells you *what* broke if the logic is wrong. An integration test boots the real container, creates the real `SqliteProductRepository` with a real PDO, seeds two rows, calls `$service->getAll()`, and asserts two rows are returned. If it fails, you know *the wiring works* (or does not) — for example, the SQL query might have a WHERE clause bug that the fake repository never caught. The unit test catches logic errors; the integration test catches SQL errors and misconfiguration.

**Q16 — Model answer:**
1. **Independence from the implementation under test.** If `ProductService::create()` (or the repository's `save()`) has a bug, a test that uses it to seed data will set up broken state — and may mask the bug rather than reveal it. Raw PDO seeds are independent of the code being tested.
2. **Speed and simplicity.** Raw PDO INSERT statements are faster and more direct than going through the service layer. They do not trigger validation, logging, mailer calls, or any other service logic — they just put rows in the database exactly as specified, which is all setup code should do.

**Q17 — Model answer:**
`addErrorMiddleware(false, false, false)` disables Slim's error-handling middleware, which would otherwise catch all exceptions and convert them into a `500 Internal Server Error` response. In tests, this is harmful because PHPUnit would see a 500 response (which might make the test pass if you forget to assert on the status code) rather than the real PHP exception with its full stack trace. Without disabling the middleware, a bug that throws `\TypeError` deep in the stack appears as a 500 response; with the middleware disabled, PHPUnit reports the exception directly as an error, pointing you immediately to the source.

## Section D

**Q18 — Answer:**
The test verifies that the container is configured to resolve `ProductRepositoryInterface` to `SqliteProductRepository`. If the container is misconfigured — for example, the PDO binding is missing and PHP-DI cannot autowire `SqliteProductRepository` because it needs a `\PDO` argument — `$this->container->get(ProductRepositoryInterface::class)` would throw a `\DI\DependencyException` or `\DI\NotFoundException`. PHPUnit would report this as an **Error** (unexpected exception), not a Failure. The stack trace would show exactly which binding is missing. This is the intended behaviour: container misconfiguration should surface immediately as a test error.

**Q19 — Answer:**
The full path:

1. `seedProduct()` → raw `PDO::prepare/execute` → `INSERT INTO products` → SQLite in-memory DB
2. `(new ServerRequestFactory())->createServerRequest('GET', '/products')` → creates a `ServerRequest` object
3. `$this->app->handle($request)` → Slim router matches `/products` → dispatches to `ProductController::list()`
4. `ProductController::list()` → calls `$this->service->getAll()` → `ProductService::getAll()`
5. `ProductService::getAll()` → calls `$this->repository->findAll()` → `SqliteProductRepository::findAll()`
6. `SqliteProductRepository::findAll()` → executes `SELECT * FROM products ORDER BY id` → SQLite returns the seeded row
7. Result bubbles back: repository → service → controller → JSON-encodes the array → writes to response body
8. `$this->app->handle()` returns the response
9. `decodeBody($response)` → `json_decode((string) $response->getBody(), true)` → `[['id' => 1, 'name' => 'Widget Pro', ...]]`
10. `assertCount(1, ...)` → passes (one row)
11. `assertSame('Widget Pro', ...[0]['name'])` → passes

**Q20 — Answer:**
The problem is **shared mutable state between tests via the static `$pdo` property**. `setUpBeforeClass()` creates the database ONCE for the entire class. `testCreateProductPersistsRow()` inserts a row. If that test runs BEFORE `testFindAllReturnsEmptyWhenNothingInserted()`, the second test will see one row and fail (`assertSame([], ...)` fails because the row from the first test is still there).

This is an order-dependent test failure caused by shared database state — the exact problem that a fresh-connection-per-test strategy prevents.

The fix: replace `static \PDO $pdo` and `setUpBeforeClass()` with an instance property `private \PDO $pdo` and `setUp()`:

```php
protected function setUp(): void
{
    $this->pdo = new \PDO('sqlite::memory:');
    $this->pdo->exec('CREATE TABLE products (...)');
}
```

Each test now gets its own empty database. The tests can run in any order and produce consistent results.

---

## Score Guide

| Score | Verdict |
|-------|---------|
| 18–20 | Strong grasp of integration testing. Ready for Lesson 5.5. |
| 14–17 | Re-read README Sections 3, 4, and 7. Redo one example before moving on. |
| Below 14 | Complete the challenge in full before retaking. Integration testing is learned by running code, not reading. |