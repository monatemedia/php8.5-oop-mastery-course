# Quiz — Lesson 3.2: Constructor Injection
> Complete this quiz **without** looking at any example or solution files.
> Write your answers before checking the answer key at the bottom.
> Any question you get wrong is a reading target.

---

## Section A — Multiple Choice

**Q1.** What is the core principle behind Dependency Injection?

- A) A class should inherit its dependencies from a parent class.
- B) A class should receive its dependencies from the outside rather than creating them internally.
- C) Dependencies should be stored in global variables so any class can access them.
- D) Each class should create one instance of each dependency it needs.

---

**Q2.** Why is constructor injection the **preferred** form of DI?

- A) It is the only form supported by PHP.
- B) It makes the class lighter by lazy-loading dependencies.
- C) Required dependencies are declared at construction time — the class is always in a valid state and its dependencies are immediately visible.
- D) It is faster than setter injection at runtime.

---

**Q3.** A class has this constructor:
```php
public function __construct(private MySQLDatabase $db) {}
```
The object is passed in from outside. Which problem still exists?

- A) None — injecting a dependency always makes the class loosely coupled.
- B) The property type is a concrete class — only `MySQLDatabase` can be passed, not an in-memory fake or PostgreSQL implementation.
- C) The class has no constructor body — this is invalid PHP.
- D) Property promotion is not supported in PHP.

---

**Q4.** What is the **composition root**?

- A) The abstract base class from which all services inherit.
- B) The single entry point (e.g. `index.php`) where all dependencies are wired together with `new` calls.
- C) The service container that automatically resolves dependencies.
- D) A trait that provides shared constructor logic to all classes.

---

**Q5.** A service has six injected dependencies. What does this most likely signal?

- A) The service is well-architected because it separates all its concerns.
- B) The service may be violating the Single Responsibility Principle — it might need to be split.
- C) PHP will throw an error — constructors cannot have more than five parameters.
- D) The service should store its dependencies in a config file instead.

---

**Q6.** What does PHP 8.0 constructor property promotion do?

- A) Automatically generates getter methods for all constructor parameters.
- B) Allows declaring, type-hinting, and assigning a property in a single constructor parameter declaration.
- C) Makes constructor parameters available globally throughout the application.
- D) Caches constructor parameters so they do not need to be passed again on subsequent calls.

---

**Q7.** You have:
```php
interface LoggerInterface {
    public function log(string $level, string $message): void;
}

class UserService {
    public function __construct(private LoggerInterface $logger) {}
}
```
Which of the following can be passed to `UserService`'s constructor?

- A) Only `FileLogger` instances.
- B) Only classes that extend `LoggerInterface`.
- C) Any object whose class implements `LoggerInterface`.
- D) Only `null` — interface types cannot be injected.

---

**Q8.** Which of the following is the **correct order** for building a multi-layer system with constructor injection?

- A) Services → Repositories → Infrastructure → Entry point
- B) Entry point → Services → Repositories → Infrastructure
- C) Infrastructure → Repositories → Services → Entry point (composition root)
- D) Any order — DI containers handle the ordering automatically.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 9  | A class that accepts a `MySQLDatabase` object via its constructor is fully decoupled from MySQL. | |
| 10 | The composition root should be the only place in the application where `new` is called on service-level classes. | |
| 11 | Constructor injection works without any framework — it is pure PHP. | |
| 12 | If a class needs a dependency, it is acceptable to call `new ConcreteClass()` inside a private helper method. | |
| 13 | An anonymous class that implements `LoggerInterface` can be passed to a constructor typed against `LoggerInterface`. | |
| 14 | Constructor property promotion (`private DatabaseInterface $db` in the parameter list) produces a different result at runtime than the traditional declare-then-assign approach. | |

---

## Section C — Short Answer

**Q15.** Explain in two sentences why typing a constructor parameter as `DatabaseInterface` (an interface) rather than `MySQLDatabase` (a concrete class) is important, even when the object is passed in from outside either way.

*Your answer:*

---

**Q16.** A class `OrderService` needs three dependencies: a gateway, a database, and a logger. Write the constructor signature using PHP 8 property promotion, typed against interfaces.

*Your answer:*

---

**Q17.** What does "the class is always in a valid state after construction" mean in the context of constructor injection, and why is this an advantage over setter injection?

*Your answer:*

---

## Section D — Code Reading

**Q18.** How many coupling violations remain in the following class after refactoring? List each one.

```php
class ReportService {
    public function __construct(
        private DatabaseInterface $db,
        private MySQLDatabase     $readReplica,
        private LoggerInterface   $logger
    ) {}

    public function generate(int $reportId): array {
        $this->logger->log('INFO', "Generating report #{$reportId}");
        $rows = $this->db->query('SELECT * FROM reports WHERE id = ?', [$reportId]);
        $raw  = $this->readReplica->query('SELECT * FROM raw_data WHERE report_id = ?', [$reportId]);
        return array_merge($rows, $raw);
    }
}
```

*Your answer:*

---

**Q19.** What will the following code output? Write the output exactly, or write "TypeError / Fatal error" and explain why.

```php
<?php
declare(strict_types=1);

interface Greeter {
    public function greet(string $name): string;
}

class FormalGreeter implements Greeter {
    public function greet(string $name): string { return "Good day, {$name}."; }
}

class WelcomeService {
    public function __construct(private Greeter $greeter) {}
    public function welcome(string $name): void {
        echo $this->greeter->greet($name) . "\n";
    }
}

$service = new WelcomeService(new FormalGreeter());
$service->welcome('Alice');

$casual = new class implements Greeter {
    public function greet(string $name): string { return "Hey, {$name}!"; }
};
$service2 = new WelcomeService($casual);
$service2->welcome('Bob');
```

