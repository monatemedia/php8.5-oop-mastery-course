<?php
declare(strict_types=1);

/**
 * Example 03 — DIP vs DI: The Principle vs The Technique
 * --------------------------------------------------------
 * DIP and DI are frequently confused — even by experienced developers.
 * This example makes the distinction concrete by showing all four combinations:
 *
 *   Case A: No DIP, No DI  — the worst case
 *   Case B: DI without DIP — injection but still coupled to concretions
 *   Case C: DIP without DI — abstractions but a Service Locator anti-pattern
 *   Case D: DIP + DI       — the correct combination
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  DIP vs DI — Principle vs Technique                ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";

echo "┌────────────────────────────────────────────────────────┐\n";
echo "│  DIP = Dependency Inversion PRINCIPLE                 │\n";
echo "│        'Depend on abstractions (interfaces),          │\n";
echo "│         not concretions (concrete classes)'           │\n";
echo "│                                                        │\n";
echo "│  DI  = Dependency INJECTION                           │\n";
echo "│        'Receive dependencies from the outside         │\n";
echo "│         rather than creating them internally'         │\n";
echo "└────────────────────────────────────────────────────────┘\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Shared infrastructure
// ─────────────────────────────────────────────────────────────────────────────

interface LoggerInterface {
    public function log(string $level, string $message): void;
}

interface CacheInterface {
    public function get(string $key): mixed;
    public function set(string $key, mixed $value): void;
}

class FileLogger implements LoggerInterface {
    public function log(string $level, string $message): void {
        echo "  [FILE:{$level}] {$message}\n";
    }
}

class ConsoleLogger implements LoggerInterface {
    public function log(string $level, string $message): void {
        echo "  [CONSOLE:{$level}] {$message}\n";
    }
}

class ArrayCache implements CacheInterface {
    private array $store = [];
    public function get(string $key): mixed    { return $this->store[$key] ?? null; }
    public function set(string $key, mixed $v): void { $this->store[$key] = $v; }
}

class NullCache implements CacheInterface {
    public function get(string $key): mixed     { return null; }
    public function set(string $key, mixed $v): void {}
}

class NullLogger implements LoggerInterface {
    public function log(string $level, string $message): void {}
}


// ═══════════════════════════════════════════════════════════
// CASE A — No DIP, No DI
// Concrete type properties + creates own dependencies
// Result: tightly coupled, untestable
// ═══════════════════════════════════════════════════════════

echo "── Case A: No DIP + No DI (worst) ───────────────────\n\n";

class UserServiceA {
    private FileLogger $logger; // ❌ concrete type
    private ArrayCache $cache;  // ❌ concrete type

    public function __construct() {
        $this->logger = new FileLogger();   // ❌ creates own deps
        $this->cache  = new ArrayCache();   // ❌ creates own deps
    }

    public function getUser(int $id): array {
        $this->logger->log('INFO', "getUser({$id})");
        $cached = $this->cache->get("user:{$id}");
        if ($cached !== null) return $cached;
        $user = ['id' => $id, 'name' => "User#{$id}"];
        $this->cache->set("user:{$id}", $user);
        return $user;
    }
}

$a = new UserServiceA();
echo "UserServiceA::getUser(1):\n";
print_r($a->getUser(1));

echo "Verdict:\n";
echo "  ✗ DIP violated: properties typed as FileLogger, ArrayCache (concretions)\n";
echo "  ✗ DI missing:   creates own deps in constructor\n";
echo "  ✗ Cannot test without filesystem and real cache\n";
echo "  ✗ Cannot swap FileLogger to ConsoleLogger without editing the class\n\n";


// ═══════════════════════════════════════════════════════════
// CASE B — DI without DIP
// Dependencies injected, but typed as concrete classes
// Result: injectable but still coupled to concrete implementations
// ═══════════════════════════════════════════════════════════

echo "── Case B: DI without DIP ────────────────────────────\n\n";

class UserServiceB {
    // ❌ Concrete types — only FileLogger and ArrayCache accepted
    public function __construct(
        private FileLogger $logger,  // DI ✅ — injected from outside
        private ArrayCache $cache    // DI ✅ — injected from outside
                                     // DIP ❌ — concrete, not interface
    ) {}

    public function getUser(int $id): array {
        $this->logger->log('INFO', "getUser({$id})");
        $cached = $this->cache->get("user:{$id}");
        if ($cached !== null) return $cached;
        $user = ['id' => $id, 'name' => "User#{$id}"];
        $this->cache->set("user:{$id}", $user);
        return $user;
    }
}

$b = new UserServiceB(new FileLogger(), new ArrayCache());
echo "UserServiceB::getUser(1):\n";
$b->getUser(1);

echo "\nVerdict:\n";
echo "  ✓ DI present: dependencies injected from outside\n";
echo "  ✗ DIP missing: cannot pass ConsoleLogger — only FileLogger accepted\n";
echo "  ✗ Cannot inject a NullLogger for testing — wrong type\n";
echo "  ✗ Injection alone is not enough — the TYPE must be an interface\n\n";

// Proof: this would be a TypeError at runtime
try {
    // $bBad = new UserServiceB(new ConsoleLogger(), new NullCache());
    // ↑ TypeError: ConsoleLogger is not FileLogger
    echo "  (new UserServiceB(new ConsoleLogger(), ...) → TypeError)\n";
    echo "  The concrete type hint REJECTS other implementations.\n\n";
} catch (\TypeError $e) {
    echo "  TypeError: " . $e->getMessage() . "\n\n";
}


// ═══════════════════════════════════════════════════════════
// CASE C — DIP without DI
// Interface types but class fetches deps from a Service Locator
// Result: right types, wrong acquisition — hidden coupling to the locator
// ═══════════════════════════════════════════════════════════

echo "── Case C: DIP without DI (Service Locator anti-pattern)\n\n";

// The Service Locator — a global registry
class ServiceLocator {
    private static array $services = [];

    public static function bind(string $id, object $service): void {
        self::$services[$id] = $service;
    }

    public static function get(string $id): object {
        return self::$services[$id] ?? throw new \RuntimeException("Not found: {$id}");
    }
}

// Register services globally
ServiceLocator::bind(LoggerInterface::class, new FileLogger());
ServiceLocator::bind(CacheInterface::class,  new ArrayCache());

class UserServiceC {
    private LoggerInterface $logger; // ✅ interface type (DIP)
    private CacheInterface  $cache;  // ✅ interface type (DIP)

    public function __construct() {
        // ❌ Not DI — reaches into a global registry
        // DIP in the types but Service Locator for acquisition
        $this->logger = ServiceLocator::get(LoggerInterface::class);
        $this->cache  = ServiceLocator::get(CacheInterface::class);
    }

    public function getUser(int $id): array {
        $this->logger->log('INFO', "getUser({$id})");
        $user = ['id' => $id, 'name' => "User#{$id}"];
        $this->cache->set("user:{$id}", $user);
        return $user;
    }
}

$c = new UserServiceC();
echo "UserServiceC::getUser(1):\n";
$c->getUser(1);

echo "\nVerdict:\n";
echo "  ✓ DIP present: interface type hints — any conforming impl accepted\n";
echo "  ✗ DI missing: deps fetched from a global Service Locator\n";
echo "  ✗ Hidden dependency: class depends on ServiceLocator itself\n";
echo "  ✗ Test challenge: must pre-populate ServiceLocator before test runs\n";
echo "  ✗ Not transparent: constructor signature shows nothing about deps\n\n";


// ═══════════════════════════════════════════════════════════
// CASE D — DIP + DI (the correct combination)
// Interface types + injected from outside
// Result: fully decoupled, testable, container-ready
// ═══════════════════════════════════════════════════════════

echo "── Case D: DIP + DI (correct) ───────────────────────\n\n";

class UserServiceD {
    public function __construct(
        private LoggerInterface $logger, // ✅ interface type (DIP)
        private CacheInterface  $cache   // ✅ interface type (DIP)
                                         // ✅ injected from outside (DI)
    ) {}

    public function getUser(int $id): array {
        $this->logger->log('INFO', "getUser({$id})");
        $cached = $this->cache->get("user:{$id}");
        if ($cached !== null) {
            $this->logger->log('INFO', "Cache hit: user:{$id}");
            return $cached;
        }
        $user = ['id' => $id, 'name' => "User#{$id}"];
        $this->cache->set("user:{$id}", $user);
        return $user;
    }
}

// Production
$dProd = new UserServiceD(new FileLogger(), new ArrayCache());
echo "Production:\n";
$dProd->getUser(1);

// Staging — different logger, same cache
$dStage = new UserServiceD(new ConsoleLogger(), new ArrayCache());
echo "\nStaging:\n";
$dStage->getUser(2);

// Testing — null implementations, spy
$spyLogger = new class implements LoggerInterface {
    public array $entries = [];
    public function log(string $level, string $message): void {
        $this->entries[] = compact('level', 'message');
    }
};
$dTest = new UserServiceD($spyLogger, new NullCache());
echo "\nTest:\n";
$dTest->getUser(3);
echo "  Log entries captured: " . count($spyLogger->entries) . "\n";
echo "  (Zero infrastructure — pure logic test)\n\n";

echo "Verdict:\n";
echo "  ✓ DIP: interface type hints — any conforming impl accepted\n";
echo "  ✓ DI:  all deps injected from outside the class\n";
echo "  ✓ Testable: inject NullLogger, NullCache, SpyLogger — no infra needed\n";
echo "  ✓ Swappable: change any dep without touching UserServiceD\n";
echo "  ✓ Container-ready: Reflection reads the interface types and wires automatically\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Summary table
// ─────────────────────────────────────────────────────────────────────────────

echo "── Summary ──────────────────────────────────────────\n\n";
echo "  Case │ DI  │ DIP │ Testable │ Swappable │ Container-ready\n";
echo "  ─────┼─────┼─────┼──────────┼───────────┼────────────────\n";
echo "  A    │ ✗   │ ✗   │ ✗        │ ✗         │ ✗\n";
echo "  B    │ ✓   │ ✗   │ Partial  │ ✗         │ No (concrete)\n";
echo "  C    │ ✗   │ ✓   │ Partial  │ ✓         │ No (locator)\n";
echo "  D    │ ✓   │ ✓   │ ✓        │ ✓         │ ✓\n\n";

echo "DIP tells you WHAT to type-hint: interfaces, not concrete classes.\n";
echo "DI  tells you HOW to receive deps: constructor/setter, not new/locator.\n";
echo "You need BOTH to write clean, testable, container-ready code.\n";