# Lesson 2.4 — Anonymous Classes
> **Module 2: Advanced Types & Enums** · PHP 8.5 OOP Mastery Course
> ✅ Available from PHP 7.0 — works in all modern PHP versions.

---

## 📁 Lesson Folder Structure

```
lesson-2.4-anonymous-classes/
├── README.md                              ← Theory (you are here)
│
├── examples/
│   ├── 01-syntax-and-instantiation.php    ← How to create and use anonymous classes
│   ├── 02-implementing-interfaces.php     ← Inline stubs and test doubles
│   ├── 03-extending-classes.php           ← Extending concrete and abstract classes
│   └── 04-when-to-use.php                 ← Decision guide: anonymous vs named vs closure
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

## 1 — What Is an Anonymous Class?

An anonymous class is a class with **no name**, created with `new class`. It behaves exactly like a named class — it can implement interfaces, extend classes, use traits, have properties and methods — but it exists only for that one instantiation and has no reusable name.

```php
$greeter = new class('Alice') {
    public function __construct(private string $name) {}
    public function greet(): string { return "Hello, {$this->name}!"; }
};

echo $greeter->greet(); // "Hello, Alice!"
```

The class has no name you can reference elsewhere. The variable `$greeter` holds the only reference to this particular class definition.

---

## 2 — Syntax

```php
// Minimal anonymous class
$obj = new class {};

// With constructor arguments (passed after `class`)
$obj = new class('arg1', 42) {
    public function __construct(
        private string $a,
        private int    $b
    ) {}
};

// Implementing an interface
$obj = new class implements MyInterface {
    public function doSomething(): string { return "done"; }
};

// Extending a class
$obj = new class extends BaseClass {
    public function overrideMethod(): void { /* ... */ }
};

// Extending AND implementing
$obj = new class('value') extends Base implements InterfaceA, InterfaceB {
    public function __construct(string $v) { parent::__construct($v); }
    // implement interface methods...
};

