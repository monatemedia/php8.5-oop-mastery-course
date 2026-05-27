# Lesson 1.2 — Abstract Classes
> **Module 1: OOP Building Blocks** · PHP 8.5 OOP Mastery Course

---

## 📁 Lesson Folder Structure

```
lesson-1.2-abstract-classes/
├── README.md                              ← Theory (you are here)
│
├── examples/
│   ├── 01-abstract-vs-interface.php       ← Choosing the right tool
│   ├── 02-abstract-methods-and-concrete.php
│   ├── 03-constructor-in-abstract.php
│   ├── 04-combining-with-interfaces.php
│   ├── 05-template-method-pattern.php
│   └── 06-clone-with.php                  ← PHP 8.5
│
├── challenge/
│   ├── CHALLENGE.md
│   ├── starter.php
│   └── solution.php
│
└── quiz/
    └── QUIZ.md
```

**How to use this lesson:**
1. Read this README fully — pay particular attention to Section 2 (the decision table).
2. Run each example in sequence and read the output.
3. Complete the challenge.
4. Take the quiz cold.

---

## 1 — What Is an Abstract Class?

An abstract class sits **between** an interface and a concrete class on the abstraction spectrum.

```
More abstract ◄────────────────────────────────────► More concrete
  Interface       Abstract Class        Concrete Class
  (no code)    (some code, some gaps)    (all code)
```

An abstract class is a class that **cannot be instantiated directly**. It exists solely to be extended. It may contain:
- **Abstract methods** — declared but not implemented (just like interface methods)
- **Concrete methods** — fully implemented, shared by all subclasses
- **Properties** — with values or without
- **A constructor** — which subclasses call via `parent::__construct()`
- **Constants**

```php
abstract class PaymentGateway {
    // Concrete — shared by all gateways
    public function __construct(protected string $apiKey) {}

    // Abstract — each gateway implements this differently
    abstract public function charge(float $amount, string $currency): bool;

    // Concrete — shared logging logic, no need to repeat in every subclass
    protected function log(string $message): void {
        echo "[" . get_class($this) . "] " . $message . "\n";
    }
}
```

Trying to instantiate it directly produces a fatal error:
```php
$gw = new PaymentGateway('key_123'); // Fatal error: Cannot instantiate abstract class
```

---

## 2 — Abstract Class vs Interface — The Decision Table

This is the most important thing to get right in Module 1. Use this table every time you are unsure.

| Question | If YES → use... |
|----------|----------------|
| Do I need to share **implemented code** (methods with bodies) across multiple classes? | Abstract class |
| Do I need to store **shared state** (properties) across subclasses? | Abstract class |
| Do I need a **constructor** that subclasses build on? | Abstract class |
| Am I defining a **capability** that unrelated classes can opt into? | Interface |
| Do I need a class to sign **multiple contracts** simultaneously? | Interface (a class can only extend one abstract class) |
| Do I want to enforce a **method signature only**, with no shared implementation? | Interface |
| Am I modelling a genuine **"is-a"** relationship with shared behaviour? | Abstract class |
| Am I modelling a **"can-do"** capability that multiple unrelated types share? | Interface |

**The rule of thumb in one line:**
> Use an **interface** when you are defining *what* something can do. Use an **abstract class** when you are defining *what* something is and providing *some of how* it works.

### Practical example of the distinction

```
Notification (abstract class)
├── shared: constructor stores $recipient
├── shared: send() method calls prepare() then dispatch()
├── abstract: prepare(): string   ← each subclass formats differently
└── abstract: dispatch(): void    ← each subclass uses a different channel

Schedulable (interface)
├── scheduleFor(DateTimeImmutable $at): void
└── cancel(): void
```

`EmailNotification` extends `Notification` (it IS a notification, shares the send logic)
and implements `Schedulable` (it CAN be scheduled — a capability it opts into).

This is the most common real-world pattern: **one abstract class + one or more interfaces**.

---

## 3 — Defining Abstract Methods

