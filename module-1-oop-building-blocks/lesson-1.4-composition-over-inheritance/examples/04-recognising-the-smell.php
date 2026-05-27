<?php
declare(strict_types=1);

/**
 * Example 04 — Recognising the Smell
 * -------------------------------------
 * When is `extends` correct, and when is it a design smell?
 * This example walks through five scenarios — two where inheritance
 * is correct, three where it is the wrong tool.
 *
 * For each smell, the fix is shown.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Recognising the Composition Smell                  ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// THE PRACTICAL TEST — apply before every `extends`
// ─────────────────────────────────────────────────────────────────────────────

echo "── The practical test ───────────────────────────────\n\n";
echo "Before writing 'class X extends Y', ask:\n\n";
echo "  1. Can I replace 'extends Y' with 'private Y \$y; __construct(Y \$y)'?\n";
echo "     If YES → compose, don't inherit.\n\n";
echo "  2. Does a genuine 'is-a' relationship exist?\n";
echo "     AdminUser IS a User — substitutable everywhere User is expected?\n";
echo "     If YES → inheritance may be correct (check LSP holds).\n\n";
echo "  3. Is the chain 3+ levels deep?\n";
echo "     If YES → almost always a design smell.\n\n";


// ═══════════════════════════════════════════════════════════
// ✅ CORRECT — Scenario 1: Template Method Pattern
// The abstract class was designed FOR extension
// ═══════════════════════════════════════════════════════════

echo "── ✅ Correct use 1: Template Method Pattern ─────────\n\n";

/**
 * ReportGenerator defines the pipeline. Subclasses fill in the steps.
 * This IS a correct use of inheritance — the class is explicitly designed
 * for extension. Subclasses are different implementations of the same process.
 */
abstract class ReportGenerator {
    // The pipeline — defined once, used by all subclasses
    final public function render(): string {
        $rows    = $this->fetchRows();
        $headers = $this->headers();
        $output  = implode(' | ', $headers) . "\n" . str_repeat('-', 40) . "\n";
        foreach ($rows as $row) {
            $output .= implode(' | ', $row) . "\n";
        }
        return $output;
    }

    abstract protected function headers(): array;
    abstract protected function fetchRows(): array;
}

class SalesReport extends ReportGenerator {
    protected function headers(): array   { return ['Product', 'Units', 'Revenue']; }
    protected function fetchRows(): array {
        return [['Widget A', '100', 'R2999'], ['Widget B', '50', 'R1499']];
    }
}

class UserReport extends ReportGenerator {
    protected function headers(): array   { return ['Name', 'Email', 'Role']; }
    protected function fetchRows(): array {
        return [['Alice', 'alice@example.com', 'admin']];
    }
}

echo (new SalesReport())->render();
echo "\n" . (new UserReport())->render();

echo "\nWhy inheritance is correct here:\n";
echo "  ✓ The parent class was designed FOR extension (abstract + template method)\n";
echo "  ✓ SalesReport IS a ReportGenerator — substitutable anywhere one is expected\n";
echo "  ✓ Chain is only 2 levels deep\n\n";


// ═══════════════════════════════════════════════════════════
// ✅ CORRECT — Scenario 2: PHPUnit TestCase extension
// Framework extension point — the framework designed it for extends
// ═══════════════════════════════════════════════════════════

echo "── ✅ Correct use 2: Framework extension points ──────\n\n";

echo "class UserServiceTest extends \\PHPUnit\\Framework\\TestCase { ... }\n";
echo "class UserFactory   extends \\Illuminate\\Database\\Eloquent\\Factory { ... }\n";
echo "class OrderMigration extends \\Illuminate\\Database\\Migrations\\Migration { ... }\n\n";
echo "Why correct: framework authors designed these for extends.\n";
echo "  TestCase provides assert*() methods, setUp/tearDown lifecycle.\n";
echo "  These are NOT domain classes — they are framework integration points.\n\n";


// ═══════════════════════════════════════════════════════════
// ❌ SMELL — Scenario 3: Extending for code reuse
// "I want save() from BaseModel" — use a trait or inject a repository
// ═══════════════════════════════════════════════════════════

echo "── ❌ Smell 1: Extending for code reuse ─────────────\n\n";

echo "BEFORE (smell):\n";
echo "  class BaseModel {\n";
echo "      public function save(): bool { /* DB persist */ }\n";
echo "      public function delete(): bool { /* DB delete */ }\n";
echo "  }\n";
echo "  class UserModel extends BaseModel { ... }  // just wants save() and delete()\n";
echo "  class PostModel extends BaseModel { ... }  // same\n\n";

echo "Problems:\n";
echo "  ✗ UserModel IS a BaseModel? No — it USES save/delete.\n";
echo "  ✗ BaseModel constructor might require DB credentials.\n";
echo "  ✗ Cannot test UserModel without satisfying BaseModel's infrastructure.\n\n";

// Fix 1: trait for horizontal code reuse
trait PersistableTrait {
    private bool $persisted = false;

    public function save(): bool {
        $this->persisted = true;
        echo "  [TRAIT] {$this->getEntityName()} saved\n";
        return true;
    }

    public function delete(): bool {
        $this->persisted = false;
        echo "  [TRAIT] {$this->getEntityName()} deleted\n";
        return true;
    }

    abstract protected function getEntityName(): string;
}

