# Code Challenge — Lesson 4.4: PHP-DI Library

> **Wire the complete Module 3 checkout system using PHP-DI with a proper `config/services.php` definitions file**

---

## The Brief

You have the complete checkout system from Module 3, currently wired with a manual flat function. Your task is to replace that wiring with PHP-DI — using a definitions file as the composition root, factory definitions for any classes with primitive constructor params, and a test wiring section that verifies the system works with anonymous class stubs.

---

## Prerequisites

PHP-DI must be installed:

```bash
composer require php-di/php-di
```

---

## What the Starter Code Has

Open `starter.php`. You will find:

- The full checkout system interfaces and service classes (identical to Lesson 4.3)
- An existing flat wiring function `buildApp()` that currently works — keep it for comparison
- A skeleton `getDefinitions()` function for you to complete
- A test section with assertions that must all pass

---

## Your Tasks

### Task 1 — Complete `getDefinitions(): array`

Write the PHP-DI definitions array that wires the checkout system. Use:

- `autowire()` for interface → concrete class bindings where the concrete class has only interface-typed constructor params
- `factory()` for any class that needs primitive constructor params or env-based logic
- The `InventoryInterface` binding should use a `factory()` that demonstrates reading from environment (`APP_ENV`) — use `InMemoryInventory` for development and `InMemoryInventory` for all environments in this exercise (since we don't have a real DB implementation, but the factory pattern should be demonstrated)

The definitions must cover:
```
DatabaseInterface::class
CacheInterface::class
LoggerInterface::class
MailerInterface::class
ProductRepositoryInterface::class
InventoryInterface::class
```

### Task 2 — Build the container and resolve `CheckoutController`

```php
$builder = new ContainerBuilder();
$builder->addDefinitions(getDefinitions());
$container = $builder->build();
$controller = $container->get(CheckoutController::class);
```

### Task 3 — Run the checkout and compare output

Call `$controller->handle([...])` and verify the response matches the flat `buildApp()` output.

### Task 4 — Verify singleton sharing

Assert:
- `$container->get(CheckoutController::class) === $container->get(CheckoutController::class)` (same instance)
- The `DatabaseInterface` inside `ProductCatalog` is the same instance as the one inside `InventoryChecker`

### Task 5 — Test wiring

Build a second container using `getTestDefinitions()` that replaces:
- `DatabaseInterface` with an anonymous fake that returns controlled data
- `LoggerInterface` with a null logger
- `MailerInterface` with a spy mailer

Assert that the spy mailer received exactly one `send()` call after checkout.

---

## Acceptance Criteria

- [ ] `getDefinitions()` uses `autowire()` and/or `factory()` for all six interfaces
- [ ] At least one `factory()` call is present (demonstrating the pattern)
- [ ] Container resolves `CheckoutController` successfully
- [ ] Checkout response matches `buildApp()` output structure
- [ ] Singleton assertion: two `get(CheckoutController::class)` calls return `===`
- [ ] Shared DB assertion passes
- [ ] Test wiring uses anonymous class stubs — no real infrastructure
- [ ] Spy mailer assertion passes
- [ ] All assertions print `✓`

---

## Expected Output

```
=== Flat wiring (buildApp) ===
  [CACHE] MISS: product_1
  ...
  [INFO] Order #XXXXX placed
{"success":true,...}

=== PHP-DI wiring ===
  [CACHE] MISS: product_1
  ...
  [INFO] Order #XXXXX placed
{"success":true,...}

=== Assertions ===
  ✓ Same controller (singleton)
  ✓ Same DB in ProductCatalog and InventoryChecker
  ✓ Spy mailer called once
  ✓ Checkout response has success=true
  All assertions PASSED
```

---

## Hints

- Import: `use DI\ContainerBuilder; use function DI\autowire; use function DI\factory;`
- The factory function receives the container as its first argument: `factory(function(\Psr\Container\ContainerInterface $c) { ... })`
- `autowire(ClassName::class)` and `autowire()` with no arg both work when the key is the class being mapped
- The challenge is intentionally self-contained in a single file — in a real project the definitions would live in `config/services.php`