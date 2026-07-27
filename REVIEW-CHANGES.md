# Course Review — What Was Wrong and What Changed

A full pass over the course: 6 modules, 30 lessons, ~200 PHP files and ~95 markdown files. Every PHP 8.4/8.5 claim was checked against php.net and the accepted RFCs. Everything below has been fixed in place — `git diff` shows the changes.

---

## Critical — the course would not have run

### 1. `clone with` used syntax that does not exist in PHP

This was the single biggest problem, and it broke three lessons.

The course wrote the PHP 8.5 "clone with" feature two different ways, neither of which is real PHP:

```php
return clone $this with ['amountCents' => $newAmount];   // Lessons 1.2, 2.2 — parse error
return clone $this with { status: 'paid' };              // Lesson 6.4 — parse error
```

Both look plausible — they resemble early drafts of the RFC and the syntax used in other languages. The accepted [Clone With v2 RFC](https://wiki.php.net/rfc/clone_with_v2) spells it as a **call**:

```php
return clone($this, ['amountCents' => $newAmount]);
```

There is no `with` keyword in PHP. **13 occurrences** across code and prose in Lesson 1.2, Lesson 2.2, Lesson 6.4 and three module READMEs. Every one is now correct, and the prose explicitly warns that the feature's name is misleading.

### 2. Every `set` hook wrote to the backing store twice

The property-hooks material used this idiom throughout — **31 occurrences** across Lesson 2.2's README, all five examples, the challenge solution and the quiz:

```php
public string $title = '' {
    set(string $value) => $this->title = trim($value);   // writes twice
}
```

In the arrow form, [the manual](https://www.php.net/manual/en/language.oop5.property-hooks.php) is explicit: *"The value the expression evaluates to will be set on the backing value."* So this assigns `trim($value)` to the backing store, and then assigns the expression's result to the backing store again. It produces the right answer by accident while teaching the wrong mental model — in a lesson whose entire purpose is that mental model. Now:

```php
set(string $value) => trim($value);
```

One case was genuinely different and needed the opposite fix: `Product::$price` writes to a *separate* backing field (`$rawPrice`), so the short form cannot express it. That one is now the block form, with a comment explaining why.

### 3. Modules 4–6 had no `composer.json`

Every PHP-DI and Slim example does `require __DIR__ . '/../../../../vendor/autoload.php'` — four levels up, resolving to the course root. There was no `composer.json` there, so **none of Module 4 could run** and Module 5's PHPUnit tests had nothing to run under. Added at the root, with PHP-DI, Slim, Slim PSR-7 and PHPUnit.

---

## Factual errors

| Where | Claim | Correction |
|-------|-------|-----------|
| Root `README.md` | Features "will throw a parse error on PHP **8.5** or below" | 8.**4** or below — as written it said 8.5 code fails on 8.5 |
| Lesson 1.3 example | "PHP **8.0** introduced the `#[Deprecated]` attribute" | PHP **8.4** |
| Module 1 `README.md` | Trait deprecation fires "when this class is instantiated" | It fires when the class is *compiled* and the trait composed in |
| Lesson 1.1 quiz, Q12 | `abstract` on an interface method "was deprecated in PHP 7" | It is a fatal error: *"Access type for interface method … must be omitted"* |
| Lesson 4.4 `README.md` | "Every example starts with `require __DIR__ . '/../vendor/autoload.php'`" | It is `'/../../../../vendor/autoload.php'` |
| Root `README.md` | Module 6: 4 challenges, 4 quizzes | 5 and 5 |

Everything else checked out. `clone with`, `#[\NoDiscard]`, `#[\Override]` on properties, `#[\Deprecated]` on traits and constants, and asymmetric visibility for static properties are all genuinely PHP 8.5, and all the version attributions in the feature tables are right.

---

## The property-hooks quiz answer key

Lesson 2.2's answer key was the weakest content in the course — two model answers visibly lose the thread mid-sentence.

**Q16** asked you to explain a bug, and the key answered *"Actually, this code works correctly as written"* before trailing off into speculation about what the colleague might have done. The code in question:

```php
public float $area = 0.0 {
    get => M_PI * $this->radius ** 2;
}
```

The `get` hook never references `$this->area`, so `$area` is **virtual** — no backing storage. A virtual property cannot have a default value, and PHP rejects this at compile time. The file does not run at all. The question is now framed correctly ("explain why PHP rejects this declaration") and the answer explains virtuality and gives the fix.

**Q17** asked how to make a property publicly readable but privately writable. The key wandered through three wrong approaches, said *"actually in PHP 8.4 the set hook visibility cannot be `private` independently"*, and landed on `readonly` — which is **incompatible with property hooks**. The correct answer is asymmetric visibility, which the course already teaches in Lesson 1.1:

```php
public private(set) string $email = '' {
    set(string $value) => strtolower(trim($value));
}
```

Rewritten, with the manual's own recommendation quoted.

**Underneath both** sat a wrong definition of "virtual", repeated in five places (Q1, Q8, Q10, Q15 and the Q16 answer): *"a virtual property has no default value and no `set` hook."* Virtuality is determined by whether the hooks reference the property itself — and a virtual property **may** have a `set` hook; the manual allows defining both. All five now carry the correct definition.

Q18's answer also quoted an exact engine message that varies by version; it now describes the error and tells you to mark yourself correct on the substance.

---

## Broken references and structure

- **Lesson 1.0's checklist** pointed at `examples/srp.php`, `ocp.php`, `lsp.php`, `isp.php`, `dip.php`. The files are numbered — `01-srp.php` and so on. All five were dead links. Two more dead references to the same files sat in Lesson 1.1's example comments.
- **Module 2's README** referenced `examples/01-the-violation.php` and three siblings relative to the module root, where they do not exist. Now `lesson-2.0-lsp/examples/…`.
- **Lesson 5.3's folder was `example/`** (singular) while every other lesson — and every README pointing at it — uses `examples/`. Content moved to `examples/`. The old files could not be deleted from here, so they are now stubs that tell you where the content went; **the `example/` folder is safe to delete.**

---

## Correctness and portability

- **`/tmp/legacy.log`** was hardcoded in Lesson 1.3's runnable example and in Module 1's README. On Windows `/tmp` does not exist, so `file_put_contents` emits a warning. Now `sys_get_temp_dir()`.
- **Lesson 1.1's challenge had impossible expected output.** `CHALLENGE.md` promised `[STRIPE] Charging R1500.00 ZAR`, but `"R{$amount}"` with a float prints `R1500` — PHP drops trailing zeros. Anyone comparing their output to the brief would have concluded they'd done it wrong. Now uses `number_format($amount, 2)`, with a note explaining why.
- **Lesson 1.1's `ResponseHandler` implemented `HttpContract` purely to borrow its constants**, stubbing `send()` with `return [];`. That is exactly the Interface Segregation violation Lesson 1.0 Example 04 warns against — the course taught the anti-pattern four files after warning about it. It no longer implements the interface (it never needed to; the file's own closing section makes that point), and a comment flags the temptation.
- **A stray escape** sat in a comment in `06-clone-with.php` (`// Carried over from original\n\n";`).
- **A browser-run instruction** told you to open Lesson 1.1 Example 01 at `http://localhost/...`. Every example is a CLI script with box-drawing output and `\n` breaks; in a browser it is unreadable. Replaced with the CLI command and a note that the whole course is CLI-only.

---

## The Module 4 capstone

`challenge/` was described as "Folder Structure (already created)" with tasks to build each file — but every file was **already fully implemented**. There is no `starter.php`. Open the folder to begin the challenge and you have read the entire answer.

The files could not be deleted or restructured from here without risking the reference implementation, so `CHALLENGE.md` now opens with a prominent warning: build it in a scratch folder outside the course, and treat `challenge/` as the solution to check yourself against. The task list is a complete specification, so this works.

The capstone also expected its own `vendor/` from a second `composer install`. It now uses the course-root autoloader; the root `composer.json` maps `App\` to its `src/`.

---

## Coverage gap

The root README, Module 2's README and Module 2's checklist all promised **`#[\Override]` on properties** in Lesson 2.0. Lesson 2.0 never mentioned it — no example, nothing in its README, nothing in its checklist. Every other PHP 8.5 feature in the course has a dedicated numbered example.

Added `lesson-2.0-lsp/examples/05-override-on-properties.php` plus a README section, both wired into the checklists. It is written to earn its place in the LSP lesson: the failure mode it prevents is a child silently declaring a *new* property when a parent renames the one it meant to override — a contract violation that produces no error at all.

---

## New files

| File | What it does |
|------|-------------|
| `composer.json` | PHP-DI, Slim, Slim PSR-7, PHPUnit. Maps `App\` to the capstone's `src/`. |
| `verify.php` | **Run this first.** Checks your PHP version, actually executes a probe for each 8.4/8.5 feature the course depends on, syntax-checks all 200 PHP files, and confirms Composer deps. |
| `phpunit.xml` | Per-lesson test suites. Always pass `--testsuite`; see below. |
| `run-tests.php` | Runs every Module 5/6 test file in its own process. `php run-tests.php`, or `php run-tests.php 5.2` — the argument is a substring match on the path, so pass `module-5` rather than `5` to scope it to a module. |
| `PROGRESS.md` | Single tracker — read / ran / challenge / quiz / score per lesson, plus per-module gates. |
| `REVIEW-CHANGES.md` | This file. |
| `index.php` | Course cover page. Herd serves this folder at a `.test` domain; without an index the site preview looks broken. Shows live PHP version, dependency status and your real progress parsed from `PROGRESS.md`. Self-contained — no autoload, no CDN — so it renders correctly *before* `composer install` has ever run. |
| `check.php` | **The one command to run.** Environment → integrity → progress. Walks the 28 challenges in course order and stops at the first unsolved one. |

### How `check.php` judges a challenge

Modules 1–4 are scripts: your `starter.php` is run and its output compared to the **Expected Output** block in `CHALLENGE.md`. The comparison is an *ordered subsequence*, not an exact string — every expected line must appear in order, but extra output around them is fine, so a debug `echo` will not fail you.

Modules 5–6 are PHPUnit files: `run-tests.php --only-starter` executes your test file. It passes when your tests pass.

The capstone runs its own request-simulation script.

**The part worth knowing:** those 24 expected-output blocks were hand-written and never validated against the solutions — and at least one was demonstrably wrong (Lesson 1.1 documented `R1500.00` where PHP prints `R1500`). Gating progress on unverified documentation would block students for the course's mistakes. So when your output does not match, `check.php` runs the *reference solution* against the same block first. If the solution fails too, you are told it is a course bug and it is not counted against you.

### Why `run-tests.php` exists

The lessons deliberately reuse familiar class names so each reads standalone — `OrderService`, `ShoppingCart`, `SpyLogger`, `SimpleContainer` and ~46 others each appear in several files. None are namespaced. PHP cannot load two classes with the same name in one process, so a plain `vendor/bin/phpunit` over the whole course dies with *"Cannot declare class X, name already in use"*. Three lessons have this collision even within themselves (5.4, 6.2, 6.5).

Namespacing 200 files is the textbook fix, but it is a large refactor and I had no PHP 8.5 available here to verify it. Giving each file its own process is safe, needs no changes to the lesson material, and always works.

---

## Verification on real PHP 8.5

Everything above was found by reading against the spec. Everything below was found by **running the course on PHP 8.5.7**, which caught a second class of bug entirely — code that reads correctly and parses fine, but does the wrong thing.

Final state: **197/197 files parse · all 8.4/8.5 feature probes pass live · every Module 1–4 example runs clean · capstone 36/36 · test suite 43/43.**

### Bugs only execution could find

| Where | Problem |
|---|---|
| 10 Module 4 files | `__DIR__ . '/../../../../vendor/autoload.php'` — four levels up from `examples/` overshoots the course root by one. Needs three. **I reviewed this, judged it correct, then edited the Lesson 4.4 README to assert "four levels up" — making the docs agree with the broken code.** |
| `4.2/02-reading-constructor-params.php` | `LoggerInterface $logger = null` — implicit nullable is fatal on 8.5 |
| `4.2/02` + `03-handling-edge-cases.php` | `ReflectionParameter::hasDefaultValue()` does not exist (4 calls) → `isDefaultValueAvailable()` |
| `4.2/04-caching-reflection.php` | `autoResolve()` never recursed, so an unbound concrete dependency aborted the demo — in the auto-wiring lesson |
| `4.2/01`, `4.2/04` | `end(explode(...))` passes a temporary to a by-reference parameter |
| `3.4/04`, 2× Module 5 | `setAccessible()` — no-op since 8.1, deprecated in 8.5 |
| `3.2/04-multiple-dependencies.php` | Array interpolated into a string |
| `2.2/01-the-problem-they-solve.php` | `setEmail()` validated *before* trimming, in both the before- and after-hooks classes — uncaught exception killed the file |
| `2.2/03-set-hook.php` | Read `$order->id` from outside a `private` promoted property |
| Capstone Rule 1 audit | `str_contains($src, 'getenv(')` matched the controllers' own docblock saying *"Rule 1: No getenv()"* — the audit flagged its own comment |
| `5.1` Money | `format()` used `number_format($x, 2)`, emitting `'EUR 1,000.00'` where CHALLENGE.md Task 7 specifies `'EUR 1000.00'`. Its data provider also contradicted itself — no separator on one row, a separator five rows later |
| `5.4` solution | Referenced 12 classes it never declared, with a comment admitting they were *"omitted here for brevity"*. All 25 tests errored. Extracted the shared code to `challenge/app.php`, which both starter and solution now require — restoring the pattern Lessons 5.1 and 5.2 already use |
| `5.5/01-brittle-vs-resilient-tests.php` | `withConsecutive()` was **removed in PHPUnit 10**. Fittingly, it was removed because it locked tests to an exact call sequence — the exact anti-pattern this lesson teaches |
| `6.1/01-share-nothing-demo.php` | Asked `$req1['counter']->isFirstVisit()` after three requests — but all three share one object, so it was false for every reference. The test was defeated by the aliasing it exists to demonstrate |
| `6.3/01-accumulating-service.php` | Expected `$28.50`, assuming leaked state only crossed the discount threshold. It also inflates the subtotal: 6 items → `$60` → 5% off → **`$57.00`**. Two compounding faults, not one |

### Bugs in my own tooling

Worth recording, because the tooling was supposed to be the safety net:

- **`verify.php` clone-with probe** — modified a `readonly` property from *outside* the declaring class, which throws. It reported `clone with — failed to run` and I nearly read that as PHP's fault rather than mine. `-d error_reporting=0` had swallowed the message that would have said so.
- **`verify.php` probes** — a top-level `return` halts a PHP script, so the `PROBE_OK` sentinel appended after each probe never executed. 7 of 8 features reported failure while working perfectly.
- **`run-tests.php`, three attempts** — PHPUnit 11 resolves the test class from the *filename*, so `01-first-test.php` (holding `CalculatorTest`) was unrunnable. A `<file>` element did not help; a shim that `require`d the real file made PHPUnit claim the class "does not extend TestCase" and broke files that had been passing. What works: run the file as-is when the name already matches, otherwise drop a temporary copy named after the class *beside* the original — a sibling, not a temp dir, so `__DIR__` stays valid for Lesson 5.4's `../app.php`.
- **`run-tests.php` reported "All green" having executed zero tests.** A mangled regex matched nothing, every file was skipped before any counter moved, and an empty failure list read as success. It now prints skipped counts, names skipped files, and exits non-zero with *"NO TESTS EXECUTED — that is a runner failure, not a pass"*.

That last one is the one to remember. A harness that fails loudly gets investigated; a harness that reports green having done nothing gets trusted.

---

## Cover page and progress checker

Added later, after the course was already verified green.

`index.php` is a self-contained cover page. Herd serves this folder at a `.test` domain, and with no index file the site preview looks like a broken site. It is PHP rather than static HTML so it can show live state: your actual detected PHP version, whether dependencies are installed, and your real completion percentage parsed from `PROGRESS.md`.

`check.php` answers the question nothing else did — *where am I?* `verify.php` only ever said whether the course was intact. `check.php` runs environment → integrity → progress, walking the 28 challenges in course order and stopping at the first unsolved one.

### What building it uncovered

Grading challenges meant comparing solution output to the documented "Expected Output" blocks — and those blocks had never been executed. **Twelve of them did not match their own reference solutions.** Regenerating them from verified runs exposed three further defects that no earlier check could see, because `php -l` proves a file parses and PHPUnit proves tests pass; neither notices a reference solution misbehaving at runtime:

- **Lesson 1.4's solution failed its own assertions** — it printed `Some assertions FAILED` while its checklist asked "All seven test assertions pass?". Both `publish()` methods injected `StorageInterface` and never called it: an unused injected dependency, in the lesson about composing injected collaborators. It now persists before publishing.
- **Lesson 2.3's solution emitted six PHP warnings.** `"Notification.$channel"` in a double-quoted string interpolated three variables that do not exist; they were meant to be literal property names.
- **Lesson 3.3 leaked floating-point noise** into a JSON payload — `2999.9700000000003` instead of `2999.97`.

Money formatting was also wrong across Modules 1–4: sixteen `number_format($x, 2)` calls used the default thousands separator, so documented values like `R1500.00` printed as `R1,500.00`. All now pass `'.', ''` explicitly.

The expected-output blocks were then regenerated from verified solution output, truncated before each solution's self-review tail, with non-deterministic values masked — random order ids to `XXXXX`, object hashes and timestamps likewise. That is a deliberate editorial choice: the blocks are now longer and more literal than the hand-written excerpts they replace, but they describe what the code actually does. One of the originals referenced a test, `testLoggerCaptures`, that does not exist.

---

## Six challenges that were already solved

Found late, by asking why `check.php` reported 21% complete on an untouched checkout. Six of the
28 challenges passed without the student doing anything — and they had **four different causes**,
which is why the single symptom was misleading.

- **1.2, 2.4, 3.4 are refactors.** Extract an abstract base; replace named stubs with anonymous
  classes; invert dependencies. A refactor prints exactly the same thing before and after, so
  comparing output can never detect it. Each now carries an **acceptance block** that inspects
  structure through Reflection — is the base class abstract, do the constructor parameters
  type-hint interfaces rather than concretions — and the expected output demands its
  `ACCEPTANCE: all checks passed` line.
- **3.1 is an audit, not a coding exercise.** You annotate violations in deliberately-bad code
  that runs perfectly well. Now judged by counting the annotations.
- **4.5 and 5.5 cannot be machine-judged at all**, and pretending otherwise was the real mistake.
  The capstone's `challenge/` folder holds the *reference* implementation, so running its tests
  only ever proved my code worked. And 5.5's brittle tests pass **by design** — they go red when
  you apply the refactor, so red is success. Both now use an attestation box, and `check.php`
  learned a general rule: any challenge may opt out of automated judging by carrying one.

### And a fifth broken reference solution

**Lesson 3.4's `solution.php` was a byte-identical copy of `starter.php`** — same 18 TODO markers,
zero differences. The reference solution had never been written. It was invisible because the
expected output had been regenerated from that file, so the starter matched itself perfectly.

The solution now exists and is verified: four interfaces, constructor injection throughout, a flat
composition root, a reflection-based `MiniContainer`, and a test wiring using a fake repository, a
spy mailer and a null-object logger. Three wirings, identical output, no class changed between
them.

Lesson 2.4's solution was also broken in a subtler way: it printed a `FAIL` line and then
announced `All 5 tests passed.` The summary was an unconditional `echo`, and the failing assertion
looked for a log message containing `'charged'` — which is the *audit event* vocabulary
(`payment.charged`), while the logger writes "Charging…" and "Charge result:". The author
conflated the two.

**A note on method.** Regenerating expected output from verified solution runs is the right
approach, but it inherits whatever the solution does wrong — that is exactly how 2.4's
contradiction got baked into its documentation. Run the solution *and read what it printed*
before trusting it as the source of truth.

---

## Quiz answer keys

Every quiz has code-reading questions that state exactly what a program prints. Those claims are
checkable, so `audit-quizzes.php` runs each program and compares: **31 of the course's 374
questions, all 31 now passing.** The multiple-choice keys, true/false answers and short-answer
models — the other 343 — still need reading. But this is the 8% where a wrong key does the most
damage, because a student who reasons correctly and is told they are wrong will distrust
everything after it.

Four keys were wrong, and all four failed the same way: the author wrote an answer, realised
mid-paragraph it was wrong, corrected it in the prose — and left the fenced block above still
showing the original. A student reads the block and stops.

- **Lesson 2.4 Q18** — block said `different class`; the prose reasoned its way to `same class`
  and was right. Both instances come from the same `new class(...)` expression, so the engine
  gives them the same generated name. An anonymous class is anonymous to *you*, not to the engine.
- **Lesson 2.3 Q19** — block showed four lines ending `Most frequent level value: 3`; the prose
  corrected it to a `TypeError`. An enum case has both a `name` and a `value` and they are not
  interchangeable — `from()` takes the value.
- **Lesson 4.2 Q20** — block claimed `App\DbInterface, App\LoggerInterface`; the explanation three
  lines below correctly said `DbInterface` and `LoggerInterface`. The snippet declares those
  interfaces in the global namespace, so `ReflectionNamedType::getName()` has no prefix to return.
- **Lesson 2.2 Q18** — the expected `Error:` line was a placeholder rather than the engine's
  wording. Now `Error: Property Temperature::$fahrenheit is read-only`.

Four snippets also said "assume `AutowiringContainer` from the lesson", which is unusable advice —
lesson 4.3 defines four different classes by that name. They now name the file. That is a fix for
the student first; the audit reads the same line and lifts the named class out of the named file
so those questions became checkable rather than being written off.

**Lesson 6.1 had no quiz at all.** `quiz/QUIZ.md` was a 443-line copy of the challenge solution — a
file write that went to the wrong path and was never opened. Written now to the house format
(8 multiple choice, 6 true/false, 3 short answer, 3 code reading), covering the three lifecycles,
the three runtime models, why share-nothing is an accidental safety feature rather than an earned
one, and why a `clear()` method is a weaker fix than a stateless design. Lesson 6.2's key refers to
"the `clear()` approach critiqued in the Lesson 6.1 quiz" — that reference now resolves.

### What the tool had to learn

The first version of this audit reported **28 wrong answer keys**. All 28 were the tool. It pasted
`require 'vendor/autoload.php';` above each snippet, which displaced `declare(strict_types=1)` from
the first line, so every program died before executing a statement. The autoloader is now passed as
`auto_prepend_file` — a separate file, so the declare keeps its position — and the runner exits with
a HARNESS FAULT notice if it ever sees that engine message again. A number this tool produces should
be trustworthy or absent.

The second version reported three wrong keys that were all correct. It had been deciding whether a
program dies by looking for words like `TypeError` **in the key**, and keys legitimately discuss
errors that do not occur: *"if `add()` had returned `self` this would be a TypeError"*, *"a different
default would be a fatal error"*. Teaching by counterfactual read as a prediction of failure. What
the program does now selects the comparison; what the key says about it is the thing being judged.

Three outcomes are reported, not two — correct, wrong, and *unverifiable*. Anything the harness
cannot run is stated as such and excluded from the count. Calling those failures would produce a
list that trains you to ignore the list, which is worse than not running the audit at all.

---

## Module gates

The gates in `PROGRESS.md` were self-assessment checkboxes, each drawn entirely from the module just
finished — "name the four test-double types", asked on the afternoon you learned them. That tests
whether the material is still in working memory, which is the one thing you can be confident of at
that moment. Module 4 had no gate at all.

They now point at `GATES.md`, and each gate is in two halves. Part 1 covers the module just
completed. Part 2 reaches back:

| First taught | Revisited | And again |
|---|---|---|
| Module 1 — SOLID, composition, value objects | Gate 2 | Gates 4 and 5 |
| Module 2 — LSP, hooks, enums, anonymous classes | Gate 3 | Gate 5 |
| Module 3 — coupling, injection, composition root | Gate 4 | Gate 6 |
| Module 4 — containers, reflection, auto-wiring | Gate 5 | Gate 6 |
| Module 5 — doubles, TDD, behaviour testing | Gate 6 | — |

Forty-six prompts, every one with a model answer. The carried-forward ones are written to make the
connection rather than merely repeat the earlier lesson: Gate 5 asks which SOLID principle determines
how much work a test double is (Interface Segregation — you must implement every method whether the
test uses it or not), and Gate 6 asks why a value object is automatically safe as a singleton. The
answer a student gives to the second question is worth more if they have not been told the two topics
are related.

Two things worth being explicit about. **None of this is machine-checked, and it cannot be** — no
script can tell whether you reconstructed an answer or recognised one. Given that the rest of the
course deliberately refuses to take your word for anything, the one place that must is worth naming
rather than glossing over. And the instruction throughout is to answer with the lesson **shut**:
opening it first turns retrieval into recognition, which feels like studying and is close to
worthless.

`php run-tests.php 5` also appeared in two places as the way to run Module 5's tests. The argument is
a case-insensitive substring match against the full path, so `5` additionally matches
`lesson-6.5-factory-definitions`. Corrected to `module-5`.

---

## Running it

```bash
composer install
php verify.php        # PHP version, live feature probes, syntax-check all 197 files
php run-tests.php     # Modules 5 and 6, one process per file
```

Maintenance only, not part of the course: `php audit-quizzes.php` re-checks the answer keys.

Track your way through in [`PROGRESS.md`](PROGRESS.md). Start at Module 1, Lesson 1.0.

Both scripts are safe to re-run at any time. `verify.php` changes nothing. `run-tests.php` creates a temporary copy of a lesson file only while that file's tests are executing and deletes it immediately, with a shutdown handler as a backstop if the run is interrupted — a clean `git status` after a run is the check that it behaved.

## Known cosmetic leftovers

- `module-5-testing-and-tdd/lesson-5.3-tdd/example/` (singular) holds three redirect stubs. The content moved to `examples/` to match every other lesson. **The `example/` folder is safe to delete** — nothing references it and `verify.php` skips it.
- The Module 4 capstone's `challenge/` is the finished reference implementation, not a starter. `CHALLENGE.md` opens with a warning to build it in your own folder first.

