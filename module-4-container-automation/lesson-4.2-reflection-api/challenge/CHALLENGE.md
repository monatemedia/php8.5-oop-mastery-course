# Code Challenge — Lesson 4.2: PHP Reflection API

> **Write `getConstructorDependencies()` — the function every auto-wiring container is built on**

---

## The Brief

You will write and test a production-quality `getConstructorDependencies()` function that a container can call to discover what a class needs. This is the exact function that sits at the heart of every auto-wiring container — once you have written it, Lesson 4.3 will use it to resolve entire dependency graphs automatically.

---

## What the Starter Code Has

Open `starter.php`. You will find:

- A set of interfaces and classes with various constructor signatures (the same edge cases from Example 03)
- A skeleton `getConstructorDependencies()` function for you to complete
- A test harness that calls your function against each class and asserts the results
- A skeleton `ReflectionCache` class for you to complete (Task 2)

---

## Your Tasks

Work in `starter.php`. Do NOT look at `solution.php` until you have made a genuine attempt.

### Task 1 — Complete `getConstructorDependencies(string $className): array`

The function must return an array of dependency descriptors. Each descriptor is an associative array:

```php
[
    'param'    => string,  // parameter name (e.g. 'db', 'logger')
    'type'     => string,  // fully-qualified type name (e.g. 'App\DatabaseInterface')
    'builtin'  => bool,    // true if scalar (string, int, etc.) — cannot auto-wire
    'optional' => bool,    // true if has a default value
    'nullable' => bool,    // true if type allows null (?Type)
    'auto'     => bool,    // true if the container can auto-wire this param
]
```

Handle ALL of these cases:
- No constructor → return `[]`
- Constructor with zero parameters → return `[]`
- Parameter with no type hint → `builtin = false, auto = false`
- `ReflectionNamedType` (scalar or class/interface) → `builtin` and `auto` set correctly
- `ReflectionUnionType` → `auto = false` (ambiguous)
- `ReflectionIntersectionType` → `auto = false` (needs explicit factory)
- Optional parameters → `optional = true`
- Nullable types (`?Type`) → `nullable = true`

### Task 2 — Complete the `ReflectionCache` class

Implement these methods:

```php
public function getClass(string $className): ReflectionClass
public function getConstructorParams(string $className): array   // ReflectionParameter[]
public function getResolvableDeps(string $className): array      // auto-wirable deps only
public function isInstantiable(string $className): bool
```

Use the in-memory caching pattern from Example 04 — reflect each class at most once.

### Task 3 — Run the assertions

The starter has a `runAssertions()` function that tests your implementation against eight classes. All eight assertions must pass.

### Task 4 — Benchmark

After all assertions pass, run the benchmark at the bottom of the file. Call your function (without cache) 1000 times on four classes, then call it (with your `ReflectionCache`) 1000 times. Print both times. The cached version should be measurably faster.

---

## Acceptance Criteria

- [ ] `getConstructorDependencies()` returns correct results for all eight test classes
- [ ] No constructor and empty constructor both return `[]`
- [ ] `builtin = true` for scalar types; `builtin = false` for interfaces/classes
- [ ] `auto = true` only for non-builtin named types
- [ ] `optional = true` for parameters with default values
- [ ] `nullable = true` for `?Type` parameters
- [ ] Union and intersection types have `auto = false`
- [ ] `ReflectionCache` reflects each class at most once (verified by `stats()`)
- [ ] All eight assertions PASS
- [ ] Benchmark shows cached version is faster

---

## Hints

- `$param->getType()` can return `ReflectionNamedType`, `ReflectionUnionType`, `ReflectionIntersectionType`, or `null`
- `$type->isBuiltin()` is only available on `ReflectionNamedType` — check the type first
- `$param->isOptional()` returns `true` if the parameter has a default value (including `null`)
- A nullable type `?LoggerInterface` has `allowsNull() = true` AND `getName() = 'LoggerInterface'`
- See Example 03 for the complete analysis function as a reference