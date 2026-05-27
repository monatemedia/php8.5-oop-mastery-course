# Lesson 3.2 — Constructor Injection
> **Module 3: Dependency Injection & IoC** · PHP 8.5 OOP Mastery Course

---

## 📁 Lesson Folder Structure

```
lesson-3.2-constructor-injection/
├── README.md                              ← Theory (you are here)
│
├── examples/
│   ├── 01-passing-not-creating.php        ← The DI principle in one clear before/after
│   ├── 02-constructor-injection-pattern.php ← Full pattern with interfaces
│   ├── 03-type-hinting-against-interfaces.php ← Why interface types, not concrete types
│   └── 04-multiple-dependencies.php       ← Injecting several dependencies cleanly
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

## 1 — The Dependency Injection Principle

Dependency Injection (DI) is a single idea stated simply:

> **A class should receive its dependencies — not create them.**

That is it. No framework, no container, no magic. Just: if a class needs something, pass it in from outside rather than letting the class build it internally.

The distinction:

```
WITHOUT DI: class creates its own dependencies
              ↓
            $this->db = new MySQLDatabase(...);   ← class is responsible

WITH DI:    class receives its dependencies
              ↓
            public function __construct(DatabaseInterface $db)
            { $this->db = $db; }                  ← caller is responsible
```

The class does not change what it *does* — it still uses the database. What changes is *who decides* which database to use and how to build it. That decision moves out of the class and up to the **caller**.

---

## 2 — Constructor Injection — The Preferred Pattern

Constructor injection is the most common and most recommended form of DI. Dependencies are declared as constructor parameters. The class stores them as properties. Every method in the class can then use them.

```php
// The interfaces — contracts, not implementations
interface DatabaseInterface {
    public function query(string $sql, array $params = []): array;
    public function execute(string $sql, array $params = []): bool;
}

interface LoggerInterface {
    public function log(string $level, string $message): void;
}

// The class — depends on interfaces, not concrete classes
class UserRepository {
    public function __construct(
        private DatabaseInterface $db,     // ✅ interface
        private LoggerInterface   $logger  // ✅ interface
    ) {}

    public function findById(int $id): ?array {
        $this->logger->log('INFO', "Finding user #{$id}");
        $rows = $this->db->query('SELECT * FROM users WHERE id = ?', [$id]);
        return $rows[0] ?? null;
    }

    public function save(array $user): bool {
        $this->logger->log('INFO', "Saving user #{$user['id']}");
        return $this->db->execute(
            'INSERT INTO users (email, name) VALUES (?, ?)',
            [$user['email'], $user['name']]
        );
    }
}
```

**Why constructor injection is preferred:**
1. **Mandatory dependencies are obvious** — if you cannot instantiate the class without them, they must be required. The constructor signature is the contract.
2. **The class is always in a valid state** — after construction, all dependencies are set. No method can be called before dependencies are ready.
3. **Easy to test** — pass fakes in the constructor. No setup methods, no property injection, no tricks.
4. **Works without a framework** — pure PHP, no annotations, no config files.

---

## 3 — Type-Hinting Against Interfaces, Not Concrete Classes

The parameter types in the constructor should be **interfaces**, not concrete class names. This is the "I" in SOLID and the key to making injection actually work.

```php
// ❌ Wrong — still tightly coupled even though we inject
class OrderService {
    public function __construct(
        private StripeGateway $gateway,  // Concrete — can only pass StripeGateway
        private MySQLDatabase $db        // Concrete — can only pass MySQLDatabase
    ) {}
}

// ✅ Correct — loosely coupled via interfaces
class OrderService {
    public function __construct(
        private PaymentGatewayInterface $gateway,  // Any gateway
        private DatabaseInterface       $db         // Any database
    ) {}
}
```

With interface types:
- Tests pass anonymous class stubs or simple in-memory fakes
- Production passes real implementations
- Staging passes test-mode implementations
- The class itself never changes — only the wiring changes

---

## 4 — Multiple Dependencies

A class may need several dependencies. Constructor injection handles this naturally. Use PHP 8's **constructor property promotion** to keep it clean:

```php
class CheckoutService {
    public function __construct(
        private ProductRepositoryInterface  $products,
        private InventoryInterface          $inventory,
        private PaymentGatewayInterface     $gateway,
        private MailerInterface             $mailer,
        private LoggerInterface             $logger
    ) {}

