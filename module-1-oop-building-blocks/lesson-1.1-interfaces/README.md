# Lesson 1.1 — Interfaces
> **Module 1: OOP Building Blocks** · PHP 8.5 OOP Mastery Course

---

## 📁 Lesson Folder Structure

```
lesson-1.1-interfaces/
├── README.md                          ← Theory (you are here)
│
├── examples/
│   ├── 01-defining-and-implementing.php
│   ├── 02-multiple-interfaces.php
│   ├── 03-type-hints-and-polymorphism.php
│   ├── 04-interface-constants.php
│   ├── 05-interface-inheritance.php
│   └── 06-static-asymmetric-visibility.php  ← PHP 8.5
│
├── challenge/
│   ├── CHALLENGE.md                   ← Instructions
│   ├── starter.php                    ← Your working file (tightly coupled — fix this)
│   └── solution.php                   ← Only open after your own attempt
│
└── quiz/
    └── QUIZ.md
```

**How to use this lesson:**
1. Read this README fully before touching any code.
2. Run each example file in sequence and read the output.
3. Complete the challenge with the starter file.
4. Take the quiz without looking at the solution.

---

## 1 — What Is an Interface and Why Does It Exist?

An interface is a **contract**. It says: *"Any class that claims to implement me must provide these exact methods."* It contains zero implementation — only method signatures and, optionally, constants.

Think of it like a power socket standard. A socket does not care whether you plug in a lamp, a phone charger, or a kettle. As long as the plug matches the socket shape (the contract), it works. The socket is the interface. The devices are the concrete classes.

```
┌─────────────────────────────────────────┐
│           <<interface>>                 │
│           Notification                  │
├─────────────────────────────────────────┤
│  + send(message: string): void          │
└──────────────┬──────────────────────────┘
               │ implements
    ┌──────────┴──────────┐
    ▼                     ▼
EmailNotification    SmsNotification
```

### Why this matters

Without interfaces, your code looks like this:

```php
class OrderService {
    public function notify(): void {
        $notifier = new EmailNotification(); // Hardcoded. Cannot be swapped.
        $notifier->send("Your order is ready.");
    }
}
```

`OrderService` is **glued** to `EmailNotification`. You cannot test it without sending real emails. You cannot switch to SMS without editing `OrderService`.

With an interface:

```php
class OrderService {
    public function __construct(
        private Notification $notifier // Any class that implements Notification
    ) {}

    public function notify(): void {
        $this->notifier->send("Your order is ready.");
    }
}
```

Now `OrderService` knows nothing about the concrete class. You can pass in `EmailNotification`, `SmsNotification`, or a fake test double — all without touching `OrderService`.

---

## 2 — Defining and Implementing a Single Interface

```php
<?php
declare(strict_types=1);

// Define the contract
interface Greetable {
    public function greet(string $name): string;
}

// Fulfil the contract
class FormalGreeter implements Greetable {
    public function greet(string $name): string {
        return "Good day, {$name}.";
    }
}

class CasualGreeter implements Greetable {
    public function greet(string $name): string {
        return "Hey, {$name}!";
    }
}
```

**Rules enforced by PHP:**
- Every method declared in the interface **must** be implemented in the class.
- The method signature (parameter types, return type) must be **compatible** — you can widen types but not narrow them.
- A class that does not implement all methods will throw a fatal error at load time, not at runtime.

---

## 3 — Implementing Multiple Interfaces

A class can only extend **one** parent class, but it can implement **as many interfaces as needed**.

```php
interface Printable {
    public function print(): void;
}

interface Exportable {
    public function exportToCsv(): string;
}

class Report implements Printable, Exportable {
    public function print(): void { /* ... */ }
    public function exportToCsv(): string { /* ... */ }
}
```

This is a key advantage over abstract classes: you can compose multiple contracts onto a single class without inheritance chains.

---

## 4 — Using Interfaces as Type Hints (Polymorphism)

This is where interfaces pay off. When you type-hint a parameter against an interface, PHP accepts **any** class that implements it.

```php
function sendAlert(Notification $notifier, string $message): void {
    $notifier->send($message);
}

sendAlert(new EmailNotification(), "Server is down.");
sendAlert(new SmsNotification(), "Server is down.");
sendAlert(new SlackNotification(), "Server is down.");
```

All three calls are valid. The function does not know or care about the concrete class — it only knows that whatever it receives will have a `send()` method. This is **polymorphism**.

> **Key insight:** Program to the interface, not the implementation. Your functions and class constructors should accept interfaces as parameters, not concrete class names.

---

## 5 — Interface Constants

Interfaces can declare constants. Any class implementing the interface inherits them and cannot override them (prior to PHP 8.1). From PHP 8.1+ interface constants can have a `final` modifier.

```php
interface HttpMethod {
    const GET    = 'GET';
    const POST   = 'POST';
    const PUT    = 'PUT';
    const DELETE = 'DELETE';
}

class ApiClient implements HttpMethod {
    public function request(string $method): void {
        echo "Making a " . self::GET . " request.\n";
        // Or via the interface name: HttpMethod::GET
    }
}
```

