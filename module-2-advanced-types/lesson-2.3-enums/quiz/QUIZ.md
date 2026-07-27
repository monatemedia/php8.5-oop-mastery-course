# Quiz — Lesson 2.3: Enums (PHP 8.1+)
> Complete this quiz **without** looking at any example or solution files.
> Write your answers before checking the answer key at the bottom.
> Any question you get wrong is a reading target.

---

## Section A — Multiple Choice

**Q1.** Which of the following is a **pure (unit) enum** case?

- A) `case Active = 'active';`
- B) `case Active = 1;`
- C) `case Active;`
- D) `const Active = 'active';`

---

**Q2.** You have `enum Color { case Red; case Green; case Blue; }`. What does `Color::Red->value` return?

- A) `"Red"`
- B) `0` — PHP assigns integer indices automatically.
- C) `null` — with an "Undefined property" warning.
- D) Fatal error — pure enum cases have no `->value` property.

---

**Q3.** What is the difference between `Status::from('active')` and `Status::tryFrom('active')`?

- A) `from()` is for string-backed enums; `tryFrom()` is for integer-backed enums.
- B) `from()` returns null if the value is not found; `tryFrom()` throws an exception.
- C) `from()` throws `\ValueError` if the value is not found; `tryFrom()` returns `null`.
- D) They are identical — `tryFrom()` is just an alias for `from()`.

---

**Q4.** You define `enum Priority: int { case Low = 1; case High = 10; }`. What does `Priority::High->name` return?

- A) `10`
- B) Fatal error — `->name` is only available on pure enums.
- C) `"Priority::High"`
- D) `"High"`

---

**Q5.** An enum implements an interface. Which of the following is **true**?

- A) Enum cases are `instanceof` both the enum AND the interface it implements.
- B) Enum cases are `instanceof` the enum only — not the interface.
- C) Enum cases cannot be passed to functions typed against that interface.
- D) Enums cannot implement interfaces in PHP 8.1.

---

**Q6.** You have:
```php
enum Season { case Spring; case Summer; case Autumn; case Winter; }

function describe(Season $s): string {
    return match($s) {
        Season::Spring => "Flowers bloom.",
        Season::Summer => "Sun is hot.",
        Season::Autumn => "Leaves fall.",
        // Winter is not covered
    };
}
```
What happens when `describe(Season::Winter)` is called?

- A) PHP returns an empty string silently.
- B) PHP returns `null`.
- C) `\UnhandledMatchError` is thrown at runtime.
- D) PHP falls through to the next match arm.

---

**Q7.** Which of the following correctly iterates over all cases of a backed enum and prints each name and value?

- A) `foreach (Status as $case) { echo $case->name . ': ' . $case->value; }`
- B) `foreach (Status::cases() as $case) { echo $case->name . ': ' . $case->value; }`
- C) `foreach (Status::all() as $case) { echo $case->name . ': ' . $case->value; }`
- D) `foreach (Status::values() as $case) { echo $case->name . ': ' . $case->value; }`

---

**Q8.** Which of the following statements about enums is **false**?

- A) Enums can be extended using `extends`.
- B) Enum cases can be used as type hints for function parameters.
- C) An enum case can be used as a default parameter value.
- D) Enums can contain static methods.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 9  | In a backed enum, two cases can share the same backing value. | |
| 10 | `enum Status: string { case Active = 'active'; }` — `Status::Active->name` returns `"active"`. | |
| 11 | Pure enum cases are singletons — `Status::Active === Status::Active` is always `true`. | |
| 12 | An enum can have instance properties beyond `name` and `value`. | |
| 13 | `Status::cases()` returns an array of all enum cases, available on both pure and backed enums. | |
| 14 | When using `match` with an enum and all cases are covered, no `default` arm is needed. | |

---

## Section C — Short Answer

**Q15.** Explain in two sentences when you should use `from()` vs `tryFrom()` when converting an external value to an enum case.

*Your answer:*

---

**Q16.** What is the key advantage of using `match` with an enum over using `if`/`elseif` chains comparing string values?

*Your answer:*

---

**Q17.** A colleague writes this code to validate a form input:
```php
$status = $_POST['status'];
if ($status !== 'active' && $status !== 'inactive' && $status !== 'banned') {
    throw new \InvalidArgumentException("Invalid status.");
}
```
Describe how you would refactor this using a backed enum and `tryFrom()`.

