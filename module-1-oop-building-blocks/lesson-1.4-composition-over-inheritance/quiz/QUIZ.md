# Quiz — Lesson 1.4: Composition over Inheritance
> Complete this quiz **without** looking at any example or solution files.
> Write your answers before checking the answer key at the bottom.
> Any question you get wrong is a reading target.

---

## Section A — Multiple Choice

**Q1.** What does "favour composition over inheritance" mean in practice?

- A) Never use the `extends` keyword under any circumstances.
- B) When you need to share behaviour between classes, prefer holding a reference to a collaborator over inheriting from a parent class.
- C) Always use traits instead of abstract classes.
- D) Use interfaces for everything and avoid all concrete classes.

---

**Q2.** You are writing `class NotificationService extends DatabaseService`. You apply the practical test: "Can I replace `extends DatabaseService` with `private DatabaseService $db` injected via constructor?" The answer is YES. What should you do?

- A) Keep the inheritance — both approaches are equally valid.
- B) Refactor to composition: remove `extends`, inject `DatabaseService` (or better, `DatabaseInterface`) via the constructor.
- C) Use a trait instead of either approach.
- D) Make `DatabaseService` abstract so the relationship is more explicit.

---

**Q3.** Which of the following is a correct use of inheritance?

- A) `class UserReport extends DatabaseConnection` — UserReport wants to use the query method.
- B) `class LoggingMailer extends SmtpMailer` — LoggingMailer wants to add logging to send().
- C) `class SalesReport extends ReportGenerator` — where `ReportGenerator` is an abstract class with a template method pipeline that subclasses fill in.
- D) `class UserModel extends BaseModel` — UserModel wants the `save()` and `delete()` methods.

---

**Q4.** A `LoggingGateway` class wraps a `PaymentGatewayInterface` to add logging before and after every `charge()` call. It implements `PaymentGatewayInterface` itself. What composition pattern is this?

- A) Constructor injection
- B) Setter injection
- C) Method parameter
- D) Delegating decorator

---

**Q5.** What is the "fragile base class problem"?

- A) Abstract classes are fragile and should never be used.
- B) A change to a parent class in a deep inheritance chain can break all subclasses, including ones several levels down that were never meant to be affected.
- C) Base classes compile slower than concrete classes.
- D) The `parent::` keyword causes unexpected behaviour in PHP.

---

**Q6.** Why does a deep inheritance chain make a DI container unable to wire the classes?

- A) Containers only work with interfaces, not classes.
- B) Deep chains require circular dependencies that containers cannot resolve.
- C) Dependencies are created with `new` inside parent constructors — they have no constructor parameters, so there is nothing for the container to read and resolve.
- D) PHP-DI does not support more than two levels of inheritance.

---

**Q7.** You have `BlogPost` and `VideoPost`. You want both to be accepted by a function `function process(??? $content)`. You do NOT need shared implementation — just a shared type. What is the correct approach?

- A) `class ContentBase` with `BlogPost extends ContentBase` and `VideoPost extends ContentBase`
- B) Define a `ContentInterface` that both implement — no shared parent class needed
- C) Use a trait to give both classes the same methods
- D) Use `mixed` as the type hint and check `instanceof` inside `process()`

---

**Q8.** The Null Object pattern is described in this lesson as an optional dependency default. Which statement about Null Objects is correct?

- A) A Null Object must extend the class it replaces.
- B) A Null Object is `null` stored in a nullable property — `?LoggerInterface`.
- C) A Null Object implements the interface but does nothing — it is the "off" state, eliminating null checks.
- D) Null Objects are only useful in testing and should never appear in production code.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 9  | Using composition instead of inheritance requires the class to implement an interface. | |
| 10 | An inheritance chain three or more levels deep is almost always a design smell. | |
| 11 | The decorator pattern achieves the same result as extending a class to override one method, but without any inheritance. | |
| 12 | Constructor injection is possible with inherited classes because the container reads the parent constructor. | |
| 13 | "Composition makes DI possible; inheritance makes DI impossible" is an absolute rule — there are no exceptions. | |
| 14 | A class that uses `extends` for a framework extension point (e.g. `extends TestCase`) is a legitimate use of inheritance. | |

---

## Section C — Short Answer

**Q15.** Explain in two sentences why a PHP-DI container can auto-wire `ComposedOrderService` but cannot auto-wire `InheritedOrderService`, even though both do the same work.

*Your answer:*

---

**Q16.** A colleague writes:
```php
class AdminUserModel extends UserModel {
    public function validate(): bool {
        return true; // admins are always valid
    }
}
```
Name the two design problems this introduces and state how composition would fix each one.

*Your answer:*

---

