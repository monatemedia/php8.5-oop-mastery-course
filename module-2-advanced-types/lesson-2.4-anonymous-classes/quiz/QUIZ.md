# Quiz — Lesson 2.4: Anonymous Classes
> **Module 2 capstone quiz** — questions draw on anonymous classes AND earlier Module 2 topics.
> Complete this quiz **without** looking at any example or solution files.
> Write your answers before checking the answer key at the bottom.

---

## Section A — Multiple Choice

**Q1.** Which of the following correctly creates an anonymous class that implements `Logger`?

- A) `$obj = new Logger { public function log(string $m): void {} };`
- B) `$obj = new class Logger { public function log(string $m): void {} };`
- C) `$obj = new class implements Logger { public function log(string $m): void {} };`
- D) `$obj = (new class)->implements(Logger::class);`

---

**Q2.** How do you pass a value from the outer scope into an anonymous class?

- A) `new class use ($value) { ... }` — same syntax as closures.
- B) `new class { public function __construct() { global $value; } }` — global keyword.
- C) `new class($value) { public function __construct(private mixed $v) {} }` — constructor argument.
- D) You cannot — anonymous classes have no access to outer scope values.

---

**Q3.** You have:
```php
interface Notifier { public function notify(string $msg): void; }

$n = new class implements Notifier {
    public function notify(string $msg): void { echo $msg; }
};
```
Which of the following is **true**?

- A) `$n instanceof Notifier` is `false` — anonymous classes cannot be `instanceof` checked.
- B) `$n instanceof Notifier` is `true`.
- C) The code is invalid — anonymous classes cannot implement interfaces.
- D) `get_class($n)` returns `"Notifier"`.

---

**Q4.** An anonymous class extends `BaseProcessor` and the parent has a constructor that takes `string $name`. Which syntax correctly calls the parent constructor?

- A) `new class('Alice') extends BaseProcessor { }` — constructor args automatically forwarded.
- B) `new class('Alice') extends BaseProcessor { public function __construct(string $n) { parent::__construct($n); } }`
- C) `new class extends BaseProcessor('Alice') { }`
- D) `new class extends BaseProcessor { public function __construct() { parent::__construct('Alice'); } }`

---

**Q5.** When is an **anonymous class** more appropriate than a **closure**?

- A) When you need to capture outer scope variables.
- B) When you need a single callable with no state.
- C) When you need multiple methods or to maintain state across method calls.
- D) When the logic needs to be reused in more than one place.

---

**Q6.** Two different `new class {}` expressions in the same file — are they the same class?

- A) Yes — PHP reuses the same internal class for identical anonymous class definitions.
- B) No — each `new class` expression generates a distinct internal class name.
- C) Yes — as long as the class bodies are identical.
- D) It depends on whether they implement the same interface.

---

**Q7.** You have a function typed `function process(PaymentGateway $gw): void`. Which can be passed?

- A) Only named classes that implement `PaymentGateway`.
- B) Only named classes — anonymous classes cannot be used as type arguments.
- C) Any object that implements `PaymentGateway`, including anonymous class instances.
- D) Any object — PHP ignores the type hint for anonymous class instances.

---

**Q8.** Which statement about anonymous classes and serialisation is **true**?

- A) Anonymous class instances serialise identically to named class instances.
- B) Anonymous class instances cannot be reliably serialised with `serialize()`.
- C) Anonymous class instances must implement `Serializable` before they can be serialised.
- D) PHP automatically converts anonymous class instances to `stdClass` before serialising.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 9  | An anonymous class can use traits with `use MyTrait;` inside the class body. | |
| 10 | `get_class($anon)` returns `null` for anonymous class instances. | |
| 11 | An anonymous class instance is `instanceof` its parent class if it extends one. | |
| 12 | You can define static methods inside an anonymous class. | |
| 13 | An anonymous class defined inside a function is re-created (new class definition) on every function call. | |
| 14 | A named class is always preferable to an anonymous class because anonymous classes are harder to debug. | |

---

## Section C — Short Answer

