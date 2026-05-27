# Module 2 — Advanced Types & Enums
> **PHP 8.5 OOP Mastery Course**
> **Folder:** `module-2-advanced-types/`

---

## Module Goal

Strengthen your type system knowledge and learn the PHP 8.x/8.5 features that make interfaces and value objects far more powerful. Understand LSP deeply before writing covariant return types. Use the type system as a **security layer** — not just documentation.

By the end of this module you will be able to:

1. Identify and fix all four forms of LSP violation
2. Declare strict types on every parameter and return value
3. Use the `#[NoDiscard]` attribute to enforce that return values are used
4. Write PHP 8.4 property hooks in place of getter/setter boilerplate
5. Use `clone with` (PHP 8.5) on value objects with property hooks
6. Use backed enums as type-safe replacements for magic string constants
7. Define anonymous class stubs for one-off interface implementations

---

## 🆕 PHP 8.5 Features in This Module

| Feature | Lesson | What it replaces / improves |
|---------|--------|-----------------------------|
| **`#[NoDiscard]`** attribute | **2.1** | Silent discard of return values causing invisible bugs |
| **`clone with`** on hooked properties | **2.2** | Manual wither methods on value objects with hooks |
| **`#[Override]` on properties** | **2.0** | Runtime discovery of missing parent property overrides |

---

## 📁 Module Structure

```
module-2-advanced-types/
├── README.md                              ← You are here
├── lesson-2.0-lsp/
├── lesson-2.1-type-hinting/
├── lesson-2.2-property-hooks/
├── lesson-2.3-enums/
└── lesson-2.4-anonymous-classes/
```

---

## Lesson 2.0 — Liskov Substitution Principle (LSP) ⭐ Do before Lesson 2.1

> **The rule that makes polymorphism safe.**

### Topics
- Preconditions, postconditions, invariants
- The four violation types: throwing override, no-op, `instanceof` guard, strengthened precondition
- Fixing each violation by restructuring the hierarchy
- Covariance — narrowing return types is safe; widening is a PHP fatal error
- Contravariance — widening parameter types is safe; narrowing is a PHP fatal error

### PHP 8.5 addition: `#[Override]` on properties

PHP 8.5 extends the `#[Override]` attribute (introduced in PHP 8.3 for methods) to **properties**. If the parent class renames or removes a property, the child class will immediately throw a compile-time error rather than silently producing wrong behaviour.

```php
class BaseRepository {
    protected string $table = 'generic';
}

class UserRepository extends BaseRepository {
    #[\Override]
    protected string $table = 'users'; // ✅ explicit override — compile error if parent changes

    // Without #[Override], if BaseRepository renamed $table to $tableName,
    // UserRepository would silently have TWO properties and use the wrong one.
}
```

This is particularly valuable in deep hierarchies — exactly the kind of hierarchy this lesson warns against.

### Lesson checklist
- [ ] Preconditions, postconditions, invariants
- [ ] Four violation types (examples/01-the-violation.php)
- [ ] Fixing each violation (examples/02-fix-the-hierarchy.php)
- [ ] Covariance (examples/03-covariance.php)
- [ ] Contravariance (examples/04-contravariance.php)
- [ ] PHP 8.5 `#[Override]` on properties
- [ ] **Code Challenge:** Fix three LSP violations in a CMS codebase
- [ ] **Quiz:** LSP rules, covariance, contravariance

---

## Lesson 2.1 — Type Hinting & Return Types

> **Use the type system as a security layer — not just documentation.**

### Topics
- Scalar types and `declare(strict_types=1)`
- Nullable types (`?string`) and union types (`int|string`)
- `void`, `never`, and `mixed` return types
- `self`, `static`, and `parent` return types
- Intersection types (PHP 8.1+): `Countable&Traversable`
- Enforcing strict typing across a module

### PHP 8.5 addition: `#[NoDiscard]`

The `#[NoDiscard]` attribute tells PHP to emit a warning if the return value of a method or function is discarded. This prevents a common silent bug in fluent/immutable APIs where a developer forgets to capture the result.

```php
class Order {
    public function __construct(
        public readonly float  $total,
        public readonly string $status
    ) {}

    // Without #[NoDiscard], someone could write:
    //   $order->applyDiscount($d);  ← return value silently discarded, $order unchanged
    #[\NoDiscard('The return value is the new Order — the original is unchanged')]
    public function applyDiscount(float $discountRate): static {
        return new static(
            $this->total * (1 - $discountRate),
            $this->status
        );
    }
}

$order = new Order(1500.00, 'pending');

// ✅ correct — return value captured
$discounted = $order->applyDiscount(0.10);

// ❌ PHP 8.5 warning: "Return value of Order::applyDiscount() should not be discarded"
$order->applyDiscount(0.10);
```

