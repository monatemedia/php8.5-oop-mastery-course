<?php
declare(strict_types=1);

/**
 * index.php — Course cover page
 * ================================
 * Served by Herd at https://php8.5-oop-mastery-course.test (or whatever Herd
 * named this folder). Nothing in the course itself is served over HTTP — every
 * lesson is a CLI script — but Herd shows a "Site Preview" for the folder, and
 * without an index file that preview looks like a broken site.
 *
 * So this page does three jobs:
 *   1. Explains what the course is and what you will be able to do afterwards
 *   2. Reports LIVE environment status — your actual PHP version, whether
 *      Composer dependencies are installed
 *   3. Shows your real progress, parsed from PROGRESS.md
 *
 * Deliberately self-contained: no Composer autoload, no CDN, no build step.
 * It has to render correctly BEFORE `composer install` has ever been run,
 * because that is exactly when a confused student will first open it.
 */

// ─────────────────────────────────────────────────────────────────────────────
// Live environment detection
// ─────────────────────────────────────────────────────────────────────────────

$phpVersion   = PHP_VERSION;
$phpOk        = PHP_VERSION_ID >= 80500;
$vendorOk     = is_file(__DIR__ . '/vendor/autoload.php');
$lockOk       = is_file(__DIR__ . '/composer.lock');

/** Parse PROGRESS.md checkboxes so the page reflects real work done. */
$done = $total = 0;
$progressFile = __DIR__ . '/PROGRESS.md';
if (is_file($progressFile)) {
    $md = (string) file_get_contents($progressFile);
    preg_match_all('/\[([ xX])\]/', $md, $boxes);
    $total = count($boxes[1]);
    $done  = count(array_filter($boxes[1], static fn(string $c): bool => strtolower($c) === 'x'));
}
$pct = $total > 0 ? (int) round($done / $total * 100) : 0;

/** @return array{0:string,1:string} [label, state] */
function statusPill(bool $ok, string $okText, string $badText): array
{
    return [$ok ? $okText : $badText, $ok ? 'ok' : 'bad'];
}

[$phpLabel, $phpState]       = statusPill($phpOk, "PHP {$phpVersion}", "PHP {$phpVersion} — needs 8.5+");
[$vendorLabel, $vendorState] = statusPill($vendorOk, 'Dependencies installed', 'Run composer install');

$modules = [
    [
        'n'     => 1,
        'title' => 'OOP Building Blocks',
        'blurb' => 'All five SOLID principles, then the choice that defines everything after it: composition over inheritance.',
        'lessons' => ['SOLID Principles Overview', 'Interfaces', 'Abstract Classes & Value Objects', 'Traits', 'Composition over Inheritance'],
        'feature' => 'Static asymmetric visibility · clone with · #[\Deprecated] on traits',
    ],
    [
        'n'     => 2,
        'title' => 'Advanced Types & Enums',
        'blurb' => 'Use the type system as an enforcement mechanism, not documentation. Liskov, variance, hooks and enums.',
        'lessons' => ['Liskov Substitution Principle', 'Type Hinting & Return Types', 'Property Hooks', 'Enums', 'Anonymous Classes'],
        'feature' => '#[\Override] on properties · #[\NoDiscard] · property hooks',
    ],
    [
        'n'     => 3,
        'title' => 'Dependency Injection & IoC',
        'blurb' => 'Why <code>new</code> inside a constructor is the root of untestable code, and the three ways to fix it.',
        'lessons' => ['Tight vs Loose Coupling', 'Constructor Injection', 'Setter & Interface Injection', 'Inversion of Control'],
        'feature' => 'Constructor property promotion · interface type hints',
    ],
    [
        'n'     => 4,
        'title' => 'Container Automation',
        'blurb' => 'Build a service container from scratch with Reflection, then replace it with PHP-DI and ship a real API.',
        'lessons' => ['Service Containers', 'PHP Reflection API', 'Auto-wiring', 'PHP-DI Library', 'Capstone: Slim + PHP-DI'],
        'feature' => 'Reflection API · PHP-DI 7 · Slim 4',
    ],
    [
        'n'     => 5,
        'title' => 'Automated Testing & TDD',
        'blurb' => 'Why DI is the prerequisite for testing at all — then PHPUnit, doubles, red-green-refactor, and what not to assert.',
        'lessons' => ['Why Testing Requires DI', 'PHPUnit Fundamentals', 'Fakes and Stubs', 'TDD: Red, Green, Refactor', 'Integration Testing', 'Testing Behaviours, Not Layouts'],
        'feature' => 'PHPUnit 11 · SQLite integration tests · request simulation',
    ],
    [
        'n'     => 6,
        'title' => 'Object Lifecycle & State',
        'blurb' => "PHP's share-nothing model, and what breaks the moment you move to a long-running worker.",
        'lessons' => ['Share-Nothing Architecture', 'Transient vs Singleton Scopes', 'The Danger of Stateful Services', 'Designing Stateless Services', 'Factory Definitions'],
        'feature' => 'Container scopes · immutable value objects',
    ],
];

