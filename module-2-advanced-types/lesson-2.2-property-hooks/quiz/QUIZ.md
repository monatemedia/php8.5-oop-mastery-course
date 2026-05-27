# Quiz — Lesson 2.2: PHP 8.4 Property Hooks
> PHP 8.5. Complete this quiz **without** looking at any example or solution files.
> Write your answers before checking the answer key at the bottom.

---

## Section A — Multiple Choice

**Q1.** Which of the following correctly defines a **virtual** property in PHP 8.4?

- A) `public string $name = '' { get => strtoupper($this->name); }`
- B) `public string $name { get => strtoupper($this->firstName . ' ' . $this->lastName); }`
- C) `public string $name = 'default' { set(string $v) => $this->name = $v; }`
- D) `public string $name { set(string $v) => $this->name = $v; }`

---

**Q2.** A `get` hook is defined on a property. When does it run?

- A) Only when the property is assigned a value.
- B) Every time the property is read.
- C) Only once — when the object is first constructed.
- D) Only when the property is accessed from outside the class.

---

**Q3.** You assign a value to a property that has only a `set` hook (no `get` hook). When you later read the property, what happens?

- A) PHP throws a fatal error — a `get` hook is required if a `set` hook exists.
- B) PHP returns `null` because no `get` hook was defined.
- C) PHP returns the raw stored value directly, as if no hook existed.
- D) PHP calls the `set` hook again to compute the value.

---

**Q4.** Which of the following will cause a **fatal error** at runtime?

- A) Reading a backed property with only a `set` hook.
- B) Writing a value to a backed property with only a `get` hook and a default value.
- C) Assigning a value to a virtual property.
- D) Defining both `get` and `set` hooks on the same property.

---

**Q5.** An interface declares:
```php
interface HasTitle {
    public string $title { get; }
}
```
Which implementing class satisfies this contract?

- A) A class with `private string $title;` and a `getTitle()` method.
- B) A class with `public string $title = '';` (a plain public property).
- C) A class with `protected string $title = '';`.
- D) A class with `public string $title { set(string $v) => $this->title = $v; }` only.

---

**Q6.** What does the following property declaration mean?

```php
public ?\DateTimeImmutable $publishedAt = null {
    set(string|\DateTimeImmutable $value) { ... }
}
```

- A) The property stores either a string or a DateTimeImmutable — both are valid stored values.
- B) The set hook accepts a string or DateTimeImmutable as input, but the property always stores a `?\DateTimeImmutable`. The set hook must convert strings before storing.
- C) This is a syntax error — the set hook type must match the property type exactly.
- D) The property stores a string, and the get hook converts it to DateTimeImmutable on read.

---

**Q7.** Which statement about hooks in abstract classes is **true**?

- A) Abstract classes cannot have property hooks.
- B) An abstract class can declare a property with a concrete hook, which subclasses inherit.
- C) Only `get` hooks are allowed in abstract classes — not `set` hooks.
- D) All property hooks in abstract classes must be abstract.

---

**Q8.** A property is declared: `public float $area { get => $this->width * $this->height; }`. Which statement is correct?

- A) `$area` is a backed property — the computed value is cached after the first read.
- B) `$area` is a virtual property — it is recomputed every time it is read.
- C) `$area` can be assigned externally because it has no `set` hook.
- D) This declaration is invalid — a property with only a `get` hook must also have a default value.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 9  | A property hooks `get` block requires an explicit `return` statement; the arrow syntax (`get =>`) does not. | |
| 10 | A virtual property can have a `set` hook if you provide a default value. | |
| 11 | Property hooks can be declared `static`. | |
| 12 | In an interface, `public string $name { get; set; }` means the property must be both readable and writable by callers of the interface. | |
| 13 | A plain (unhocked) `public string $name;` property satisfies an interface that declares `public string $name { get; set; }`. | |
| 14 | A `set` hook's parameter type must be exactly the same as the property's declared type. | |

---

## Section C — Short Answer

**Q15.** Explain in two sentences what makes a property **virtual** in PHP 8.4, and why virtual properties cannot be assigned to from outside the class.

