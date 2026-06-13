# Lesson 4.5 — Capstone: Slim PHP + PHP-DI ⭐
> **Module 4: Container Automation with PHP-DI** · PHP 8.5 OOP Mastery Course

---

## 📁 Lesson Folder Structure

```
lesson-4.5-slim-phpdi-capstone/
├── README.md                              ← Theory (you are here)
│
├── examples/
│   ├── 01-slim-basics.php                 ← Slim routing without a container
│   ├── 02-slim-with-container.php         ← Wiring PHP-DI as Slim's PSR-11 container
│   ├── 03-auto-wired-controllers.php      ← Controllers resolved from container
│   └── 04-request-response-cycle.php      ← Full request → controller → response cycle
│
├── challenge/
│   ├── CHALLENGE.md
│   ├── public/
│   │   └── index.php                      ← Entry point
│   ├── config/
│   │   ├── services.php                   ← PHP-DI definitions
│   │   └── routes.php                     ← Route definitions
│   ├── src/
│   │   ├── Contracts/
│   │   │   ├── LoggerInterface.php
│   │   │   └── MailerInterface.php
│   │   ├── Domain/
│   │   │   ├── Product/
│   │   │   │   ├── ProductRepositoryInterface.php
│   │   │   │   └── InMemoryProductRepository.php
│   │   │   └── Order/
│   │   │       ├── OrderRepositoryInterface.php
│   │   │       ├── OrderService.php
│   │   │       └── InMemoryOrderRepository.php
│   │   ├── Http/
│   │   │   ├── ProductController.php      ← auto-wired: __construct(ProductRepositoryInterface $repo)
│   │   │   └── OrderController.php        ← auto-wired: __construct(OrderService $service)
│   │   └── Infrastructure/
│   │       ├── ConsoleLogger.php
│   │       └── NullMailer.php
│   ├── tests/
│   │   └── routes.test.php                ← Request simulation tests
│   └── composer.json
└── quiz/
    └── QUIZ.md
```

---

## 0 — Prerequisites

```bash
composer require slim/slim slim/psr7 php-di/php-di
```

Verify:
```bash
php -r "
  require 'vendor/autoload.php';
  echo \Slim\Factory\AppFactory::class . PHP_EOL;
  echo \DI\ContainerBuilder::class . PHP_EOL;
"
```

---

## 1 — What Slim PHP Is

Slim is a **PSR-7/PSR-15 micro-framework**. It handles:
- HTTP routing (match a URL + method to a handler)
- PSR-7 request and response objects
- Middleware pipeline

It does NOT handle:
- Database access
- ORM
- Template rendering
- Authentication

This makes it the perfect pairing with PHP-DI: Slim handles routing, PHP-DI handles wiring.

---

## 2 — The Bootstrap Pattern

```php
// public/index.php

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// Step 1: Build the PHP-DI container
$builder = new ContainerBuilder();
$builder->addDefinitions(__DIR__ . '/../config/services.php');
$container = $builder->build();

// Step 2: Tell Slim to use PHP-DI as its container
AppFactory::setContainer($container);
$app = AppFactory::create();

// Step 3: Load route definitions
require __DIR__ . '/../config/routes.php';

// Step 4: Run
$app->run();
```

When Slim receives a request for `GET /products`, it looks up the handler in the route definitions. If the handler is a class name string, Slim calls `$container->get(ProductController::class)` — PHP-DI auto-wires the controller with all its dependencies.

---

## 3 — Route Definitions

```php
// config/routes.php

use App\Http\ProductController;
use App\Http\OrderController;

$app->get('/products',        [ProductController::class, 'index']);
$app->post('/orders',         [OrderController::class,   'store']);
$app->get('/orders/{id}',     [OrderController::class,   'show']);
```

The `[ControllerClass::class, 'methodName']` syntax tells Slim to resolve `ProductController` from the container and call its `index` method.

---

## 4 — Controller Structure

Controllers are plain PHP classes. They receive their dependencies via constructor injection — PHP-DI wires them automatically.

```php
// src/Http/ProductController.php

namespace App\Http;

use App\Domain\Product\ProductRepositoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ProductController {
    public function __construct(
        private ProductRepositoryInterface $products
    ) {}

    public function index(Request $request, Response $response): Response {
        $data = $this->products->findAll();
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
```

**Critical rule (Course Philosophy Rule 1):** Controllers never call `getenv()`, never call `$container->get()`, never create their own dependencies. They receive everything via constructor. Config belongs at the entry point.

---

## 5 — The PSR-7 Request/Response Pattern

Every Slim action method receives two arguments and returns a response:

```php
public function index(Request $request, Response $response): Response
public function store(Request $request, Response $response): Response
public function show(Request $request, Response $response, array $args): Response
```

Reading request data:
```php
// Query params: GET /products?category=electronics
$params   = $request->getQueryParams();     // ['category' => 'electronics']

// Body (POST/PUT): JSON body
$body     = json_decode((string)$request->getBody(), true);

// Route params: GET /orders/42
$id       = (int)$args['id'];
```

Writing responses:
```php
// JSON response
$response->getBody()->write(json_encode(['data' => $result]));
return $response->withHeader('Content-Type', 'application/json')
                ->withStatus(200);

// 404 not found
return $response->withStatus(404);

// 201 created
return $response->withStatus(201);
```

---

## 6 — The Config vs Core Separation (Rule 1)

```
config/services.php   ← ALL of:
  - Interface → concrete bindings
  - getenv() calls
  - DSNs, API keys, env-based decisions
  - Factory definitions for primitive-param classes

src/                  ← NONE of:
  - getenv()
  - new SomeInfrastructureClass()
  - container->get()
  - Any reference to which concrete class implements what
```

The services that live in `src/` only know about interfaces. They are completely portable across environments — the same `OrderService` runs in development (with `InMemoryOrderRepository`) and production (with `MySQLOrderRepository`). Only `config/services.php` changes.

---

## 7 — The Three API Routes

| Route | Controller method | What it does |
|-------|-------------------|--------------|
| `GET /products` | `ProductController::index` | Returns all products as JSON |
| `POST /orders` | `OrderController::store` | Creates an order from JSON body, returns 201 |
| `GET /orders/{id}` | `OrderController::show` | Returns one order by ID, 404 if not found |

---

## 8 — Request Simulation for Testing

To test routes without a running web server, Slim's PSR-7 implementation provides `ServerRequestFactory`:

```php
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;

// Simulate GET /products
$request  = (new ServerRequestFactory())->createServerRequest('GET', '/products');
$response = $app->handle($request);

echo $response->getStatusCode();     // 200
echo (string)$response->getBody();   // [{"id":1,...}]
```

This is a lightweight alternative to spinning up a real HTTP server — the full Slim middleware and routing pipeline runs, but no network I/O occurs.

---

## ✅ Lesson Checklist

- [ ] Run `composer require slim/slim slim/psr7 php-di/php-di`
- [ ] Read this README fully — especially Sections 2 (bootstrap), 4 (controllers), and 6 (Config vs Core)
- [ ] Run and study `examples/01-slim-basics.php`
- [ ] Run and study `examples/02-slim-with-container.php`
- [ ] Run and study `examples/03-auto-wired-controllers.php`
- [ ] Run and study `examples/04-request-response-cycle.php`
- [ ] Read `challenge/CHALLENGE.md` and build the three-route API
- [ ] Run the route tests in `challenge/tests/routes.test.php`
- [ ] Complete `quiz/QUIZ.md` without looking at any files

---

*Module 4 complete. Next: **Module 5 — Automated Testing & TDD** — prove that your composed, injected, container-wired code actually works correctly.*