**Q15.** Explain in two sentences why anonymous classes are particularly well-suited for test doubles (stubs and spies), compared to creating a dedicated named class file for each double.

*Your answer:*

---

**Q16.** A colleague writes this and says it is equivalent to a closure:
```php
$double = new class($factor) {
    public function __construct(private float $factor) {}
    public function run(float $n): float { return $n * $this->factor; }
};
```
Is this equivalent to `$double = fn(float $n): float => $n * $factor;`? Explain the key differences.

*Your answer:*

---

**Q17.** Describe the **Null Object pattern** as implemented with an anonymous class. When would you use it, and what advantage does it have over passing `null` and checking for it everywhere?

*Your answer:*

---

## Section D — Code Reading

**Q18.** What will the following code output? Write the output exactly, or write "Fatal error / TypeError" and explain why.

```php
<?php
declare(strict_types=1);

interface Greeter {
    public function greet(string $name): string;
}

function makeGreeter(string $prefix): Greeter {
    return new class($prefix) implements Greeter {
        public function __construct(private string $prefix) {}
        public function greet(string $name): string {
            return "{$this->prefix}, {$name}!";
        }
    };
}

$formal  = makeGreeter('Good day');
$casual  = makeGreeter('Hey');

echo $formal->greet('Alice') . "\n";
echo $casual->greet('Bob')   . "\n";
echo var_export($formal instanceof Greeter, true) . "\n";
echo (get_class($formal) === get_class($casual) ? 'same class' : 'different class') . "\n";
```

*Your answer:*

---

**Q19.** What will the following code output? Write the output exactly, or write "Fatal error / TypeError" and explain why.

```php
<?php
declare(strict_types=1);

abstract class BaseCounter {
    protected int $count = 0;
    abstract public function label(): string;

    public function increment(): static {
        $this->count++;
        return $this;
    }

    public function report(): string {
        return $this->label() . ": {$this->count}";
    }
}

$counter = new class extends BaseCounter {
    public function label(): string { return "Widget counter"; }
};

echo $counter->increment()->increment()->increment()->report() . "\n";
echo var_export($counter instanceof BaseCounter, true) . "\n";
```

*Your answer:*

---

**Q20.** This question combines anonymous classes with enums and interfaces from Module 2. What will the following code output?

