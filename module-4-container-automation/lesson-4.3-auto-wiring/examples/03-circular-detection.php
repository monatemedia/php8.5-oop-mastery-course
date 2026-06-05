<?php
declare(strict_types=1);

/**
 * Example 03 — Circular Dependency Detection
 * --------------------------------------------
 * A circular dependency occurs when class A depends on class B which
 * (directly or indirectly) depends on class A. Without detection, the
 * container would recurse infinitely until PHP hits its stack limit.
 *
 * This example:
 *   A. Shows a direct circular dependency (A → B → A)
 *   B. Shows an indirect circular dependency (A → B → C → A)
 *   C. Shows the detection mechanism ("resolving stack")
 *   D. Shows the correct fix for each case
 *
 * Course Philosophy Rule 4: Favour composition over inheritance.
 * Circular dependencies almost always indicate that two classes have been
 * merged when they should be separated (SRP violation), or that a shared
 * collaborator should be extracted to break the cycle.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Circular Dependency Detection                      ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Interfaces used in all scenarios
// ─────────────────────────────────────────────────────────────────────────────

interface LoggerInterface    { public function log(string $m): void; }
interface DatabaseInterface  { public function query(string $sql): array; }
interface EventInterface     { public function dispatch(string $e): void; }

class ConsoleLogger implements LoggerInterface {
    public function log(string $m): void { echo "  [LOG] {$m}\n"; }
}
class InMemoryDb implements DatabaseInterface {
    public function query(string $sql): array { return []; }
}


// ─────────────────────────────────────────────────────────────────────────────
// AutowiringContainer with circular detection
// ─────────────────────────────────────────────────────────────────────────────

class CircularDependencyException extends \RuntimeException {}
class UnresolvableParameterException extends \RuntimeException {}

class AutowiringContainer {
    private array $bindings   = [];
    private array $instances  = [];
    /** @var array<string, bool> classes currently being resolved */
    private array $resolving  = [];

    public function bind(string $id, string|callable $target): void {
        $this->bindings[$id] = $target;
    }
    public function instance(string $id, object $obj): void {
        $this->instances[$id] = $obj;
    }

    public function get(string $id): object {
        if (isset($this->instances[$id])) return $this->instances[$id];
        if (isset($this->bindings[$id])) {
            $binding = $this->bindings[$id];
            if (is_callable($binding)) {
                return $this->instances[$id] = $binding($this);
            }
            return $this->instances[$id] = $this->resolve($binding);
        }
        return $this->instances[$id] = $this->resolve($id);
    }

    public function has(string $id): bool {
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }

    /**
     * Reset the container state (for the examples below — not for production use).
     */
    public function reset(): void {
        $this->bindings  = [];
        $this->instances = [];
        $this->resolving = [];
    }

    private function resolve(string $class): object {
        // ── Circular dependency check ─────────────────────────────────────────
        if (isset($this->resolving[$class])) {
            $chain = implode(' → ', array_keys($this->resolving)) . ' → ' . $class;
            throw new CircularDependencyException(
                "Circular dependency detected: {$chain}"
            );
        }

        $ref = new ReflectionClass($class);
        if (!$ref->isInstantiable()) {
            throw new \RuntimeException("Not instantiable: {$class}");
        }

        // Mark as currently being resolved
        $this->resolving[$class] = true;

        try {
            $ctor = $ref->getConstructor();
            if ($ctor === null || count($ctor->getParameters()) === 0) {
                return new $class();
            }

            $deps = [];
            foreach ($ctor->getParameters() as $param) {
                $type = $param->getType();
                if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                    $deps[] = $this->get($type->getName());
                } elseif ($param->isOptional()) {
                    $deps[] = $param->getDefaultValue();
                } else {
                    throw new UnresolvableParameterException(
                        "Cannot auto-wire '\${$param->getName()}' in '{$class}'"
                    );
                }
            }

            $instance = $ref->newInstanceArgs($deps);
        } finally {
            // Always unmark — even if an exception was thrown
            unset($this->resolving[$class]);
        }

        return $instance;
    }
}


// ═══════════════════════════════════════════════════════════
// SCENARIO A — Direct circular dependency (A → B → A)
// ═══════════════════════════════════════════════════════════

echo "── Scenario A: Direct circular dependency (A → B → A) ─\n\n";

class ServiceA {
    // ❌ ServiceA depends on ServiceB
    public function __construct(private ServiceB $b) {}
}

class ServiceB {
    // ❌ ServiceB depends on ServiceA — circular!
    public function __construct(private ServiceA $a) {}
}

$container = new AutowiringContainer();

try {
    $container->get(ServiceA::class);
} catch (CircularDependencyException $e) {
    echo "Caught CircularDependencyException:\n";
    echo "  " . $e->getMessage() . "\n\n";
}


// ═══════════════════════════════════════════════════════════
// SCENARIO B — Indirect circular dependency (A → B → C → A)
// ═══════════════════════════════════════════════════════════

echo "── Scenario B: Indirect circular dependency (A → B → C → A) ─\n\n";

class BillingService {
    public function __construct(private OrderProcessor $processor) {}
}

