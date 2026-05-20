<?php
declare(strict_types=1);

/**
 * CHALLENGE SOLUTION — Lesson 3.3: Setter & Interface Injection
 * ─────────────────────────────────────────────────────────────
 * ⚠️  Only open this file after completing starter.php yourself.
 *
 * Key things to compare in your solution:
 *   1. NullLogger, NullCache, NullDispatcher defined correctly
 *   2. LoggerAwareInterface + LoggerAwareTrait used for logger
 *   3. InvoiceService constructor defaults all optional deps to Null Objects
 *   4. setCache() and setDispatcher() are fluent (return static)
 *   5. generate() and processPayment() use direct calls (no ?->)
 *   6. Three wiring contexts all produce the correct output
 */


// ─────────────────────────────────────────────────────────────────────────────
// Core interfaces — unchanged
// ─────────────────────────────────────────────────────────────────────────────

interface DatabaseInterface {
    public function query(string $sql, array $params = []): array;
    public function execute(string $sql, array $params = []): bool;
}

interface PaymentGatewayInterface {
    public function charge(float $amount, string $token): bool;
}

interface LoggerInterface {
    public function log(string $level, string $message): void;
}

interface CacheInterface {
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, int $ttl = 300): void;
    public function has(string $key): bool;
}

interface EventDispatcherInterface {
    public function dispatch(string $event, array $payload = []): void;
}


// ─────────────────────────────────────────────────────────────────────────────
// Task 1 — Null Object implementations
// ─────────────────────────────────────────────────────────────────────────────

class NullLogger implements LoggerInterface {
    public function log(string $level, string $message): void {
        // Intentionally silent
    }
}

class NullCache implements CacheInterface {
    public function get(string $key): mixed  { return null; }
    public function set(string $key, mixed $value, int $ttl = 300): void {}
    public function has(string $key): bool   { return false; }
}

