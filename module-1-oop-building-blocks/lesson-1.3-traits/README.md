# Lesson 1.3 — Traits
> **Module 1: OOP Building Blocks** · PHP 8.5 OOP Mastery Course

---

## 📁 Lesson Folder Structure

```
lesson-1.3-traits/
├── README.md                                ← Theory (you are here)
│
├── examples/
│   ├── 01-defining-and-using.php            ← Basic trait usage
│   ├── 02-multiple-traits-and-conflicts.php ← insteadof and as
│   ├── 03-trait-properties-and-abstract.php ← State and enforcement
│   ├── 04-traits-with-interfaces.php        ← The most common real-world pattern
│   ├── 05-choosing-the-right-tool.php       ← Trait vs interface vs abstract class
│   └── 06-deprecated-trait-and-constant.php ← PHP 8.5
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
1. Read this README fully — Section 2 (what traits are NOT) and Section 7 (the comparison table) are the most important parts.
2. Run each example in sequence.
3. Complete the challenge.
4. Take the quiz cold.

---

## 1 — What Is a Trait?

PHP supports **single inheritance** — a class can extend only one parent. But some behaviours (logging, timestamps, soft-deletes, caching) are genuinely useful across many unrelated class hierarchies. Copy-pasting the code violates DRY. Creating a shared parent class forces an artificial "is-a" relationship. Neither is correct.

Traits solve this with **horizontal code reuse** — injecting a block of methods directly into any class that uses it, regardless of where that class sits in the inheritance hierarchy.

```php
trait Timestampable {
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    public function initTimestamps(): void {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function touch(): void {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}

class User {
    use Timestampable; // ← 4 methods injected, no inheritance needed
    public function __construct(public string $name) {
        $this->initTimestamps();
    }
}

class Product {
    use Timestampable; // ← Same 4 methods, no shared parent needed
    public function __construct(public string $sku) {
        $this->initTimestamps();
    }
}
```

`User` and `Product` share `Timestampable` behaviour without being related by inheritance. That is the whole point.

---

## 2 — What Traits Are NOT

Traits are often misunderstood. Being clear about what they are *not* prevents architectural mistakes.

| Trait is NOT... | Why it matters |
|----------------|----------------|
| A type | You cannot type-hint `function f(MyTrait $x)`. Traits are not part of PHP's type system. |
| An interface | A trait provides code; an interface defines a contract. They serve different purposes. |
| A class | You cannot instantiate a trait with `new`. |
| A replacement for inheritance | Traits inject *methods*. Abstract classes provide *identity*, shared state, and a constructor. |
| Always the right choice | See Section 7. Overusing traits leads to hard-to-follow "magic" behaviour. |

**The correct mental model:** A trait is a **named block of code** that PHP pastes into any class that uses it. When you call `use Timestampable`, PHP copies the trait's methods into your class at compile time — as if you had written them there yourself.

---

## 3 — Defining and Using a Trait

```php
// Define
trait HasSlug {
    public function generateSlug(string $title): string {
        return strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $title), '-'));
    }
}

// Use in a class
class BlogPost {
    use HasSlug;

    public function __construct(public string $title) {}

    public function getSlug(): string {
        return $this->generateSlug($this->title);
    }
}

class Category {
    use HasSlug; // Same method, different class, no shared parent needed

    public function __construct(public string $name) {}
}
```

**Rules:**
- Declare with `trait ClassName {}`
- Use inside a class with `use TraitName;`
- A class can use multiple traits: `use TraitA, TraitB, TraitC;`
- Methods injected by a trait behave as if they were written directly in the class
- Trait methods have access to `$this` — they can read and write the class's properties

---

## 4 — Using Multiple Traits and Handling Conflicts

When two traits define a method with the same name, PHP cannot resolve the conflict automatically — you must tell it which one to use.

### The conflict

```php
trait Logger {
    public function log(string $message): void {
        echo "[LOG] {$message}\n";
    }
}

trait Debugger {
    public function log(string $message): void {    // Same method name!
        echo "[DEBUG] {$message}\n";
    }
}

class App {
    use Logger, Debugger; // ← Fatal error: log() conflict
}
```

### Resolution 1 — `insteadof`: choose one implementation over the other

```php
class App {
    use Logger, Debugger {
        Logger::log    insteadof Debugger; // Use Logger's log(), discard Debugger's
        Debugger::log  insteadof Logger;   // ← Would be the opposite choice
    }
}
```

### Resolution 2 — `as`: keep both under different names

```php
class App {
    use Logger, Debugger {
        Logger::log   insteadof Debugger; // Logger's log() wins the 'log' name
        Debugger::log as debugLog;        // Debugger's version kept under a new alias
    }

    public function run(): void {
        $this->log('Application started');    // Uses Logger::log
        $this->debugLog('Memory: 42MB');      // Uses Debugger::log
    }
}
```

### `as` for visibility change

`as` can also change a method's visibility without renaming it:

```php
trait Internals {
    public function secretMethod(): void {
        echo "Internal logic.\n";
    }
}

