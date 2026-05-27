# Lesson 2.2 — PHP 8.5 Property Hooks
> **Module 2: Advanced Types & Enums** · PHP 8.5 OOP Mastery Course
> ⚠️  **PHP 8.5.** Property hooks do not exist in earlier versions.

---

## 📁 Lesson Folder Structure

```
lesson-2.2-property-hooks/
├── README.md                              ← Theory (you are here)
│
├── examples/
│   ├── 01-the-problem-they-solve.php      ← Boilerplate before hooks existed
│   ├── 02-get-hook.php                    ← Computed and validated reads
│   ├── 03-set-hook.php                    ← Validation and transformation on write
│   ├── 04-backed-vs-virtual.php           ← Stored value vs computed-only
│   └── 05-hooks-in-interfaces-and-abstract.php
│
├── challenge/
│   ├── CHALLENGE.md
│   ├── starter.php
│   └── solution.php
│
└── quiz/
    └── QUIZ.md
```

---

## 1 — What Problem Do Property Hooks Solve?

Before PHP 8.4, enforcing validation or computation on property access required writing explicit getter and setter methods. For a class with six properties, that meant twelve boilerplate methods:

```php
// Pre-8.4: The boilerplate problem
class UserProfile {
    private string $email;
    private string $firstName;
    private string $lastName;

    public function getEmail(): string      { return $this->email; }
    public function setEmail(string $email): void {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email.");
        }
        $this->email = strtolower($email);
    }

    public function getFirstName(): string        { return $this->firstName; }
    public function setFirstName(string $v): void { $this->firstName = trim($v); }

    public function getLastName(): string        { return $this->lastName; }
    public function setLastName(string $v): void { $this->lastName = trim($v); }

    // Plus a computed property — requires another method:
    public function getFullName(): string {
        return $this->firstName . ' ' . $this->lastName;
    }
}
```

**PHP 8.4 property hooks** let you attach `get` and `set` logic directly to a property declaration, eliminating the boilerplate:

```php
// PHP 8.4: The same class with hooks
class UserProfile {
    public string $email {
        get => $this->email;
        set(string $value) {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException("Invalid email.");
            }
            $this->email = strtolower($value);
        }
    }

    public string $firstName {
        set(string $v) => $this->firstName = trim($v);
    }

    public string $lastName {
        set(string $v) => $this->lastName = trim($v);
    }

    // Virtual property — computed, not stored
    public string $fullName {
        get => $this->firstName . ' ' . $this->lastName;
    }
}
```

The properties are accessed directly — `$user->email`, not `$user->getEmail()`. The hooks run transparently.

---

## 2 — Syntax Overview

```php
class MyClass {
    // Full block syntax
    public Type $propertyName {
        get {
            // multi-line logic
            return $this->propertyName;
        }
        set(Type $value) {
            // multi-line logic
            $this->propertyName = $value;
        }
    }

    // Short (arrow) syntax — single expression only
    public Type $computedProp {
        get => someExpression();
    }

    public Type $validated {
        set(Type $value) => $this->validated = transform($value);
    }
}
```

**Rules:**
- A property can have a `get` hook, a `set` hook, or both.
- If only a `set` hook is defined, PHP provides a default `get` that returns the stored value.
- If only a `get` hook is defined, the property becomes **read-only from outside** — the set hook does not exist publicly.
- The `set` hook parameter type must be compatible with the property's declared type.
- Hooks cannot be `static`.

---

## 3 — The `get` Hook

The `get` hook runs whenever the property is **read**.

### Computed read (derived value)

```php
class Circle {
    public float $radius = 0.0;

    // area is always derived from radius — never stored separately
    public float $area {
        get => M_PI * $this->radius ** 2;
    }
}

$c = new Circle();
$c->radius = 5.0;
echo $c->area; // 78.539... — computed on every read
```

### Validated / transformed read

```php
class Product {
    private float $rawPrice = 0.0;

    public float $price {
        get => round($this->rawPrice, 2); // Always returns rounded value
        set(float $value) => $this->rawPrice = $value;
    }
}
```

### Lazy-loaded read

```php
class HeavyReport {
    private ?array $cachedData = null;

    public array $data {
        get {
            if ($this->cachedData === null) {
                $this->cachedData = $this->loadFromDatabase();
            }
            return $this->cachedData;
        }
    }

    private function loadFromDatabase(): array { /* expensive */ return []; }
}
```

---

## 4 — The `set` Hook

The `set` hook runs whenever the property is **written**.

### Validation on write

```php
class BankAccount {
    public float $balance {
        get => $this->balance;
        set(float $value) {
            if ($value < 0) {
                throw new \InvalidArgumentException("Balance cannot be negative.");
            }
            $this->balance = $value;
        }
    }
}

$account = new BankAccount();
$account->balance = 1000.00;  // OK
$account->balance = -50.00;   // InvalidArgumentException
```

### Transformation on write

```php
class Tag {
    public string $name {
        set(string $value) => $this->name = strtolower(trim($value));
    }
}

$tag = new Tag();
$tag->name = '  PHP Development  ';
echo $tag->name; // "php development"
```

### The `$value` parameter

Inside a `set` hook, `$value` is the value being assigned. It is available implicitly (no need to name it) if you use a shorthand expression:

