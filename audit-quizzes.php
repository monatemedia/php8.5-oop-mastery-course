<?php
declare(strict_types=1);

/**
 * audit-quizzes.php — Check quiz answer keys against reality
 * ============================================================
 *
 *     php audit-quizzes.php            report to stdout
 *     php audit-quizzes.php --write    also write quiz-audit.txt
 *
 * MAINTENANCE TOOL — not part of the course. Students never run this.
 *
 * Every quiz has code-reading questions: a complete PHP program, and an answer
 * key stating exactly what it prints. Those claims are checkable — run the
 * program and compare. That is all this does.
 *
 * It cannot check the multiple-choice keys, the true/false answers or the
 * short-answer model answers. Those need a human. This handles the ~31
 * questions where the answer is a factual claim about program output, which is
 * also where a wrong answer is most damaging: a student who reasons correctly
 * and is told they are wrong will distrust everything after it.
 *
 * Each program runs in its own process, so quizzes that declare classes with
 * the same name cannot collide.
 */

$root  = __DIR__;
$write = in_array('--write', array_slice($argv, 1), true);

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'php85course_quizaudit';
@mkdir($tmp, 0777, true);

$report  = [];
$letters = [];
$checked = 0;
$matched = 0;
$issues  = [];

/** Collapse whitespace and drop blank lines so formatting differences do not matter. */
function normalise(string $blob): array
{
    $out = [];
    foreach (preg_split('/\R/', $blob) ?: [] as $line) {
        $line = trim(preg_replace('/\s+/', ' ', $line) ?? '');
        if ($line !== '') {
            $out[] = $line;
        }
    }
    return $out;
}

/**
 * Lift a single class declaration out of a file that also contains demo code.
 *
 * Several quiz snippets are written against a class taught in the lesson —
 * "Assume AutowiringContainer from examples/02-recursive-resolution.php". The
 * whole file cannot be included, because it ends by running a demonstration
 * that prints its own output. So take the class and leave the rest.
 *
 * Uses the tokenizer rather than a regex: a brace inside a string literal or a
 * comment must not be counted, and only the tokenizer knows the difference.
 */
function extractClass(string $file, string $class): ?string
{
    if (!is_file($file)) {
        return null;
    }

    $src    = (string) file_get_contents($file);
    $tokens = token_get_all($src);

    for ($i = 0, $n = count($tokens); $i < $n; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_CLASS) {
            continue;
        }

        // The next meaningful token is the class name.
        $j = $i + 1;
        while ($j < $n && is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $j++;
        }
        if (!is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING || $tokens[$j][1] !== $class) {
            continue;
        }

        // Walk forward to the opening brace, then brace-match to the close.
        $depth = 0;
        $start = null;
        for ($k = $j; $k < $n; $k++) {
            $text = is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
            if ($text === '{') {
                if ($depth === 0) {
                    $start = $k;
                }
                $depth++;
            } elseif ($text === '}') {
                $depth--;
                if ($depth === 0) {
                    $out = '';
                    for ($m = $i; $m <= $k; $m++) {
                        $out .= is_array($tokens[$m]) ? $tokens[$m][1] : $tokens[$m];
                    }
                    return $out;
                }
            }
        }
        return null;   // unbalanced — refuse rather than guess
    }

    return null;
}

$quizzes = glob($root . '/module-*/lesson-*/quiz/QUIZ.md') ?: [];
sort($quizzes);

