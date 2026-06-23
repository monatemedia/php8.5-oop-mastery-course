<?php
declare(strict_types=1);

/**
 * CHALLENGE SOLUTION — Lesson 5.4: Integration Testing with a Real Container
 * ───────────────────────────────────────────────────────────────────────────
 * ⚠️  Only open this file after completing starter/ApiIntegrationTest.php yourself.
 *
 * Key things to compare with your solution:
 *   1. setUp() creates a fresh PDO, runs both schema migrations, builds the
 *      container, boots Slim, and registers ALL routes — every test starts clean
 *   2. Seed helpers use raw PDO (not the repository under test)
 *   3. Container wiring tests just resolve classes and assert the type
 *   4. HTTP tests assert status code, body keys, and header — in that order
 *   5. Database state tests bypass the HTTP layer entirely and assert on SQL
 */

use PHPUnit\Framework\TestCase;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// ─────────────────────────────────────────────────────────────────────────────
// All contracts, repositories, services, and controllers are declared in the
// starter file. In a real project these come from src/ via autoloader.
// This solution re-declares the same set for self-contained execution.
// ─────────────────────────────────────────────────────────────────────────────

// (Same declarations as starter/ApiIntegrationTest.php — omitted here for
//  brevity; the test class below assumes all classes are already defined above.)

