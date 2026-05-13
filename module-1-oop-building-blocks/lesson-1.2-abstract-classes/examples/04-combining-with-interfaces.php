<?php
declare(strict_types=1);

/**
 * Example 04 — Combining Abstract Classes with Interfaces
 * ---------------------------------------------------------
 * The most common real-world architecture:
 *   Abstract class  → shared "is-a" implementation
 *   Interface(s)    → additional "can-do" capabilities
 *   Concrete class  → extends one abstract + implements many interfaces
 *
 * Scenario: A data export pipeline. Different exporters share
 * common buffering and error-handling logic (abstract class),
 * but some exporters are also Compressible or Encryptable (interfaces).
 *
 * ┌─────────────────────────────────────────────────────────┐
 * │              <<abstract>>  Exporter                     │
 * │  + __construct(string $filename)                        │
 * │  # abstract: encode(array $data): string                │
 * │  # abstract: getMimeType(): string                      │
 * │  + export(array $data): void          ← shared          │
 * │  # writeToBuffer(string $content)     ← shared          │
 * └──────────────────────┬──────────────────────────────────┘
 *                        │ extends
 *          ┌─────────────┼──────────────┐
 *          ▼             ▼              ▼
 *      CsvExporter   JsonExporter   XmlExporter
 *   implements          implements
 *   Compressible        Encryptable
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Abstract Class + Interfaces Combined               ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// Interfaces — opt-in capabilities
// ─────────────────────────────────────────────────────────────────────────────

interface Compressible {
    public function compress(): void;
    public function getCompressionAlgorithm(): string;
}

interface Encryptable {
    public function encrypt(string $passphrase): void;
    public function isEncrypted(): bool;
}

interface Streamable {
    public function stream(): \Generator;
}


// ─────────────────────────────────────────────────────────────────────────────
// Abstract base — shared identity and implementation
// ─────────────────────────────────────────────────────────────────────────────

abstract class Exporter {
    protected string $buffer  = '';
    protected array  $errors  = [];
    protected bool   $exported = false;

    public function __construct(protected string $filename) {
        echo "[EXPORTER] Initialised: {$filename}\n";
    }

    // ── Abstract: each format encodes data differently ────────────────────────
    abstract protected function encode(array $data): string;
    abstract public function getMimeType(): string;
    abstract public function getExtension(): string;

    // ── Concrete: shared export pipeline ─────────────────────────────────────
    public function export(array $data): void {
        if (empty($data)) {
            $this->addError("No data to export.");
            return;
        }

        $this->buffer   = $this->encode($data); // Calls subclass implementation
        $this->exported = true;

        $path = $this->filename . '.' . $this->getExtension();
        echo "[EXPORT] {$path} | MIME: {$this->getMimeType()} | " . strlen($this->buffer) . " bytes\n";
    }

    // ── Concrete: shared error handling ──────────────────────────────────────
    protected function addError(string $message): void {
        $this->errors[] = $message;
        echo "[ERROR] " . get_class($this) . ": {$message}\n";
    }

    public function hasErrors(): bool   { return !empty($this->errors); }
    public function getErrors(): array  { return $this->errors; }
    public function getBuffer(): string { return $this->buffer; }
}


// ─────────────────────────────────────────────────────────────────────────────
// Concrete exporters — extend abstract + selectively implement interfaces
// ─────────────────────────────────────────────────────────────────────────────

/**
 * CSV Exporter — also Compressible (large CSVs benefit from gzip).
 */
class CsvExporter extends Exporter implements Compressible {
    private bool $compressed = false;

    protected function encode(array $data): string {
        $rows = [];
        if (!empty($data)) {
            $rows[] = implode(',', array_keys(reset($data))); // Header row
        }
        foreach ($data as $row) {
            $rows[] = implode(',', array_map(fn($v) => "\"{$v}\"", $row));
        }
        return implode("\n", $rows);
    }

    public function getMimeType(): string  { return 'text/csv'; }
    public function getExtension(): string { return 'csv'; }

    // Compressible implementation
    public function compress(): void {
        if (!$this->exported) {
            $this->addError("Cannot compress before exporting.");
            return;
        }
        $originalSize   = strlen($this->buffer);
        $this->buffer   = gzencode($this->buffer) ?: $this->buffer;
        $this->compressed = true;
        $compressedSize = strlen($this->buffer);
        echo "[COMPRESS] {$this->getCompressionAlgorithm()}: {$originalSize}B → {$compressedSize}B\n";
    }

    public function getCompressionAlgorithm(): string { return 'gzip'; }
}

/**
 * JSON Exporter — also Encryptable (sensitive JSON payloads can be secured).
 */
class JsonExporter extends Exporter implements Encryptable {
    private bool $encrypted = false;