*Your answer:*

---

**Q20.** This code attempts to use constructor injection. Identify every remaining violation.

```php
class PaymentProcessor {
    private StripeGateway    $gateway;
    private LoggerInterface  $logger;

    public function __construct(LoggerInterface $logger) {
        $this->gateway = new StripeGateway('sk_live_abc123');
        $this->logger  = $logger;
    }

    public function charge(float $amount, string $token): bool {
        $this->logger->log('INFO', "Charging R{$amount}");
        return $this->gateway->charge($amount, $token);
    }
}
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
| 1 | **B** | DI's core principle: receive dependencies from outside, do not create them internally. |
| 2 | **C** | Constructor injection makes dependencies mandatory and visible — the class cannot be constructed without them, so it is always in a valid state. |
| 3 | **B** | Even though the object is passed in, the concrete type `MySQLDatabase` means only that one class is accepted. An interface type would allow fakes, test doubles, or alternative implementations. |
| 4 | **B** | The composition root is the application entry point — the one place where all `new` calls on services live, and where dependencies are wired together. |
| 5 | **B** | Six or more injected dependencies often indicates the class is handling too many responsibilities (SRP violation). Consider splitting it. |
| 6 | **B** | Property promotion: `private DatabaseInterface $db` in the parameter list declares the property, type-hints it, and assigns the value — all in one line. |
| 7 | **C** | Any class implementing `LoggerInterface` can be passed — including named classes, anonymous classes, and any future logger implementation. |
| 8 | **C** | Infrastructure is built first (it has no dependencies), then repositories (depend on infrastructure), then services (depend on repositories), and finally the entry point wires all of them together. |

## Section B
| # | Answer | Explanation |
|---|--------|-------------|
| 9  | **F** | The property type `MySQLDatabase` still couples the class to MySQL. The parameter should be typed against `DatabaseInterface` to be truly decoupled. |
| 10 | **T** | The composition root is the designated location for all `new` on services. Business classes should be `new`-free. |
| 11 | **T** | Constructor injection is just a PHP design pattern — no framework, no container, no annotations required. |
| 12 | **F** | `new ConcreteClass()` inside any method body (not just the constructor) is a coupling violation — the smell applies wherever services are created. |
| 13 | **T** | Any object implementing the interface satisfies the type hint — including anonymous class instances. |
| 14 | **F** | Constructor property promotion is syntactic sugar — the generated bytecode is identical to declaring the property and assigning it in the constructor body. |

## Section C

**Q15 — Model answer:**
When the parameter type is `DatabaseInterface`, the class accepts any object implementing that interface — including in-memory fakes, spies, and future PostgreSQL or SQLite implementations. When the type is `MySQLDatabase`, only that one concrete class is accepted, making it impossible to pass a test double or swap to a different database engine without editing the class itself.

**Q16 — Model answer:**
```php
public function __construct(
    private PaymentGatewayInterface $gateway,
    private DatabaseInterface       $db,
    private LoggerInterface         $logger
) {}
```

**Q17 — Model answer:**
"Always in a valid state" means that after calling `new OrderService(...)`, every dependency the class needs is guaranteed to be assigned and ready to use. No method can be called before the class is correctly initialised. With setter injection, dependencies are set after construction, which means there is a window where a method could be called before a required dependency has been injected — leading to a null reference error. Constructor injection eliminates that window.

## Section D

**Q18 — Answer:**
One violation remains: `private MySQLDatabase $readReplica` is a **concrete property type**. The class depends on the specific `MySQLDatabase` class for the read replica, not on `DatabaseInterface`. If you want to swap the read replica to PostgreSQL or an in-memory fake for testing, you cannot — only `MySQLDatabase` is accepted.
`private DatabaseInterface $db` and `private LoggerInterface $logger` are correctly typed against interfaces.
Total remaining violations: **1** (concrete-property on `$readReplica`).

**Q19 — Answer:**
```
Good day, Alice.
Hey, Bob!
```
`WelcomeService` accepts any `Greeter` implementation. `FormalGreeter` and the anonymous class both implement `Greeter`. Polymorphism: `greet()` dispatches to each class's implementation. No errors.

**Q20 — Answer:**
Three violations remain:
1. `private StripeGateway $gateway` — **concrete-property**: property typed as `StripeGateway`, not `PaymentGatewayInterface`. Only `StripeGateway` can ever be the gateway.
2. `new StripeGateway('sk_live_abc123')` — **new-in-constructor**: the class creates its own gateway, taking responsibility for choosing and constructing it.
3. `'sk_live_abc123'` — **hardcoded-config**: the live Stripe API key is embedded in the source code.

Fix: change the property type to `PaymentGatewayInterface`, remove the `new` call, and accept a `PaymentGatewayInterface $gateway` constructor parameter. The API key moves to the composition root where `new StripeGateway(getenv('STRIPE_KEY'))` is called.

---

## Score Guide

| Score | Verdict |
|-------|---------|
| 18–20 | Ready for Lesson 3.3 — strong DI mastery. |
| 14–17 | Re-read the README sections for any missed questions, then move on. |
| Below 14 | Re-run the examples, redo the challenge, then retake the quiz before continuing. |