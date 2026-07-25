<?php
declare(strict_types=1);

/**
 * verify.php — Pre-flight check for the PHP 8.5 OOP Mastery Course
 * ==================================================================
 *
 * Run this ONCE before starting Module 1, and again any time something
 * behaves unexpectedly:
 *
 *     php verify.php
 *
 * What it checks:
 *   1. Your PHP version is 8.5 or newer
 *   2. The PHP 8.4 / 8.5 features the course relies on actually work here
 *   3. Every .php file in the course parses (php -l on all of them)
 *   4. Composer dependencies are installed (needed from Module 4 onwards)
 *
 * It never modifies anything. Exit code 0 = all green, 1 = something to fix.
 */

$root = __DIR__;
$failures = 0;
$warnings = 0;

function heading(string $text): void {
    echo "\n" . str_repeat('=', 66) . "\n  {$text}\n" . str_repeat('=', 66) . "\n\n";
}
function pass(string $m): void { echo "  [ OK ]   {$m}\n"; }
function warn(string $m): void { global $warnings; $warnings++; echo "  [ WARN ] {$m}\n"; }
function fail(string $m): void { global $failures; $failures++; echo "  [ FAIL ] {$m}\n"; }


// ─────────────────────────────────────────────────────────────────────────────
heading('1. PHP version');

echo "  Detected: PHP " . PHP_VERSION . "  (" . PHP_BINARY . ")\n\n";

if (PHP_VERSION_ID >= 80500) {
    pass('PHP 8.5+ — every lesson in this course will run.');
} elseif (PHP_VERSION_ID >= 80400) {
    fail('PHP 8.4 detected. Modules 1 and 2 use PHP 8.5-only syntax that will not parse:');
    echo "           clone(\$obj, [...]), #[\\NoDiscard], #[\\Override] on properties,\n";
    echo "           #[\\Deprecated] on traits, static asymmetric visibility.\n";
    echo "           Fix: run  herd use 8.5  (or lerd init) and re-run this script.\n";
} else {
    fail('PHP ' . PHP_VERSION . ' is far too old. This course needs PHP 8.5.');
    echo "           Property hooks alone need 8.4. Install PHP 8.5 via Laravel Herd.\n";
}


// ─────────────────────────────────────────────────────────────────────────────
heading('2. Language features used by the course');

/**
 * Each probe is a snippet that must parse AND run on a correct install.
 * Every probe must echo PROBE_OK as its last act — checking for that sentinel
 * is far more reliable than trusting an exit code, and it means a probe that
 * throws reports its actual error instead of a useless "failed to run".
 */
$probes = [
    // NOTE: never end a probe with a top-level `return` — that halts the script
    // before the PROBE_OK sentinel is echoed. Assert by throwing instead.
    'Property hooks (8.4)' =>
        'class P84 { public string $v = "" { set(string $x) => trim($x); } }
         $o = new P84(); $o->v = "  a  ";
         if ($o->v !== "a") { throw new RuntimeException("set hook did not trim"); }',

    'Asymmetric visibility, instance (8.4)' =>
        'class A84 { public private(set) string $s = "x"; }
         if ((new A84())->s !== "x") { throw new RuntimeException("aviz read failed"); }',

    'Asymmetric visibility, static (8.5)' =>
        'class A85 { public static private(set) string $s = "x"; }
         if (A85::$s !== "x") { throw new RuntimeException("static aviz read failed"); }',

    'clone with — clone($obj, [...]) (8.5)' =>
        // NOTE: a readonly property may only be replaced by clone-with from
        // INSIDE the declaring class. Doing it from the outside throws
        // "Cannot modify readonly property" — which is exactly why the wither
        // lives on the class here, mirroring how Lesson 1.2 uses it.
        'final class C85 { public function __construct(public readonly int $n) {}
             public function withN(int $n): static { return clone($this, ["n" => $n]); } }
         $a = new C85(1); $b = $a->withN(2);
         if ($a->n !== 1 || $b->n !== 2) { throw new RuntimeException("clone-with returned wrong values"); }',

    '#[\\NoDiscard] attribute (8.5)' =>
        '#[\\NoDiscard("use it")] function nd85(): int { return 1; }
         if (nd85() !== 1) { throw new RuntimeException("NoDiscard function misbehaved"); }',

    '#[\\Deprecated] on a trait (8.5)' =>
        '#[\\Deprecated("gone in v3")] trait T85 { public function t(): int { return 1; } }
         if (!trait_exists("T85")) { throw new RuntimeException("deprecated trait not declared"); }',

    'Enums (8.1)' =>
        'enum E81: string { case A = "a"; }
         if (E81::from("a") !== E81::A) { throw new RuntimeException("enum from() failed"); }',

    'Readonly classes (8.2)' =>
        'readonly class R82 { public function __construct(public int $n) {} }
         if ((new R82(5))->n !== 5) { throw new RuntimeException("readonly class failed"); }',
];

$probeDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'php85course_probe';
@mkdir($probeDir, 0777, true);

foreach ($probes as $label => $code) {
    $file = $probeDir . DIRECTORY_SEPARATOR . 'probe_' . md5($label) . '.php';
    file_put_contents(
        $file,
        "<?php\ndeclare(strict_types=1);\n" . $code . "\necho 'PROBE_OK';\n"
    );

    $out = [];
    $status = 0;
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1', $out, $status);
    $text = trim(implode("\n", $out));

    if ($status === 0 && str_contains($text, 'PROBE_OK')) {
        pass($label);
    } else {
        // Show the real reason, first meaningful line only.
        $reason = 'no output and exit code ' . $status;
        foreach (explode("\n", $text) as $line) {
            $line = trim($line);
            if ($line !== '' && $line !== 'PROBE_OK') {
                $reason = $line;
                break;
            }
        }
        fail($label . "\n           " . $reason);
    }
    @unlink($file);
}
@rmdir($probeDir);


// ─────────────────────────────────────────────────────────────────────────────
heading('3. Syntax check — every .php file in the course');

$skipDirs = ['/vendor/', '/.git/', '/node_modules/', '/lesson-5.3-tdd/example/'];

$files = [];
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($it as $f) {
    /** @var SplFileInfo $f */
    if ($f->getExtension() !== 'php') {
        continue;
    }
    $path = str_replace('\\', '/', $f->getPathname());
    foreach ($skipDirs as $skip) {
        if (str_contains($path, $skip)) {
            continue 2;
        }
    }
    if ($f->getFilename() === 'verify.php') {
        continue;
    }
    $files[] = $f->getPathname();
}
sort($files);

$broken = [];
foreach ($files as $file) {
    $out = [];
    $status = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $out, $status);
    if ($status !== 0) {
        $broken[$file] = trim(implode("\n           ", $out));
    }
}

$total = count($files);
$okCount = $total - count($broken);
echo "  Checked {$total} files.\n\n";

if ($broken === []) {
    pass("All {$total} files parse cleanly.");
} else {
    foreach ($broken as $file => $msg) {
        fail(str_replace($root . DIRECTORY_SEPARATOR, '', $file));
        echo "           {$msg}\n";
    }
    echo "\n  {$okCount}/{$total} files parse. See the failures above.\n";
    if (PHP_VERSION_ID < 80500) {
        echo "  Most of these are almost certainly just your PHP version — fix that first.\n";
    }
}


// ─────────────────────────────────────────────────────────────────────────────
heading('4. Composer dependencies (needed from Module 4 onwards)');

$autoload = $root . '/vendor/autoload.php';
if (!is_file($autoload)) {
    warn('vendor/ not found. Modules 1-3 will still work.');
    echo "           Run  composer install  before starting Module 4.\n";
} else {
    pass('vendor/autoload.php present.');
    require_once $autoload;

    foreach ([
        'PHP-DI'  => \DI\ContainerBuilder::class,
        'Slim'    => \Slim\Factory\AppFactory::class,
        'Slim PSR-7' => \Slim\Psr7\Factory\ServerRequestFactory::class,
        'PHPUnit' => \PHPUnit\Framework\TestCase::class,
    ] as $name => $class) {
        class_exists($class) ? pass("{$name} installed.") : fail("{$name} missing — run: composer install");
    }
}


// ─────────────────────────────────────────────────────────────────────────────
heading('Summary');

if ($failures === 0 && $warnings === 0) {
    echo "  All green. Open README.md and start with Module 1, Lesson 1.0.\n\n";
    exit(0);
}

if ($failures === 0) {
    echo "  {$warnings} warning(s), 0 failures.\n";
    echo "  You can start Module 1 now; resolve the warnings before Module 4.\n\n";
    exit(0);
}

echo "  {$failures} failure(s), {$warnings} warning(s).\n";
echo "  Fix the failures above before starting the course.\n\n";
exit(1);
