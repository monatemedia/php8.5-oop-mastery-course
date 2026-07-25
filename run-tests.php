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
echo "Running " . count($testFiles) . " test file(s), one process each.\n";
echo str_repeat('-', 70) . "\n";

$passedFiles = 0;
$failedFiles = [];

foreach ($testFiles as $path) {
    $label = str_replace(str_replace('\\', '/', $root) . '/', '', $path);
    printf("%-62s ", substr($label, 0, 62));

    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($phpunit)
         . ' --no-configuration --colors=never --do-not-cache-result '
         . escapeshellarg($path) . ' 2>&1';

    $out = [];
    $status = 0;
    exec($cmd, $out, $status);

    if ($status === 0) {
        echo "PASS\n";
        $passedFiles++;
    } else {
        echo "FAIL\n";
        $failedFiles[$label] = implode("\n", $out);
    }
}

echo str_repeat('-', 70) . "\n";
printf("%d passed, %d failed, %d total.\n", $passedFiles, count($failedFiles), count($testFiles));

if ($failedFiles !== []) {
    foreach ($failedFiles as $label => $output) {
        echo "\n" . str_repeat('=', 70) . "\n{$label}\n" . str_repeat('=', 70) . "\n";
        echo $output . "\n";
    }
    exit(1);
}

echo "\nAll green.\n";
exit(0);
