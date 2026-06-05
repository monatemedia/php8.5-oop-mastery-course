# Quiz — Lesson 4.3: Auto-wiring
> Complete this quiz **without** looking at any example or solution files.
> Write your answers before checking the answer key at the bottom.
> Any question you get wrong is a reading target.

---

## Section A — Multiple Choice

**Q1.** What is auto-wiring in the context of a DI container?

- A) Automatically generating interface implementations using PHP's Reflection API.
- B) The container resolves a class's constructor dependencies by reading type hints via Reflection, without requiring a manual factory for every service class.
- C) A PHP 8.5 feature that injects dependencies at the language level.
- D) Automatically detecting which classes implement which interfaces.

---

**Q2.** An `AutowiringContainer` has no explicit binding for `OrderService`. When `get(OrderService::class)` is called, what does the container do?

- A) Throws a `RuntimeException` because no binding exists.
- B) Returns `null`.
- C) Reflects on `OrderService::__construct()`, resolves each typed parameter recursively, instantiates the class, and caches the result.
- D) Calls `new OrderService()` without resolving any dependencies.

---

**Q3.** Which parameters can an auto-wiring container resolve without explicit configuration?

- A) All parameters, regardless of type.
- B) Only parameters with `mixed` type hints.
- C) Only parameters typed as non-builtin classes or interfaces (where `isBuiltin() = false`).
- D) Only parameters that are optional (have default values).

---

**Q4.** A class has constructor `__construct(private string $dsn, private LoggerInterface $logger)`. An auto-wiring container tries to resolve it with a binding for `LoggerInterface`. What happens?

- A) Both parameters are auto-wired — the container injects an empty string for `$dsn`.
- B) The container resolves `$logger` but throws a `RuntimeException` for `$dsn` because `string` is a builtin type that cannot be auto-wired.
- C) The container skips `$dsn` and only injects `$logger`.
- D) The container resolves `$dsn` as an empty string singleton.

---

**Q5.** What data structure is used to detect circular dependencies during recursive resolution?

- A) A stack of resolved class names.
- B) A set (associative array) of class names currently being resolved.
- C) A queue of pending resolutions.
- D) A reference counter for each class name.

---

**Q6.** Why must the circular dependency detection unmark a class in a `finally` block rather than after the `return` statement?

- A) `finally` is required for all Reflection operations.
- B) If an exception is thrown mid-resolution (for any reason), the class must be unmarked so the container remains usable for subsequent resolutions.
- C) PHP garbage-collects the `$resolving` array if it is not cleared in `finally`.
- D) The `return` statement runs before `finally` in PHP.

---

**Q7.** `OrderService` has three interface-typed constructor parameters. Its container has bindings for all three interfaces. How many explicit `bind()` calls are needed to resolve `OrderService`?

- A) 4 (one per parameter + one for OrderService itself).
- B) 3 (one per interface — OrderService itself needs no binding).
- C) 0 (auto-wiring requires no explicit bindings for any class).
- D) 1 (only the first parameter needs a binding).

---

**Q8.** Auto-wired results are cached as singletons. Why is this the right default for service classes?

- A) Services must be singletons for PHP to function correctly.
- B) Services are typically stateless (they perform work, hold no mutable per-request state), so sharing one instance is safe and avoids unnecessary re-construction. (Course Philosophy Rule 5)
- C) Singletons are faster because PHP can optimise them at compile time.
- D) Caching is required to prevent circular dependencies.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 9  | An explicit `bind(InterfaceA::class, ConcreteA::class)` takes precedence over auto-wiring when `get(InterfaceA::class)` is called. | |
| 10 | An auto-wiring container can resolve a class that has no constructor at all. | |
| 11 | A circular dependency `A → B → A` would cause infinite recursion without detection, eventually crashing PHP with a stack overflow. | |
| 12 | Auto-wiring replaces the need for any explicit configuration at the composition root. | |
| 13 | When a parameter has a default value and cannot be auto-wired (e.g. `private int $timeout = 30`), the container should use the default value rather than throwing. | |
| 14 | The `$resolving` stack approach for circular detection works correctly when exceptions are thrown mid-resolution, because the `finally` block removes the class from the stack. | |

---

## Section C — Short Answer

**Q15.** Explain why interface bindings are the ONLY bindings typically needed in a well-designed auto-wiring container. What makes service classes resolvable without explicit bindings?

*Your answer:*

---

**Q16.** You have:
```php
class ServiceA { public function __construct(private ServiceB $b) {} }
class ServiceB { public function __construct(private ServiceC $c) {} }
class ServiceC { public function __construct(private ServiceA $a) {} }
```
Trace exactly what happens when `$container->get(ServiceA::class)` is called, step by step, until the `CircularDependencyException` is thrown. What is the exception message?

*Your answer:*

