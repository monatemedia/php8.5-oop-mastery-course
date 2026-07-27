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
    // Two shapes are used. Most answers quote a fenced output block; a few
    // predict a fatal error in prose instead. Both are real answers.
    $claims = [];

    preg_match_all('/\*\*Q(\d+) — Answer:?\*\*\s*\R+```\R(.*?)```/su', $key, $am);
    foreach ($am[1] as $i => $num) {
        $claims[(int) $num] = $am[2][$i];
    }

    preg_match_all('/\*\*Q(\d+) — Answer:?\*\*\s*\R+([^`]{10,}?)(?=\R\s*\R\*\*Q|\R\s*\R---|$)/su', $key, $pm);
    foreach ($pm[1] as $i => $num) {
        $n = (int) $num;
        if (!isset($claims[$n])) {
            $claims[$n] = $pm[2][$i];   // prose answer, e.g. "Fatal error. log() is final ..."
        }
    }

    foreach ($programs as $num => $code) {
        if (!isset($claims[$num])) {
            $issues[] = "{$rel} Q{$num}: runnable program, but the key states no expected output.";
            continue;
        }

        $checked++;

        $file = $tmp . DIRECTORY_SEPARATOR . 'q_' . md5($quizPath . $num) . '.php';
        file_put_contents($file, $code);

        $out = [];
        $rc  = 0;
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1', $out, $rc);
        @unlink($file);

        $actual   = implode("\n", $out);
        $claimed  = $claims[$num];

        $isFatal  = (bool) preg_match('/(Fatal error|Parse error)/i', $actual);
        $saysFatal = (bool) preg_match('/(Fatal error|Parse error)/i', $claimed);

        // The key sometimes writes "Fatal error" plus prose rather than the
        // engine's exact wording. Agreeing that it dies is the real claim.
        if ($saysFatal || $isFatal) {
            $agree = ($saysFatal === $isFatal);
            $report[] = [
                'quiz' => $rel, 'q' => $num, 'ok' => $agree, 'kind' => 'fatal',
                'claimed' => trim($claimed), 'actual' => trim($actual),
            ];
            $agree && $matched++;
            continue;
        }

        $a = normalise($actual);
        $c = normalise($claimed);
        $ok = ($a === $c);

        $report[] = [
            'quiz' => $rel, 'q' => $num, 'ok' => $ok, 'kind' => 'output',
            'claimed' => trim($claimed), 'actual' => trim($actual),
        ];
        $ok && $matched++;
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