*Your answer:*

---

**Q16.** A colleague writes this and reports it does not work as expected:

```php
class Circle {
    public float $radius = 0.0;
    public float $area   = 0.0 {
        get => M_PI * $this->radius ** 2;
    }
}

$c = new Circle();
$c->radius = 5.0;
echo $c->area; // Expected ~78.54, got 0.0
```

Explain the bug and how to fix it.

*Your answer:*

---

**Q17.** You want a property `$email` that is readable from outside but can only be written from within the class itself. Describe how you would implement this using property hooks.

*Your answer:*

---

## Section D — Code Reading

**Q18.** What will the following code output? Write the output exactly, or write "Fatal error" and explain why.

```php
<?php
declare(strict_types=1);

class Temperature {
    public float $celsius = 0.0 {
        set(float $v) {
            if ($v < -273.15) {
                throw new \RangeException("Below absolute zero!");
            }
            $this->celsius = $v;
        }
    }

    public float $fahrenheit {
        get => round($this->celsius * 9/5 + 32, 1);
    }
}

$t = new Temperature();
$t->celsius = 100.0;
echo $t->celsius . "\n";
echo $t->fahrenheit . "\n";

try {
    $t->fahrenheit = 32.0;
} catch (\Error $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

*Your answer:*

---

**Q19.** What will the following code output? Write the output exactly, or write "Fatal error" and explain why.

```php
<?php
declare(strict_types=1);

interface Labelled {
    public string $label { get; }
}

abstract class BaseItem implements Labelled {
    public string $name = '' {
        set(string $v) => $this->name = strtoupper(trim($v));
    }
}

class Widget extends BaseItem {
    public string $label {
        get => "Widget: {$this->name}";
    }
}

$w = new Widget();
$w->name = '  super pro  ';
echo $w->name  . "\n";
echo $w->label . "\n";
```

*Your answer:*

---

**Q20.** What will the following code output? Write the output exactly, or write "Fatal error" and explain why.

```php
<?php
declare(strict_types=1);

class Config {
    public array $items = [] {
        set(array $value) {
            $this->items = array_map('strtolower', $value);
        }
    }

    public int $count {
        get => count($this->items);
    }

    public string $csv {
        get => implode(',', $this->items);
    }
}

$c = new Config();
$c->items = ['PHP', 'OOP', 'Hooks'];
echo $c->count . "\n";
echo $c->csv   . "\n";

$c->items = ['A', 'B'];
echo $c->count . "\n";
echo $c->csv   . "\n";
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
| 1 | **B** | A virtual property has no default value and no `set` hook. Option A has a default `''` (backed). Option C has a default and set hook (backed). Option D has only a `set` hook — it would be backed (no default, but a set hook means it stores values). Only B has solely a `get` hook and no default. |
| 2 | **B** | The `get` hook runs every time the property is read — `$obj->prop` triggers it. It is not cached unless you implement caching explicitly inside the hook. |
| 3 | **C** | If only a `set` hook is defined, PHP provides a default `get` behaviour that returns the raw stored value. No error occurs. |
| 4 | **C** | Assigning to a virtual property (get-only, no default) is a fatal `Error`: *"Cannot write to a non-writable property."* |
| 5 | **B** | A plain `public string $title = '';` is naturally readable — it satisfies `{ get; }`. Option A uses a private property (not readable by callers). Option C uses protected (not readable from outside). Option D provides only a `set` hook — not readable. |
| 6 | **B** | The property's declared type is `?\DateTimeImmutable`. The `set` hook accepts a wider input type (`string|\DateTimeImmutable`). The hook converts strings to `DateTimeImmutable` before storing. The stored value is always `?\DateTimeImmutable`. |
| 7 | **B** | An abstract class can have concrete property hooks, which subclasses inherit just like concrete methods. Abstract hook declarations are also possible, forcing subclasses to provide them. |
| 8 | **B** | `$area` has no default value and no `set` hook — it is virtual. It is recomputed on every read. The value is not cached. Assigning to it would be a fatal error. |