// ─────────────────────────────────────────────────────────────────────────────
// Helper function
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('makeJsonRequest')) {
    function makeJsonRequest(string $method, string $uri, array $body = []): \Slim\Psr7\Request
    {
        $stream = (new StreamFactory())->createStream(json_encode($body));
        return (new ServerRequestFactory())
            ->createServerRequest($method, $uri)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($stream)
            ->withParsedBody($body);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Solution test class
// ─────────────────────────────────────────────────────────────────────────────

class ApiIntegrationTest extends TestCase
{
    private \PDO          $pdo;
    private \Slim\App     $app;
    private \DI\Container $container;

    // ─────────────────────────────────────────────────────────────────────────
    // setUp — everything fresh before every test
    // ─────────────────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        // ── 1. Fresh in-memory SQLite database ────────────────────────────────
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE,            \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        $this->pdo->exec('
            CREATE TABLE products (
                id    INTEGER PRIMARY KEY AUTOINCREMENT,
                name  TEXT    NOT NULL,
                price INTEGER NOT NULL,
                sku   TEXT    NOT NULL UNIQUE
            )
        ');

        $this->pdo->exec('
            CREATE TABLE orders (
                id             INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_email TEXT    NOT NULL,
                status         TEXT    NOT NULL DEFAULT \'pending\',
                product_id     INTEGER NOT NULL REFERENCES products(id),
                qty            INTEGER NOT NULL DEFAULT 1,
                total_cents    INTEGER NOT NULL,
                created_at     TEXT    NOT NULL
            )
        ');

        // ── 2. PHP-DI container with test PDO injected ────────────────────────
        $testPdo = $this->pdo;

        $this->container = (new \DI\ContainerBuilder())
            ->addDefinitions([
                \PDO::class => $testPdo,

                ProductRepositoryInterface::class =>
                    \DI\autowire(SqliteProductRepository::class),

                OrderRepositoryInterface::class =>
                    \DI\autowire(SqliteOrderRepository::class),

                LoggerInterface::class =>
                    \DI\autowire(NullLogger::class),

                MailerInterface::class =>
                    \DI\autowire(NullMailer::class),
            ])
            ->build();

        // ── 3. Slim app with all routes ───────────────────────────────────────
        $this->app = AppFactory::createFromContainer($this->container);

        $this->app->get('/products',                    [ProductController::class, 'list']);
        $this->app->get('/products/{id:[0-9]+}',        [ProductController::class, 'show']);
        $this->app->post('/products',                   [ProductController::class, 'create']);
        $this->app->get('/orders',                      [OrderController::class,   'list']);
        $this->app->post('/orders',                     [OrderController::class,   'create']);

        $this->app->addErrorMiddleware(false, false, false);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Seed helpers — raw PDO, bypass the classes under test
    // ─────────────────────────────────────────────────────────────────────────

    private function seedProduct(string $name, int $price, string $sku): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO products (name, price, sku) VALUES (?, ?, ?)');
        $stmt->execute([$name, $price, $sku]);
        return (int) $this->pdo->lastInsertId();
    }

    private function seedOrder(int $productId, int $qty, string $email, int $totalCents): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO orders (customer_email, status, product_id, qty, total_cents, created_at)
             VALUES (?, \'pending\', ?, ?, ?, ?)'
        );
        $stmt->execute([$email, $productId, $qty, $totalCents, '2026-01-01 10:00:00']);
        return (int) $this->pdo->lastInsertId();
    }

    private function decodeBody(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true) ?? [];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Task 1 — Container wiring
    // ─────────────────────────────────────────────────────────────────────────

    public function testContainerResolvesProductRepositoryToSqliteClass(): void
    {
        $repo = $this->container->get(ProductRepositoryInterface::class);
        $this->assertInstanceOf(SqliteProductRepository::class, $repo);
    }

    public function testContainerResolvesOrderRepositoryToSqliteClass(): void
    {
        $repo = $this->container->get(OrderRepositoryInterface::class);
        $this->assertInstanceOf(SqliteOrderRepository::class, $repo);
    }

    public function testContainerResolvesLoggerToNullLogger(): void
    {
        $logger = $this->container->get(LoggerInterface::class);
        $this->assertInstanceOf(NullLogger::class, $logger);
    }

    public function testContainerResolvesProductControllerWithoutError(): void
    {
        $controller = $this->container->get(ProductController::class);
        $this->assertInstanceOf(ProductController::class, $controller);
    }

    public function testContainerResolvesOrderControllerWithoutError(): void
    {
        $controller = $this->container->get(OrderController::class);
        $this->assertInstanceOf(OrderController::class, $controller);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Task 2 — GET /products
    // ─────────────────────────────────────────────────────────────────────────

    public function testGetProductsReturns200WithEmptyArray(): void
    {
        $response = $this->app->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/products')
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], $this->decodeBody($response));
    }

    public function testGetProductsReturnsAllSeededProducts(): void
    {
        $this->seedProduct('Widget Pro',  29999, 'WDG-001');
        $this->seedProduct('Widget Lite', 14999, 'WDG-002');

        $response = $this->app->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/products')
        );

        $body = $this->decodeBody($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(2, $body);
        $this->assertSame('Widget Pro',  $body[0]['name']);
        $this->assertSame('Widget Lite', $body[1]['name']);
    }

    public function testGetProductsHasJsonContentTypeHeader(): void
    {
        $response = $this->app->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/products')
        );

        $this->assertStringContainsString(
            'application/json',
            $response->getHeaderLine('Content-Type')
        );
    }

    public function testGetProductsBodyItemsHaveRequiredKeys(): void
    {
        $this->seedProduct('Widget Pro', 29999, 'WDG-001');

        $response = $this->app->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/products')
        );

        $item = $this->decodeBody($response)[0];

        $this->assertArrayHasKey('id',    $item);
        $this->assertArrayHasKey('name',  $item);
        $this->assertArrayHasKey('price', $item);
        $this->assertArrayHasKey('sku',   $item);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Task 3 — GET /products/{id}
    // ─────────────────────────────────────────────────────────────────────────

    public function testGetProductByIdReturns200WithProduct(): void
    {
        $id = $this->seedProduct('Widget Pro', 29999, 'WDG-001');

        $response = $this->app->handle(
            (new ServerRequestFactory())->createServerRequest('GET', "/products/{$id}")
        );

        $body = $this->decodeBody($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Widget Pro', $body['name']);
        $this->assertSame(29999, (int) $body['price']);
    }

    public function testGetProductByIdReturns404ForUnknownId(): void
    {
        $response = $this->app->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/products/9999')
        );

        $body = $this->decodeBody($response);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertArrayHasKey('error', $body);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Task 4 — POST /products
    // ─────────────────────────────────────────────────────────────────────────

    public function testPostProductReturns201WithCreatedProduct(): void
    {
        $response = $this->app->handle(makeJsonRequest('POST', '/products', [
            'name'  => 'Widget Pro',
            'price' => 29999,
            'sku'   => 'WDG-001',
        ]));

        $body = $this->decodeBody($response);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertArrayHasKey('id', $body);
        $this->assertSame('Widget Pro', $body['name']);
        $this->assertSame(29999, (int) $body['price']);
    }

    public function testPostProductReturns422WhenNameIsMissing(): void
    {
        $response = $this->app->handle(makeJsonRequest('POST', '/products', [
            'price' => 29999,
            'sku'   => 'WDG-001',
        ]));

        $body = $this->decodeBody($response);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('name', $body['errors']);
    }

    public function testPostProductReturns422WhenPriceIsZero(): void
    {
        $response = $this->app->handle(makeJsonRequest('POST', '/products', [
            'name'  => 'Widget',
            'price' => 0,
            'sku'   => 'WDG-001',
        ]));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('price', $this->decodeBody($response)['errors']);
    }

    public function testPostProductReturns422WhenAllFieldsMissing(): void
    {
        $response = $this->app->handle(makeJsonRequest('POST', '/products', []));

        $body = $this->decodeBody($response);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('name',  $body['errors']);
        $this->assertArrayHasKey('price', $body['errors']);
        $this->assertArrayHasKey('sku',   $body['errors']);
    }

    public function testPostProductPersistsToDatabase(): void
    {
        $this->app->handle(makeJsonRequest('POST', '/products', [
            'name'  => 'Stored Widget',
            'price' => 9900,
            'sku'   => 'STR-001',
        ]));

        $row = $this->pdo->query("SELECT * FROM products WHERE sku = 'STR-001'")->fetch();

        $this->assertNotFalse($row);
        $this->assertSame('Stored Widget', $row['name']);
        $this->assertSame(9900, (int) $row['price']);
    }

    public function testCreatedProductIsRetrievableViaGetRoute(): void
    {
        $postResponse = $this->app->handle(makeJsonRequest('POST', '/products', [
            'name'  => 'Round Trip Widget',
            'price' => 4999,
            'sku'   => 'RTW-001',
        ]));

        $id = $this->decodeBody($postResponse)['id'];

        $getResponse = $this->app->handle(
            (new ServerRequestFactory())->createServerRequest('GET', "/products/{$id}")
        );

        $this->assertSame(200, $getResponse->getStatusCode());
        $this->assertSame('Round Trip Widget', $this->decodeBody($getResponse)['name']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Task 5 — GET /orders
    // ─────────────────────────────────────────────────────────────────────────

    public function testGetOrdersReturns200WithEmptyArray(): void
    {
        $response = $this->app->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/orders')
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], $this->decodeBody($response));
    }

    public function testGetOrdersReturnsAllSeededOrders(): void
    {
        $productId = $this->seedProduct('Widget Pro', 29999, 'WDG-001');
        $this->seedOrder($productId, 1, 'alice@example.com', 29999);
        $this->seedOrder($productId, 2, 'bob@example.com',   59998);

        $response = $this->app->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/orders')
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(2, $this->decodeBody($response));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Task 6 — POST /orders
    // ─────────────────────────────────────────────────────────────────────────

    public function testPostOrderReturns201WithCorrectTotalCents(): void
    {
        $productId = $this->seedProduct('Widget Pro', 29999, 'WDG-001');

        $response = $this->app->handle(makeJsonRequest('POST', '/orders', [
            'product_id'     => $productId,
            'qty'            => 2,
            'customer_email' => 'alice@example.com',
        ]));

        $body = $this->decodeBody($response);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertArrayHasKey('id', $body);
        $this->assertSame(59998, (int) $body['total_cents']); // 29999 × 2
    }

    public function testPostOrderReturns404WhenProductNotFound(): void
    {
        $response = $this->app->handle(makeJsonRequest('POST', '/orders', [
            'product_id'     => 9999,
            'qty'            => 1,
            'customer_email' => 'alice@example.com',
        ]));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertArrayHasKey('error', $this->decodeBody($response));
    }

    public function testPostOrderReturns422WhenCustomerEmailIsMissing(): void
    {
        $productId = $this->seedProduct('Widget Pro', 29999, 'WDG-001');

        $response = $this->app->handle(makeJsonRequest('POST', '/orders', [
            'product_id' => $productId,
            'qty'        => 1,
        ]));

        $body = $this->decodeBody($response);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('customer_email', $body['errors']);
    }

    public function testPostOrderReturns422WhenQtyIsMissing(): void
    {
        $productId = $this->seedProduct('Widget Pro', 29999, 'WDG-001');

        $response = $this->app->handle(makeJsonRequest('POST', '/orders', [
            'product_id'     => $productId,
            'customer_email' => 'alice@example.com',
        ]));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('qty', $this->decodeBody($response)['errors']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Task 7 — Database state assertions
    // ─────────────────────────────────────────────────────────────────────────

    public function testPostProductPersistsCorrectValuesToDatabase(): void
    {
        $this->app->handle(makeJsonRequest('POST', '/products', [
            'name'  => 'DB Widget',
            'price' => 4995,
            'sku'   => 'DBW-001',
        ]));

        $stmt = $this->pdo->prepare("SELECT * FROM products WHERE sku = ?");
        $stmt->execute(['DBW-001']);
        $row = $stmt->fetch();

        $this->assertNotFalse($row, 'Product row should exist in the database');
        $this->assertSame('DB Widget', $row['name']);
        $this->assertSame(4995, (int) $row['price']);
        $this->assertSame('DBW-001', $row['sku']);
    }

    public function testPostOrderPersistsToDatabase(): void
    {
        $productId = $this->seedProduct('Widget Pro', 29999, 'WDG-001');

        $response = $this->app->handle(makeJsonRequest('POST', '/orders', [
            'product_id'     => $productId,
            'qty'            => 3,
            'customer_email' => 'persist@example.com',
        ]));

        $orderId = $this->decodeBody($response)['id'];

        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $row = $stmt->fetch();

        $this->assertNotFalse($row, 'Order row should exist in the database');
        $this->assertSame('persist@example.com', $row['customer_email']);
        $this->assertSame($productId, (int) $row['product_id']);
        $this->assertSame(3, (int) $row['qty']);
        $this->assertSame(89997, (int) $row['total_cents']); // 29999 × 3
        $this->assertSame('pending', $row['status']);
    }
}