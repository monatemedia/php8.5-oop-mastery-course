<?php
declare(strict_types=1);

/**
 * Example 03 — Testing HTTP Routes with Slim Request Simulation
 * --------------------------------------------------------------
 * Run via PHPUnit:
 *   ./vendor/bin/phpunit module-5-testing-and-tdd/lesson-5.4-integration-testing/examples/03-slim-route-test.php
 *
 * Prerequisites:
 *   composer require slim/slim slim/psr7 php-di/php-di
 *
 * This example covers:
 *   A. Building a Slim app with a real container + in-memory SQLite
 *   B. Simulating GET requests and asserting status + JSON body
 *   C. Simulating POST requests with a JSON body
 *   D. Testing 404 and 422 error responses
 *   E. Response header assertions
 *   F. The complete route → controller → service → repository → SQLite cycle
 *
 * All tests run without a real HTTP server. Slim processes requests
 * entirely in-process via App::handle($request).
 */

use PHPUnit\Framework\TestCase;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

// ─────────────────────────────────────────────────────────────────────────────
// Domain — would normally live in src/
// ─────────────────────────────────────────────────────────────────────────────

interface ProductRepositoryInterface
{
    public function findAll(): array;
    public function findById(int $id): ?array;
    public function save(string $name, int $priceCents, string $sku): array;
}

class SqliteProductRepository implements ProductRepositoryInterface
{
    public function __construct(private \PDO $pdo) {}

