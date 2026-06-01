# Code Challenge — Lesson 4.1: Service Containers

> **Build a `SimpleContainer` from scratch and wire the Module 3 checkout system with it**

---

## The Brief

You will build a production-quality `SimpleContainer` class that supports all three registration modes (`bind`, `singleton`, `instance`) and then use it to wire the complete Module 3 checkout system — replacing the flat `buildApp()` wiring function with a container-driven composition root.

---

## What the Starter Code Has

Open `starter.php`. You will find:

- The four interfaces and concrete classes from Module 3 (`DatabaseInterface`, `LoggerInterface`, `MailerInterface`, `ProductRepositoryInterface`)
- `ProductCatalog`, `InventoryChecker`, `CheckoutService`, `CheckoutController` — all already using constructor injection (from the Module 3 solution)
- A flat `buildApp()` function that currently wires everything manually
- A skeleton `SimpleContainer` class for you to complete

---

## Your Tasks

Work in `starter.php`. Do NOT look at `solution.php` until you have made a genuine attempt.

### Task 1 — Complete `SimpleContainer`

Implement these methods inside the `SimpleContainer` skeleton:

```php
public function bind(string $id, callable $factory): void
```
Registers a factory. Every `get()` call invokes the factory and returns a fresh instance.

```php
public function singleton(string $id, callable $factory): void
```
Registers a singleton factory. The factory is called once; subsequent `get()` calls return the cached instance.

```php
public function instance(string $id, object $object): void
```
Stores a pre-built object. Always returns this exact object.

```php
public function get(string $id): mixed
```
Resolves the binding for `$id`. Throws `\RuntimeException` if not found.

```php
public function has(string $id): bool
```
Returns `true` if a binding or instance exists for `$id`.

### Task 2 — Wire the checkout system using `SimpleContainer`

Replace the existing `buildApp()` wiring function with a container composition root. Bind:

- `DatabaseInterface::class` → `InMemoryDatabase` as singleton
- `CacheInterface::class` → `ArrayCache` as singleton
- `LoggerInterface::class` → `ConsoleLogger` as singleton
- `MailerInterface::class` → `ConsoleMailer` as singleton
- `ProductRepositoryInterface::class` → `ProductCatalog` as singleton
- `InventoryInterface::class` → `InventoryChecker` as singleton
- `CheckoutService::class` → singleton with factory
- `CheckoutController::class` → singleton with factory

### Task 3 — Resolve and use the controller

Call `$container->get(CheckoutController::class)` and use it to process a checkout. The output must be identical to the current `buildApp()` output.

### Task 4 — Verify singleton behaviour

After calling `$container->get(CheckoutController::class)`, call it again and assert both calls return the same object (`===`). Then verify that the `DatabaseInterface` resolved inside `ProductCatalog` is the same instance as the one resolved by `InventoryChecker`.

### Task 5 — Demonstrate the Service Locator anti-pattern

At the bottom of the file, write a `BadCheckoutController` class that stores the container and calls `$container->get()` inside its methods. Call it and show what it outputs. Then write a one-paragraph comment explaining why this is wrong.

---

## Acceptance Criteria

- [ ] `SimpleContainer` has all five methods implemented correctly
- [ ] `bind()` returns a fresh instance on every `get()`
- [ ] `singleton()` returns the same instance on every `get()`
- [ ] `instance()` always returns the pre-built object
- [ ] `get()` throws `\RuntimeException` for unregistered bindings
- [ ] Checkout system wired entirely via container — no manual `new` in the wiring section
- [ ] Container output matches the flat `buildApp()` output exactly
- [ ] Singleton assertion: two `get(CheckoutController::class)` calls return `===`
- [ ] Shared DB assertion: the DB used in `ProductCatalog` === the DB used in `InventoryChecker`
- [ ] `BadCheckoutController` example shows and comments on the anti-pattern

---

## Expected Output

```
=== Flat wiring (buildApp) ===
[INFO] Checkout request received
[INFO] Starting checkout for alice@example.com
[CACHE] MISS: product_1
[INFO] DB fetch: product #1
[CACHE] SET: product_1
[INVENTORY] Checking WDG-001 × 2
[INVENTORY] Reserving WDG-001 × 2
[MAIL] To: alice@example.com | Order Confirmed #XXXXX
[INFO] Checkout complete. Order #XXXXX

=== Container wiring ===
[INFO] Checkout request received
... (identical output)

=== Singleton assertions ===
Same controller? YES ✓
Same DB in ProductCatalog and InventoryChecker? YES ✓

=== Service Locator anti-pattern (BadCheckoutController) ===
[INFO] Request received (bad pattern)
...
// Comment: BadCheckoutController is wrong because...
```