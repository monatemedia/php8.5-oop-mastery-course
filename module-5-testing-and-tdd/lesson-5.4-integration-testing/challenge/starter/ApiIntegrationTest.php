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

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../app.php';   // contracts, repositories, services, controllers

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