// Using a trait
$obj = new class {
    use MyTrait;
};
```

**Constructor arguments** are passed between `class` and `{` — before the class body.

---

## 3 — Implementing Interfaces Inline

This is the most common use case. Instead of creating a named class file just to provide a one-off implementation of an interface, you define it inline where it is needed.

```php
interface Logger {
    public function log(string $message): void;
}

// Named class approach — requires a separate file or class definition
class ConsoleLogger implements Logger {
    public function log(string $message): void {
        echo $message . "\n";
    }
}
$logger = new ConsoleLogger();

// Anonymous class approach — defined exactly where it is used
$logger = new class implements Logger {
    public function log(string $message): void {
        echo $message . "\n";
    }
};
```

Both approaches produce an object that satisfies the `Logger` interface. The anonymous version is ideal when:
- The implementation is short and obvious
- You only need it in one place
- Creating a named class file would add more ceremony than clarity

---

## 4 — Test Doubles and Stubs

Anonymous classes shine in **tests** (and anywhere you need a lightweight fake). Instead of creating a dedicated file for each test double, you define it inline in the test function.

```php
interface EmailSender {
    public function send(string $to, string $subject, string $body): bool;
}

class OrderService {
    public function __construct(private EmailSender $mailer) {}

    public function confirm(string $email, string $orderId): void {
        $sent = $this->mailer->send(
            $email,
            "Order Confirmed",
            "Your order #{$orderId} is confirmed."
        );
        if (!$sent) {
            throw new \RuntimeException("Failed to send confirmation.");
        }
    }
}

// Test using an anonymous class stub — no separate file needed
function testOrderConfirmation(): void {
    $stub = new class implements EmailSender {
        public array $sent = [];

        public function send(string $to, string $subject, string $body): bool {
            $this->sent[] = compact('to', 'subject', 'body');
            return true;
        }
    };

    $service = new OrderService($stub);
    $service->confirm('alice@example.com', 'ORD-001');

    assert(count($stub->sent) === 1);
    assert($stub->sent[0]['to'] === 'alice@example.com');
    echo "Test passed.\n";
}
```

---

## 5 — Extending Classes Anonymously

Anonymous classes can extend both concrete and abstract classes:

```php
// Extending a concrete class
class BaseLogger {
    protected string $prefix = '[LOG]';

    public function format(string $message): string {
        return "{$this->prefix} {$message}";
    }
}

$verboseLogger = new class extends BaseLogger {
    public function __construct() {
        $this->prefix = '[VERBOSE]';
    }

    public function log(string $message): void {
        echo $this->format($message) . "\n";
    }
};

$verboseLogger->log("Server started"); // "[VERBOSE] Server started"
```

```php
// Extending an abstract class
abstract class HttpHandler {
    abstract public function handle(array $request): string;

    protected function jsonResponse(array $data): string {
        return json_encode($data);
    }
}

// Quick one-off handler — no need for a named class
$handler = new class extends HttpHandler {
    public function handle(array $request): string {
        return $this->jsonResponse(['status' => 'ok', 'path' => $request['path']]);
    }
};

echo $handler->handle(['path' => '/health']); // {"status":"ok","path":"\/health"}
```

---

## 6 — Outer Scope Access

Anonymous classes **cannot** access the outer scope automatically (unlike closures, which capture via `use`). To pass values from the outer scope into an anonymous class, pass them to its constructor:

```php
$multiplier = 3;

// ❌ Anonymous class CANNOT use $multiplier directly (not like closures)
// $obj = new class { public function calc(int $n): int { return $n * $multiplier; } };

// ✅ Pass via constructor
$obj = new class($multiplier) {
    public function __construct(private int $multiplier) {}
    public function calc(int $n): int { return $n * $this->multiplier; }
};

echo $obj->calc(5); // 15
```

---

## 7 — Type System: How PHP Names Anonymous Classes Internally

Anonymous classes are unnamed by design, but PHP generates an internal name like `class@anonymous/path/to/file.php:12$0`. You generally never see this — but it matters for a few things:

```php
$obj = new class {};

// get_class() returns the internal generated name
echo get_class($obj); // "class@anonymous/path/to/file.php:1$0"

// instanceof works normally against interfaces and parent classes
var_dump($obj instanceof MyInterface); // true (if it implements MyInterface)

// You cannot use get_class() result as a type hint or in new
// (The class cannot be instantiated again from outside)
```

**Because anonymous classes have no reusable name:**
- You cannot type-hint a parameter as "this specific anonymous class"
- You use the interface or parent class as the type hint instead
- This means they integrate naturally into type-safe code via interfaces

---

## 8 — Anonymous Class vs Named Class vs Closure

This is the key decision table for this lesson:

| Need | Best choice |
|------|------------|
| A reusable class used in multiple places | Named class |
| A one-off object with multiple methods, used once | Anonymous class |
| A single callable — one function, no state | Closure |
| A test double or stub for a specific test | Anonymous class |
| A quick implementation of a known interface, used once | Anonymous class |
| A class that needs to be referenced by name elsewhere | Named class |
| Capturing outer variables in a function-like object | Closure (`use`) |
| Capturing outer variables in a multi-method object | Anonymous class (constructor) |

### Decision flowchart

```
Does this need to be used in more than one place?
  YES → Named class

Does it have multiple methods, or does it need to hold state?
  YES → Anonymous class
  NO  → Closure (fn() => ...)

Is it a test double that lives in one test function?
  YES → Anonymous class (never needs a separate file)
```

---

## 9 — What Anonymous Classes Cannot Do

| Limitation | Notes |
|-----------|-------|
| Cannot be referenced by name | No reusable identifier — use an interface instead |
| Cannot be instantiated again | The class definition is tied to that `new class` expression |
| Cannot access outer scope variables directly | Pass via constructor |
| Cannot be serialised reliably | `serialize()` on anonymous class instances may throw or produce unusable output |
| Cannot be used in `instanceof` by class name | Only by interface or parent class |

---

## 10 — Quick Reference

```php
// Minimal
$obj = new class {};

// With constructor args (before the body)
$obj = new class($arg1, $arg2) {
    public function __construct(private string $a, private int $b) {}
};

// Implements interface
$obj = new class implements Logger {
    public function log(string $msg): void { echo $msg; }
};

// Extends class
$obj = new class extends Base {
    public function override(): void { parent::override(); }
};

// Extends + implements + uses trait
$obj = new class($val) extends Base implements InterfaceA {
    use MyTrait;
    public function __construct(mixed $v) { parent::__construct($v); }
    public function method(): void {}
};

// Capture outer scope via constructor
$config = ['debug' => true];
$obj = new class($config) {
    public function __construct(private array $config) {}
    public function isDebug(): bool { return $this->config['debug']; }
};

// Use as type-safe value (type hint against interface/parent)
function process(Logger $logger): void { $logger->log("hi"); }
process(new class implements Logger {
    public function log(string $msg): void { echo $msg; }
});
```

---

## ✅ Lesson Checklist

- [ ] Read this README fully — especially Section 8 (the decision table)
- [ ] Run and study `examples/01-syntax-and-instantiation.php`
- [ ] Run and study `examples/02-implementing-interfaces.php`
- [ ] Run and study `examples/03-extending-classes.php`
- [ ] Run and study `examples/04-when-to-use.php`
- [ ] Read `challenge/CHALLENGE.md` and complete `challenge/starter.php`
- [ ] Check your work against `challenge/solution.php`
- [ ] Complete `quiz/QUIZ.md` without looking at any files

---

*Module 2 complete. Next: **Module 3 — Dependency Injection & IoC***