class Service {
    use Internals {
        secretMethod as protected; // Now protected in this class only
    }
}
```

---

## 5 — Trait Properties and Abstract Trait Methods

### Trait Properties

Traits can declare properties. Every class that uses the trait gets those properties injected — as if they were declared in the class itself.

```php
trait HasStatus {
    private string $status = 'draft';

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $s): void { $this->status = $s; }
}
```

**Important rule:** if the using class also declares a property with the same name, the types and default values must be compatible — otherwise PHP throws a fatal error.

### Abstract Trait Methods

A trait can declare abstract methods. This forces any class that uses the trait to provide that method. It is a way for a trait to declare a *dependency* on the class it is used in.

```php
trait Renderable {
    // The trait needs getTemplate() to exist — it declares it as abstract
    abstract protected function getTemplate(): string;

    // The trait's concrete method depends on getTemplate()
    public function render(): string {
        return "Rendering: " . $this->getTemplate();
    }
}

class EmailView {
    use Renderable;

    // Required by the trait — class must provide this
    protected function getTemplate(): string {
        return 'email/welcome.html';
    }
}
```

This pattern is powerful but should be used sparingly — if a trait depends too heavily on the host class, that is a sign the coupling is too tight.

---

## 6 — Traits and Interfaces — the Most Important Pattern

Traits cannot be type-hinted. Interfaces provide type contracts. **The combination of both** gives you the best of each world:

1. Define the contract with an **interface**
2. Provide the default implementation with a **trait**
3. Classes **implement the interface AND use the trait** — they get the type contract and the free implementation

```php
// 1. The contract
interface Auditable {
    public function getAuditLog(): array;
    public function recordChange(string $action, array $context = []): void;
}

// 2. The default implementation
trait AuditableTrait {
    private array $auditLog = [];

    public function getAuditLog(): array { return $this->auditLog; }

    public function recordChange(string $action, array $context = []): void {
        $this->auditLog[] = [
            'action'    => $action,
            'context'   => $context,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }
}

// 3. Use both together
class Order implements Auditable {
    use AuditableTrait; // Free implementation of the Auditable interface

    public function __construct(private string $id) {
        $this->recordChange('created', ['id' => $id]);
    }
}

class UserProfile implements Auditable {
    use AuditableTrait; // Same free implementation

