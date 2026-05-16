<?php
declare(strict_types=1);

/**
 * Example 02 — The `new` Keyword Smell
 * ----------------------------------------
 * Every call to `new ConcreteClass()` inside a class body is a coupling point.
 * This example dissects exactly WHY it is a problem and what each `new` takes
 * away from the class — testability, flexibility, and replaceability.
 *
 * This is not about `new` in general — value objects, DTOs, and simple data
 * structures are fine to construct anywhere. The smell is specifically:
 *   new Service/Repository/Gateway/Logger inside another service/class
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  The `new` Keyword Smell Inside Constructors        ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 1 — Anatomy of what `new` takes away
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 1: What each `new` takes away ───────────────\n\n";

// Simulated infrastructure classes (the things we don't want hardwired)
class MySQLDatabase {
    public function __construct(
        private string $host,
        private string $db,
        private string $user,
        private string $pass
    ) {
        echo "  [MYSQL] Connecting to {$host}/{$db}...\n";
    }

    public function query(string $sql): array {
        echo "  [MYSQL] Running: {$sql}\n";
        return [['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']];
    }
}

class SmtpMailer {
    public function __construct(
        private string $host,
        private int    $port
    ) {
        echo "  [SMTP] Connecting to {$host}:{$port}...\n";
    }

    public function send(string $to, string $subject): void {
        echo "  [SMTP] Sending '{$subject}' to {$to}\n";
    }
}

class FileLogger {
    public function __construct(private string $path) {
        echo "  [FILE-LOG] Opening {$path}...\n";
    }

    public function log(string $message): void {
        echo "  [FILE-LOG] {$message}\n";
    }
}


// ────────────────────────────────────────────────────────────────
// THE PROBLEMATIC CLASS — three `new` calls in the constructor
// ────────────────────────────────────────────────────────────────

class TightlyCoupledUserService {
    private MySQLDatabase $db;
    private SmtpMailer    $mailer;
    private FileLogger    $logger;

    public function __construct() {
        // ❌ PROBLEM 1: This class DECIDES which concrete classes to use.
        //    Business requirement: switch from MySQL to Postgres?
        //    → Must edit THIS constructor.

        // ❌ PROBLEM 2: This class KNOWS the constructor signatures of its deps.
        //    If SmtpMailer adds a new required parameter?
        //    → Must edit THIS constructor.

        // ❌ PROBLEM 3: Instantiation happens at construction time, unconditionally.
        //    Writing a test? The DB, SMTP, and file system are ALL invoked.
        //    → Cannot test business logic without real infrastructure.

        $this->db     = new MySQLDatabase('localhost', 'app_db', 'root', '');
        $this->mailer = new SmtpMailer('smtp.example.com', 587);
        $this->logger = new FileLogger('/var/log/users.log');
    }

    public function register(string $email, string $password): bool {
        $this->logger->log("Registering: {$email}");
        $existing = $this->db->query("SELECT id FROM users WHERE email='{$email}'");
        if (!empty($existing)) {
            $this->logger->log("Email already exists: {$email}");
            return false;
        }
        $this->db->query("INSERT INTO users (email, password) VALUES ('{$email}', ...)");
        $this->mailer->send($email, 'Welcome!');
        $this->logger->log("Registered: {$email}");
        return true;
    }
}

echo "Instantiating TightlyCoupledUserService:\n";
$service = new TightlyCoupledUserService();
// ↑ Three infrastructure connections happen BEFORE any business logic runs.

echo "\nCalling register():\n";
$service->register('bob@example.com', 'secret');


// ─────────────────────────────────────────────────────────────────────────────
// PART 2 — The three things `new` forces your class to know
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 2: The three forced responsibilities ─────────\n\n";

echo "1. WHICH concrete class to use:\n";
echo "   new MySQLDatabase(...) — not PostgreSQL, not SQLite, not a fake\n";
echo "   Decision made HERE, not at the composition root.\n\n";

echo "2. HOW to construct the dependency:\n";
echo "   new MySQLDatabase('localhost', 'app_db', 'root', '')\n";
echo "   UserService now must know MySQLDatabase's constructor signature.\n";
echo "   If MySQLDatabase changes its constructor → UserService breaks.\n\n";

echo "3. WHEN to create it:\n";
echo "   At UserService construction time — always, unconditionally.\n";
echo "   Even in tests that only need to test password validation.\n";
echo "   Even in CLI scripts that never send email.\n\n";

echo "SRP violation: UserService has 4 responsibilities:\n";
echo "   1. Register users (its actual job)\n";
echo "   2. Create a database connection\n";
echo "   3. Create an SMTP connection\n";
echo "   4. Open a log file\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 3 — The false comfort: `new` in a method body (equally bad)
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 3: `new` in method bodies is equally bad ────\n\n";

class AlsoTightlyCoupled {
    public function generateReport(int $month): string {
        // ❌ Still tightly coupled — just deferred to method time
        $db        = new MySQLDatabase('localhost', 'reporting', 'root', '');
        $formatter = new class {
            public function format(array $data): string {
                return json_encode($data);
            }
        };

        $rows   = $db->query("SELECT * FROM sales WHERE month={$month}");
        return $formatter->format($rows);
    }
}

echo "Creating AlsoTightlyCoupled:\n";
$obj = new AlsoTightlyCoupled(); // No DB yet — false comfort

echo "\nCalling generateReport():\n";
$obj->generateReport(1); // DB created here — still untestable in isolation


// ─────────────────────────────────────────────────────────────────────────────
// PART 4 — When `new` IS acceptable
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 4: When `new` IS acceptable ─────────────────\n\n";

// ✅ OK: Value objects — they have no external dependencies
class Money {
    public function __construct(
        private int    $amountCents,
        private string $currency
    ) {}

    public function format(): string {
        return $this->currency . ' ' . number_format($this->amountCents / 100, 2);
    }
}

class OrderTotal {
    public function calculate(array $lines): Money {
        $total = 0;
        foreach ($lines as $line) {
            $total += $line['price'] * $line['quantity'];
        }
        // ✅ new Money() here is fine — Money is a value object, no infrastructure
        return new Money($total, 'ZAR');
    }
}

$calculator = new OrderTotal();
$result = $calculator->calculate([
    ['price' => 29999, 'quantity' => 2],
    ['price' => 5000,  'quantity' => 1],
]);
echo "Order total: " . $result->format() . "\n\n";

// ✅ OK: DTOs / simple data structures
class UserRegistrationDto {
    public function __construct(
        public readonly string $email,
        public readonly string $password
    ) {}
}

// ✅ OK: Exceptions
function validateEmail(string $email): void {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new \InvalidArgumentException("Invalid email: {$email}"); // Fine
    }
}

echo "Rule: `new` is fine for value objects, DTOs, and exceptions.\n";
echo "Smell: `new` on services, repositories, gateways, loggers — any class with external deps.\n";

echo "\n── The diagnostic question ──────────────────────────\n\n";
echo "Ask yourself: 'Does this `new` reach outside the current process?'\n";
echo "  new Money(...)         → No. Only math. ✅ Fine.\n";
echo "  new MySQLDatabase(...) → Yes. Network, disk, external process. ❌ Inject it.\n";
echo "  new SmtpMailer(...)    → Yes. Network. ❌ Inject it.\n";
echo "  new FileLogger(...)    → Yes. Disk. ❌ Inject it.\n";
echo "  new \InvalidArgumentException() → No. Just an object. ✅ Fine.\n";

echo "\n--- Recap ---\n";
echo "`new ConcreteService()` inside a class takes three responsibilities away:\n";
echo "  which class to use, how to construct it, and when to create it.\n";
echo "These belong at the COMPOSITION ROOT — not inside business classes.\n";
echo "`new` is fine for value objects, DTOs, and exceptions — not for services.\n";