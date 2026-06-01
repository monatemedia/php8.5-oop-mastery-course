# Quiz — Lesson 4.1: Service Containers
> Complete this quiz **without** looking at any example or solution files.
> Write your answers before checking the answer key at the bottom.
> Any question you get wrong is a reading target.

---

## Section A — Multiple Choice

**Q1.** What are the two core responsibilities of a service container?

- A) Automate unit tests and manage database connections.
- B) Store bindings (instructions for how to build a service) and resolve requests (build the service when asked).
- C) Replace the composition root and eliminate the need for interfaces.
- D) Manage HTTP routing and handle service lifecycle events.

---

**Q2.** What is the difference between `bind()` (factory) and `singleton()` in a container?

- A) `bind()` registers interfaces; `singleton()` registers concrete classes.
- B) `bind()` calls the factory on every `get()` call (fresh instance); `singleton()` calls the factory once and returns the cached instance for all subsequent `get()` calls.
- C) `bind()` is for stateless services; `singleton()` is for stateful services.
- D) They are identical — `singleton()` is just an alias for `bind()`.

---

**Q3.** You register a `ShoppingCart` as a singleton in your container. Two different users make requests. What happens?

- A) Each user gets their own fresh cart — singleton ensures thread safety.
- B) Both users share the same cart instance — user A's items appear in user B's cart.
- C) PHP automatically clones the singleton for each new request.
- D) An exception is thrown because singletons cannot hold user state.

---

**Q4.** What is the key difference between a **container** and a **Service Locator**?

- A) A container uses interfaces; a Service Locator uses concrete classes.
- B) A container uses Reflection; a Service Locator uses manual bindings.
- C) Both use the same technology. The difference is where `get()` is called: a container calls `get()` only at the entry point; a Service Locator has business classes calling `get()` internally.
- D) A container can only manage singletons; a Service Locator supports factory mode.

---

**Q5.** What does PSR-11 define?

- A) How service containers should implement auto-wiring.
- B) A standard `ContainerInterface` with `get()` and `has()` methods that any PSR-11 compliant container must implement.
- C) The format of the definitions file for PHP-DI.
- D) How constructors must be declared for a class to be auto-wired.

---

**Q6.** Which of the following is the correct use of a container?

- A) `class OrderService { public function process() { $db = $this->container->get(DatabaseInterface::class); } }`
- B) `// index.php: $controller = $container->get(OrderController::class); $controller->dispatch($request);`
- C) `class UserRepository { public function __construct(Container $container) { $this->db = $container->get(DatabaseInterface::class); } }`
- D) `Container::getInstance()->bind(LoggerInterface::class, fn() => new FileLogger());`

---

**Q7.** You have:
```php
$container->singleton(LoggerInterface::class, fn($c) => new FileLogger('/var/log/app.log'));
$log1 = $container->get(LoggerInterface::class);
$log2 = $container->get(LoggerInterface::class);
```
What is `$log1 === $log2`?

- A) `false` — each `get()` creates a new instance.
- B) `true` — the singleton factory is called once; the same instance is returned both times.
- C) It depends on whether `FileLogger` implements `LoggerInterface`.
- D) `false` — `===` checks object identity, which is always unique.

---

**Q8.** Why should infrastructure services (database, logger, mailer) almost always be registered as singletons?

- A) They are faster when shared.
- B) They are stateless or manage their own internal state safely, and creating multiple instances is wasteful (opening multiple DB connections, multiple file handles).
- C) PHP-DI only supports singleton mode for infrastructure.
- D) Framework conventions require it.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 9  | A class that calls `$container->get()` inside a method is using the Service Locator anti-pattern. | |
| 10 | `instance()` registers a pre-built object that is always returned by `get()`, never re-constructed. | |
| 11 | Using interface class names (`DatabaseInterface::class`) as container keys is the recommended convention because it aligns with constructor type hints used in auto-wiring. | |
| 12 | A Service Locator satisfies the Dependency Inversion Principle because it uses interface names as keys. | |
| 13 | After calling `$container->get(CheckoutController::class)` twice, both calls return `===` if the service is registered as a singleton. | |
| 14 | A container makes it impossible to test a class in isolation, because the class becomes dependent on the container. | |

