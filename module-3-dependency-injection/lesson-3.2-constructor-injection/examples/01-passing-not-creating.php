<?php
declare(strict_types=1);

/**
 * Example 01 — Passing, Not Creating
 * ------------------------------------
 * The entire Dependency Injection principle in one clear before/after.
 * Same class, same behaviour, same output — different responsibility.
 *
 * BEFORE: class creates its own dependencies (tight coupling)
 * AFTER:  class receives its dependencies (loose coupling / DI)
 *
 * The business logic does not change. Only who is responsible for
 * building the dependencies changes.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Passing, Not Creating — The DI Principle           ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Infrastructure: the things that need to be injected
// ─────────────────────────────────────────────────────────────────────────────

class FileLogger {
    public function __construct(private string $path) {
        echo "  [FILE-LOG] Opened: {$path}\n";
    }
    public function log(string $level, string $message): void {
        echo "  [FILE-LOG:{$level}] {$message}\n";
    }
}

class ConsoleLogger {
    public function log(string $level, string $message): void {
        echo "  [CONSOLE:{$level}] {$message}\n";
    }
}

class NullLogger {
    public function log(string $level, string $message): void {
        // Silent — does nothing
    }
}

class InMemoryDatabase {
    private array $data = [
        1 => ['id' => 1, 'email' => 'alice@example.com', 'name' => 'Alice', 'role' => 'admin'],
        2 => ['id' => 2, 'email' => 'bob@example.com',   'name' => 'Bob',   'role' => 'user'],
    ];

    public function query(string $sql, array $params = []): array {
        // Very simple simulation
        if (isset($params[0]) && is_int($params[0])) {
            return isset($this->data[$params[0]]) ? [$this->data[$params[0]]] : [];
        }
        return array_values($this->data);
    }

    public function execute(string $sql, array $params = []): bool {
        echo "  [MEM-DB] Execute: " . substr($sql, 0, 50) . "\n";
        return true;
    }
}


// ═══════════════════════════════════════════════════════════
// BEFORE — class creates its own dependencies
// ═══════════════════════════════════════════════════════════

echo "── BEFORE: Class creates its own dependencies ────────\n\n";

class TightUserService {
    private FileLogger       $logger;
    private InMemoryDatabase $db;

    public function __construct() {
        // The class decides: FileLogger at this path, InMemoryDatabase
        // These decisions are buried inside the class — invisible from outside
        $this->logger = new FileLogger('/var/log/users.log');
        $this->db     = new InMemoryDatabase();
    }

    public function findUser(int $id): ?array {
        $this->logger->log('INFO', "Finding user #{$id}");
        $rows = $this->db->query('SELECT * FROM users WHERE id = ?', [$id]);
        return $rows[0] ?? null;
    }

    public function listUsers(): array {
        $this->logger->log('INFO', "Listing all users");
        return $this->db->query('SELECT * FROM users');
    }
}

echo "Creating TightUserService (watch what is triggered):\n";
$tight = new TightUserService();

echo "\nfindUser(1):\n";
$user = $tight->findUser(1);
echo "  Result: {$user['name']} ({$user['email']})\n";

echo "\nlistUsers():\n";
$users = $tight->listUsers();
echo "  Count: " . count($users) . "\n";

echo "\nProblems with this approach:\n";
echo "  ✗ Cannot test without /var/log/users.log write access\n";
echo "  ✗ Cannot swap to ConsoleLogger or NullLogger for tests\n";
echo "  ✗ Cannot swap to a real database without editing TightUserService\n";
echo "  ✗ 'Creating TightUserService' above IMMEDIATELY opened a log file\n";


// ═══════════════════════════════════════════════════════════
// AFTER — class receives its dependencies (DI)
// ═══════════════════════════════════════════════════════════

echo "\n── AFTER: Class receives its dependencies (DI) ───────\n\n";

interface LoggerInterface {
    public function log(string $level, string $message): void;
}

interface DatabaseInterface {
    public function query(string $sql, array $params = []): array;
    public function execute(string $sql, array $params = []): bool;
}

// Implementations now implement the interfaces
class FileLoggerDI extends FileLogger implements LoggerInterface {}
class ConsoleLoggerDI extends ConsoleLogger implements LoggerInterface {}
class NullLoggerDI extends NullLogger implements LoggerInterface {}
class InMemoryDatabaseDI extends InMemoryDatabase implements DatabaseInterface {}

// The service: receives, does not create
class LooseUserService {
    public function __construct(
        private DatabaseInterface $db,    // ← interface, not InMemoryDatabase
        private LoggerInterface   $logger // ← interface, not FileLogger
    ) {
        // Nothing is created here — everything already exists
        // This constructor cannot fail due to infrastructure issues
    }

    public function findUser(int $id): ?array {
        $this->logger->log('INFO', "Finding user #{$id}");
        $rows = $this->db->query('SELECT * FROM users WHERE id = ?', [$id]);
        return $rows[0] ?? null;
    }

    public function listUsers(): array {
        $this->logger->log('INFO', "Listing all users");
        return $this->db->query('SELECT * FROM users');
    }
}

echo "── Context 1: Production (file logger + real DB wiring) ──\n\n";
// The caller decides which concrete classes to use
$prodDb     = new InMemoryDatabaseDI();  // In real life: new MySQLDatabase(...)
$prodLogger = new FileLoggerDI('/var/log/users.log');
$prodService = new LooseUserService($prodDb, $prodLogger);

$user = $prodService->findUser(2);
echo "  Found: {$user['name']}\n";

echo "\n── Context 2: Console/CLI (console logger) ──\n\n";
$consoleLogger = new ConsoleLoggerDI();
$cliService    = new LooseUserService($prodDb, $consoleLogger);
$cliService->findUser(1);

echo "\n── Context 3: Tests (null logger — silent, fast) ──\n\n";
$nullLogger  = new NullLoggerDI();
$fakeDb      = new class implements DatabaseInterface {
    public function query(string $sql, array $params = []): array {
        return [['id' => 99, 'email' => 'test@example.com', 'name' => 'Test User', 'role' => 'user']];
    }
    public function execute(string $sql, array $params = []): bool { return true; }
};

$testService = new LooseUserService($fakeDb, $nullLogger);
$testUser    = $testService->findUser(99);
echo "  Test result: {$testUser['name']} — no log output (NullLogger)\n";

$allUsers = $testService->listUsers();
echo "  listUsers() returned " . count($allUsers) . " result(s)\n";


// ─────────────────────────────────────────────────────────────────────────────
// The core insight
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── The core insight ─────────────────────────────────\n\n";

echo "The business logic in findUser() and listUsers() is IDENTICAL\n";
echo "in TightUserService and LooseUserService.\n\n";

echo "What changed:\n";
echo "  Before: TightUserService CREATES its dependencies\n";
echo "          → it owns the decision of which classes to use\n";
echo "          → it cannot be used in any context other than production\n\n";

echo "  After:  LooseUserService RECEIVES its dependencies\n";
echo "          → the caller owns the decision of which classes to use\n";
echo "          → it works in production, testing, CLI, and any other context\n\n";

echo "DI in one sentence:\n";
echo "  'Don't create what you need — ask for it.'\n";

echo "\n--- Recap ---\n";
echo "DI principle: classes receive dependencies, they do not create them.\n";
echo "Constructor injection: pass dependencies as constructor parameters.\n";
echo "Type-hint against interfaces: accept any conforming implementation.\n";
echo "Caller decides: which concrete class to use is the caller's responsibility.\n";
echo "Composition root: the one place in the app where 'new' is called on services.\n";