<?php
declare(strict_types=1);

/**
 * CHALLENGE SOLUTION — Lesson 1.3: Traits
 * ──────────────────────────────────────────
 * ⚠️  Only open this file after completing starter.php yourself.
 *
 * Key things to compare in your solution:
 *   1. Are your three interfaces defined correctly with the right signatures?
 *   2. Do LoggableTrait and TimestampableTrait own all the state (properties)?
 *   3. Does JsonSerialisableTrait provide ONLY toJson() — not toArray()?
 *   4. Are all four classes free of duplicated log/timestamp/toJson code?
 *   5. Are printEntityLog() and exportToJson() typed against interfaces?
 */


// ─────────────────────────────────────────────────────────────────────────────
// INTERFACES — the type contracts
// ─────────────────────────────────────────────────────────────────────────────

interface Loggable {
    public function addLog(string $action, array $context = []): void;
    public function getLogs(): array;
    public function printLogs(): void;
}

interface Timestampable {
    public function getCreatedAt(): string;
    public function getUpdatedAt(): string;
    public function touchUpdatedAt(): void;
}

interface JsonSerialisable {
    public function toArray(): array;   // Each class provides its own
    public function toJson(): string;   // Trait provides this
}


// ─────────────────────────────────────────────────────────────────────────────
// TRAITS — the shared implementations
// ─────────────────────────────────────────────────────────────────────────────

trait LoggableTrait {
    private array $log = [];

    public function addLog(string $action, array $context = []): void {
        $this->log[] = [
            'action'    => $action,
            'context'   => $context,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    public function getLogs(): array {
        return $this->log;
    }

    public function printLogs(): void {
        foreach ($this->log as $entry) {
            $ctx = empty($entry['context']) ? '{}' : json_encode($entry['context']);
            echo "  [{$entry['timestamp']}] {$entry['action']} {$ctx}\n";
        }
    }
}

trait TimestampableTrait {
    private string $createdAt;
    private string $updatedAt;

    // Not in the interface — this is an internal init helper called from constructors
    public function initTimestamps(): void {
        $now             = date('Y-m-d H:i:s');
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getCreatedAt(): string { return $this->createdAt; }
    public function getUpdatedAt(): string { return $this->updatedAt; }

    public function touchUpdatedAt(): void {
        $this->updatedAt = date('Y-m-d H:i:s');
    }
}

trait JsonSerialisableTrait {
    // toArray() is NOT implemented here — each host class provides its own.
    // toJson() delegates to toArray(), so it works correctly for every class.
    public function toJson(): string {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// CONTENT HIERARCHY — refactored
// Each class: implement 3 interfaces + use 3 traits + keep unique toArray()
// ─────────────────────────────────────────────────────────────────────────────

class BlogPost implements Loggable, Timestampable, JsonSerialisable {
    use LoggableTrait, TimestampableTrait, JsonSerialisableTrait;

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

    public function toArray(): array {
        return [
            'title'      => $this->title,
            'slug'       => $this->slug,
            'author'     => $this->author,
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
        ];
    }
}

class LandingPage implements Loggable, Timestampable, JsonSerialisable {
    use LoggableTrait, TimestampableTrait, JsonSerialisableTrait;

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

    public function toArray(): array {
        return [
            'url'        => $this->url,
            'headline'   => $this->headline,
            'campaign'   => $this->campaign,
            'is_live'    => $this->isLive,
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
        ];
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// COMMERCE HIERARCHY — refactored
// Completely unrelated to the content hierarchy above — traits bridge the gap
// ─────────────────────────────────────────────────────────────────────────────

class Product implements Loggable, Timestampable, JsonSerialisable {
    use LoggableTrait, TimestampableTrait, JsonSerialisableTrait;

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

    public function toArray(): array {
        return [
            'sku'        => $this->sku,
            'name'       => $this->name,
            'price'      => $this->price,
            'stock'      => $this->stock,
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
        ];
    }
}

class Order implements Loggable, Timestampable, JsonSerialisable {
    use LoggableTrait, TimestampableTrait, JsonSerialisableTrait;

    private string $status = 'pending';

    public function __construct(
        private int    $id,
        private float  $total,
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

    public function toArray(): array {
        return [
            'id'             => $this->id,
            'status'         => $this->status,
            'total'          => $this->total,
            'customer_email' => $this->customerEmail,
            'created_at'     => $this->getCreatedAt(),
            'updated_at'     => $this->getUpdatedAt(),
        ];
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// TYPE-SAFE FUNCTIONS — typed against interfaces, not trait names
// ─────────────────────────────────────────────────────────────────────────────

function printEntityLog(Loggable $entity): void {
    echo "Logs for " . get_class($entity) . ":\n";
    $entity->printLogs();
}

function exportToJson(JsonSerialisable $entity): string {
    return $entity->toJson();
}


// ─────────────────────────────────────────────────────────────────────────────
// USAGE — output matches the starter file exactly
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

echo "\n=== Type-safe function calls ===\n";
printEntityLog($post);
printEntityLog($order);
echo "\nProduct JSON via exportToJson():\n";
echo exportToJson($product) . "\n";


// ─────────────────────────────────────────────────────────────────────────────
// BONUS: Adding a fifth model requires zero changes to traits or interfaces
// ─────────────────────────────────────────────────────────────────────────────

class Customer implements Loggable, Timestampable, JsonSerialisable {
    use LoggableTrait, TimestampableTrait, JsonSerialisableTrait;

    public function __construct(
        private int    $id,
        private string $email,
        private string $tier = 'standard'
    ) {
        $this->initTimestamps();
        $this->addLog('registered', ['email' => $email]);
    }

    public function upgradeTier(string $tier): void {
        $this->addLog('tier_upgraded', ['from' => $this->tier, 'to' => $tier]);
        $this->tier = $tier;
        $this->touchUpdatedAt();
    }

    public function toArray(): array {
        return [
            'id'         => $this->id,
            'email'      => $this->email,
            'tier'       => $this->tier,
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
        ];
    }
}

echo "\n=== BONUS: Customer (fifth model, zero trait/interface changes) ===\n";
$customer = new Customer(1, 'carol@example.com');
$customer->upgradeTier('premium');
printEntityLog($customer);
echo exportToJson($customer) . "\n";


// ─────────────────────────────────────────────────────────────────────────────
// SELF-REVIEW CHECKLIST
// ─────────────────────────────────────────────────────────────────────────────
echo "\n--- Self-review checklist ---\n";
echo "[ ] Three interfaces defined with the correct method signatures?\n";
echo "[ ] LoggableTrait owns the \$log array and all three log methods?\n";
echo "[ ] TimestampableTrait owns \$createdAt, \$updatedAt and initTimestamps()?\n";
echo "[ ] JsonSerialisableTrait provides toJson() ONLY — not toArray()?\n";
echo "[ ] All four classes have zero duplicated log/timestamp/toJson code?\n";
echo "[ ] printEntityLog() and exportToJson() typed against interfaces, not traits?\n";
echo "[ ] Adding Customer required only: implement + use + toArray() — nothing else changed?\n";