**Q17.** Describe the four composition patterns from this lesson. For each, give a one-line scenario where it is the right choice.

*Your answer:*

---

## Section D — Code Reading

**Q18.** How many coupling violations does the following code have? List each one by type (`new-in-constructor`, `concrete-property`, etc.).

```php
class ReportService extends DatabaseService {
    private FileLogger   $logger;
    private CsvFormatter $formatter;

    public function __construct() {
        parent::__construct('mysql:host=localhost;dbname=reports', 'root', '');
        $this->logger    = new FileLogger('/var/log/reports.log');
        $this->formatter = new CsvFormatter();
    }

    public function generate(int $reportId): string {
        $rows = $this->query("SELECT * FROM reports WHERE id = {$reportId}");
        return $this->formatter->format($rows);
    }
}
```

*Your answer:*

---

**Q19.** Refactor the following inheritance-based code to use composition. Keep the output identical. Show only the refactored class definition and the wiring.

```php
abstract class BaseExporter {
    protected array $data = [];
    abstract protected function format(): string;

    public function export(array $data): string {
        $this->data = $data;
        return $this->format();
    }
}

class JsonExporter extends BaseExporter {
    protected function format(): string {
        return json_encode($this->data);
    }
}

class CsvExporter extends BaseExporter {
    protected function format(): string {
        if (empty($this->data)) return '';
        return implode(',', array_keys(reset($this->data))) . "\n"
             . implode("\n", array_map(fn($r) => implode(',', $r), $this->data));
    }
}
```

*Your answer:*

---

**Q20.** What will the following code output? Write the output exactly, or identify the design flaw and describe what it will cause at runtime.

```php
<?php
declare(strict_types=1);

interface Notifiable {
    public function notify(string $message): bool;
}

class NullNotifier implements Notifiable {
    public function notify(string $message): bool { return true; }
}

class AlertService {
    private Notifiable $notifier;

    public function __construct(private string $channel) {
        $this->notifier = new NullNotifier();
    }

    public function setNotifier(Notifiable $notifier): static {
        $this->notifier = $notifier;
        return $this;
    }

    public function alert(string $message): void {
        $sent = $this->notifier->notify("[{$this->channel}] {$message}");
        echo $sent ? "Sent\n" : "Failed\n";
    }
}

$spy = new class implements Notifiable {
    public array $calls = [];
    public function notify(string $message): bool {
        $this->calls[] = $message;
        echo "Notified: {$message}\n";
        return true;
    }
};

$service = (new AlertService('PROD'))->setNotifier($spy);
$service->alert('Server down');
$service->alert('DB slow');

echo "Total calls: " . count($spy->calls) . "\n";
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
| 1 | **B** | Composition over inheritance means preferring to hold a reference to a collaborator (via injection) over inheriting implementation from a parent. It does not mean never using `extends`. |
| 2 | **B** | The practical test said YES — you can replace inheritance with a field. That means you should. Type the parameter against `DatabaseInterface`, not the concrete class, for full decoupling. |
| 3 | **C** | The Template Method Pattern in an abstract class is a correct use of inheritance — the class is explicitly designed for extension and subclasses fill in specific steps. Options A, B, D all describe code-reuse or override scenarios that are better handled by composition. |
| 4 | **D** | `LoggingGateway` wraps a `PaymentGatewayInterface` and delegates the real work to it while adding behaviour — this is the delegating decorator pattern. |
| 5 | **B** | The fragile base class problem: a change to a base class can break subclasses several levels down that you never intended to affect. |
| 6 | **C** | When dependencies are created with `new` inside parent constructors, the outer class has no constructor parameters. A container reads constructor parameters to resolve dependencies — if there are none, it has nothing to work with. |
| 7 | **B** | An interface provides the type contract without shared implementation. `ContentInterface` is all that is needed. No abstract base class, no traits, no `mixed`. |
| 8 | **C** | A Null Object implements the interface with no-op methods. It is the "off" state that eliminates null checks throughout the class. It is never `null` — it is always a valid, callable object. |

## Section B
| # | Answer | Explanation |
|---|--------|-------------|
| 9  | **F** | Composition just means holding a collaborator reference instead of inheriting. The class does not need to implement an interface for composition to be used — though typing against an interface is strongly recommended. |
| 10 | **T** | Three or more levels almost always indicates code reuse, type grouping, or behaviour override that is better solved with traits, interfaces, or composition. |
| 11 | **T** | The decorator pattern wraps any implementation of an interface and adds behaviour. It achieves exactly what "extend and override one method" achieves, without any inheritance. |
| 12 | **F** | Containers read the constructor of the class they are resolving. If that constructor calls `parent::__construct()` with hardwired dependencies, the container sees no resolvable parameters. |
| 13 | **F** | Framework extension points (`extends TestCase`, `extends Migration`) are legitimate uses of inheritance where the framework authors designed the class for extension. |
| 14 | **T** | Framework extension points are the clearest legitimate use of inheritance — the framework authors explicitly designed these classes for `extends`. |

## Section C

**Q15 — Model answer:**
`ComposedOrderService` has four constructor parameters typed as interfaces — the container reads these with Reflection, resolves each interface to its bound concrete class, and instantiates the service with all dependencies wired. `InheritedOrderService` has a no-parameter constructor (or one that calls `parent::__construct()` with hardwired values) — there is nothing for the container to read, so it cannot inject anything and the dependencies remain fixed to their hardwired implementations.

**Q16 — Model answer:**
Problem 1 is an **LSP violation**: `AdminUserModel::validate()` always returns `true`, weakening the postcondition of `UserModel::validate()`. Code that calls `validate()` expecting it to return `false` for invalid data will silently misbehave for admin users. Composition fix: `AdminUserModel` is not a subtype of `UserModel` — it should have its own `validate()` that stands alone, not one that breaks the parent contract.
Problem 2 is the **inheritance-for-type-grouping smell**: if the only goal is to have both types accepted by `function processUser(UserModel $u)`, define a `UserInterface` that both implement — no shared parent needed.

**Q17 — Model answer:**
1. **Constructor injection** — required collaborator, class cannot function without it. Scenario: `OrderService` needs a `PaymentGatewayInterface` — always required, inject it via constructor.
2. **Setter injection** — optional collaborator with a NullObject default. Scenario: `ReportService` optionally logs output — defaults to `NullLogger`, caller opts in via `setLogger()`.
3. **Method parameter** — per-call collaborator, varies per invocation. Scenario: `PriceCalculator::calculate(Money $price, DiscountStrategy $discount)` — the discount changes per cart item, not per service instance.
4. **Delegating decorator** — wraps an interface to add behaviour without modification. Scenario: `LoggingGateway implements PaymentGatewayInterface` — wraps any gateway to add logging without touching the gateway's source code.

## Section D

**Q18 — Answer:**
Five violations:
1. `extends DatabaseService` — **inheritance-for-code-reuse smell** (and potential concrete coupling depending on DatabaseService)
2. `private FileLogger $logger` — **concrete-property** (should be `LoggerInterface`)
3. `private CsvFormatter $formatter` — **concrete-property** (should be `FormatterInterface`)
4. `new FileLogger('/var/log/reports.log')` — **new-in-constructor** + **hardcoded-config**
5. `new CsvFormatter()` — **new-in-constructor**
6. `parent::__construct('mysql:host=localhost;...', 'root', '')` — **hardcoded-config** passed up through the chain

Total: 6 violations (5 distinct violation *types* — hardcoded-config appears twice).

**Q19 — Model answer (composition refactor):**
```php
interface FormatterInterface {
    public function format(array $data): string;
}

