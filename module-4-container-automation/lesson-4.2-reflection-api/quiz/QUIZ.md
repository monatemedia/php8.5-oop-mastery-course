# Quiz — Lesson 4.2: PHP Reflection API
> Complete this quiz **without** looking at any example or solution files.
> Write your answers before checking the answer key at the bottom.
> Any question you get wrong is a reading target.

---

## Section A — Multiple Choice

**Q1.** What does `ReflectionClass` do?

- A) It creates a new instance of a class with all dependencies injected.
- B) It lets you inspect class metadata (constructor, methods, properties, interfaces) at runtime without instantiating the class.
- C) It validates that a class correctly implements all its declared interfaces.
- D) It generates documentation from PHPDoc comments.

---

**Q2.** What does `ReflectionNamedType::isBuiltin()` return for `LoggerInterface`?

- A) `true` — interfaces are built into PHP.
- B) `false` — `LoggerInterface` is a user-defined class/interface, not a PHP scalar.
- C) It throws an exception — `isBuiltin()` only works on concrete classes.
- D) `null` — the return type is nullable.

---

**Q3.** You are writing an auto-wiring container. A constructor parameter has type `string`. `$type->isBuiltin()` returns `true`. What should the container do?

- A) Resolve it as an empty string.
- B) Look it up in the container bindings like any other type.
- C) Throw an exception or fall back to a default value — primitive types cannot be auto-wired from the container.
- D) Skip the parameter and instantiate the class without it.

---

**Q4.** `ReflectionClass::getConstructor()` returns `null`. What does this mean?

- A) The class has an empty constructor with no parameters.
- B) The class has no declared constructor — PHP will use the default zero-argument constructor.
- C) The class is abstract and cannot be instantiated.
- D) The constructor is private and cannot be accessed via Reflection.

---

**Q5.** You call `new ReflectionClass(OrderService::class)` and then inspect its constructor parameters. Does this instantiate `OrderService`?

- A) Yes — Reflection must create an instance to read the constructor signature.
- B) No — Reflection reads class metadata without running the constructor.
- C) Yes, but only if `OrderService` has a zero-argument constructor.
- D) It depends on whether `OrderService` has side effects in its constructor.

---

**Q6.** What PHP class should you check `instanceof` against to detect a union type parameter (`int|string`)?

- A) `ReflectionNamedType`
- B) `ReflectionUnionType`
- C) `ReflectionType`
- D) `ReflectionMultiType`

---

**Q7.** Why do real containers cache `ReflectionClass` results?

- A) To prevent two instances of the same class from being created.
- B) Because `ReflectionClass` is not thread-safe without caching.
- C) Because creating `ReflectionClass` objects has a cost — caching ensures each class is reflected at most once per container lifetime, reducing overhead at scale.
- D) PHP requires all Reflection objects to be cached, otherwise they are garbage-collected immediately.

---

**Q8.** What does `ReflectionParameter::isOptional()` return `true` for?

- A) Parameters typed as `?Type` (nullable).
- B) Parameters that have a default value (including `null` default).
- C) Parameters typed as `mixed`.
- D) Parameters that are not the first parameter in the constructor.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 9  | `$ref->newInstanceArgs([$dep1, $dep2])` instantiates the class with the provided arguments — equivalent to `new ClassName($dep1, $dep2)`. | |
| 10 | A nullable parameter `?LoggerInterface $logger = null` has `isBuiltin() = true`. | |
| 11 | PHP-DI's compiled container eliminates Reflection calls at runtime by generating a plain PHP file during a build step. | |
| 12 | `ReflectionClass::isInstantiable()` returns `false` for abstract classes and interfaces. | |
| 13 | An intersection type parameter `Countable&Traversable` should be auto-wired by resolving it as if it were a named type. | |
| 14 | If a constructor parameter has no type hint at all, `$param->getType()` returns `null`. | |

---

## Section C — Short Answer

**Q15.** Explain why typing all constructor parameters as interfaces (rather than `string` or `mixed`) directly enables auto-wiring. Reference `isBuiltin()` in your answer.

*Your answer:*

---

**Q16.** A class has this constructor:
```php
public function __construct(
    private DatabaseInterface $db,
    private string            $tableName = 'users'
) {}
```
Which parameters can be auto-wired, which cannot, and what should the container do with the unresolvable one?

*Your answer:*

---

**Q17.** Describe the two strategies for caching Reflection results covered in this lesson and state which is appropriate for development vs production.

*Your answer:*

