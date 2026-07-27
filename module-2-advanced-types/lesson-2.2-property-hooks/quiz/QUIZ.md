# Quiz — Lesson 2.2: PHP 8.4 Property Hooks
> PHP 8.5. Complete this quiz **without** looking at any example or solution files.
> Write your answers before checking the answer key at the bottom.

---

## Section A — Multiple Choice

**Q1.** Which of the following correctly defines a **virtual** property in PHP 8.4?

- A) `public string $name = '' { get => strtoupper($this->name); }`
- B) `public string $name { get => strtoupper($this->firstName . ' ' . $this->lastName); }`
- C) `public string $name = 'default' { set(string $v) => trim($v); }`
- D) `public string $name { set(string $v) => trim($v); }`

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
- D) A class with `public string $title { set(string $v) => trim($v); }` only.

---

**Q6.** What does the following property declaration mean?

```php
public ?\DateTimeImmutable $publishedAt = null {
    set(string|\DateTimeImmutable|null $value) { ... }
}
```

- A) The property stores a string, a DateTimeImmutable or null — all three are valid stored values.
- B) The set hook accepts a string, a DateTimeImmutable or null as input, but the property always stores a `?\DateTimeImmutable`. The hook must convert strings before storing.
- C) This is a syntax error — the set hook type must match the property type exactly.
- D) Dropping `|null` from the hook's parameter type would make no difference, since the property is already nullable.

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

**Q16.** A colleague writes this and reports that the file will not even run:

```php
class Circle {
    public float $radius = 0.0;
    public float $area   = 0.0 {
        get => M_PI * $this->radius ** 2;
    }
}
```

Explain why PHP rejects this declaration and how to fix it.

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
        set(string $v) => strtoupper(trim($v));
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
| 1 | **B** | A property is **virtual** when *neither* of its hooks references the property itself (`$this->name`). B's `get` hook reads `$this->firstName`/`$this->lastName`, never `$this->name` — so nothing is stored and the value is derived on every read. A has a default value (only backed properties may have one). C and D use a short-form `set`, which implies a backing store to write into. |
| 2 | **B** | The `get` hook runs every time the property is read — `$obj->prop` triggers it. It is not cached unless you implement caching explicitly inside the hook. |
| 3 | **C** | If only a `set` hook is defined, PHP provides a default `get` behaviour that returns the raw stored value. No error occurs. |
| 4 | **C** | A virtual property with only a `get` hook has no `set` operation at all, so assigning to it throws an `Error` at runtime. (A is fine — a backed property with only a `set` hook keeps the default read behaviour. B is fine for the same reason in reverse. D is ordinary and valid.) |
| 5 | **B** | A plain `public string $title = '';` is naturally readable — it satisfies `{ get; }`. Option A uses a private property (not readable by callers). Option C uses protected (not readable from outside). Option D provides only a `set` hook — not readable. |
| 6 | **B** | The property's declared type is `?\DateTimeImmutable` (i.e. `DateTimeImmutable\|null`). The `set` hook's parameter type must be the same or **wider**, so it must still accept `null` — hence `string\|\DateTimeImmutable\|null`. The hook converts strings before storing; the stored value is always `?\DateTimeImmutable`. **D is the trap:** dropping `\|null` *narrows* the type and is a fatal error — *"Type of parameter $value of hook ...::set must be compatible with property type"*. Nullable property, nullable hook parameter. |
| 7 | **B** | An abstract class can have concrete property hooks, which subclasses inherit just like concrete methods. Abstract hook declarations are also possible, forcing subclasses to provide them. |
| 8 | **B** | The `get` hook references `$this->width` and `$this->height` but never `$this->area`, so `$area` is virtual — no storage, recomputed on every read, never cached. With no `set` hook there is no write operation, so assigning to it throws an `Error`. |

