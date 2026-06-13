<?php
declare(strict_types=1);

/**
 * public/index.php — Application Entry Point (Composition Root)
 * ---------------------------------------------------------------
 * Course Philosophy Rule 1: Config belongs at the entry point.
 *
 * This file is the ONLY place where:
 *   - The PHP-DI container is constructed
 *   - The Slim application is bootstrapped
 *   - Route definitions are loaded
 *   - The application runs
 *
 * Nothing in src/ knows this file exists. The container wires
 * everything transparently via constructor injection.
 */

require __DIR__ . '/../vendor/autoload.php';

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;

// Step 1: Build the PHP-DI container from the definitions file
$builder = new ContainerBuilder();
$builder->addDefinitions(__DIR__ . '/../config/services.php');

// Uncomment for production — compiles the container to eliminate Reflection overhead:
// $builder->enableCompilation(__DIR__ . '/../var/cache');

$container = $builder->build();

// Step 2: Pass the container to Slim
AppFactory::setContainer($container);
$app = AppFactory::create();

// Step 3: Add middleware
$app->addErrorMiddleware(
    displayErrorDetails: (bool)(getenv('APP_DEBUG') ?: false),
    logErrors:           false,
    logErrorDetails:     false
);

// Step 4: Load route definitions
// $app is available in routes.php via the including scope
require __DIR__ . '/../config/routes.php';

// Step 5: Run
$app->run();