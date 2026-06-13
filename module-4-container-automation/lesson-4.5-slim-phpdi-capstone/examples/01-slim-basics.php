<?php
declare(strict_types=1);

/**
 * Example 01 — Slim Basics: Routing Without a Container
 * -------------------------------------------------------
 * Before wiring PHP-DI, understand what Slim does on its own.
 * Slim is a micro-framework: it handles routing and the PSR-7
 * request/response cycle. Nothing else.
 *
 * This example shows:
 *   A. Creating a Slim app and defining routes
 *   B. Reading request data (query params, body, route params)
 *   C. Writing JSON responses with status codes
 *   D. Running the app and simulating requests
 *
 * Requires: composer require slim/slim slim/psr7
 *
 * Run from project root:
 *   php module-4-container-automation/lesson-4.5-slim-phpdi-capstone/examples/01-slim-basics.php
 */

require __DIR__ . '/../../../../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Slim Basics — Routing Without a Container          ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Inline data (no database — keeping this example self-contained)
// ─────────────────────────────────────────────────────────────────────────────

$products = [
    1 => ['id' => 1, 'name' => 'Widget Pro',  'price' => 29999, 'sku' => 'WDG-001'],
    2 => ['id' => 2, 'name' => 'Widget Lite', 'price' => 14999, 'sku' => 'WDG-002'],
];

$orders = [];


// ─────────────────────────────────────────────────────────────────────────────
// Create the Slim app
// ─────────────────────────────────────────────────────────────────────────────

$app = AppFactory::create();


// ═══════════════════════════════════════════════════════════
// PART A — Define routes (closures, simplest form)
// ═══════════════════════════════════════════════════════════

// GET /products — list all products
$app->get('/products', function (Request $request, Response $response) use ($products): Response {
    $response->getBody()->write(json_encode(array_values($products)));
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
});

// GET /products/{id} — get one product
$app->get('/products/{id}', function (Request $request, Response $response, array $args) use ($products): Response {
    $id      = (int) $args['id'];
    $product = $products[$id] ?? null;

    if ($product === null) {
        $response->getBody()->write(json_encode(['error' => "Product #{$id} not found"]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    $response->getBody()->write(json_encode($product));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});

// POST /orders — create an order from JSON body
$app->post('/orders', function (Request $request, Response $response) use ($products, &$orders): Response {
    $body      = json_decode((string) $request->getBody(), true) ?? [];
    $productId = (int) ($body['product_id'] ?? 0);
    $quantity  = (int) ($body['quantity']   ?? 1);
    $email     = $body['email'] ?? '';

    if (!isset($products[$productId])) {
        $response->getBody()->write(json_encode(['error' => 'Product not found']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
    }

    if (empty($email)) {
        $response->getBody()->write(json_encode(['error' => 'Email is required']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
    }

    $orderId  = count($orders) + 1;
    $orders[] = [
        'id'         => $orderId,
        'product_id' => $productId,
        'quantity'   => $quantity,
        'email'      => $email,
        'total'      => $products[$productId]['price'] * $quantity,
    ];

    $response->getBody()->write(json_encode(['order_id' => $orderId, 'status' => 'created']));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
});

// GET /orders/{id} — get one order
$app->get('/orders/{id}', function (Request $request, Response $response, array $args) use (&$orders): Response {
    $id    = (int) $args['id'];
    $order = $orders[$id - 1] ?? null;

    if ($order === null) {
        $response->getBody()->write(json_encode(['error' => "Order #{$id} not found"]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    $response->getBody()->write(json_encode($order));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});


// ═══════════════════════════════════════════════════════════
// PART B — Simulate requests (no web server needed)
// ═══════════════════════════════════════════════════════════

echo "── Part B: Request simulation ───────────────────────\n\n";

$factory = new ServerRequestFactory();

function simulate(string $method, string $uri, \Slim\App $app,
                  ?string $body = null, array $headers = []): void {
    $factory = new ServerRequestFactory();
    $request = $factory->createServerRequest($method, $uri);

    if ($body !== null) {
        $stream = (new \Slim\Psr7\Factory\StreamFactory())->createStream($body);
        $request = $request->withBody($stream)
                           ->withHeader('Content-Type', 'application/json');
    }

    foreach ($headers as $name => $value) {
        $request = $request->withHeader($name, $value);
    }

    $response = $app->handle($request);
    $status   = $response->getStatusCode();
    $data     = json_decode((string) $response->getBody(), true);

    echo "  {$method} {$uri}\n";
    echo "  Status: {$status}\n";
    echo "  Body:   " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
}

echo "1. List all products:\n";
simulate('GET', '/products', $app);

echo "2. Get product #1:\n";
simulate('GET', '/products/1', $app);

echo "3. Get product #99 (not found):\n";
simulate('GET', '/products/99', $app);

echo "4. Create an order:\n";
simulate('POST', '/orders', $app, json_encode([
    'product_id' => 1,
    'quantity'   => 2,
    'email'      => 'alice@example.com',
]));

echo "5. Create order — missing email (validation error):\n";
simulate('POST', '/orders', $app, json_encode([
    'product_id' => 1,
    'quantity'   => 1,
]));

echo "6. Get order #1:\n";
simulate('GET', '/orders/1', $app);

echo "7. Get order #99 (not found):\n";
simulate('GET', '/orders/99', $app);


// ═══════════════════════════════════════════════════════════
// PART C — What Slim is and is not
// ═══════════════════════════════════════════════════════════

echo "── Part C: What Slim is and is not ──────────────────\n\n";
echo "Slim IS:\n";
echo "  ✓ HTTP router — matches URL + method to a handler\n";
echo "  ✓ PSR-7 request/response objects\n";
echo "  ✓ Middleware pipeline\n";
echo "  ✓ PSR-11 container bridge (next example)\n\n";
echo "Slim is NOT:\n";
echo "  ✗ An ORM or database layer\n";
echo "  ✗ A template engine\n";
echo "  ✗ An authentication system\n";
echo "  ✗ A full-stack framework\n\n";
echo "The closures above embed business logic directly in routes.\n";
echo "Example 02 extracts that logic into controller classes wired by PHP-DI.\n";