<?php
declare(strict_types=1);

/**
 * Example 03 — Auto-wired Controllers
 * -------------------------------------
 * A complete multi-controller API with:
 *   - ProductController (GET /products, GET /products/{id})
 *   - OrderController   (POST /orders, GET /orders/{id})
 *
 * Every controller dependency is auto-wired by PHP-DI.
 * The config/services.php file (simulated inline) is the ONLY place
 * where concrete class decisions are made.
 *
 * Course Philosophy Rule 1: Config at the entry point.
 * Controllers know nothing about which concrete classes back them.
 * They declare needs via constructor type hints — PHP-DI provides everything.
 *
 * Requires: composer require slim/slim slim/psr7 php-di/php-di
 */

require __DIR__ . '/../../../../vendor/autoload.php';

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use function DI\autowire;

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Auto-wired Controllers — Full Multi-route API      ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Domain interfaces
// ─────────────────────────────────────────────────────────────────────────────

interface ProductRepositoryInterface {
    public function findAll(): array;
    public function findById(int $id): ?array;
}

interface OrderRepositoryInterface {
    public function create(array $data): array;
    public function findById(int $id): ?array;
    public function all(): array;
}

interface LoggerInterface {
    public function log(string $level, string $message): void;
}

interface MailerInterface {
    public function send(string $to, string $subject, string $body): bool;
}


// ─────────────────────────────────────────────────────────────────────────────
// Concrete implementations
// ─────────────────────────────────────────────────────────────────────────────

class InMemoryProductRepository implements ProductRepositoryInterface {
    private array $products = [
        1 => ['id' => 1, 'name' => 'Widget Pro',  'price' => 29999, 'sku' => 'WDG-001', 'stock' => 50],
        2 => ['id' => 2, 'name' => 'Widget Lite', 'price' => 14999, 'sku' => 'WDG-002', 'stock' => 5],
    ];
    public function findAll(): array       { return array_values($this->products); }
    public function findById(int $id): ?array { return $this->products[$id] ?? null; }
}

class InMemoryOrderRepository implements OrderRepositoryInterface {
    private array $orders  = [];
    private int   $nextId  = 1;

    public function create(array $data): array {
        $order          = array_merge(['id' => $this->nextId++], $data);
        $this->orders[] = $order;
        return $order;
    }
    public function findById(int $id): ?array {
        foreach ($this->orders as $o) {
            if ($o['id'] === $id) return $o;
        }
        return null;
    }
    public function all(): array { return $this->orders; }
}

class ConsoleLogger implements LoggerInterface {
    public function log(string $level, string $message): void {
        echo "  [{$level}] {$message}\n";
    }
}

