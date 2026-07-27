<?php
declare(strict_types=1);

/**
 * check.php — Where am I, and does everything still work?
 * =========================================================
 *
 *     php check.php                 full run: environment, integrity, progress
 *     php check.php --no-install    never run composer install automatically
 *     php check.php --all           do not stop at the first unsolved challenge
 *     php check.php --skip-verify   skip the whole-course syntax sweep (faster)
 *     php check.php --dump          write every reference solution's real output
 *                                   to solution-output/ for comparison
 *
 * This is the script to run. It does three things in order, and stops at the
 * first thing that is genuinely wrong:
 *
 *   1. ENVIRONMENT — is PHP 8.5 active, is Composer installed, are the
 *      dependencies present? Missing dependencies are installed for you.
 *
 *   2. INTEGRITY — hands off to verify.php, which syntax-checks every file in
 *      the course and probes each PHP 8.4/8.5 feature the lessons rely on.
 *      This answers "is the COURSE intact", nothing about you.
 *
 *   3. PROGRESS — walks all 30 lessons in course order and works out how far
 *      you have got. It stops at the first one that is not yet solved and tells
 *      you exactly where you are, rather than dumping 30 failures on you.
 *
 * HOW A CHALLENGE IS JUDGED
 * --------------------------
 * Modules 1-4 are scripts: your challenge/starter.php is run, and its output is
 * checked against the "Expected Output" block in CHALLENGE.md. Every expected
 * line must appear SOMEWHERE in your output; order is not required and extra
 * output is fine, so a debug echo will not fail you. Lines containing "...",
 * "XXXXX" or "[timestamp]" are placeholders and match anything.
 *
 * Modules 5-6 are PHPUnit files: your challenge/starter/*Test.php is executed
 * by run-tests.php. It passes when your tests pass.
 *
 * Lessons 1.0 and 5.0 have no challenge at all. They carry a single attestation
 * checkbox at the foot of their README, which you tick yourself. That is the
 * only claim about them a script can check.
 *
 * If your output does not match, the checker runs the REFERENCE SOLUTION against
 * the same expected block before blaming you. If the solution fails too, that is
 * a course bug: you are told so, it is not counted, and it does not block you.
 */

$root = __DIR__;
$args = array_slice($argv, 1);

$noInstall  = in_array('--no-install', $args, true);
$showAll    = in_array('--all', $args, true);
$skipVerify = in_array('--skip-verify', $args, true);
$dump       = in_array('--dump', $args, true);

$php = escapeshellarg(PHP_BINARY);

// ─────────────────────────────────────────────────────────────────────────────
// Presentation helpers
// ─────────────────────────────────────────────────────────────────────────────

const MARK_DONE    = '[ done ]';
const MARK_HERE    = '[ HERE ]';
const MARK_LOCKED  = '[      ]';
const MARK_OK      = '[  ok  ]';
const MARK_FAIL    = '[ FAIL ]';
const MARK_WARN    = '[ warn ]';

function heading(string $text): void
{
    echo "\n" . str_repeat('=', 72) . "\n  {$text}\n" . str_repeat('=', 72) . "\n\n";
}

function line(string $mark, string $text): void
{
    echo "  {$mark}  {$text}\n";
}

function para(string $text, string $indent = '           '): void
{
    foreach (explode("\n", wordwrap($text, 72 - strlen($indent))) as $l) {
        echo $indent . $l . "\n";
    }
}

/** Strip the course root from a path, tolerating mixed \ and / separators. */
function relPath(string $path, string $root): string
{
    $p = str_replace('\\', '/', $path);
    $r = rtrim(str_replace('\\', '/', $root), '/') . '/';
    return str_starts_with($p, $r) ? substr($p, strlen($r)) : $p;
}