---

## Section D — Code Reading

**Q18.** What does the following code print?

```php
<?php
declare(strict_types=1);

interface Logger { public function log(string $m): void; }

class Service {
    public function __construct(
        private Logger  $logger,
        private string  $name = 'default',
        private ?Logger $fallback = null
    ) {}
}

$ref  = new ReflectionClass(Service::class);
$ctor = $ref->getConstructor();

foreach ($ctor->getParameters() as $param) {
    $type = $param->getType();
    $name = $param->getName();

    if ($type instanceof ReflectionNamedType) {
        echo "{$name}: builtin=" . ($type->isBuiltin() ? 'true' : 'false')
           . ", auto=" . (!$type->isBuiltin() ? 'true' : 'false')
           . ", optional=" . ($param->isOptional() ? 'true' : 'false')
           . ", nullable=" . ($type->allowsNull() ? 'true' : 'false')
           . "\n";
    }
}
```

*Your answer:*

---

**Q19.** The following auto-wiring function has a bug. Identify it and explain the fix.

```php
function autowire(string $class, array $bindings): object {
    $ref    = new ReflectionClass($class);
    $ctor   = $ref->getConstructor();
    $params = $ctor->getParameters();
    $deps   = [];

    foreach ($params as $param) {
        $type = $param->getType();
        $deps[] = $bindings[$type->getName()];
    }

    return $ref->newInstanceArgs($deps);
}
```

*Your answer:*

---

**Q20.** What does the following `ReflectionCache` usage print? Trace through the cache hits and misses.

