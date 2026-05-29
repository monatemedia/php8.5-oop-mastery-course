# Quiz — Lesson 3.4: Inversion of Control (IoC)
> Complete this quiz **without** looking at any example or solution files.
> Write your answers before checking the answer key at the bottom.
> Any question you get wrong is a reading target.

---

## Section A — Multiple Choice

**Q1.** What does the Hollywood Principle mean in the context of OOP and IoC?

- A) Business logic classes should call the framework to get the dependencies they need.
- B) Classes should declare what they need; something at the entry point provides it — classes do not reach out to create or find their own dependencies.
- C) All dependencies should be retrieved from a global service registry.
- D) The framework should call business logic classes only when an HTTP request arrives.

---

**Q2.** What does the Dependency Inversion Principle (DIP) state?

- A) High-level modules should depend directly on low-level modules for efficiency.
- B) All classes should use constructor injection.
- C) High-level modules and low-level modules should both depend on abstractions; details should depend on abstractions, not the other way around.
- D) Dependencies should be inverted by passing them in reverse order.

---

**Q3.** What is the key difference between **DIP** and **DI**?

- A) DIP is for interfaces; DI is for abstract classes.
- B) DIP is a design principle (depend on abstractions); DI is a technique (receive dependencies from outside). Both are needed; neither alone is sufficient.
- C) DI is the broader concept; DIP is just the PHP-specific implementation.
- D) They are synonyms — DIP and DI describe the same thing.

---

**Q4.** You have `class OrderService` with constructor parameter `private StripeGateway $gateway`. DI is present because the gateway is injected. Is DIP satisfied?

- A) Yes — DI implies DIP.
- B) No — the type hint is a concrete class (`StripeGateway`), not an interface. DIP requires the parameter to be typed as `PaymentGatewayInterface`.
- C) Yes — DIP only requires that the dependency is created outside the class.
- D) No — DIP requires using setter injection, not constructor injection.

---

**Q5.** What is the "composition root"?

- A) The abstract base class from which all services inherit.
- B) The single entry point (e.g. `index.php` or a `buildApp()` function) where all `new` calls on services live and the dependency graph is wired.
- C) A PHP-DI configuration file.
- D) A static factory class that creates all application objects.

---

**Q6.** A class calls `$this->container->get(DatabaseInterface::class)` inside one of its methods. Which anti-pattern is this?

- A) Tight coupling — the class creates its own dependency.
- B) Service Locator — the class reaches into a global registry to fetch its dependency.
- C) Circular dependency — the class depends on the container which depends on the class.
- D) Null Object — the container returns null when the binding is not found.

---

**Q7.** Why does manual IoC wiring become problematic at 50+ services?

- A) PHP cannot handle more than 50 constructor parameters in a single file.
- B) The wiring function grows into hundreds of lines, the ordering of `new` calls must be managed manually, and every parameter change in any class requires an update to the wiring file.
- C) PHP-DI stops working correctly when there are more than 50 services.
- D) Interfaces cannot be used as type hints when there are more than 50 of them.

---

**Q8.** What does a Reflection-based container do that a flat wiring function does not?

- A) It provides better performance than writing `new` manually.
- B) It reads constructor type hints at runtime and resolves the dependency graph automatically — you bind interfaces to concrete classes once, and the container wires everything.
- C) It prevents circular dependencies at compile time.
- D) It allows classes to have more than five constructor parameters.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 9  | The Dependency Inversion Principle and Dependency Injection mean the same thing. | |
| 10 | In a correctly IoC-wired system, business logic classes (`OrderService`, `UserRepository`) should have zero `new` calls on other services. | |
| 11 | A Service Locator satisfies the Dependency Inversion Principle because it uses interfaces as lookup keys. | |
| 12 | The composition root is the only place in the application where `new` should be called on service-level classes. | |
| 13 | PHP-DI reads constructor type hints using PHP's Reflection API, which is the same technique used by the `MiniContainer` in Example 04. | |
| 14 | Injecting a concrete class (`new StripeGateway`) satisfies DIP as long as the injection happens via the constructor. | |

---

## Section C — Short Answer

**Q15.** Explain in two sentences how DIP, DI, and IoC form a stack. What does each one contribute that the previous one alone does not provide?

*Your answer:*

---

**Q16.** A system has 30 services. The flat wiring function (composition root) is 80 lines long. A colleague suggests switching every service to a Service Locator so the wiring function can be deleted. Explain why this is the wrong solution and what the right solution is.