class JsonFormatter implements FormatterInterface {
    public function format(array $data): string { return json_encode($data); }
}

class CsvFormatter implements FormatterInterface {
    public function format(array $data): string {
        if (empty($data)) return '';
        return implode(',', array_keys(reset($data))) . "\n"
             . implode("\n", array_map(fn($r) => implode(',', $r), $data));
    }
}

class DataExporter {
    public function __construct(private FormatterInterface $formatter) {}

    public function export(array $data): string {
        return $this->formatter->format($data);
    }
}

// Wiring
$jsonExporter = new DataExporter(new JsonFormatter());
$csvExporter  = new DataExporter(new CsvFormatter());
```
`BaseExporter` is gone. `DataExporter` has no parent. Each formatter is a standalone, injectable class. Output is identical.

**Q20 — Answer:**
```
Notified: [PROD] Server down
Sent
Notified: [PROD] DB slow
Sent
Total calls: 2
```
`AlertService` constructor defaults `$this->notifier = new NullNotifier()`. `setNotifier($spy)` replaces it with the anonymous spy (returns `static` — fluent). `alert('Server down')` calls `$spy->notify('[PROD] Server down')` — spy prints `"Notified: [PROD] Server down"` and returns `true` — service prints `"Sent"`. Same for the second alert. `count($spy->calls)` is 2.

---

## Score Guide

| Score | Verdict |
|-------|---------|
| 18–20 | Module 1 complete — ready for Module 2 (Lesson 2.0 LSP). |
| 14–17 | Re-read the README sections for any missed questions, then move on. |
| Below 14 | Re-run the examples, redo the challenge, then retake the quiz before continuing. |