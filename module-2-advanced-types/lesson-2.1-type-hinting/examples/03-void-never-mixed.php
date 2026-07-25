<?php
declare(strict_types=1);

/**
 * Example 03 — void, never, and mixed
 * -------------------------------------
 * Three special return types that describe what a function does with control flow.
 *
 *   void    — function completes but returns nothing meaningful
 *   never   — function does NOT return (always throws or exits)
 *   mixed   — function may return anything (last resort — avoid when possible)
 *
 * Understanding `never` is particularly important because static analysers
 * (PHPStan, Psalm) use it to detect unreachable code after calls.
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  void, never, and mixed Return Types               ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 1 — void
// ─────────────────────────────────────────────────────────────────────────────

echo "── Part 1: void ─────────────────────────────────────\n\n";

class EventDispatcher {
    private array $listeners = [];

    // void: registers a listener, returns nothing
    public function on(string $event, callable $listener): void {
        $this->listeners[$event][] = $listener;
        echo "[DISPATCHER] Registered listener for '{$event}'\n";
        // return;        ← allowed: explicit empty return
        // return null;   ← TypeError in strict mode
        // return true;   ← TypeError always
    }

    // void: fires all listeners, returns nothing
    public function emit(string $event, mixed $payload = null): void {
        if (!isset($this->listeners[$event])) {
            echo "[DISPATCHER] No listeners for '{$event}'\n";
            return; // Early return — no value
        }
        foreach ($this->listeners[$event] as $listener) {
            $listener($payload);
        }
    }
}

$dispatcher = new EventDispatcher();

$dispatcher->on('user.created', function (mixed $user): void {
    echo "[HANDLER] New user: {$user['email']}\n";
});

$dispatcher->on('user.created', function (mixed $user): void {
    echo "[MAILER] Sending welcome email to {$user['email']}\n";
});

$dispatcher->emit('user.created', ['email' => 'carol@example.com']);
$dispatcher->emit('order.placed'); // No listeners

// void return type error demonstration
function returnsVoid(): void {
    echo "This function returns void.\n";
    // A bare `return;` is fine. `return $something;` is not — see below.
}

returnsVoid();

// IMPORTANT: returning a value from a void function is NOT a catchable TypeError.
// PHP rejects it at COMPILE time, before any of this file executes:
//
//     $fn = function(): void { return 42; };
//     Fatal error: A void function must not return a value
//
// That is why the line below is commented out — uncommenting it does not throw
// an exception you can catch, it stops the whole file from compiling. Try it.
//
// try {
//     $fn = function(): void { return 42; };
//     $fn();
// } catch (\TypeError $e) {          // ← never reached; catch cannot help here
//     echo "void TypeError: " . $e->getMessage() . "\n";
// }

echo "void: `return;` is allowed, `return \$value;` is a compile-time error.\n";


// ─────────────────────────────────────────────────────────────────────────────
// PART 2 — never (PHP 8.1+)
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 2: never ────────────────────────────────────\n\n";

// `never` means this function ALWAYS throws or exits — it never returns normally.
// Static analysers use this to mark code after such calls as unreachable.

class HttpException extends \RuntimeException {
    public function __construct(
        public readonly int $statusCode,
        string $message
    ) {
        parent::__construct($message);
    }
}

/**
 * Never returns — always throws.
 * The return type `never` tells PHP and static analysers:
 * "Nothing after a call to abort() will ever execute."
 */
function abort(int $code, string $message): never {
    throw new HttpException($code, $message);
}

function notFound(string $resource): never {
    abort(404, "{$resource} was not found.");
}

function unauthorised(): never {
    abort(401, "Authentication required.");
}

// Demonstrate: code AFTER a never function is genuinely unreachable
function findOrFail(int $id): array {
    $records = [1 => ['id' => 1, 'name' => 'Alice']];

    if (!isset($records[$id])) {
        notFound("Record #{$id}"); // never returns — throws HttpException
        // The next line is UNREACHABLE — a static analyser will flag it
        // echo "This never runs.\n";
    }

    return $records[$id]; // Only reached if record exists
}

