# Quiz — Lesson 4.5: Capstone — Slim PHP + PHP-DI
> Complete this quiz **without** looking at any example or solution files.
> Write your answers before checking the answer key at the bottom.
> Any question you get wrong is a reading target.

---

## Section A — Multiple Choice

**Q1.** What does `AppFactory::setContainer($container)` do in a Slim application?

- A) It replaces Slim's router with PHP-DI's dependency graph.
- B) It tells Slim to use the provided PSR-11 container when resolving route handler classes — so `$container->get(ProductController::class)` is called automatically.
- C) It registers all container bindings as Slim routes.
- D) It prevents Slim from creating its own internal container.

---

**Q2.** A route is defined as `$app->get('/products', [ProductController::class, 'index'])`. When `GET /products` is received, what does Slim do with `ProductController::class`?

- A) Slim calls `new ProductController()` directly.
- B) Slim calls `$container->get(ProductController::class)`, which PHP-DI auto-wires with all constructor dependencies.
- C) Slim searches all registered services for a class named `ProductController`.
- D) Slim calls `ProductController::index()` statically.

---

**Q3.** Which two files together form the composition root in a Slim + PHP-DI application?

- A) `src/Http/ProductController.php` and `src/Domain/Order/OrderService.php`
- B) `public/index.php` and `config/services.php`
- C) `config/routes.php` and `src/Http/OrderController.php`
- D) `composer.json` and `config/services.php`

---

**Q4.** A `ProductController` action method signature is `public function index(Request $request, Response $response): Response`. Where do `$request` and `$response` come from?

- A) They are injected by PHP-DI via the constructor.
- B) They are provided by Slim for every route handler call — not injected via constructor.
- C) They must be declared as constructor parameters alongside other dependencies.
- D) They are global variables provided by the PSR-7 layer.

---

**Q5.** According to Course Philosophy Rule 1, which file should be the ONLY file in the application that calls `getenv()`?

- A) `public/index.php`
- B) `src/Infrastructure/ConsoleLogger.php`
- C) `config/services.php`
- D) `src/Http/ProductController.php`

---

**Q6.** How does Slim's request simulation (using `$app->handle($request)`) differ from a real HTTP request in terms of testing value?

- A) It only tests the routing layer — controllers are not invoked.
- B) It runs the full Slim middleware pipeline, routing, and controller logic without a web server — providing accurate HTTP behaviour testing with no network I/O.
- C) It skips middleware and calls controllers directly.
- D) It requires a running web server — Slim cannot handle requests programmatically.

---

**Q7.** A controller reads `$args['id']` where `$args` is the third parameter. What is `$args` and where does it come from?

- A) It is the PHP-DI container — injected automatically.
- B) It is an array of route placeholder values (e.g. `['id' => '42']` for `/orders/42`) — provided by Slim for routes with `{id}` placeholders.
- C) It is the JSON body of the request.
- D) It is a query parameter array — the same as `$request->getQueryParams()`.

---

**Q8.** What is the correct way to add a second set of definitions to override existing ones (e.g. for testing)?

- A) Call `$builder->addDefinitions()` twice — the second call's definitions take precedence for overlapping keys.
- B) Modify `config/services.php` before building the container.
- C) Call `$container->override()` after building.
- D) PHP-DI does not support multiple definition sets in one container.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 9  | `config/routes.php` should contain business logic for validating request bodies. | |
| 10 | A controller class that calls `$this->container->get(OrderService::class)` inside a method violates Course Philosophy Rule 1. | |
| 11 | Slim's `[ControllerClass::class, 'method']` route syntax requires the controller to be registered explicitly in `config/services.php`. | |
| 12 | Adding `$app->addErrorMiddleware(false, false, false)` makes Slim return JSON error responses instead of HTML error pages. | |
| 13 | A test that asserts `$response->getStatusCode() === 201` is testing observable HTTP behaviour, not internal implementation layout. | |
| 14 | To swap from `InMemoryProductRepository` to `MySQLProductRepository`, you must modify `ProductController.php`. | |

---

## Section C — Short Answer

**Q15.** Explain why `public/index.php` is called the "composition root" and what would go wrong if a controller class also acted as a composition root by building its own container.

