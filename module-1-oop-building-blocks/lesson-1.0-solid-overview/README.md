# Lesson 1.0 — SOLID Principles Overview
> **Module 1: OOP Building Blocks** · PHP 8.5 OOP Mastery Course

---

## Why This Lesson Exists

SOLID is a set of five design principles that tell you **how to organise classes and their relationships** so that your code stays maintainable as it grows. Every module in this course teaches one or more of these principles — but without a map, the connections are easy to miss.

This lesson gives you that map. You will see each principle defined, illustrated with a before/after example, and pinned to the exact module where it is taught in depth. You do not need to master everything here — you just need to recognise the vocabulary when it appears later.

---

## The Five Principles at a Glance

| Letter | Principle | One-line summary | Taught in |
|--------|-----------|-----------------|-----------|
| **S** | Single Responsibility | A class should have one reason to change | Module 1 (implicit throughout) |
| **O** | Open/Closed | Open for extension, closed for modification | Module 1 (explicit in Lesson 1.1 challenge) |
| **L** | Liskov Substitution | Subtypes must be safely swappable for their base type | **Lesson 2.0** |
| **I** | Interface Segregation | Don't force classes to implement methods they don't need | Module 1 — Lesson 1.1 Examples 02 & 05 |
| **D** | Dependency Inversion | Depend on abstractions, not concretions | Modules 3 & 4 |

---

## S — Single Responsibility Principle

> *"A class should have one, and only one, reason to change."*
> — Robert C. Martin

If a class does too many things, a change to any one of them risks breaking the others. A class that handles both business logic and database persistence needs to change whenever either the business rules or the database schema changes — two unrelated reasons.

**Bad — one class, multiple responsibilities:**
```php
class UserService {
    public function register(string $email, string $password): void {
        // Responsibility 1: hashing (security logic)
        $hashed = password_hash($password, PASSWORD_BCRYPT);

        // Responsibility 2: persistence (database logic)
        $pdo = new PDO('mysql:host=localhost;dbname=app', 'root', '');
        $pdo->prepare('INSERT INTO users (email, password) VALUES (?, ?)')
            ->execute([$email, $hashed]);

        // Responsibility 3: notification (email logic)
        mail($email, 'Welcome!', 'Your account has been created.');
    }
}
// Reason to change: business rules change, DB schema changes, mail provider changes.
// That is THREE reasons. SRP says: one.
```

**Good — three classes, one responsibility each:**
```php
class PasswordHasher {
    public function hash(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT);
    }
}

class UserRepository {
    public function __construct(private PDO $pdo) {}
    public function save(string $email, string $hashedPassword): void {
        $this->pdo->prepare('INSERT INTO users (email, password) VALUES (?, ?)')
                  ->execute([$email, $hashedPassword]);
    }
}

class WelcomeMailer {
    public function send(string $email): void {
        mail($email, 'Welcome!', 'Your account has been created.');
    }
}

class UserService {
    public function __construct(
        private PasswordHasher  $hasher,
        private UserRepository  $repository,
        private WelcomeMailer   $mailer
    ) {}

    public function register(string $email, string $password): void {
        $hashed = $this->hasher->hash($password);
        $this->repository->save($email, $hashed);
        $this->mailer->send($email);
    }
}
// Each class now has exactly one reason to change.
```

**Where you will see this in the course:**
The pattern of small, focused classes appears throughout Modules 1–4. It becomes especially visible in Module 3 when we build a dependency graph — each node in that graph should have one responsibility.

---

## O — Open/Closed Principle

> *"Software entities should be open for extension, but closed for modification."*
> — Bertrand Meyer

Once a class works correctly, you should be able to add new behaviour by **adding new code** (a new class), not by **editing existing code**. Editing working code is risky — you can introduce regressions. Adding a new class that implements an existing interface carries no such risk.

**Bad — adding a new payment type requires editing the existing class:**
```php
class PaymentProcessor {
    public function process(string $type, float $amount): void {
        if ($type === 'stripe') {
            echo "Charging R{$amount} via Stripe.\n";
        } elseif ($type === 'payfast') {
            echo "Charging R{$amount} via PayFast.\n";
        }
        // To add PayPal: edit THIS method. That violates OCP.
    }
}
```

**Good — adding a new payment type means adding a new class, never editing existing ones:**
```php
interface PaymentGateway {
    public function charge(float $amount): void;
}

class StripeGateway implements PaymentGateway {
    public function charge(float $amount): void {
        echo "Charging R{$amount} via Stripe.\n";
    }
}

class PayFastGateway implements PaymentGateway {
    public function charge(float $amount): void {
        echo "Charging R{$amount} via PayFast.\n";
    }
}

// To add PayPal: add PayPalGateway. Touch nothing else.
class PayPalGateway implements PaymentGateway {
    public function charge(float $amount): void {
        echo "Charging R{$amount} via PayPal.\n";
    }
}

class PaymentProcessor {
    public function process(PaymentGateway $gateway, float $amount): void {
        $gateway->charge($amount); // Never changes, no matter how many gateways you add.
    }
}
```

**Where you will see this in the course:**
The Lesson 1.1 challenge solution is a direct demonstration of OCP — `InvoiceService::process()` never changes regardless of how many `PaymentGateway` implementations you add.

---

## L — Liskov Substitution Principle

> *"Objects of a subclass should be replaceable with objects of the superclass without breaking the program."*
> — Barbara Liskov

