<?php
declare(strict_types=1);

/**
 * Example 02 — Abstract Methods and Concrete Methods
 * ----------------------------------------------------
 * Abstract methods enforce a contract (like interfaces).
 * Concrete methods share real implementation (unlike interfaces).
 * This example shows how to use BOTH together in a real report system.
 *
 * Pay attention to access modifiers:
 *   public    — callable from anywhere
 *   protected — callable from the class and subclasses only
 *   private   — callable only within the defining class (do NOT make abstract methods private)
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Abstract Methods + Concrete Methods                ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// The abstract base class — a report generator skeleton
// ─────────────────────────────────────────────────────────────────────────────

abstract class Report {
    protected array $data;
    protected string $title;
    private string $generatedAt;

    public function __construct(string $title, array $data) {
        $this->title       = $title;
        $this->data        = $data;
        $this->generatedAt = date('Y-m-d H:i:s');
    }

    // ── Abstract methods ──────────────────────────────────────────────────────
    // Every subclass MUST implement these — they are the "gaps" to fill.

    /**
     * Format the data rows into output-specific content.
     * CSV will return comma-separated lines. HTML will return <tr> elements.
     */
    abstract protected function formatRows(): string;

    /**
     * Wrap the formatted rows in the output format's outer structure.
     */
    abstract protected function wrapInStructure(string $rows): string;

    /**
     * Return the file extension for this format.
     */
    abstract public function getExtension(): string;


    // ── Concrete methods ──────────────────────────────────────────────────────
    // These are implemented ONCE here. Subclasses inherit them for free.

    /**
     * The main entry point — shared pipeline. All formats run through this.
     * Note: this could be marked `final` to prevent override (see Example 05).
     */
    public function generate(): string {
        $rows      = $this->formatRows();           // Calls subclass implementation
        $structure = $this->wrapInStructure($rows); // Calls subclass implementation
        return $this->addMetadata($structure);       // Appends shared metadata
    }

    /**
     * Shared metadata footer — identical in all formats.
     */
    protected function addMetadata(string $content): string {
        return $content
            . "\n-- Generated: {$this->generatedAt}"
            . " | Title: {$this->title}"
            . " | Rows: " . count($this->data);
    }

    /**
     * Shared data summary — available to any subclass that wants it.
     */
    protected function summarise(): string {
        $total = array_sum(array_column($this->data, 'value'));
        return "Total: {$total}";
    }

    /**
     * Save the generated report. Identical for all formats — file handling is shared.
     */
    public function save(string $directory): string {
        $filename = strtolower(str_replace(' ', '-', $this->title))
                  . '.' . $this->getExtension();
        $path     = rtrim($directory, '/') . '/' . $filename;
        // In a real app: file_put_contents($path, $this->generate());
        echo "[SAVE] Would write to: {$path}\n";
        return $path;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Concrete subclasses — each fills in the abstract gaps
// ─────────────────────────────────────────────────────────────────────────────

class CsvReport extends Report {
    // Fills gap 1: format rows as CSV lines
    protected function formatRows(): string {
        $lines = ['name,value,category'];
        foreach ($this->data as $row) {
            $lines[] = "{$row['name']},{$row['value']},{$row['category']}";
        }
        return implode("\n", $lines);
    }

    // Fills gap 2: CSV has no outer wrapper — just return the rows
    protected function wrapInStructure(string $rows): string {
        return $rows;
    }

    // Fills gap 3: file extension
    public function getExtension(): string { return 'csv'; }
}

class HtmlReport extends Report {
    // Fills gap 1: format rows as HTML table rows
    protected function formatRows(): string {
        $rows = '';
        foreach ($this->data as $row) {
            $rows .= "<tr><td>{$row['name']}</td><td>{$row['value']}</td>"
                   . "<td>{$row['category']}</td></tr>\n";
        }
        return $rows;
    }

    // Fills gap 2: wrap rows in an HTML table with header
    protected function wrapInStructure(string $rows): string {
        return "<table>\n"
             . "  <thead><tr><th>Name</th><th>Value</th><th>Category</th></tr></thead>\n"
             . "  <tbody>\n{$rows}  </tbody>\n"
             . "</table>\n"
             . "<p>" . $this->summarise() . "</p>"; // Uses inherited concrete method
    }

    public function getExtension(): string { return 'html'; }
}

class JsonReport extends Report {
    // Fills gap 1: format rows as JSON-ready data (not yet encoded)
    protected function formatRows(): string {
        return json_encode($this->data, JSON_PRETTY_PRINT);
    }

    // Fills gap 2: wrap in a JSON envelope
    protected function wrapInStructure(string $rows): string {
        return json_encode([
            'title'   => $this->title,
            'summary' => $this->summarise(), // Uses inherited concrete method
            'data'    => json_decode($rows),
        ], JSON_PRETTY_PRINT);
    }

    public function getExtension(): string { return 'json'; }
}


// ─────────────────────────────────────────────────────────────────────────────
// Using the reports
// ─────────────────────────────────────────────────────────────────────────────

$data = [
    ['name' => 'Widget A', 'value' => 1200, 'category' => 'Hardware'],
    ['name' => 'Widget B', 'value' => 850,  'category' => 'Software'],
    ['name' => 'Widget C', 'value' => 430,  'category' => 'Hardware'],
];

echo "── CSV Report ───────────────────────────────────────\n\n";
$csv = new CsvReport('Q4 Sales', $data);
echo $csv->generate() . "\n\n";
$csv->save('/var/reports');

echo "\n── HTML Report ──────────────────────────────────────\n\n";
$html = new HtmlReport('Q4 Sales', $data);
echo $html->generate() . "\n\n";
$html->save('/var/reports');

echo "\n── JSON Report ──────────────────────────────────────\n\n";
$json = new JsonReport('Q4 Sales', $data);
echo $json->generate() . "\n\n";
$json->save('/var/reports');


// ─────────────────────────────────────────────────────────────────────────────
// instanceof — abstract classes work just like interfaces here
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── instanceof checks ────────────────────────────────\n\n";

$reports = [
    new CsvReport('Test', $data),
    new HtmlReport('Test', $data),
    new JsonReport('Test', $data),
];

foreach ($reports as $report) {
    $name = get_class($report);
    echo "{$name} instanceof Report? " . ($report instanceof Report ? 'YES' : 'NO') . "\n";
}


// ─────────────────────────────────────────────────────────────────────────────
// EXPERIMENT — uncomment to see PHP enforce the abstract contract
// ─────────────────────────────────────────────────────────────────────────────

/*
class BrokenReport extends Report {
    // Missing all three abstract methods
    // Fatal error: Class BrokenReport contains 3 abstract methods and must
    // therefore be declared abstract or implement the remaining methods
}
$r = new BrokenReport('test', []);
*/

/*
// Cannot instantiate the abstract class itself
$r = new Report('test', []); // Fatal error: Cannot instantiate abstract class Report
*/

echo "\n--- Recap ---\n";
echo "Abstract methods:  enforce the contract — subclasses MUST implement them.\n";
echo "Concrete methods:  share implementation — subclasses inherit them for free.\n";
echo "Private abstract:  ILLEGAL — private methods cannot be overridden.\n";
echo "Protected:         the ideal visibility for abstract methods (subclass only).\n";