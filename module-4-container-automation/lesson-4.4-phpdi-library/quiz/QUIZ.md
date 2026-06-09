# Quiz — Lesson 4.4: PHP-DI Library
> Complete this quiz **without** looking at any example or solution files.
> Write your answers before checking the answer key at the bottom.
> Any question you get wrong is a reading target.

---

## Section A — Multiple Choice

**Q1.** What is the role of `ContainerBuilder` in PHP-DI?

- A) It validates that all interface bindings are correct before the container starts.
- B) It assembles the container from configuration (definitions files, compilation settings) and produces a ready-to-use PSR-11 container.
- C) It generates PHP class stubs for every registered interface.
- D) It replaces Composer's autoloader for dependency loading.

---

**Q2.** Which PHP-DI function maps an interface to a concrete class whose constructor will be auto-wired?

- A) `factory(ConcreteClass::class)`
- B) `create(ConcreteClass::class)`
- C) `autowire(ConcreteClass::class)`
- D) `bind(ConcreteClass::class)`

---

**Q3.** A class `MySQLDatabase` has constructor `__construct(string $dsn, int $port = 3306)`. Which PHP-DI definition is correct?

- A) `autowire(MySQLDatabase::class)` — PHP-DI resolves the string automatically.
- B) `factory(function() { return new MySQLDatabase(getenv('DB_DSN')); })` — factory reads env and constructs with the primitive.
- C) `create(MySQLDatabase::class)` — create() resolves string params from the container.
- D) No definition needed — PHP-DI ignores primitive params.

---

**Q4.** What PSR does PHP-DI implement, and why does this matter?

- A) PSR-4 (autoloading) — required for Composer compatibility.
- B) PSR-12 (coding style) — required for framework integration.
- C) PSR-11 (container interface) — any framework that accepts a `ContainerInterface` works with PHP-DI without an adapter.
- D) PSR-7 (HTTP messages) — required for routing.

---

**Q5.** According to Course Philosophy Rule 1, where should all `getenv()` calls live in a PHP-DI application?

- A) Inside the service classes that need the configuration values.
- B) In a dedicated `Config` class that is injected everywhere.
- C) In the definitions file (`config/services.php`) — the composition root.
- D) In `bootstrap.php`, split evenly with service classes.

---

**Q6.** A factory definition receives the container as its first argument. What is this useful for?

- A) It allows the factory to register new bindings at runtime.
- B) It allows the factory to resolve other services from the container while constructing the class — useful for decorators and conditional wiring.
- C) It provides access to the container's singleton cache for manual invalidation.
- D) It is required for all factory definitions — the container always passes itself.

---

**Q7.** You register `LoggerInterface::class => autowire(FileLogger::class)`. `FileLogger` has constructor `__construct(private string $path = '/tmp/app.log')`. What does PHP-DI do?

- A) Throws an exception — `string` cannot be auto-wired.
- B) Uses the default value `'/tmp/app.log'` for `$path`, since the parameter is optional.
- C) Injects an empty string for `$path`.
- D) Ignores the parameter entirely.

---

**Q8.** What is the difference between `autowire()` and `create()` in PHP-DI?

- A) `autowire()` is for interfaces; `create()` is for concrete classes.
- B) `autowire()` uses Reflection to resolve all constructor params automatically; `create()` does the same but also allows explicit `->constructor()` overrides for specific params.
- C) `create()` creates a new instance every resolution; `autowire()` creates a singleton.
- D) They are identical — one is just an alias for the other.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 9  | `$builder->addDefinitions()` can accept either an array of definitions or a path to a PHP file that returns an array. | |
| 10 | PHP-DI auto-wires concrete classes with no definitions — as long as their constructors have only interface-typed or optional params. | |
| 11 | A class that calls `$container->get()` inside one of its methods is using PHP-DI correctly as a DI container. | |
| 12 | In PHP-DI, all auto-wired classes are singletons by default (one instance per container lifetime). | |
| 13 | `factory()` and `autowire()` can be mixed in the same definitions array. | |
| 14 | PHP-DI's compiled container (`enableCompilation()`) eliminates Reflection calls at runtime by generating a plain PHP class with direct `new` calls. | |

---

## Section C — Short Answer

**Q15.** Explain why factory definitions enforce Course Philosophy Rule 1 (Config at the entry point). What would go wrong if a service class called `getenv('DB_DSN')` directly?

*Your answer:*

---

