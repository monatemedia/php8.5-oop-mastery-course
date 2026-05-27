<?php
declare(strict_types=1);

/**
 * Example 03 — Composing Behaviour
 * ----------------------------------
 * Building flexible classes by combining interface-typed collaborators.
 * This example demonstrates all four composition patterns from the README:
 *
 *   Pattern 1: Constructor injection     — required collaborators
 *   Pattern 2: Setter injection          — optional collaborators
 *   Pattern 3: Method parameter          — per-call collaborators
 *   Pattern 4: Delegating decorator      — wrapping a collaborator to add behaviour
 *
 * Scenario: A report generation system. The same ReportService handles
 * different formats, storage targets, and optional logging — all through
 * composition, with no inheritance beyond implementing an interface.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Composing Behaviour — Four Patterns                ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// The interfaces — each collaborator role declared as a contract
// ─────────────────────────────────────────────────────────────────────────────

interface FormatterInterface {
    public function format(array $data): string;
    public function mimeType(): string;
}

interface StorageInterface {
    public function save(string $name, string $content): string; // returns saved path/key
}

interface LoggerInterface {
    public function log(string $level, string $message): void;
}

interface ReportServiceInterface {
    public function generate(string $name, array $data): string;
}


// ─────────────────────────────────────────────────────────────────────────────
// Concrete collaborator implementations
// ─────────────────────────────────────────────────────────────────────────────

class JsonFormatter implements FormatterInterface {
    public function format(array $data): string {
        return json_encode($data, JSON_PRETTY_PRINT);
    }
    public function mimeType(): string { return 'application/json'; }
}

class CsvFormatter implements FormatterInterface {
    public function format(array $data): string {
        if (empty($data)) return '';
        $lines = [implode(',', array_keys(reset($data)))];
        foreach ($data as $row) {
            $lines[] = implode(',', array_map('strval', $row));
        }
        return implode("\n", $lines);
    }
    public function mimeType(): string { return 'text/csv'; }
}

class MemoryStorage implements StorageInterface {
    public array $files = [];
    public function save(string $name, string $content): string {
        $this->files[$name] = $content;
        echo "  [STORAGE] Saved: {$name} (" . strlen($content) . " bytes)\n";
        return $name;
    }
}

class ConsoleLogger implements LoggerInterface {
    public function log(string $level, string $message): void {
        echo "  [{$level}] {$message}\n";
    }
}

class NullLogger implements LoggerInterface {
    public function log(string $level, string $message): void {}
}


// ═══════════════════════════════════════════════════════════
// PATTERN 1 — Constructor injection (required collaborators)
// ═══════════════════════════════════════════════════════════

echo "── Pattern 1: Constructor injection ─────────────────\n\n";

/**
 * ReportService — core service, no parent class.
 * Required deps: formatter + storage (must have both to function).
 * Optional dep:  logger (works without it via NullLogger default).
 */
class ReportService implements ReportServiceInterface {
    private LoggerInterface $logger; // Pattern 2 — optional

    public function __construct(
        private FormatterInterface $formatter,  // Pattern 1 — required
        private StorageInterface   $storage     // Pattern 1 — required
    ) {
        $this->logger = new NullLogger(); // safe default (Pattern 2)
    }

    // Pattern 2 — setter for optional dep
    public function setLogger(LoggerInterface $logger): static {
        $this->logger = $logger;
        return $this;
    }

    public function generate(string $name, array $data): string {
        $this->logger->log('INFO', "Generating report: {$name}");
        $content = $this->formatter->format($data);
        $path    = $this->storage->save($name, $content);
        $this->logger->log('INFO', "Report saved: {$path}");
        return $path;
    }
}

$data = [
    ['product' => 'Widget Pro',  'qty' => 120, 'revenue' => 3588],
    ['product' => 'Widget Lite', 'qty' => 85,  'revenue' => 1275],
];

// JSON reports → memory storage
$jsonService = new ReportService(new JsonFormatter(), new MemoryStorage());
$jsonService->generate('sales-2024.json', $data);

// CSV reports → same memory storage (same interface, different formatter)
$csvStorage  = new MemoryStorage();
$csvService  = new ReportService(new CsvFormatter(), $csvStorage);
$csvService->setLogger(new ConsoleLogger()); // Pattern 2 — opt-in logging
$csvService->generate('sales-2024.csv', $data);

echo "\nKey point: ReportService never changes — only the wiring changes.\n";
echo "  JSON → MemoryStorage: no logging\n";
echo "  CSV  → MemoryStorage: with logging\n\n";