class NullDispatcher implements EventDispatcherInterface {
    public function dispatch(string $event, array $payload = []): void {
        // Intentionally silent
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Task 2 — LoggerAwareInterface + LoggerAwareTrait
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


// ─────────────────────────────────────────────────────────────────────────────
// Concrete implementations — unchanged from starter
// ─────────────────────────────────────────────────────────────────────────────

class ConsoleLogger implements LoggerInterface {
    public function log(string $level, string $message): void {
        echo "  [{$level}] {$message}\n";
    }
}

class ArrayCache implements CacheInterface {
    private array $store = [];

    public function get(string $key): mixed {
        $hit = isset($this->store[$key]);
        echo "  [CACHE] " . ($hit ? 'HIT' : 'MISS') . ": {$key}\n";
        return $this->store[$key] ?? null;
    }

    public function set(string $key, mixed $value, int $ttl = 300): void {
        $this->store[$key] = $value;
        echo "  [CACHE] SET: {$key}\n";
    }

    public function has(string $key): bool {
        return isset($this->store[$key]);
    }
}

class ConsoleDispatcher implements EventDispatcherInterface {
    public function dispatch(string $event, array $payload = []): void {
        echo "  [EVENT] {$event}: " . json_encode($payload) . "\n";
    }
}

class InMemoryDb implements DatabaseInterface {
    private array $invoices = [];

    public function query(string $sql, array $params = []): array {
        if (!empty($params) && isset($this->invoices[$params[0]])) {
            return [$this->invoices[$params[0]]];
        }
        return array_values($this->invoices);
    }

    public function execute(string $sql, array $params = []): bool {
        if (str_contains($sql, 'INSERT') && count($params) >= 2) {
            $this->invoices[$params[0]] = [
                'id'     => $params[0],
                'total'  => $params[1],
                'status' => 'pending',
            ];
            return true;
        }
        if (str_contains($sql, 'UPDATE') && !empty($params)) {
            if (isset($this->invoices[$params[1]])) {
                $this->invoices[$params[1]]['status'] = $params[0];
            }
            return true;
        }
        return false;
    }
}

class FakePaymentGateway implements PaymentGatewayInterface {
    public function charge(float $amount, string $token): bool {
        return true;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Tasks 2–5 — InvoiceService fully refactored
// ─────────────────────────────────────────────────────────────────────────────

class InvoiceService implements LoggerAwareInterface {
    use LoggerAwareTrait {
        setLogger as public; // expose public so container/caller can call it
    }

    // Task 3: optional cache
    private CacheInterface $cache;

    // Task 4: optional dispatcher
    private EventDispatcherInterface $dispatcher;

    public function __construct(
        private DatabaseInterface       $db,      // Required
        private PaymentGatewayInterface $gateway  // Required
    ) {
        // Task 2: interface injection default
        $this->logger     = new NullLogger();

        // Task 3: setter injection default
        $this->cache      = new NullCache();

        // Task 4: setter injection default
        $this->dispatcher = new NullDispatcher();
    }

    // Task 3 — fluent setter for cache
    public function setCache(CacheInterface $cache): static {
        $this->cache = $cache;
        return $this;
    }

    // Task 4 — fluent setter for dispatcher
    public function setDispatcher(EventDispatcherInterface $dispatcher): static {
        $this->dispatcher = $dispatcher;
        return $this;
    }

    // Task 5 — generate() with logging, caching, and events
    public function generate(string $invoiceId, array $lineItems): array {
        $this->logger->log('INFO', "Generating invoice #{$invoiceId}");

        // Check cache first
        $cacheKey = "invoice:{$invoiceId}";
        $cached   = $this->cache->get($cacheKey);
        if ($cached !== null) {
            $this->logger->log('INFO', "Returning cached invoice #{$invoiceId}");
            return $cached;
        }

        $total = array_sum(array_column($lineItems, 'subtotal'));

        $this->db->execute(
            'INSERT INTO invoices (id, total) VALUES (?, ?)',
            [$invoiceId, $total]
        );

        $invoice = [
            'id'    => $invoiceId,
            'total' => $total,
            'items' => $lineItems,
        ];

        // Cache the result
        $this->cache->set($cacheKey, $invoice);

        // Dispatch event
        $this->dispatcher->dispatch('invoice.generated', [
            'id'    => $invoiceId,
            'total' => $total,
        ]);

        $this->logger->log('INFO', "Invoice #{$invoiceId} generated. Total: R" . number_format($total, 2));
        echo "Invoice #{$invoiceId} generated. Total: R" . number_format($total, 2) . "\n";

        return $invoice;
    }

    // Task 5 — processPayment() with logging and events
    public function processPayment(string $invoiceId, string $token): bool {
        $this->logger->log('INFO', "Processing payment for #{$invoiceId}");

        $rows = $this->db->query('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
        if (empty($rows)) {
            $this->logger->log('ERROR', "Invoice #{$invoiceId} not found");
            return false;
        }

        $invoice = $rows[0];
        $charged = $this->gateway->charge($invoice['total'], $token);

        if ($charged) {
            $this->db->execute(
                'UPDATE invoices SET status = ? WHERE id = ?',
                ['paid', $invoiceId]
            );

            $this->dispatcher->dispatch('invoice.paid', ['id' => $invoiceId]);
            $this->logger->log('INFO', "Payment for #{$invoiceId}: success");
            echo "Payment for #{$invoiceId}: success\n";
        } else {
            $this->logger->log('ERROR', "Payment for #{$invoiceId}: failed");
        }

        return $charged;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Task 6 — Three wiring contexts
// ─────────────────────────────────────────────────────────────────────────────

$items1 = [
    ['name' => 'Widget Pro',  'qty' => 2, 'price' => 299.99, 'subtotal' => 599.98],
    ['name' => 'Widget Lite', 'qty' => 3, 'price' => 300.00, 'subtotal' => 900.00],
];

$items2 = [
    ['name' => 'Gadget X',    'qty' => 1, 'price' => 149.99, 'subtotal' => 149.99],
    ['name' => 'Widget Pro',  'qty' => 6, 'price' => 299.99, 'subtotal' => 1799.94],
    ['name' => 'Cable Pack',  'qty' => 2, 'price' => 525.02, 'subtotal' => 1050.04],
];

// ── Context 1: Minimal — only required deps ────────────────────────────────

echo "=== Context 1: Minimal (Null Objects) ===\n\n";

$db1      = new InMemoryDb();
$gateway1 = new FakePaymentGateway();

// All optional deps default to NullObjects — no setters called
$service1 = new InvoiceService($db1, $gateway1);

$invoice1 = $service1->generate('INV-001', $items1);
$service1->processPayment('INV-001', 'tok_abc123');


// ── Context 2: Full production ─────────────────────────────────────────────

echo "\n=== Context 2: Full production ===\n\n";

$db2      = new InMemoryDb();
$gateway2 = new FakePaymentGateway();

$service2 = new InvoiceService($db2, $gateway2);
// Interface injection (simulated — container would do this automatically)
$service2->setLogger(new ConsoleLogger());
// Setter injection — caller opts in
$service2->setCache(new ArrayCache());
$service2->setDispatcher(new ConsoleDispatcher());

$invoice2 = $service2->generate('INV-002', $items2);
$service2->processPayment('INV-002', 'tok_def456');


// ── Context 3: Test — spy assertions ──────────────────────────────────────

echo "\n=== Context 3: Test (spy assertions) ===\n\n";

$db3      = new InMemoryDb();
$gateway3 = new FakePaymentGateway();

// Spy logger
$spyLogger = new class implements LoggerInterface {
    public array $entries = [];
    public function log(string $level, string $message): void {
        $this->entries[] = compact('level', 'message');
    }
};

// Spy dispatcher
$spyDispatcher = new class implements EventDispatcherInterface {
    public array $events = [];
    public function dispatch(string $event, array $payload = []): void {
        $this->events[] = compact('event', 'payload');
    }
};

$service3 = new InvoiceService($db3, $gateway3);
$service3->setLogger($spyLogger);
$service3->setDispatcher($spyDispatcher);
// Cache stays as NullCache — not needed for this test

$service3->generate('INV-003', $items1);
$service3->processPayment('INV-003', 'tok_ghi789');

// Assertions
$assertions = [
    'Logger captured 4 entries'      => count($spyLogger->entries) === 4,
    'Dispatcher received 2 events'   => count($spyDispatcher->events) === 2,
    'First event is invoice.generated' => $spyDispatcher->events[0]['event'] === 'invoice.generated',
    'Second event is invoice.paid'   => $spyDispatcher->events[1]['event'] === 'invoice.paid',
    'Logger has INFO entries'        => count(array_filter(
        $spyLogger->entries, fn($e) => $e['level'] === 'INFO'
    )) >= 4,
];

echo "  Spy logger entries: " . count($spyLogger->entries) . "\n";
echo "  Spy dispatcher events: " . count($spyDispatcher->events) . "\n";
echo "  First event: " . $spyDispatcher->events[0]['event'] . "\n\n";

$allPassed = true;
foreach ($assertions as $label => $result) {
    echo "  " . ($result ? '✓' : '✗') . " {$label}\n";
    if (!$result) $allPassed = false;
}
echo "\n" . ($allPassed ? "  All assertions PASSED" : "  Some assertions FAILED") . "\n";


// ─────────────────────────────────────────────────────────────────────────────
// SELF-REVIEW CHECKLIST
// ─────────────────────────────────────────────────────────────────────────────
echo "\n--- Self-review checklist ---\n";
echo "[ ] NullLogger, NullCache, NullDispatcher all defined?\n";
echo "[ ] LoggerAwareInterface declares setLogger()?\n";
echo "[ ] LoggerAwareTrait provides the setLogger() implementation?\n";
echo "[ ] InvoiceService implements LoggerAwareInterface + uses LoggerAwareTrait?\n";
echo "[ ] Constructor defaults all three optional deps to Null Objects?\n";
echo "[ ] setCache() and setDispatcher() return static (fluent)?\n";
echo "[ ] generate() and processPayment() use direct calls — no ?-> anywhere?\n";
echo "[ ] Context 1 produces no log/cache/event output?\n";
echo "[ ] Context 2 produces full log/cache/event output?\n";
echo "[ ] Context 3 spy assertions all pass?\n";