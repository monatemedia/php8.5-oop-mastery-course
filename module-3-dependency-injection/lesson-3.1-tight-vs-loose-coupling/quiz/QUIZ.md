# Quiz — Lesson 3.1: Tight vs Loose Coupling
> Complete this quiz **without** looking at any example or solution files.
> Write your answers before checking the answer key at the bottom.
> Any question you get wrong is a reading target.

---

## Section A — Multiple Choice

**Q1.** Which of the following is the clearest sign of tight coupling?

- A) A class that has more than five methods.
- B) A class whose constructor calls `new ConcreteService()` on its dependencies.
- C) A class that implements two interfaces.
- D) A class that uses a trait for shared behaviour.

---

**Q2.** A class `ReportService` has the property `private MySQLDatabase $db`. What type of coupling is this?

- A) Message coupling — it communicates only via a contract.
- B) Data coupling — it passes only the data it needs.
- C) Concrete-property coupling — it depends on the implementation, not an abstraction.
- D) Content coupling — it accesses private internals.

---

**Q3.** Which of the following uses of `new` is **acceptable** and does NOT represent a coupling smell?

- A) `$this->logger = new FileLogger('/var/log/app.log');` inside a service constructor.
- B) `$this->db = new MySQLDatabase('localhost', 'app');` inside a repository constructor.
- C) `return new Money(4999, 'ZAR');` inside a method that calculates a price.
- D) `$this->mailer = new SmtpMailer('smtp.example.com', 587);` inside a service constructor.

---

**Q4.** You have a tightly coupled `OrderService` that creates its own `StripeGateway`. The business needs to add a PayFast gateway option. What does this require?

- A) Nothing — you can add PayFast without touching `OrderService`.
- B) Editing `OrderService` directly — the gateway is hardwired inside it.
- C) Only adding a new `PayFastGateway` class — `OrderService` detects it automatically.
- D) Deleting `OrderService` and rewriting it from scratch.

---

**Q5.** Which statement about `SomeClass::getInstance()` (the Singleton pattern) and coupling is **true**?

- A) Singletons are the preferred way to share dependencies — they are always available.
- B) Singletons create hidden dependencies — callers depend on global state they cannot swap or test with.
- C) Singletons are fine as long as only one class uses them.
- D) Singletons are equivalent to constructor injection — both provide a shared instance.

---

**Q6.** A tightly coupled class is described as "untestable." What does this mean specifically?

- A) The class has too many methods to write tests for.
- B) The class cannot be instantiated or its logic exercised in a test without requiring real infrastructure (database, network, filesystem).
- C) The class has private methods that cannot be accessed from a test.
- D) The class uses magic numbers that make assertions hard to write.

---

**Q7.** What is the fundamental problem with calling `new ConcreteClass()` inside a constructor?

- A) It uses extra memory compared to injecting the dependency.
- B) The class takes on three responsibilities it should not have: deciding which class to use, knowing how to construct it, and managing its lifetime.
- C) It prevents the use of interfaces.
- D) It makes the class run slower because the dependency is created eagerly.

---

**Q8.** Moving from tight coupling to loose coupling means the `new` calls for services move to:

- A) The test file — tests are responsible for creating everything.
- B) The composition root — the single place in the application where everything is wired together.
- C) A static factory method inside each service.
- D) The global scope at the top of the entry point file.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 9  | A class with no `new` calls on services is guaranteed to be loosely coupled. | |
| 10 | Tight coupling makes it impossible to test business logic without real infrastructure. | |
| 11 | Using a concrete class as a property type (`private MySQLDatabase $db`) is a coupling violation even if the object is injected via the constructor. | |
| 12 | Calling `new Money(500, 'ZAR')` inside a service method is always a coupling smell. | |
| 13 | The primary benefit of loose coupling is faster application performance. | |
| 14 | A class that depends on an interface instead of a concrete class can have any implementation of that interface swapped in without being modified. | |

---

## Section C — Short Answer

**Q15.** Explain in two sentences: what is the difference between **tight coupling** and **loose coupling**? Use the terms "concrete class" and "interface" in your answer.