class UserModel {
    use PersistableTrait;
    public function __construct(private string $email) {}
    protected function getEntityName(): string { return "User({$this->email})"; }
}

class PostModel {
    use PersistableTrait;
    public function __construct(private string $title) {}
    protected function getEntityName(): string { return "Post({$this->title})"; }
}

echo "AFTER (trait for reuse — no inheritance):\n";
$user = new UserModel('alice@example.com');
$user->save();
$post = new PostModel('Hello World');
$post->save();
echo "  ✓ No parent class. No constructor chain. Testable in isolation.\n\n";


// ═══════════════════════════════════════════════════════════
// ❌ SMELL — Scenario 4: Extending for a type group
// "I want UserModel and PostModel to be the same type"
// Fix: interface, not abstract base class
// ═══════════════════════════════════════════════════════════

echo "── ❌ Smell 2: Extending for a type group ───────────\n\n";

echo "BEFORE (smell):\n";
echo "  abstract class Model {\n";
echo "      abstract public function getTable(): string;\n";
echo "  }\n";
echo "  class UserModel extends Model { ... }\n";
echo "  class PostModel extends Model { ... }\n";
echo "  // Only reason: function process(Model \$m) accepts both\n\n";

// Fix: interface provides the type grouping without inheritance
interface ModelInterface {
    public function getTable(): string;
    public function toArray(): array;
}

class UserModelV2 implements ModelInterface {
    public function __construct(
        private string $email,
        private string $name
    ) {}

    public function getTable(): string { return 'users'; }
    public function toArray(): array   { return ['email' => $this->email, 'name' => $this->name]; }
}

class PostModelV2 implements ModelInterface {
    public function __construct(private string $title) {}
    public function getTable(): string { return 'posts'; }
    public function toArray(): array   { return ['title' => $this->title]; }
}

function persistModel(ModelInterface $model): void {
    echo "  [DB] INSERT INTO {$model->getTable()}: " . json_encode($model->toArray()) . "\n";
}

echo "AFTER (interface for type grouping — no abstract base class):\n";
persistModel(new UserModelV2('alice@example.com', 'Alice'));
persistModel(new PostModelV2('Hello World'));
echo "  ✓ Same type safety. Zero inheritance. No shared constructor baggage.\n\n";


// ═══════════════════════════════════════════════════════════
// ❌ SMELL — Scenario 5: Extending a concrete class to change one method
// Fix: implement the interface instead, or use the decorator pattern
// ═══════════════════════════════════════════════════════════

echo "── ❌ Smell 3: Extending concrete to override one method\n\n";

echo "BEFORE (smell):\n";
echo "  class SmtpMailer {\n";
echo "      public function send(string \$to, string \$subject): bool { /* SMTP */ }\n";
echo "  }\n";
echo "  class LoggingMailer extends SmtpMailer {\n";
echo "      public function send(string \$to, string \$subject): bool {\n";
echo "          echo \"[LOG] Sending to \$to\";\n";
echo "          return parent::send(\$to, \$subject);\n";
echo "      }\n";
echo "  }\n\n";
echo "Problems:\n";
echo "  ✗ LoggingMailer IS an SmtpMailer? No — it wraps any mailer.\n";
echo "  ✗ Cannot use LoggingMailer with a different mailer (e.g. MailgunMailer).\n";
echo "  ✗ Tight coupling to SmtpMailer's implementation.\n\n";

// Fix: decorator pattern (Pattern 4 from Example 03)
interface MailerInterface2 {
    public function send(string $to, string $subject): bool;
}

class SmtpMailerV2 implements MailerInterface2 {
    public function send(string $to, string $subject): bool {
        echo "  [SMTP] To: {$to} | {$subject}\n";
        return true;
    }
}

class LoggingMailer implements MailerInterface2 {
    public function __construct(
        private MailerInterface2 $inner, // wraps ANY mailer
        private string           $prefix = '[LOG]'
    ) {}

    public function send(string $to, string $subject): bool {
        echo "  {$this->prefix} Sending to {$to}: {$subject}\n";
        return $this->inner->send($to, $subject);
    }
}

echo "AFTER (decorator — wraps any MailerInterface2):\n";
$mailer = new LoggingMailer(new SmtpMailerV2());
$mailer->send('alice@example.com', 'Welcome!');
echo "  ✓ LoggingMailer works with ANY MailerInterface2 implementation.\n";
echo "  ✓ No inheritance. SmtpMailerV2 and LoggingMailer are peers.\n\n";

echo "── Summary table ────────────────────────────────────\n\n";
echo "  Scenario                    | Use extends? | Fix if no\n";
echo "  ─────────────────────────── | ──────────── | ─────────────────────\n";
echo "  Template Method Pattern     | ✅ YES       | N/A\n";
echo "  Framework extension point   | ✅ YES       | N/A\n";
echo "  Reuse: want save() method   | ❌ NO        | Trait\n";
echo "  Grouping: same type needed  | ❌ NO        | Interface\n";
echo "  Override one method         | ❌ NO        | Decorator\n\n";

echo "--- Recap ---\n";
echo "Inheritance is correct: Template Method, genuine is-a, framework points.\n";
echo "Smell 1 (code reuse):   extract to trait or inject a collaborator.\n";
echo "Smell 2 (type group):   create an interface — no shared implementation needed.\n";
echo "Smell 3 (one override): use the decorator pattern — wraps any implementation.\n";