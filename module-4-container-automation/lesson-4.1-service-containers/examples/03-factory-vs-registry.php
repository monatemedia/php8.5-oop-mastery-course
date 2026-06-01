<?php
declare(strict_types=1);

/**
 * Example 03 — Factory vs Registry
 * ----------------------------------
 * Same binding, different resolution behaviour.
 *
 * This example makes the factory vs singleton distinction concrete by
 * showing three real-world scenarios where getting it wrong causes bugs:
 *
 *   Scenario A: Infrastructure (DB, logger) — MUST be singleton
 *   Scenario B: Per-request objects (shopping cart) — MUST be factory
 *   Scenario C: Mixed system — some singleton, some factory
 *
 * The key insight: the mistake is almost always registering a per-request
 * object as a singleton — then it retains state from a previous user/request.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Factory vs Registry — Same Binding, Different Behaviour\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Container (from Example 02)
// ─────────────────────────────────────────────────────────────────────────────

class Container {
    private array $bindings   = [];
    private array $singletons = [];
    private array $instances  = [];

    public function bind(string $id, callable $factory): void {
        $this->bindings[$id]   = $factory;
        $this->singletons[$id] = false;
    }

    public function singleton(string $id, callable $factory): void {
        $this->bindings[$id]   = $factory;
        $this->singletons[$id] = true;
    }

    public function instance(string $id, object $object): void {
        $this->instances[$id]  = $object;
        $this->singletons[$id] = true;
    }

    public function get(string $id): mixed {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }
        if (!isset($this->bindings[$id])) {
            throw new \RuntimeException("No binding for '{$id}'");
        }
        $result = ($this->bindings[$id])($this);
        if ($this->singletons[$id] ?? false) {
            $this->instances[$id] = $result;
        }
        return $result;
    }

    public function has(string $id): bool {
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Domain classes
// ─────────────────────────────────────────────────────────────────────────────

interface LoggerInterface {
    public function log(string $level, string $message): void;
    public function getInstanceId(): string;
}

class ConsoleLogger implements LoggerInterface {
    private string $id;
    public function __construct() {
        $this->id = substr(md5(uniqid()), 0, 6);
        echo "  [NEW LOGGER] #{$this->id} created\n";
    }
    public function log(string $level, string $message): void {
        echo "  [LOG:{$level}] (logger #{$this->id}) {$message}\n";
    }
    public function getInstanceId(): string { return $this->id; }
}

class ShoppingCart {
    private array  $items = [];
    private string $id;

    public function __construct() {
        $this->id = substr(md5(uniqid()), 0, 6);
        echo "  [NEW CART] #{$this->id} created\n";
    }

    public function addItem(string $name, int $priceCents, int $qty = 1): void {
        $this->items[] = compact('name', 'priceCents', 'qty');
    }

    public function getItems(): array  { return $this->items; }
    public function getTotal(): int    { return array_sum(array_map(fn($i) => $i['priceCents'] * $i['qty'], $this->items)); }
    public function getId(): string    { return $this->id; }
    public function itemCount(): int   { return count($this->items); }
}

class EmailQueue {
    private array  $queue = [];
    private string $id;

    public function __construct() {
        $this->id = substr(md5(uniqid()), 0, 6);
        echo "  [NEW QUEUE] #{$this->id} created\n";
    }

    public function enqueue(string $to, string $subject): void {
        $this->queue[] = compact('to', 'subject');
        echo "  [QUEUE #{$this->id}] Queued: {$to} | {$subject}\n";
    }

    public function flush(): void {
        echo "  [QUEUE #{$this->id}] Flushing " . count($this->queue) . " email(s)\n";
        $this->queue = [];
    }

    public function pending(): int  { return count($this->queue); }
    public function getId(): string { return $this->id; }
}


// ═══════════════════════════════════════════════════════════
// SCENARIO A — Infrastructure should be SINGLETON
// ═══════════════════════════════════════════════════════════

echo "── Scenario A: Infrastructure — must be singleton ───\n\n";

$containerA = new Container();

// ❌ Wrong: Logger as factory — creates a new logger on every resolution
$containerA->bind(LoggerInterface::class, fn($c) => new ConsoleLogger());

echo "Resolving LoggerInterface three times (factory mode):\n";
$log1 = $containerA->get(LoggerInterface::class);
$log2 = $containerA->get(LoggerInterface::class);
$log3 = $containerA->get(LoggerInterface::class);

echo "\n";
$log1->log('INFO', 'Service A initialised');
$log2->log('INFO', 'Service B initialised');
$log3->log('INFO', 'Service C initialised');

echo "\nProblem — three separate loggers:\n";
echo "  Logger 1 ID: #{$log1->getInstanceId()}\n";
echo "  Logger 2 ID: #{$log2->getInstanceId()}\n";
echo "  Logger 3 ID: #{$log3->getInstanceId()}\n";
echo "  All three are different objects — wasteful, and if they buffer to a file,\n";
echo "  three separate file handles are opened.\n\n";

// ✅ Correct: Logger as singleton
$containerB = new Container();
$containerB->singleton(LoggerInterface::class, fn($c) => new ConsoleLogger());

echo "Resolving LoggerInterface three times (singleton mode):\n";
$slog1 = $containerB->get(LoggerInterface::class);
$slog2 = $containerB->get(LoggerInterface::class);
$slog3 = $containerB->get(LoggerInterface::class);

echo "\n";
$slog1->log('INFO', 'Service A initialised');
$slog2->log('INFO', 'Service B initialised');
$slog3->log('INFO', 'Service C initialised');

echo "\nOne shared logger (all the same object):\n";
echo "  slog1 === slog2? " . ($slog1 === $slog2 ? 'YES ✓' : 'NO') . "\n";
echo "  slog2 === slog3? " . ($slog2 === $slog3 ? 'YES ✓' : 'NO') . "\n\n";


// ═══════════════════════════════════════════════════════════
// SCENARIO B — Per-request objects should be FACTORY
// ═══════════════════════════════════════════════════════════

echo "── Scenario B: Per-request objects — must be factory ─\n\n";

$containerC = new Container();

// ❌ Wrong: ShoppingCart as singleton — state BLEEDS between users
$containerC->singleton(ShoppingCart::class, fn($c) => new ShoppingCart());

echo "Simulating two separate users — cart registered as SINGLETON (WRONG):\n\n";

echo "User Alice shops:\n";
$aliceCart = $containerC->get(ShoppingCart::class);
$aliceCart->addItem('Widget Pro', 29999, 2);
echo "  Alice's cart: " . $aliceCart->itemCount() . " item(s), total: R" . number_format($aliceCart->getTotal() / 100, 2) . "\n\n";

echo "User Bob shops (gets Alice's cart — BUG!):\n";
$bobCart = $containerC->get(ShoppingCart::class); // ← same singleton as Alice's!
echo "  Bob gets cart #{$bobCart->getId()} (same as Alice's #{$aliceCart->getId()})\n";
echo "  Bob's cart already has " . $bobCart->itemCount() . " item(s) — Alice's items leaked!\n";
$bobCart->addItem('Widget Lite', 14999, 1);
echo "  After Bob adds item: " . $bobCart->itemCount() . " item(s)\n\n";

// ✅ Correct: ShoppingCart as factory — fresh cart per user
$containerD = new Container();
$containerD->bind(ShoppingCart::class, fn($c) => new ShoppingCart());

echo "Same users — cart registered as FACTORY (correct):\n\n";

echo "User Alice shops:\n";
$aliceCart2 = $containerD->get(ShoppingCart::class);
$aliceCart2->addItem('Widget Pro', 29999, 2);
echo "  Alice's cart #{$aliceCart2->getId()}: " . $aliceCart2->itemCount() . " item(s)\n\n";

echo "User Bob shops (gets a fresh cart):\n";
$bobCart2 = $containerD->get(ShoppingCart::class); // ← NEW instance
echo "  Bob's cart #{$bobCart2->getId()}: " . $bobCart2->itemCount() . " item(s) — empty ✓\n";
$bobCart2->addItem('Widget Lite', 14999, 1);
echo "  After Bob adds item: " . $bobCart2->itemCount() . " item(s)\n";
echo "  Alice's cart unaffected: " . $aliceCart2->itemCount() . " item(s) ✓\n\n";


// ═══════════════════════════════════════════════════════════
// SCENARIO C — Mixed system
// ═══════════════════════════════════════════════════════════

echo "── Scenario C: Mixed — singleton + factory together ──\n\n";

$containerE = new Container();

// Shared infrastructure — singleton
$containerE->singleton(LoggerInterface::class, fn($c) => new ConsoleLogger());

// Per-request objects — factory
$containerE->bind(ShoppingCart::class, fn($c) => new ShoppingCart());
$containerE->bind(EmailQueue::class,   fn($c) => new EmailQueue());

// Shared logger used inside factory-created objects
$logger = $containerE->get(LoggerInterface::class);
$logger->log('INFO', "System ready");

echo "\nRequest 1 (user Alice):\n";
$cart1  = $containerE->get(ShoppingCart::class);  // fresh
$queue1 = $containerE->get(EmailQueue::class);      // fresh
$cart1->addItem('Widget Pro', 29999);
$queue1->enqueue('alice@example.com', 'Order confirmed');
$logger->log('INFO', "Alice: {$cart1->itemCount()} items in cart #{$cart1->getId()}");

echo "\nRequest 2 (user Bob):\n";
$cart2  = $containerE->get(ShoppingCart::class);   // NEW fresh instance
$queue2 = $containerE->get(EmailQueue::class);      // NEW fresh instance
$cart2->addItem('Widget Lite', 14999);
$cart2->addItem('Cable Pack',  4999);
$queue2->enqueue('bob@example.com', 'Order confirmed');
// Shared logger — same object as Request 1
$logger->log('INFO', "Bob: {$cart2->itemCount()} items in cart #{$cart2->getId()}");

echo "\nVerification:\n";
echo "  Logger shared:   " . ($containerE->get(LoggerInterface::class) === $logger ? 'YES ✓' : 'NO') . "\n";
echo "  Carts separate:  " . ($cart1 !== $cart2 ? 'YES ✓' : 'NO') . "\n";
echo "  Queues separate: " . ($queue1 !== $queue2 ? 'YES ✓' : 'NO') . "\n";
echo "  Alice's cart items: {$cart1->itemCount()} (unaffected by Bob) ✓\n";

echo "\n--- Recap ---\n";
echo "Factory:   fresh instance every get(). Use for per-request/per-user state.\n";
echo "Singleton: one instance shared forever. Use for stateless infrastructure.\n";
echo "The bug:   registering a stateful object as singleton → state bleeds between users.\n";
echo "The rule:  if it holds user/request-specific state → factory. Otherwise → singleton.\n";