```php
<?php
declare(strict_types=1);

interface LoggerInterface { public function log(string $m): void; }
interface DbInterface { public function q(): array; }

class UserService {
    public function __construct(
        private DbInterface     $db,
        private LoggerInterface $log
    ) {}
}

class OrderService {
    public function __construct(
        private DbInterface     $db,
        private LoggerInterface $log
    ) {}
}

// Assume ReflectionCache from the challenge solution
$cache = new ReflectionCache();

$deps1 = $cache->getResolvableDeps(UserService::class);
$deps2 = $cache->getResolvableDeps(OrderService::class);
$deps3 = $cache->getResolvableDeps(UserService::class); // third call — same class

$stats = $cache->stats();
echo "classes_cached: " . $stats['classes_cached'] . "\n";
echo "params_cached:  " . $stats['params_cached']  . "\n";
echo "deps1 == deps3? " . ($deps1 === $deps3 ? 'true' : 'false') . "\n";
echo "dep types for UserService: ";
echo implode(', ', array_column(array_values($deps1), 'type')) . "\n";
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
| 1 | **B** | Reflection reads class metadata at runtime without instantiating. It does not create instances, validate, or generate docs. |
| 2 | **B** | `LoggerInterface` is user-defined — `isBuiltin()` returns `false`. `isBuiltin()` is `true` only for PHP's built-in scalar types. |
| 3 | **C** | Built-in scalar types cannot be resolved from a container. The container should throw a `RuntimeException` (if required) or use the default value (if optional). |
| 4 | **B** | `getConstructor()` returns `null` when no constructor is declared — PHP uses a default no-argument constructor. The container can call `new ClassName()` directly. |
| 5 | **B** | Reflection reads metadata — it never runs the constructor. `OrderService` is not instantiated. This is why Reflection is safe for planning the wiring graph. |
| 6 | **B** | Union types (`int|string`) produce a `ReflectionUnionType` instance. Check `$type instanceof ReflectionUnionType`. |
| 7 | **C** | Creating `ReflectionClass` objects has a cost. Caching ensures each class is reflected once per container lifetime — significant at scale. |
| 8 | **B** | `isOptional()` is `true` when the parameter has a default value (including `null`). It is NOT about nullability — `?Type` with no default is NOT optional. |

## Section B
| # | Answer | Explanation |
|---|--------|-------------|
| 9  | **T** | `newInstanceArgs([$dep1, $dep2])` is exactly equivalent to `new ClassName($dep1, $dep2)` — it uses the constructor with the provided arguments array. |
| 10 | **F** | `?LoggerInterface` has `isBuiltin() = false` — `LoggerInterface` is a user-defined interface, not a scalar. The `?` adds nullability but does not change the type name. |
| 11 | **T** | PHP-DI's `ContainerBuilder::enableCompilation()` generates a PHP file with direct `new` calls — zero Reflection at runtime. |
| 12 | **T** | Abstract classes and interfaces cannot be directly instantiated. `isInstantiable()` returns `false` for both. |
| 13 | **F** | Intersection types (`Countable&Traversable`) are ambiguous — the container cannot auto-wire them. They require an explicit factory definition. |
| 14 | **T** | `getType()` returns `null` when the parameter has no type hint. Always check for `null` before calling methods on the result. |

## Section C

**Q15 — Model answer:**
When a constructor parameter is typed as `LoggerInterface`, `$param->getType()->isBuiltin()` returns `false` — the type is a class or interface name, not a PHP scalar. The container can look up `LoggerInterface::class` in its bindings and resolve the dependency automatically. When a parameter is typed as `string` or `int`, `isBuiltin()` returns `true` — the container has no way to know which string value to inject and must throw an exception or fall back to a default. This is why Course Philosophy Rule 3 (type system as security layer) directly enables auto-wiring: well-typed interfaces produce `isBuiltin() = false` on every parameter, giving the container everything it needs.

**Q16 — Model answer:**
`private DatabaseInterface $db` — auto-wirable: `isBuiltin() = false`, the container can resolve `DatabaseInterface` from its bindings.
`private string $tableName = 'users'` — NOT auto-wirable: `isBuiltin() = true`, a string cannot be resolved from the container. However, `isOptional() = true` because it has a default value (`'users'`). The container should skip this parameter and use its default value — which means `new TheClass($db)` (PHP fills in `'users'` automatically). If it were required with no default, the container would need to throw and require an explicit factory definition.

**Q17 — Model answer:**
Strategy 1 — **In-memory cache per container lifetime**: a private `$classCache` array stores one `ReflectionClass` per class name. Each class is reflected at most once, then returned from the array. The cache resets on every request in PHP-FPM (each request has a fresh container). Appropriate for development — picks up code changes on every request.
Strategy 2 — **Compiled container (PHP-DI production mode)**: Reflection runs once during a build/deployment step and generates a plain PHP file with direct `new` calls and no Reflection. Appropriate for production — zero reflection overhead, but must be re-compiled when class signatures change.

## Section D

**Q18 — Answer:**
```
logger: builtin=false, auto=true, optional=false, nullable=false
name: builtin=true, auto=false, optional=true, nullable=false
fallback: builtin=false, auto=true, optional=true, nullable=true
```
`$logger`: `Logger` is a user interface — `isBuiltin()=false`. Not optional (no default). Not nullable (not `?Logger`).
`$name`: `string` is built-in — `isBuiltin()=true`. Optional (has default `'default'`). Not nullable.
`$fallback`: `?Logger` — `isBuiltin()=false` (Logger is still an interface). Optional (default `null`). Nullable (the `?` prefix).

**Q19 — Answer:**
Three bugs:
1. **`$ctor` can be `null`**: if the class has no constructor, `$ctor->getParameters()` throws a fatal error. Fix: check `if ($ctor === null) return new $class();`
2. **`$type` can be `null`**: if a parameter has no type hint, `$type->getName()` throws. Fix: check `if ($type instanceof ReflectionNamedType) { ... } else { /* use default or throw */ }`
3. **No check for `isBuiltin()`**: if a parameter is typed as `string`, `$bindings['string']` will not exist and the function will insert `null` silently. Fix: add `if ($type->isBuiltin()) { throw or use default }` before looking up the binding.

**Q20 — Answer:**
```
classes_cached: 2
params_cached:  2
deps1 == deps3? true
dep types for UserService: App\DbInterface, App\LoggerInterface
```
Call 1 (`getResolvableDeps(UserService::class)`): cache miss — creates `ReflectionClass(UserService)`, reads params, caches both. `classes_cached=1, params_cached=1`.
Call 2 (`getResolvableDeps(OrderService::class)`): cache miss — creates `ReflectionClass(OrderService)`, reads params, caches both. `classes_cached=2, params_cached=2`.
Call 3 (`getResolvableDeps(UserService::class)`): cache HIT — returns the cached deps array directly. No new ReflectionClass created.
`deps1 === deps3`: `true` — same array returned from cache.
`dep types for UserService`: `DbInterface` and `LoggerInterface` (the two auto-wirable types).

---

## Score Guide

| Score | Verdict |
|-------|---------|
| 18–20 | Ready for Lesson 4.3 — strong Reflection foundation. |
| 14–17 | Re-read the README sections for any missed questions, then move on. |
| Below 14 | Re-run the examples, redo the challenge, then retake the quiz before continuing. |