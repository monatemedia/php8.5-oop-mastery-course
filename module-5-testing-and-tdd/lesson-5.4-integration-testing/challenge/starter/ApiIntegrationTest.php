<?php
declare(strict_types=1);

/**
 * CHALLENGE STARTER — Lesson 5.4: Integration Testing with a Real Container
 * ──────────────────────────────────────────────────────────────────────────
 * Read CHALLENGE.md before touching this file.
 *
 * This starter contains:
 *   - SQLite schemas for products and orders
 *   - Minimal SQLite repository implementations to get you started
 *   - Stub interface declarations (replace with your src/ imports if available)
 *   - A scaffolded test class with TODO markers for each task
 *
 * If you have the full Lesson 4.5 capstone in place, replace the inline
 * class definitions below with require_once / autoloader imports from src/.
 */

use PHPUnit\Framework\TestCase;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// ─────────────────────────────────────────────────────────────────────────────
// Contracts — replace these with imports from src/Contracts/ if available
// ─────────────────────────────────────────────────────────────────────────────

interface ProductRepositoryInterface
{
    public function findAll(): array;
    public function findById(int $id): ?array;
    public function save(string $name, int $priceCents, string $sku): array;
}

interface OrderRepositoryInterface
{
    public function findAll(): array;
    public function findById(int $id): ?array;
    public function save(int $productId, int $qty, string $customerEmail, int $totalCents): array;
}

interface LoggerInterface
{
    public function log(string $level, string $message): void;
}

interface MailerInterface
{
    public function send(string $to, string $subject, string $body): bool;
}

// ─────────────────────────────────────────────────────────────────────────────
// Infrastructure — Null Object implementations
// ─────────────────────────────────────────────────────────────────────────────

class NullLogger implements LoggerInterface
{
    public function log(string $level, string $message): void {}
}

class NullMailer implements MailerInterface
{
    public function send(string $to, string $subject, string $body): bool { return true; }
}

// ─────────────────────────────────────────────────────────────────────────────
// SQLite repositories — complete these or replace with your src/ versions
// ─────────────────────────────────────────────────────────────────────────────

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
        return $this->findById((int) $this->pdo->lastInsertId());
    }
}

class SqliteOrderRepository implements OrderRepositoryInterface
{
    public function __construct(private \PDO $pdo) {}

    public function findAll(): array
    {
        return $this->pdo->query('SELECT * FROM orders ORDER BY id')->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function save(int $productId, int $qty, string $customerEmail, int $totalCents): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO orders (customer_email, status, product_id, qty, total_cents, created_at)
             VALUES (?, \'pending\', ?, ?, ?, ?)'
        );
        $stmt->execute([$customerEmail, $productId, $qty, $totalCents, date('Y-m-d H:i:s')]);
        return $this->findById((int) $this->pdo->lastInsertId());
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Services — wire repositories to business logic
// ─────────────────────────────────────────────────────────────────────────────

class ProductService
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private LoggerInterface            $logger
    ) {}

    public function getAll(): array
    {
        return $this->products->findAll();
    }

    public function getById(int $id): ?array
    {
        return $this->products->findById($id);
    }

    public function create(string $name, int $priceCents, string $sku): array
    {
        if (empty(trim($name)))  throw new \InvalidArgumentException('Name is required');
        if ($priceCents <= 0)    throw new \InvalidArgumentException('Price must be positive');
        if (empty(trim($sku)))   throw new \InvalidArgumentException('SKU is required');

        return $this->products->save($name, $priceCents, $sku);
    }
}

class OrderService
{
    public function __construct(
        private OrderRepositoryInterface   $orders,
        private ProductRepositoryInterface $products,
        private MailerInterface            $mailer,
        private LoggerInterface            $logger
    ) {}

    public function getAll(): array
    {
        return $this->orders->findAll();
    }

