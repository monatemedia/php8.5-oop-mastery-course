<?php
declare(strict_types=1);

/**
 * CHALLENGE STARTER — Lesson 1.3: Traits
 * ────────────────────────────────────────
 * Read CHALLENGE.md before touching this file.
 *
 * All four classes below WORK — but logging, timestamps, and toJson()
 * are copy-pasted across every class. Your job is to extract these into
 * three interface + trait pairs, then refactor each class to use them.
 *
 * Rules:
 *  - Do NOT change what gets printed to the screen.
 *  - Keep each class's toArray() method — it is intentionally unique.
 *  - Do NOT look at solution.php until you have made a genuine attempt.
 */


// ─────────────────────────────────────────────────────────────────────────────
// TODO 1: Define interface Loggable + trait LoggableTrait
// ─────────────────────────────────────────────────────────────────────────────


// ─────────────────────────────────────────────────────────────────────────────
// TODO 2: Define interface Timestampable + trait TimestampableTrait
// ─────────────────────────────────────────────────────────────────────────────


// ─────────────────────────────────────────────────────────────────────────────
// TODO 3: Define interface JsonSerialisable + trait JsonSerialisableTrait
//         (toJson() only — toArray() stays in each class)
// ─────────────────────────────────────────────────────────────────────────────


// ─────────────────────────────────────────────────────────────────────────────
// Content hierarchy
// ─────────────────────────────────────────────────────────────────────────────

class BlogPost {   // TODO: implement Loggable, Timestampable, JsonSerialisable
                   // TODO: use LoggableTrait, TimestampableTrait, JsonSerialisableTrait

    // ❗ DUPLICATED logging state + methods (same in all four classes)
    private array $log = [];

    private function addLog(string $action, array $context = []): void {
        $this->log[] = [
            'action'    => $action,
            'context'   => $context,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    public function getLogs(): array { return $this->log; }

    public function printLogs(): void {
        foreach ($this->log as $entry) {
            $ctx = empty($entry['context']) ? '{}' : json_encode($entry['context']);
            echo "  [{$entry['timestamp']}] {$entry['action']} {$ctx}\n";
        }
    }

    // ❗ DUPLICATED timestamp state + methods (same in all four classes)
    private string $createdAt;
    private string $updatedAt;

    private function initTimestamps(): void {
        $now = date('Y-m-d H:i:s');
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function touchUpdatedAt(): void {
        $this->updatedAt = date('Y-m-d H:i:s');
    }

    public function getCreatedAt(): string { return $this->createdAt; }
    public function getUpdatedAt(): string { return $this->updatedAt; }

    // ── Business logic — unique to BlogPost ──────────────────────────────────
    private string $slug;

    public function __construct(
        private string $title,
        private string $author,
        private string $body
    ) {
        $this->initTimestamps();
        $this->addLog('created');
        $this->slug = $this->makeSlug($title);
    }

    public function publish(): void {
        $this->addLog('published', ['slug' => $this->slug]);
        $this->touchUpdatedAt();
    }

    private function makeSlug(string $title): string {
        return strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $title), '-'));
    }

