# Code Challenge — Lesson 3.1: Tight vs Loose Coupling

> **Identify and document every coupling violation in a given class hierarchy**

---

## The Brief

You have been handed a small e-commerce checkout system. Before anyone writes a single line of fix code, the team needs a **complete coupling audit** — every violation identified, named, and categorised. This is your task.

You will NOT be writing any fix code in this challenge. The goal is pure **recognition**. Lesson 3.2 will fix everything you find here.

---

## What the Code Does

The system has four classes:

- `ProductCatalog` — fetches product data from a database
- `InventoryChecker` — verifies stock availability  
- `CheckoutService` — orchestrates the checkout process
- `CheckoutController` — handles the HTTP layer

---

## Your Tasks

Open `starter.php` and read all four classes carefully.

### Task 1 — Fill in the coupling audit table

At the bottom of `starter.php` there is a structured comment block. Fill in each row:

```
| Class | Line | Violation type | Description |
```

Violation types to use:
- `new-in-constructor` — `new ConcreteClass()` inside a constructor
- `new-in-method` — `new ConcreteClass()` inside a method body
- `concrete-property` — property typed as a concrete class, not an interface
- `singleton-access` — `SomeClass::getInstance()` or similar global state
- `static-call` — static method call on a concrete class
- `hardcoded-config` — DSN, API key, path, or connection string hardwired
- `magic-value` — unexplained literal (number or string used without context)
- `god-parameter` — passing a large object when only one of its fields is needed

### Task 2 — Answer the three testability questions

For each class, answer:
1. Can this class be instantiated in a test without real infrastructure?
2. Can its primary method be tested without network/disk/database access?
3. How many lines must be edited to switch the database from MySQL to PostgreSQL?

### Task 3 — Count the total violations

Sum up all violations across all four classes.

---

## Acceptance Criteria

- [ ] Every coupling violation in every class is listed in the audit table
- [ ] Each violation has the correct type label from the list above
- [ ] The three testability questions are answered for each class
- [ ] The total violation count is correct
- [ ] No fix code has been written — audit only

---

## How to Know You Found Everything

There are exactly **14 coupling violations** across the four classes. If your count differs:
- Check for static method calls (they are easy to miss)
- Check for hardcoded strings in constructors (DSNs, paths, API keys)
- Check method bodies as well as constructors
- Check property type declarations (not just `new` calls)

---

## Hints

- Work top to bottom through each class
- Mark each violation with a comment in the code (e.g. `// ❌ new-in-constructor`) as you find it
- Then transfer your findings to the audit table
- See `examples/04-identifying-coupling.php` for the annotation style used on a similar codebase

---

## Expected Output

This challenge is an audit — the code already runs. What changes is the acceptance
block at the bottom, which counts the annotations you add.

```
╔══════════════════════════════════════════════════════╗
║  Complete Coupling Audit                            ║
╚══════════════════════════════════════════════════════╝

Class                  Violation Type           Description
──────────────────────────────────────────────────────────────────────────────────────────
ProductCatalog         concrete-property        private PostgresDb $db — should be an interface
ProductCatalog         concrete-property        private RedisCache $cache — should be an interface
ProductCatalog         singleton-access         PostgresDb::getInstance() — hidden global dependency
ProductCatalog         hardcoded-config         "pgsql:host=db.prod:5432;dbname=shop" — hardwired DSN
ProductCatalog         new-in-constructor       new RedisCache("redis.prod", 6379) — creates own cache
InventoryChecker       concrete-property        private PostgresDb $db — should be an interface
InventoryChecker       singleton-access         PostgresDb::getInstance() — hidden global dependency
CheckoutService        concrete-property        private ProductCatalog $catalog — not an interface
CheckoutService        concrete-property        private InventoryChecker $inventory — not an interface
CheckoutService        concrete-property        private SendGridMailer $mailer — not an interface
CheckoutService        concrete-property        private MonologLogger $logger — not an interface
CheckoutService        new-in-constructor       new ProductCatalog() — cascades ProductCatalog's 5 deps
CheckoutService        new-in-constructor       new InventoryChecker() — cascades InventoryChecker's 2 deps
CheckoutService        new-in-constructor       new SendGridMailer("SG.abc123xyz789") — hardwired API key
──────────────────────────────────────────────────────────────────────────────────────────
Total violations documented: 14 (14 across 4 classes)

── Testability Question Answers ────────────────────

ProductCatalog:
  Instantiate without infra: NO — constructor calls PostgresDb::getInstance() and new RedisCache()
  Method without network: NO — findById() requires Redis and Postgres to be running
  Lines to switch db: 2 (property type + constructor call) — but also affects PostgresDb class

InventoryChecker:
  Instantiate without infra: NO — constructor calls PostgresDb::getInstance()
  Method without network: NO — isAvailable() requires Postgres to be running
  Lines to switch db: 1 (constructor call) — but singleton is still hardwired

CheckoutService:
  Instantiate without infra: NO — creates ProductCatalog, InventoryChecker, SendGridMailer = all their deps
  Method without network: NO — checkout() requires Postgres, Redis, and SendGrid all running
  Lines to switch mailer: 2 (property type + constructor line) + change import

CheckoutController:
  Instantiate without infra: NO — new CheckoutService() cascades the entire dependency tree
  Method without network: NO — handleCheckout() requires the entire stack

── What Lesson 3.2 Will Fix ─────────────────────────

Every `new ConcreteClass()` in a constructor will be replaced with
a constructor parameter typed against an interface.

After the fix:
  ProductCatalog(DatabaseInterface $db, CacheInterface $cache)
  InventoryChecker(DatabaseInterface $db)
  CheckoutService(CatalogInterface $catalog, InventoryInterface $inventory,
                  MailerInterface $mailer, LoggerInterface $logger)
  CheckoutController(CheckoutServiceInterface $service, LoggerInterface $logger)

Violations remaining after fix: 0
Classes testable in isolation:  4 (all of them)
Lines to switch payment provider: 1 (just the wiring)


--- Acceptance ---
  Violations annotated: XXXXX
  PASS  At least 14 coupling violations marked with ❌
ACCEPTANCE: all checks passed
```