*Your answer:*

---

**Q16.** A colleague argues that `config/routes.php` could be merged into `public/index.php` since both are entry-point files. Give one reason to keep them separate.

*Your answer:*

---

**Q17.** Describe how you would wire a `LoggingProductRepository` decorator in `config/services.php` — a class that wraps `InMemoryProductRepository` to add logging before every `findAll()` call. Show the factory definition.

*Your answer:*

---

## Section D — Code Reading

**Q18.** What HTTP status code and response body will the following action return, and why?

```php
public function show(Request $request, Response $response, array $args): Response {
    $id      = (int) $args['id'];
    $product = $this->products->findById($id);

    if ($product === null) {
        $response->getBody()->write(json_encode(['success' => false, 'error' => "Not found"]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    $response->getBody()->write(json_encode(['success' => true, 'data' => $product]));
    return $response->withHeader('Content-Type', 'application/json');
}
```

Called with `GET /products/99` where product 99 does not exist.

*Your answer:*

---

**Q19.** The following test helper makes a POST request. What is wrong with it, and what will happen at runtime?

```php
function postJson(string $uri, array $data, \Slim\App $app): array {
    $request  = (new ServerRequestFactory())->createServerRequest('POST', $uri);
    $request  = $request->withBody(
        (new StreamFactory())->createStream(json_encode($data))
    );
    // Missing: ->withHeader('Content-Type', 'application/json')
    $response = $app->handle($request);
    return ['status' => $response->getStatusCode(), 'body' => json_decode((string)$response->getBody(), true)];
}
```

The controller reads: `$body = json_decode((string) $request->getBody(), true) ?? [];`

*Your answer:*

---

**Q20.** Trace exactly what happens when `GET /orders/1` is handled, starting from `$app->handle($request)`. Assume the order was previously created via `POST /orders`.

```
$app->handle($request for GET /orders/1)
→ ...
```

Write the resolution chain from routing through to the JSON response.

*Your answer:*

---

---

# ✅ Answer Key
*(Scroll only after completing all questions)*

&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;

---

## Section A
| Q | Answer | Explanation |
|---|--------|-------------|
| 1 | **B** | `setContainer()` tells Slim to use the provided PSR-11 container for resolving class-string route handlers. |
| 2 | **B** | Slim calls `$container->get(ProductController::class)` — PHP-DI auto-wires it with its constructor dependencies. |
| 3 | **B** | `public/index.php` boots the container and app. `config/services.php` declares all bindings. Together they are the composition root. |
| 4 | **B** | `$request` and `$response` are provided by Slim for every route invocation — they are not constructor dependencies. |
| 5 | **C** | `config/services.php` is the definitions file — the only place `getenv()` should appear (Rule 1). |
| 6 | **B** | `$app->handle($request)` runs the full Slim pipeline — middleware, routing, controller — without a web server. This is the most accurate testing approach short of an actual HTTP call. |
| 7 | **B** | `$args` is an associative array of route placeholder values — `['id' => '42']` for a route defined with `{id}`. |
| 8 | **A** | `addDefinitions()` can be called multiple times. Later calls override earlier ones for duplicate keys — the standard approach for test definitions. |

## Section B
| # | Answer | Explanation |
|---|--------|-------------|
| 9  | **F** | `config/routes.php` only registers routes. Business logic belongs in service classes; request validation belongs in controllers. |
| 10 | **T** | A controller calling `$container->get()` internally is the Service Locator anti-pattern — violates Rule 1 (config at entry point). |
| 11 | **F** | PHP-DI auto-wires concrete classes and classes with only interface-typed constructor params. `ProductController` needs no explicit entry in `services.php` as long as its dependencies are bound. |
| 12 | **F** | `addErrorMiddleware(false, false, false)` adds error handling middleware but does not automatically convert errors to JSON. For JSON errors you need a custom error handler or the Slim error middleware still returns its default format. The first `false` means "don't display error details" — format depends on configuration. |
| 13 | **T** | Asserting on the HTTP status code is testing observable behaviour — what the API returns, not how it achieves it internally. |
| 14 | **F** | `ProductController` depends on `ProductRepositoryInterface` — an abstraction. Changing the concrete implementation in `config/services.php` requires zero changes to `ProductController`. This is DIP in action. |

