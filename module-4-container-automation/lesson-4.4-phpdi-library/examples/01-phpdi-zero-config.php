<?php
declare(strict_types=1);

/**
 * Example 01 — PHP-DI Zero-Config Auto-wiring
 * ---------------------------------------------
 * PHP-DI can resolve concrete classes with zero configuration.
 * No definitions file, no explicit bindings — just ContainerBuilder::build()
 * and get(ClassName::class).
 *
 * This example shows:
 *   A. Zero-config resolution of a concrete class graph
 *   B. What "zero config" actually means (and its limits)
 *   C. The ContainerBuilder API
 *   D. PHP-DI vs our hand-built AutowiringContainer from Lesson 4.3
 *
 * Requires: composer require php-di/php-di
 *
 * Run from project root:
 *   php module-4-container-automation/lesson-4.4-phpdi-library/examples/01-phpdi-zero-config.php
 */

require __DIR__ . '/../../../../vendor/autoload.php';

use DI\ContainerBuilder;

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  PHP-DI Zero-Config Auto-wiring                     ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// A concrete-only service graph (no interfaces — zero config needed)
// ─────────────────────────────────────────────────────────────────────────────

class ConsoleLogger {
    public function __construct() { echo "  [NEW] ConsoleLogger\n"; }
    public function log(string $level, string $message): void {
        echo "  [{$level}] {$message}\n";
    }
}

class InMemoryDatabase {
    private array $products = [
        1 => ['id' => 1, 'name' => 'Widget Pro',  'price' => 29999],
        2 => ['id' => 2, 'name' => 'Widget Lite', 'price' => 14999],
    ];
    public function __construct() { echo "  [NEW] InMemoryDatabase\n"; }
    public function query(string $sql, array $params = []): array {
        echo "  [DB] query\n";
        if (!empty($params) && is_int($params[0])) {
            return isset($this->products[$params[0]]) ? [$this->products[$params[0]]] : [];
        }
        return array_values($this->products);
    }
    public function execute(string $sql, array $params = []): bool {
        echo "  [DB] execute\n";
        return true;
    }
}

class ProductRepository {
    public function __construct(
        private InMemoryDatabase $db,
        private ConsoleLogger    $logger
    ) { echo "  [NEW] ProductRepository\n"; }

    public function findAll(): array {
        $this->logger->log('INFO', 'Fetching all products');
        return $this->db->query('SELECT * FROM products');
    }
}

class OrderService {
    public function __construct(
        private ProductRepository $products,
        private ConsoleLogger     $logger
    ) { echo "  [NEW] OrderService\n"; }

    public function listProducts(): array {
        $this->logger->log('INFO', 'Listing products');
        return $this->products->findAll();
    }
}


// ═══════════════════════════════════════════════════════════
// PART A — Zero-config: no definitions file, no explicit bindings
// ═══════════════════════════════════════════════════════════

echo "── Part A: Zero-config auto-wiring ──────────────────\n\n";

$builder   = new ContainerBuilder();
// No addDefinitions() call — pure auto-wiring
$container = $builder->build();

echo "Resolving OrderService (watch construction order):\n";
$service = $container->get(OrderService::class);
echo "\nListing products:\n";
$products = $service->listProducts();
echo "\nResolved " . count($products) . " products\n\n";


// ═══════════════════════════════════════════════════════════
// PART B — Singleton caching
// ═══════════════════════════════════════════════════════════

echo "── Part B: Singleton caching ─────────────────────────\n\n";

$s1 = $container->get(OrderService::class);
$s2 = $container->get(OrderService::class);
echo "Same OrderService? "    . ($s1 === $s2 ? 'YES ✓' : 'NO ✗') . "\n";

$db1 = $container->get(InMemoryDatabase::class);
$db2 = $container->get(InMemoryDatabase::class);
echo "Same Database?     "    . ($db1 === $db2 ? 'YES ✓' : 'NO ✗') . "\n\n";

echo "Note: PHP-DI caches all auto-wired classes as singletons by default.\n";
echo "Transient scope (fresh instance per resolution) is covered in Lesson 6.2.\n\n";


// ═══════════════════════════════════════════════════════════
// PART C — PSR-11 compliance
// ═══════════════════════════════════════════════════════════

echo "── Part C: PSR-11 compliance ─────────────────────────\n\n";

echo "Container class: "   . get_class($container) . "\n";
echo "Implements PSR-11: " . ($container instanceof \Psr\Container\ContainerInterface ? 'YES ✓' : 'NO') . "\n\n";

echo "PSR-11 methods:\n";
echo "  has(OrderService::class):   " . ($container->has(OrderService::class) ? 'true' : 'false') . "\n";
echo "  has(NonExistentClass):      " . ($container->has('NonExistentClass') ? 'true' : 'false') . "\n";
echo "  get(OrderService::class):   " . get_class($container->get(OrderService::class)) . "\n\n";


// ═══════════════════════════════════════════════════════════
// PART D — Limits of zero-config: interfaces need explicit bindings
// ═══════════════════════════════════════════════════════════

echo "── Part D: Limits — interfaces need explicit bindings ─\n\n";

interface LoggerInterface    { public function log(string $l, string $m): void; }
interface DatabaseInterface  { public function query(string $sql): array; }

class ServiceWithInterfaces {
    public function __construct(
        private DatabaseInterface $db,     // ← interface
        private LoggerInterface   $logger  // ← interface
    ) {}
    public function run(): void { $this->logger->log('INFO', 'Running'); }
}

$container2 = (new ContainerBuilder())->build();

try {
    $container2->get(ServiceWithInterfaces::class);
} catch (\Exception $e) {
    echo "Exception when resolving with no interface bindings:\n";
    // PHP-DI throws DI\Exception\NotFoundException or similar
    echo "  " . get_class($e) . "\n";
    echo "  " . substr($e->getMessage(), 0, 120) . "...\n\n";
}

echo "Solution: add interface bindings (Example 02 covers this fully).\n";
echo "  \$builder->addDefinitions([\n";
echo "      DatabaseInterface::class => \\DI\\autowire(InMemoryDatabase::class),\n";
echo "      LoggerInterface::class   => \\DI\\autowire(ConsoleLogger::class),\n";
echo "  ]);\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Comparison: our AutowiringContainer vs PHP-DI
// ─────────────────────────────────────────────────────────────────────────────

echo "── Comparison: AutowiringContainer vs PHP-DI ─────────\n\n";
echo "  Feature                    │ Lesson 4.3 Container │ PHP-DI\n";
echo "  ───────────────────────────┼──────────────────────┼──────────────\n";
echo "  Zero-config auto-wiring    │ ✓                    │ ✓\n";
echo "  Interface bindings         │ ✓ (bind/instance)    │ ✓ (autowire/factory)\n";
echo "  Singleton caching          │ ✓                    │ ✓\n";
echo "  Circular detection         │ ✓                    │ ✓\n";
echo "  PSR-11                     │ ✗                    │ ✓\n";
echo "  Factory definitions        │ Basic callable       │ ✓ Full DI\n";
echo "  Transient scope            │ ✗                    │ ✓\n";
echo "  Compiled container         │ ✗                    │ ✓\n";
echo "  Framework integrations     │ ✗                    │ ✓ (Slim, Symfony)\n\n";

echo "PHP-DI is the Lesson 4.3 container, done properly.\n";
echo "Understanding Lesson 4.3 means PHP-DI holds no surprises.\n";