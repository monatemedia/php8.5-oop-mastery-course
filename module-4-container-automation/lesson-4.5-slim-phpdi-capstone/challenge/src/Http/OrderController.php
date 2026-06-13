<?php
declare(strict_types=1);

namespace App\Http;

use App\Contracts\LoggerInterface;
use App\Domain\Order\OrderService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * OrderController
 *
 * Handles HTTP requests for the /orders resource.
 * All dependencies are injected via constructor — PHP-DI wires them automatically.
 *
 * Rule 1: No getenv(), no $container->get(), no new on infrastructure classes.
 */
class OrderController {
    public function __construct(
        private OrderService    $service,
        private LoggerInterface $logger
    ) {}

    /**
     * POST /orders
     *
     * Expected JSON body:
     * {
     *   "product_id": 1,
     *   "quantity":   2,
     *   "email":      "customer@example.com"
     * }
     *
     * Returns 201 on success, 422 on validation error.
     */
    public function store(Request $request, Response $response): Response {
        $body      = json_decode((string) $request->getBody(), true) ?? [];
        $productId = (int)    ($body['product_id'] ?? 0);
        $quantity  = (int)    ($body['quantity']   ?? 1);
        $email     = (string) ($body['email']      ?? '');

        $this->logger->log('INFO', "POST /orders — product #{$productId} for {$email}");

        // Basic presence validation before hitting the service
        if ($productId === 0 || empty($email)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error'   => 'product_id and email are required',
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(422);
        }

        try {
            $order = $this->service->place($productId, $quantity, $email);

            $response->getBody()->write(json_encode([
                'success' => true,
                'data'    => $order,
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(201);

        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error'   => $e->getMessage(),
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(422);
        }
    }

    /**
     * GET /orders/{id}
     */
    public function show(Request $request, Response $response, array $args): Response {
        $id    = (int) $args['id'];
        $order = $this->service->findById($id);

        $this->logger->log('INFO', "GET /orders/{$id}");

        if ($order === null) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error'   => "Order #{$id} not found",
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(404);
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'data'    => $order,
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }
}