---

## Section C — Short Answer

**Q15.** Explain in two sentences why registering a `ShoppingCart` as a singleton is a bug in a web application, and how the correct registration mode (factory) fixes it.

*Your answer:*

---

**Q16.** A colleague argues: *"A Service Locator is fine because we still use interfaces as keys — so DIP is satisfied."* Explain why this argument is wrong.

*Your answer:*

---

**Q17.** What is the `instance()` registration method used for, and when would you use it instead of `singleton()`?

*Your answer:*

---

## Section D — Code Reading

**Q18.** What will the following code output? Will `$a === $b`?

```php
$c = new Container();
$c->bind(LoggerInterface::class, fn($c) => new ConsoleLogger());
$c->singleton(DatabaseInterface::class, fn($c) => new MySQLDatabase());

$a = $c->get(LoggerInterface::class);
$b = $c->get(LoggerInterface::class);
$x = $c->get(DatabaseInterface::class);
$y = $c->get(DatabaseInterface::class);

var_dump($a === $b); // ?
var_dump($x === $y); // ?
```

*Your answer:*

---

**Q19.** Identify every problem with the following code. Label each one.

```php
class ReportController {
    private Container $container;

    public function __construct(Container $container) {
        $this->container = $container;
    }

    public function generate(int $reportId): string {
        $db      = $this->container->get(DatabaseInterface::class);
        $logger  = $this->container->get(LoggerInterface::class);
        $service = $this->container->get(ReportService::class);

        $logger->log('INFO', "Generating report #{$reportId}");
        return $service->generate($reportId, $db);
    }
}
```

*Your answer:*

---

**Q20.** What will the following code print? Trace through the resolution step by step.