    public function checkout(array $cart, string $customerEmail): array {
        // Each dependency is ready to use — no setup needed
        $this->logger->log('INFO', "Checkout started for {$customerEmail}");
        // ...
    }
}
```

**Guidelines for multiple dependencies:**

| Guideline | Reason |
|-----------|--------|
| Keep to 3–5 dependencies where possible | More than 5 often signals the class has too many responsibilities (SRP violation) |
| List required dependencies before optional | Makes the constructor signature self-documenting |
| Always type-hint against interfaces | Enables substitution in every context |
| Use constructor property promotion | Reduces boilerplate — declare and assign in one line |

**If a class needs more than 5 injected dependencies**, consider whether it should be split into smaller classes (SRP).

---

## 5 — The Composition Root

Once you use constructor injection, *something* has to call `new` — the concrete classes need to be created somewhere. That place is called the **composition root**: the single entry point of your application where all dependencies are wired together.

```
app/
├── index.php           ← composition root — wires everything
├── src/
│   ├── UserService.php       ← receives DatabaseInterface, LoggerInterface
│   ├── UserRepository.php    ← receives DatabaseInterface
│   └── ...
└── config/
    └── services.php    ← may hold configuration used at the composition root
```

```php
// index.php — the composition root
// This is the ONLY place where `new` is called on services

$db      = new MySQLDatabase(getenv('DB_DSN'));
$logger  = new FileLogger(getenv('LOG_PATH'));
$repo    = new UserRepository($db, $logger);
$service = new UserService($repo, $logger);

// Hand off to the HTTP layer
$router->dispatch($service);
```

Everything downstream of `index.php` uses constructor injection — no `new` on services anywhere.

---

## 6 — Before and After: The Checkout System from Lesson 3.1

In Lesson 3.1 you found 14 coupling violations in a checkout system. Here is what the fix looks like:

**Before (Lesson 3.1 — 14 violations):**
```php
class CheckoutService {
    public function __construct() {
        $this->catalog   = new ProductCatalog();
        $this->inventory = new InventoryChecker();
        $this->mailer    = new SendGridMailer('SG.abc123...');
        $this->logger    = new MonologLogger();
    }
}
```

**After (Lesson 3.2 — 0 violations):**
```php
interface ProductCatalogInterface { public function findById(int $id): ?array; }
interface InventoryInterface      { public function isAvailable(string $sku, int $qty): bool; }
interface MailerInterface         { public function send(string $to, string $sub, string $body): bool; }
interface LoggerInterface         { public function log(string $level, string $msg): void; }

class CheckoutService {
    public function __construct(
        private ProductCatalogInterface $catalog,   // ✅
        private InventoryInterface      $inventory, // ✅
        private MailerInterface         $mailer,    // ✅
        private LoggerInterface         $logger     // ✅
    ) {}

    // process() method is IDENTICAL — only the wiring changed
}
```

---

## 7 — Quick Reference

```php
// 1. Define interfaces
interface DatabaseInterface {
    public function query(string $sql, array $params = []): array;
}

// 2. Implement interfaces
class MySQLDatabase implements DatabaseInterface {
    public function query(string $sql, array $params = []): array { /* ... */ }
}

// 3. Inject via constructor (property promotion)
class UserRepository {
    public function __construct(
        private DatabaseInterface $db  // ← interface type
    ) {}
}

// 4. Wire at the composition root
$db   = new MySQLDatabase(getenv('DB_DSN'));
$repo = new UserRepository($db);  // ← only `new` here

// 5. Test with fakes (no infrastructure needed)
$fakeDb = new class implements DatabaseInterface {
    public function query(string $sql, array $params = []): array {
        return [['id' => 1, 'name' => 'Alice']];
    }
};
$repo = new UserRepository($fakeDb);  // ← works perfectly
```

---

## ✅ Lesson Checklist

- [ ] Read this README fully — especially Sections 3 (interface types) and 5 (composition root)
- [ ] Run and study `examples/01-passing-not-creating.php`
- [ ] Run and study `examples/02-constructor-injection-pattern.php`
- [ ] Run and study `examples/03-type-hinting-against-interfaces.php`
- [ ] Run and study `examples/04-multiple-dependencies.php`
- [ ] Read `challenge/CHALLENGE.md` and complete `challenge/starter.php`
- [ ] Check your work against `challenge/solution.php`
- [ ] Complete `quiz/QUIZ.md` without looking at any files

---

*Next lesson: **3.3 — Setter & Interface Injection** — handling optional dependencies.*