*Your answer:*

---

## Section D — Code Reading

**Q18.** What will the following code output? Write the output exactly, or write "Fatal error" and explain why.

```php
<?php
declare(strict_types=1);

enum Colour: string {
    case Red   = 'red';
    case Green = 'green';
    case Blue  = 'blue';

    public function hex(): string {
        return match($this) {
            self::Red   => '#FF0000',
            self::Green => '#00FF00',
            self::Blue  => '#0000FF',
        };
    }
}

$c = Colour::from('green');
echo $c->name  . "\n";
echo $c->value . "\n";
echo $c->hex() . "\n";
echo ($c === Colour::Green ? 'same' : 'different') . "\n";
```

*Your answer:*

---

**Q19.** What will the following code output? Write the output exactly, or write "Fatal error / ValueError" and explain why.

```php
<?php
declare(strict_types=1);

enum Level: int {
    case Bronze = 1;
    case Silver = 2;
    case Gold   = 3;
}

$levels = [Level::Gold, Level::Bronze, Level::Silver, Level::Gold, Level::Bronze];

$counts = [];
foreach ($levels as $level) {
    $counts[$level->name] = ($counts[$level->name] ?? 0) + 1;
}

arsort($counts);

foreach ($counts as $name => $count) {
    echo "{$name}: {$count}\n";
}

$top = Level::from(array_key_first($counts));
echo "Most frequent level value: " . $top->value . "\n";
```

*Your answer:*

---

**Q20.** What will the following code output? Write the output exactly, or write "Fatal error / ValueError" and explain why.

```php
<?php
declare(strict_types=1);

interface Labelled {
    public function label(): string;
}

enum Direction implements Labelled {
    case North;
    case South;
    case East;
    case West;

    public function label(): string {
        return match($this) {
            self::North => 'North ↑',
            self::South => 'South ↓',
            self::East  => 'East →',
            self::West  => 'West ←',
        };
    }

    public function opposite(): self {
        return match($this) {
            self::North => self::South,
            self::South => self::North,
            self::East  => self::West,
            self::West  => self::East,
        };
    }
}

function navigate(Labelled $item): void {
    echo $item->label() . "\n";
}

navigate(Direction::North);
navigate(Direction::East);

$dir = Direction::West;
echo $dir->opposite()->label() . "\n";
echo ($dir->opposite() === Direction::East ? 'correct' : 'wrong') . "\n";
echo var_export($dir instanceof Labelled, true) . "\n";
```

*Your answer:*

---

---

# ✅ Answer Key
*(Scroll only after completing all questions)*

&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;

---

## Section A
| Q | Answer | Explanation |
|---|--------|-------------|
| 1 | **C** | A pure enum case has no backing value — just the name. Options A and B have backing values (making them backed enum cases). Option D is a constant, not a case. |
| 2 | **C** | This one catches almost everyone, including people who have used enums for years. A pure enum case has no `value` — but reading a property that does not exist on an object is not fatal in PHP. You get *"Warning: Undefined property: Color::$value"* and the expression evaluates to `null`. Option D is the answer most people give, and it is the more *useful* behaviour, which is probably why the misconception is so durable — but PHP does not throw here. The practical lesson is that this mistake fails quietly: `null` flows onward and surfaces somewhere else entirely. `->name` is available on all cases, pure and backed. |
| 3 | **C** | `from()` throws `\ValueError` on an unknown value — use it for trusted data. `tryFrom()` returns `null` on an unknown value — use it for untrusted external input. |
| 4 | **D** | `->name` always returns the PHP case identifier as a string — `"High"`. `->value` would return `10`. Both are available on backed enums. |
| 5 | **A** | Enum cases are `instanceof` both the enum type AND any interface it implements. This is what makes them usable in interface-typed function parameters. |
| 6 | **C** | `match` in PHP throws `\UnhandledMatchError` when no arm matches. This is the runtime enforcement of exhaustiveness — a static analyser catches it before runtime. |
| 7 | **B** | `EnumName::cases()` is the static method that returns all cases. There is no `all()` or `values()` — and PHP's `foreach (Status as ...)` is not valid syntax. |
| 8 | **A** | Enums cannot use `extends` — they cannot be extended or extend other classes. They CAN implement interfaces, contain static methods, and be used as type hints. |

