<?php
declare(strict_types=1);

/**
 * Example 05 — The Template Method Pattern
 * ------------------------------------------
 * The Template Method Pattern is the design pattern that abstract classes
 * were built for. It is so natural in PHP that you will use it constantly
 * without even realising it has a name.
 *
 * The idea:
 *   The abstract class defines the SKELETON of an algorithm in one `final`
 *   method. The steps of the algorithm are abstract methods. Subclasses fill
 *   in the steps — they never change the order or the skeleton itself.
 *
 * Structure:
 *   final public function templateMethod(): void {
 *       $this->stepOne();   ← abstract — subclass fills this in
 *       $this->stepTwo();   ← concrete — shared, unchangeable
 *       $this->stepThree(); ← abstract — subclass fills this in
 *   }
 *
 * Scenario: A data import pipeline (read → validate → transform → persist).
 * The pipeline steps are always the same. Only the HOW differs per format.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Template Method Pattern                            ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// The abstract class — defines the pipeline skeleton
// ─────────────────────────────────────────────────────────────────────────────

abstract class DataImporter {
    protected array  $rawData       = [];
    protected array  $cleanData     = [];
    protected array  $importErrors  = [];
    protected int    $importedCount = 0;

    public function __construct(protected string $source) {}

    // ── THE TEMPLATE METHOD ───────────────────────────────────────────────────
    // `final` ensures the pipeline order is NEVER changed by a subclass.
    // Every importer runs: read → validate → transform → persist → report.
    final public function import(): void {
        echo "\n[PIPELINE] Starting import from: {$this->source}\n";
        echo str_repeat('─', 50) . "\n";

        $this->rawData = $this->readSource();          // STEP 1 — abstract
        echo "[STEP 1] Read " . count($this->rawData) . " raw record(s)\n";

        $this->rawData = $this->validateRecords($this->rawData); // STEP 2 — abstract
        echo "[STEP 2] " . count($this->rawData) . " record(s) passed validation\n";

        $this->cleanData = $this->transformRecords($this->rawData); // STEP 3 — abstract
        echo "[STEP 3] Transformed to " . count($this->cleanData) . " clean record(s)\n";

        $this->importedCount = $this->persistRecords($this->cleanData); // STEP 4 — abstract
        echo "[STEP 4] Persisted {$this->importedCount} record(s)\n";

        $this->afterImport();  // STEP 5 — hook (optional override, has a default)
        $this->printSummary(); // STEP 6 — concrete, shared, never overridden
    }

    // ── Abstract steps — subclasses MUST implement ───────────────────────────

    /** Read raw data from the source (file, API, database, etc.) */
    abstract protected function readSource(): array;

    /** Validate records and return only the valid ones */
    abstract protected function validateRecords(array $records): array;

    /** Transform raw records into the application's domain format */
    abstract protected function transformRecords(array $records): array;

    /** Persist clean records and return the count of successfully saved records */
    abstract protected function persistRecords(array $records): int;

    // ── Hook method — concrete but designed to be overridden ─────────────────
    // Unlike abstract methods, hooks have a default (often empty) implementation.
    // Subclasses may override them — but do not have to.
    protected function afterImport(): void {
        // Default: do nothing. Subclasses can override to send notifications, etc.
    }

    // ── Concrete shared step — never overridden ───────────────────────────────
    private function printSummary(): void {
        echo str_repeat('─', 50) . "\n";
        echo "[SUMMARY] Imported: {$this->importedCount} | "
           . "Errors: " . count($this->importErrors) . "\n";
        if (!empty($this->importErrors)) {
            foreach ($this->importErrors as $err) {
                echo "  ✗ {$err}\n";
            }
        }
    }

    protected function addError(string $message): void {
        $this->importErrors[] = $message;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Concrete importer 1 — CSV
// ─────────────────────────────────────────────────────────────────────────────

class CsvUserImporter extends DataImporter {
    protected function readSource(): array {
        // Simulate reading a CSV file
        echo "  [CSV] Parsing file: {$this->source}\n";
        return [
            ['email' => 'alice@example.com', 'first' => 'Alice', 'last' => 'Smith',  'age' => '32'],
            ['email' => 'not-an-email',       'first' => 'Bad',   'last' => 'Record', 'age' => 'x'],
            ['email' => 'bob@example.com',    'first' => 'Bob',   'last' => 'Jones',  'age' => '45'],
            ['email' => '',                   'first' => '',      'last' => '',       'age' => ''],
        ];
    }

    protected function validateRecords(array $records): array {
        $valid = [];
        foreach ($records as $record) {
            if (empty($record['email']) || !filter_var($record['email'], FILTER_VALIDATE_EMAIL)) {
                $this->addError("Invalid email: '{$record['email']}'");
                continue;
            }
            if (!is_numeric($record['age'])) {
                $this->addError("Invalid age for {$record['email']}: '{$record['age']}'");
                continue;
            }
            $valid[] = $record;
        }
        return $valid;
    }

    protected function transformRecords(array $records): array {
        return array_map(fn($r) => [
            'email'      => strtolower(trim($r['email'])),
            'full_name'  => trim($r['first'] . ' ' . $r['last']),
            'age'        => (int) $r['age'],
            'created_at' => date('Y-m-d'),
        ], $records);
    }

    protected function persistRecords(array $records): int {
        foreach ($records as $record) {
            echo "  [DB] INSERT users: {$record['email']} ({$record['full_name']})\n";
        }
        return count($records);
    }

    // Override the hook to send a notification after import
    protected function afterImport(): void {
        echo "[HOOK] Sending import completion email to admin@example.com\n";
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Concrete importer 2 — JSON API
// ─────────────────────────────────────────────────────────────────────────────

class JsonApiProductImporter extends DataImporter {
    protected function readSource(): array {
        // Simulate fetching from a JSON API endpoint
        echo "  [API] GET {$this->source}\n";
        return [
            ['sku' => 'WDG-001', 'name' => 'Widget A', 'price' => '29.99',  'stock' => '150'],
            ['sku' => 'WDG-002', 'name' => 'Widget B', 'price' => '-5.00',  'stock' => '80'],   // bad price
            ['sku' => '',        'name' => 'No SKU',   'price' => '10.00',  'stock' => '10'],   // missing SKU
            ['sku' => 'WDG-003', 'name' => 'Widget C', 'price' => '49.99',  'stock' => '200'],
        ];
    }

    protected function validateRecords(array $records): array {
        $valid = [];
        foreach ($records as $record) {
            if (empty($record['sku'])) {
                $this->addError("Missing SKU for product: '{$record['name']}'");
                continue;
            }
            if ((float) $record['price'] <= 0) {
                $this->addError("Invalid price for SKU {$record['sku']}: {$record['price']}");
                continue;
            }
            $valid[] = $record;
        }
        return $valid;
    }

    protected function transformRecords(array $records): array {
        return array_map(fn($r) => [
            'sku'        => strtoupper(trim($r['sku'])),
            'name'       => trim($r['name']),
            'price_cents' => (int) round((float) $r['price'] * 100),
            'stock'      => (int) $r['stock'],
            'synced_at'  => date('Y-m-d H:i:s'),
        ], $records);
    }

    protected function persistRecords(array $records): int {
        foreach ($records as $record) {
            echo "  [DB] UPSERT products: {$record['sku']} — {$record['name']} "
               . "(R" . number_format($record['price_cents'] / 100, 2) . ")\n";
        }
        return count($records);
    }

    // No afterImport() override — default (do nothing) is fine for products
}


// ─────────────────────────────────────────────────────────────────────────────
// Running the pipelines
// ─────────────────────────────────────────────────────────────────────────────

echo "── CSV User Import ──────────────────────────────────";
$csvImporter = new CsvUserImporter('/uploads/users-jan.csv');
$csvImporter->import();

echo "\n\n── JSON API Product Import ──────────────────────────";
$apiImporter = new JsonApiProductImporter('https://supplier.example.com/api/products');
$apiImporter->import();


// ─────────────────────────────────────────────────────────────────────────────
// Why `final` on the template method matters
// ─────────────────────────────────────────────────────────────────────────────

echo "\n\n── Why final matters ────────────────────────────────\n\n";
echo "The import() method is marked final.\n";
echo "This guarantees that every importer — no matter who writes it —\n";
echo "always runs: read → validate → transform → persist → afterImport → summary.\n";
echo "No subclass can skip validation or reorder the steps.\n";
echo "The abstract class OWNS the algorithm. Subclasses only fill in the steps.\n\n";

echo "Without final, a subclass could do:\n";
echo "  public function import(): void {\n";
echo "      \$this->readSource();\n";
echo "      \$this->persistRecords([...]); // Validation skipped — dangerous!\n";
echo "  }\n";

echo "\n--- Recap ---\n";
echo "Template Method: abstract class owns the SKELETON (order of steps).\n";
echo "Abstract steps:  subclasses fill in the WHAT of each step.\n";
echo "Hook methods:    concrete methods with empty defaults — override if needed.\n";
echo "final:           locks the skeleton — no subclass can reorder or skip steps.\n";
echo "Payoff:          guarantees consistency across all importers, all formats.\n";