    // ── toArray — intentionally unique to BlogPost ───────────────────────────
    public function toArray(): array {
        return [
            'title'      => $this->title,
            'slug'       => $this->slug,
            'author'     => $this->author,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    // ❗ DUPLICATED toJson (same in all four classes)
    public function toJson(): string {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }
}


class LandingPage {   // TODO: implement Loggable, Timestampable, JsonSerialisable
                      // TODO: use LoggableTrait, TimestampableTrait, JsonSerialisableTrait

    // ❗ DUPLICATED logging state + methods
    private array $log = [];

    private function addLog(string $action, array $context = []): void {
        $this->log[] = [
            'action'    => $action,
            'context'   => $context,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    public function getLogs(): array { return $this->log; }

    public function printLogs(): void {
        foreach ($this->log as $entry) {
            $ctx = empty($entry['context']) ? '{}' : json_encode($entry['context']);
            echo "  [{$entry['timestamp']}] {$entry['action']} {$ctx}\n";
        }
    }

    // ❗ DUPLICATED timestamp state + methods
    private string $createdAt;
    private string $updatedAt;

    private function initTimestamps(): void {
        $now = date('Y-m-d H:i:s');
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function touchUpdatedAt(): void {
        $this->updatedAt = date('Y-m-d H:i:s');
    }

    public function getCreatedAt(): string { return $this->createdAt; }
    public function getUpdatedAt(): string { return $this->updatedAt; }

    // ── Business logic — unique to LandingPage ───────────────────────────────
    private bool $isLive = false;

    public function __construct(
        private string $url,
        private string $headline,
        private string $campaign
    ) {
        $this->initTimestamps();
        $this->addLog('created');
    }

    public function goLive(): void {
        $this->isLive = true;
        $this->addLog('went_live', ['url' => $this->url, 'campaign' => $this->campaign]);
        $this->touchUpdatedAt();
    }

    // ── toArray — intentionally unique to LandingPage ────────────────────────
    public function toArray(): array {
        return [
            'url'        => $this->url,
            'headline'   => $this->headline,
            'campaign'   => $this->campaign,
            'is_live'    => $this->isLive,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    // ❗ DUPLICATED toJson
    public function toJson(): string {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Commerce hierarchy (completely unrelated to ContentBase)
// ─────────────────────────────────────────────────────────────────────────────

class Product {   // TODO: implement Loggable, Timestampable, JsonSerialisable
                  // TODO: use LoggableTrait, TimestampableTrait, JsonSerialisableTrait

    // ❗ DUPLICATED logging state + methods
    private array $log = [];

    private function addLog(string $action, array $context = []): void {
        $this->log[] = [
            'action'    => $action,
            'context'   => $context,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    public function getLogs(): array { return $this->log; }

    public function printLogs(): void {
        foreach ($this->log as $entry) {
            $ctx = empty($entry['context']) ? '{}' : json_encode($entry['context']);
            echo "  [{$entry['timestamp']}] {$entry['action']} {$ctx}\n";
        }
    }

    // ❗ DUPLICATED timestamp state + methods
    private string $createdAt;
    private string $updatedAt;

    private function initTimestamps(): void {
        $now = date('Y-m-d H:i:s');
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function touchUpdatedAt(): void {
        $this->updatedAt = date('Y-m-d H:i:s');
    }

    public function getCreatedAt(): string { return $this->createdAt; }
    public function getUpdatedAt(): string { return $this->updatedAt; }

    // ── Business logic — unique to Product ───────────────────────────────────

    public function __construct(
        private string $sku,
        private string $name,
        private float  $price,
        private int    $stock
    ) {
        $this->initTimestamps();
        $this->addLog('created');
    }

    public function adjustStock(int $newStock): void {
        $this->addLog('stock_updated', ['from' => $this->stock, 'to' => $newStock]);
        $this->stock = $newStock;
        $this->touchUpdatedAt();
    }

    // ── toArray — intentionally unique to Product ─────────────────────────────
    public function toArray(): array {
        return [
            'sku'        => $this->sku,
            'name'       => $this->name,
            'price'      => $this->price,
            'stock'      => $this->stock,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    // ❗ DUPLICATED toJson
    public function toJson(): string {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }
}


class Order {   // TODO: implement Loggable, Timestampable, JsonSerialisable
                // TODO: use LoggableTrait, TimestampableTrait, JsonSerialisableTrait

    // ❗ DUPLICATED logging state + methods
    private array $log = [];

    private function addLog(string $action, array $context = []): void {
        $this->log[] = [
            'action'    => $action,
            'context'   => $context,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    public function getLogs(): array { return $this->log; }

    public function printLogs(): void {
        foreach ($this->log as $entry) {
            $ctx = empty($entry['context']) ? '{}' : json_encode($entry['context']);
            echo "  [{$entry['timestamp']}] {$entry['action']} {$ctx}\n";
        }
    }

    // ❗ DUPLICATED timestamp state + methods
    private string $createdAt;
    private string $updatedAt;

    private function initTimestamps(): void {
        $now = date('Y-m-d H:i:s');
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function touchUpdatedAt(): void {
        $this->updatedAt = date('Y-m-d H:i:s');
    }

    public function getCreatedAt(): string { return $this->createdAt; }
    public function getUpdatedAt(): string { return $this->updatedAt; }

    // ── Business logic — unique to Order ─────────────────────────────────────
    private string $status = 'pending';

    public function __construct(
        private int   $id,
        private float $total,
        private string $customerEmail
    ) {
        $this->initTimestamps();
        $this->addLog('created', ['id' => $id, 'total' => $total]);
    }

    public function confirm(): void {
        $this->status = 'confirmed';
        $this->addLog('confirmed');
        $this->touchUpdatedAt();
    }

    public function ship(): void {
        $this->status = 'shipped';
        $this->addLog('shipped', ['customer' => $this->customerEmail]);
        $this->touchUpdatedAt();
    }

    // ── toArray — intentionally unique to Order ───────────────────────────────
    public function toArray(): array {
        return [
            'id'             => $this->id,
            'status'         => $this->status,
            'total'          => $this->total,
            'customer_email' => $this->customerEmail,
            'created_at'     => $this->createdAt,
            'updated_at'     => $this->updatedAt,
        ];
    }

    // ❗ DUPLICATED toJson
    public function toJson(): string {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// TODO 4: Add two type-safe functions
// function printEntityLog(Loggable $entity): void { ... }
// function exportToJson(JsonSerialisable $entity): string { ... }
// ─────────────────────────────────────────────────────────────────────────────


// ─────────────────────────────────────────────────────────────────────────────
// CURRENT usage — output must remain UNCHANGED after your refactor
// ─────────────────────────────────────────────────────────────────────────────

$post = new BlogPost('My First Post', 'Alice', 'Body content here.');
$post->publish();

$page = new LandingPage('/promo/summer', 'Summer Sale — 50% Off', 'SUMMER2024');
$page->goLive();

$product = new Product('WDG-001', 'Widget Pro', 299.00, 100);
$product->adjustStock(85);

$order = new Order(1042, 1500.00, 'bob@example.com');
$order->confirm();
$order->ship();

echo "=== BlogPost ===\n";
echo "Logs:\n";
$post->printLogs();
echo "JSON:\n" . $post->toJson() . "\n";

echo "\n=== LandingPage ===\n";
echo "Logs:\n";
$page->printLogs();
echo "JSON:\n" . $page->toJson() . "\n";

echo "\n=== Product ===\n";
echo "Logs:\n";
$product->printLogs();
echo "JSON:\n" . $product->toJson() . "\n";

echo "\n=== Order ===\n";
echo "Logs:\n";
$order->printLogs();
echo "JSON:\n" . $order->toJson() . "\n";

// TODO: Add calls using your two type-safe functions after the refactor:
// echo "\n=== Type-safe function calls ===\n";
// printEntityLog($post);
// echo exportToJson($product);