# Lesson 2.0 — Liskov Substitution Principle (LSP)
> **Module 2: Advanced Types & Enums** · PHP 8.5 OOP Mastery Course

---

## 📁 Lesson Folder Structure

```
lesson-2.0-lsp/
├── README.md                          ← Theory (you are here)
│
├── examples/
│   ├── 01-the-violation.php           ← What LSP looks like when broken
│   ├── 02-fix-the-hierarchy.php       ← Restructuring to honour contracts
│   ├── 03-covariance.php              ← Return type widening/narrowing in PHP
│   └── 04-contravariance.php          ← Parameter type widening in PHP
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

## 1 — Why LSP Exists (and Why It Lives in Module 2)

You learned in Lesson 1.1 that interfaces are contracts. But a contract is only useful if every class that signs it **truly honours it** — not just syntactically (PHP-enforced), but **behaviourally** (logically).

LSP is the principle that makes substitution safe. It says:

> *"If S is a subtype of T, then objects of type T may be replaced with objects of type S without altering any of the desirable properties of the program."*
> — Barbara Liskov, 1987

In plain terms: **if a function works with a `Bird`, it must still work when you hand it a `Penguin`.** If it does not, your hierarchy is lying about what it promises.

LSP sits here in Module 2 — before the type hinting and return type lessons — because once you understand LSP, the *reason* PHP enforces strict covariant return types and contravariant parameter types becomes obvious rather than arbitrary.

---

## 2 — The Classic Violation: Rectangle and Square

This is the canonical LSP example. It appears in nearly every OOP textbook because it exposes a surprising truth: **"is-a" in natural language does not always map to safe inheritance in code.**

A square *is* a rectangle in mathematics. So this seems reasonable:

```php
class Rectangle {
    public function __construct(
        protected int $width,
        protected int $height
    ) {}

    public function setWidth(int $w): void  { $this->width  = $w; }
    public function setHeight(int $h): void { $this->height = $h; }
    public function area(): int { return $this->width * $this->height; }
}

class Square extends Rectangle {
    // A square must keep width === height, so override both setters:
    public function setWidth(int $w): void  { $this->width = $this->height = $w; }
    public function setHeight(int $h): void { $this->width = $this->height = $h; }
}
```

Now watch what happens when a function uses `Rectangle`:

```php
function stretchRectangle(Rectangle $rect): void {
    $rect->setWidth(10);
    $rect->setHeight(5);
    assert($rect->area() === 50); // Reasonable expectation for a Rectangle
}

stretchRectangle(new Rectangle(0, 0)); // ✓ area = 50
stretchRectangle(new Square(0));       // ✗ area = 25 — Square overrode setWidth, which
                                        //   then also reset the height to 10, so
                                        //   setHeight(5) sets BOTH to 5. Area = 25, not 50.
```

The assertion fails. `Square` cannot be safely substituted for `Rectangle`. The inheritance hierarchy is wrong.

**The fix is not to patch `Square` — it is to restructure the hierarchy** so that neither class inherits from the other, and both implement a shared interface (or neither inherits the setters at all).

---

## 3 — The Three Behavioural Rules of LSP

Beyond signatures, a subtype must honour these behavioural contracts:

### Rule 1 — Preconditions cannot be strengthened
A subtype's method cannot require *more* from its caller than the parent requires.

```php
// Parent accepts any positive amount
class PaymentGateway {
    public function charge(float $amount): void {
        if ($amount <= 0) throw new \InvalidArgumentException("Must be positive");
    }
}

// VIOLATION: Child requires amount >= 10 — a stricter precondition
class StrictGateway extends PaymentGateway {
    public function charge(float $amount): void {
        if ($amount < 10) throw new \InvalidArgumentException("Minimum is R10"); // Stronger!
    }
}
// Code written against PaymentGateway and passing R5 will break on StrictGateway.
```

### Rule 2 — Postconditions cannot be weakened
A subtype's method must deliver *at least* what the parent promised.

```php
// Parent promises to return a non-empty string
class Formatter {
    public function format(array $data): string {
        return json_encode($data) ?: '{}';
    }
}

// VIOLATION: Child can return empty string — weaker postcondition
class LazyFormatter extends Formatter {
    public function format(array $data): string {
        return ''; // Weakened — callers relying on non-empty output break.
    }
}
```

### Rule 3 — Invariants must be preserved
If the parent class maintains a guarantee about its state (e.g. `balance >= 0`), the subtype must maintain it too.

```php
class BankAccount {
    protected float $balance = 0.0;