## Section B
| # | Answer | Explanation |
|---|--------|-------------|
| 9  | **F** | All backing values in a backed enum must be unique. Duplicate values cause a fatal compile error. |
| 10 | **F** | `->name` returns the case *identifier* — `"Active"`, not the backing value `"active"`. |
| 11 | **T** | Each enum case is a singleton. Every reference to `Status::Active` is the exact same object — identity comparison `===` always returns `true` for the same case. |
| 12 | **F** | Enums cannot have instance properties beyond the built-in `name` and `value`. They cannot have a constructor that sets properties. |
| 13 | **T** | `cases()` is available on all enums — pure and backed. It returns an array of all case objects. |
| 14 | **T** | When all enum cases are covered in a `match`, no `default` is needed. If you add a new case later and forget to update the `match`, PHP throws `UnhandledMatchError` at runtime. |

## Section C

**Q15 — Model answer:**
Use `from()` when the value comes from a trusted internal source (your own database, serialised config, or internal system) — if the value is invalid, it represents a programming bug and crashing with `ValueError` is appropriate. Use `tryFrom()` when the value comes from an external or untrusted source (user input, API payload, form data) — invalid values are expected user errors, so returning `null` and handling it gracefully is the correct approach.

**Q16 — Model answer:**
`match` with an enum is exhaustive — if you add a new enum case and forget to handle it in a `match`, PHP throws `UnhandledMatchError` at runtime (and static analysers like PHPStan catch it at analysis time). With `if`/`elseif` chains on strings, adding a new status silently falls through to the `else` branch without any error — the bug is invisible until a wrong result is noticed downstream.

**Q17 — Model answer:**
Define `enum UserStatus: string { case Active = 'active'; case Inactive = 'inactive'; case Banned = 'banned'; }`. Then replace the validation block with: `$status = UserStatus::tryFrom($_POST['status'] ?? ''); if ($status === null) { throw new \InvalidArgumentException("Invalid status."); }`. This is safer — `tryFrom()` automatically checks against all valid backing values without you having to manually maintain the list of valid strings. Adding a new status case automatically expands what `tryFrom()` accepts.

## Section D

**Q18 — Answer:**
```
Green
green
#00FF00
same
```
`Colour::from('green')` returns `Colour::Green`. `->name` is `"Green"` (the case identifier). `->value` is `"green"` (the backing string). `hex()` uses `match($this)` which returns `"#00FF00"` for `self::Green`. Identity check `$c === Colour::Green` is `true` because enum cases are singletons.

**Q19 — Answer:**
```
Gold: 2
Bronze: 2
Silver: 1
```
…and then a fatal `TypeError`:

```
Level::from(): Argument #1 ($value) must be of type int, string given
```

The counting and sorting work fine. `$counts` is keyed by `$level->name`, so its
keys are the strings `"Gold"`, `"Bronze"`, `"Silver"`. PHP sorts are stable, so
after `arsort()` the two entries tied on 2 keep their insertion order and `Gold`
comes first.

The failure is the last two lines. `array_key_first($counts)` returns the string
`"Gold"`, but `Level` is an **int-backed** enum, so `Level::from()` requires an
`int`. Under `declare(strict_types=1)` there is no coercion and it throws.

**The lesson:** an enum case has both a `name` (the identifier) and a `value`
(the backing value), and they are not interchangeable. `from()` and `tryFrom()`
work on the *value*. To go the other way — from a name back to a case — you need
`constant(Level::class . '::' . $name)` or a `match` over the names.

**Q20 — Answer:**
```
North ↑
East →
East →
correct
true
```
`navigate(Direction::North)` — `Direction` implements `Labelled`, so it satisfies the type hint. Prints `"North ↑"`. `navigate(Direction::East)` prints `"East →"`. `$dir = Direction::West`. `$dir->opposite()` returns `Direction::East`. `Direction::East->label()` is `"East →"`. `Direction::East === Direction::East` is `true` (singleton) → `"correct"`. `$dir instanceof Labelled` is `true` because `Direction implements Labelled` → `var_export` prints `true`.

---

## Score Guide

| Score | Verdict |
|-------|---------|
| 18–20 | Ready for Lesson 2.4 — strong enum mastery. |
| 14–17 | Re-read the README sections for any missed questions, then move on. |
| Below 14 | Re-run the examples, redo the challenge, then retake the quiz before continuing. |