/** Turn "slim-phpdi-capstone" into "Slim PHP-DI Capstone". */
function prettyTitle(string $slug): string
{
    $acronyms = [
        'lsp' => 'LSP', 'tdd' => 'TDD', 'api' => 'API', 'di' => 'DI',
        'ioc' => 'IoC', 'oop' => 'OOP', 'php' => 'PHP', 'phpdi' => 'PHP-DI',
        'phpunit' => 'PHPUnit', 'sqlite' => 'SQLite', 'solid' => 'SOLID',
    ];
    $small = ['vs', 'and', 'not', 'of', 'the', 'with', 'over', 'to', 'in', 'a', 'why'];

    $words = explode('-', $slug);
    $out   = [];
    foreach ($words as $i => $w) {
        $lower = strtolower($w);
        if (isset($acronyms[$lower])) {
            $out[] = $acronyms[$lower];
        } elseif ($i > 0 && in_array($lower, $small, true)) {
            $out[] = $lower;
        } else {
            $out[] = ucfirst($lower);
        }
    }
    return implode(' ', $out);
}

/** Run a command, return [exitCode, combinedOutput]. */
function run(string $cmd): array
{
    $out = [];
    $rc  = 0;
    exec($cmd . ' 2>&1', $out, $rc);
    return [$rc, implode("\n", $out)];
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. ENVIRONMENT
// ─────────────────────────────────────────────────────────────────────────────

heading('1. Environment');

echo "  PHP " . PHP_VERSION . "  (" . PHP_BINARY . ")\n\n";

if (PHP_VERSION_ID < 80500) {
    line(MARK_FAIL, 'PHP 8.5 or newer is required.');
    para('Several lessons use syntax that does not parse on older versions: '
       . 'clone($obj, [...]), #[\\NoDiscard], #[\\Override] on properties, '
       . '#[\\Deprecated] on traits, and asymmetric visibility for static properties.');
    para('');
    para('If you use Herd, check the GLOBAL PHP override as well as the per-site '
       . 'version — a global override silently wins over the project setting.');
    echo "\n  Stopping here. Nothing else can be checked meaningfully.\n\n";
    exit(1);
}
line(MARK_OK, 'PHP ' . PHP_VERSION . ' — every lesson will run.');

// Composer presence
[$rcComposer] = run('composer --version');
$hasComposer  = $rcComposer === 0;
$hasComposer
    ? line(MARK_OK, 'Composer available.')
    : line(MARK_WARN, 'Composer not found on PATH.');

// Dependencies — install them if they are missing
$autoload = $root . '/vendor/autoload.php';

if (!is_file($autoload)) {
    if ($noInstall) {
        line(MARK_FAIL, 'Dependencies not installed (--no-install given).');
        echo "\n  Run: composer install\n\n";
        exit(1);
    }
    if (!$hasComposer) {
        line(MARK_FAIL, 'Dependencies missing and Composer is not available.');
        para('Install Composer from https://getcomposer.org, then run: composer install');
        echo "\n";
        exit(1);
    }

    line(MARK_WARN, 'Dependencies missing — installing them now.');
    para('Running: composer install    (this may take a minute)');
    echo "\n";

    passthru('composer install --no-interaction --working-dir=' . escapeshellarg($root), $rcInstall);
    echo "\n";

    if ($rcInstall !== 0 || !is_file($autoload)) {
        line(MARK_FAIL, 'composer install did not complete.');
        para('Read the Composer output above — the usual cause is a platform '
           . 'requirement your PHP build does not satisfy.');
        echo "\n";
        exit(1);
    }
    line(MARK_OK, 'Dependencies installed.');
} else {
    line(MARK_OK, 'Dependencies installed.');
}

require_once $autoload;

foreach ([
    'PHP-DI'     => \DI\ContainerBuilder::class,
    'Slim'       => \Slim\Factory\AppFactory::class,
    'Slim PSR-7' => \Slim\Psr7\Factory\ServerRequestFactory::class,
    'PHPUnit'    => \PHPUnit\Framework\TestCase::class,
] as $name => $class) {
    class_exists($class)
        ? line(MARK_OK, "{$name} present.")
        : line(MARK_FAIL, "{$name} missing — try: composer install");
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. COURSE INTEGRITY  (delegated to verify.php)
// ─────────────────────────────────────────────────────────────────────────────

heading('2. Course integrity');

if ($skipVerify) {
    line(MARK_WARN, 'Skipped (--skip-verify).');
} else {
    echo "  Syntax-checking every course file and probing each PHP 8.4/8.5 feature...\n\n";

    [$rcVerify, $verifyOut] = run($php . ' ' . escapeshellarg($root . '/verify.php'));

    if ($rcVerify === 0) {
        line(MARK_OK, 'Course files are intact — nothing broken or missing.');
    } else {
        line(MARK_FAIL, 'verify.php reported problems with the course itself.');
        echo "\n";
        echo $verifyOut . "\n";
        para('This is a problem with the course files, not with your work. '
           . 'Fix these before continuing — your challenges cannot be judged '
           . 'against a broken course.', '  ');
        echo "\n";
        exit(1);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. PROGRESS
// ─────────────────────────────────────────────────────────────────────────────

heading('3. Your progress through the challenges');

/**
 * Build the ordered lesson list straight from the filesystem, so this never
 * drifts out of sync with the course.
 *
 * @return list<array{id:string,dir:string,title:string,challenge:?string}>
 */
function discoverLessons(string $root): array
{
    $lessons = [];
    foreach (glob($root . '/module-*', GLOB_ONLYDIR) ?: [] as $moduleDir) {
        foreach (glob($moduleDir . '/lesson-*', GLOB_ONLYDIR) ?: [] as $lessonDir) {
            $slug = basename($lessonDir);                       // lesson-2.3-enums
            if (!preg_match('/^lesson-(\d+)\.(\d+)-(.*)$/', $slug, $m)) {
                continue;
            }
            $challenge = is_dir($lessonDir . '/challenge') ? $lessonDir . '/challenge' : null;
            $lessons[] = [
                'sort'      => ((int) $m[1]) * 1000 + (int) $m[2],
                'id'        => $m[1] . '.' . $m[2],
                'dir'       => $lessonDir,
                'title'     => prettyTitle($m[3]),
                'challenge' => $challenge,
            ];
        }
    }
    usort($lessons, static fn(array $a, array $b): int => $a['sort'] <=> $b['sort']);
    return $lessons;
}

/** Pull the fenced code block that follows an "Expected Output" heading. */
function expectedOutput(string $challengeDir): ?string
{
    $md = $challengeDir . '/CHALLENGE.md';
    if (!is_file($md)) {
        return null;
    }
    $text = (string) file_get_contents($md);
    // NOTE the \r? — every CHALLENGE.md in this repo uses CRLF line endings.
    // Requiring a bare \n after the fence silently matched nothing at all.
    if (!preg_match('/^#+\s*Expected Output\s*$(.*?)```[a-z]*\r?\n(.*?)```/msi', $text, $m)) {
        return null;
    }
    return $m[2];
}

/** Normalise a blob of output into comparable non-empty lines. */
function normaliseLines(string $blob): array
{
    $lines = preg_split('/\R/', $blob) ?: [];
    $out   = [];
    foreach ($lines as $l) {
        $l = trim(preg_replace('/\s+/', ' ', $l) ?? '');
        if ($l !== '') {
            $out[] = $l;
        }
    }
    return $out;
}

/**
 * Every expected line must appear SOMEWHERE in the actual output. Order is not
 * required, and extra output is fine.
 *
 * Order was required originally, and it was too strict: the Expected Output
 * blocks are hand-written excerpts, and several lessons legitimately print the
 * right lines in a different sequence to the one documented. Presence is the
 * meaningful signal; sequence is not.
 *
 * @return array{0:bool,1:?string} [matched, firstMissingLine]
 */
function matchesExpected(array $expected, array $actual): array
{
    foreach ($expected as $want) {
        if (isAnnotationLine($want)) {
            continue;
        }
        $found = false;
        foreach ($actual as $line) {
            if (lineMatches($want, $line)) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            return [false, $want];
        }
    }
    return [true, null];
}

/**
 * Some documented lines are notes to the reader rather than program output:
 * "...", "... (identical output)", "(same as above)". They are not assertions.
 */
function isAnnotationLine(string $line): bool
{
    $t = trim($line);
    if ($t === '' || trim($t, '. ') === '') {
        return true;
    }
    // A line that is an ellipsis plus a parenthetical aside.
    return (bool) preg_match('/^\.{2,}\s*\(.*\)$/', $t);
}

/**
 * Does one actual line satisfy one expected line?
 *
 * The CHALLENGE.md blocks legitimately contain placeholders: "#XXXXX" where an
 * order number is randomly generated, and "..." where output has been elided.
 * Those are correct documentation, so treat them as wildcards rather than
 * failing on them.
 */
function lineMatches(string $want, string $actual): bool
{
    $hasPlaceholder = str_contains($want, '...')
        || preg_match('/X{3,}/', $want) === 1
        || preg_match('/\[[a-z_ ]+\]/i', $want) === 1;

    if (!$hasPlaceholder) {
        return $actual === $want || str_contains($actual, $want);
    }

    if (isAnnotationLine($want)) {
        return true;
    }

    $pattern = preg_quote($want, '/');

    // "... " and " ..." absorb their surrounding whitespace, so a documented
    // "name ... PASS" still matches an actual "name..... PASS".
    $pattern = preg_replace('/(?:\\ )*' . preg_quote('\.\.\.', '/') . '(?:\\ )*/', '.*', $pattern) ?? $pattern;

    // #XXXXX — a randomly generated id.
    $pattern = preg_replace('/X{3,}/', '.*', $pattern) ?? $pattern;

    // [timestamp], [id], [random value] — bracketed placeholder tokens. Real
    // bracketed output like "[EMAIL]" is uppercase or contains punctuation, so
    // only lowercase word tokens are treated as placeholders.
    $pattern = preg_replace('/\\\[[a-z_]+(?:\\ [a-z_]+)*\\\]/', '.*', $pattern) ?? $pattern;

    return preg_match('/' . $pattern . '/', $actual) === 1;
}

/**
 * Judge one challenge.
 *
 * @return array{state:string,detail:string,hint:string}
 *         state: done | todo | course-bug | unknown
 */
function judge(array $lesson, string $root, string $php): array
{
    $dir = $lesson['challenge'];

    // A challenge may opt out of machine judging by carrying an attestation
    // box in its CHALLENGE.md. Two need this: the Module 4 capstone (you build
    // it in your own folder, so there is nothing here to run) and Lesson 5.5
    // (its starter tests pass by design — the work is adding new ones).
    if ($dir !== null && is_file($dir . '/CHALLENGE.md')) {
        $md = (string) file_get_contents($dir . '/CHALLENGE.md');
        if (preg_match(
            '/^\s*-\s*\[([ xX])\]\s*\*\*Lesson\s+' . preg_quote($lesson['id'], '/') . '\s+complete\.\*\*/mi',
            $md,
            $box
        )) {
            if (strtolower(trim($box[1])) === 'x') {
                return ['state' => 'done', 'detail' => 'Marked complete in CHALLENGE.md.', 'hint' => ''];
            }
            return [
                'state'  => 'todo',
                'detail' => 'Not yet marked complete.',
                'hint'   => 'This challenge cannot be judged automatically. Do the work described '
                          . 'in ' . relPath($dir . '/CHALLENGE.md', $root) . ', then tick the '
                          . '"Lesson ' . $lesson['id'] . ' complete." box at the bottom of that file.',
            ];
        }
    }

    // Lessons 1.0 and 5.0 are reading-only — there is no code to judge. They
    // carry a single attestation checkbox at the bottom of their README, which
    // is the one thing a script can check about them.
    if ($dir === null) {
        $readme = $lesson['dir'] . '/README.md';
        if (!is_file($readme)) {
            return ['state' => 'none', 'detail' => '', 'hint' => ''];
        }
        $text = (string) file_get_contents($readme);
        if (!preg_match(
            '/^\s*-\s*\[([ xX])\]\s*\*\*Lesson\s+' . preg_quote($lesson['id'], '/') . '\s+complete\.\*\*/mi',
            $text,
            $box
        )) {
            return ['state' => 'none', 'detail' => '', 'hint' => ''];
        }
        if (strtolower(trim($box[1])) === 'x') {
            return ['state' => 'done', 'detail' => 'Marked complete in the lesson README.', 'hint' => ''];
        }
        return [
            'state'  => 'todo',
            'detail' => 'Reading lesson — not yet marked complete.',
            'hint'   => 'Read ' . relPath($readme, $root) . ', run its examples, then tick the '
                      . '"Lesson ' . $lesson['id'] . ' complete." box at the bottom of that file.',
        ];
    }

    // ── Capstone: it ships its own request-simulation test script ──────────
    $capstoneTests = $dir . '/tests/routes.test.php';
    if (is_file($capstoneTests)) {
        [$rc, $out] = run($php . ' ' . escapeshellarg($capstoneTests));
        if ($rc === 0 && str_contains($out, 'All tests PASSED')) {
            return ['state' => 'done', 'detail' => 'All capstone route tests pass.', 'hint' => ''];
        }
        $summary = '';
        if (preg_match('/Results:.*$/m', $out, $m)) {
            $summary = trim($m[0]);
        }
        return [
            'state'  => 'todo',
            'detail' => $summary !== '' ? $summary : 'The capstone route tests are not passing yet.',
            'hint'   => 'Build the API in your own folder from the task list in CHALLENGE.md, '
                      . 'then run: php ' . relPath($capstoneTests, $root),
        ];
    }

    // ── PHPUnit challenges (Modules 5-6): delegate to the verified runner ──
    $starterTests = glob($dir . '/starter/*.php') ?: [];
    if ($starterTests !== []) {
        $fragment = basename(dirname($dir));   // lesson-5.2-unit-testing-...
        [$rc, $out] = run(
            $php . ' ' . escapeshellarg($root . '/run-tests.php')
            . ' --only-starter ' . escapeshellarg($fragment)
        );
        if ($rc === 0 && !str_contains($out, 'NO TESTS EXECUTED')) {
            return ['state' => 'done', 'detail' => 'Your tests pass.', 'hint' => ''];
        }

        $detail = 'Your test file is not passing yet.';
        if (preg_match('/^\d+\)\s*(.+)$/m', $out, $m)) {
            $detail = trim($m[1]);
        } elseif (str_contains($out, 'No tests executed')) {
            $detail = 'No tests ran — the starter file has no completed test methods yet.';
        }
        return [
            'state'  => 'todo',
            'detail' => $detail,
            'hint'   => 'Work in ' . relPath($starterTests[0], $root)
                      . ' — then re-run this check.',
        ];
    }

    // ── Script challenges (Modules 1-4) ────────────────────────────────────
    $starter = $dir . '/starter.php';
    if (!is_file($starter)) {
        return ['state' => 'unknown', 'detail' => 'No starter file found.', 'hint' => ''];
    }

    [$rc, $out] = run($php . ' ' . escapeshellarg($starter));
    $rel        = relPath($starter, $root);
    $todoCount  = preg_match_all('/\bTODO\b/', (string) file_get_contents($starter));

    if ($rc !== 0) {
        $first = '';
        foreach (preg_split('/\R/', $out) ?: [] as $l) {
            if (preg_match('/(Fatal error|Parse error|Uncaught)/i', $l)) {
                $first = trim($l);
                break;
            }
        }
        return [
            'state'  => 'todo',
            'detail' => $first !== '' ? $first : 'The starter script exits with an error.',
            'hint'   => $todoCount > 0
                ? "Looks untouched — {$todoCount} TODO markers remain in {$rel}."
                : "Work in {$rel}.",
        ];
    }

    $expectedBlob = expectedOutput($dir);

    if ($expectedBlob === null) {
        // No documented output to compare against. Fall back to a weak check and
        // say so plainly rather than pretending this is a real pass.
        if ($todoCount > 0) {
            return [
                'state'  => 'todo',
                'detail' => "Runs cleanly, but {$todoCount} TODO markers still remain.",
                'hint'   => "Complete the tasks in {$rel}, then re-run.",
            ];
        }
        return [
            'state'  => 'done',
            'detail' => 'Runs cleanly with no TODO markers left (no documented output to compare).',
            'hint'   => '',
        ];
    }

    $expected = normaliseLines($expectedBlob);
    $actual   = normaliseLines($out);
    [$ok, $missing] = matchesExpected($expected, $actual);

    if ($ok) {
        return ['state' => 'done', 'detail' => 'Output matches the expected result.', 'hint' => ''];
    }

    // Before blaming the student: does the REFERENCE SOLUTION match this block?
    $solution = $dir . '/solution.php';
    if (is_file($solution)) {
        [$srcCode, $solOut]     = run($php . ' ' . escapeshellarg($solution));
        $solLines               = normaliseLines($solOut);
        [$solOk, $solMissing]   = matchesExpected($expected, $solLines);
        if ($srcCode !== 0 || !$solOk) {
            $detail = 'The documented expected output does not match the reference solution either.';
            if ($solMissing !== null) {
                // Name the exact line, and show the nearest thing the solution
                // actually printed — that pair is usually enough to see the fix.
                $near      = '';
                $outOfOrder = false;
                foreach ($solLines as $l) {
                    if (lineMatches($solMissing, $l)) {
                        // It IS printed — just not where the documented order
                        // says it should be. Very different problem.
                        $outOfOrder = true;
                        $near       = $l;
                        break;
                    }
                    similar_text($l, $solMissing, $pctSim);
                    if ($near === '' && $pctSim > 60) { $near = $l; }
                }
                $detail .= "\n\nDocumented:  {$solMissing}";
                if ($outOfOrder) {
                    $detail .= "\nSolution prints this line, but in a DIFFERENT ORDER than documented.";
                } elseif ($near !== '') {
                    $detail .= "\nSolution prints:  {$near}";
                } else {
                    $detail .= "\n(the solution prints nothing resembling it)";
                }
            }
            return [
                'state'  => 'course-bug',
                'detail' => $detail,
                'hint'   => 'This is a course documentation bug, not your mistake. '
                          . 'It is not blocking you — check your work against solution.php by eye.',
            ];
        }
    }

    return [
        'state'  => 'todo',
        'detail' => 'Output does not yet contain: ' . $missing,
        'hint'   => $todoCount > 0
            ? "Looks untouched — {$todoCount} TODO markers remain in {$rel}."
            : "Work in {$rel}. Compare your output with the Expected Output block in CHALLENGE.md.",
    ];
}

// ── Walk the lessons ────────────────────────────────────────────────────────

// ── --dump: capture what each solution actually prints ─────────────────────
if ($dump) {
    $dumpDir = $root . '/solution-output';
    @mkdir($dumpDir, 0777, true);
    $n = 0;
    foreach (discoverLessons($root) as $l) {
        $sol = $l['challenge'] . '/solution.php';
        if ($l['challenge'] === null || !is_file($sol)) {
            continue;
        }
        [, $out] = run($php . ' ' . escapeshellarg($sol));
        file_put_contents($dumpDir . '/' . $l['id'] . '.txt', $out);
        $n++;
    }
    line(MARK_OK, "Wrote {$n} solution outputs to solution-output/");
    echo "\n";
}

$lessons   = discoverLessons($root);
// Every lesson is accountable: 28 through their challenge, 2 through the
// attestation box in their README.
$totalWork = count($lessons);

$completed  = 0;
/** @var array<string,string> lesson id => why it could not be judged */
$courseBugs = [];
$stopped    = null;
$reached    = false;

foreach ($lessons as $lesson) {
    $label = sprintf('Lesson %-4s %s', $lesson['id'], $lesson['title']);

    if ($reached && !$showAll) {
        line(MARK_LOCKED, $label);
        continue;
    }

    $result = judge($lesson, $root, $php);

    if ($result['state'] === 'none') {
        line('[ read ]', $label . '  (nothing to check)');
        continue;
    }

    if ($result['state'] === 'done') {
        $completed++;
        line(MARK_DONE, $label);
        continue;
    }

    if ($result['state'] === 'course-bug') {
        // Deliberately NOT counted as complete — you may not have done it. But
        // it cannot be judged fairly either, so it does not block you.
        $courseBugs[$lesson['id']] = $result['detail'];
        line(MARK_WARN, $label . '  (cannot be judged — course issue, not blocking)');
        continue;
    }

    line(MARK_HERE, $label);
    if (!$reached) {
        $stopped = ['lesson' => $lesson, 'result' => $result];
        $reached = true;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Report
// ─────────────────────────────────────────────────────────────────────────────

heading('Where you are');

$pct = $totalWork > 0 ? (int) round($completed / $totalWork * 100) : 0;
$bar = str_repeat('#', (int) round($pct / 4)) . str_repeat('.', 25 - (int) round($pct / 4));

echo "  [{$bar}] {$pct}%\n";
echo "  {$completed} of {$totalWork} lessons complete.\n";

if ($courseBugs !== []) {
    echo "\n";
    line(MARK_WARN, 'Could not judge ' . count($courseBugs) . ' lesson(s): '
                  . implode(', ', array_keys($courseBugs)));
    para("The documented output for these does not match the course's own reference "
       . 'solution, so neither passing nor failing you would mean anything. They are '
       . 'skipped, not counted, and not blocking.');
    foreach ($courseBugs as $id => $why) {
        echo "\n";
        echo "    Lesson {$id}\n";
        foreach (explode("\n", $why) as $l) {
            echo '      ' . $l . "\n";
        }
    }
}

if ($stopped === null) {
    echo "\n";
    line(MARK_OK, 'Every challenge is complete. That is the whole course.');
    para('Re-read COURSE_PHILOSOPHY.md — the six rules should feel obvious now in a '
       . 'way they did not on day one.');
    echo "\n";
    exit(0);
}

$lesson = $stopped['lesson'];
$result = $stopped['result'];
$rel    = relPath($lesson['dir'], $root);

echo "\n";
echo "  You are here:  Lesson {$lesson['id']} — {$lesson['title']}\n";
echo "  " . str_repeat('-', 68) . "\n\n";
echo "  What is failing:\n";
para($result['detail'], '    ');

if ($result['hint'] !== '') {
    echo "\n  What to do:\n";
    para($result['hint'], '    ');
}

echo "\n  Where to look:\n";
echo "    Lesson       {$rel}/README.md\n";
echo "    Challenge    {$rel}/challenge/CHALLENGE.md\n";
if (is_dir($lesson['dir'] . '/quiz')) {
    echo "    Quiz         {$rel}/quiz/QUIZ.md\n";
}

echo "\n  Everything before this is done. Solve this one and re-run:\n";
echo "    php check.php\n\n";

exit(1);
