<?php
declare(strict_types=1);

/**
 * Example 04 — When to Use Which Pattern
 * ----------------------------------------
 * All three injection patterns on the same service, showing why
 * each dependency was assigned to its pattern.
 *
 * Constructor → required, class cannot function without it
 * Setter      → optional, class has a safe default
 * Interface   → framework/PSR contract, container wires it automatically
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  When to Use Which Injection Pattern                ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Interfaces
// ─────────────────────────────────────────────────────────────────────────────

interface DatabaseInterface {
    public function query(string $sql, array $params = []): array;
    public function execute(string $sql, array $params = []): bool;
}

interface PaymentGatewayInterface {
    public function charge(float $amount, string $token): bool;
    public function refund(string $transactionId): bool;
}

interface LoggerInterface {
    public function log(string $level, string $message): void;
}

interface CacheInterface {
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, int $ttl = 300): void;
}

interface EventDispatcherInterface {
    public function dispatch(string $event, array $payload = []): void;
}

// Null Objects
class NullLogger implements LoggerInterface {
    public function log(string $level, string $message): void {}
}

class NullCache implements CacheInterface {
    public function get(string $key): mixed  { return null; }
    public function set(string $key, mixed $value, int $ttl = 300): void {}
}

class NullDispatcher implements EventDispatcherInterface {
    public function dispatch(string $event, array $payload = []): void {}
}

// Implementations
class ConsoleLogger implements LoggerInterface {
    public function log(string $level, string $message): void {
        echo "  [{$level}] {$message}\n";
    }
}

class ArrayCache implements CacheInterface {
    private array $store = [];
    public function get(string $key): mixed {
        echo "  [CACHE] " . (isset($this->store[$key]) ? 'HIT' : 'MISS') . ": {$key}\n";
        return $this->store[$key] ?? null;
    }
    public function set(string $key, mixed $value, int $ttl = 300): void {
        $this->store[$key] = $value;
        echo "  [CACHE] SET: {$key}\n";
    }
}

class SimpleDispatcher implements EventDispatcherInterface {
    public function dispatch(string $event, array $payload = []): void {
        echo "  [EVENT] {$event}: " . json_encode($payload) . "\n";
    }
}

class InMemoryDb implements DatabaseInterface {
    private array $orders = [];
    public function query(string $sql, array $params = []): array {
        if (!empty($params)) {
            return isset($this->orders[$params[0]])
                ? [$this->orders[$params[0]]]
                : [];
        }
        return array_values($this->orders);
    }
    public function execute(string $sql, array $params = []): bool {
        if (str_contains($sql, 'INSERT') && !empty($params)) {
            $this->orders[$params[0]] = ['id' => $params[0], 'total' => $params[1] ?? 0];
            echo "  [DB] INSERT order #{$params[0]}\n";
        }
        return true;
    }
}

class FakeGateway implements PaymentGatewayInterface {
    public function charge(float $amount, string $token): bool {
        echo "  [GATEWAY] Charged R{$amount} token={$token}\n";
        return true;
    }
    public function refund(string $transactionId): bool {
        echo "  [GATEWAY] Refunded {$transactionId}\n";
        return true;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Interface injection "aware" interface + trait
// ─────────────────────────────────────────────────────────────────────────────

interface LoggerAwareInterface {
    public function setLogger(LoggerInterface $logger): void;
}

trait LoggerAwareTrait {
    protected LoggerInterface $logger;
    public function setLogger(LoggerInterface $logger): void {
        $this->logger = $logger;
    }
}


// ═══════════════════════════════════════════════════════════
// THE SERVICE — uses all three injection patterns deliberately
// ═══════════════════════════════════════════════════════════

class OrderService implements LoggerAwareInterface {
    use LoggerAwareTrait {
        setLogger as public; // expose the setter
    }

    // ── Optional deps — Null Object defaults ────────────────
    private CacheInterface           $cache;
    private EventDispatcherInterface $dispatcher;

    public function __construct(
        // ── CONSTRUCTOR INJECTION ─────────────────────────────
        // These are REQUIRED. The class cannot function without them.
        // A payment service without a payment gateway is useless.
        // A data service without a database is useless.
        private DatabaseInterface       $db,       // Required — no DB = no orders
        private PaymentGatewayInterface $gateway   // Required — no gateway = no charging
    ) {
        // ── INTERFACE INJECTION baseline ──────────────────────
        // logger is managed by LoggerAwareTrait — NullLogger by default
        $this->logger     = new NullLogger();

        // ── SETTER INJECTION defaults ─────────────────────────
        // Cache and dispatcher are optional — NullObjects by default
        $this->cache      = new NullCache();
        $this->dispatcher = new NullDispatcher();
    }

    // ── SETTER INJECTION ──────────────────────────────────────
    // Optional — class works without these, but caller can enhance it

    public function setCache(CacheInterface $cache): static {
        $this->cache = $cache;
        return $this;
    }

    public function setDispatcher(EventDispatcherInterface $dispatcher): static {
        $this->dispatcher = $dispatcher;
        return $this;
    }

    // Business logic — uses all dependencies, never checks for null
    public function placeOrder(array $cart, string $token): array {
        $this->logger->log('INFO', "placeOrder started");

        // Check cache for recent order
        $cacheKey = "cart:" . md5(json_encode($cart));
        if ($this->cache->get($cacheKey) !== null) {
            $this->logger->log('WARN', "Duplicate order detected");
            return ['success' => false, 'error' => 'Duplicate order'];
        }

        $total = array_sum(array_column($cart, 'price'));
        $charged = $this->gateway->charge($total, $token);

        if (!$charged) {
            $this->logger->log('ERROR', "Payment failed");
            return ['success' => false, 'error' => 'Payment failed'];
        }

        $orderId = rand(10000, 99999);
        $this->db->execute(
            'INSERT INTO orders (id, total) VALUES (?, ?)',
            [$orderId, $total]
        );

        $this->cache->set($cacheKey, ['order_id' => $orderId], 60);

        $this->dispatcher->dispatch('order.placed', [
            'order_id' => $orderId,
            'total'    => $total,
        ]);

        $this->logger->log('INFO', "Order #{$orderId} placed. Total: R{$total}");
        return ['success' => true, 'order_id' => $orderId, 'total' => $total];
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// DECISION AUDIT — why each dep got its injection pattern
// ─────────────────────────────────────────────────────────────────────────────

echo "── Decision audit: why each pattern was chosen ──────\n\n";

echo "DatabaseInterface      → CONSTRUCTOR\n";
echo "  Reason: No database = no orders. The class is meaningless without it.\n";
echo "  Test:   Pass an in-memory DB. Required dep — always in the constructor.\n\n";

echo "PaymentGatewayInterface → CONSTRUCTOR\n";
echo "  Reason: No gateway = no charging. This IS the core responsibility.\n";
echo "  Test:   Pass a fake gateway. Required dep — always in the constructor.\n\n";

echo "LoggerInterface        → INTERFACE INJECTION (LoggerAwareTrait)\n";
echo "  Reason: Logging is a cross-cutting concern, not a core dep.\n";
echo "          PSR-3 standard — container injects via LoggerAwareInterface.\n";
echo "          NullLogger default means the class works with zero wiring.\n\n";

echo "CacheInterface         → SETTER INJECTION\n";
echo "  Reason: Cache is a performance enhancement, not a requirement.\n";
echo "          NullCache default means the class works uncached.\n";
echo "          Caller opts in at the composition root.\n\n";

echo "EventDispatcherInterface → SETTER INJECTION\n";
echo "  Reason: Events are optional side effects — not core logic.\n";
echo "          NullDispatcher default means events can be silently ignored.\n";
echo "          Only add when something actually listens to these events.\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Three wiring contexts — same class, different behaviour
// ─────────────────────────────────────────────────────────────────────────────

$db      = new InMemoryDb();
$gateway = new FakeGateway();
$cart    = [['name' => 'Widget', 'price' => 500.00]];

echo "── Context 1: Minimal (required deps only) ──────────\n\n";
$service1 = new OrderService($db, $gateway);
$result1  = $service1->placeOrder($cart, 'tok_abc');
echo "  Result: " . ($result1['success'] ? "Order #{$result1['order_id']}" : $result1['error']) . "\n";
echo "  (Silent — NullLogger, NullCache, NullDispatcher)\n\n";


echo "── Context 2: Full production ────────────────────────\n\n";
$service2 = new OrderService($db, $gateway);
// Interface injection (simulated — container would do this automatically)
$service2->setLogger(new ConsoleLogger());
// Setter injection (caller opts in)
$service2->setCache(new ArrayCache());
$service2->setDispatcher(new SimpleDispatcher());

$result2 = $service2->placeOrder($cart, 'tok_def');
echo "  Result: " . ($result2['success'] ? "Order #{$result2['order_id']}" : $result2['error']) . "\n\n";


echo "── Context 3: Test (spy on specific behaviour) ───────\n\n";

$spyDispatcher = new class implements EventDispatcherInterface {
    public array $events = [];
    public function dispatch(string $event, array $payload = []): void {
        $this->events[] = compact('event', 'payload');
    }
};

$service3 = new OrderService(new InMemoryDb(), new FakeGateway());
$service3->setDispatcher($spyDispatcher);

$result3 = $service3->placeOrder([['name' => 'Test Item', 'price' => 100.00]], 'tok_test');

echo "  Dispatched events: " . count($spyDispatcher->events) . "\n";
if (!empty($spyDispatcher->events)) {
    echo "  First event: " . $spyDispatcher->events[0]['event'] . "\n";
}

echo "\n── The decision table ───────────────────────────────\n\n";
echo "  'Can this class do its PRIMARY JOB without this dep?'\n";
echo "  NO  → Constructor injection\n\n";
echo "  'Is this a framework/cross-cutting dep (logging, events)?'\n";
echo "  YES → Interface injection (Aware interface + trait)\n\n";
echo "  'Is this optional — class works fine, but dep adds value?'\n";
echo "  YES → Setter injection (NullObject default)\n\n";
echo "  'Is this a per-call detail (not per-instance)?'\n";
echo "  YES → Method parameter (not injection at all)\n";

echo "\n--- Recap ---\n";
echo "Constructor:  required deps — class cannot work without them.\n";
echo "Setter:       optional deps — NullObject default, caller opts in.\n";
echo "Interface:    framework/PSR deps — container calls setter automatically.\n";
echo "NullObject:   always use as the setter default — eliminates null checks.\n";
echo "Method arg:   per-call values (user ID, amount) — not injection.\n";