*Your answer:*

---

**Q16.** A colleague argues: *"I always use `new` in my constructors — it makes the code simpler because everything is in one place."* Identify the specific problem this causes when the team wants to write unit tests for that class.

*Your answer:*

---

**Q17.** Name and briefly describe the **three costs** of tight coupling covered in this lesson.

*Your answer:*

---

## Section D — Code Reading

**Q18.** Identify every coupling violation in the following class. Name each violation type.

```php
class NotificationService {
    private SlackWebhook $slack;
    private FileLogger   $logger;

    public function __construct() {
        $this->slack  = new SlackWebhook('https://hooks.slack.com/services/T00000/B00000/XXXXXXXX');
        $this->logger = new FileLogger('/var/log/notifications.log');
    }

    public function notify(string $message, int $level): void {
        if ($level > 5) {
            $this->slack->send($message);
        }
        $this->logger->write("NOTIFICATION: {$message}");
    }
}
```

*Your answer:*

---

**Q19.** The following class has been improved. For each numbered comment, identify what coupling violation was fixed and how.

```php
// BEFORE
class ReportService {
    public function __construct() {
        $this->db     = new MySQLDatabase('localhost', 'reports');    // 1
        $this->cache  = new RedisCache('redis.local', 6379);          // 2
        $this->logger = FileLogger::getInstance();                     // 3
    }
}

// AFTER
class ReportService {
    public function __construct(
        private DatabaseInterface $db,      // 1 fixed
        private CacheInterface    $cache,   // 2 fixed
        private LoggerInterface   $logger   // 3 fixed
    ) {}
}
```

*Your answer:*

---

**Q20.** You are reviewing this controller. List every coupling violation, then explain what the cascading effect is when `new CheckoutService()` is called.

```php
class CheckoutController {
    private CheckoutService $service;
    private FileLogger      $logger;

    public function __construct() {
        $this->service = new CheckoutService();
        $this->logger  = new FileLogger('/logs/checkout.log');
    }

    public function handle(array $request): string {
        $this->logger->write("Request received");
        return json_encode($this->service->process($request));
    }
}
```

