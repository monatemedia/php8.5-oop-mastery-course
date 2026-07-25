<?php
declare(strict_types=1);

/**
 * Example 02 — Implementing Multiple Interfaces
 * -----------------------------------------------
 * A class can only extend ONE parent class.
 * A class can implement AS MANY interfaces as needed.
 *
 * This example models a report that can be both printed and exported.
 * Each capability is a separate interface. The Report class signs
 * both contracts by implementing both.
 */

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║  SOLID CALLOUT — I: Interface Segregation Principle (ISP)               ║
// ╠══════════════════════════════════════════════════════════════════════════╣
// ║  "Clients should not be forced to depend on methods they do not use."   ║
// ║                                                                          ║
// ║  Watch how this example keeps interfaces SMALL and FOCUSED:             ║
// ║   • Printable  — one method  (printOut)                                 ║
// ║   • Exportable — two methods (exportToCsv, exportToJson)                ║
// ║   • Archivable — one method  (archive)                                  ║
// ║                                                                          ║
// ║  ThumbnailImage only needs to print — it implements ONLY Printable.     ║
// ║  It is NEVER forced to implement export or archive methods it can't use. ║
// ║                                                                          ║
// ║  The VIOLATION would be: one fat interface with all five methods,       ║
// ║  forcing ThumbnailImage to stub out exportToCsv() with an exception.    ║
// ║  See lesson-1.0-solid-overview/examples/04-isp.php for the full violation.║
// ╚══════════════════════════════════════════════════════════════════════════╝

// ─────────────────────────────────────────────────────────────────────────────
// Two focused, single-purpose interfaces (Interface Segregation Principle)
// ─────────────────────────────────────────────────────────────────────────────

interface Printable {
    public function printOut(): void;
}

interface Exportable {
    public function exportToCsv(): string;
    public function exportToJson(): string;
}

// A third interface — not all classes will need this one
interface Archivable {
    public function archive(): bool;
}


// ─────────────────────────────────────────────────────────────────────────────
// A class implementing TWO interfaces
// ─────────────────────────────────────────────────────────────────────────────

class SalesReport implements Printable, Exportable {
    private array $rows;

    public function __construct(array $rows) {
        $this->rows = $rows;
    }

    // Fulfils Printable
    public function printOut(): void {
        echo "=== Sales Report ===\n";
        foreach ($this->rows as $row) {
            echo "  Product: {$row['product']} | Units: {$row['units']} | Revenue: R{$row['revenue']}\n";
        }
        echo "====================\n";
    }

    // Fulfils Exportable
    public function exportToCsv(): string {
        $lines = ["product,units,revenue"];
        foreach ($this->rows as $row) {
            $lines[] = "{$row['product']},{$row['units']},{$row['revenue']}";
        }
        return implode("\n", $lines);
    }

    public function exportToJson(): string {
        return json_encode($this->rows, JSON_PRETTY_PRINT);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// A class implementing ALL THREE interfaces
// ─────────────────────────────────────────────────────────────────────────────

class AuditReport implements Printable, Exportable, Archivable {
    private array $entries;

    public function __construct(array $entries) {
        $this->entries = $entries;
    }

    public function printOut(): void {
        echo "=== Audit Report ===\n";
        foreach ($this->entries as $entry) {
            echo "  [{$entry['timestamp']}] {$entry['action']} by {$entry['user']}\n";
        }
        echo "====================\n";
    }

    public function exportToCsv(): string {
        $lines = ["timestamp,action,user"];
        foreach ($this->entries as $entry) {
            $lines[] = "{$entry['timestamp']},{$entry['action']},{$entry['user']}";
        }
        return implode("\n", $lines);
    }

    public function exportToJson(): string {
        return json_encode($this->entries, JSON_PRETTY_PRINT);
    }

    // Fulfils Archivable
    public function archive(): bool {
        echo "[ARCHIVE] AuditReport archived to cold storage.\n";
        return true;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// A class implementing ONLY ONE interface — perfectly valid
// ─────────────────────────────────────────────────────────────────────────────

class ThumbnailImage implements Printable {
    public function __construct(private string $filename) {}

    public function printOut(): void {
        echo "[PRINT] Rendering image: {$this->filename}\n";
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Using the classes
// ─────────────────────────────────────────────────────────────────────────────

$sales = new SalesReport([
    ['product' => 'Widget A', 'units' => 120, 'revenue' => 4800],
    ['product' => 'Widget B', 'units' => 85,  'revenue' => 5100],
]);

echo "\n--- Printing the Sales Report ---\n";
$sales->printOut();

echo "\n--- CSV Export ---\n";
echo $sales->exportToCsv() . "\n";

echo "\n--- JSON Export ---\n";
echo $sales->exportToJson() . "\n";

// ─────────────────────────────────────────────────────────────────────────────
// Polymorphism: a function that accepts ANY Printable — it does not care
// whether it is a SalesReport, AuditReport, or ThumbnailImage.
// ─────────────────────────────────────────────────────────────────────────────

function sendToPrinter(Printable $document): void {
    echo "\n[PRINTER] Received a " . get_class($document) . ":\n";
    $document->printOut();
}

sendToPrinter($sales);
sendToPrinter(new ThumbnailImage("logo.png"));

$audit = new AuditReport([
    ['timestamp' => '2024-01-15 09:00', 'action' => 'Login',  'user' => 'alice'],
    ['timestamp' => '2024-01-15 09:05', 'action' => 'Export', 'user' => 'alice'],
]);

sendToPrinter($audit);

// ─────────────────────────────────────────────────────────────────────────────
// instanceof with multiple interfaces
// ─────────────────────────────────────────────────────────────────────────────

echo "\n--- instanceof checks ---\n";

$objects = [$sales, $audit, new ThumbnailImage("banner.png")];

foreach ($objects as $obj) {
    $name = get_class($obj);
    echo "\n{$name}:\n";
    echo "  Printable?   " . ($obj instanceof Printable   ? 'YES' : 'NO') . "\n";
    echo "  Exportable?  " . ($obj instanceof Exportable  ? 'YES' : 'NO') . "\n";
    echo "  Archivable?  " . ($obj instanceof Archivable  ? 'YES' : 'NO') . "\n";
}

echo "\n--- Recap ---\n";
echo "1. A class can implement multiple interfaces separated by commas.\n";
echo "2. It must fulfil ALL methods from ALL interfaces it implements.\n";
echo "3. Interfaces should be small and focused — do not lump unrelated methods together.\n";
echo "4. A class only implements the interfaces it actually needs.\n";