Abstract methods are declared with the `abstract` keyword and have **no body**. The subclass is contractually required to provide an implementation.

```php
abstract class Report {
    // Abstract: subclasses must define how to generate content
    abstract protected function generateContent(): string;

    // Abstract: subclasses must define the output format name
    abstract public function getFormat(): string;

    // Concrete: the rendering pipeline is shared — no need to repeat it
    final public function render(): string {
        $content = $this->generateContent(); // Calls subclass implementation
        $format  = $this->getFormat();
        return "=== {$format} Report ===\n{$content}\n=================";
    }
}
```

**Rules for abstract methods:**
- Must use the `abstract` keyword
- Must have **no body** — no curly braces, just a semicolon
- Can be `public` or `protected` — never `private` (private cannot be overridden)
- The overriding method's signature must be **compatible** (same or covariant return type; same or contravariant parameters)
- If a class has even one abstract method, the class itself must be declared `abstract`

---

## 4 — Concrete Methods in Abstract Classes

This is the key differentiator from interfaces: concrete methods let you share real implementation across all subclasses.

```php
abstract class HttpController {
    // Concrete — every controller does the same auth check
    protected function requireAuth(array $request): void {
        if (empty($request['token'])) {
            throw new \RuntimeException("Unauthenticated request.");
        }
    }

    // Concrete — every controller formats JSON responses the same way
    protected function jsonResponse(array $data, int $status = 200): string {
        http_response_code($status);
        return json_encode(['status' => $status, 'data' => $data]);
    }

    // Abstract — each controller handles its own route logic
    abstract public function handle(array $request): string;
}

class UserController extends HttpController {
    public function handle(array $request): string {
        $this->requireAuth($request); // Inherited — no repetition
        return $this->jsonResponse(['users' => ['Alice', 'Bob']]); // Inherited
    }
}

class ProductController extends HttpController {
    public function handle(array $request): string {
        $this->requireAuth($request); // Same call — DRY
        return $this->jsonResponse(['products' => ['Widget A', 'Widget B']]);
    }
}
```

`requireAuth()` and `jsonResponse()` are written once and shared. If the auth logic changes, you change it in one place.

---

## 5 — Constructor Logic in Abstract Classes

Abstract classes can have constructors. The subclass constructor calls `parent::__construct()` to initialise the shared state before adding its own setup.

```php
abstract class DatabaseModel {
    protected array $attributes = [];
    protected bool  $exists     = false;

    public function __construct(array $attributes = []) {
        $this->attributes = $attributes;
        $this->exists     = isset($attributes['id']);
    }

    public function getAttribute(string $key): mixed {
        return $this->attributes[$key] ?? null;
    }

    abstract public function tableName(): string;

    public function save(): void {
        $table = $this->tableName();
        if ($this->exists) {
            echo "[UPDATE] {$table} id={$this->attributes['id']}\n";
        } else {
            echo "[INSERT] {$table}\n";
        }
    }
}

class UserModel extends DatabaseModel {
    public function __construct(array $attributes = []) {
        parent::__construct($attributes); // ← Always call this first
        // User-specific setup can go here
    }

    public function tableName(): string { return 'users'; }

    public function fullName(): string {
        return $this->getAttribute('first_name') . ' ' . $this->getAttribute('last_name');
    }
}
```

**Rule:** Always call `parent::__construct()` in the subclass constructor unless you have a deliberate reason not to. Skipping it means the shared initialisation in the abstract class never runs.

---

## 6 — Combining Abstract Classes with Interfaces

This is the most powerful pattern and the one you will see most often in mature PHP codebases (including frameworks like Laravel).

```
Interface: defines "can-do" capabilities (contracts without implementation)
Abstract class: defines "is-a" identity with shared implementation
Concrete class: combines both — extends ONE abstract class, implements MANY interfaces
```