foreach ($quizzes as $quizPath) {
    $text = (string) file_get_contents($quizPath);
    $rel  = str_replace(str_replace('\\', '/', $root) . '/', '', str_replace('\\', '/', $quizPath));

    $split = preg_split('/^#\s*✅\s*Answer Key/mu', $text, 2);
    if (!is_array($split) || count($split) < 2) {
        $issues[] = "{$rel}: no '# ✅ Answer Key' heading found — cannot pair answers.";
        continue;
    }
    [$body, $key] = $split;

    // ── Answer-position balance ────────────────────────────────────────────
    // Not a correctness check, but it belongs in the same sweep. B was once the
    // answer to 62% of the course's multiple-choice questions and A or D to 5%
    // between them, which meant a student who never read a question could score
    // 62% by answering B — above the "re-read the README" threshold on most of
    // these quizzes. A quiz that can be passed by pattern-matching measures
    // nothing.
    $sectionA = preg_split('/## Section B/', $key)[0];
    if (preg_match_all('/^\|\s*\d+\s*\|\s*\*\*([A-E])\*\*/m', $sectionA, $lm)) {
        foreach ($lm[1] as $L) {
            $letters[$L] = ($letters[$L] ?? 0) + 1;
        }
    }

    // ── Which question does each code block belong to? ──────────────────────
    preg_match_all('/\*\*Q(\d+)\./', $body, $qm, PREG_OFFSET_CAPTURE);
    $questionAt = [];
    foreach ($qm[1] as $i => $hit) {
        $questionAt[] = ['num' => (int) $hit[0], 'pos' => $qm[0][$i][1]];
    }

    preg_match_all('/```php\R(.*?)```/s', $body, $cm, PREG_OFFSET_CAPTURE);

    $programs = [];   // question number => source
    foreach ($cm[1] as $hit) {
        [$code, $offset] = $hit;
        if (!str_starts_with(ltrim($code), '<?php')) {
            continue;   // a fragment (an answer option, a snippet) — not runnable
        }
        $owner = null;
        foreach ($questionAt as $q) {
            if ($q['pos'] < $offset) {
                $owner = $q['num'];
            }
        }
        if ($owner !== null) {
            $programs[$owner] = $code;
        }
    }

    // ── What does the key claim each one prints? ────────────────────────────
    // Slice the key into one block per question, then interpret each block.
    // Two shapes are in use and both are legitimate: a fenced output block
    // (sometimes followed by prose that qualifies it, e.g. "…then a TypeError"),
    // or pure prose predicting a fatal. Parsing them with two separate regexes
    // was a mistake — the prose pattern excluded backticks, so any answer that
    // mentioned `log()` or `Logger` silently registered as "no answer given".
    $claims = [];

    preg_match_all(
        '/\*\*Q(\d+) — Answer:?\*\*(.*?)(?=\R\*\*Q\d+ — Answer|\R##\s|\z)/su',
        $key,
        $am
    );
    foreach ($am[1] as $i => $num) {
        $block = trim($am[2][$i]);
        if ($block === '') {
            continue;
        }
        // Where a fence is present, the fence is the expected output and any
        // prose after it qualifies that output. Keep both — 2.1 Q18 prints
        // "20" and *then* dies, and the death is only stated in the prose.
        $claims[(int) $num] = $block;
    }

    foreach ($programs as $num => $code) {
        if (!isset($claims[$num])) {
            $issues[] = "{$rel} Q{$num}: runnable program, but the key states no expected output.";
            continue;
        }

        $checked++;

        $file = $tmp . DIRECTORY_SEPARATOR . 'q_' . md5($quizPath . $num) . '.php';
        file_put_contents($file, $code);

        // A snippet may name the lesson class it is written against:
        //     // Assume AutowiringContainer from examples/02-recursive-resolution.php
        // That line is there for the student's benefit — without it the snippet
        // cannot be run at all, and lesson 4.3 defines four different classes by
        // that name. Since it is unambiguous, the audit can honour it too.
        $extra = '';
        if (preg_match('/Assume\s+(\w+)\s+from\s+([\w.\/-]+\.php)/', $code, $src)) {
            $lessonDir = dirname($quizPath, 2);
            $lifted    = extractClass($lessonDir . '/' . $src[2], $src[1]);
            if ($lifted === null) {
                $issues[] = "{$rel} Q{$num}: names '{$src[1]}' in '{$src[2]}', but that class "
                          . "could not be found there (answer key NOT checked).";
                $checked--;
                @unlink($file);
                continue;
            }
            $extra = $lifted;
        }

        // Load the course autoloader so snippets that use PHP-DI or Slim can
        // actually run. This MUST be auto_prepend_file rather than a require
        // pasted above the snippet: nearly every snippet opens with
        // declare(strict_types=1), which the engine requires to be the first
        // statement in ITS file. An auto-prepended file is a separate file, so
        // the declare keeps its position.
        $autoload = $root . '/vendor/autoload.php';

        $preludeSrc = "<?php\n";
        if (is_file($autoload)) {
            $preludeSrc .= 'require ' . var_export($autoload, true) . ";\n";
        }
        $preludeSrc .= $extra . "\n";

        $preludeFile = $tmp . DIRECTORY_SEPARATOR . 'prelude_' . md5($quizPath . $num) . '.php';
        file_put_contents($preludeFile, $preludeSrc);

        $prepend = ' -d ' . escapeshellarg('auto_prepend_file=' . $preludeFile);

        $out = [];
        $rc  = 0;
        exec(
            escapeshellarg(PHP_BINARY) . $prepend . ' ' . escapeshellarg($file) . ' 2>&1',
            $out,
            $rc
        );
        @unlink($file);
        @unlink($preludeFile);

        $actual   = implode("\n", $out);
        $claimed  = $claims[$num];

        // Guard against this tool corrupting the file it is testing. If the
        // engine complains about the shape of the temp file rather than about
        // the code in it, that is a harness bug and must be shouted about, not
        // quietly counted as 28 wrong answer keys.
        if (str_contains($actual, 'strict_types declaration must be the very first statement')) {
            fwrite(
                STDERR,
                "HARNESS FAULT: the temp file for {$rel} Q{$num} has something before its\n"
                . "declare(strict_types=1). The audit results are meaningless — fix the runner.\n"
            );
            exit(2);
        }

        // A snippet that references a class taught in the lesson body cannot
        // run standalone. That is a limitation of this tool, not a wrong answer.
        if (preg_match('/Class "([^"]+)" not found/', $actual, $missing)) {
            $issues[] = "{$rel} Q{$num}: snippet needs class '{$missing[1]}' from the lesson — "
                      . "cannot be verified standalone (answer key NOT checked).";
            $checked--;
            continue;
        }

        // Separate the block into the output it quotes and the prose around it.
        // The fence is the claim about what is PRINTED; the prose usually carries
        // the claim that it then DIES. Conflating them is how 2.3 Q19 stayed
        // wrong — its fence showed four happy lines while the prose underneath
        // corrected the answer to a TypeError.
        preg_match_all('/```(?:[a-z]*)\R(.*?)```/su', $claimed, $fm);
        $fences = $fm[1];
        $prose  = trim(preg_replace('/```(?:[a-z]*)\R.*?```/su', '', $claimed) ?? '');

        $isFatal = (bool) preg_match('/(Fatal error|Parse error|Uncaught)/i', $actual);

        // ── The program ran to completion ───────────────────────────────────
        // Then the only checkable claim is what it printed, and the prose is
        // irrelevant. Reading the prose here was a mistake: keys legitimately
        // discuss errors that did NOT occur — "if add() had returned self this
        // would be a TypeError", "a different default WOULD be a fatal error" —
        // and matching on the word alone turned three correct keys into
        // failures. What the program does decides which comparison applies;
        // what the key says about it is the thing being judged.
        if (!$isFatal) {
            if ($fences === []) {
                $issues[] = "{$rel} Q{$num}: the key answers in prose and the program runs "
                          . "cleanly — nothing to compare (answer key NOT checked).";
                $checked--;
                continue;
            }

            $ok = (normalise($actual) === normalise(implode("\n", $fences)));

            $report[] = [
                'quiz' => $rel, 'q' => $num, 'ok' => $ok, 'kind' => 'output',
                'claimed' => trim($claimed), 'actual' => trim($actual),
            ];
            $ok && $matched++;
            continue;
        }

        // ── The program died ────────────────────────────────────────────────
        // Two things must hold: the key must say it dies, and any output it
        // quotes must match what actually printed before the death.
        $saysFatal = (bool) preg_match(
            '/(Fatal error|Parse error|Uncaught|TypeError|ValueError|ArgumentCountError|Error:)/i',
            $claimed
        );
        $agree = $saysFatal;

        if ($agree && $fences !== []) {
            $beforeFatal = [];
            foreach (normalise($actual) as $l) {
                if (preg_match('/(Fatal error|Parse error|Uncaught|Stack trace|thrown in|^#\d)/i', $l)) {
                    break;
                }
                $beforeFatal[] = $l;
            }

            // Only the FIRST fence quotes the successful output; a second fence
            // quotes the engine's message, which is not part of what printed.
            //
            // The house style ends that first fence by NAMING the error on its
            // own line — "NULL / string(5) HELLO / TypeError", or just
            // "Fatal error" where nothing prints at all. So the fence is the
            // printed lines followed by a label, and both halves must be judged
            // separately: the printed lines must match exactly, and whatever
            // trails them must be a label rather than more claimed output.
            $quoted = normalise($fences[0]);
            $n      = count($beforeFatal);

            $agree = (array_slice($quoted, 0, $n) === $beforeFatal);

            foreach (array_slice($quoted, $n) as $trailing) {
                if (!preg_match(
                    '/^(?:a )?(Fatal error|Parse error|Uncaught|TypeError|ValueError|'
                    . 'ArgumentCountError|Error|DivisionByZeroError|\\?\w*(?:Error|Exception))\b/i',
                    $trailing
                )) {
                    // A line that is neither printed output nor an error label
                    // is the key claiming something the program never did.
                    $agree = false;
                    break;
                }
            }
        }

        $report[] = [
            'quiz' => $rel, 'q' => $num, 'ok' => $agree, 'kind' => 'fatal',
            'claimed' => trim($claimed), 'actual' => trim($actual),
        ];
        $agree && $matched++;
    }
}
@rmdir($tmp);