**Q16.** A colleague writes:
```php
class OrderService {
    public function __construct(private \DI\Container $container) {}
    public function process(): void {
        $gateway = $this->container->get(PaymentGatewayInterface::class);
        $gateway->charge(100.00, 'tok');
    }
}
```
Name the anti-pattern and explain two concrete problems it causes.

*Your answer:*

---

**Q17.** Describe when you would use `factory()` instead of `autowire()`. Give two distinct scenarios.

*Your answer:*

---

## Section D — Code Reading

**Q18.** What does the following definitions array wire? State which concrete class each interface resolves to, and identify any potential issues.

```php
use function DI\autowire;
use function DI\factory;

return [
    DatabaseInterface::class => autowire(MySQLDatabase::class),
    LoggerInterface::class   => factory(function() {
        return new FileLogger(getenv('LOG_PATH') ?: '/tmp/app.log');
    }),
    MailerInterface::class   => autowire(SmtpMailer::class),
];
```

`MySQLDatabase` has constructor: `__construct(private string $dsn)`
`SmtpMailer` has constructor: `__construct(private LoggerInterface $logger)`

*Your answer:*

---

**Q19.** What will the following code output (assume PHP-DI is installed and the classes are defined)?

```php
<?php
use DI\ContainerBuilder;
use function DI\factory;
use function DI\autowire;

interface Logger { public function log(string $m): void; }
class ConsoleLogger implements Logger {
    public function __construct() { echo "Logger created\n"; }
    public function log(string $m): void { echo "[LOG] {$m}\n"; }
}
class Service {
    public function __construct(private Logger $log) {
        echo "Service created\n";
    }
    public function run(): void { $this->log->log("Running"); }
}

$builder = new ContainerBuilder();
$builder->addDefinitions([
    Logger::class => autowire(ConsoleLogger::class),
]);
$container = $builder->build();

$s1 = $container->get(Service::class);
$s2 = $container->get(Service::class);
$s1->run();
echo $s1 === $s2 ? "same\n" : "different\n";
```

*Your answer:*

---

**Q20.** The following definitions file has two bugs. Identify both and explain the correct fix for each.