*Your answer:*

---

**Q17.** Describe what "inverting the dependency arrow" means using a concrete before/after example with `OrderService` and `StripeGateway`.

*Your answer:*

---

## Section D — Code Reading

**Q18.** Identify every IoC/DIP violation in the following class and state what should be changed.

```php
class ReportController {
    private ReportService $service;
    private FileLogger    $logger;

    public function __construct() {
        $this->service = new ReportService();
        $this->logger  = new FileLogger('/var/log/reports.log');
    }

    public function generate(int $reportId): string {
        $this->logger->log('INFO', "Generating report #{$reportId}");
        return $this->service->generate($reportId);
    }
}
```

*Your answer:*

---

**Q19.** What will the following `MiniContainer` call resolve? Trace the Reflection chain step by step.

```php
interface LoggerInterface { public function log(string $m): void; }
interface DbInterface     { public function query(string $sql): array; }

class ConsoleLogger implements LoggerInterface {
    public function log(string $m): void { echo $m; }
}

class InMemoryDb implements DbInterface {
    public function query(string $sql): array { return []; }
}

class UserRepo {
    public function __construct(
        private DbInterface     $db,
        private LoggerInterface $logger
    ) {}
}

class UserService {
    public function __construct(
        private UserRepo        $repo,
        private LoggerInterface $logger
    ) {}
}

$c = new MiniContainer();
$c->bind(LoggerInterface::class, ConsoleLogger::class);
$c->bind(DbInterface::class,     InMemoryDb::class);

$service = $c->make(UserService::class);
```

Trace: What does the container build, in what order, and what singleton cache entries exist after `make(UserService::class)`?

*Your answer:*

---

**Q20.** The following code claims to use IoC. Identify what is WRONG with it and what pattern it is actually using.