---

**Q17.** A colleague argues that auto-wiring reduces architectural visibility because "you can't see the wiring anymore." Give a two-sentence response explaining why this concern is addressed by the explicit interface bindings and the constructor type hints.

*Your answer:*

---

## Section D — Code Reading

**Q18.** What will the following code output?

```php
<?php
declare(strict_types=1);

interface Logger { public function log(string $m): void; }

class ConsoleLogger implements Logger {
    public function __construct() { echo "Logger created\n"; }
    public function log(string $m): void { echo "[LOG] {$m}\n"; }
}

class ServiceA {
    public function __construct(private Logger $log) { echo "ServiceA created\n"; }
    public function run(): void { $this->log->log("Running A"); }
}

class ServiceB {
    public function __construct(private Logger $log, private ServiceA $a) {
        echo "ServiceB created\n";
    }
    public function run(): void { $this->log->log("Running B"); $this->a->run(); }
}

// Assume AutowiringContainer from the lesson
$c = new AutowiringContainer();
$c->bind(Logger::class, ConsoleLogger::class);

$b = $c->get(ServiceB::class);
$b->run();

$b2 = $c->get(ServiceB::class);
echo $b === $b2 ? "same\n" : "different\n";
```

*Your answer:*

---

**Q19.** Identify the bug in this auto-wiring implementation and explain the consequence.

```php
private function autowire(string $class): object {
    if (isset($this->resolving[$class])) {
        throw new CircularDependencyException("Circular: {$class}");
    }

    $ref  = new ReflectionClass($class);
    $ctor = $ref->getConstructor();
    $this->resolving[$class] = true;

    $deps = [];
    foreach ($ctor->getParameters() as $param) {
        $type = $param->getType();
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $deps[] = $this->get($type->getName());
        }
    }

    unset($this->resolving[$class]);  // ← removed from resolving BEFORE caching
    $instance = $ref->newInstanceArgs($deps);
    return $this->instances[$class] = $instance;
}
```

*Your answer:*

---

**Q20.** What does the following auto-wiring attempt produce? Trace through exactly what the container does for each call.

