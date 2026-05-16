<?php
declare(strict_types=1);

/**
 * Example 03 — Extending Classes Anonymously
 * ---------------------------------------------
 * Anonymous classes can extend both concrete and abstract parent classes.
 * This lets you create quick one-off overrides without polluting your
 * namespace with named subclasses that are only used once.
 *
 * Three scenarios:
 *   A. Extending a concrete class — override specific behaviour
 *   B. Extending an abstract class — fulfil abstract requirements inline
 *   C. Extend + implement — combine a parent class with an interface
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Extending Classes Anonymously                      ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART A — Extending a concrete class
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part A: Extending a concrete class ───────────────\n\n";

class BaseFormatter {
    protected string $prefix = '';
    protected string $suffix = '';

    public function format(string $text): string {
        return $this->prefix . $text . $this->suffix;
    }

    public function formatAll(array $items): array {
        return array_map(fn(string $i) => $this->format($i), $items);
    }
}

// Named subclass — reusable, worth naming
class HtmlFormatter extends BaseFormatter {
    protected string $prefix = '<p>';
    protected string $suffix = '</p>';
}

// Anonymous subclass — one-off, not worth a named class
$bracketFormatter = new class extends BaseFormatter {
    protected string $prefix = '[';
    protected string $suffix = ']';
};

$markdownFormatter = new class extends BaseFormatter {
    protected string $prefix = '**';
    protected string $suffix = '**';

    // Override parent method to add extra behaviour
    public function format(string $text): string {
        return parent::format(strtolower($text));
    }
};

$items = ['Hello', 'World', 'PHP'];

echo "Named HtmlFormatter:\n";
$html = new HtmlFormatter();
foreach ($html->formatAll($items) as $item) {
    echo "  {$item}\n";
}

echo "\nAnonymous bracketFormatter:\n";
foreach ($bracketFormatter->formatAll($items) as $item) {
    echo "  {$item}\n";
}

echo "\nAnonymous markdownFormatter (also lowercases):\n";
foreach ($markdownFormatter->formatAll($items) as $item) {
    echo "  {$item}\n";
}

// instanceof against the PARENT class works
var_dump($bracketFormatter instanceof BaseFormatter);    // true
var_dump($markdownFormatter instanceof BaseFormatter);   // true


// ─────────────────────────────────────────────────────────────────────────────
// PART B — Extending an abstract class
// Fulfil abstract requirements inline without a separate file
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part B: Extending an abstract class ─────────────\n\n";

abstract class ReportGenerator {
    public function __construct(protected string $title) {}

    // Abstract: subclass provides the data rows
    abstract protected function fetchRows(): array;

    // Abstract: subclass provides the column headers
    abstract protected function headers(): array;

    // Concrete: shared rendering pipeline
    final public function render(): string {
        $rows    = $this->fetchRows();
        $headers = $this->headers();

        $output = "=== {$this->title} ===\n";
        $output .= implode(' | ', $headers) . "\n";
        $output .= str_repeat('-', 40) . "\n";

        foreach ($rows as $row) {
            $output .= implode(' | ', $row) . "\n";
        }

        $output .= str_repeat('-', 40) . "\n";
        $output .= "Total rows: " . count($rows) . "\n";
        return $output;
    }
}

// Inline fulfilment of the abstract class — no named class needed
$salesReport = new class('Monthly Sales') extends ReportGenerator {
    protected function headers(): array {
        return ['Product', 'Units', 'Revenue'];
    }

    protected function fetchRows(): array {
        // In a real app this would query a database
        return [
            ['Widget A',  '120', 'R3,600'],
            ['Widget B',  '85',  'R5,100'],
            ['Widget C',  '210', 'R2,100'],
        ];
    }
};

echo $salesReport->render();

// Another quick report — different data, same pipeline
$userReport = new class('Active Users') extends ReportGenerator {
    protected function headers(): array {
        return ['Name', 'Email', 'Joined'];
    }

    protected function fetchRows(): array {
        return [
            ['Alice', 'alice@example.com', '2023-01-15'],
            ['Bob',   'bob@example.com',   '2023-06-20'],
        ];
    }
};

echo "\n" . $userReport->render();


// ─────────────────────────────────────────────────────────────────────────────
// PART C — Extend a class AND implement an interface
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part C: Extend + implement ───────────────────────\n\n";

interface Exportable {
    public function export(): string;
}

class BaseModel {
    protected array $data = [];

    public function __construct(array $data) {
        $this->data = $data;
    }

    public function get(string $key): mixed {
        return $this->data[$key] ?? null;
    }

    public function all(): array { return $this->data; }
}

// Extends BaseModel AND implements Exportable
$exportableUser = new class(['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'])
    extends BaseModel
    implements Exportable
{
    public function export(): string {
        return json_encode($this->data, JSON_PRETTY_PRINT);
    }

    public function displayName(): string {
        return $this->get('name') ?? 'Anonymous';
    }
};

echo "Name:   " . $exportableUser->displayName() . "\n";
echo "Email:  " . $exportableUser->get('email')  . "\n";
echo "Export:\n" . $exportableUser->export() . "\n";

// instanceof works for both the parent and the interface
var_dump($exportableUser instanceof BaseModel);   // true
var_dump($exportableUser instanceof Exportable);  // true

// Type-safe usage via the interface
function exportEntity(Exportable $entity): void {
    echo "[EXPORT] " . substr($entity->export(), 0, 50) . "...\n";
}

exportEntity($exportableUser); // ✓ — satisfies Exportable


// ─────────────────────────────────────────────────────────────────────────────
// PART D — Calling parent::__construct() from an anonymous class
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part D: Parent constructor call ──────────────────\n\n";

class Connection {
    private bool $connected = false;

    public function __construct(
        protected string $host,
        protected int    $port
    ) {
        echo "[CONNECTION] Initialised {$host}:{$port}\n";
    }

    public function connect(): void {
        $this->connected = true;
        echo "[CONNECTION] Connected to {$this->host}:{$this->port}\n";
    }

    public function isConnected(): bool { return $this->connected; }
}

// Anonymous subclass calls parent constructor
$testConnection = new class('localhost', 3306) extends Connection {
    private array $queries = [];

    public function __construct(string $host, int $port) {
        parent::__construct($host, $port); // Required — same as named subclass
    }

    public function query(string $sql): array {
        $this->queries[] = $sql;
        echo "[TEST-DB] Query: {$sql}\n";
        return [['id' => 1, 'name' => 'Test']]; // Fake result
    }

    public function getQueryLog(): array { return $this->queries; }
};

$testConnection->connect();
$result = $testConnection->query("SELECT * FROM users WHERE id = 1");
echo "Rows: " . count($result) . "\n";
echo "Query log: " . implode(', ', $testConnection->getQueryLog()) . "\n";
echo "Is connected: " . ($testConnection->isConnected() ? 'YES' : 'NO') . "\n";

echo "\n--- Recap ---\n";
echo "extends in anonymous class: same syntax as named class extension.\n";
echo "parent::__construct(): required when parent has a constructor — call it.\n";
echo "instanceof works against: the parent class, any interface it implements.\n";
echo "Abstract classes: fulfil abstract requirements inline — no named class needed.\n";
echo "Use case: one-off overrides, quick report generators, test database stubs.\n";