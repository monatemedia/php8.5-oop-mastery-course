<?php
declare(strict_types=1);

/**
 * CHALLENGE STARTER — Lesson 3.3: Setter & Interface Injection
 * ─────────────────────────────────────────────────────────────
 * Read CHALLENGE.md before touching this file.
 *
 * InvoiceService already uses constructor injection correctly.
 * Your job: add optional logging, caching, and event dispatching
 * using setter injection (with Null Object defaults) and interface
 * injection (LoggerAwareInterface + trait) for the logger.
 *
 * Do NOT look at solution.php until you have made a genuine attempt.
 */


// ─────────────────────────────────────────────────────────────────────────────
// CORE INTERFACES (already defined — do not change these)
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
// TODO Task 1: Define Null Object implementations
// NullLogger, NullCache, NullDispatcher
// ─────────────────────────────────────────────────────────────────────────────


// ─────────────────────────────────────────────────────────────────────────────
// TODO Task 2: Define LoggerAwareInterface and LoggerAwareTrait
// ─────────────────────────────────────────────────────────────────────────────


// ─────────────────────────────────────────────────────────────────────────────
// CONCRETE IMPLEMENTATIONS (provided — do not change internals)
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
        return true; // Always succeeds
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// InvoiceService — refactor this class (Tasks 2–5)
// ─────────────────────────────────────────────────────────────────────────────

class InvoiceService {  // TODO Task 2: implements LoggerAwareInterface
                        // TODO Task 2: use LoggerAwareTrait

    // TODO Task 2: the trait adds protected LoggerInterface $logger
    // TODO Task 3: add private CacheInterface $cache
    // TODO Task 4: add private EventDispatcherInterface $dispatcher

    public function __construct(
        // Required deps — constructor injection (already correct)
        private DatabaseInterface       $db,
        private PaymentGatewayInterface $gateway
    ) {
        // TODO Task 2: $this->logger     = new NullLogger();
        // TODO Task 3: $this->cache      = new NullCache();
        // TODO Task 4: $this->dispatcher = new NullDispatcher();
    }

    // TODO Task 3: add setCache(CacheInterface $cache): static
    // TODO Task 4: add setDispatcher(EventDispatcherInterface $dispatcher): static

    public function generate(string $invoiceId, array $lineItems): array {
        // TODO Task 5: add logging, caching, and event dispatch here

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

        echo "Invoice #{$invoiceId} generated. Total: R" . number_format($total, 2) . "\n";
        return $invoice;
    }

    public function processPayment(string $invoiceId, string $token): bool {
        // TODO Task 5: add logging and event dispatch here

        $rows = $this->db->query('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
        if (empty($rows)) {
            return false;
        }

        $invoice = $rows[0];
        $charged = $this->gateway->charge($invoice['total'], $token);

        if ($charged) {
            $this->db->execute(
                'UPDATE invoices SET status = ? WHERE id = ?',
                ['paid', $invoiceId]
            );
            echo "Payment for #{$invoiceId}: success\n";
        }

        return $charged;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// CURRENT wiring (replace with three contexts in Tasks 6)
// ─────────────────────────────────────────────────────────────────────────────

$db      = new InMemoryDb();
$gateway = new FakePaymentGateway();
$items   = [
    ['name' => 'Widget Pro',  'qty' => 2, 'price' => 29999, 'subtotal' => 599.98],
    ['name' => 'Widget Lite', 'qty' => 3, 'price' => 30000, 'subtotal' => 900.00],
];

$service = new InvoiceService($db, $gateway);
$invoice = $service->generate('INV-001', $items);
$service->processPayment('INV-001', 'tok_abc123');


// ─────────────────────────────────────────────────────────────────────────────
// TODO Task 6: Replace the above with three contexts
// ─────────────────────────────────────────────────────────────────────────────

// echo "\n=== Context 1: Minimal (Null Objects) ===\n\n";
// ... wire with required deps only

// echo "\n=== Context 2: Full production ===\n\n";
// ... wire with all optional deps via setters

// echo "\n=== Context 3: Test (spy assertions) ===\n\n";
// ... wire with spy logger and spy dispatcher
// ... assert on what was called