```php
class Container {
    private static array $bindings = [];
    private static array $instances = [];

    public static function bind(string $id, callable $factory): void {
        self::$bindings[$id] = $factory;
    }

    public static function get(string $id): object {
        if (!isset(self::$instances[$id])) {
            self::$instances[$id] = (self::$bindings[$id])();
        }
        return self::$instances[$id];
    }
}

Container::bind(DatabaseInterface::class, fn() => new MySQLDatabase(getenv('DB_DSN')));
Container::bind(LoggerInterface::class,   fn() => new FileLogger('/var/log/app.log'));

class OrderService {
    public function __construct() {
        $this->db     = Container::get(DatabaseInterface::class);
        $this->logger = Container::get(LoggerInterface::class);
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
| 1 | **B** | The Hollywood Principle: classes declare what they need; the entry point provides it. They do not reach out. |
| 2 | **C** | DIP: high-level modules depend on abstractions, low-level modules implement abstractions, abstractions do not depend on details. |
| 3 | **B** | DIP is the design principle (what to type-hint); DI is the technique (how to receive it). You need both: DIP tells you to use interfaces, DI tells you to inject them via constructor. |
| 4 | **B** | DI is present (injected from outside) but DIP is not (type hint is concrete). The type should be `PaymentGatewayInterface`, not `StripeGateway`. |
| 5 | **B** | The composition root is the single entry point where all `new` calls on services live. |
| 6 | **B** | Service Locator anti-pattern: the class reaches into a global container to fetch its dependencies, hiding them from the constructor signature. |
| 7 | **B** | At scale, the wiring file becomes a maintenance burden: ordering, duplication, and fragility when constructor signatures change. |
| 8 | **B** | A Reflection-based container reads constructor type hints automatically and resolves the graph from a small set of interface→concrete bindings. |

## Section B
| # | Answer | Explanation |
|---|--------|-------------|
| 9  | **F** | DIP is a design principle; DI is a technique. They are related but distinct. |
| 10 | **T** | In a correctly IoC-wired system all `new` calls on services live in the composition root. Business logic classes receive, they do not create. |
| 11 | **F** | A Service Locator uses interfaces as keys but the class still reaches into a global registry — it hides dependencies and couples the class to the locator itself. |
| 12 | **T** | The composition root is the one place for `new` on services. Anywhere else is a coupling violation. |
| 13 | **T** | PHP-DI uses `ReflectionClass` and `ReflectionParameter` to read constructor type hints — exactly what the `MiniContainer` in Example 04 does. |
| 14 | **F** | DIP specifically requires depending on abstractions (interfaces), not concretions. Injecting `new StripeGateway` satisfies DI but not DIP. |

## Section C

**Q15 — Model answer:**
DIP defines *what* type to depend on: interfaces, not concrete classes. DI defines *how* to receive that dependency: via constructor or setter, not by creating or fetching it. IoC defines *who* is responsible: the entry point assembles the graph and provides everything — services never reach out. Without DIP, DI still couples to concretions. Without DI, DIP still requires the class to fetch its dependencies somehow. Without IoC, the dependency graph has no single point of control.

**Q16 — Model answer:**
The Service Locator is the wrong solution because it does not eliminate coupling — it just hides it. Each class now has a hidden dependency on the container itself, which cannot be seen from the constructor signature and cannot be replaced in tests without setting up the global container. The right solution is to use a Reflection-based container (PHP-DI) that reads the existing interface type hints and wires the graph automatically. The 80-line wiring function shrinks to a ~10-line definitions file binding interfaces to concrete classes, and the container handles the rest.

**Q17 — Model answer:**
Before DIP: `OrderService` depends directly on `StripeGateway`. The dependency arrow points from high-level to low-level: `OrderService ──► StripeGateway`. After DIP: `OrderService` depends on `PaymentGatewayInterface`, and `StripeGateway` implements `PaymentGatewayInterface`. The arrows become: `OrderService ──► PaymentGatewayInterface ◄── StripeGateway`. Both the high-level and low-level modules now point at the abstraction in the middle — the original arrow from high-level to low-level has been inverted.

## Section D

**Q18 — Answer:**
Four violations:
1. `private ReportService $service` — **concrete-property**: should be `ReportServiceInterface`.
2. `private FileLogger $logger` — **concrete-property**: should be `LoggerInterface`.
3. `new ReportService()` — **new-in-constructor**: class creates its own service dep.
4. `new FileLogger('/var/log/reports.log')` — **new-in-constructor** + **hardcoded-config**.

Fix: `public function __construct(private ReportServiceInterface $service, private LoggerInterface $logger) {}` — remove all `new` calls, accept via constructor with interface types.

**Q19 — Answer:**
The container resolves in this order:
1. `make(UserService::class)` — not in cache. Reflect constructor: needs `UserRepo` and `LoggerInterface`.
2. `make(UserRepo::class)` — not in cache. Reflect constructor: needs `DbInterface` and `LoggerInterface`.
3. `make(DbInterface::class)` — bound to `InMemoryDb`. Reflect: no params. Create `InMemoryDb`. Cache: `DbInterface → InMemoryDb`, `InMemoryDb → InMemoryDb`.
4. `make(LoggerInterface::class)` — bound to `ConsoleLogger`. Reflect: no params. Create `ConsoleLogger`. Cache: `LoggerInterface → ConsoleLogger`, `ConsoleLogger → ConsoleLogger`.
5. Create `UserRepo(InMemoryDb, ConsoleLogger)`. Cache: `UserRepo → UserRepo`.
6. `make(LoggerInterface::class)` — already in cache. Return `ConsoleLogger` (singleton).
7. Create `UserService(UserRepo, ConsoleLogger)`. Cache: `UserService → UserService`.

Final cache keys: `DbInterface`, `InMemoryDb`, `LoggerInterface`, `ConsoleLogger`, `UserRepo`, `UserService` — six entries. `ConsoleLogger` is shared (singleton) between `UserRepo` and `UserService`.

**Q20 — Answer:**
The code claims to use IoC but is actually the **Service Locator anti-pattern**. The problems:
1. `OrderService::__construct()` calls `Container::get(...)` — the class reaches into a global static registry to fetch its dependencies. The constructor signature reveals nothing about what the class needs.
2. `Container` is a static class — it is global state, not injected. `OrderService` is now coupled to `Container` itself.
3. To test `OrderService`, you must pre-populate the global `Container::$bindings` before instantiating it — invisible setup requirement.
4. DIP appears satisfied (interface keys) but the mechanism is a locator, not injection.

Fix: Remove the `Container::get()` calls from the constructor. Add `DatabaseInterface $db` and `LoggerInterface $logger` as constructor parameters (type-hinted as interfaces). Wire at the composition root where the actual container or `new` calls live.

---

## Score Guide

| Score | Verdict |
|-------|---------|
| 18–20 | Module 3 complete — ready for Module 4. |
| 14–17 | Re-read the README sections for any missed questions, then move on. |
| Below 14 | Re-run the examples, redo the challenge, then retake the quiz before continuing. |