```php
<?php
declare(strict_types=1);

interface DbInterface { public function q(): array; }

class SqliteDb implements DbInterface {
    public function __construct(private string $path = ':memory:') {
        echo "SqliteDb created ({$path})\n";
    }
    public function q(): array { return []; }
}

class UserRepo {
    public function __construct(private DbInterface $db) {
        echo "UserRepo created\n";
    }
}

class UserService {
    public function __construct(private UserRepo $repo) {
        echo "UserService created\n";
    }
}

// Assume AutowiringContainer from the lesson
$c = new AutowiringContainer();
$c->bind(DbInterface::class, SqliteDb::class);

$svc = $c->get(UserService::class);
echo "Done\n";
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
| 1 | **B** | Auto-wiring reads constructor type hints via Reflection and resolves dependencies recursively, without a manual factory for each service class. |
| 2 | **C** | No explicit binding → container reflects `OrderService`, resolves each param recursively, instantiates, caches as singleton. |
| 3 | **C** | Only non-builtin named types (`isBuiltin() = false`) can be resolved from the container. Scalars cannot. |
| 4 | **B** | `string` is builtin — `isBuiltin() = true`. The container cannot resolve it. Unless `$dsn` has a default value, a `RuntimeException` is thrown. |
| 5 | **B** | An associative array (`$resolving`) keyed by class name. Before resolving a class, check if it is already in the array — if so, circular. |
| 6 | **B** | If any step throws (e.g. an unresolvable parameter), the `finally` block guarantees the class is removed from `$resolving`, leaving the container in a usable state. |
| 7 | **B** | 3 bindings — one per interface. `OrderService` itself requires no `bind()` call; the container auto-wires it. |
| 8 | **B** | Stateless services perform work with no mutable per-request state. Sharing one instance is safe and avoids wasteful re-construction. Course Philosophy Rule 5. |

## Section B
| # | Answer | Explanation |
|---|--------|-------------|
| 9  | **T** | Explicit bindings always take precedence — the container checks bindings first before attempting auto-wiring. |
| 10 | **T** | `getConstructor()` returns `null` for a class with no constructor. The container instantiates with `new $class()`. |
| 11 | **T** | Without detection, each `get()` call triggers another `get()` in an infinite recursive chain until PHP stack exhaustion. |
| 12 | **F** | Interface bindings are still required — the container cannot know which concrete class to use for an interface without being told. |
| 13 | **T** | `$param->isOptional()` returns `true` if a default value exists. The container calls `$param->getDefaultValue()` and uses it. |
| 14 | **T** | `finally` runs even when an exception is thrown, guaranteeing the resolving flag is cleared and the container remains consistent. |

## Section C

**Q15 — Model answer:**
Interface bindings are the only configuration needed because service classes — classes that depend only on other interface-typed services — have all their parameters typed as interfaces (`isBuiltin() = false`). The container reads these type hints, resolves each interface to its bound concrete class recursively, and builds the service automatically. The constructor type hints are the complete declaration of what a class needs; as long as each interface has a binding, the entire service graph resolves without any additional registration.

**Q16 — Model answer:**
1. `get(ServiceA::class)` → no binding → `autowire(ServiceA)`. Mark `$resolving = {ServiceA}`.
2. Reflect `ServiceA` → needs `ServiceB`. Call `get(ServiceB::class)`.
3. No binding → `autowire(ServiceB)`. Mark `$resolving = {ServiceA, ServiceB}`.
4. Reflect `ServiceB` → needs `ServiceC`. Call `get(ServiceC::class)`.
5. No binding → `autowire(ServiceC)`. Mark `$resolving = {ServiceA, ServiceB, ServiceC}`.
6. Reflect `ServiceC` → needs `ServiceA`. Call `get(ServiceA::class)`.
7. `autowire(ServiceA)` → `ServiceA` IS in `$resolving` → throw `CircularDependencyException`.
Message: `"Circular dependency detected: ServiceA → ServiceB → ServiceC → ServiceA"`

**Q17 — Model answer:**
The wiring is still fully visible — it just lives in two places: the explicit interface bindings at the composition root (which show exactly which concrete class maps to each interface), and the constructor signatures of service classes (which show exactly what each service needs). Auto-wiring does not hide coupling; it automates the mechanical task of writing `new OrderService($db, $mailer, $logger)` when all three deps are already declared as constructor parameters typed against their interfaces.

## Section D

**Q18 — Answer:**
```
Logger created
ServiceA created
ServiceB created
[LOG] Running B
[LOG] Running A
same
```
Trace: `get(ServiceB)` → no binding → `autowire(ServiceB)`. Needs `Logger` and `ServiceA`.
`get(Logger)` → binding to `ConsoleLogger` → `autowire(ConsoleLogger)` → `"Logger created"`. Cached.
`get(ServiceA)` → no binding → `autowire(ServiceA)`. Needs `Logger` → cache hit. `"ServiceA created"`. Cached.
`"ServiceB created"`. Cached.
`$b->run()`: `log("Running B")` → `"[LOG] Running B"`, `$a->run()` → `"[LOG] Running A"`.
`$b2 = get(ServiceB)` → cache hit, same instance.
`$b === $b2` → `true` → `"same"`.

**Q19 — Answer:**
The bug is that `unset($this->resolving[$class])` happens **before** `$ref->newInstanceArgs($deps)`. If `newInstanceArgs()` throws an exception (e.g. wrong argument count, type error), the class was already removed from `$resolving`, but it was never successfully cached. On a subsequent call to `get()` for the same class, the container would try to resolve it again — which is fine, but the `$resolving` unmark before successful instantiation means there is no "atomic" guarantee. More critically: **the instance is cached (`$this->instances[$class]`)** even though instantiation hasn't happened yet in this reading — actually the real bug is that `unset` before `newInstanceArgs` means if `newInstanceArgs` throws, the class is no longer marked as resolving, but also never cached. The container would silently retry on next call without the exception propagating correctly to the caller. The correct pattern is to instantiate inside the `try` block and `unset($resolving)` in `finally`, ensuring the resolved instance is only cached after successful construction.

**Q20 — Answer:**
```
SqliteDb created (:memory:)
UserRepo created
UserService created
Done
```
Trace:
1. `get(UserService)` → no binding → `autowire(UserService)`. Needs `UserRepo`.
2. `get(UserRepo)` → no binding → `autowire(UserRepo)`. Needs `DbInterface`.
3. `get(DbInterface)` → explicit binding to `SqliteDb` → `autowire(SqliteDb)`.
4. `SqliteDb.__construct(string $path = ':memory:')` — `$path` is optional (has default). Container uses default `':memory:'`. Prints `"SqliteDb created (:memory:)"`. Cached.
5. `UserRepo` instantiated with `SqliteDb`. Prints `"UserRepo created"`. Cached.
6. `UserService` instantiated with `UserRepo`. Prints `"UserService created"`. Cached.
7. `echo "Done\n"` → `"Done"`.

Note: `SqliteDb.$path` is `string` (builtin) but is **optional** (`isOptional() = true`) — so the container uses `getDefaultValue()` rather than throwing.

---

## Score Guide

| Score | Verdict |
|-------|---------|
| 18–20 | Ready for Lesson 4.4 — strong auto-wiring foundation. |
| 14–17 | Re-read the README sections for any missed questions, then move on. |
| Below 14 | Re-run the examples, redo the challenge, then retake the quiz before continuing. |