```php
// Interfaces — capabilities
interface Loggable {
    public function getLogContext(): array;
}

interface Cacheable {
    public function getCacheKey(): string;
    public function getCacheTtl(): int;
}

// Abstract class — shared implementation for all "query" types
abstract class DatabaseQuery implements Loggable {
    protected float $executionTime = 0.0;

    public function __construct(protected string $connection = 'default') {}

    abstract public function toSql(): string;
    abstract public function getBindings(): array;

    // Shared concrete implementation of Loggable
    public function getLogContext(): array {
        return [
            'sql'      => $this->toSql(),
            'bindings' => $this->getBindings(),
            'time'     => $this->executionTime,
            'conn'     => $this->connection,
        ];
    }

    public function execute(): void {
        $start = microtime(true);
        echo "[SQL] " . $this->toSql() . "\n";
        $this->executionTime = round((microtime(true) - $start) * 1000, 3);
    }
}

// Concrete — extends abstract class AND adds another interface
class SelectQuery extends DatabaseQuery implements Cacheable {
    private array $columns = ['*'];
    private string $table  = '';

    public function from(string $table): static {
        $this->table = $table;
        return $this;
    }

    public function select(string ...$columns): static {
        $this->columns = $columns;
        return $this;
    }

    public function toSql(): string {
        $cols = implode(', ', $this->columns);
        return "SELECT {$cols} FROM {$this->table}";
    }

    public function getBindings(): array { return []; }

    // Cacheable — SelectQuery can be cached, other query types cannot
    public function getCacheKey(): string  { return md5($this->toSql()); }
    public function getCacheTtl(): int     { return 3600; }
}
```

---

## 7 — The `final` Keyword

You can mark a concrete method in an abstract class as `final` to prevent subclasses from overriding it. This is useful when you want to guarantee the shared logic is never accidentally bypassed.

```php
abstract class Authenticator {
    // Subclasses CANNOT override this — the security pipeline must not be changed
    final public function authenticate(array $credentials): bool {
        $this->rateLimit($credentials['ip'] ?? '');
        $result = $this->verify($credentials); // Calls the subclass implementation
        $this->audit($credentials, $result);
        return $result;
    }

    abstract protected function verify(array $credentials): bool;

    private function rateLimit(string $ip): void { /* ... */ }
    private function audit(array $credentials, bool $result): void { /* ... */ }
}
```

This is called the **Template Method Pattern** — the abstract class defines the *skeleton* of an algorithm, and subclasses fill in the *steps*. Example 05 covers this in depth.

---

## 8 — PHP 8.5 — `clone with` for Immutable Copies

PHP 8.5 introduces the `clone with` syntax for producing immutable copies of objects with targeted property changes.

### The problem it solves

Immutable value objects need "wither" methods that return a modified copy. Before PHP 8.5, these methods had to manually list every property in `new static(...)` — verbose and fragile when properties are added or renamed.

```php
// PHP 8.4 — verbose wither method: must list ALL properties
readonly class Money {
    public function __construct(
        public int    $amountCents,
        public string $currency,
        public string $locale = 'en-ZA'
    ) {}

    public function withAmount(int $newAmount): static {
        return new static($newAmount, $this->currency, $this->locale);
        // Add a 4th property → must update every withX() method
    }
}
```

### PHP 8.5: `clone with`

```php
// PHP 8.5 — only the CHANGED property appears in the with array
readonly class Money {
    public function __construct(
        public int    $amountCents,
        public string $currency,
        public string $locale     = 'en-ZA',
        public string $precision  = 'standard' // New property — withers unaffected
    ) {}

    #[\NoDiscard('Returns a new Money instance — the original is unchanged')]
    public function withAmount(int $newAmount): static {
        return clone $this with ['amountCents' => $newAmount];
        // currency, locale, precision all carried over automatically
    }

    #[\NoDiscard('Returns a new Money instance — the original is unchanged')]
    public function withAmountAndCurrency(int $cents, string $currency): static {
        return clone $this with ['amountCents' => $cents, 'currency' => $currency];
    }
}

$price    = new Money(29999, 'ZAR');
$adjusted = $price->withAmount(24999);           // ZAR 249.99
$both     = $price->withAmountAndCurrency(19999, 'EUR'); // EUR 199.99
// $price is unchanged in both cases
```