    public function deposit(float $amount): void {
        $this->balance += $amount;
        // Invariant: $balance is always >= 0
    }

    public function withdraw(float $amount): void {
        if ($amount > $this->balance) throw new \UnderflowException("Insufficient funds");
        $this->balance -= $amount;
    }
}

// VIOLATION: Child allows negative balance — breaks the parent's invariant
class OverdraftAccount extends BankAccount {
    public function withdraw(float $amount): void {
        $this->balance -= $amount; // No check! Balance can go negative.
    }
}
```

---

## 4 — PHP's Type System and LSP: Covariance and Contravariance

PHP enforces two specific aspects of LSP through its type system. Understanding these makes Module 2's type hinting lesson much clearer.

### Covariance — Return types can be narrowed (more specific is OK)

An overriding method's return type can be a **subtype** of the parent's return type. This is safe because the caller asked for a `T` and received an `S` (which is also a `T`).

```php
interface AnimalFactory {
    public function create(): Animal; // Returns Animal
}

class DogFactory implements AnimalFactory {
    public function create(): Dog { // Returns Dog (subtype of Animal) ✓
        return new Dog();
    }
}
// The caller expected Animal. Got Dog. Dog IS an Animal. Safe.
```

### Contravariance — Parameter types can be widened (more general is OK)

An overriding method's parameter type can be a **supertype** of the parent's. This is safe because the caller is passing a `T` (e.g. `Cat`), and the child can handle anything broader — including `T`.

```php
interface CatFeeder {
    public function feed(Cat $cat): void; // Accepts Cat
}

class AnyAnimalFeeder implements CatFeeder {
    public function feed(Animal $animal): void { // Accepts Animal (wider) ✓
        echo "Feeding " . get_class($animal) . "\n";
    }
}
// Caller passes Cat. Child accepts Animal (broader). Cat is an Animal. Safe.
```

**The rule in one line:** return types go **down** (narrower ✓), parameter types go **up** (wider ✓). Both directions mean the caller's expectations are always met.

---

## 5 — How to Spot and Fix LSP Violations

**Smell 1: An overriding method throws `NotImplementedException` or similar.**
If a subclass inherits a method it cannot meaningfully implement, the hierarchy is wrong. Use ISP — split the interface so the class only signs what it can do.

**Smell 2: Caller code checks `instanceof` before calling a method.**
```php
function processPayment(Gateway $gw): void {
    if ($gw instanceof LegacyGateway) {
        // Different path for legacy... this means LegacyGateway breaks substitution
    }
}
```
If you need `instanceof` guards, your subtypes are not truly substitutable.

**Smell 3: A subclass's overriding method silently does nothing.**
A no-op override is just a quieter version of throwing — it breaks the postcondition by delivering less than promised.

**The fix pattern:**
1. Identify the behaviour the parent promises.
2. Ask whether every subtype can genuinely deliver it.
3. If not — split the hierarchy. Use separate interfaces for each real capability, and only implement what a class can honestly support.

---

## 6 — Quick Reference

```
LSP CHECKLIST for any subtype S of type T:
──────────────────────────────────────────────────────────────
✓  Preconditions:  S accepts anything T accepts (or more)
✓  Postconditions: S delivers everything T promises (or more)
✓  Invariants:     S maintains all guarantees T maintains
✓  Return types:   S can return a subtype of T's return type (covariance)
✓  Parameters:     S can accept a supertype of T's params (contravariance)

RED FLAGS (likely violations):
──────────────────────────────────────────────────────────────
✗  Overriding method throws UnsupportedException / NotImplemented
✗  Overriding method silently does nothing
✗  Caller uses instanceof to pick different code paths
✗  Subtype adds a stricter precondition on any parameter
✗  Subtype weakens a postcondition (returns less than promised)
```

---

## ✅ Lesson Checklist

- [ ] Read this README fully
- [ ] Run and study `examples/01-the-violation.php`
- [ ] Run and study `examples/02-fix-the-hierarchy.php`
- [ ] Run and study `examples/03-covariance.php`
- [ ] Run and study `examples/04-contravariance.php`
- [ ] Read `challenge/CHALLENGE.md` and complete `challenge/starter.php`
- [ ] Check your work against `challenge/solution.php`
- [ ] Complete `quiz/QUIZ.md` without looking at any files

---

*Next lesson: **2.1 — Type Hinting & Return Types** — the PHP syntax that makes LSP's covariance and contravariance rules concrete.*