    protected function encode(array $data): string {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function getMimeType(): string  { return 'application/json'; }
    public function getExtension(): string { return 'json'; }

    // Encryptable implementation
    public function encrypt(string $passphrase): void {
        if (!$this->exported) {
            $this->addError("Cannot encrypt before exporting.");
            return;
        }
        // Simplified encryption simulation (not real security — demo only)
        $this->buffer    = base64_encode($this->buffer);
        $this->encrypted = true;
        echo "[ENCRYPT] JSON payload encrypted (passphrase: " . str_repeat('*', strlen($passphrase)) . ")\n";
    }

    public function isEncrypted(): bool { return $this->encrypted; }
}

/**
 * XML Exporter — Compressible AND Streamable (large XML benefits from both).
 */
class XmlExporter extends Exporter implements Compressible, Streamable {
    private bool $compressed = false;

    protected function encode(array $data): string {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<records>\n";
        foreach ($data as $row) {
            $xml .= "  <record>\n";
            foreach ($row as $key => $value) {
                $xml .= "    <{$key}>" . htmlspecialchars((string)$value) . "</{$key}>\n";
            }
            $xml .= "  </record>\n";
        }
        $xml .= "</records>";
        return $xml;
    }

    public function getMimeType(): string  { return 'application/xml'; }
    public function getExtension(): string { return 'xml'; }

    public function compress(): void {
        $size = strlen($this->buffer);
        $this->buffer = gzencode($this->buffer) ?: $this->buffer;
        $this->compressed = true;
        echo "[COMPRESS] {$this->getCompressionAlgorithm()}: {$size}B → " . strlen($this->buffer) . "B\n";
    }

    public function getCompressionAlgorithm(): string { return 'deflate'; }

    public function stream(): \Generator {
        foreach (str_split($this->buffer, 1024) as $i => $chunk) {
            echo "[STREAM] Chunk " . ($i + 1) . " (" . strlen($chunk) . " bytes)\n";
            yield $chunk;
        }
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Using the exporters
// ─────────────────────────────────────────────────────────────────────────────

$data = [
    ['name' => 'Alice',   'role' => 'Admin',  'salary' => 85000],
    ['name' => 'Bob',     'role' => 'Dev',    'salary' => 72000],
    ['name' => 'Charlie', 'role' => 'Design', 'salary' => 68000],
];

echo "── CSV + Compress ───────────────────────────────────\n\n";
$csv = new CsvExporter('staff-report');
$csv->export($data);
$csv->compress();

echo "\n── JSON + Encrypt ───────────────────────────────────\n\n";
$json = new JsonExporter('staff-data');
$json->export($data);
$json->encrypt('super-secret-passphrase');
echo "Buffer is encrypted: " . ($json->isEncrypted() ? 'YES' : 'NO') . "\n";

echo "\n── XML + Compress + Stream ──────────────────────────\n\n";
$xml = new XmlExporter('staff-xml');
$xml->export($data);
$xml->compress();
foreach ($xml->stream() as $chunk) {
    // Process chunk in real streaming scenario
}


// ─────────────────────────────────────────────────────────────────────────────
// Functions typed against different levels
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Type-hinting at different levels ─────────────────\n\n";

// Accepts any Exporter — works with all three
function runExport(Exporter $exporter, array $data): void {
    echo "Exporting as " . $exporter->getExtension() . "...\n";
    $exporter->export($data);
}

// Accepts only Compressible exporters
function exportAndCompress(Exporter&Compressible $exporter, array $data): void {
    $exporter->export($data);
    $exporter->compress();
    echo "Algorithm used: " . $exporter->getCompressionAlgorithm() . "\n";
}

runExport(new JsonExporter('output'), $data);
exportAndCompress(new CsvExporter('compressed-output'), $data);
exportAndCompress(new XmlExporter('compressed-xml'), $data);
// exportAndCompress(new JsonExporter('j'), $data); // PHP type error — JsonExporter is not Compressible

echo "\n── instanceof matrix ────────────────────────────────\n\n";

$exporters = [
    'CsvExporter'  => new CsvExporter('test'),
    'JsonExporter' => new JsonExporter('test'),
    'XmlExporter'  => new XmlExporter('test'),
];

$checks = [Exporter::class, Compressible::class, Encryptable::class, Streamable::class];

foreach ($exporters as $name => $exporter) {
    echo "{$name}:\n";
    foreach ($checks as $type) {
        $short = (new \ReflectionClass($type))->getShortName();
        echo "  {$short}: " . ($exporter instanceof $type ? '✓' : '✗') . "\n";
    }
}

echo "\n--- Recap ---\n";
echo "Abstract class:  provides shared 'is-a' code (encode pipeline, error handling).\n";
echo "Interfaces:      provide opt-in 'can-do' capabilities (Compressible, Encryptable).\n";
echo "Concrete class:  extends ONE abstract + implements the interfaces it genuinely supports.\n";
echo "Intersection type hint (Exporter&Compressible): requires BOTH simultaneously.\n";