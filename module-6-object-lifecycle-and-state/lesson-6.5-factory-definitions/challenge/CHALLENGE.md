# Code Challenge — Lesson 6.5: Factory Definitions for Complex Lifecycles

> **Wire four factory definitions, write a test proving each, and provide a decorator integration test.**

---

## The Brief

You are completing the container configuration for a small web application. The classes are all written; you must wire them correctly in the container using PHP-DI's `factory()` pattern. Four definitions need writing, and a fifth test verifies that the decorator chain works end-to-end.

---

## Prerequisites

- Read README Sections 2–5
- Completed Examples 01–04

---

## The Four Wiring Tasks

All classes are defined in `starter/FactoryDefinitionsTest.php`. Do not modify them.

---

### Wiring 1 — `DatabaseConnection`

**The problem:** `DatabaseConnection` takes `string $dsn`, `string $user`, `string $password` in its constructor. Auto-wiring cannot resolve these scalar strings.

**Your task:**
- Register `DatabaseConnection` as a **singleton** using a `factory()` definition
- The factory reads the connection parameters from the simulated config array provided in `setUp()`
- Write `testDatabaseConnectionFactoryWiresCorrectly()` proving:
  - The resolved instance is a `DatabaseConnection`
  - Two resolutions return the same instance (singleton)
  - The DSN was correctly passed to the constructor

---

### Wiring 2 — `ShoppingCart`

**The problem:** `ShoppingCart` has mutable state — it must be **transient**.

**Your task:**
- Register `ShoppingCart` using a `factory()` definition with transient scope
- Write `testShoppingCartFactoryIsTransient()` proving:
  - Two resolutions return different instances (`assertNotSame`)
  - Each instance starts empty

---

### Wiring 3 — `NotificationServiceInterface` (Decorator)

**The problem:** `NotificationService` is the real implementation. `LoggingNotificationService` is a decorator that wraps it and logs every notification. Consumers should receive `LoggingNotificationService`.

**Your task:**
- Register `NotificationService` as its own concrete class (singleton)
- Register `NotificationServiceInterface` using a `factory()` that wraps `NotificationService` in `LoggingNotificationService`
- Write `testNotificationServiceDecoratorIsWiredCorrectly()` proving:
  - Resolving `NotificationServiceInterface` returns a `LoggingNotificationService`
  - Sending a notification via the interface logs an entry AND delivers the notification
  - The decorator correctly delegates to the inner `NotificationService`

---

### Wiring 4 — `StorageInterface` (Environment binding)

**The problem:** `S3Storage` is used in production; `LocalStorage` is used in development/test. The choice depends on `APP_ENV`.

**Your task:**
- Register `StorageInterface` using a `factory()` that selects the implementation based on the `$appEnv` variable captured from `setUp()`
- Write `testStorageInterfaceBindingSelectsCorrectImplementation()` proving:
  - When `$appEnv = 'production'`, `S3Storage` is returned
  - When `$appEnv = 'development'`, `LocalStorage` is returned
  - Both satisfy the `StorageInterface` contract

---

### Wiring 5 — Integration test

**Your task:**
- Write `testFullDecoratorChainIntegrationTest()` proving that calling `send()` on the resolved `NotificationServiceInterface`:
  1. Delivers the notification (the inner service's `getDelivered()` count increments)
  2. Logs the operation (the spy logger's entry count increments)
  3. Returns the correct result

---

## Acceptance Criteria

- [ ] `DatabaseConnection` is registered as a singleton with scalar args resolved from config
- [ ] `ShoppingCart` is registered as transient
- [ ] `NotificationServiceInterface` resolves to `LoggingNotificationService` wrapping `NotificationService`
- [ ] `StorageInterface` resolves to the correct impl based on `$appEnv`
- [ ] All five tests pass: `./vendor/bin/phpunit`
- [ ] `DatabaseConnection` registration uses `factory()` (not `new DatabaseConnection()` directly in `setUp()`)
- [ ] `NotificationService` is registered as its own class before being used in the decorator factory

---

## Running Your Tests

```bash
./vendor/bin/phpunit module-6-object-lifecycle-and-state/lesson-6.5-factory-definitions/challenge/starter/FactoryDefinitionsTest.php

# Verbose
./vendor/bin/phpunit --testdox module-6-object-lifecycle-and-state/lesson-6.5-factory-definitions/challenge/starter/FactoryDefinitionsTest.php
```

---

## Expected Output

```
FactoryDefinitions
 ✔ Database connection factory wires correctly
 ✔ Shopping cart factory is transient
 ✔ Notification service decorator is wired correctly
 ✔ Storage interface binding selects correct implementation
 ✔ Full decorator chain integration test

OK (5 tests, N assertions)
```