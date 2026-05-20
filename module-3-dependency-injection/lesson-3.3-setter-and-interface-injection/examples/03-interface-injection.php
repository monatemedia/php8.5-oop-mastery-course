<?php
declare(strict_types=1);

/**
 * Example 03 — Interface Injection
 * ------------------------------------
 * Interface injection: the dependency declares a setter contract via an interface.
 * Any class that implements the interface "announces" it wants that dependency.
 * A framework or container sees the interface and automatically calls the setter.
 *
 * This is less common than constructor/setter injection, but you will see it
 * in PSR-3 (LoggerAwareInterface), Symfony, and Laravel internals.
 *
 * Three parts:
 *   A. Building interface injection from scratch
 *   B. PSR-3 LoggerAwareInterface — the real-world standard
 *   C. A simple "aware container" that wires everything automatically
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Interface Injection                                ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Core interfaces
// ─────────────────────────────────────────────────────────────────────────────

interface LoggerInterface {
    public function log(string $level, string $message): void;
}

interface EventDispatcherInterface {
    public function dispatch(string $event, array $payload = []): void;
}

interface CacheInterface {
    public function get(string $key): mixed;
    public function set(string $key, mixed $value): void;
}

// Null Objects
class NullLogger implements LoggerInterface {
    public function log(string $level, string $message): void {}
}

class NullDispatcher implements EventDispatcherInterface {
    public function dispatch(string $event, array $payload = []): void {}
}

class NullCache implements CacheInterface {
    public function get(string $key): mixed  { return null; }
    public function set(string $key, mixed $value): void {}
}

// Real implementations
class ConsoleLogger implements LoggerInterface {
    public function log(string $level, string $message): void {
        echo "  [{$level}] {$message}\n";
    }
}

class SimpleDispatcher implements EventDispatcherInterface {
    public function dispatch(string $event, array $payload = []): void {
        echo "  [EVENT] {$event}: " . json_encode($payload) . "\n";
    }
}

class ArrayCache implements CacheInterface {
    private array $store = [];
    public function get(string $key): mixed { return $this->store[$key] ?? null; }
    public function set(string $key, mixed $value): void {
        $this->store[$key] = $value;
        echo "  [CACHE] SET {$key}\n";
    }
}


// ═══════════════════════════════════════════════════════════
// PART A — Interface injection from scratch
// The "Aware" interface declares the setter contract
// ═══════════════════════════════════════════════════════════

echo "── Part A: Interface injection from scratch ─────────\n\n";

// "Aware" interfaces — declare what dependency the class needs
interface LoggerAwareInterface {
    public function setLogger(LoggerInterface $logger): void;
}

interface EventDispatcherAwareInterface {
    public function setEventDispatcher(EventDispatcherInterface $dispatcher): void;
}

interface CacheAwareInterface {
    public function setCache(CacheInterface $cache): void;
}

// Reusable trait implementations (so classes don't repeat the setter body)
trait LoggerAwareTrait {
    protected LoggerInterface $logger;

    public function setLogger(LoggerInterface $logger): void {
        $this->logger = $logger;
    }
}

trait EventDispatcherAwareTrait {
    protected EventDispatcherInterface $dispatcher;

    public function setEventDispatcher(EventDispatcherInterface $dispatcher): void {
        $this->dispatcher = $dispatcher;
    }
}

trait CacheAwareTrait {
    protected CacheInterface $cache;

    public function setCache(CacheInterface $cache): void {
        $this->cache = $cache;
    }
}

// Service that announces: "I need a logger AND an event dispatcher"
class OrderProcessorService
    implements LoggerAwareInterface, EventDispatcherAwareInterface
{
    use LoggerAwareTrait {
        setLogger as public;
    }
    use EventDispatcherAwareTrait {
        setEventDispatcher as public;
    }

    private array $orders = [];

    public function __construct() {
        // Safe defaults — framework replaces these via interface injection
        $this->logger     = new NullLogger();
        $this->dispatcher = new NullDispatcher();
    }

    public function placeOrder(array $order): string {
        $id = 'ORD-' . rand(1000, 9999);
        $this->orders[$id] = $order;

        $this->logger->log('INFO', "Order {$id} placed");
        $this->dispatcher->dispatch('order.placed', ['id' => $id, 'total' => $order['total']]);

        return $id;
    }
}

// Service that announces: "I need a logger AND a cache"
class UserLookupService
    implements LoggerAwareInterface, CacheAwareInterface
{
    use LoggerAwareTrait {
        setLogger as public;
    }
    use CacheAwareTrait {
        setCache as public;
    }

    private array $users = [
        1 => ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
        2 => ['id' => 2, 'name' => 'Bob',   'email' => 'bob@example.com'],
    ];

    public function __construct() {
        $this->logger = new NullLogger();
        $this->cache  = new NullCache();
    }

    public function findById(int $id): ?array {
        $key    = "user:{$id}";
        $cached = $this->cache->get($key);
        if ($cached !== null) {
            $this->logger->log('INFO', "Cache hit: user #{$id}");
            return $cached;
        }

        $user = $this->users[$id] ?? null;
        if ($user) {
            $this->cache->set($key, $user);
            $this->logger->log('INFO', "DB fetch: user #{$id}");
        }
        return $user;
    }
}

echo "Services before injection (Null Objects as defaults):\n";
$orders = new OrderProcessorService();
$users  = new UserLookupService();

$id   = $orders->placeOrder(['total' => 500.00, 'items' => 2]);
$user = $users->findById(1);
echo "  Order: {$id} | User: {$user['name']} (silent — Null Objects)\n\n";

echo "After interface injection (container calls setters):\n";
$orders->setLogger(new ConsoleLogger());
$orders->setEventDispatcher(new SimpleDispatcher());
$users->setLogger(new ConsoleLogger());
$users->setCache(new ArrayCache());

$id2  = $orders->placeOrder(['total' => 750.00, 'items' => 3]);
$user2 = $users->findById(2);
$user2Again = $users->findById(2); // Cache hit
echo "  Order: {$id2} | User: {$user2['name']}\n";


// ═══════════════════════════════════════════════════════════
// PART B — PSR-3 LoggerAwareInterface (the real standard)
// ═══════════════════════════════════════════════════════════

echo "\n── Part B: PSR-3 LoggerAwareInterface ───────────────\n\n";

// This mirrors the actual PSR-3 standard interfaces exactly:
// https://www.php-fig.org/psr/psr-3/

interface Psr3LoggerInterface {
    public function emergency(string $message, array $context = []): void;
    public function alert(string $message, array $context = []): void;
    public function critical(string $message, array $context = []): void;
    public function error(string $message, array $context = []): void;
    public function warning(string $message, array $context = []): void;
    public function notice(string $message, array $context = []): void;
    public function info(string $message, array $context = []): void;
    public function debug(string $message, array $context = []): void;
    public function log(string $level, string $message, array $context = []): void;
}

// PSR-3 "Aware" interface — declares the setter contract
interface Psr3LoggerAwareInterface {
    public function setLogger(Psr3LoggerInterface $logger): void;
}

// PSR-3 trait — free implementation of the setter
trait Psr3LoggerAwareTrait {
    protected ?Psr3LoggerInterface $logger = null;

    public function setLogger(Psr3LoggerInterface $logger): void {
        $this->logger = $logger;
    }
}

// A simple PSR-3 compatible logger
class Psr3ConsoleLogger implements Psr3LoggerInterface {
    public function emergency(string $m, array $c = []): void { $this->log('EMERGENCY', $m, $c); }
    public function alert(string $m, array $c = []): void     { $this->log('ALERT', $m, $c); }
    public function critical(string $m, array $c = []): void  { $this->log('CRITICAL', $m, $c); }
    public function error(string $m, array $c = []): void     { $this->log('ERROR', $m, $c); }
    public function warning(string $m, array $c = []): void   { $this->log('WARNING', $m, $c); }
    public function notice(string $m, array $c = []): void    { $this->log('NOTICE', $m, $c); }
    public function info(string $m, array $c = []): void      { $this->log('INFO', $m, $c); }
    public function debug(string $m, array $c = []): void     { $this->log('DEBUG', $m, $c); }
    public function log(string $level, string $message, array $context = []): void {
        echo "  [PSR-3:{$level}] {$message}\n";
    }
}

// A service that uses the PSR-3 pattern — constructor unchanged
class PaymentService implements Psr3LoggerAwareInterface {
    use Psr3LoggerAwareTrait;

    public function charge(float $amount, string $token): bool {
        $this->logger?->info("Charging R{$amount} with token {$token}");
        // ... payment logic ...
        $this->logger?->info("Charge successful");
        return true;
    }
}

$payment = new PaymentService();

echo "Before logger injection:\n";
$payment->charge(500.00, 'tok_abc123');
echo "  (Silent — no logger set)\n\n";

echo "After PSR-3 logger injection:\n";
$payment->setLogger(new Psr3ConsoleLogger());
$payment->charge(250.00, 'tok_def456');

echo "\nReal-world note:\n";
echo "  Any PSR-3 compliant container (Symfony, Laravel) that sees\n";
echo "  a service implementing Psr3LoggerAwareInterface will\n";
echo "  automatically call setLogger(\$monologInstance) after wiring.\n";
echo "  You do not have to wire it manually.\n";


// ═══════════════════════════════════════════════════════════
// PART C — A simple "aware container" demonstrating automatic injection
// ═══════════════════════════════════════════════════════════

echo "\n── Part C: Automatic injection via 'aware' container ─\n\n";

class SimpleAwareContainer {
    private array $services = [];
    private ?LoggerInterface $logger         = null;
    private ?EventDispatcherInterface $dispatcher = null;
    private ?CacheInterface $cache           = null;

    public function setLogger(LoggerInterface $logger): void {
        $this->logger = $logger;
    }

    public function setDispatcher(EventDispatcherInterface $d): void {
        $this->dispatcher = $d;
    }

    public function setCache(CacheInterface $c): void {
        $this->cache = $c;
    }

    public function register(string $name, object $service): void {
        $this->services[$name] = $service;
        $this->autowireAwareness($service);
    }

    private function autowireAwareness(object $service): void {
        // Automatically call setters based on which interfaces are implemented
        if ($service instanceof LoggerAwareInterface && $this->logger) {
            $service->setLogger($this->logger);
            echo "  [CONTAINER] Injected logger into " . get_class($service) . "\n";
        }
        if ($service instanceof EventDispatcherAwareInterface && $this->dispatcher) {
            $service->setEventDispatcher($this->dispatcher);
            echo "  [CONTAINER] Injected dispatcher into " . get_class($service) . "\n";
        }
        if ($service instanceof CacheAwareInterface && $this->cache) {
            $service->setCache($this->cache);
            echo "  [CONTAINER] Injected cache into " . get_class($service) . "\n";
        }
    }

    public function get(string $name): object {
        return $this->services[$name] ?? throw new \RuntimeException("Service not found: {$name}");
    }
}

$container = new SimpleAwareContainer();
$container->setLogger(new ConsoleLogger());
$container->setDispatcher(new SimpleDispatcher());
$container->setCache(new ArrayCache());

echo "Registering services — container auto-injects based on interfaces:\n";
$container->register('orders', new OrderProcessorService());
$container->register('users',  new UserLookupService());

echo "\nUsing auto-wired services:\n";
$autoOrders = $container->get('orders');
$autoOrders->placeOrder(['total' => 1000.00, 'items' => 1]);

$autoUsers = $container->get('users');
$autoUsers->findById(1);

echo "\n--- Recap ---\n";
echo "Interface injection: the class implements an 'Aware' interface — announces its need.\n";
echo "Trait: provides the free setter implementation (LoggerAwareTrait).\n";
echo "Container: sees the interface, calls the setter automatically.\n";
echo "PSR-3: the real-world standard — LoggerAwareInterface + LoggerAwareTrait.\n";
echo "When to use: framework-provided deps (logger, dispatcher) — not biz logic deps.\n";