    public function findAll(): array
    {
        return $this->pdo->query('SELECT * FROM products ORDER BY id')->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function save(string $name, int $priceCents, string $sku): array
    {
        $stmt = $this->pdo->prepare('INSERT INTO products (name, price, sku) VALUES (?, ?, ?)');
        $stmt->execute([$name, $priceCents, $sku]);
        $id = (int) $this->pdo->lastInsertId();
        return $this->findById($id);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// HTTP layer — controllers
// ─────────────────────────────────────────────────────────────────────────────

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ProductController
{
    public function __construct(private ProductRepositoryInterface $products) {}

    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $products = $this->products->findAll();
        $response->getBody()->write(json_encode($products));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function show(
        ServerRequestInterface $request,
        ResponseInterface      $response,
        array                  $args
    ): ResponseInterface {
        $product = $this->products->findById((int) $args['id']);

        if ($product === null) {
            $response->getBody()->write(json_encode(['error' => 'Product not found']));
            return $response
                ->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode($product));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();

        $name  = trim($body['name']  ?? '');
        $price = (int) ($body['price'] ?? 0);
        $sku   = trim($body['sku']   ?? '');

        $errors = [];
        if ($name === '')  $errors['name']  = 'Name is required';
        if ($price <= 0)   $errors['price'] = 'Price must be a positive integer (cents)';
        if ($sku === '')   $errors['sku']   = 'SKU is required';

        if (!empty($errors)) {
            $response->getBody()->write(json_encode(['errors' => $errors]));
            return $response
                ->withStatus(422)
                ->withHeader('Content-Type', 'application/json');
        }

        $product = $this->products->save($name, $price, $sku);
        $response->getBody()->write(json_encode($product));
        return $response
            ->withStatus(201)
            ->withHeader('Content-Type', 'application/json');
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Test helper: build a request with a JSON body
// ─────────────────────────────────────────────────────────────────────────────

function makeJsonRequest(string $method, string $uri, array $body = []): \Slim\Psr7\Request
{
    $factory = new ServerRequestFactory();
    $stream  = (new StreamFactory())->createStream(json_encode($body));

    return $factory
        ->createServerRequest($method, $uri)
        ->withHeader('Content-Type', 'application/json')
        ->withBody($stream)
        ->withParsedBody($body);   // Slim reads ParsedBody for JSON
}


// ─────────────────────────────────────────────────────────────────────────────
// The test class
// ─────────────────────────────────────────────────────────────────────────────

class SlimRouteTestExampleTest extends TestCase
{
    private \PDO        $pdo;
    private \Slim\App   $app;

    protected function setUp(): void
    {
        // ── Fresh SQLite database ─────────────────────────────────────────────
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

        // ── PHP-DI container with test overrides ──────────────────────────────
        $testPdo   = $this->pdo;
        $container = (new \DI\ContainerBuilder())
            ->addDefinitions([
                \PDO::class                      => $testPdo,
                ProductRepositoryInterface::class => \DI\autowire(SqliteProductRepository::class),
            ])
            ->build();

        // ── Slim application ──────────────────────────────────────────────────
        $this->app = AppFactory::createFromContainer($container);

        // ── Route definitions (inline — would normally be in config/routes.php)
        $this->app->get('/products', [ProductController::class, 'list']);
        $this->app->get('/products/{id:[0-9]+}', [ProductController::class, 'show']);
        $this->app->post('/products', [ProductController::class, 'create']);

        // Disable Slim's error middleware so exceptions surface in tests
        $this->app->addErrorMiddleware(false, false, false);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Seed helper
    // ─────────────────────────────────────────────────────────────────────────

    private function seedProduct(string $name, int $price, string $sku): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO products (name, price, sku) VALUES (?, ?, ?)');
        $stmt->execute([$name, $price, $sku]);
        return (int) $this->pdo->lastInsertId();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper: decode response body to array
    // ─────────────────────────────────────────────────────────────────────────

    private function decodeBody(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true) ?? [];
    }

    // ═══════════════════════════════════════════════════════════
    // PART B — GET requests
    // ═══════════════════════════════════════════════════════════

    /**
     * GET /products → 200 with empty array when no products exist.
     * This tests the full route → controller → repository → SQLite cycle.
     */
    public function testGetProductsReturns200WithEmptyArray(): void
    {
        $request  = (new ServerRequestFactory())->createServerRequest('GET', '/products');
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], $this->decodeBody($response));
    }

    public function testGetProductsReturnsAllSeededProducts(): void
    {
        $this->seedProduct('Widget Pro',  29999, 'WDG-001');
        $this->seedProduct('Widget Lite', 14999, 'WDG-002');

        $request  = (new ServerRequestFactory())->createServerRequest('GET', '/products');
        $response = $this->app->handle($request);

        $body = $this->decodeBody($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(2, $body);
        $this->assertSame('Widget Pro',  $body[0]['name']);
        $this->assertSame('Widget Lite', $body[1]['name']);
    }

    public function testGetProductsResponseHasJsonContentTypeHeader(): void
    {
        $request  = (new ServerRequestFactory())->createServerRequest('GET', '/products');
        $response = $this->app->handle($request);

        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
    }

    // ═══════════════════════════════════════════════════════════
    // PART C — GET /products/{id}
    // ═══════════════════════════════════════════════════════════

    public function testGetProductByIdReturns200WithProduct(): void
    {
        $id = $this->seedProduct('Widget Pro', 29999, 'WDG-001');

        $request  = (new ServerRequestFactory())->createServerRequest('GET', "/products/{$id}");
        $response = $this->app->handle($request);

        $body = $this->decodeBody($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Widget Pro', $body['name']);
        $this->assertSame(29999, (int) $body['price']);
        $this->assertSame('WDG-001', $body['sku']);
    }

    // ═══════════════════════════════════════════════════════════
    // PART D — 404 and 422 error responses
    // ═══════════════════════════════════════════════════════════

    /**
     * GET /products/9999 → 404 when product does not exist.
     * This test exercises the error handling path all the way to the HTTP response.
     */
    public function testGetProductByIdReturns404ForUnknownId(): void
    {
        $request  = (new ServerRequestFactory())->createServerRequest('GET', '/products/9999');
        $response = $this->app->handle($request);

        $body = $this->decodeBody($response);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertArrayHasKey('error', $body);
        $this->assertStringContainsString('not found', strtolower($body['error']));
    }

    /**
     * POST /products with missing fields → 422 Unprocessable Entity.
     */
    public function testPostProductReturns422WhenNameIsMissing(): void
    {
        $request  = makeJsonRequest('POST', '/products', ['price' => 29999, 'sku' => 'WDG-001']);
        $response = $this->app->handle($request);

        $body = $this->decodeBody($response);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('errors', $body);
        $this->assertArrayHasKey('name', $body['errors']);
    }

    public function testPostProductReturns422WhenPriceIsZero(): void
    {
        $request  = makeJsonRequest('POST', '/products', ['name' => 'Widget', 'price' => 0, 'sku' => 'WDG-001']);
        $response = $this->app->handle($request);

        $this->assertSame(422, $response->getStatusCode());

        $body = $this->decodeBody($response);
        $this->assertArrayHasKey('price', $body['errors']);
    }

    public function testPostProductReturns422WhenAllFieldsMissing(): void
    {
        $request  = makeJsonRequest('POST', '/products', []);
        $response = $this->app->handle($request);

        $body = $this->decodeBody($response);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('name',  $body['errors']);
        $this->assertArrayHasKey('price', $body['errors']);
        $this->assertArrayHasKey('sku',   $body['errors']);
    }

    // ═══════════════════════════════════════════════════════════
    // POST /products — success (201 Created)
    // ═══════════════════════════════════════════════════════════

    /**
     * POST /products with valid body → 201 with the created product.
     * This test covers the entire write cycle: HTTP → controller → repository → SQLite.
     */
    public function testPostProductReturns201WithCreatedProduct(): void
    {
        $request = makeJsonRequest('POST', '/products', [
            'name'  => 'Widget Pro',
            'price' => 29999,
            'sku'   => 'WDG-001',
        ]);

        $response = $this->app->handle($request);
        $body     = $this->decodeBody($response);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertArrayHasKey('id', $body);
        $this->assertSame('Widget Pro', $body['name']);
        $this->assertSame(29999, (int) $body['price']);
        $this->assertSame('WDG-001', $body['sku']);
    }

    public function testPostProductPersistsToDatabase(): void
    {
        $request = makeJsonRequest('POST', '/products', [
            'name'  => 'Stored Widget',
            'price' => 9900,
            'sku'   => 'STR-001',
        ]);

        $this->app->handle($request);

        // Assert directly on the database
        $row = $this->pdo->query("SELECT * FROM products WHERE sku = 'STR-001'")->fetch();

        $this->assertNotFalse($row);
        $this->assertSame('Stored Widget', $row['name']);
        $this->assertSame(9900, (int) $row['price']);
    }

    /**
     * POST → GET round trip: create a product, then retrieve it via the GET route.
     * This test covers the full lifecycle within the integration test environment.
     */
    public function testCreatedProductIsRetrievableViaGetRoute(): void
    {
        // POST to create
        $postRequest = makeJsonRequest('POST', '/products', [
            'name'  => 'Round Trip Widget',
            'price' => 4999,
            'sku'   => 'RTW-001',
        ]);

        $postResponse = $this->app->handle($postRequest);
        $created      = $this->decodeBody($postResponse);

        $this->assertSame(201, $postResponse->getStatusCode());
        $id = $created['id'];

        // GET to retrieve
        $getRequest  = (new ServerRequestFactory())->createServerRequest('GET', "/products/{$id}");
        $getResponse = $this->app->handle($getRequest);

        $retrieved = $this->decodeBody($getResponse);

        $this->assertSame(200, $getResponse->getStatusCode());
        $this->assertSame('Round Trip Widget', $retrieved['name']);
        $this->assertSame(4999, (int) $retrieved['price']);
    }

    // ═══════════════════════════════════════════════════════════
    // PART E — Response header assertions
    // ═══════════════════════════════════════════════════════════

    public function testAllResponsesHaveJsonContentTypeHeader(): void
    {
        $routes = [
            (new ServerRequestFactory())->createServerRequest('GET', '/products'),
            (new ServerRequestFactory())->createServerRequest('GET', '/products/9999'),
            makeJsonRequest('POST', '/products', []),
        ];

        foreach ($routes as $request) {
            $response = $this->app->handle($request);
            $this->assertStringContainsString(
                'application/json',
                $response->getHeaderLine('Content-Type'),
                "Route did not return application/json Content-Type"
            );
        }
    }
}