class ConsoleMailer implements MailerInterface {
    public function send(string $to, string $subject, string $body): bool {
        echo "  [MAIL] To: {$to} | {$subject}\n";
        return true;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Domain service
// ─────────────────────────────────────────────────────────────────────────────

class OrderService {
    public function __construct(
        private ProductRepositoryInterface $products,
        private OrderRepositoryInterface   $orders,
        private MailerInterface            $mailer,
        private LoggerInterface            $logger
    ) {}

    public function place(int $productId, int $quantity, string $email): array {
        $product = $this->products->findById($productId);
        if (!$product) {
            throw new \InvalidArgumentException("Product #{$productId} not found");
        }
        if ($quantity < 1) {
            throw new \InvalidArgumentException("Quantity must be at least 1");
        }

        $order = $this->orders->create([
            'product_id' => $productId,
            'quantity'   => $quantity,
            'email'      => $email,
            'total'      => $product['price'] * $quantity,
            'status'     => 'confirmed',
        ]);

        $this->mailer->send($email, "Order #{$order['id']} Confirmed",
            "Total: R" . number_format($order['total'] / 100, 2));
        $this->logger->log('INFO', "Order #{$order['id']} placed for {$email}");

        return $order;
    }

    public function findById(int $id): ?array {
        return $this->orders->findById($id);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// HTTP Controllers — auto-wired, never call new or getenv
// ─────────────────────────────────────────────────────────────────────────────

class ProductController {
    public function __construct(
        private ProductRepositoryInterface $products,
        private LoggerInterface            $logger
    ) {}

    public function index(Request $request, Response $response): Response {
        $this->logger->log('INFO', 'GET /products');
        $response->getBody()->write(json_encode([
            'data' => $this->products->findAll(),
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function show(Request $request, Response $response, array $args): Response {
        $id      = (int) $args['id'];
        $this->logger->log('INFO', "GET /products/{$id}");
        $product = $this->products->findById($id);

        if ($product === null) {
            $response->getBody()->write(json_encode(['error' => "Product #{$id} not found"]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode(['data' => $product]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}

class OrderController {
    public function __construct(
        private OrderService    $service,
        private LoggerInterface $logger
    ) {}

    public function store(Request $request, Response $response): Response {
        $body       = json_decode((string) $request->getBody(), true) ?? [];
        $productId  = (int)    ($body['product_id'] ?? 0);
        $quantity   = (int)    ($body['quantity']   ?? 1);
        $email      = (string) ($body['email']      ?? '');

        $this->logger->log('INFO', "POST /orders for {$email}");

        if ($productId === 0 || empty($email)) {
            $response->getBody()->write(json_encode([
                'error' => 'product_id and email are required',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        try {
            $order = $this->service->place($productId, $quantity, $email);
            $response->getBody()->write(json_encode(['data' => $order]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
    }

    public function show(Request $request, Response $response, array $args): Response {
        $id    = (int) $args['id'];
        $this->logger->log('INFO', "GET /orders/{$id}");
        $order = $this->service->findById($id);

        if ($order === null) {
            $response->getBody()->write(json_encode(['error' => "Order #{$id} not found"]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode(['data' => $order]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Bootstrap — simulates public/index.php + config/services.php
// ─────────────────────────────────────────────────────────────────────────────

echo "── Bootstrap ─────────────────────────────────────────\n\n";

// Simulates config/services.php
$definitions = [
    ProductRepositoryInterface::class => autowire(InMemoryProductRepository::class),
    OrderRepositoryInterface::class   => autowire(InMemoryOrderRepository::class),
    LoggerInterface::class            => autowire(ConsoleLogger::class),
    MailerInterface::class            => autowire(ConsoleMailer::class),
    // OrderService, ProductController, OrderController: auto-wired — no entry needed
];

$builder = new ContainerBuilder();
$builder->addDefinitions($definitions);
$container = $builder->build();

AppFactory::setContainer($container);
$app = AppFactory::create();

// Simulates config/routes.php
$app->get('/products',        [ProductController::class, 'index']);
$app->get('/products/{id}',   [ProductController::class, 'show']);
$app->post('/orders',         [OrderController::class,   'store']);
$app->get('/orders/{id}',     [OrderController::class,   'show']);

echo "4 routes registered. 2 controllers. 4 interface bindings.\n";
echo "OrderService, ProductController, OrderController: zero explicit bindings.\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Simulate the three API routes
// ─────────────────────────────────────────────────────────────────────────────

function req(string $method, string $uri, \Slim\App $app, ?string $body = null): void {
    $request = (new ServerRequestFactory())->createServerRequest($method, $uri);
    if ($body !== null) {
        $stream  = (new StreamFactory())->createStream($body);
        $request = $request->withBody($stream)->withHeader('Content-Type', 'application/json');
    }
    $response = $app->handle($request);
    $status   = $response->getStatusCode();
    $decoded  = json_decode((string) $response->getBody(), true);
    echo "  → {$status}: " . json_encode($decoded) . "\n";
}

echo "── Route tests ───────────────────────────────────────\n\n";

echo "GET /products:\n";
req('GET', '/products', $app);

echo "\nGET /products/2:\n";
req('GET', '/products/2', $app);

echo "\nPOST /orders (success):\n";
req('POST', '/orders', $app, json_encode([
    'product_id' => 1, 'quantity' => 3, 'email' => 'alice@example.com'
]));

echo "\nPOST /orders (missing email — 422):\n";
req('POST', '/orders', $app, json_encode(['product_id' => 1, 'quantity' => 1]));

echo "\nPOST /orders (invalid product — 422):\n";
req('POST', '/orders', $app, json_encode([
    'product_id' => 99, 'quantity' => 1, 'email' => 'bob@example.com'
]));

echo "\nGET /orders/1:\n";
req('GET', '/orders/1', $app);

echo "\nGET /orders/99 (not found — 404):\n";
req('GET', '/orders/99', $app);

echo "\n--- Recap ---\n";
echo "Controllers are plain classes — constructor injection, no `new` on infrastructure.\n";
echo "PHP-DI resolves them when Slim handles a matching request.\n";
echo "All concrete class decisions live in the definitions array (composition root).\n";
echo "To swap to MySQL: change two lines in definitions — zero controller changes.\n";