class OrderProcessor {
    public function __construct(private NotificationService $notifier) {}
}

class NotificationService {
    // ❌ NotificationService depends on BillingService — closes the cycle
    public function __construct(private BillingService $billing) {}
}

$container->reset();

try {
    $container->get(BillingService::class);
} catch (CircularDependencyException $e) {
    echo "Caught CircularDependencyException:\n";
    echo "  " . $e->getMessage() . "\n\n";
}


// ═══════════════════════════════════════════════════════════
// SCENARIO C — How the resolving stack works
// ═══════════════════════════════════════════════════════════

echo "── Scenario C: The resolving stack ──────────────────\n\n";

echo "When get(BillingService) is called:\n";
echo "  resolving = {BillingService}\n";
echo "  → needs OrderProcessor → get(OrderProcessor)\n";
echo "  resolving = {BillingService, OrderProcessor}\n";
echo "  → needs NotificationService → get(NotificationService)\n";
echo "  resolving = {BillingService, OrderProcessor, NotificationService}\n";
echo "  → needs BillingService → get(BillingService)\n";
echo "  BillingService IS in resolving → CircularDependencyException!\n\n";
echo "The 'resolving' set is the detection mechanism.\n";
echo "It is cleared on exception (via finally) so the container stays usable.\n\n";


// ═══════════════════════════════════════════════════════════
// SCENARIO D — Fixing the circular dependency
// ═══════════════════════════════════════════════════════════

echo "── Scenario D: Fixing the circular dependency ────────\n\n";

echo "Root cause: NotificationService depends on BillingService because it\n";
echo "needs to 'bill and notify' together. This is an SRP violation — the\n";
echo "notification concern is mixed with the billing trigger.\n\n";

echo "Fix: extract the shared responsibility into a third class.\n\n";

// EventDispatcher breaks the cycle — both services depend on it,
// neither depends on the other.
class EventDispatcher implements EventInterface {
    private array $handlers = [];
    public function on(string $event, callable $handler): void {
        $this->handlers[$event][] = $handler;
    }
    public function dispatch(string $event): void {
        foreach ($this->handlers[$event] ?? [] as $h) $h();
        echo "  [EVENT] {$event}\n";
    }
}

class FixedBillingService {
    public function __construct(
        private DatabaseInterface $db,
        private EventInterface    $events  // depends on abstraction, not OrderProcessor
    ) {}

    public function processPayment(float $amount): void {
        $this->db->query("INSERT INTO payments...");
        $this->events->dispatch('payment.completed'); // dispatch, don't call directly
        echo "  [BILLING] Payment processed: R{$amount}\n";
    }
}

class FixedOrderProcessor {
    public function __construct(
        private DatabaseInterface $db,
        private EventInterface    $events
    ) {}

    public function process(int $orderId): void {
        $this->db->query("UPDATE orders...");
        $this->events->dispatch('order.processed');
        echo "  [ORDER] Order #{$orderId} processed\n";
    }
}

class FixedNotificationService {
    public function __construct(
        private LoggerInterface $logger,
        private EventInterface  $events
    ) {}

    // Listens to events — no direct dependency on Billing or Order
    public function sendConfirmation(string $email): void {
        $this->logger->log('INFO', "Notification sent to {$email}");
    }
}

$container->reset();
$container->bind(DatabaseInterface::class, InMemoryDb::class);
$container->bind(LoggerInterface::class,   ConsoleLogger::class);
$container->bind(EventInterface::class,    EventDispatcher::class);

echo "Fixed system (no circular dependencies):\n";
$billing  = $container->get(FixedBillingService::class);
$orders   = $container->get(FixedOrderProcessor::class);
$notifier = $container->get(FixedNotificationService::class);

$billing->processPayment(500.00);
$orders->process(1001);
$notifier->sendConfirmation('alice@example.com');

echo "\nNo circular dependencies — EventDispatcher breaks the cycle.\n";
echo "All three classes depend on the abstraction (EventInterface), not each other.\n";


// ─────────────────────────────────────────────────────────────────────────────
// Common causes and fixes
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Common causes and fixes ──────────────────────────\n\n";
echo "Cause 1: Two services that should call each other directly.\n";
echo "  Fix:   Extract a shared event bus / dispatcher. Both publish/listen.\n\n";
echo "Cause 2: A service that needs 'everything' (god class).\n";
echo "  Fix:   Split responsibilities (SRP). Each service does one thing.\n\n";
echo "Cause 3: Bi-directional associations between domain entities.\n";
echo "  Fix:   Choose a direction. One side holds the reference; the other queries.\n\n";
echo "Cause 4: Testing convenience — services wired to each other for test setup.\n";
echo "  Fix:   Use test doubles (fakes/stubs) — don't wire production services together.\n\n";

echo "--- Recap ---\n";
echo "The resolving stack detects cycles — if a class appears twice, throw.\n";
echo "finally{}: always unmark the class, even if resolution fails.\n";
echo "Error message: show the full chain so the developer knows where to look.\n";
echo "Fix: extract shared behaviour to a third class; use events; apply SRP.\n";