// ═══════════════════════════════════════════════════════════
// PATTERN 3 — Method parameter (per-call collaborator)
// ═══════════════════════════════════════════════════════════

echo "── Pattern 3: Method parameter ──────────────────────\n\n";

/**
 * The discount strategy is not a persistent dependency —
 * it changes per calculation call. Pass it as a method argument.
 */
interface DiscountStrategyInterface {
    public function apply(float $price): float;
    public function describe(): string;
}

class PercentageDiscount implements DiscountStrategyInterface {
    public function __construct(private float $rate) {}
    public function apply(float $price): float   { return round($price * (1 - $this->rate), 2); }
    public function describe(): string            { return (int)($this->rate * 100) . '% off'; }
}

class FlatDiscount implements DiscountStrategyInterface {
    public function __construct(private float $amount) {}
    public function apply(float $price): float   { return max(0, $price - $this->amount); }
    public function describe(): string            { return "R{$this->amount} off"; }
}

class NoDiscount implements DiscountStrategyInterface {
    public function apply(float $price): float   { return $price; }
    public function describe(): string            { return 'No discount'; }
}

class PriceCalculator {
    // No persistent collaborator — strategy is passed per-call
    public function calculate(float $basePrice, DiscountStrategyInterface $discount): float {
        $final = $discount->apply($basePrice);
        echo "  Base: R{$basePrice} | {$discount->describe()} | Final: R{$final}\n";
        return $final;
    }
}

$calc = new PriceCalculator();
$calc->calculate(1500.00, new PercentageDiscount(0.15)); // 15% off
$calc->calculate(1500.00, new FlatDiscount(200.00));      // R200 off
$calc->calculate(1500.00, new NoDiscount());              // full price

echo "\nKey point: PriceCalculator has no state and no stored deps.\n";
echo "  The strategy is per-call — it varies by cart item, not by service instance.\n\n";


// ═══════════════════════════════════════════════════════════
// PATTERN 4 — Delegating decorator
// ═══════════════════════════════════════════════════════════

echo "── Pattern 4: Delegating decorator ──────────────────\n\n";

/**
 * LoggingReportService wraps ANY ReportServiceInterface to add logging.
 * The wrapped service does not change — it does not need to know about logging.
 * This is the Open/Closed Principle: extend behaviour without modifying source.
 */
class LoggingReportService implements ReportServiceInterface {
    public function __construct(
        private ReportServiceInterface $inner,   // wraps any report service
        private LoggerInterface        $logger
    ) {}

    public function generate(string $name, array $data): string {
        $this->logger->log('INFO', "START generate: {$name}");
        $start  = microtime(true);
        $result = $this->inner->generate($name, $data); // delegate to wrapped service
        $ms     = round((microtime(true) - $start) * 1000, 2);
        $this->logger->log('INFO', "END generate: {$name} in {$ms}ms");
        return $result;
    }
}

/**
 * CachingReportService wraps ANY ReportServiceInterface to add caching.
 * Decorators can be stacked: cache(log(realService)).
 */
class CachingReportService implements ReportServiceInterface {
    private array $cache = [];

    public function __construct(
        private ReportServiceInterface $inner
    ) {}

    public function generate(string $name, array $data): string {
        $key = $name . ':' . md5(json_encode($data));
        if (isset($this->cache[$key])) {
            echo "  [CACHE] Hit: {$name}\n";
            return $this->cache[$key];
        }
        $result            = $this->inner->generate($name, $data);
        $this->cache[$key] = $result;
        return $result;
    }
}

// Stack decorators: cache → log → real service
$realService    = new ReportService(new JsonFormatter(), new MemoryStorage());
$loggedService  = new LoggingReportService($realService, new ConsoleLogger());
$cachedService  = new CachingReportService($loggedService);

echo "First call (cache miss → logs → real service):\n";
$cachedService->generate('q4-report.json', $data);

echo "\nSecond call (cache hit — no log, no formatter, no storage):\n";
$cachedService->generate('q4-report.json', $data);

echo "\nKey point: No class was modified to add caching or logging.\n";
echo "  Each decorator wraps the interface — they are stackable in any order.\n";
echo "  This is composition, not inheritance.\n";

echo "\n--- Recap ---\n";
echo "Pattern 1 (constructor): required deps — always present, always ready.\n";
echo "Pattern 2 (setter):      optional deps — NullObject default, opt-in.\n";
echo "Pattern 3 (method param): per-call deps — strategy, varies per invocation.\n";
echo "Pattern 4 (decorator):   wraps an interface — adds behaviour without modification.\n";