    public function placeOrder(int $productId, int $qty, string $customerEmail): array
    {
        $product = $this->products->findById($productId);
        if ($product === null) {
            throw new \DomainException("Product {$productId} not found");
        }

        $totalCents = (int) $product['price'] * $qty;
        $order      = $this->orders->save($productId, $qty, $customerEmail, $totalCents);

        $this->mailer->send($customerEmail, 'Order confirmed', "Total: {$totalCents} cents");

        return $order;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Controllers — HTTP layer
// ─────────────────────────────────────────────────────────────────────────────

class ProductController
{
    public function __construct(private ProductService $service) {}

    public function list(ServerRequestInterface $req, ResponseInterface $res): ResponseInterface
    {
        $res->getBody()->write(json_encode($this->service->getAll()));
        return $res->withHeader('Content-Type', 'application/json');
    }

    public function show(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        $product = $this->service->getById((int) $args['id']);
        if ($product === null) {
            $res->getBody()->write(json_encode(['error' => 'Product not found']));
            return $res->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        $res->getBody()->write(json_encode($product));
        return $res->withHeader('Content-Type', 'application/json');
    }

    public function create(ServerRequestInterface $req, ResponseInterface $res): ResponseInterface
    {
        $body   = (array) $req->getParsedBody();
        $name   = trim($body['name']  ?? '');
        $price  = (int) ($body['price'] ?? 0);
        $sku    = trim($body['sku']   ?? '');
        $errors = [];

        if ($name === '')  $errors['name']  = 'Name is required';
        if ($price <= 0)   $errors['price'] = 'Price must be a positive integer (cents)';
        if ($sku === '')   $errors['sku']   = 'SKU is required';

        if (!empty($errors)) {
            $res->getBody()->write(json_encode(['errors' => $errors]));
            return $res->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        $product = $this->service->create($name, $price, $sku);
        $res->getBody()->write(json_encode($product));
        return $res->withStatus(201)->withHeader('Content-Type', 'application/json');
    }
}

class OrderController
{
    public function __construct(private OrderService $service) {}

    public function list(ServerRequestInterface $req, ResponseInterface $res): ResponseInterface
    {
        $res->getBody()->write(json_encode($this->service->getAll()));
        return $res->withHeader('Content-Type', 'application/json');
    }

    public function create(ServerRequestInterface $req, ResponseInterface $res): ResponseInterface
    {
        $body        = (array) $req->getParsedBody();
        $productId   = (int) ($body['product_id']     ?? 0);
        $qty         = (int) ($body['qty']             ?? 0);
        $email       = trim($body['customer_email']    ?? '');
        $errors      = [];

        if ($productId <= 0) $errors['product_id']     = 'product_id must be a positive integer';
        if ($qty <= 0)       $errors['qty']             = 'qty must be a positive integer';
        if ($email === '')   $errors['customer_email']  = 'customer_email is required';

        if (!empty($errors)) {
            $res->getBody()->write(json_encode(['errors' => $errors]));
            return $res->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        try {
            $order = $this->service->placeOrder($productId, $qty, $email);
            $res->getBody()->write(json_encode($order));
            return $res->withStatus(201)->withHeader('Content-Type', 'application/json');
        } catch (\DomainException $e) {
            $res->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $res->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Helper function: build a request with a parsed JSON body
// ─────────────────────────────────────────────────────────────────────────────

function makeJsonRequest(string $method, string $uri, array $body = []): \Slim\Psr7\Request
{
    $stream = (new StreamFactory())->createStream(json_encode($body));
    return (new ServerRequestFactory())
        ->createServerRequest($method, $uri)
        ->withHeader('Content-Type', 'application/json')
        ->withBody($stream)
        ->withParsedBody($body);
}

// ─────────────────────────────────────────────────────────────────────────────
// The test class
// ─────────────────────────────────────────────────────────────────────────────

class ApiIntegrationTest extends TestCase
{
    private \PDO       $pdo;
    private \Slim\App  $app;

    protected function setUp(): void
    {
        // TODO 1: Create the in-memory SQLite PDO connection
        // $this->pdo = new \PDO('sqlite::memory:');
        // $this->pdo->setAttribute(...)
        // $this->pdo->exec('CREATE TABLE products ...');
        // $this->pdo->exec('CREATE TABLE orders ...');

        // TODO 2: Build the PHP-DI container with the test PDO injected
        // $container = (new \DI\ContainerBuilder())
        //     ->addDefinitions([...])
        //     ->build();

        // TODO 3: Boot the Slim app and register routes
        // $this->app = AppFactory::createFromContainer($container);
        // $this->app->get('/products', [...]);
        // ...
    }

    // TODO: seed helpers
    // private function seedProduct(string $name, int $price, string $sku): int { ... }
    // private function seedOrder(int $productId, int $qty, string $email): int { ... }

    // TODO: decodeBody helper
    // private function decodeBody(ResponseInterface $response): array { ... }


    // ─────────────────────────────────────────────────────────────────────────
    // TODO Task 1 — Container wiring
    // ─────────────────────────────────────────────────────────────────────────

    // public function testContainerResolvesProductRepositoryToSqliteClass(): void {}
    // public function testContainerResolvesLoggerToNullLogger(): void {}
    // public function testContainerResolvesProductControllerWithoutError(): void {}
    // public function testContainerResolvesOrderControllerWithoutError(): void {}


    // ─────────────────────────────────────────────────────────────────────────
    // TODO Task 2 — GET /products
    // ─────────────────────────────────────────────────────────────────────────

    // public function testGetProductsReturns200WithEmptyArray(): void {}
    // public function testGetProductsReturnsAllSeededProducts(): void {}
    // public function testGetProductsHasJsonContentTypeHeader(): void {}


    // ─────────────────────────────────────────────────────────────────────────
    // TODO Task 3 — GET /products/{id}
    // ─────────────────────────────────────────────────────────────────────────

    // public function testGetProductByIdReturns200WithProduct(): void {}
    // public function testGetProductByIdReturns404ForUnknownId(): void {}


    // ─────────────────────────────────────────────────────────────────────────
    // TODO Task 4 — POST /products
    // ─────────────────────────────────────────────────────────────────────────

    // public function testPostProductReturns201WithCreatedProduct(): void {}
    // public function testPostProductReturns422WhenNameIsMissing(): void {}
    // public function testPostProductReturns422WhenPriceIsZero(): void {}
    // public function testPostProductPersistsToDatabase(): void {}
    // public function testCreatedProductIsRetrievableViaGetRoute(): void {}


    // ─────────────────────────────────────────────────────────────────────────
    // TODO Task 5 — GET /orders
    // ─────────────────────────────────────────────────────────────────────────

    // public function testGetOrdersReturns200WithEmptyArray(): void {}
    // public function testGetOrdersReturnsAllSeededOrders(): void {}


    // ─────────────────────────────────────────────────────────────────────────
    // TODO Task 6 — POST /orders
    // ─────────────────────────────────────────────────────────────────────────

    // public function testPostOrderReturns201WithCorrectTotalCents(): void {}
    // public function testPostOrderReturns404WhenProductNotFound(): void {}
    // public function testPostOrderReturns422WhenCustomerEmailIsMissing(): void {}


    // ─────────────────────────────────────────────────────────────────────────
    // TODO Task 7 — Database state assertions
    // ─────────────────────────────────────────────────────────────────────────

    // public function testPostProductPersistsCorrectValuesToDatabase(): void {}
    // public function testPostOrderPersistsToDatabase(): void {}
}