### `clone with` + PHP 8.4 property hooks

When a property has a `set` hook, the hook runs on the new value during cloning:

```php
class BlogPost {
    public string $title = '' {
        set(string $value) => $this->title = trim($value);
    }
    public string $slug {
        get => strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', trim($this->title)));
    }
}

$post    = new BlogPost();
$post->title = '  Hello PHP World  ';

$updated = clone $post with ['title' => '  PHP 8.5 Is Here  '];
// set hook normalised the new title automatically
// virtual $slug is re-computed from the new title
```

### Rules
- `clone with ['prop' => $value]` — only changed properties need to appear
- All other properties are carried over from the original
- `set` hooks run on cloned values (the new value is validated/transformed)
- Virtual properties (get-only) are **not** in the `with` array — they re-compute on every read
- Works on any class, not just `readonly`
- Pair with `#[\NoDiscard]` to catch silent discard bugs

> **Full runnable example:** `examples/06-clone-with.php`

---

## 9 — Common Mistakes to Avoid

| Mistake | Why it is wrong | Fix |
|---------|----------------|-----|
| Declaring an abstract class when you need zero shared state or implementation | Unnecessary overhead — just use an interface | Switch to an interface |
| Making abstract methods `private` | Private methods cannot be overridden — PHP will throw a fatal error | Use `protected` or `public` |
| Forgetting `parent::__construct()` in a subclass | Shared state in the abstract class never gets initialised | Always call it as the first line |
| Overriding a `final` method | PHP throws a fatal error | Remove `final`, or redesign so the subclass does not need to change that step |
| Treating an abstract class as a substitute for multiple inheritance | PHP supports only single inheritance | Use interfaces for additional contracts |
| Creating an abstract class with zero abstract methods | Legal in PHP, but confusing — why not just use a regular class? | Either add abstract methods or use a regular class |

---

## 10 — Quick Reference

```php
// Define
abstract class MyBase {
    protected string $shared;

    public function __construct(string $value) {
        $this->shared = $value;
    }

    abstract public function doSomething(): string;   // Must be implemented
    abstract protected function helper(): int;         // Must be implemented

    public function sharedMethod(): void {             // Shared — not overridden
        echo $this->doSomething();
    }

    final public function lockedMethod(): void {       // Cannot be overridden
        echo "Fixed behaviour.";
    }
}

// Extend
class MyChild extends MyBase {
    public function __construct(string $value, private int $extra) {
        parent::__construct($value); // Always call parent first
    }

    public function doSomething(): string { return $this->shared . $this->extra; }
    protected function helper(): int      { return $this->extra * 2; }
}

// Extend + implement interfaces
class MyOtherChild extends MyBase implements InterfaceA, InterfaceB {
    public function doSomething(): string { return "other"; }
    protected function helper(): int      { return 0; }
    // Plus all methods from InterfaceA and InterfaceB
}

// Check at runtime
if ($obj instanceof MyBase) { /* ... */ }
```

---

## ✅ Lesson Checklist

- [ ] Read this README fully — especially Section 2 (the decision table)
- [ ] Run and study `examples/01-abstract-vs-interface.php`
- [ ] Run and study `examples/02-abstract-methods-and-concrete.php`
- [ ] Run and study `examples/03-constructor-in-abstract.php`
- [ ] Run and study `examples/04-combining-with-interfaces.php`
- [ ] Run and study `examples/05-template-method-pattern.php`
- [ ] Run and study `examples/06-clone-with.php` *(PHP 8.5)*
- [ ] Read `challenge/CHALLENGE.md` and complete `challenge/starter.php`
- [ ] Check your work against `challenge/solution.php`
- [ ] Complete `quiz/QUIZ.md` without looking at any files

---

*Next lesson: **1.3 — Traits** — horizontal code reuse without inheritance.*