    public function updateEmail(string $email): void {
        $this->recordChange('email_changed', ['new' => $email]);
    }
}

// ✅ Type-safe — works because of the interface
function auditReport(Auditable $entity): void {
    foreach ($entity->getAuditLog() as $entry) {
        echo "  [{$entry['timestamp']}] {$entry['action']}\n";
    }
}
```

This pattern is used extensively in frameworks like Laravel (`SoftDeletes`, `HasFactory`, `Notifiable` — all traits paired with interfaces or abstract base classes).

---

## 7 — Choosing the Right Tool

This is the full comparison across all three Module 1 tools.

| Need | Interface | Abstract Class | Trait |
|------|-----------|---------------|-------|
| Define a type contract (type-hint) | ✅ | ✅ | ❌ |
| Share concrete method implementations | ❌ | ✅ | ✅ |
| Share across unrelated class hierarchies | ✅ (contract only) | ❌ | ✅ (code) |
| Provide shared constructor / init logic | ❌ | ✅ | ⚠️ (init method, not constructor) |
| Store shared state (properties) | ❌ | ✅ | ✅ (with caveats) |
| A class can use multiple | ✅ (unlimited) | ❌ (one only) | ✅ (unlimited) |
| Force subclass to implement a method | ✅ | ✅ | ✅ (abstract in trait) |
| Part of PHP's type system | ✅ | ✅ | ❌ |

### The decision flowchart

```
Do you need a TYPE that can be used in a type-hint or instanceof?
  YES → Interface (or abstract class if shared implementation is also needed)
  NO  ↓

Do you have SHARED IMPLEMENTATION for classes in a single hierarchy?
  YES → Abstract class
  NO  ↓

Do you have SHARED IMPLEMENTATION for classes in MULTIPLE, UNRELATED hierarchies?
  YES → Trait (optionally paired with an interface for the type contract)
  NO  → You probably do not need any of these — just write the method in the class
```

### Common real-world uses for traits

- `Timestampable` — `created_at` / `updated_at` management
- `SoftDeletable` — `deleted_at` and restore logic
- `HasSlug` — URL slug generation
- `Auditable` — change log recording
- `Cacheable` — caching helpers
- `Loggable` — structured logging helpers
- `Serializable` — custom serialisation logic

---

## 8 — Common Mistakes to Avoid

| Mistake | Why it is wrong | Fix |
|---------|----------------|-----|
| Type-hinting with a trait name | Traits are not types — PHP throws a fatal error | Define an interface alongside the trait |
| Using traits as a substitute for proper design | Traits scattered everywhere make code hard to follow | Ask "is this cross-cutting?" — if it belongs in an inheritance chain, use abstract class |
| Trait properties conflicting with class properties | PHP throws a fatal error if type or default value differs | Name trait properties carefully, or make them private |
| Forgetting that trait methods can conflict | `use A, B` where both define `foo()` is a fatal error | Use `insteadof` and `as` to resolve |
| Trait with a constructor | You cannot define `__construct` in a trait reliably — conflict risk is high | Use an `initX()` method that the class constructor calls manually |
| Using doc-comment @deprecated on traits | Not enforced by PHP | Use #[Deprecated] attribute (PHP 8.5) |

---

## 9 — PHP 8.5 — `#[Deprecated]` on Traits and Constants

PHP 8.0 introduced `#[Deprecated]` for functions and methods.
PHP 8.5 extends it to **traits** and **class constants** — making deprecation machine-enforceable rather than relying on `@deprecated` doc comments that PHP never checked.

### Deprecated trait

When a class `use`s a deprecated trait, PHP 8.5 emits a deprecation notice:

```php
#[\Deprecated(
    message: 'Use LoggableTrait instead. Will be removed in v3.0.',
    since: '2.5.0'
)]
trait LegacyLogTrait {
    public function writeLog(string $msg): void { /* old impl */ }
}

class SomeService {
    use LegacyLogTrait; // ← PHP 8.5: Deprecated notice emitted here
}
```

### Deprecated constant

When a deprecated constant is read, PHP 8.5 emits a deprecation notice:

```php
class PaymentStatus {
    #[\Deprecated('Use the PaymentStatus enum instead', since: '2.0.0')]
    const STATUS_PENDING = 'pending';   // ← deprecated

    // Replacement:
}

enum PaymentStatus: string {
    case Pending = 'pending'; // ← current
}

// Old code:
$s = PaymentStatus::STATUS_PENDING;  // PHP 8.5: Deprecated notice

// New code:
$s = PaymentStatus::Pending->value;  // No notice
```

### Attribute anatomy

Both named arguments are optional:
```php
#[\Deprecated]                                  // Minimal
#[\Deprecated('Use X instead')]                 // With message
#[\Deprecated(since: '2.0.0')]                  // With version only
#[\Deprecated('Use X instead', since: '2.0.0')] // Full form
```

### When to use it

Use `#[Deprecated]` on a **trait** when:
- Replacing one trait with a better-designed one
- Replacing a trait-based approach with constructor injection
- The trait is in a library consumed by other teams

Use `#[Deprecated]` on a **constant** when:
- Replacing string/int constants with a backed enum
- A constant was renamed (keep old name deprecated, alias to new)
- Providing a clear migration path for library consumers

> **Full runnable example:** `examples/06-deprecated-trait-and-constant.php`

---

## 10 — Quick Reference

```php
// Define a trait
trait MyTrait {
    private string $traitProp = 'default';

    abstract protected function requiredMethod(): string; // Host class must implement

    public function concreteMethod(): void {
        echo $this->requiredMethod();
    }
}

// Use one trait
class MyClass {
    use MyTrait;
    protected function requiredMethod(): string { return "Hello"; }
}

// Use multiple traits, resolve conflict
class MyOtherClass {
    use TraitA, TraitB {
        TraitA::sharedMethod insteadof TraitB;   // TraitA wins
        TraitB::sharedMethod as traitBMethod;     // TraitB kept under alias
        TraitA::internalMethod as protected;      // Visibility change
    }
}

// Interface + Trait pattern
interface Contractable { public function doThing(): string; }
trait ContractableTrait { public function doThing(): string { return "done"; } }
class ConcreteClass implements Contractable { use ContractableTrait; }

// Cannot do this — traits are not types:
// function f(MyTrait $x): void {}  ← Fatal error
```

---

## ✅ Lesson Checklist

- [ ] Read this README fully — especially Section 2 (what traits are NOT) and Section 7 (the comparison table)
- [ ] Run and study `examples/01-defining-and-using.php`
- [ ] Run and study `examples/02-multiple-traits-and-conflicts.php`
- [ ] Run and study `examples/03-trait-properties-and-abstract.php`
- [ ] Run and study `examples/04-traits-with-interfaces.php`
- [ ] Run and study `examples/05-choosing-the-right-tool.php`
- [ ] Run and study `examples/06-deprecated-trait-and-constant.php` *(PHP 8.5)*
- [ ] Read `challenge/CHALLENGE.md` and complete `challenge/starter.php`
- [ ] Check your work against `challenge/solution.php`
- [ ] Complete `quiz/QUIZ.md` without looking at any files

---

*Next lesson: **2.0 — Liskov Substitution Principle** — the behavioural contract that makes polymorphism safe.*