Use interface constants for values that are part of the **contract's domain** — things every implementor needs to know about.

---

## 6 — Interface Inheritance

An interface can extend one or more other interfaces using `extends`. This builds a hierarchy of contracts.

```php
interface Readable {
    public function read(): string;
}

interface Writable {
    public function write(string $data): void;
}

// ReadWritable extends both — it is a superset contract
interface ReadWritable extends Readable, Writable {
    public function seek(int $position): void;
}

// Must implement read(), write(), AND seek()
class FileStream implements ReadWritable {
    public function read(): string { return "data"; }
    public function write(string $data): void { /* ... */ }
    public function seek(int $position): void { /* ... */ }
}
```

**When to use interface inheritance:**
- When a broader contract naturally includes a narrower one.
- When you want to keep granular interfaces (Readable, Writable) for code that only needs one capability, and a composite (ReadWritable) for code that needs both. This is the **Interface Segregation Principle** (the I in SOLID).

---

## 7 — PHP 8.5 — Asymmetric Visibility for Static Properties

PHP 8.4 introduced asymmetric visibility for **instance** properties:
```php
public private(set) string $name = '';   // instance — PHP 8.4
```

PHP 8.5 extends this to **static** properties:
```php
public static private(set) string $environment = 'production';
```

This allows a static property to be readable from anywhere but writable only from inside the class — eliminating the need for static getter methods on guarded class state.

### Before PHP 8.5 (boilerplate required)
```php
class AppConfig {
    private static string $environment = 'production';

    // Getter required just to expose the read
    public static function getEnvironment(): string {
        return self::$environment;
    }

    public static function setEnvironment(string $env): void {
        self::$environment = $env;
    }
}

echo AppConfig::getEnvironment(); // must use getter
```

### PHP 8.5 (no getter needed)
```php
class AppConfig {
    // Readable from anywhere, writable only inside the class
    public static private(set) string $environment = 'production';

    public static function setEnvironment(string $env): void {
        self::$environment = $env; // ✅ write from inside — allowed
    }
}

echo AppConfig::$environment;              // ✅ direct read — no getter
AppConfig::$environment = 'staging';       // ❌ Fatal error — write from outside
AppConfig::setEnvironment('staging');      // ✅ controlled write via method
```

### All static asymmetric visibility combinations
```php
public static private(set)   string $a;  // readable anywhere, writable inside only
public static protected(set) string $b;  // readable anywhere, writable inside + subclasses
protected static private(set) string $c; // readable inside + subclasses, writable inside only
```

> **Full runnable example:** `examples/06-static-asymmetric-visibility.php`

---

## 8 — Common Mistakes to Avoid

| Mistake | Why it's wrong | Fix |
|---|---|---|
| Putting logic in an interface | Interfaces are contracts, not implementations | Use an abstract class if you need shared logic |
| Type-hinting the concrete class in function parameters | Defeats the purpose of the interface | Type-hint the interface instead |
| One giant interface with 15 methods | Forces classes to implement methods they don't need | Split into smaller, focused interfaces (ISP) |
| Using `abstract` keyword inside an interface | All interface methods are implicitly abstract | Remove the keyword |
| Accessing `$this` in an interface | Interfaces have no instance | Not possible — only in implementing classes |

---

## 9 — Quick Reference

```php
// Define
interface MyInterface {
    public function doSomething(string $input): int;
    const MY_CONST = 'value';
}

// Implement (one interface)
class MyClass implements MyInterface {
    public function doSomething(string $input): int {
        return strlen($input);
    }
}

// Implement (multiple interfaces)
class MyOtherClass implements InterfaceA, InterfaceB { /* ... */ }

// Extend (interface inheriting another interface)
interface BigInterface extends SmallInterface { /* ... */ }

// Type hint (polymorphism)
function process(MyInterface $obj): void {
    $obj->doSomething("hello");
}

// Check at runtime
if ($obj instanceof MyInterface) { /* ... */ }
```

---

## ✅ Lesson Checklist

Work through these in order:

- [ ] Read this README fully
- [ ] Run and study `examples/01-defining-and-implementing.php`
- [ ] Run and study `examples/02-multiple-interfaces.php`
- [ ] Run and study `examples/03-type-hints-and-polymorphism.php`
- [ ] Run and study `examples/04-interface-constants.php`
- [ ] Run and study `examples/05-interface-inheritance.php`
- [ ] Run and study `examples/06-static-asymmetric-visibility.php` *(PHP 8.5)*
- [ ] Read `challenge/CHALLENGE.md` and complete `challenge/starter.php`
- [ ] Check your work against `challenge/solution.php`
- [ ] Complete `quiz/QUIZ.md` without looking at any files

---

*Next lesson: **1.2 — Abstract Classes** — balancing code reuse with architectural enforcement.*