// ─────────────────────────────────────────────────────────────────────────────
// Report
// ─────────────────────────────────────────────────────────────────────────────

$lines = [];
$lines[] = str_repeat('=', 72);
$lines[] = '  QUIZ ANSWER-KEY AUDIT';
$lines[] = str_repeat('=', 72);
$lines[] = '';
$lines[] = "  Quizzes scanned          : " . count($quizzes);
$lines[] = "  Code-reading Qs checked  : {$checked}";
$lines[] = "  Answer key was correct   : {$matched}";
$lines[] = "  Answer key was WRONG     : " . ($checked - $matched);
$lines[] = '';

$wrong = array_values(array_filter($report, static fn(array $r): bool => !$r['ok']));

if ($wrong === []) {
    $lines[] = '  Every checkable answer matches what the code actually prints.';
} else {
    foreach ($wrong as $r) {
        $lines[] = str_repeat('-', 72);
        $lines[] = "  {$r['quiz']}  —  Q{$r['q']}  ({$r['kind']})";
        $lines[] = str_repeat('-', 72);
        $lines[] = '  KEY CLAIMS:';
        foreach (explode("\n", $r['claimed']) as $l) { $lines[] = '    ' . $l; }
        $lines[] = '  ACTUALLY PRINTS:';
        foreach (explode("\n", $r['actual']) as $l) { $lines[] = '    ' . $l; }
        $lines[] = '';
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Answer-position balance
// ─────────────────────────────────────────────────────────────────────────────

if ($letters !== []) {
    ksort($letters);
    $totalMcq = array_sum($letters);
    $topCount = max($letters);
    $topShare = $topCount / $totalMcq * 100;

    $lines[] = str_repeat('=', 72);
    $lines[] = '  ANSWER-POSITION BALANCE';
    $lines[] = str_repeat('=', 72);
    $lines[] = '';
    foreach ($letters as $L => $n) {
        $bar = str_repeat('#', (int) round($n / $totalMcq * 40));
        $lines[] = sprintf('  %s  %4d  %5.1f%%  %s', $L, $n, $n / $totalMcq * 100, $bar);
    }
    $lines[] = '';
    $lines[] = sprintf(
        '  Best "always answer the same letter" score: %d/%d = %.0f%%   (chance = 25%%)',
        $topCount,
        $totalMcq,
        $topShare
    );
    $lines[] = '';
    if ($topShare > 35.0) {
        $lines[] = '  ** One letter is over-represented. A student can score that much without';
        $lines[] = '     reading the questions, so the quiz is measuring recall of a pattern';
        $lines[] = '     rather than of the material. Move some correct answers to other';
        $lines[] = '     positions — and swap the option TEXT, not just the key letter. **';
        $lines[] = '';
    }
}

if ($issues !== []) {
    $lines[] = str_repeat('=', 72);
    $lines[] = '  STRUCTURAL NOTES (not necessarily wrong — just unpaired)';
    $lines[] = str_repeat('=', 72);
    foreach ($issues as $i) { $lines[] = '  ' . $i; }
    $lines[] = '';
}

$lines[] = str_repeat('=', 72);
$lines[] = '  This tool checks only questions whose answer is a claim about program';
$lines[] = '  output. Multiple-choice keys, true/false answers and short-answer model';
$lines[] = '  answers are NOT verified here and still need reading by a human.';
$lines[] = str_repeat('=', 72);

$text = implode("\n", $lines) . "\n";
echo $text;

if ($write) {
    file_put_contents($root . '/quiz-audit.txt', $text);
    echo "\nWritten to quiz-audit.txt\n";
}

exit($wrong === [] ? 0 : 1);