And `CheckoutService` is defined as:
```php
class CheckoutService {
    public function __construct() {
        $this->gateway = new StripeGateway('sk_live_...');
        $this->db      = new MySQLDatabase('prod-db.internal', 'orders');
        $this->mailer  = new SmtpMailer('smtp.prod', 587);
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
| 1 | **B** | `new ConcreteService()` inside a constructor is the canonical coupling smell — the class takes responsibility for creating its own dependencies. |
| 2 | **C** | A concrete class as a property type means the class depends on the implementation, not an abstraction. This is a concrete-property coupling violation. |
| 3 | **C** | `new Money(...)` creates a value object — a pure data structure with no external dependencies. This is always acceptable. The other three all create service-level objects that connect to infrastructure. |
| 4 | **B** | With `new StripeGateway()` hardwired inside `OrderService`, adding PayFast requires editing `OrderService` directly — a violation of OCP. |
| 5 | **B** | Singletons create hidden global state. The class depends on a specific concrete instance that exists in global scope, which cannot be swapped or replaced for testing. |
| 6 | **B** | Untestability means the class cannot be exercised in a test without real infrastructure — database must be running, network must be available, etc. |
| 7 | **B** | `new` inside a constructor forces the class to decide which class, know how to build it, and manage its lifetime — three responsibilities it should not have. |
| 8 | **B** | The composition root is the single place (typically the application bootstrap) where all dependencies are wired together. Business classes should be free of `new`. |

## Section B
| # | Answer | Explanation |
|---|--------|-------------|
| 9  | **F** | A class might still use static calls, singletons, or concrete property types without any `new`. These are also coupling violations. |
| 10 | **T** | When a class creates its dependencies with `new`, tests must bring up real databases, network services, etc. to exercise any business logic. |
| 11 | **T** | The property type `private MySQLDatabase $db` means the class is committed to MySQL even if the instance is injected. The type hint should be `DatabaseInterface`. |
| 12 | **F** | `new Money(...)` creates a value object — a pure data structure. This is explicitly called out as acceptable in Section 3 of the README. |
| 13 | **F** | The primary benefits of loose coupling are testability, flexibility, and replaceability — not performance. |
| 14 | **T** | This is the entire point of interface-based coupling — any implementation can be substituted without modifying the depending class. |

## Section C

**Q15 — Model answer:**
Tight coupling means a class depends on a specific **concrete class** — it knows exactly which implementation it is using and often creates it with `new`. Loose coupling means a class depends only on an **interface** — it knows only the contract and receives any conforming implementation from outside, with no knowledge of how the dependency is built or which concrete class provides it.

**Q16 — Model answer:**
When the class creates all its dependencies with `new` in the constructor, instantiating it in a test immediately triggers all those real infrastructure connections — a live database, an SMTP server, a filesystem path. None of these may be available in a test environment, and even if they are, using them in a unit test is slow, brittle, and introduces side effects. The business logic inside the class cannot be tested without the entire infrastructure stack running — making true unit testing impossible.

**Q17 — Model answer:**
1. **Untestability** — Cannot test business logic without running real infrastructure (database, SMTP, filesystem). Tests become slow integration tests or cannot be written at all.
2. **Inflexibility** — Changing a dependency (e.g. switching from Stripe to PayFast) requires editing the class that uses it, which risks introducing regressions in working code.
3. **Hard to swap** — Cannot use different implementations in different contexts (test/staging/production) because the concrete implementation is hardwired inside the class.

## Section D

**Q18 — Answer:**
Four violations:
1. `private SlackWebhook $slack` — **concrete-property**: property typed as a concrete class, not an interface.
2. `private FileLogger $logger` — **concrete-property**: same issue.
3. `new SlackWebhook('https://hooks.slack.com/...')` — **new-in-constructor** AND **hardcoded-config**: creates own dependency with a hardwired webhook URL.
4. `new FileLogger('/var/log/notifications.log')` — **new-in-constructor** AND **hardcoded-config**: creates own logger with a hardwired file path.
Additional note: `$level > 5` is a **magic-value** — the number 5 has no named context.

**Q19 — Answer:**
1. `new MySQLDatabase('localhost', 'reports')` — **new-in-constructor** and **hardcoded-config** fixed by injecting `DatabaseInterface $db`. The class no longer decides which database to use or knows the connection details.
2. `new RedisCache('redis.local', 6379)` — **new-in-constructor** and **hardcoded-config** fixed by injecting `CacheInterface $cache`. Any cache implementation can now be substituted.
3. `FileLogger::getInstance()` — **singleton-access** fixed by injecting `LoggerInterface $logger`. The hidden global dependency is gone — any logger can be passed in, including a `NullLogger` for tests.

**Q20 — Answer:**
Violations in `CheckoutController`:
1. `private CheckoutService $service` — **concrete-property**
2. `private FileLogger $logger` — **concrete-property**
3. `new CheckoutService()` — **new-in-constructor**
4. `new FileLogger('/logs/checkout.log')` — **new-in-constructor** and **hardcoded-config**

Cascading effect of `new CheckoutService()`: because `CheckoutService` also creates its dependencies with `new`, instantiating `CheckoutController` triggers the entire chain: `new CheckoutService()` → `new StripeGateway('sk_live_...')` (live API key, network connection) + `new MySQLDatabase('prod-db.internal', 'orders')` (network, database connection) + `new SmtpMailer('smtp.prod', 587)` (SMTP connection). Creating a single `CheckoutController` in a test immediately attempts three real infrastructure connections before a single line of business logic runs.

---

## Score Guide

| Score | Verdict |
|-------|---------|
| 18–20 | Ready for Lesson 3.2 — strong coupling recognition. |
| 14–17 | Re-read the README sections for any missed questions, then move on. |
| Below 14 | Re-run the examples, redo the challenge, then retake the quiz before continuing. |