# Code Challenge — Lesson 4.3: Auto-wiring

> **Extend `SimpleContainer` from Lesson 4.1 to support auto-wiring, and resolve the full checkout system with zero manual `bind()` calls for service classes**

---

## The Brief

You have the `SimpleContainer` from Lesson 4.1 (with `bind()`, `singleton()`, `instance()`, `get()`, `has()`). Your task is to extend it with two new capabilities:

1. **Auto-wiring** — when `get(ClassName)` is called and no explicit binding exists, the container reflects on the constructor, resolves all typed parameters recursively, and instantiates the class automatically
2. **Circular dependency detection** — if the recursive resolution encounters a class that is already being resolved, throw a `CircularDependencyException` with a descriptive chain message

Once your container is complete, wire the full checkout system (from Lesson 4.1's challenge) using only interface bindings — zero `bind()` or `singleton()` calls for service classes like `ProductCatalog`, `InventoryChecker`, `CheckoutService`, or `CheckoutController`.

---

## What the Starter Code Has

Open `starter.php`. You will find:

- The full checkout system from Lesson 4.1 (interfaces, concrete classes, service classes)
- The `SimpleContainer` from Lesson 4.1 (already working — do not break the existing methods)
- A `CircularDependencyException` class for you to throw
- Circular dependency test classes (`CircularA`, `CircularB`) to verify detection works
- Assertions at the bottom that must all pass

---

## Your Tasks

Work in `starter.php`. Do NOT look at `solution.php` until you have made a genuine attempt.

### Task 1 — Add auto-wiring to `SimpleContainer`

Modify the `get()` method so that when no explicit binding exists for an id, it calls a new `private function autowire(string $class): object` method.

The `autowire()` method must:
- Check `ReflectionClass::isInstantiable()` — throw if not instantiable
- Check for circular dependency — throw `CircularDependencyException` with the chain
- Mark the class as "being resolved" (add to `$this->resolving`)
- Reflect on the constructor
- For each parameter:
  - Non-builtin named type → call `$this->get()` recursively
  - Optional (has default) → use the default value
  - Required primitive/untyped → throw `\RuntimeException`
- Unmark the class (remove from `$this->resolving`) in a `finally` block
- Cache the result as a singleton (same behaviour as explicit singleton bindings)

### Task 2 — Wire the checkout system with only interface bindings

Register only these five bindings:
```php
$container->bind(DatabaseInterface::class,         InMemoryDatabase::class);
$container->bind(CacheInterface::class,             ArrayCache::class);
$container->bind(LoggerInterface::class,            ConsoleLogger::class);
$container->bind(MailerInterface::class,            ConsoleMailer::class);
$container->bind(ProductRepositoryInterface::class, ProductCatalog::class);
$container->bind(InventoryInterface::class,         InventoryChecker::class);
```

Then call `$container->get(CheckoutController::class)` — no other bindings.

### Task 3 — Verify singleton sharing

After resolving the controller, assert that:
- Two calls to `$container->get(CheckoutController::class)` return `===`
- The `DatabaseInterface` resolved inside `ProductCatalog` is `===` to the one inside `InventoryChecker`

### Task 4 — Verify circular dependency detection

Using the provided `CircularA` and `CircularB` classes, call `$container->get(CircularA::class)` and assert that a `CircularDependencyException` is thrown with a message containing "CircularA" and "CircularB".

---

## Acceptance Criteria

- [ ] `SimpleContainer::get()` falls back to auto-wiring when no explicit binding exists
- [ ] `autowire()` uses Reflection to resolve constructor params recursively
- [ ] `autowire()` detects circular dependencies and throws with a descriptive chain
- [ ] `finally` block always unmarks the class from `$resolving`
- [ ] Auto-wired results are cached as singletons
- [ ] Checkout system resolves correctly with only 6 interface bindings
- [ ] Zero `bind()`/`singleton()` calls for `ProductCatalog`, `InventoryChecker`, `CheckoutService`, `CheckoutController`
- [ ] Singleton assertion passes: two controller resolutions return `===`
- [ ] Shared DB assertion passes
- [ ] Circular dependency assertion passes

---

## Expected Output

```
=== Checkout via auto-wiring container ===
  [INFO] Checkout request received
  [CACHE] MISS: product_1
  [INFO] DB fetch: product #1
  [CACHE] SET: product_1
  [INVENTORY] Checking WDG-001 × 2
  [INVENTORY] Reserving WDG-001 × 2
  [MAIL] To: alice@example.com | Order Confirmed #XXXXX
  [INFO] Checkout complete. Order #XXXXX
{"success":true,...}

=== Assertions ===
  ✓ Same controller instance (singleton)
  ✓ Same DB in ProductCatalog and InventoryChecker
  ✓ CircularDependencyException thrown
  ✓ Exception message contains both class names
  All assertions PASSED
```