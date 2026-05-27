<?php
declare(strict_types=1);

/**
 * Example 06 — #[Deprecated] on Traits and Constants (PHP 8.5)
 * ---------------------------------------------------------------
 * PHP 8.0 introduced the #[Deprecated] attribute for functions and methods.
 * PHP 8.5 extends it to:
 *   - Traits (the entire trait is deprecated)
 *   - Class constants (a specific constant is deprecated)
 *
 * Before PHP 8.5, deprecating a trait or constant required a @deprecated
 * doc comment — which IDEs could surface but PHP itself never enforced.
 *
 * PHP 8.5 makes these deprecations machine-enforceable:
 *   - Using a deprecated trait triggers a PHP deprecation notice
 *   - Reading a deprecated constant triggers a PHP deprecation notice
 *
 * PHP 8.5+ required for this file.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  #[Deprecated] on Traits and Constants (PHP 8.5)   ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 1 — Before PHP 8.5: doc-comment deprecation (unenforced)
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 1: Before PHP 8.5 — unenforced @deprecated ──\n\n";

/**
 * @deprecated Use LoggableTrait from src/Traits/LoggableTrait.php instead.
 *             This trait will be removed in v3.0.
 */
trait LegacyLogTrait {
    public function writeLog(string $message): void {
        // Old implementation — writes to flat file, no levels, no context
        file_put_contents('/tmp/legacy.log', date('Y-m-d H:i:s') . " {$message}\n", FILE_APPEND);
        echo "  [LEGACY-LOG] {$message}\n";
    }
}

class OldOrderService {
    use LegacyLogTrait; // ← Using deprecated trait — PHP says NOTHING

    public function process(string $orderId): void {
        $this->writeLog("Processing order {$orderId}");
        echo "  [ORDER] Processed: {$orderId}\n";
    }
}

echo "Using LegacyLogTrait — PHP emits NO warning:\n";
$service = new OldOrderService();
$service->process('ORD-001');
echo "\n  A developer reading the code might miss the @deprecated comment.\n";
echo "  PHP itself does not enforce it — no warning, no error, no notice.\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 2 — PHP 8.5: #[Deprecated] on a trait
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 2: PHP 8.5 — #[Deprecated] on a trait ───────\n\n";

// The replacement trait — what everyone should migrate to
trait LoggableTrait {
    private array $logEntries = [];

    public function log(string $level, string $message, array $context = []): void {
        $this->logEntries[] = compact('level', 'message', 'context');
        echo "  [{$level}] {$message}\n";
    }

    public function getLogs(): array { return $this->logEntries; }
}

// The old trait — now formally deprecated with PHP 8.5
#[\Deprecated(
    message: 'Use LoggableTrait instead. This trait will be removed in v3.0.',
    since: '2.5.0'
)]
trait OldLoggerTrait {
    public function writeLog(string $message): void {
        echo "  [OLD-LOG] {$message}\n";
    }
}

// ✅ Migration complete — using the new trait
class NewOrderService {
    use LoggableTrait;

    public function process(string $orderId): void {
        $this->log('INFO', "Processing order {$orderId}");
        echo "  [ORDER] Processed: {$orderId}\n";
    }
}

echo "NewOrderService using LoggableTrait (current, no deprecation):\n";
$newService = new NewOrderService();
$newService->process('ORD-002');
echo "\n";

// ⚠️  Using the deprecated trait — PHP 8.5 emits a deprecation notice
// (Demonstrated with error_reporting set to show deprecations)
echo "Class using OldLoggerTrait (deprecated in PHP 8.5):\n";
echo "  PHP 8.5 emits: Deprecated: Use of deprecated trait OldLoggerTrait\n";
echo "  since 2.5.0 — Use LoggableTrait instead. This trait will be removed in v3.0.\n\n";

// To actually trigger the notice, you would write:
// class AnotherService { use OldLoggerTrait; } // triggers deprecation notice
// We demonstrate the effect without triggering it in this example.


// ─────────────────────────────────────────────────────────────────────────────
// PART 3 — PHP 8.5: #[Deprecated] on a class constant
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 3: PHP 8.5 — #[Deprecated] on constants ─────\n\n";

class PaymentStatus {
    // ❌ Old string constants — being replaced by the PaymentStatus enum
    #[\Deprecated(
        message: 'Use the PaymentStatus enum cases instead: PaymentStatus::Pending',
        since: '2.0.0'
    )]
    const STATUS_PENDING = 'pending';

    #[\Deprecated(
        message: 'Use the PaymentStatus enum cases instead: PaymentStatus::Completed',
        since: '2.0.0'
    )]
    const STATUS_COMPLETED = 'completed';

    #[\Deprecated(
        message: 'Use the PaymentStatus enum cases instead: PaymentStatus::Failed',
        since: '2.0.0'
    )]
    const STATUS_FAILED = 'failed';

    // ✅ Current recommendation — enum cases
    const VALID_STATUSES = ['pending', 'completed', 'failed', 'refunded'];
}