If a function accepts a `Bird`, and you pass in a `Penguin` (which extends `Bird`), the function must still work correctly. If `Penguin` overrides `fly()` by throwing an exception, the substitution breaks the contract — that is an LSP violation.

**Bad — subclass breaks the parent contract:**
```php
class Bird {
    public function fly(): void {
        echo "Flying.\n";
    }
}

class Penguin extends Bird {
    public function fly(): void {
        throw new \Exception("Penguins cannot fly!"); // Breaks the Bird contract
    }
}

function makeBirdFly(Bird $bird): void {
    $bird->fly(); // Crashes when a Penguin is passed. LSP violated.
}
```

**Good — model the hierarchy so the contract is always honoured:**
```php
interface Bird {
    public function move(): void;
}

interface FlyingBird extends Bird {
    public function fly(): void;
}

class Eagle implements FlyingBird {
    public function move(): void { $this->fly(); }
    public function fly(): void  { echo "Eagle soaring.\n"; }
}

class Penguin implements Bird {
    public function move(): void { echo "Penguin waddling.\n"; }
    // No fly() — Penguin never promised it could fly.
}

function moveAnimal(Bird $bird): void {
    $bird->move(); // Safe with any Bird. LSP satisfied.
}
```

**Where you will see this in the course:**
Lesson 2.0 covers LSP in full — including how PHP's type system enforces (or fails to enforce) it, and what covariance and contravariance mean for interface method signatures.

---

## I — Interface Segregation Principle

> *"Clients should not be forced to depend on methods they do not use."*
> — Robert C. Martin

One fat interface that bundles unrelated methods forces every implementing class to provide methods it does not need. Split the interface into smaller, focused ones so each class only signs the contracts that are relevant to it.

**Bad — one interface forces all implementors to provide everything:**
```php
interface FileStorage {
    public function read(string $path): string;
    public function write(string $path, string $data): void;
    public function delete(string $path): void;
    public function listFiles(string $dir): array;
    public function compress(string $path): void;
    public function encrypt(string $path): void;
}

// A read-only cache is forced to implement write(), delete(), compress(), encrypt()
// even though it can never meaningfully do those things.
class ReadOnlyCache implements FileStorage {
    public function read(string $path): string { return ''; }
    public function write(string $path, string $data): void {
        throw new \Exception("Read-only!"); // Forced to implement, but broken.
    }
    // ... and so on for four more methods it cannot support
}
```

**Good — small, focused interfaces that each class opts into:**
```php
interface Readable   { public function read(string $path): string; }
interface Writable   { public function write(string $path, string $data): void; }
interface Deletable  { public function delete(string $path): void; }
interface Listable   { public function listFiles(string $dir): array; }

class ReadOnlyCache implements Readable {
    public function read(string $path): string { return 'cached data'; }
    // Implements exactly what it can do — nothing more.
}

class LocalDisk implements Readable, Writable, Deletable, Listable {
    // Implements everything, because it genuinely supports everything.
    public function read(string $path): string    { return ''; }
    public function write(string $path, string $data): void {}
    public function delete(string $path): void    {}
    public function listFiles(string $dir): array { return []; }
}
```

**Where you will see this in the course:**
Lesson 1.1 Examples 02 and 05 demonstrate ISP directly. Example 05's `Readable/Writable/Listable/ReadWritable/FullStorage` hierarchy is a full worked implementation.

---

## D — Dependency Inversion Principle

> *"High-level modules should not depend on low-level modules. Both should depend on abstractions."*
> — Robert C. Martin

A class that creates its own dependencies with `new` is tightly coupled to those concrete classes. Flip the dependency: accept abstractions (interfaces) as constructor parameters, and let the caller decide which concrete class to provide.

**Bad — high-level module depends on a low-level concrete class:**
```php
class ReportService {
    private MySqlReportRepository $repo; // Hardcoded to MySQL

    public function __construct() {
        $this->repo = new MySqlReportRepository(); // High-level depends on low-level
    }

    public function generate(int $id): string {
        return $this->repo->findById($id)->render();
    }
}
// Cannot swap MySQL for PostgreSQL, or a fake for testing, without editing ReportService.
```

**Good — both sides depend on an abstraction:**
```php
interface ReportRepository {
    public function findById(int $id): Report;
}

class MySqlReportRepository implements ReportRepository { /* ... */ }
class FakeReportRepository implements ReportRepository { /* ... */ } // For tests

class ReportService {
    public function __construct(
        private ReportRepository $repo // Depends on the abstraction only
    ) {}

    public function generate(int $id): string {
        return $this->repo->findById($id)->render();
    }
}
// Swap the repository by changing one line at the wiring point. ReportService never changes.
```

**Where you will see this in the course:**
Module 3 (Lessons 3.1–3.4) covers DIP end-to-end. Module 4 automates it with a container.

---

## ✅ Lesson Checklist

- [ ] Read this README fully — understand what each letter stands for
- [ ] Run `examples/srp.php` — Single Responsibility
- [ ] Run `examples/ocp.php` — Open/Closed
- [ ] Run `examples/lsp.php` — Liskov Substitution
- [ ] Run `examples/isp.php` — Interface Segregation
- [ ] Run `examples/dip.php` — Dependency Inversion
- [ ] Without looking at the README, write a one-sentence definition of each principle from memory

---

*Next lesson: **1.1 — Interfaces** — your first deep dive into the tool that makes O, L, I, and D possible.*