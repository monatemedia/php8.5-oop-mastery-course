<?php
declare(strict_types=1);

/**
 * run-tests.php — Test runner for Modules 5 and 6
 * =================================================
 *
 *     php run-tests.php                 # every test file in Modules 5 and 6
 *     php run-tests.php 5.2             # just Lesson 5.2
 *     php run-tests.php module-6        # just Module 6
 *     php run-tests.php --starter       # include the starter/ files too
 *
 * WHY THIS EXISTS INSTEAD OF PLAIN `vendor/bin/phpunit`
 * ------------------------------------------------------
 * The course deliberately reuses familiar class names across lessons —
 * `OrderService`, `ShoppingCart`, `SpyLogger`, `SimpleContainer` and others
 * each appear in several lessons, because each lesson is meant to be read
 * standalone. None of them are namespaced.
 *
 * That is fine when you run one file, but PHP cannot load two classes with
 * the same name in one process, so a single `phpunit` run over the whole
 * course dies with "Cannot declare class X, name already in use".
 *
 * This runner sidesteps that by invoking PHPUnit once per file, in its own
 * process. Slower, but it always works and needs no refactoring of the
 * lesson material.
 *
 * `challenge/starter/` files are skipped by default — those are the
 * deliberately-incomplete versions you are meant to fill in yourself, so
 * they are supposed to fail until you have done the work.
 */

$root = __DIR__;

$phpunit = $root . '/vendor/bin/phpunit';
if (!is_file($phpunit)) {
    fwrite(STDERR, "PHPUnit not found.\nRun 'composer install' from the course root first.\n");
    exit(1);
}

$argsIn        = array_slice($argv, 1);
$includeStarter = in_array('--starter', $argsIn, true);
$filters       = array_values(array_filter($argsIn, fn(string $a): bool => !str_starts_with($a, '--')));

// ── Collect candidate test files ────────────────────────────────────────────
$testFiles = [];
foreach (['module-5-testing-and-tdd', 'module-6-object-lifecycle-and-state'] as $module) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/' . $module, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $f) {
        /** @var SplFileInfo $f */
        if ($f->getExtension() !== 'php') {
            continue;
        }
        $path = str_replace('\\', '/', $f->getPathname());

        // The retired singular-'example' folder holds only redirect stubs.
        if (str_contains($path, '/lesson-5.3-tdd/example/')) {
            continue;
        }
        if (!$includeStarter && str_contains($path, '/challenge/starter/')) {
            continue;
        }

        // Only files that actually define a PHPUnit test case.
        $src = (string) file_get_contents($f->getPathname());
        if (!str_contains($src, 'extends TestCase')) {
            continue;
        }

        $testFiles[] = $path;
    }
}
sort($testFiles);

// ── Apply the optional filter ───────────────────────────────────────────────
if ($filters !== []) {
    $testFiles = array_values(array_filter(
        $testFiles,
        function (string $path) use ($filters): bool {
            foreach ($filters as $needle) {
                if (stripos($path, $needle) !== false) {
                    return true;
                }
            }
            return false;
        }
    ));
}

if ($testFiles === []) {
    fwrite(STDERR, "No test files matched" . ($filters ? ' ' . implode(', ', $filters) : '') . ".\n");
    exit(1);
}

// ── Run each file in its own PHPUnit process ────────────────────────────────
$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'php85course_phpunit';
@mkdir($tmpDir, 0777, true);

echo "Running " . count($testFiles) . " test file(s), one process each.\n";
echo str_repeat('-', 70) . "\n";

$passedFiles  = 0;
$failedFiles  = [];
$skippedFiles = [];
$tempCopies   = [];

// If this run dies partway through, do not leave stray <Class>.php files
// sitting in the lesson folders.
register_shutdown_function(static function () use (&$tempCopies): void {
    foreach ($tempCopies as $stray) {
        @unlink($stray);
    }
});

