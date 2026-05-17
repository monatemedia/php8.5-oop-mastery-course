# Code Challenge — Lesson 3.2: Constructor Injection

> **Refactor a class that calls `new` internally to use constructor injection**

---

## The Brief

This challenge directly continues from Lesson 3.1. You audited the checkout system and found 14 violations. Now you fix them. The `starter.php` contains the same four classes from Lesson 3.1's starter — your job is to refactor all of them to use constructor injection throughout.

---

## What the Starter Code Has

Open `starter.php`. It contains the same tightly coupled `ProductCatalog`, `InventoryChecker`, `CheckoutService`, and `CheckoutController` from Lesson 3.1 — with all 14 violations intact.

The output when you run the file shows the checkout working with tight coupling. After your refactor, the output must be **identical**.

---

## Your Tasks

Work in `starter.php`. Do NOT look at `solution.php` until you have made a genuine attempt.

### Task 1 — Define the interfaces

Create these four interfaces (you may add more if needed):

```php
interface ProductRepositoryInterface {
    public function findById(int $id): ?array;
    public function findBySku(string $sku): ?array;
}

interface InventoryInterface {
    public function isAvailable(string $sku, int $quantity): bool;
    public function reserve(string $sku, int $quantity): bool;
}

interface MailerInterface {
    public function send(string $to, string $subject, string $body): bool;
}

interface LoggerInterface {
    public function log(string $level, string $message): void;
}
```

### Task 2 — Make existing classes implement the interfaces

Update `ProductCatalog` to `implement ProductRepositoryInterface`.
Update `SendGridMailer` to `implement MailerInterface`.
Update `MonologLogger` to `implement LoggerInterface`.

For `InventoryChecker`: it already has `isAvailable()` and `reserve()` — make it `implement InventoryInterface`.

### Task 3 — Refactor `ProductCatalog`

Remove all `new` and singleton calls from the constructor. Accept:
- `DatabaseInterface $db`
- `CacheInterface $cache`
- `LoggerInterface $logger`

You will need to define `DatabaseInterface` and `CacheInterface` as well.

### Task 4 — Refactor `InventoryChecker`

Remove all `new` and singleton calls. Accept:
- `DatabaseInterface $db`

### Task 5 — Refactor `CheckoutService`

Remove all `new` calls and concrete property types. Accept:
- `ProductRepositoryInterface $catalog`
- `InventoryInterface $inventory`
- `MailerInterface $mailer`
- `LoggerInterface $logger`

### Task 6 — Refactor `CheckoutController`

Remove all `new` calls and concrete property types. Accept:
- `CheckoutService $service` (concrete — it has no interface yet)
- `LoggerInterface $logger`

### Task 7 — Wire the composition root

At the bottom of the file, replace the current `new CheckoutController()` call with an explicit composition root — wire all dependencies manually and inject them from the outside.

### Task 8 — Add a test wiring

After the composition root, add a second wiring that uses anonymous class stubs for all infrastructure. Call `handleCheckout()` with the same request and confirm it produces the same result (without any real infrastructure).

---

## Acceptance Criteria

- [ ] Four interfaces defined: `ProductRepositoryInterface`, `InventoryInterface`, `MailerInterface`, `LoggerInterface`
- [ ] Two additional interfaces: `DatabaseInterface`, `CacheInterface`
- [ ] All four classes have zero `new` calls on services inside their bodies
- [ ] All four classes have zero concrete property types for dependencies
- [ ] `ProductCatalog::__construct()` accepts `DatabaseInterface`, `CacheInterface`, `LoggerInterface`
- [ ] `InventoryChecker::__construct()` accepts `DatabaseInterface`
- [ ] `CheckoutService::__construct()` accepts four interface-typed parameters
- [ ] `CheckoutController::__construct()` accepts two interface-typed parameters
- [ ] Composition root wires all dependencies explicitly
- [ ] Test wiring uses anonymous class stubs — no real infrastructure
- [ ] Both wirings produce correct output

---

## Expected Output

```
=== Production wiring ===
[INFO] Checkout request received
[INFO] Starting checkout for alice@example.com
[CACHE] GET product_1
[INFO] DB fetch: product #1
[CACHE] SET product_1
[INVENTORY] Checking WDG-001 × 2
[INVENTORY] Reserving WDG-001 × 2
[MAIL] To: alice@example.com | Subject: Order Confirmed #XXXXX
[INFO] Checkout complete. Order #XXXXX

=== Test wiring (anonymous class stubs — no infrastructure) ===
[TEST] Logger: INFO — Checkout request received
[TEST] Logger: INFO — Starting checkout for alice@example.com
[TEST] Logger: INFO — Checkout complete.
Test result: {"success":true,...}
```