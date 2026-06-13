<?php
declare(strict_types=1);

/**
 * config/routes.php — Route Definitions
 * ----------------------------------------
 * This file only registers routes. No business logic here.
 * Controllers are resolved from the PHP-DI container automatically
 * when Slim handles a matching request.
 *
 * The [ControllerClass::class, 'method'] syntax tells Slim:
 *   1. Call $container->get(ControllerClass::class)
 *   2. PHP-DI auto-wires the controller with its dependencies
 *   3. Slim calls the named method with ($request, $response[, $args])
 *
 * $app is available from the scope that requires this file (public/index.php).
 */

use App\Http\OrderController;
use App\Http\ProductController;

// ── Product routes ────────────────────────────────────────────────────────────
$app->get('/products',      [ProductController::class, 'index']);
$app->get('/products/{id}', [ProductController::class, 'show']);

// ── Order routes ──────────────────────────────────────────────────────────────
$app->post('/orders',       [OrderController::class, 'store']);
$app->get('/orders/{id}',   [OrderController::class, 'show']);