<?php
declare(strict_types=1);

/**
 * probe-claims.php — Execute the factual claims the quiz keys make
 * =================================================================
 *
 *     php probe-claims.php
 *
 * MAINTENANCE TOOL — not part of the course. Students never run this.
 *
 * `audit-quizzes.php` checks the questions whose answer is a program's output.
 * This checks the other kind: a multiple-choice or true/false key that asserts
 * something about how PHP behaves — "PHP throws a fatal error", "the message
 * reads X", "this is allowed since 8.2". Those are just as checkable, they are
 * simply not attached to a runnable program in the quiz itself.
 *
 * Each probe runs in its own process, because most of them are fatal errors and
 * a fatal ends the process that meets it.
 *
 * A probe states what the key claims and what PHP actually said. Where the two
 * differ the key is wrong — unless the difference is only in wording, in which
 * case the key is quoting the engine inaccurately, which for a message a student
 * will meet in their own terminal is still worth correcting.
 */

$probes = [

    // ── Lesson 1.1 Q4 / Lesson 1.2 Q3 ───────────────────────────────────────
    'missing interface method' => [
        'claim' => 'Fatal: "... must therefore be declared abstract or implement the remaining method (Countable::count)"',
        'code'  => '<?php
            interface Countable2 { public function count(): int; }
            class Broken implements Countable2 {}
        ',
    ],
    'missing TWO interface methods (is the noun pluralised?)' => [
        'claim' => 'the count and the noun should agree — "2 abstract methods ... the remaining methods"',
        'code'  => '<?php
            interface Pair { public function a(): void; public function b(): void; }
            class BrokenPair implements Pair {}
        ',
    ],

    // ── Lesson 1.1 Q12 ──────────────────────────────────────────────────────
    'abstract keyword on an interface method' => [
        'claim' => 'rejected: "Access type for interface method ... must be omitted"',
        'code'  => '<?php interface I { abstract public function f(): void; }',
    ],
    'non-public visibility on an interface method' => [
        'claim' => 'rejected, same message',
        'code'  => '<?php interface I2 { protected function f(): void; }',
    ],

    // ── Lesson 1.2 Q17 ──────────────────────────────────────────────────────
    'abstract private method in an abstract class' => [
        'claim' => 'Fatal: "Abstract function Validator::validate() cannot be declared private"',
        'code'  => '<?php abstract class Validator { abstract private function validate(): bool; }',
    ],

    // ── Lesson 1.3 Q8 ───────────────────────────────────────────────────────
    'trait property redeclared with a DIFFERENT default' => [
        'claim' => 'fatal error',
        'code'  => '<?php
            trait T { private string $status = "draft"; }
            class C { use T; private string $status = "active"; }
            echo "no error\n";
        ',
    ],
    'trait property redeclared with the SAME default' => [
        'claim' => 'allowed (Lesson 1.3 Q20 depends on this)',
        'code'  => '<?php
            trait T2 { private int $count = 0; }
            class C2 { use T2; private int $count = 0; }
            echo "allowed\n";
        ',
    ],

    // ── Lesson 1.3 Q10 ──────────────────────────────────────────────────────
    'class_uses() and inherited traits' => [
        'claim' => 'the key suggests in_array(T::class, class_uses($obj)) as the instanceof substitute',
        'code'  => '<?php
            trait Marker {}
            class ParentC { use Marker; }
            class ChildC extends ParentC {}
            $o = new ChildC();
            var_dump(in_array(Marker::class, class_uses($o), true));
            var_dump(in_array(Marker::class, class_uses(new ParentC()), true));
        ',
    ],

    // ── Lesson 1.3 Q14 ──────────────────────────────────────────────────────
    'constants in traits' => [
        'claim' => 'allowed since PHP 8.2',
        'code'  => '<?php trait T3 { const X = 1; } class C3 { use T3; } echo C3::X, "\n";',
    ],

    // ── Lesson 2.1 Q4 ───────────────────────────────────────────────────────
    'return null; from a void function' => [
        'claim' => 'the question calls this a TypeError',
        'code'  => '<?php function f(): void { return null; } f();',
    ],

    // ── Lesson 2.1 Q12 ──────────────────────────────────────────────────────
    'override a MIXED return type with void' => [
        'claim' => 'key says mixed is "precisely equivalent" to no type declaration',
        'code'  => '<?php
            class A { public function f(): mixed { return 1; } }
            class B extends A { public function f(): void {} }
            echo "allowed
";
        ',
    ],
    'override an UNTYPED return with void' => [
        'claim' => 'if this differs from the probe above, the two are not equivalent',
        'code'  => '<?php
            class A2 { public function f() { return 1; } }
            class B2 extends A2 { public function f(): void {} }
            echo "allowed
";
        ',
    ],

    // ── Lesson 2.2 Q16 ──────────────────────────────────────────────────────
    'default value on a virtual hooked property' => [
        'claim' => 'Fatal: "Cannot specify default value for virtual hooked property Circle::$area"',
        'code'  => '<?php
            class Circle {
                public float $radius = 0.0;
                public float $area = 0.0 { get => M_PI * $this->radius ** 2; }
            }
        ',
    ],

    // ── Lesson 2.2 Q6 / Q14 ─────────────────────────────────────────────────
    'set hook parameter narrower than the property type' => [
        'claim' => 'Fatal: "Type of parameter $value of hook ...::set must be compatible with property type"',
        'code'  => '<?php
            class P {
                public ?DateTimeImmutable $at = null {
                    set(string|DateTimeImmutable $value) { $this->at = null; }
                }
            }
        ',
    ],

    // ── Lesson 2.3 Q2 ───────────────────────────────────────────────────────
    'reading ->value on a PURE enum case' => [
        'claim' => 'fatal Error',
        'code'  => '<?php enum Colour { case Red; } echo Colour::Red->value;',
    ],

    // ── Lesson 2.4 Q8 ───────────────────────────────────────────────────────
    'serialize() an anonymous class instance' => [
        'claim' => 'key says it serialises but cannot be DEserialised elsewhere',
        'code'  => '<?php
            $o = new class { public int $n = 1; };
            echo serialize($o), "
";
        ',
    ],

    // ── Lesson 4.3 Q11 ──────────────────────────────────────────────────────
    'infinite recursion — crash, or a catchable fatal?' => [
        'claim' => 'key says "crashing PHP with a stack overflow"',
        'code'  => '<?php
            // Catch it so we see WHAT PHP raised rather than a 4,000-frame trace.
            function boom(int $n): int { return boom($n + 1); }
            try { boom(0); } catch (\\Throwable $e) {
                echo get_class($e), ": ", $e->getMessage(), "\\n";
            }
        ',
    ],

    // ── Lesson 4.4 Q8 ───────────────────────────────────────────────────────
    // The key says create() auto-wires and merely adds overrides. PHP-DI's own
    // documentation says the opposite, so ask the library rather than the docs.
    'PHP-DI autowire() with an interface-typed constructor param' => [
        'claim' => 'resolves the dependency automatically',
        'code'  => '<?php
            require "' . str_replace('\\', '/', __DIR__) . '/vendor/autoload.php";
            interface Dep { public function v(): string; }
            class RealDep implements Dep { public function v(): string { return "real"; } }
            class Svc { public function __construct(public Dep $d) {} }
            $b = new DI\ContainerBuilder();
            $b->addDefinitions([
                Dep::class => DI\autowire(RealDep::class),
                Svc::class => DI\autowire(Svc::class),
            ]);
            echo "autowire(): ", $b->build()->get(Svc::class)->d->v(), "\n";
        ',
    ],
    'PHP-DI create() with the same interface-typed param' => [
        'claim' => 'if this fails, create() does NOT auto-wire and the key is backwards',
        'code'  => '<?php
            require "' . str_replace('\\', '/', __DIR__) . '/vendor/autoload.php";
            interface Dep2 { public function v(): string; }
            class RealDep2 implements Dep2 { public function v(): string { return "real"; } }
            class Svc2 { public function __construct(public Dep2 $d) {} }
            $b = new DI\ContainerBuilder();
            $b->addDefinitions([
                Dep2::class => DI\autowire(RealDep2::class),
                Svc2::class => DI\create(Svc2::class),
            ]);
            echo "create(): ", $b->build()->get(Svc2::class)->d->v(), "\n";
        ',
    ],

    // ── Lesson 5.1 Q7 ───────────────────────────────────────────────────────
    // The course's phpunit.xml does not set beStrictAboutTestsThatDoNotTestAnything,
    // so whatever PHPUnit defaults to is what a student will actually see.
    'a test with no assertions — risky, or a pass?' => [
        'claim' => 'key says PHPUnit marks it Risky by default',
        'code'  => '<?php
            $root = "' . str_replace('\\', '/', __DIR__) . '";
            $t = sys_get_temp_dir() . "/NoAssertionTest.php";
            file_put_contents($t, <<<\'PHPCODE\'
            <?php
            use PHPUnit\Framework\TestCase;
            final class NoAssertionTest extends TestCase {
                public function testDoesNothingAtAll(): void { $x = 1 + 1; }
            }
            PHPCODE);
            $out = shell_exec(escapeshellarg(PHP_BINARY) . " "
                 . escapeshellarg($root . "/vendor/phpunit/phpunit/phpunit")
                 . " --no-configuration --do-not-cache-result " . escapeshellarg($t) . " 2>&1");
            @unlink($t);
            foreach (explode("\n", (string) $out) as $l) {
                if (preg_match("/risky|Risky|OK|assertions/i", $l)) { echo trim($l), "\n"; }
            }
        ',
    ],

    // ── Lesson 5.4 Q11 ──────────────────────────────────────────────────────
    // The key says addErrorMiddleware(false,false,false) "tells Slim NOT to
    // catch exceptions". Adding the middleware is what makes Slim catch them,
    // and the three booleans are displayErrorDetails / logErrors /
    // logErrorDetails. Ask Slim, because six files depend on the answer.
    'Slim: route throws, WITHOUT error middleware' => [
        'claim' => 'the exception should escape handle() and reach PHPUnit',
        'code'  => '<?php
            require "' . str_replace('\\', '/', __DIR__) . '/vendor/autoload.php";
            $app = Slim\Factory\AppFactory::create();
            $app->get("/boom", function () { throw new RuntimeException("kaboom"); });
            $req = (new Slim\Psr7\Factory\ServerRequestFactory())
                 ->createServerRequest("GET", "/boom");
            try {
                $r = $app->handle($req);
                echo "handle() returned status ", $r->getStatusCode(), " — exception was SWALLOWED\n";
            } catch (Throwable $e) {
                echo "exception ESCAPED: ", get_class($e), ": ", $e->getMessage(), "\n";
            }
        ',
    ],
    'Slim: route throws, WITH addErrorMiddleware(false,false,false)' => [
        'claim' => 'key says this is what lets the exception through',
        'code'  => '<?php
            require "' . str_replace('\\', '/', __DIR__) . '/vendor/autoload.php";
            $app = Slim\Factory\AppFactory::create();
            $app->get("/boom", function () { throw new RuntimeException("kaboom"); });
            $app->addErrorMiddleware(false, false, false);
            $req = (new Slim\Psr7\Factory\ServerRequestFactory())
                 ->createServerRequest("GET", "/boom");
            try {
                $r = $app->handle($req);
                echo "handle() returned status ", $r->getStatusCode(), " — exception was SWALLOWED\n";
            } catch (Throwable $e) {
                echo "exception ESCAPED: ", get_class($e), ": ", $e->getMessage(), "\n";
            }
        ',
    ],

    // ── Lesson 6.2 and 6.5: does factory() give a NEW instance per get()? ────
    // 6.5 Q2 says yes (transient). 6.5 Q8 says no (called once, cached). Both
    // are in the same answer key, and Lesson 6.2 is built entirely on the first
    // claim. PHP-DI's own docs say scopes were removed in v6 and every entry is
    // shared. This is the probe that decides whether a whole lesson is wrong.
    'PHP-DI: two get() calls on a factory() binding' => [
        'claim' => '6.2 and 6.5 Q2 say transient (different objects); 6.5 Q8 says singleton (same)',
        'code'  => '<?php
            require "' . str_replace('\\', '/', __DIR__) . '/vendor/autoload.php";
            class Cart { public array $items = []; }
            $b = new DI\ContainerBuilder();
            $b->addDefinitions([ Cart::class => DI\factory(fn() => new Cart()) ]);
            $c = $b->build();
            $a1 = $c->get(Cart::class);
            $a2 = $c->get(Cart::class);
            echo "get() twice: ", ($a1 === $a2 ? "SAME object (singleton)" : "DIFFERENT objects (transient)"), "\n";
            $a1->items[] = "widget";
            echo "items visible through the second reference: ", count($a2->items), "\n";
        ',
    ],
    'PHP-DI: make() instead of get() on the same binding' => [
        'claim' => 'PHP-DI docs say make() is how you get a fresh instance',
        'code'  => '<?php
            require "' . str_replace('\\', '/', __DIR__) . '/vendor/autoload.php";
            class Cart2 { public array $items = []; }
            $b = new DI\ContainerBuilder();
            $b->addDefinitions([ Cart2::class => DI\factory(fn() => new Cart2()) ]);
            $c = $b->build();
            echo "make() twice: ",
                 ($c->make(Cart2::class) === $c->make(Cart2::class)
                    ? "SAME object" : "DIFFERENT objects"), "\n";
            echo "get() vs make(): ",
                 ($c->get(Cart2::class) === $c->make(Cart2::class)
                    ? "SAME object" : "DIFFERENT objects"), "\n";
        ',
    ],
    'PHP-DI: how many times is a factory() callable actually invoked?' => [
        'claim' => '6.5 Q8 says once for five resolutions',
        'code'  => '<?php
            require "' . str_replace('\\', '/', __DIR__) . '/vendor/autoload.php";
            class Logger3 {}
            $calls = 0;
            $b = new DI\ContainerBuilder();
            $b->addDefinitions([
                Logger3::class => DI\factory(function () use (&$calls) { $calls++; return new Logger3(); }),
            ]);
            $c = $b->build();
            for ($i = 0; $i < 5; $i++) { $c->get(Logger3::class); }
            echo "five get() calls invoked the factory {$calls} time(s)\n";
            for ($i = 0; $i < 5; $i++) { $c->make(Logger3::class); }
            echo "five more make() calls brought the total to {$calls}\n";
        ',
    ],
];

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'php85course_probes';
@mkdir($tmp, 0777, true);

