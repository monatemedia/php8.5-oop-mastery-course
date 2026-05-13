<?php
declare(strict_types=1);

/**
 * Example 02 — Multiple Traits and Conflict Resolution
 * ------------------------------------------------------
 * When two traits define a method with the same name, PHP cannot resolve
 * it automatically — you must tell it what to do.
 *
 * Two resolution tools:
 *   insteadof  — choose which trait's version wins the method name
 *   as         — keep both versions under different names (aliases)
 *   as         — can also change a method's visibility
 *
 * Scenario: An application service class that uses a Logger trait and a
 * Debugger trait — both happen to define a method called format().
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Multiple Traits and Conflict Resolution            ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// STEP 1 — What a conflict looks like (commented out — uncomment to test)
// ─────────────────────────────────────────────────────────────────────────────

echo "── The conflict (demonstrated safely) ──────────────\n\n";

trait LoggerTrait {
    public function format(string $message): string {
        return "[LOG " . date('H:i:s') . "] {$message}";
    }

    public function info(string $message): void {
        echo $this->format($message) . "\n";
    }
}

trait DebuggerTrait {
    public function format(string $message): string {   // ← Same name as LoggerTrait!
        return "[DEBUG] {$message} | memory=" . round(memory_get_usage() / 1024) . "KB";
    }

    public function dump(mixed $value): void {
        echo $this->format(print_r($value, true)) . "\n";
    }
}

/*
// ❌ Uncomment to see the fatal error:
class BrokenService {
    use LoggerTrait, DebuggerTrait;
    // Fatal error: Trait method DebuggerTrait::format has not been applied as BrokenService::format,
    // because of collision with LoggerTrait::format
}
*/

echo "Without resolution, using two traits with the same method name\n";
echo "causes: Fatal error: Trait method conflict\n";
echo "(See commented-out BrokenService above)\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// STEP 2 — Resolution with insteadof (pick one, discard the other)
// ─────────────────────────────────────────────────────────────────────────────

echo "── insteadof: pick one, discard the other ───────────\n\n";

class LoggingService {
    use LoggerTrait, DebuggerTrait {
        LoggerTrait::format insteadof DebuggerTrait; // Logger's format() wins
        // DebuggerTrait::format is now completely discarded
    }

    public function process(string $task): void {
        $this->info("Processing: {$task}");
        // dump() from DebuggerTrait still works — only format() conflicted
        $this->dump(['task' => $task, 'status' => 'running']);
    }
}

$svc = new LoggingService();
$svc->process('send-emails');
echo "\nNote: dump() uses DebuggerTrait's format() because... wait — it can't!\n";
echo "format() was resolved to LoggerTrait's version globally in this class.\n";
echo "dump() will use [LOG H:i:s] format — DebuggerTrait's format is gone.\n";


// ─────────────────────────────────────────────────────────────────────────────
// STEP 3 — Resolution with as (keep both under different names)
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── as: keep both, different names ───────────────────\n\n";

class FullDiagnosticService {
    use LoggerTrait, DebuggerTrait {
        LoggerTrait::format   insteadof DebuggerTrait; // LoggerTrait wins 'format'
        DebuggerTrait::format as debugFormat;           // DebuggerTrait kept as alias
    }

    public function run(string $task): void {
        // Uses LoggerTrait::format (via info())
        $this->info("Task started: {$task}");

        // Uses DebuggerTrait::format (via the alias)
        echo $this->debugFormat("Task payload: {$task}") . "\n";

        // dump() internally calls $this->format() which is LoggerTrait's version
        $this->dump(['task' => $task]);
    }
}

$full = new FullDiagnosticService();
$full->run('generate-report');


// ─────────────────────────────────────────────────────────────────────────────
// STEP 4 — as for visibility changes (no rename, just change access level)
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── as: visibility change ────────────────────────────\n\n";

trait HtmlHelpers {
    public function escapeHtml(string $input): string {
        return htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function buildTag(string $tag, string $content, array $attrs = []): string {
        $attrStr = '';
        foreach ($attrs as $k => $v) {
            $attrStr .= " {$k}=\"" . $this->escapeHtml($v) . "\"";
        }
        return "<{$tag}{$attrStr}>{$this->escapeHtml($content)}</{$tag}>";
    }
}

class TemplateEngine {
    use HtmlHelpers {
        buildTag    as public;          // Keep public (no change here)
        escapeHtml  as protected;       // Restrict to protected — internal use only
    }

    public function renderAlert(string $message): string {
        // escapeHtml() is called here (protected — inside the class, fine)
        return $this->buildTag('div', $message, ['class' => 'alert']);
    }
}

$tpl = new TemplateEngine();
echo $tpl->renderAlert('<script>alert("xss")</script>') . "\n";
echo $tpl->buildTag('p', 'Hello & welcome', ['id' => 'intro']) . "\n";

// $tpl->escapeHtml('test'); // ← Would fail: escapeHtml is now protected


// ─────────────────────────────────────────────────────────────────────────────
// STEP 5 — Conflict resolution across three traits
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Three-trait conflict resolution ─────────────────\n\n";

trait FileLogger {
    public function log(string $message): void {
        echo "[FILE]    {$message}\n";
    }
    public function getName(): string { return 'FileLogger'; }
}

trait DatabaseLogger {
    public function log(string $message): void {
        echo "[DATABASE] {$message}\n";
    }
    public function getName(): string { return 'DatabaseLogger'; }
}

trait CloudLogger {
    public function log(string $message): void {
        echo "[CLOUD]   {$message}\n";
    }
    public function getName(): string { return 'CloudLogger'; }
}

class MultiLogger {
    use FileLogger, DatabaseLogger, CloudLogger {
        // 'log' conflict: File wins; the other two get aliases
        FileLogger::log     insteadof DatabaseLogger, CloudLogger;
        DatabaseLogger::log as dbLog;
        CloudLogger::log    as cloudLog;

        // 'getName' conflict: File wins; others discarded
        FileLogger::getName insteadof DatabaseLogger, CloudLogger;
    }

    public function logAll(string $message): void {
        $this->log($message);       // FileLogger::log
        $this->dbLog($message);     // DatabaseLogger::log
        $this->cloudLog($message);  // CloudLogger::log
    }
}

$multi = new MultiLogger();
$multi->logAll("User registered");
echo "Primary logger: " . $multi->getName() . "\n";

echo "\n--- Recap ---\n";
echo "insteadof: TraitA::method insteadof TraitB — TraitA wins, TraitB's version discarded.\n";
echo "as alias:  TraitB::method as newName — both versions kept under different names.\n";
echo "as visibility: TraitA::method as protected — changes access level in this class.\n";
echo "You MUST resolve any conflict before the class will load.\n";