```php
// config/services.php
return [
    // Bug 1
    DatabaseInterface::class => new MySQLDatabase(getenv('DB_DSN')),

    // Bug 2
    LoggerInterface::class => factory(function() {
        $container = new \DI\ContainerBuilder()->build();
        return $container->get(FileLogger::class);
    }),

    MailerInterface::class => autowire(ConsoleMailer::class),
];
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
| 1 | **B** | `ContainerBuilder` assembles the container from configuration and produces a PSR-11 compliant container. |
| 2 | **C** | `autowire(ConcreteClass::class)` maps an interface to a concrete class and auto-wires its constructor. |
| 3 | **B** | `string` is a primitive — `autowire()` cannot resolve it. A `factory()` that reads `getenv()` is the correct approach. |
| 4 | **C** | PSR-11 (`ContainerInterface`). Slim, Symfony, Mezzio all accept any PSR-11 container — no adapter needed for PHP-DI. |
| 5 | **C** | The definitions file is the composition root — the only place where config, env vars, and implementation decisions belong (Rule 1). |
| 6 | **B** | The container argument lets the factory resolve other services — needed for decorators (`new LoggingGateway($c->get(GatewayInterface::class), $c->get(LoggerInterface::class))`). |
| 7 | **B** | `$path` has a default value — PHP-DI calls `getDefaultValue()` and uses `'/tmp/app.log'`. No exception is thrown. |
| 8 | **B** | `autowire()` resolves everything automatically. `create()` does the same but allows explicit constructor argument overrides via `->constructor(arg1, arg2)`. |

## Section B
| # | Answer | Explanation |
|---|--------|-------------|
| 9  | **T** | `addDefinitions()` accepts an array directly OR a string path to a PHP file that returns an array. |
| 10 | **T** | For concrete classes whose constructors have only interface-typed or optional params, PHP-DI resolves them with zero explicit definitions. |
| 11 | **F** | A class calling `$container->get()` internally is a Service Locator — the anti-pattern DI was invented to replace. The container belongs only at the entry point. |
| 12 | **T** | PHP-DI's default scope is singleton — one instance per container lifetime. Transient scope requires explicit configuration (Lesson 6.2). |
| 13 | **T** | `factory()` and `autowire()` are both valid values in the definitions array and can be mixed freely. |
| 14 | **T** | `ContainerBuilder::enableCompilation($cacheDir)` generates a compiled PHP class with direct `new` calls — zero Reflection at runtime in production. |

## Section C

**Q15 — Model answer:**
Factory definitions enforce Rule 1 because `getenv()` calls are physically located in the definitions file — the composition root — and nowhere else. Service classes receive constructed objects via their constructors; they never know where the values came from. If `MySQLDatabase` called `getenv('DB_DSN')` directly, it would be impossible to test without setting environment variables, impossible to use in a context with a different DSN, and the configuration concern would be entangled with the data access concern — violating both SRP and Rule 1.

**Q16 — Model answer:**
The anti-pattern is the **Service Locator**. `OrderService` calls `$container->get()` inside `process()` — it reaches into a global registry at runtime rather than declaring its dependency via the constructor.

Problem 1 — **Hidden dependency**: The constructor signature shows only `\DI\Container`. The real dependency (`PaymentGatewayInterface`) is invisible until you read every line of every method. A developer cannot determine what `OrderService` needs without auditing its source.

Problem 2 — **Untestable in isolation**: To unit-test `process()`, you must pre-populate a real `\DI\Container` with a `PaymentGatewayInterface` binding. With constructor injection, you simply pass a fake gateway directly — one line. The Service Locator version requires full container setup for every test.

**Q17 — Model answer:**
Scenario 1 — **Primitive constructor params**: A class like `MySQLDatabase($dsn, $port)` cannot be auto-wired because `string` and `int` are builtin types (`isBuiltin() = true`). A `factory()` reads `getenv('DB_DSN')` and constructs the class with the correct values.

Scenario 2 — **Decorator pattern**: A `LoggingGateway` wraps another `PaymentGatewayInterface`. `autowire()` cannot express this wrapping — it would try to resolve `PaymentGatewayInterface` recursively and hit a circular binding. A `factory()` receives the container, resolves the inner gateway with `$c->get(StripeGateway::class)`, and wraps it: `return new LoggingGateway($innerGateway, $c->get(LoggerInterface::class))`.

## Section D

**Q18 — Answer:**
- `DatabaseInterface` → `MySQLDatabase` via `autowire()`. **Potential issue**: `MySQLDatabase::__construct(string $dsn)` has a required primitive param. PHP-DI will throw because `string` cannot be auto-wired. Should be `factory(fn() => new MySQLDatabase(getenv('DB_DSN')))`.
- `LoggerInterface` → `FileLogger` via `factory()`. Correctly reads `LOG_PATH` from env with fallback. No issue.
- `MailerInterface` → `SmtpMailer` via `autowire()`. `SmtpMailer::__construct(LoggerInterface $logger)` — this IS auto-wirable because `LoggerInterface` is registered. PHP-DI will inject the `FileLogger` singleton. No issue.

**Q19 — Answer:**
```
Logger created
Service created
[LOG] Running
same
```
`get(Service::class)` → no binding → auto-wire `Service`. Needs `Logger`. `get(Logger::class)` → binding to `ConsoleLogger` → auto-wire → `"Logger created"`. Cached. `Service` created with `ConsoleLogger` → `"Service created"`. Cached.
`get(Service::class)` again → cache hit → same instance, no output.
`$s1->run()` → `log("Running")` → `"[LOG] Running"`.
`$s1 === $s2` → `true` (singleton) → `"same"`.

**Q20 — Answer:**
Bug 1: `DatabaseInterface::class => new MySQLDatabase(getenv('DB_DSN'))` — the `new` expression is evaluated **immediately when the definitions file is loaded**, not lazily when the container resolves it. This means the database connection is created at definition-load time regardless of whether it is ever needed, and `getenv()` is called before the container has been configured. Fix: wrap in `factory()`: `factory(fn() => new MySQLDatabase(getenv('DB_DSN')))`.

Bug 2: Inside the factory, `new \DI\ContainerBuilder()->build()` creates a **new, empty container** — separate from the application container. This means `FileLogger` is resolved without any of the application's definitions, and the factory bypasses singleton caching entirely. Fix: use the container argument that PHP-DI passes to the factory: `factory(function(\Psr\Container\ContainerInterface $c) { return $c->get(FileLogger::class); })` — or simply `autowire(FileLogger::class)` since `FileLogger` likely has only optional/interface params.

---

## Score Guide

| Score | Verdict |
|-------|---------|
| 18–20 | Ready for Lesson 4.5 (Capstone: Slim + PHP-DI). |
| 14–17 | Re-read the README sections for any missed questions, then move on. |
| Below 14 | Re-run the examples, redo the challenge, then retake the quiz before continuing. |