foreach ($testFiles as $path) {
    $label = str_replace(str_replace('\\', '/', $root) . '/', '', $path);
    printf("%-70s ", strlen($label) > 70 ? '...' . substr($label, -67) : $label);

    // PHPUnit 11 resolves a test class from the FILE NAME — even through a
    // <file> element. That is fine for MoneyTest.php, but 01-first-test.php
    // holds CalculatorTest, so PHPUnit hunts for a class called
    // "01-first-test" and reports "No tests executed!".
    //
    // Work around it without renaming any lesson file: for every concrete
    // TestCase subclass in the file, emit a shim NAMED AFTER THE CLASS that
    // requires the real file, then point PHPUnit at the shims.
    //
    // Class detection uses the tokenizer rather than a regex — the escaping
    // needed to match "\PHPUnit\Framework\TestCase" inside a PHP string
    // literal is a reliable way to end up with a pattern that silently
    // matches nothing.
    $classes = [];
    $tokens  = token_get_all((string) file_get_contents($path));
    $count   = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_CLASS) {
            continue;
        }

        // Abstract base classes cannot be run on their own.
        $isAbstract = false;
        for ($b = $i - 1; $b >= 0; $b--) {
            if (!is_array($tokens[$b])) {
                break;
            }
            if ($tokens[$b][0] === T_ABSTRACT) {
                $isAbstract = true;
                break;
            }
            if (!in_array($tokens[$b][0], [T_WHITESPACE, T_FINAL, T_READONLY, T_COMMENT, T_DOC_COMMENT], true)) {
                break;
            }
        }

        // Class name.
        $n = $i + 1;
        while ($n < $count && is_array($tokens[$n]) && $tokens[$n][0] === T_WHITESPACE) {
            $n++;
        }
        if ($n >= $count || !is_array($tokens[$n]) || $tokens[$n][0] !== T_STRING) {
            continue; // anonymous class
        }
        $name = $tokens[$n][1];

        // Parent, if any: everything between `extends` and `implements`/`{`.
        $parent  = '';
        $seenExt = false;
        for ($k = $n + 1; $k < $count; $k++) {
            $tok = $tokens[$k];
            if (!is_array($tok)) {
                if ($tok === '{') {
                    break;
                }
                continue;
            }
            if ($tok[0] === T_EXTENDS) {
                $seenExt = true;
                continue;
            }
            if ($tok[0] === T_IMPLEMENTS) {
                break;
            }
            if ($seenExt && in_array($tok[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR], true)) {
                $parent .= $tok[1];
            }
        }

        if (!$isAbstract && str_ends_with($parent, 'TestCase')) {
            $classes[] = $name;
        }
    }

    if ($classes === []) {
        echo "SKIP (no concrete TestCase class)\n";
        $skippedFiles[] = $label;
        continue;
    }

    // PHPUnit wants the test class PHYSICALLY DECLARED in the file it is given.
    // A shim that require()s the real file does not satisfy that — PHPUnit
    // reports "does not extend PHPUnit\Framework\TestCase" even when it plainly
    // does. So: if the filename already matches the class, run the file as-is;
    // otherwise drop a temporary COPY next to it named after the class, run
    // that, and delete it. Copying as a sibling (rather than into a temp dir)
    // keeps __DIR__ intact, which Lesson 5.4 relies on to find ../app.php.
    $basename = pathinfo($path, PATHINFO_FILENAME);
    $targets  = [];

    foreach ($classes as $class) {
        if ($class === $basename) {
            $targets[$class] = [$path, false];
            continue;
        }
        $sibling = dirname($path) . DIRECTORY_SEPARATOR . $class . '.php';
        if (file_exists($sibling)) {
            // Never clobber a real course file.
            $targets[$class] = [$sibling, false];
            continue;
        }
        if (!@copy($path, $sibling)) {
            echo "FAIL (could not stage {$class})\n";
            $failedFiles[$label] = "Could not create temporary copy at {$sibling}";
            continue 2;
        }
        $tempCopies[]    = $sibling;
        $targets[$class] = [$sibling, true];
    }

    $out         = [];
    $status      = 0;
    $combined    = '';
    $anyFailure  = false;

    foreach ($targets as $class => [$runFile, $isTemp]) {
        $cfg = $tmpDir . DIRECTORY_SEPARATOR . 'phpunit_' . md5($runFile) . '.xml';
        file_put_contents($cfg, sprintf(
            '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<phpunit bootstrap="%s" colors="false" cacheResult="false"' . "\n"
            . '         beStrictAboutOutputDuringTests="false"' . "\n"
            . '         failOnWarning="false" failOnNotice="false" failOnDeprecation="false">' . "\n"
            . '  <testsuites><testsuite name="single"><file>%s</file></testsuite></testsuites>' . "\n"
            . '</phpunit>' . "\n",
            htmlspecialchars($root . '/vendor/autoload.php', ENT_XML1),
            htmlspecialchars($runFile, ENT_XML1)
        ));

        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($phpunit)
             . ' --configuration ' . escapeshellarg($cfg)
             . ' --colors=never --do-not-cache-result 2>&1';

        $one = [];
        $rc  = 0;
        exec($cmd, $one, $rc);
        @unlink($cfg);
        if ($isTemp) {
            @unlink($runFile);
            $tempCopies = array_values(array_diff($tempCopies, [$runFile]));
        }

        $text = implode("\n", $one);
        // "No tests executed" is a runner problem, not a pass.
        if ($rc !== 0 || str_contains($text, 'No tests executed')) {
            $anyFailure = true;
        }
        $combined .= ($combined === '' ? '' : "\n") . "--- {$class} ---\n" . $text;
    }

    $status = $anyFailure ? 1 : 0;
    $out    = explode("\n", $combined);

    if ($status === 0) {
        echo "PASS\n";
        $passedFiles++;
    } else {
        echo "FAIL\n";
        $failedFiles[$label] = implode("\n", $out);
    }
}

echo str_repeat('-', 70) . "\n";
printf(
    "%d passed, %d failed, %d skipped, %d total.\n",
    $passedFiles,
    count($failedFiles),
    count($skippedFiles),
    count($testFiles)
);

if ($skippedFiles !== []) {
    echo "\nSkipped (no concrete TestCase class found — check these are really not tests):\n";
    foreach ($skippedFiles as $label) {
        echo "  - {$label}\n";
    }
}

if ($failedFiles !== []) {
    foreach ($failedFiles as $label => $output) {
        echo "\n" . str_repeat('=', 70) . "\n{$label}\n" . str_repeat('=', 70) . "\n";
        echo $output . "\n";
    }
    exit(1);
}

// Nothing failing is only good news if something actually ran.
if ($passedFiles === 0) {
    echo "\nNO TESTS EXECUTED — that is a runner failure, not a pass.\n";
    exit(1);
}

echo "\nAll green.\n";
exit(0);
