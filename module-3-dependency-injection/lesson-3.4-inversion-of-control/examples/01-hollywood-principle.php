<?php
declare(strict_types=1);

/**
 * Example 01 — The Hollywood Principle
 * -----------------------------------------
 * "Don't call us — we'll call you."
 *
 * This example shows the same application built twice:
 *   WITHOUT IoC: classes reach out and get what they need
 *   WITH IoC:    classes declare what they need; something else provides it
 *
 * The Hollywood Principle is about who is in control of the dependency graph.
 * Without IoC: each class controls its own dependencies.
 * With IoC:    a single entry point controls all dependencies.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  The Hollywood Principle                            ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Infrastructure classes (exist in both versions)
// ─────────────────────────────────────────────────────────────────────────────

class MySQLDatabase {
    public function __construct(private string $dsn) {
        echo "  [MySQL] Connected: {$dsn}\n";
    }
    public function query(string $sql, array $params = []): array {
        echo "  [MySQL] Query: " . substr($sql, 0, 50) . "\n";
        return [['id' => 1, 'name' => 'Widget Pro', 'price' => 29999, 'stock' => 50]];
    }
    public function execute(string $sql, array $params = []): bool {
        echo "  [MySQL] Execute: " . substr($sql, 0, 50) . "\n";
        return true;
    }
}

class FileLogger {
    public function __construct(private string $path) {
        echo "  [Logger] Opened: {$path}\n";
    }
    public function log(string $level, string $message): void {
        echo "  [{$level}] {$message}\n";
    }
}

class SmtpMailer {
    public function __construct(private string $host, private int $port) {
        echo "  [SMTP] Connected: {$host}:{$port}\n";
    }
    public function send(string $to, string $subject, string $body): bool {
        echo "  [SMTP] To: {$to} | {$subject}\n";
        return true;
    }
}


// ═══════════════════════════════════════════════════════════
// WITHOUT IoC — classes reach out for what they need
// ═══════════════════════════════════════════════════════════

echo "── Without IoC: classes call out for dependencies ───\n\n";

class OldProductRepository {
    private MySQLDatabase $db;
    private FileLogger    $logger;

    public function __construct() {
        // ❌ Reaches out — class controls its own wiring
        $this->db     = new MySQLDatabase('mysql:host=localhost;dbname=shop');
        $this->logger = new FileLogger('/var/log/products.log');
        echo "  [OldProductRepo] Wired itself\n";
    }

    public function findById(int $id): ?array {
        $this->logger->log('INFO', "findById({$id})");
        $rows = $this->db->query('SELECT * FROM products WHERE id = ?', [$id]);
        return $rows[0] ?? null;
    }
}

class OldOrderService {
    private OldProductRepository $products;
    private SmtpMailer           $mailer;
    private FileLogger           $logger;

    public function __construct() {
        // ❌ Reaches out — creates everything itself
        // Also creates ANOTHER FileLogger and MySQLDatabase inside OldProductRepository
        $this->products = new OldProductRepository();
        $this->mailer   = new SmtpMailer('smtp.example.com', 587);
        $this->logger   = new FileLogger('/var/log/orders.log');
        echo "  [OldOrderService] Wired itself\n";
    }

    public function placeOrder(int $productId, string $customerEmail): bool {
        $this->logger->log('INFO', "Placing order for product #{$productId}");
        $product = $this->products->findById($productId);
        if (!$product) return false;
        $this->mailer->send($customerEmail, 'Order confirmed', 'Thank you!');
        $this->logger->log('INFO', "Order placed for {$customerEmail}");
        return true;
    }
}

echo "Creating OldOrderService (watch all the connections fire):\n";
$oldService = new OldOrderService();
echo "\nPlacing order:\n";
$oldService->placeOrder(1, 'alice@example.com');

echo "\nProblems:\n";
echo "  ✗ THREE separate infrastructure connections created (two DB, two loggers)\n";
echo "  ✗ Cannot test without real MySQL, real SMTP, real filesystem\n";
echo "  ✗ Cannot swap to PostgreSQL without editing multiple files\n";
echo "  ✗ Classes call outward — they are in control, not the entry point\n\n";


// ═══════════════════════════════════════════════════════════
// WITH IoC — classes declare needs; entry point provides them
// ═══════════════════════════════════════════════════════════

echo "── With IoC: entry point provides everything ─────────\n\n";

// Interfaces — what classes depend on
interface DatabaseInterface {
    public function query(string $sql, array $params = []): array;
    public function execute(string $sql, array $params = []): bool;
}

interface LoggerInterface {
    public function log(string $level, string $message): void;
}

interface MailerInterface {
    public function send(string $to, string $subject, string $body): bool;
}

// Make infrastructure implement interfaces
class MySQLDatabaseDI extends MySQLDatabase implements DatabaseInterface {}
class FileLoggerDI    extends FileLogger    implements LoggerInterface {}
class SmtpMailerDI    extends SmtpMailer    implements MailerInterface {}

// Services — declare needs, never reach out
class ProductRepository {
    public function __construct(
        private DatabaseInterface $db,     // ← declared need
        private LoggerInterface   $logger  // ← declared need
    ) {
        echo "  [ProductRepo] Ready (deps provided)\n";
    }

    public function findById(int $id): ?array {
        $this->logger->log('INFO', "findById({$id})");
        $rows = $this->db->query('SELECT * FROM products WHERE id = ?', [$id]);
        return $rows[0] ?? null;
    }
}

class OrderService {
    public function __construct(
        private ProductRepository $products, // ← declared need
        private MailerInterface   $mailer,   // ← declared need
        private LoggerInterface   $logger    // ← declared need
    ) {
        echo "  [OrderService] Ready (deps provided)\n";
    }

    public function placeOrder(int $productId, string $customerEmail): bool {
        $this->logger->log('INFO', "Placing order for product #{$productId}");
        $product = $this->products->findById($productId);
        if (!$product) return false;
        $this->mailer->send($customerEmail, 'Order confirmed', 'Thank you!');
        $this->logger->log('INFO', "Order placed for {$customerEmail}");
        return true;
    }
}

// ── THE ENTRY POINT — the IoC wiring function ────────────────────────────────
// This is the ONLY place where `new` is called on services.
// All control lives here. Nothing else reaches out.

echo "Entry point (IoC wiring function):\n";

// ONE database, ONE logger, ONE mailer — shared across the whole graph
$db     = new MySQLDatabaseDI('mysql:host=localhost;dbname=shop');
$logger = new FileLoggerDI('/var/log/app.log');
$mailer = new SmtpMailerDI('smtp.example.com', 587);

// Build the graph — each class receives what it declared it needs
$products = new ProductRepository($db, $logger);
$service  = new OrderService($products, $mailer, $logger);

echo "\nPlacing order (same business logic, clean wiring):\n";
$service->placeOrder(1, 'alice@example.com');

echo "\nAdvantages:\n";
echo "  ✓ ONE database connection, ONE logger — shared across everything\n";
echo "  ✓ Test: inject FakeDatabase, NullLogger, SpyMailer — zero infrastructure\n";
echo "  ✓ Swap to PostgreSQL: change ONE line in the entry point\n";
echo "  ✓ The entry point is in control — classes just declare their needs\n\n";

echo "── The inversion ────────────────────────────────────\n\n";
echo "WITHOUT IoC:\n";
echo "  OldOrderService → calls new → OldProductRepository → calls new → MySQLDatabase\n";
echo "  Control flows OUTWARD from each class — each class decides what it needs\n\n";
echo "WITH IoC:\n";
echo "  Entry point → creates MySQLDatabase → passes to ProductRepository\n";
echo "             → creates OrderService with ProductRepository + Mailer + Logger\n";
echo "  Control flows INWARD — the entry point decides and provides\n\n";
echo "Hollywood Principle: Don't call us — we'll call you.\n";
echo "  Classes don't call new on services — the entry point calls them into existence.\n";