```php
public string $slug {
    set => $this->slug = strtolower(preg_replace('/\s+/', '-', $value));
}
```

---

## 5 — Backed vs Virtual Properties

### Backed properties

A **backed property** has actual storage — a value is held in memory. Most properties with hooks are backed:

```php
class Order {
    public string $status = 'pending' {   // ← default value makes it backed
        set(string $value) {
            $allowed = ['pending', 'confirmed', 'shipped', 'cancelled'];
            if (!in_array($value, $allowed)) {
                throw new \InvalidArgumentException("Invalid status: {$value}");
            }
            $this->status = $value;
        }
    }
}
```

### Virtual properties

A **virtual property** has **no storage** — it is computed entirely by its `get` hook. It has no default value and no `set` hook (because there is nothing to store):

```php
class Rectangle {
    public float $width  = 0.0;
    public float $height = 0.0;

    // Virtual — no stored value, purely derived
    public float $area {
        get => $this->width * $this->height;
    }

    public float $perimeter {
        get => 2 * ($this->width + $this->height);
    }
}
```

Virtual properties cannot be assigned to — attempting `$rect->area = 5.0` is a fatal error.

**How to tell them apart:**

| | Backed | Virtual |
|--|--------|---------|
| Has a default value | ✓ (can have) | ✗ |
| Stores a value in memory | ✓ | ✗ |
| Can have a `set` hook | ✓ | ✗ |
| Can be assigned from outside | ✓ (if set hook exists or no hooks) | ✗ |
| Can only be read | Optional | Always |

---

## 6 — Hooks in Interfaces

PHP 8.4 allows interfaces to declare **property requirements with hook signatures**. An implementing class must honour the contract:

```php
interface HasName {
    // Requires a readable string $name property
    public string $name { get; }
}

interface HasEmail {
    // Requires both a readable and writable $email property
    public string $email { get; set; }
}

class Contact implements HasName, HasEmail {
    public string $name {
        get => $this->name;
    }

    public string $email {
        get => $this->email;
        set(string $value) => $this->email = strtolower(trim($value));
    }
}
```

**Interface hook rules:**
- `{ get; }` — the property must be readable (implementing class must provide a `get` hook or a plain property).
- `{ get; set; }` — the property must be both readable and writable.
- `{ set; }` — the property must be writable (rare).
- The interface cannot specify a default value — that is the class's responsibility.

---

## 7 — Hooks in Abstract Classes

Abstract classes can declare properties with hooks, including **abstract hook declarations**:

```php
abstract class BaseEntity {
    // Concrete hook — shared across all subclasses
    public string $id {
        get => $this->id;
        set(string $value) {
            if (empty(trim($value))) {
                throw new \InvalidArgumentException("ID cannot be empty.");
            }
            $this->id = trim($value);
        }
    }

    // Abstract get hook — every subclass must define how to compute this
    abstract public string $label { get; }
}

class ProductEntity extends BaseEntity {
    public string $name = '';

    public string $label {
        get => "Product: {$this->name}";
    }
}
```

---

## 8 — What Hooks Cannot Do

| Limitation | Notes |
|-----------|-------|
| Cannot be `static` | Hooks always operate on instance properties |
| Cannot use `readonly` with `set` hooks | `readonly` and a custom `set` hook are mutually exclusive |
| Cannot change the property type in the hook | The hook parameter type must be compatible with the declared property type |
| Virtual properties cannot have `set` hooks | A property with only a `get` hook and no default has nowhere to write to |
| Cannot call `parent::$prop` | Hook inheritance does not support `parent` access in the same way method inheritance does |
| Short `set` syntax must assign `$this->propName` | Otherwise the value is never stored |

---

## 9 — Quick Reference

```php
// Backed property — both hooks
public Type $prop = defaultValue {
    get {
        return $this->prop; // or transform it
    }
    set(Type $value) {
        // validate, transform, then:
        $this->prop = $value;
    }
}

// Backed property — set only (get returns raw value automatically)
public string $email {
    set(string $v) => $this->email = strtolower($v);
}

// Virtual property — get only, no storage
public float $area {
    get => $this->width * $this->height;
}

// In an interface
interface MyInterface {
    public string $name { get; }          // read-only contract
    public string $email { get; set; }    // read-write contract
}

// In an abstract class
abstract class Base {
    abstract public string $label { get; } // subclass must provide get hook
}
```

---

## ✅ Lesson Checklist

- [ ] Read this README fully — especially Section 5 (backed vs virtual) and Section 6 (hooks in interfaces)
- [ ] Run and study `examples/01-the-problem-they-solve.php`
- [ ] Run and study `examples/02-get-hook.php`
- [ ] Run and study `examples/03-set-hook.php`
- [ ] Run and study `examples/04-backed-vs-virtual.php`
- [ ] Run and study `examples/05-hooks-in-interfaces-and-abstract.php`
- [ ] Read `challenge/CHALLENGE.md` and complete `challenge/starter.php`
- [ ] Check your work against `challenge/solution.php`
- [ ] Complete `quiz/QUIZ.md` without looking at any files

---

*Next lesson: **2.3 — Enums** — replacing magic string constants with a first-class type.*