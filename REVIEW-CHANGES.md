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
| `run-tests.php` | Runs every Module 5/6 test file in its own process. `php run-tests.php`, or `php run-tests.php 5.2`. |
| `PROGRESS.md` | Single tracker — read / ran / challenge / quiz / score per lesson, plus per-module gates. |
| `REVIEW-CHANGES.md` | This file. |

### Why `run-tests.php` exists

The lessons deliberately reuse familiar class names so each reads standalone — `OrderService`, `ShoppingCart`, `SpyLogger`, `SimpleContainer` and ~46 others each appear in several files. None are namespaced. PHP cannot load two classes with the same name in one process, so a plain `vendor/bin/phpunit` over the whole course dies with *"Cannot declare class X, name already in use"*. Three lessons have this collision even within themselves (5.4, 6.2, 6.5).

Namespacing 200 files is the textbook fix, but it is a large refactor and I had no PHP 8.5 available here to verify it. Giving each file its own process is safe, needs no changes to the lesson material, and always works.

---

## One thing you should know

**I could not execute any of this.** The sandbox has no PHP, and installing one was blocked. Every syntax correction above is verified against the official PHP manual and the accepted RFCs rather than against a running interpreter, and I deliberately kept the changes surgical for that reason — no mass refactors I couldn't test.

`verify.php` closes that gap on your machine. Run it before Lesson 1.0:

```bash
composer install
php verify.php
```

It runs a live probe for each 8.4/8.5 feature and syntax-checks every file, so anything I got wrong surfaces in one command rather than three lessons in.