## Section C

**Q15 — Model answer:**
`public/index.php` is the composition root because it is the single entry point where the container is built, bindings are loaded, and the application is assembled — all from one place. If a controller also built its own container (`new ContainerBuilder()->build()`), it would create a second, separate container with none of the application's bindings, potentially resolving completely different implementations. More fundamentally, it would make the controller impossible to test in isolation — you could not inject test doubles without first reproducing all the container setup. Composition roots must be singular; having more than one is the Service Locator anti-pattern applied at the bootstrap level.

**Q16 — Model answer:**
Keeping `config/routes.php` separate from `public/index.php` lets you test the routes file independently — you can require it in a test bootstrap that builds a test container, without also running `$app->run()`. It also keeps the entry point clean: `index.php` handles infrastructure (container, middleware, run), while `routes.php` handles domain concerns (which URLs map to which handlers). As the API grows to dozens of routes, grouping and modularising routes becomes much easier in a separate file.

**Q17 — Model answer:**
```php
// config/services.php
ProductRepositoryInterface::class => factory(function (\Psr\Container\ContainerInterface $c) {
    return new LoggingProductRepository(
        new InMemoryProductRepository(),   // the real repository
        $c->get(LoggerInterface::class)    // resolved from the container
    );
}),
```
`LoggingProductRepository` implements `ProductRepositoryInterface` and wraps `InMemoryProductRepository`. The factory receives the container so it can resolve `LoggerInterface`. Controllers still type-hint `ProductRepositoryInterface` — they are unaware of the decorator.

## Section D

**Q18 — Answer:**
Status: `404`. Body: `{"success": false, "error": "Not found"}`.
`$args['id']` is `'99'` (string from route match) — `(int)'99'` = `99`. `findById(99)` returns `null` (product doesn't exist). The `if ($product === null)` branch executes, writing the error JSON and returning with `->withStatus(404)`. The `200` branch (no explicit `withStatus`) is never reached. Note: Slim's default `withStatus` when none is set is `200`, but this path is not taken here.

**Q19 — Answer:**
The bug is the missing `->withHeader('Content-Type', 'application/json')`. At runtime, the request body contains valid JSON, and `json_decode((string) $request->getBody(), true)` will successfully decode it — because `json_decode` does not check the `Content-Type` header, it just reads the raw string. So in this specific case the test will still work correctly. However, the missing header is still wrong because: (1) it misrepresents the content type to any middleware that inspects headers; (2) it does not match what a real API client would send; and (3) if Slim middleware ever validates `Content-Type` for `POST` requests, the test would silently break. The fix is to add `->withHeader('Content-Type', 'application/json')` before `$app->handle()`.

**Q20 — Answer:**
```
$app->handle(GET /orders/1)
  → Slim middleware pipeline runs
  → Router matches: GET /orders/{id} → [OrderController::class, 'show']
  → Slim calls $container->get(OrderController::class)
    → PHP-DI reflects OrderController::__construct(OrderService $service, LoggerInterface $logger)
    → Resolves OrderService (cache hit — singleton from POST /orders call)
    → Resolves LoggerInterface → ConsoleLogger (cache hit)
    → Returns cached OrderController instance (singleton)
  → Slim calls $controller->show($request, $response, ['id' => '1'])
    → $id = (int)'1' = 1
    → $this->logger->log('INFO', 'GET /orders/1')
    → $this->service->findById(1)
      → $this->orders->findById(1)
        → Searches InMemoryOrderRepository::$orders
        → Returns the order created during POST /orders
    → $order is not null → success branch
    → $response->getBody()->write(json_encode(['success' => true, 'data' => $order]))
    → Returns response with Content-Type: application/json, status 200
```

---

## Score Guide

| Score | Verdict |
|-------|---------|
| 18–20 | Module 4 complete — ready for Module 5 (Testing & TDD). |
| 14–17 | Re-read the README sections for any missed questions, then move on. |
| Below 14 | Re-run the examples, redo the challenge, then retake the quiz. |