`#[NoDiscard]` is most valuable on:
- Immutable/fluent methods that return a new object
- Methods using `clone with` (Lesson 1.2 / 2.2)
- Factory methods
- Methods returning a status or error that callers must check

### Lesson checklist
- [ ] Scalar types and `strict_types=1`
- [ ] Nullable and union types
- [ ] `void`, `never`, `mixed`
- [ ] `self`, `static`, `parent`
- [ ] Intersection types
- [ ] PHP 8.5 `#[NoDiscard]` attribute
- [ ] **Code Challenge:** Add strict type declarations + `#[NoDiscard]` to a loosely typed hierarchy
- [ ] **Quiz:** Type compatibility, strict mode, `#[NoDiscard]` semantics

---

## Lesson 2.2 — PHP 8.4/8.5 Property Hooks
> ⚠️ Property hooks require PHP 8.4 minimum. `clone with` on hooked properties requires PHP 8.5.

### Topics
- What property hooks replace (boilerplate getters/setters)
- The `get` hook — computed and validated reads
- The `set` hook — validation and transformation on write
- Backed vs virtual properties
- Hooks in interfaces (`{ get; }`, `{ get; set; }`)
- Hooks in abstract classes

### PHP 8.5 addition: `clone with` on hooked properties

`clone with` works seamlessly on classes with property hooks:

```php
class UserProfile {
    public string $email = '' {
        set(string $value) => $this->email = strtolower(trim($value));
    }

    public string $displayName = '';

    // Virtual property — derived, no storage
    public string $slug {
        get => strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $this->displayName));
    }
}

$profile = new UserProfile();
$profile->email       = '  ALICE@EXAMPLE.COM  ';
$profile->displayName = 'Alice Smith';

// clone with — produces a new object; the set hook runs on the new value
$updated = clone $profile with ['email' => 'ALICE-NEW@EXAMPLE.COM'];
// $updated->email === 'alice-new@example.com' (hook normalised it)
// $profile->email  === 'alice@example.com'    (original unchanged)

echo $updated->slug; // "alice-smith" (virtual property still works on the clone)
```

Note: Virtual properties are re-computed on the clone — they are not part of the `with` array.

### Lesson checklist
- [ ] The problem hooks solve (before/after comparison)
- [ ] `get` hook — computed reads
- [ ] `set` hook — validation and transformation
- [ ] Backed vs virtual properties
- [ ] Hooks in interfaces and abstract classes
- [ ] PHP 8.5 `clone with` on hooked properties
- [ ] **Code Challenge:** Rewrite a class with six getter/setter pairs using hooks + `clone with`
- [ ] **Quiz:** Hook behaviour, inheritance, `clone with` interaction

---

## Lesson 2.3 — Enums (PHP 8.1+)

> **Type-safe replacements for magic string constants.**

### Topics
- Pure (unit) enums — named cases with no value
- Backed enums — string and integer backing
- Enum methods, constants, and interfaces
- `from()` vs `tryFrom()` — safe parsing of external data
- Enums in `match` — exhaustiveness checking

### No PHP 8.5 additions in this lesson
Enums received no significant OOP-relevant changes in PHP 8.5. The lesson content is fully covered by PHP 8.1+ features.

### Lesson checklist
- [ ] Pure enums and identity comparison
- [ ] Backed enums — string and integer
- [ ] Enum methods and constants
- [ ] Enums implementing interfaces
- [ ] `from()` vs `tryFrom()`
- [ ] `match` exhaustiveness
- [ ] **Code Challenge:** Replace magic string constants with a backed enum
- [ ] **Quiz:** Enum rules, interface implementation, safe value parsing

---

## Lesson 2.4 — Anonymous Classes

> **Inline interface implementations for tests and one-off use.**

### Topics
- Syntax and instantiation
- Implementing interfaces inline
- Extending concrete and abstract classes anonymously
- Anonymous vs named vs closures — the decision guide

### No PHP 8.5 additions in this lesson
Anonymous classes received no significant changes in PHP 8.5.

### Lesson checklist
- [ ] Syntax and constructor arguments
- [ ] Implementing interfaces inline
- [ ] Extending classes anonymously
- [ ] Decision guide: anonymous vs named vs closure
- [ ] **Code Challenge:** Replace a test double file with an anonymous class stub
- [ ] **Quiz:** Anonymous class scoping and use cases

---

## ✅ Module 2 Completion Checklist

- [ ] Lesson 2.0 — LSP + PHP 8.5 `#[Override]` on properties
- [ ] Lesson 2.1 — Type Hinting + PHP 8.5 `#[NoDiscard]`
- [ ] Lesson 2.2 — Property Hooks + PHP 8.5 `clone with`
- [ ] Lesson 2.3 — Enums
- [ ] Lesson 2.4 — Anonymous Classes

---

*Next module: **Module 3 — Dependency Injection & IoC***