$rules = [
    'Config belongs at the entry point, not in core logic',
    'Test behaviours, not layouts',
    'The type system is a security layer',
    'Favour composition over inheritance',
    'Objects either hold state or perform work — rarely both',
    'Read the failing test before reading the code',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PHP 8.5 OOP Mastery Course</title>
<meta name="description" content="A local, CLI-first course in advanced PHP object orientation: SOLID, dependency injection, containers, TDD and object lifecycle.">
<style>
  :root {
    --bg:        #0e1116;
    --bg-soft:   #161b22;
    --bg-card:   #1a2029;
    --line:      #2a323d;
    --text:      #e6edf3;
    --muted:     #9aa7b4;
    --accent:    #a78bfa;
    --accent-dk: #7c5cf0;
    --ok:        #3fb950;
    --bad:       #f85149;
    --radius:    14px;
    --maxw:      1080px;
  }

  * { box-sizing: border-box; }

  html { -webkit-text-size-adjust: 100%; }

  body {
    margin: 0;
    background: var(--bg);
    color: var(--text);
    font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto,
                 "Helvetica Neue", Arial, sans-serif;
    line-height: 1.65;
    -webkit-font-smoothing: antialiased;
  }

  code, .mono {
    font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas,
                 "Liberation Mono", monospace;
    font-size: 0.9em;
  }

  .wrap { width: 100%; max-width: var(--maxw); margin-inline: auto; padding-inline: clamp(1rem, 4vw, 2rem); }

  a { color: var(--accent); }

  /* ── Hero ──────────────────────────────────────────────────────────────── */
  .hero {
    padding-block: clamp(2.5rem, 8vw, 5rem) clamp(2rem, 6vw, 3.5rem);
    background:
      radial-gradient(ellipse 80% 60% at 50% -10%, rgba(124, 92, 240, 0.22), transparent 70%),
      var(--bg);
    border-bottom: 1px solid var(--line);
  }
  .eyebrow {
    display: inline-block;
    font-size: 0.78rem;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--accent);
    border: 1px solid rgba(167, 139, 250, 0.35);
    background: rgba(167, 139, 250, 0.08);
    padding: 0.3rem 0.75rem;
    border-radius: 999px;
    margin-bottom: 1.25rem;
  }
  h1 {
    font-size: clamp(2rem, 7vw, 3.4rem);
    line-height: 1.1;
    margin: 0 0 1rem;
    letter-spacing: -0.02em;
  }
  h1 .grad {
    background: linear-gradient(100deg, var(--accent), #67e8f9);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
  }
  .lede {
    font-size: clamp(1.02rem, 2.5vw, 1.2rem);
    color: var(--muted);
    max-width: 62ch;
    margin: 0 0 2rem;
  }

  /* ── Status strip ──────────────────────────────────────────────────────── */
  .status { display: flex; flex-wrap: wrap; gap: 0.6rem; margin-bottom: 2rem; }
  .pill {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.45rem 0.9rem;
    border-radius: 999px;
    font-size: 0.86rem;
    border: 1px solid var(--line);
    background: var(--bg-soft);
  }
  .dot { width: 8px; height: 8px; border-radius: 50%; flex: none; }
  .ok  .dot { background: var(--ok);  box-shadow: 0 0 0 3px rgba(63,185,80,0.15); }
  .bad .dot { background: var(--bad); box-shadow: 0 0 0 3px rgba(248,81,73,0.15); }
  .ok  { color: #a4e0ad; }
  .bad { color: #ffb3ae; }

  /* ── Progress ──────────────────────────────────────────────────────────── */
  .progress-card {
    background: var(--bg-card);
    border: 1px solid var(--line);
    border-radius: var(--radius);
    padding: 1.1rem 1.25rem;
    margin-bottom: 2rem;
  }
  .progress-head {
    display: flex; justify-content: space-between; align-items: baseline;
    gap: 1rem; margin-bottom: 0.7rem; flex-wrap: wrap;
  }
  .progress-head strong { font-size: 0.95rem; }
  .progress-head span { color: var(--muted); font-size: 0.86rem; }
  .bar { height: 8px; background: #0b0e13; border-radius: 999px; overflow: hidden; }
  .bar > i {
    display: block; height: 100%;
    background: linear-gradient(90deg, var(--accent-dk), var(--accent));
    border-radius: 999px;
    transition: width .4s ease;
  }

  .cta { display: flex; flex-wrap: wrap; gap: 0.75rem; }
  .btn {
    display: inline-block;
    padding: 0.7rem 1.3rem;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.94rem;
    text-decoration: none;
    border: 1px solid var(--line);
    color: var(--text);
    background: var(--bg-soft);
  }
  .btn.primary { background: var(--accent-dk); border-color: var(--accent-dk); color: #fff; }

  /* ── Sections ──────────────────────────────────────────────────────────── */
  section { padding-block: clamp(2.5rem, 7vw, 4rem); }
  section + section { border-top: 1px solid var(--line); }
  h2 {
    font-size: clamp(1.35rem, 4vw, 1.9rem);
    margin: 0 0 0.5rem;
    letter-spacing: -0.01em;
  }
  .sub { color: var(--muted); margin: 0 0 2rem; max-width: 62ch; }

  /* ── Module grid ───────────────────────────────────────────────────────── */
  .grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 300px), 1fr)); }
  .card {
    background: var(--bg-card);
    border: 1px solid var(--line);
    border-radius: var(--radius);
    padding: 1.35rem;
    display: flex; flex-direction: column;
    min-width: 0; overflow-wrap: anywhere;
  }
  .card h3 { margin: 0.5rem 0 0.5rem; font-size: 1.08rem; }
  .num {
    display: inline-grid; place-items: center;
    width: 30px; height: 30px; border-radius: 8px;
    background: rgba(167,139,250,0.12);
    color: var(--accent);
    font-weight: 700; font-size: 0.85rem;
    border: 1px solid rgba(167,139,250,0.3);
  }
  .card p { color: var(--muted); font-size: 0.92rem; margin: 0 0 1rem; }
  .card ul { list-style: none; margin: 0 0 1rem; padding: 0; }
  .card li {
    font-size: 0.87rem; padding: 0.28rem 0 0.28rem 1.1rem;
    position: relative; color: #cbd5e1;
  }
  .card li::before {
    content: ""; position: absolute; left: 0; top: 0.85em;
    width: 5px; height: 5px; border-radius: 50%; background: var(--accent); opacity: 0.65;
  }
  .feat {
    margin-top: auto; padding-top: 0.9rem; border-top: 1px solid var(--line);
    font-size: 0.78rem; color: var(--muted);
  }

  /* ── Rules ─────────────────────────────────────────────────────────────── */
  .rules { display: grid; gap: 0.6rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 320px), 1fr)); list-style: none; padding: 0; margin: 0; }
  .rules li {
    background: var(--bg-soft); border: 1px solid var(--line);
    border-radius: 10px; padding: 0.85rem 1rem;
    font-size: 0.92rem; display: flex; gap: 0.75rem; align-items: flex-start;
  }
  .rules b { color: var(--accent); flex: none; }

  /* ── Start steps ───────────────────────────────────────────────────────── */
  ol.steps { counter-reset: s; list-style: none; padding: 0; margin: 0; display: grid; gap: 1rem; }
  ol.steps li {
    counter-increment: s;
    background: var(--bg-card); border: 1px solid var(--line);
    border-radius: var(--radius); padding: 1.1rem 1.25rem 1.1rem 3.4rem; position: relative;
    /* Grid children default to min-width:auto, so the long unbreakable command
       inside <pre> below would otherwise stretch this card past the viewport
       and force the whole page to scroll sideways on a phone. */
    min-width: 0;
    overflow-wrap: anywhere;
  }
  ol.steps li::before {
    content: counter(s);
    position: absolute; left: 1.1rem; top: 1.15rem;
    width: 26px; height: 26px; border-radius: 50%;
    display: grid; place-items: center;
    background: var(--accent-dk); color: #fff; font-size: 0.8rem; font-weight: 700;
  }
  ol.steps h4 { margin: 0 0 0.35rem; font-size: 0.98rem; }
  ol.steps p { margin: 0.35rem 0 0; color: var(--muted); font-size: 0.88rem; }
  pre {
    background: #0b0e13; border: 1px solid var(--line);
    border-radius: 8px; padding: 0.75rem 0.9rem; margin: 0.6rem 0 0;
    overflow-x: auto;                 /* long commands scroll inside the card... */
    max-width: 100%; min-width: 0;    /* ...instead of widening it */
    font-size: 0.86rem;
    -webkit-overflow-scrolling: touch;
  }
  pre code { white-space: pre; }
  pre code { color: #cbd5e1; }
  .cmt { color: #6b7785; }

  .note {
    background: rgba(167,139,250,0.07);
    border: 1px solid rgba(167,139,250,0.25);
    border-left-width: 3px;
    border-radius: 8px; padding: 0.9rem 1.1rem;
    font-size: 0.9rem; color: #cbd5e1; margin-top: 1.5rem;
  }

  footer {
    border-top: 1px solid var(--line);
    padding-block: 2rem 3rem;
    color: var(--muted); font-size: 0.85rem;
  }
  footer a { color: var(--muted); }

  @media (max-width: 480px) {
    .card { padding: 1.1rem; }
    ol.steps li { padding-left: 1.1rem; padding-top: 2.9rem; }
    ol.steps li::before { top: 1rem; }
  }

  @media (prefers-reduced-motion: reduce) {
    * { transition: none !important; }
  }
</style>
</head>
<body>

<header class="hero">
  <div class="wrap">
    <span class="eyebrow">Local · CLI-first · Version-locked</span>
    <h1>Advanced PHP,<br><span class="grad">properly understood</span></h1>
    <p class="lede">
      Six modules that take you from “I know what an interface is” to designing service
      graphs a container can wire itself — with the tests to prove they behave. Thirty
      lessons, 28 code challenges, 27 quizzes, and one capstone API you build from scratch.
    </p>

    <div class="status">
      <span class="pill <?= $phpState ?>"><span class="dot"></span><?= htmlspecialchars($phpLabel, ENT_QUOTES) ?></span>
      <span class="pill <?= $vendorState ?>"><span class="dot"></span><?= htmlspecialchars($vendorLabel, ENT_QUOTES) ?></span>
      <?php if ($lockOk): ?>
        <span class="pill ok"><span class="dot"></span>Versions locked</span>
      <?php endif; ?>
    </div>

    <?php if ($total > 0): ?>
    <div class="progress-card">
      <div class="progress-head">
        <strong>Your progress</strong>
        <span><?= $done ?> of <?= $total ?> items · <?= $pct ?>%</span>
      </div>
      <div class="bar"><i style="width: <?= max($pct, 1) ?>%"></i></div>
      <div class="progress-head" style="margin: 0.7rem 0 0;">
        <span>Tracked in <code>PROGRESS.md</code> — tick items off as you finish them.</span>
      </div>
    </div>
    <?php endif; ?>

    <div class="cta">
      <a class="btn primary" href="#start">How to start</a>
      <a class="btn" href="#modules">What you'll learn</a>
    </div>
  </div>
</header>

<section id="modules">
  <div class="wrap">
    <h2>The six modules</h2>
    <p class="sub">
      Strictly sequential. Each module assumes the one before it — Module 4 cannot be
      understood without Module 3, and Module 5 exists to prove why Module 3 mattered.
    </p>

    <div class="grid">
      <?php foreach ($modules as $m): ?>
      <article class="card">
        <span class="num"><?= $m['n'] ?></span>
        <h3><?= htmlspecialchars($m['title'], ENT_QUOTES) ?></h3>
        <p><?= $m['blurb'] /* contains intentional <code> markup */ ?></p>
        <ul>
          <?php foreach ($m['lessons'] as $l): ?>
            <li><?= htmlspecialchars($l, ENT_QUOTES) ?></li>
          <?php endforeach; ?>
        </ul>
        <div class="feat"><?= htmlspecialchars($m['feature'], ENT_QUOTES) ?></div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section>
  <div class="wrap">
    <h2>Six golden rules</h2>
    <p class="sub">
      Every design decision in the course runs through this list. They are not independent —
      composition enables DI, DI enables testability, testability sustains good design.
    </p>
    <ul class="rules">
      <?php foreach ($rules as $i => $r): ?>
        <li><b><?= $i + 1 ?></b> <span><?= htmlspecialchars($r, ENT_QUOTES) ?></span></li>
      <?php endforeach; ?>
    </ul>
    <div class="note">
      Read <code>COURSE_PHILOSOPHY.md</code> before Lesson 1.0. It is short, and the rest of
      the course assumes it.
    </div>
  </div>
</section>

<section id="start">
  <div class="wrap">
    <h2>Getting started</h2>
    <p class="sub">
      Nothing here is served over HTTP — this page is the only web page in the project.
      Every lesson is a command-line script. Open a terminal in the course folder.
    </p>

    <ol class="steps">
      <li>
        <h4>Confirm PHP 8.5</h4>
        <p>
          <?php if ($phpOk): ?>
            You are on <strong><?= htmlspecialchars($phpVersion, ENT_QUOTES) ?></strong> — good to go.
          <?php else: ?>
            You are on <strong><?= htmlspecialchars($phpVersion, ENT_QUOTES) ?></strong>. Several lessons
            use syntax that does not parse below 8.5. In Herd, check the <em>global</em> PHP
            override as well as the per-site version.
          <?php endif; ?>
        </p>
<pre><code>php -v</code></pre>
      </li>

      <li>
        <h4>Install dependencies</h4>
        <p>
          <?php if ($vendorOk): ?>
            Already installed. PHP-DI, Slim and PHPUnit are ready.
          <?php else: ?>
            Modules 4 to 6 need PHP-DI, Slim and PHPUnit. Modules 1 to 3 run on plain PHP.
          <?php endif; ?>
        </p>
<pre><code>composer install</code></pre>
      </li>

      <li>
        <h4>Check where you are</h4>
        <p>
          Verifies your environment, confirms all <?= 197 ?> course files are intact, then walks
          the challenges in order and stops at the first one still unsolved — so you always
          know exactly what to work on next.
        </p>
<pre><code>php check.php</code></pre>
      </li>

      <li>
        <h4>Start Module 1, Lesson 1.0</h4>
        <p>
          Read the lesson README, run every example, do the challenge without opening the
          solution, then take the quiz closed-book. Record it in <code>PROGRESS.md</code>.
        </p>
<pre><code>php module-1-oop-building-blocks/lesson-1.0-solid-overview/examples/01-srp.php</code></pre>
      </li>
    </ol>

    <div class="note">
      <strong>Each lesson has the same four parts:</strong> a README to read, an
      <code>examples/</code> folder to run, a <code>challenge/</code> to complete in
      <code>starter.php</code>, and a <code>quiz/</code> with the answer key at the bottom.
      Do them in that order.
    </div>
  </div>
</section>

<footer>
  <div class="wrap">
    PHP <?= htmlspecialchars($phpVersion, ENT_QUOTES) ?> ·
    <a href="https://www.php.net/releases/8.5/en.php">PHP 8.5 release notes</a> ·
    <a href="https://php-di.org/">PHP-DI</a> ·
    <a href="https://www.slimframework.com/docs/v4/">Slim</a> ·
    <a href="https://phpunit.de/">PHPUnit</a>
    <br>
    This page is informational only. The course runs entirely from the command line.
  </div>
</footer>

</body>
</html>
