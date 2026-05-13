<?php
declare(strict_types=1);

/**
 * Example 04 — Traits with Interfaces
 * --------------------------------------
 * The most important real-world trait pattern. It solves the one weakness
 * traits have: they are not types, so you cannot type-hint them.
 *
 * The pattern:
 *   1. Interface  — defines the contract (type-safe, for type hints)
 *   2. Trait      — provides the default implementation (avoid repetition)
 *   3. Class      — implements the interface AND uses the trait
 *
 * The class gets type safety FROM the interface and free code FROM the trait.
 * Classes that need a custom implementation can override the trait's methods.
 *
 * This is exactly how Laravel's Notifiable, SoftDeletes, and HasFactory work.
 *
 * Scenario: Three cross-cutting concerns — auditing, caching, and serialisation.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Traits + Interfaces — The Real-World Pattern       ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// CONCERN 1 — Auditing
// ─────────────────────────────────────────────────────────────────────────────

// Step 1: The contract
interface Auditable {
    public function recordChange(string $action, array $context = []): void;
    public function getAuditLog(): array;
    public function getLastAction(): ?string;
}

// Step 2: The default implementation
trait AuditableTrait {
    private array $auditLog = [];

    public function recordChange(string $action, array $context = []): void {
        $this->auditLog[] = [
            'action'    => $action,
            'context'   => $context,
            'actor'     => get_class($this),
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    public function getAuditLog(): array {
        return $this->auditLog;
    }

    public function getLastAction(): ?string {
        return empty($this->auditLog)
            ? null
            : end($this->auditLog)['action'];
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// CONCERN 2 — Caching
// ─────────────────────────────────────────────────────────────────────────────

interface Cacheable {
    public function getCacheKey(): string;
    public function getCacheTtl(): int;
    public function toCachePayload(): array;
}

trait CacheableTrait {
    public function getCacheKey(): string {
        // Default: ClassName:id — classes that need something custom can override
        $id = method_exists($this, 'getId') ? $this->getId() : spl_object_id($this);
        return strtolower((new \ReflectionClass($this))->getShortName()) . ':' . $id;
    }

    public function getCacheTtl(): int {
        return 3600; // Default 1 hour — override per class as needed
    }

    public function toCachePayload(): array {
        // Default: use public properties — override for more control
        return get_object_vars($this);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// CONCERN 3 — JSON Serialisation
// ─────────────────────────────────────────────────────────────────────────────

interface JsonSerialisable {
    public function toJson(): string;
    public function toArray(): array;
}

trait JsonSerialisableTrait {
    public function toJson(): string {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    // Default toArray — classes override this to control which fields are included
    public function toArray(): array {
        return get_object_vars($this);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Model classes — each uses the interfaces + traits pattern
// ─────────────────────────────────────────────────────────────────────────────

class OrderModel implements Auditable, Cacheable, JsonSerialisable {
    use AuditableTrait, CacheableTrait, JsonSerialisableTrait;

    private int    $id;
    private string $status;
    private float  $total;

    public function __construct(int $id, float $total) {
        $this->id     = $id;
        $this->total  = $total;
        $this->status = 'pending';
        $this->recordChange('created', ['id' => $id, 'total' => $total]);
    }

    public function getId(): int { return $this->id; }

    public function confirm(): void {
        $this->status = 'confirmed';
        $this->recordChange('confirmed', ['id' => $this->id]);
    }

    public function ship(): void {
        $this->status = 'shipped';
        $this->recordChange('shipped', ['id' => $this->id]);
    }

    // Override toArray to control exactly what is serialised
    public function toArray(): array {
        return [
            'id'     => $this->id,
            'status' => $this->status,
            'total'  => $this->total,
        ];
    }

    // Override getCacheTtl for orders specifically — 30 minutes
    public function getCacheTtl(): int { return 1800; }
}

class ProductModel implements Cacheable, JsonSerialisable {
    use CacheableTrait, JsonSerialisableTrait;
    // ProductModel does NOT need auditing — it only uses two concerns

    private int    $id;
    private string $name;
    private float  $price;
    private int    $stock;

    public function __construct(int $id, string $name, float $price, int $stock) {
        $this->id    = $id;
        $this->name  = $name;
        $this->price = $price;
        $this->stock = $stock;
    }

    public function getId(): int { return $this->id; }

    public function toArray(): array {
        return [
            'id'    => $this->id,
            'name'  => $this->name,
            'price' => $this->price,
            'stock' => $this->stock,
        ];
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Type-safe functions — typed against interfaces, not trait names
// ─────────────────────────────────────────────────────────────────────────────

function printAuditTrail(Auditable $entity): void {
    echo "Audit trail for " . get_class($entity) . ":\n";
    foreach ($entity->getAuditLog() as $entry) {
        echo "  [{$entry['timestamp']}] {$entry['action']}";
        if (!empty($entry['context'])) {
            echo " " . json_encode($entry['context']);
        }
        echo "\n";
    }
    echo "  Last action: " . ($entity->getLastAction() ?? 'none') . "\n";
}

function storeToCacheLayer(Cacheable $entity): void {
    echo "Caching " . get_class($entity) . ":\n";
    echo "  Key:     " . $entity->getCacheKey() . "\n";
    echo "  TTL:     " . $entity->getCacheTtl() . "s\n";
    echo "  Payload: " . json_encode($entity->toCachePayload()) . "\n";
}

function sendAsJsonResponse(JsonSerialisable $entity): void {
    echo "JSON response for " . get_class($entity) . ":\n";
    echo $entity->toJson() . "\n";
}


// ─────────────────────────────────────────────────────────────────────────────
// Using the models
// ─────────────────────────────────────────────────────────────────────────────

echo "── OrderModel (Auditable + Cacheable + JsonSerialisable) ──\n\n";

$order = new OrderModel(1042, 1500.00);
$order->confirm();
$order->ship();

printAuditTrail($order);
echo "\n";
storeToCacheLayer($order);
echo "\n";
sendAsJsonResponse($order);

echo "\n── ProductModel (Cacheable + JsonSerialisable only) ───\n\n";

$product = new ProductModel(7, 'Widget Pro', 299.00, 50);
storeToCacheLayer($product);
echo "\n";
sendAsJsonResponse($product);

// Type system prevents passing ProductModel where Auditable is required:
// printAuditTrail($product); // ← PHP type error — ProductModel does not implement Auditable


// ─────────────────────────────────────────────────────────────────────────────
// Custom override — a class can override trait methods when defaults won't do
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Custom override of a trait method ───────────────\n\n";

class SensitiveUserModel implements Auditable, JsonSerialisable {
    use AuditableTrait, JsonSerialisableTrait;

    public function __construct(
        private int    $id,
        private string $email,
        private string $passwordHash
    ) {
        $this->recordChange('account_created');
    }

    // Override toArray — exclude sensitive fields
    public function toArray(): array {
        return [
            'id'    => $this->id,
            'email' => $this->email,
            // password_hash deliberately excluded
        ];
    }

    // Override recordChange — redact context values before storing
    public function recordChange(string $action, array $context = []): void {
        // Strip any context values that look like passwords or tokens
        $safe = array_map(
            fn($v) => is_string($v) && strlen($v) > 20 ? '[REDACTED]' : $v,
            $context
        );
        // Delegate to the trait's implementation with the safe context
        // We can call the trait's method directly using an alias trick,
        // or simply re-implement the storage inline:
        $this->auditLog[] = [
            'action'    => $action,
            'context'   => $safe,
            'actor'     => get_class($this),
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }
}

$sensitiveUser = new SensitiveUserModel(1, 'alice@example.com', password_hash('secret', PASSWORD_BCRYPT));
$sensitiveUser->recordChange('password_reset', ['token' => 'a_very_long_reset_token_abc123xyz789']);

printAuditTrail($sensitiveUser);
echo "\nJSON (password_hash excluded):\n";
echo $sensitiveUser->toJson() . "\n";

echo "\n--- Recap ---\n";
echo "Interface:  the TYPE contract — used for type-hints and instanceof.\n";
echo "Trait:      the DEFAULT IMPLEMENTATION — injected to avoid repetition.\n";
echo "Class:      implements interface + uses trait = type-safe + DRY.\n";
echo "Override:   a class can override any trait method when the default is not enough.\n";
echo "Pattern:    interface + trait + class = Laravel's SoftDeletes, Notifiable, etc.\n";