```php
<?php
declare(strict_types=1);

enum Status: string {
    case Active   = 'active';
    case Inactive = 'inactive';
}

interface StatusProvider {
    public function getStatus(): Status;
}

function describeProvider(StatusProvider $provider): string {
    return match($provider->getStatus()) {
        Status::Active   => "Provider is ACTIVE",
        Status::Inactive => "Provider is INACTIVE",
    };
}

$active = new class implements StatusProvider {
    public function getStatus(): Status { return Status::Active; }
};

$inactive = new class implements StatusProvider {
    public function getStatus(): Status { return Status::Inactive; }
};

echo describeProvider($active)   . "\n";
echo describeProvider($inactive) . "\n";
echo var_export($active instanceof StatusProvider, true) . "\n";
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
| 1 | **C** | `new class implements Logger { ... }` is the correct syntax. Option A tries to use the interface name as a class name. Option B is invalid syntax. Option D does not exist. |
| 2 | **C** | Constructor arguments are passed between `class` and `{`. Anonymous classes have no `use` syntax — that is closures only. |
| 3 | **B** | `instanceof` works normally against interfaces for anonymous class instances — the class implements `Notifier`, so `$n instanceof Notifier` is `true`. `get_class()` returns the internal generated name, not `"Notifier"`. |
| 4 | **B** | Syntax B is correct. Constructor args are passed in `new class('Alice')`, and the anonymous class body declares its own constructor that calls `parent::__construct()`. Option A does not auto-forward args. Option C is invalid syntax. |
| 5 | **C** | Anonymous classes are the right choice when you need multiple methods or state across calls. Closures are for single callables. Option D (reuse) calls for a named class. |
| 6 | **B** | Each `new class {}` expression generates a distinct internal class name — even if the bodies are identical. They are never the same class. |
| 7 | **C** | Type hints check the interface — any object implementing `PaymentGateway` qualifies, whether it is a named class or an anonymous class instance. |
| 8 | **B** | Anonymous class instances cannot be reliably serialised. The internal class name contains the file path and line number — deserialising in a different context will fail because the class cannot be found. |

## Section B
| # | Answer | Explanation |
|---|--------|-------------|
| 9  | **T** | Traits work inside anonymous classes exactly as in named classes: `use MyTrait;` inside the class body. |
| 10 | **F** | `get_class()` returns a string — the internal generated name (e.g. `class@anonymous...`). It never returns `null`. |
| 11 | **T** | `instanceof` checks inheritance — an anonymous class extending `Base` is `instanceof Base`. |
| 12 | **T** | Static methods, static properties, and constants all work inside anonymous classes. |
| 13 | **F** | PHP creates the class definition once at compile time. Each function call creates a new **instance**, but the class definition is not redefined on every call. |
| 14 | **F** | This is a matter of context, not an absolute rule. Anonymous classes are perfectly appropriate for test doubles, null objects, and one-off inline implementations. The decision guide in the README makes this clear. |

## Section C

**Q15 — Model answer:**
Anonymous classes defined inside a test function live entirely within that function's scope — there is no separate file to maintain, no class name to keep globally unique, and no risk of the test double being accidentally reused or depending on shared state. Each test gets a fresh, explicitly configured stub with exactly the behaviour it needs, making the test self-contained and easy to read: the stub definition is right next to the assertions that rely on it.

**Q16 — Model answer:**
They are not equivalent. The closure `fn(float $n): float => $n * $factor` is a single callable — it has one operation and captures `$factor` via `use` (arrow functions capture automatically). The anonymous class has a named method `run()` that must be called explicitly: `$double->run(5.0)`. The closure is called directly: `$double(5.0)`. The anonymous class is more appropriate if you later need to add a second method (e.g. `reset()`) or hold additional state. The closure is the cleaner choice for a single transformation operation.

**Q17 — Model answer:**
The Null Object pattern is an anonymous (or named) class that implements an interface but does nothing — all methods have empty bodies or return neutral values. It is used when a dependency is genuinely optional and you do not want `null` checks scattered throughout the code. Rather than checking `if ($logger !== null) $logger->log(...)` everywhere, you inject a `NullLogger` that accepts all calls silently. The code reads the same whether a real logger or a null logger is injected — no defensive checks needed.

## Section D

**Q18 — Answer:**
```
Good day, Alice!
Hey, Bob!
true
different class
```
`makeGreeter` returns an anonymous class instance typed against `Greeter`. Both calls are `instanceof Greeter` (true). `get_class($formal) === get_class($casual)` is **false** because each `new class` expression in the `makeGreeter` function body produces the same class definition — wait, actually: both instances come from the **same** `new class(...)` expression inside `makeGreeter`. Since it is the same expression, PHP reuses the same internal class name. So `get_class($formal) === get_class($casual)` is **true**, and the output is `"same class"`.

**Corrected final line: `same class`**

Full output:
```
Good day, Alice!
Hey, Bob!
true
same class
```

**Q19 — Answer:**
```
Widget counter: 3
true
```
The anonymous class extends `BaseCounter` and provides `label()`. `increment()` returns `static` — which is the anonymous class instance. Three chained `increment()` calls bring `$count` to 3. `report()` calls `$this->label()` (polymorphism) returning `"Widget counter"`. `instanceof BaseCounter` is `true`.

**Q20 — Answer:**
```
Provider is ACTIVE
Provider is INACTIVE
true
```
Both anonymous classes implement `StatusProvider`. `describeProvider()` is typed against the interface — both qualify. The `match` expression is exhaustive over `Status` cases. `$active instanceof StatusProvider` is `true`.

---

## Score Guide

| Score | Verdict |
|-------|---------|
| 18–20 | Module 2 complete — ready for Module 3. |
| 14–17 | Re-read the README sections for any missed questions, then move on. |
| Below 14 | Re-run the examples, redo the challenge, then retake the quiz before continuing. |