try {
    $record = findOrFail(1);
    echo "Found: " . $record['name'] . "\n";
} catch (HttpException $e) {
    echo "HTTP {$e->statusCode}: " . $e->getMessage() . "\n";
}

try {
    $record = findOrFail(99);
    echo "Found: " . $record['name'] . "\n"; // Never reached
} catch (HttpException $e) {
    echo "HTTP {$e->statusCode}: " . $e->getMessage() . "\n";
}

// never vs void — the key distinction
echo "\n── void vs never: the distinction ───────────────────\n\n";
echo "void:  'I complete normally and return nothing useful'\n";
echo "never: 'I do NOT complete — I always throw or exit'\n\n";

echo "A void function:\n";
echo "  function log(string \$msg): void { echo \$msg; }\n";
echo "  \$x = log('hi'); // \$x is null\n\n";

echo "A never function:\n";
echo "  function fail(): never { throw new Exception(); }\n";
echo "  \$x = fail(); // Unreachable — exception was thrown\n";


// ─────────────────────────────────────────────────────────────────────────────
// never in an abstract class (enforcing contract shape)
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── never in class hierarchies ───────────────────────\n\n";

abstract class Command {
    abstract public function execute(): void;

    // Every command can bail out — subclasses call this when something goes wrong
    protected function fail(string $reason): never {
        throw new \RuntimeException("[{$this->name()}] Failed: {$reason}");
    }

    abstract protected function name(): string;
}

class CreateUserCommand extends Command {
    public function __construct(private string $email) {}

    protected function name(): string { return 'CreateUser'; }

    public function execute(): void {
        if (empty($this->email)) {
            $this->fail("Email cannot be empty."); // never — throws
            // Unreachable:
        }
        echo "[CMD] Created user: {$this->email}\n";
    }
}

$cmd = new CreateUserCommand('dave@example.com');
$cmd->execute();

$bad = new CreateUserCommand('');
try {
    $bad->execute();
} catch (\RuntimeException $e) {
    echo "Caught: " . $e->getMessage() . "\n";
}


// ─────────────────────────────────────────────────────────────────────────────
// PART 3 — mixed
// ─────────────────────────────────────────────────────────────────────────────

echo "\n── Part 3: mixed ────────────────────────────────────\n\n";

echo "mixed accepts and returns ANY type.\n";
echo "It is equivalent to no type declaration at all.\n";
echo "Use it ONLY when you genuinely cannot be more specific.\n\n";

// Legitimate use: a generic container
class Container {
    private array $bindings = [];

    public function bind(string $key, mixed $value): void {
        $this->bindings[$key] = $value;
    }

    // Returns whatever was stored — mixed is honest here
    public function get(string $key): mixed {
        return $this->bindings[$key] ?? null;
    }
}

$container = new Container();
$container->bind('config', ['debug' => true, 'version' => '8.4']);
$container->bind('counter', 42);
$container->bind('name', 'PHP Mastery');

echo "config: "  . json_encode($container->get('config'))  . "\n";
echo "counter: " . $container->get('counter')               . "\n";
echo "name: "    . $container->get('name')                  . "\n";
echo "missing: " . var_export($container->get('missing'), true) . "\n";

echo "\n── When NOT to use mixed ─────────────────────────────\n\n";
echo "// Bad — use a proper type:\n";
echo "function getName(): mixed { return \$this->name; } // name is always string\n\n";
echo "// Bad — use a union type:\n";
echo "function parse(): mixed { return ... }  // Can be int|float — be specific\n\n";
echo "// OK — genuinely could be anything:\n";
echo "function get(string \$key): mixed { return \$this->store[\$key]; }\n";

echo "\n--- Recap ---\n";
echo "void:  function returns nothing — not even null explicitly.\n";
echo "never: function does not return — always throws or exits.\n";
echo "mixed: function accepts/returns anything — last resort, be specific when possible.\n";
echo "Key: void and never are very different — void completes, never does not.\n";