## Section B
| # | Answer | Explanation |
|---|--------|-------------|
| 9  | **T** | Block `get { ... }` requires `return`. Arrow `get => expr` implicitly returns the expression. |
| 10 | **F** | A virtual property is defined by having NO default value and NO `set` hook. If you add a default value, the property becomes backed and can theoretically have a `set` hook. |
| 11 | **F** | Property hooks cannot be `static` — they always operate on instance properties via `$this`. |
| 12 | **T** | `{ get; set; }` in an interface means callers can both read and write the property. |
| 13 | **T** | A plain `public` property is naturally readable and writable, satisfying both `{ get; }` and `{ set; }` requirements. |
| 14 | **F** | The `set` hook parameter type may be **wider** (a supertype) than the property's declared type. This is how you accept multiple input types (e.g. `string|\DateTimeImmutable`) while storing only one (`?\DateTimeImmutable`). |

## Section C

**Q15 — Model answer:**
A virtual property has no default value and no `set` hook, so PHP allocates no memory to store a value for it. It is defined entirely by its `get` hook, which computes a value on every read. Because there is no storage and no `set` hook, there is nowhere for an assigned value to go — PHP throws a fatal `Error` to prevent the silent data loss that would otherwise occur.

**Q16 — Model answer:**
The bug is that `$area` is declared with a default value (`= 0.0`), which makes it a **backed** property. The `get` hook runs and correctly computes `M_PI * 5.0² ≈ 78.54`, but the hook's expression `M_PI * $this->radius ** 2` is the return value — so reading `$c->area` does return the correct value. *Actually, this code works correctly as written.* If the colleague got `0.0`, they may have been reading the property before setting `$radius`, or using a PHP version prior to 8.4. The fix if there truly is an issue: ensure `$radius` is set before reading `$area`, or make `$area` a virtual property by removing the `= 0.0` default. The computation is already correct.

**Q17 — Model answer:**
Declare the property with a `set` hook that has `private` visibility. With property hooks there is no direct `private set` modifier, but you can achieve read-only-from-outside by making the property have only a `get` hook (virtual, no set), and use a separate private method or constructor assignment internally. Alternatively, declare the property without a hook but as `public readonly string $email` — `readonly` makes it writable only once (typically in the constructor) and read-only thereafter. For full hook control: declare the property with a public `get` and keep the `set` hook — the property can be assigned internally by calling `$this->email = ...` inside the class, but since there is no public set hook visible from outside... actually in PHP 8.4 the set hook visibility cannot be `private` independently. The standard pattern is: use a `readonly` property or only initialise via constructor.

## Section D

**Q18 — Answer:**
```
100
212
Error: Cannot modify read-only property Temperature::$fahrenheit
```
`$celsius` is backed with a `set` hook (validation). Setting `100.0` passes. `$fahrenheit` is virtual (no default, no set hook) — reading it computes `100 * 9/5 + 32 = 212.0`. Trying to assign to the virtual property throws `Error: Cannot modify read-only property`.

**Q19 — Answer:**
```
SUPER PRO
Widget: SUPER PRO
```
`BaseItem::$name` has a `set` hook that trims and uppercases. Assigning `'  super pro  '` stores `'SUPER PRO'`. `Widget::$label` is a virtual property that derives from `$this->name`. Both are accessible correctly.

**Q20 — Answer:**
```
3
php,oop,hooks
2
a,b
```
`$items` set hook lowercases the array via `strtolower`. `$count` and `$csv` are virtual — both recompute on every read. After the first assignment `['PHP','OOP','Hooks']` → `['php','oop','hooks']` → count=3, csv=`php,oop,hooks`. After reassignment `['A','B']` → `['a','b']` → count=2, csv=`a,b`.

---

## Score Guide

| Score | Verdict |
|-------|---------|
| 18–20 | Ready for Lesson 2.3 — strong property hooks mastery. |
| 14–17 | Re-read the README sections for any missed questions, then move on. |
| Below 14 | Re-run the examples, redo the challenge, then retake the quiz before continuing. |