// The recommended replacement — enum
enum PaymentStatusEnum: string {
    case Pending   = 'pending';
    case Completed = 'completed';
    case Failed    = 'failed';
    case Refunded  = 'refunded';
}

echo "Using deprecated constants — PHP 8.5 emits a deprecation notice:\n\n";
echo "  // Reading PaymentStatus::STATUS_PENDING triggers:\n";
echo "  // Deprecated: Constant PaymentStatus::STATUS_PENDING is deprecated since 2.0.0.\n";
echo "  // Use the PaymentStatus enum cases instead: PaymentStatus::Pending\n\n";

// Correct usage — enum cases (no deprecation)
$status = PaymentStatusEnum::Pending;
echo "Using the enum (current, no deprecation):\n";
echo "  PaymentStatusEnum::Pending->value = {$status->value}\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 4 — Interface constants can also be deprecated
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 4: #[Deprecated] on interface constants ──────\n\n";

interface HttpCodes {
    // Deprecated — prefer using the HttpStatus enum
    #[\Deprecated('Use HttpStatus::Ok->value instead', since: '3.0.0')]
    const OK = 200;

    #[\Deprecated('Use HttpStatus::NotFound->value instead', since: '3.0.0')]
    const NOT_FOUND = 404;

    // Still current
    const SERVER_ERROR = 500;
}

enum HttpStatus: int {
    case Ok        = 200;
    case NotFound  = 404;
    case ServerError = 500;
}

echo "Interface constant deprecation:\n";
echo "  HttpCodes::OK      → deprecated, PHP 8.5 warns\n";
echo "  HttpCodes::NOT_FOUND → deprecated, PHP 8.5 warns\n";
echo "  HttpCodes::SERVER_ERROR → still current\n\n";
echo "Recommended migration:\n";
echo "  HttpStatus::Ok->value       = " . HttpStatus::Ok->value . "\n";
echo "  HttpStatus::NotFound->value = " . HttpStatus::NotFound->value . "\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 5 — #[Deprecated] attribute anatomy
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 5: Attribute anatomy ────────────────────────\n\n";

echo "#[\\Deprecated] accepts two optional named arguments:\n\n";
echo "  #[\\Deprecated(\n";
echo "      message: 'What to use instead and why',\n";
echo "      since:   'Version when it was deprecated (e.g. 2.5.0)'\n";
echo "  )]\n\n";

echo "Both arguments are optional:\n";
echo "  #[\\Deprecated]                         // Minimal — just marks as deprecated\n";
echo "  #[\\Deprecated('Use X instead')]        // With message\n";
echo "  #[\\Deprecated(since: '2.0.0')]         // With version only\n";
echo "  #[\\Deprecated('Use X', since: '2.0')] // Full\n\n";

echo "The deprecation notice PHP emits contains:\n";
echo "  - The deprecated element (trait name / constant name)\n";
echo "  - The 'since' version (if provided)\n";
echo "  - The 'message' (if provided)\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 6 — When to use #[Deprecated] on traits and constants
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 6: When to use it ───────────────────────────\n\n";

echo "Use #[Deprecated] on a TRAIT when:\n";
echo "  ✓ You are replacing one trait with a better-named one\n";
echo "  ✓ You are replacing a trait with an interface + trait combination\n";
echo "  ✓ You are replacing a trait with full constructor injection\n";
echo "  ✓ The trait is part of a library/package — other teams consume it\n\n";

echo "Use #[Deprecated] on a CONSTANT when:\n";
echo "  ✓ You are replacing string/int constants with a backed enum\n";
echo "  ✓ A constant was renamed (keep old name, deprecate it, alias to new)\n";
echo "  ✓ A constant's value changed meaning and it should be removed\n\n";

echo "Do NOT use #[Deprecated] as a todo comment:\n";
echo "  ✗ #[Deprecated] on a constant you plan to change but haven't yet\n";
echo "  ✗ #[Deprecated] on internal code with no external consumers\n";
echo "     → Just delete it or refactor it directly\n";

echo "\n--- Recap ---\n";
echo "#[\\Deprecated] on a trait:    deprecation notice when the trait is used in a class.\n";
echo "#[\\Deprecated] on a constant: deprecation notice when the constant is read.\n";
echo "Arguments: message (what to use instead), since (version string).\n";
echo "Enforced by PHP 8.5 — not just a doc comment convention.\n";
echo "Migration path: mark old → introduce new → remove old in next major version.\n";