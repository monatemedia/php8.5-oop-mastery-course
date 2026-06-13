<?php
declare(strict_types=1);

namespace App\Http;

use App\Contracts\LoggerInterface;
use App\Domain\Product\ProductRepositoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * ProductController
 *
 * Handles HTTP requests for the /products resource.
 * All dependencies are injected via constructor — PHP-DI wires them automatically.
 *
 * Rule 1: No getenv(), no $container->get(), no new on infrastructure classes.
 */
class ProductController {
    public function __construct(
        private ProductRepositoryInterface $products,
        private LoggerInterface            $logger
    ) {}

    /**
     * GET /products
     * Optional query param: ?min_price=N (filters by minimum price in cents)
     */
    public function index(Request $request, Response $response): Response {
        $this->logger->log('INFO', 'GET /products');

        $products = $this->products->findAll();

        // Optional price filter
        $params = $request->getQueryParams();
        if (isset($params['min_price'])) {
            $min      = (int) $params['min_price'];
            $products = array_values(
                array_filter($products, fn(array $p): bool => $p['price'] >= $min)
            );
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'data'    => $products,
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }

    /**
     * GET /products/{id}
     */
    public function show(Request $request, Response $response, array $args): Response {
        $id      = (int) $args['id'];
        $product = $this->products->findById($id);

        $this->logger->log('INFO', "GET /products/{$id}");

        if ($product === null) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error'   => "Product #{$id} not found",
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(404);
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'data'    => $product,
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }
}