```php
<?php
declare(strict_types=1);

interface Logger { public function log(string $m): void; }
interface Db     { public function query(): array; }

class FileLogger implements Logger {
    public function __construct() { echo "Logger created\n"; }
    public function log(string $m): void { echo "[LOG] {$m}\n"; }
}

class InMemDb implements Db {
    public function __construct() { echo "Db created\n"; }
    public function query(): array { return [['id' => 1]]; }
}

class UserRepo {
    public function __construct(private Db $db, private Logger $log) {
        echo "UserRepo created\n";
    }
    public function find(): void {
        $this->log->log("Querying");
        $this->db->query();
    }
}

$c = new SimpleContainer();  // assume SimpleContainer from the challenge
$c->singleton(Logger::class, fn($c) => new FileLogger());
$c->singleton(Db::class,     fn($c) => new InMemDb());
$c->singleton(UserRepo::class, fn($c) => new UserRepo(
    $c->get(Db::class),
    $c->get(Logger::class)
));

$r1 = $c->get(UserRepo::class);
$r2 = $c->get(UserRepo::class);
$r1->find();
echo $r1 === $r2 ? "same\n" : "different\n";
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
| 1 | **B** | A container stores bindings (how to build) and resolves requests (builds when asked). |
| 2 | **B** | `bind()` = factory (fresh every time). `singleton()` = built once, cached. |
| 3 | **B** | A singleton is one shared instance. User A's items are in the same cart object that user B receives — a serious state-bleed bug. |
| 4 | **C** | Same technology; different calling context. Container: `get()` only at entry point. Service Locator: business classes call `get()` directly. |
| 5 | **B** | PSR-11 defines `ContainerInterface` with `get()` and `has()`. PHP-DI, Symfony, and Laravel all implement it. |
| 6 | **B** | Only option B calls `get()` at the entry point (index.php). Options A and C are Service Locators inside business classes. D uses a static singleton anti-pattern. |
| 7 | **B** | Singleton: factory called once, cached. Both calls return the same object. `===` checks identity — same object → `true`. |
| 8 | **B** | Infrastructure is stateless or safely manages its own state. Multiple instances waste resources (DB connections, file handles). |

## Section B
| # | Answer | Explanation |
|---|--------|-------------|
| 9  | **T** | Calling `$container->get()` inside a business method is exactly the Service Locator pattern. |
| 10 | **T** | `instance()` stores a pre-built object. `get()` always returns that exact object — the factory is never called. |
| 11 | **T** | Using `DatabaseInterface::class` as the key means the auto-wiring container (Lesson 4.3) can match it to `private DatabaseInterface $db` constructor params automatically. |
| 12 | **F** | Using interface keys doesn't prevent the class from coupling to the container itself. DIP requires depending on abstractions via constructor — not fetching them from a registry. |
| 13 | **T** | Singleton registration: first call builds and caches, second call returns the cached instance. Both calls return `===`. |
| 14 | **F** | The opposite is true. With a container, business classes have no dependency on the container — they receive dependencies via constructor injection and can be tested by passing fakes directly. |

## Section C

**Q15 — Model answer:**
A singleton `ShoppingCart` means every `get()` call returns the same cart object. When user A adds items to their cart, those items persist in the singleton and appear in user B's cart on their next request — a critical data-leakage bug. Registering as factory (`bind()`) ensures each `get()` call creates a new, empty `ShoppingCart` so each user gets an isolated cart with no shared state.

**Q16 — Model answer:**
Using interface keys means the *lookup key* is an abstraction — but the *calling class* is still directly coupled to the container. `OrderService` now depends on `Container` itself: change the container's class name, change its `get()` method signature, or swap to a different container library, and every class using the locator must be updated. Furthermore, the dependencies of `OrderService` are completely invisible from its constructor signature — you cannot determine what it needs without reading every line of every method. DIP requires depending on abstractions in the *constructor*, not fetching them from a global registry.

**Q17 — Model answer:**
`instance()` stores an already-constructed object and returns it on every `get()` call. Use it when the object requires constructor arguments that are not available as container bindings — typically environment variables or primitives. For example: `$container->instance(DatabaseInterface::class, new MySQLDatabase(getenv('DB_DSN')))` — the DSN string is not a type-hinted class, so the container cannot auto-wire it; you construct the object manually and register the result.

## Section D

**Q18 — Answer:**
```
bool(false)  // $a === $b — LoggerInterface bound with bind() — factory mode — two different instances
bool(true)   // $x === $y — DatabaseInterface bound with singleton() — same instance
```
`bind()` calls the factory on every `get()` → two different `ConsoleLogger` instances → `$a !== $b`.
`singleton()` calls the factory once, caches it → same `MySQLDatabase` instance both times → `$x === $y`.

**Q19 — Answer:**
Four problems:
1. **Service Locator anti-pattern**: `generate()` calls `$this->container->get()` three times inside the method — fetching dependencies at runtime from the container.
2. **Hidden dependencies**: The constructor shows only `Container`. The real dependencies (`DatabaseInterface`, `LoggerInterface`, `ReportService`) are invisible without reading the method body.
3. **Coupled to Container class**: `ReportController` depends on `Container` directly. Change the container library → must update `ReportController`.
4. **Untestable in isolation**: To test `generate()`, you must pre-populate a `Container` with the correct bindings instead of simply passing fakes to a constructor.

Fix: `public function __construct(private ReportService $service, private LoggerInterface $logger) {}` — remove the container, add proper constructor injection.

**Q20 — Answer:**
```
Db created
Logger created
UserRepo created
[LOG] Querying
same
```
Resolution trace:
1. `$c->get(UserRepo::class)` — not cached. Call the factory.
2. Factory calls `$c->get(Db::class)` — not cached. Creates `InMemDb` → prints `"Db created"`. Cached.
3. Factory calls `$c->get(Logger::class)` — not cached. Creates `FileLogger` → prints `"Logger created"`. Cached.
4. Creates `UserRepo(InMemDb, FileLogger)` → prints `"UserRepo created"`. Cached.
5. `$c->get(UserRepo::class)` again — now cached. Returns same instance. **No new output.**
6. `$r1->find()` → calls `log("Querying")` → prints `"[LOG] Querying"`, then calls `query()` (no output).
7. `$r1 === $r2` → `true` (singleton) → prints `"same"`.

---

## Score Guide

| Score | Verdict |
|-------|---------|
| 18–20 | Ready for Lesson 4.2 — strong container foundation. |
| 14–17 | Re-read the README sections for any missed questions, then move on. |
| Below 14 | Re-run the examples, redo the challenge, then retake the quiz before continuing. |