## Section B
| # | Answer | Explanation |
|---|--------|-------------|
| 9  | **T** | Block `get { ... }` requires `return`. Arrow `get => expr` implicitly returns the expression. |
| 10 | **F** | Backwards on both counts. A virtual property *may* have a `set` hook (the manual explicitly allows defining both `get` and `set` on a virtual property), but it may **never** have a default value — there is no backing store to hold one, and PHP rejects the declaration at compile time. |
| 11 | **F** | Property hooks cannot be `static` — they always operate on instance properties via `$this`. |
| 12 | **T** | `{ get; set; }` in an interface means callers can both read and write the property. |
| 13 | **T** | A plain `public` property is naturally readable and writable, satisfying both `{ get; }` and `{ set; }` requirements. |
| 14 | **F** | The `set` hook parameter type may be **wider** (contravariant) than the property's type — that is how you accept several input shapes while storing one. But wider means *strictly a superset*: a `?\DateTimeImmutable` property needs `string\|\DateTimeImmutable\|null`, not `string\|\DateTimeImmutable`. Forgetting `\|null` on a nullable property narrows the type and is a fatal error. |

## Section C

**Q15 — Model answer:**
A property is virtual when neither its `get` nor its `set` hook refers to the property itself (`$this->propName`) — PHP then allocates no storage for it and the value is derived on every read instead of being held in memory. Assignment fails when the virtual property defines only a `get` hook: with no backing store and no `set` operation, there is nowhere for the assigned value to go, so PHP raises an `Error` rather than silently discarding it. (A virtual property that *does* define a `set` hook can be assigned to — the hook decides what to do with the value.)

**Q16 — Model answer:**
`$area` is a **virtual** property: neither of its hooks references `$this->area`, so PHP allocates no backing storage for it. A virtual property cannot have a default value, because there is nowhere to store one. PHP therefore rejects the declaration at compile time with *"Cannot specify default value for virtual hooked property Circle::$area"* — the file never runs.

The fix is to drop the default:

```php
class Circle {
    public float $radius = 0.0;
    public float $area {
        get => M_PI * $this->radius ** 2;
    }
}
```

Now `$area` is virtual and recomputed on every read: after `$c->radius = 5.0`, reading `$c->area` gives ≈ 78.54.

**Q17 — Model answer:**
Use **asymmetric visibility** (PHP 8.4), which is the feature designed for exactly this. It composes with hooks:

```php
class Account {
    public private(set) string $email = '' {
        set(string $value) => strtolower(trim($value));   // still normalises on write
    }

    public function changeEmail(string $new): void {
        $this->email = $new;    // ✅ write from inside the class
    }
}

$a = new Account();
echo $a->email;                 // ✅ read from anywhere
$a->email = 'x@y.com';          // ❌ Error — cannot modify private(set) property
$a->changeEmail('X@Y.com');     // ✅ the supported route
```

`public private(set)` makes the property publicly readable but privately writable. The `set` hook still runs on every internal write, so validation and normalisation are preserved. This is the pattern the PHP manual recommends: *"If there is a need to restrict access to a `get` or `set` operation in addition to altering its behavior, use asymmetric property visibility."*

Note that `readonly` is **not** an alternative here — `readonly` properties are incompatible with property hooks, and `readonly` allows only one write ever, whereas this property must stay updatable from inside the class.

## Section D

**Q18 — Answer:**
```
100
212
Error: Property Temperature::$fahrenheit is read-only
```
`$celsius` is backed (its `set` hook writes `$this->celsius`) and the hook validates on write; `100.0` passes and echoes as `100`. `$fahrenheit` is virtual — its `get` hook reads `$this->celsius`, never `$this->fahrenheit` — so reading it computes `100 * 9/5 + 32 = 212.0`, which echoes as `212`. Assigning to it throws an `Error`, because a virtual property with no `set` hook has no write operation. Mark yourself correct if you predicted the two numbers and an `Error` on the write — the exact wording is PHP's, not something to memorise.

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