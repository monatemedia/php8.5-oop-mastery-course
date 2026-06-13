<?php
declare(strict_types=1);

/**
 * Example 02 — Slim with PHP-DI Container
 * -----------------------------------------
 * Wiring PHP-DI as Slim's PSR-11 container.
 * When Slim resolves a route handler that is a class name string,
 * it calls $container->get(ClassName::class) — PHP-DI auto-wires it.
 *
 * This example shows the bootstrap pattern:
 *   1. Build the PHP-DI container
 *   2. Pass it to Slim via AppFactory::setContainer()
 *   3. Define routes using [ClassName::class, 'method'] syntax
 *   4. Slim auto-resolves controllers from the container
 *
 * Course Philosophy Rule 1: Config at the entry point.
 * The definitions array (services) is the composition root.
 * Controllers never call getenv() or $container->get() directly.
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
use function DI\factory;

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Slim + PHP-DI: Container Wiring                    ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Domain layer (interfaces + implementations)
// ─────────────────────────────────────────────────────────────────────────────

interface ProductRepositoryInterface {
    public function findAll(): array;
    public function findById(int $id): ?array;
}

interface LoggerInterface {
    public function log(string $level, string $message): void;
}

class InMemoryProductRepository implements ProductRepositoryInterface {
    private array $products = [
        1 => ['id' => 1, 'name' => 'Widget Pro',  'price' => 29999, 'sku' => 'WDG-001'],
        2 => ['id' => 2, 'name' => 'Widget Lite', 'price' => 14999, 'sku' => 'WDG-002'],
    ];

    public function findAll(): array    { return array_values($this->products); }
    public function findById(int $id): ?array { return $this->products[$id] ?? null; }
}

class ConsoleLogger implements LoggerInterface {
    public function log(string $level, string $message): void {
        echo "  [{$level}] {$message}\n";
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// HTTP layer: controller class (auto-wired via PHP-DI)
// ─────────────────────────────────────────────────────────────────────────────

class ProductController {
    // ✅ Constructor injection — PHP-DI wires these automatically
    // ✅ No getenv(), no container->get(), no `new` on infrastructure
    public function __construct(
        private ProductRepositoryInterface $products,
        private LoggerInterface            $logger
    ) {}

    public function index(Request $request, Response $response): Response {
        $this->logger->log('INFO', 'GET /products');
        $data = $this->products->findAll();
        $response->getBody()->write(json_encode($data));
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

        $response->getBody()->write(json_encode($product));
        return $response->withHeader('Content-Type', 'application/json');
    }
}


// ═══════════════════════════════════════════════════════════
// PART A — The bootstrap pattern
// ═══════════════════════════════════════════════════════════

echo "── Part A: Bootstrap pattern ─────────────────────────\n\n";

// Step 1: Build the PHP-DI container (this is config/services.php in the real app)
$definitions = [
    ProductRepositoryInterface::class => autowire(InMemoryProductRepository::class),
    LoggerInterface::class            => autowire(ConsoleLogger::class),
    // ProductController: NOT listed — PHP-DI auto-wires it
];

$builder = new ContainerBuilder();
$builder->addDefinitions($definitions);
$container = $builder->build();

// Step 2: Pass container to Slim
AppFactory::setContainer($container);
$app = AppFactory::create();

// Step 3: Define routes using [ClassName::class, 'method'] syntax
// When Slim receives a request, it calls:
//   $container->get(ProductController::class) → PHP-DI auto-wires it
//   then calls ->index($request, $response)
$app->get('/products',      [ProductController::class, 'index']);
$app->get('/products/{id}', [ProductController::class, 'show']);

echo "Bootstrap complete.\n";
echo "  Container class: "  . get_class($container) . "\n";
echo "  PSR-11 compliant: " . ($container instanceof \Psr\Container\ContainerInterface ? 'YES ✓' : 'NO') . "\n\n";


// ═══════════════════════════════════════════════════════════
// PART B — How Slim resolves the controller
// ═══════════════════════════════════════════════════════════

echo "── Part B: How Slim resolves controllers ─────────────\n\n";

echo "When Slim receives GET /products:\n";
echo "  1. Router matches → handler is [ProductController::class, 'index']\n";
echo "  2. Slim calls: \$container->get(ProductController::class)\n";
echo "  3. PHP-DI reflects ProductController constructor:\n";
echo "     → needs ProductRepositoryInterface → resolves InMemoryProductRepository\n";
echo "     → needs LoggerInterface → resolves ConsoleLogger\n";
echo "  4. ProductController is instantiated and injected ✓\n";
echo "  5. Slim calls: \$controller->index(\$request, \$response)\n\n";


// ═══════════════════════════════════════════════════════════
// PART C — Simulate requests
// ═══════════════════════════════════════════════════════════

echo "── Part C: Request simulation ───────────────────────\n\n";

function simulateRequest(
    string $method,
    string $uri,
    \Slim\App $app,
    ?string $body = null
): void {
    $request = (new ServerRequestFactory())->createServerRequest($method, $uri);
    if ($body !== null) {
        $stream  = (new StreamFactory())->createStream($body);
        $request = $request->withBody($stream)
                           ->withHeader('Content-Type', 'application/json');
    }

    $response = $app->handle($request);
    $status   = $response->getStatusCode();
    $decoded  = json_decode((string) $response->getBody(), true);

    echo "  {$method} {$uri} → {$status}\n";
    echo "  " . json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
}

echo "1. GET /products:\n";
simulateRequest('GET', '/products', $app);

echo "2. GET /products/1:\n";
simulateRequest('GET', '/products/1', $app);

echo "3. GET /products/99 (not found):\n";
simulateRequest('GET', '/products/99', $app);


// ═══════════════════════════════════════════════════════════
// PART D — Verify singleton: controller resolved once
// ═══════════════════════════════════════════════════════════

echo "── Part D: Controller is a singleton ────────────────\n\n";

$ctrl1 = $container->get(ProductController::class);
$ctrl2 = $container->get(ProductController::class);
echo "Same controller instance? " . ($ctrl1 === $ctrl2 ? 'YES ✓' : 'NO ✗') . "\n\n";
echo "PHP-DI caches auto-wired classes — the controller is built once per container.\n\n";

echo "── Key differences from Example 01 ──────────────────\n\n";
echo "Example 01: logic embedded in closures — no class, no injection.\n";
echo "Example 02: logic in a class — constructor-injected, auto-wired by PHP-DI.\n";
echo "  ✓ Controller testable in isolation (inject fakes)\n";
echo "  ✓ Controller unaware of which concrete classes it uses\n";
echo "  ✓ Swap InMemoryProductRepository for MySQLProductRepository: zero code change\n";