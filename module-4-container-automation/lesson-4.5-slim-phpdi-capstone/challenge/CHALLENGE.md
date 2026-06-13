# Code Challenge — Lesson 4.5: Slim PHP + PHP-DI Capstone ⭐

> **Build a three-route HTTP API with auto-wired controllers, a proper definitions file, and request simulation tests**

---

## The Brief

This is the Module 4 capstone. You will build a complete Slim PHP API that wires all your controller dependencies using PHP-DI. Every file in the `src/` folder must follow Course Philosophy Rule 1 — zero `getenv()` calls, zero `$container->get()` calls, zero manual `new` on infrastructure classes.

---

## Prerequisites

```bash
composer require slim/slim slim/psr7 php-di/php-di
```

---

## Folder Structure (already created)

```
challenge/
├── public/
│   └── index.php              ← Entry point — your composition root
├── config/
│   ├── services.php           ← PHP-DI definitions (ALL config lives here)
│   └── routes.php             ← Route definitions only
├── src/
│   ├── Http/
│   │   ├── ProductController.php
│   │   └── OrderController.php
│   └── Domain/
│       ├── Product/
│       │   ├── ProductRepositoryInterface.php
│       │   └── InMemoryProductRepository.php
│       └── Order/
│           ├── OrderRepositoryInterface.php
│           ├── OrderService.php
│           └── InMemoryOrderRepository.php
└── tests/
    └── routes.test.php        ← Request simulation tests
```

---

## Your Tasks

### Task 1 — Domain layer (`src/Domain/`)

**`Product/ProductRepositoryInterface.php`**
```php
interface ProductRepositoryInterface {
    public function findAll(): array;
    public function findById(int $id): ?array;
}
```

**`Product/InMemoryProductRepository.php`**
Seed data: two products (Widget Pro R299.99, Widget Lite R149.99). Implements `ProductRepositoryInterface`.

**`Order/OrderRepositoryInterface.php`**
```php
interface OrderRepositoryInterface {
    public function create(array $data): array;   // returns the created order with generated id
    public function findById(int $id): ?array;
}
```

**`Order/InMemoryOrderRepository.php`**
In-memory store, auto-incrementing IDs. Implements `OrderRepositoryInterface`.

**`Order/OrderService.php`**
Constructor injection: `ProductRepositoryInterface`, `OrderRepositoryInterface`, `MailerInterface`, `LoggerInterface`.
Methods:
- `place(int $productId, int $qty, string $email): array` — validates, creates order, sends confirmation mail, logs
- `findById(int $id): ?array`

Throw `\InvalidArgumentException` for: product not found, quantity < 1, invalid email.

### Task 2 — HTTP layer (`src/Http/`)

**`ProductController.php`**
Constructor: `ProductRepositoryInterface $products`, `LoggerInterface $logger`
Methods:
- `index(Request $request, Response $response): Response` → `GET /products`, returns all products, supports `?min_price=N` query filter
- `show(Request $request, Response $response, array $args): Response` → `GET /products/{id}`, 404 if not found

**`OrderController.php`**
Constructor: `OrderService $service`, `LoggerInterface $logger`
Methods:
- `store(Request $request, Response $response): Response` → `POST /orders`, reads JSON body, 422 on validation error, 201 on success
- `show(Request $request, Response $response, array $args): Response` → `GET /orders/{id}`, 404 if not found

All responses must use this JSON envelope:
```json
{ "success": true,  "data":  { ... } }
{ "success": false, "error": "..." }
```

### Task 3 — Config (`config/services.php`)

Return a PHP-DI definitions array. Bind:
- `ProductRepositoryInterface::class` → `InMemoryProductRepository::class`
- `OrderRepositoryInterface::class` → `InMemoryOrderRepository::class`
- `LoggerInterface::class` → your logger implementation
- `MailerInterface::class` → your mailer implementation

Use `autowire()` for all four. Controllers and `OrderService` do not need explicit entries — they are auto-wired.

### Task 4 — Routes (`config/routes.php`)

Register four routes using `[ControllerClass::class, 'method']` syntax:
```php
$app->get('/products',      [ProductController::class, 'index']);
$app->get('/products/{id}', [ProductController::class, 'show']);
$app->post('/orders',       [OrderController::class,   'store']);
$app->get('/orders/{id}',   [OrderController::class,   'show']);
```

### Task 5 — Entry point (`public/index.php`)

```php
$builder = new \DI\ContainerBuilder();
$builder->addDefinitions(__DIR__ . '/../config/services.php');
$container = $builder->build();

\Slim\Factory\AppFactory::setContainer($container);
$app = \Slim\Factory\AppFactory::create();

require __DIR__ . '/../config/routes.php';
$app->run();
```

### Task 6 — Tests (`tests/routes.test.php`)

Write request simulation tests for all four routes. Each test must assert:
- The correct HTTP status code
- The `success` field in the response body
- At least one field of the `data` payload

Minimum tests required:
1. `GET /products` → 200, `success=true`, at least 2 products
2. `GET /products/1` → 200, `success=true`, product name matches
3. `GET /products/99` → 404, `success=false`
4. `POST /orders` (valid) → 201, `success=true`, `data.order_id` exists
5. `POST /orders` (missing email) → 422, `success=false`
6. `GET /orders/1` → 200 (after a successful POST), `success=true`
7. `GET /orders/99` → 404, `success=false`

---

## Acceptance Criteria

- [ ] All four routes respond correctly
- [ ] `src/` files contain zero `getenv()` calls
- [ ] `src/` files contain zero `$container->get()` calls
- [ ] `src/` files contain zero `new ConcreteInfrastructureClass()` calls
- [ ] All interface bindings are in `config/services.php` only
- [ ] Response envelope is consistent: `{success, data}` or `{success, error}`
- [ ] All 7 tests pass (printed with `✓`)
- [ ] `GET /products?min_price=20000` filters correctly

---

## Running the Tests

```bash
php challenge/tests/routes.test.php
```

All tests print `✓` or `✗`. No PHPUnit needed — pure request simulation.