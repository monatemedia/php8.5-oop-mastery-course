# Code Challenge — Lesson 5.4: Integration Testing with a Real Container

> **Write integration tests for the Slim API from Lesson 4.5 using SQLite in-memory**

---

## The Brief

The Lesson 4.5 capstone built a Slim + PHP-DI API for orders and products. Unit tests were written with fakes. Now you will write **integration tests** that boot the real container, use a real SQLite in-memory database, and simulate real HTTP requests.

This challenge verifies three things:
1. The container wires all interfaces correctly
2. The real repositories work with real SQL
3. The routes return the right HTTP responses for every path

---

## Prerequisites

From Lesson 4.5, these classes must exist in your project (or equivalents):

```
src/
  Contracts/
    LoggerInterface.php
    MailerInterface.php
  Domain/
    Order/
      OrderRepositoryInterface.php
      OrderService.php
      SqliteOrderRepository.php       ← you will need to create this
    Product/
      ProductRepositoryInterface.php
      SqliteProductRepository.php     ← you will need to create this
  Http/
    OrderController.php
    ProductController.php
  Infrastructure/
    NullLogger.php
    NullMailer.php
config/
  routes.php
  services.php
```

**Important:** The Lesson 4.5 capstone used `InMemoryOrderRepository` and `InMemoryProductRepository`. For this lesson you need **SQLite-backed repositories**. The starter file includes the schemas and repository skeletons you need.

---

## SQLite Schemas

```sql
-- Products
CREATE TABLE products (
    id    INTEGER PRIMARY KEY AUTOINCREMENT,
    name  TEXT    NOT NULL,
    price INTEGER NOT NULL,
    sku   TEXT    NOT NULL UNIQUE
)

-- Orders
CREATE TABLE orders (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_email TEXT    NOT NULL,
    status         TEXT    NOT NULL DEFAULT 'pending',
    product_id     INTEGER NOT NULL REFERENCES products(id),
    qty            INTEGER NOT NULL DEFAULT 1,
    total_cents    INTEGER NOT NULL,
    created_at     TEXT    NOT NULL
)
```

---

## Your Tasks

Work in `starter/ApiIntegrationTest.php`. Complete each task group in order.

---

### Task 1 — Container wiring tests

Verify the container resolves the correct concrete classes:
- `ProductRepositoryInterface` resolves to `SqliteProductRepository`
- `LoggerInterface` resolves to `NullLogger`
- `ProductController` resolves without error (all deps wired)
- `OrderController` resolves without error

### Task 2 — `GET /products` route

- Returns `200` with an empty JSON array when no products exist
- Returns `200` with all seeded products when the database has rows
- Returns `application/json` Content-Type header
- Each product in the array has `id`, `name`, `price`, `sku` keys

### Task 3 — `GET /products/{id}` route

- Returns `200` with the product when it exists
- Returns `404` when the product does not exist
- `404` body contains an `error` key

### Task 4 — `POST /products` route

- Returns `201` with the created product on valid input
- Created product has a non-null integer `id`
- Returns `422` when `name` is missing
- Returns `422` when `price` is zero or negative
- Returns `422` when `sku` is missing
- The created product is retrievable via `GET /products/{id}`

### Task 5 — `GET /orders` route

- Returns `200` with an empty JSON array when no orders exist
- Returns `200` with all seeded orders

### Task 6 — `POST /orders` route

- Returns `201` with the created order on valid input (valid `product_id`, `qty`, `customer_email`)
- Returns `404` when `product_id` does not exist
- Returns `422` when required fields are missing
- Created order has `total_cents` equal to `product.price × qty`

### Task 7 — Database state assertions

For at least one write operation, assert on the database directly (bypass the service and controller):
- After `POST /products`, verify the row exists in the `products` table with correct values
- After `POST /orders`, verify the row exists in the `orders` table

---

## Acceptance Criteria

- [ ] `setUp()` creates a fresh `PDO('sqlite::memory:')` and runs both schema migrations
- [ ] `setUp()` builds a PHP-DI container with the test PDO injected
- [ ] `setUp()` boots a Slim app with real routes registered
- [ ] Seed helpers (`seedProduct`, `seedOrder`) are present
- [ ] All four route groups have at least 2 tests each
- [ ] At least one test asserts on the database directly (not just the HTTP response)
- [ ] All tests pass: `./vendor/bin/phpunit`

---

## Running Your Tests

```bash
./vendor/bin/phpunit module-5-testing-and-tdd/lesson-5.4-integration-testing/challenge/starter/ApiIntegrationTest.php

# Verbose
./vendor/bin/phpunit --testdox module-5-testing-and-tdd/lesson-5.4-integration-testing/challenge/starter/ApiIntegrationTest.php
```

---

## Expected Output

```
ApiIntegration
 ✔ Container resolves product repository interface to sqlite class
 ✔ Container resolves logger interface to null logger
 ✔ Container resolves product controller without error
 ✔ Get products returns 200 with empty array
 ✔ Get products returns all seeded products
 ✔ Get products has json content type header
 ✔ Get product by id returns 200 with product
 ✔ Get product by id returns 404 for unknown id
 ✔ Post product returns 201 with created product
 ✔ Post product returns 422 when name is missing
 ✔ Post product returns 422 when price is zero
 ✔ Post product persists to database
 ✔ Created product is retrievable via get route
 ✔ Get orders returns 200 with empty array
 ✔ Post order returns 201 with correct total cents
 ✔ Post order returns 404 when product not found
 ✔ Post order returns 422 when customer email is missing
 ✔ Post order persists to database

OK (18 tests, N assertions)
```