echo str_repeat('=', 72), "\n  QUIZ CLAIM PROBES — PHP ", PHP_VERSION, "\n", str_repeat('=', 72), "\n";

foreach ($probes as $name => $probe) {
    $file = $tmp . DIRECTORY_SEPARATOR . 'p_' . md5($name) . '.php';
    file_put_contents($file, $probe['code']);

    // Redirect to a file and read only a bounded prefix. exec() buffers the
    // whole of a child's output in this process, and a probe that deliberately
    // recurses forever emits a stack trace with thousands of frames — enough to
    // exhaust the parent and kill the run before the later probes execute. A
    // tool that investigates crashes has to survive the crashes it causes.
    $outFile = $file . '.out';
    shell_exec(
        escapeshellarg(PHP_BINARY)
        . ' -d ' . escapeshellarg('memory_limit=64M')
        . ' ' . escapeshellarg($file)
        . ' > ' . escapeshellarg($outFile) . ' 2>&1'
    );

    $raw = '';
    if (is_file($outFile)) {
        $fh = fopen($outFile, 'rb');
        if ($fh !== false) {
            $raw = (string) fread($fh, 4096);
            fclose($fh);
        }
    }
    $out = explode("\n", $raw);
    @unlink($file);
    @unlink($outFile);

    // Keep the first meaningful line; the duplicate "PHP Fatal error" / "Fatal
    // error" pair and the stack trace add nothing here.
    $lines = [];
    foreach ($out as $l) {
        $l = trim($l);
        if ($l === '' || str_starts_with($l, 'Stack trace') || str_starts_with($l, '#')) {
            continue;
        }
        $l = preg_replace('/ in \/.*|  in [A-Z]:\\\\.*/', '', $l) ?? $l;
        if (!in_array($l, $lines, true)) {
            $lines[] = $l;
        }
    }

    echo "\n", str_repeat('-', 72), "\n  ", $name, "\n", str_repeat('-', 72), "\n";
    echo "  KEY CLAIMS : ", $probe['claim'], "\n";
    echo "  PHP SAYS   : ", ($lines === [] ? '(no output)' : implode("\n               ", $lines)), "\n";
}

@rmdir($tmp);
echo "\n", str_repeat('=', 72), "\n";
echo "  Read these against the keys by hand — a difference in wording still\n";
echo "  matters when the key presents the message as the engine's own.\n";
echo str_repeat('=', 72), "\n";
