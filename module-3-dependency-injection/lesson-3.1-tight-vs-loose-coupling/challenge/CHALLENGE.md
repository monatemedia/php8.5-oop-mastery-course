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