# Quiz — Lesson 3.3: Setter & Interface Injection
> Complete this quiz **without** looking at any example or solution files.
> Write your answers before checking the answer key at the bottom.
> Any question you get wrong is a reading target.

---

## Section A — Multiple Choice

**Q1.** When should you use setter injection instead of constructor injection?

- A) When the dependency is required for the class to function at all.
- B) When the dependency is optional — the class has a sensible default and works without it.
- C) When you want to improve application performance by deferring creation.
- D) When the class needs to be serialised.

---

**Q2.** What is the Null Object pattern in the context of setter injection?

- A) Storing `null` as the default value and using `?->` to call methods safely.
- B) An implementation of the interface that does nothing — used as the default value so null checks are unnecessary.
- C) A PHP built-in class that silently absorbs any method call.
- D) A factory that creates empty objects on demand.

---

**Q3.** A class has `private ?LoggerInterface $logger = null`. A method calls `$this->logger?->log(...)`. What problem does the Null Object pattern solve here?

- A) The `?->` syntax does not work with interfaces.
- B) The `?->` is unnecessary — if a Null Object is always assigned, `$this->logger->log()` can be called directly without null checks.
- C) The logger is still tightly coupled to the concrete type.
- D) `null` cannot be stored as a private property type.

---

**Q4.** What does a fluent setter return?

- A) `void`
- B) `$this`
- C) `static`
- D) `self`

---

**Q5.** Interface injection uses an "Aware" interface. What does this interface declare?

- A) The list of dependencies the class requires via its constructor.
- B) A `set*()` method that the framework or container will call after construction.
- C) The public methods the class exposes to its callers.
- D) Which interface the class implements for type safety.

---

**Q6.** Which PHP-FIG standard directly uses the interface injection pattern with a corresponding trait?

- A) PSR-7 (HTTP Messages)
- B) PSR-4 (Autoloading)
- C) PSR-3 (Logger Interface, LoggerAwareInterface + LoggerAwareTrait)
- D) PSR-12 (Coding Style)

---

**Q7.** A class needs a payment gateway and a logger. The payment gateway is required; the logger is optional. Which combination is correct?

- A) Both via constructor injection.
- B) Gateway via constructor; logger via setter with NullLogger default.
- C) Both via setter injection.
- D) Gateway via setter; logger via constructor.

---

**Q8.** A framework sees a service implementing `LoggerAwareInterface`. What does it do?

- A) It throws an exception — services should not implement awareness interfaces.
- B) It automatically calls `setLogger($logger)` on the service after wiring.
- C) It generates a concrete logger class for the service.
- D) It injects the logger via the constructor parameter list.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 9  | A Null Object must extend the concrete class it replaces. | |
| 10 | Using setter injection for a required dependency is dangerous because the class can be used before the dependency is set. | |
| 11 | `LoggerAwareTrait` is a PSR-3 concept that provides a free implementation of the `setLogger()` setter. | |
| 12 | With a NullLogger default, calling `$this->logger->log(...)` is always safe — no null check needed. | |
| 13 | Fluent setters should return `self` rather than `static` for correct behaviour in inherited classes. | |
| 14 | Interface injection requires the class to modify its constructor to receive the dependency. | |

---

## Section C — Short Answer

**Q15.** Explain in two sentences why defaulting to a Null Object in a setter-injected class is better than defaulting to `null` and using the nullsafe operator `?->` throughout the class body.

*Your answer:*

---

**Q16.** A colleague writes this:
```php
class ReportService {
    private ?CacheInterface $cache = null;

    public function setCache(?CacheInterface $cache): void {
        $this->cache = $cache;
    }

    public function generate(int $id): array {
        $cached = $this->cache?->get("report:{$id}");
        if ($cached !== null) return $cached;
        $data = $this->db->query(...);
        $this->cache?->set("report:{$id}", $data);
        return $data;
    }
}
```
Suggest the specific improvement from this lesson that would clean up this code, and show the key change required.

*Your answer:*

---

**Q17.** Explain the difference between **setter injection** and **interface injection**. In what scenario would you choose interface injection over setter injection?

*Your answer:*

---

## Section D — Code Reading

**Q18.** What will the following code output? Write the output exactly, or write "Fatal error / TypeError" and explain why.

```php
<?php
declare(strict_types=1);

interface Logger {
    public function log(string $message): void;
}

class NullLogger implements Logger {
    public function log(string $message): void {}
}

class ConsoleLogger implements Logger {
    public function log(string $message): void {
        echo "[LOG] {$message}\n";
    }
}

class Service {
    private Logger $logger;

    public function __construct() {
        $this->logger = new NullLogger();
    }

    public function setLogger(Logger $logger): static {
        $this->logger = $logger;
        return $this;
    }

    public function run(string $task): void {
        $this->logger->log("Running: {$task}");
        echo "Done: {$task}\n";
    }
}

$s1 = new Service();
$s1->run('task-A');

$s2 = (new Service())->setLogger(new ConsoleLogger());
$s2->run('task-B');
```

*Your answer:*

---

**Q19.** What will the following code output? Write the output exactly, or write "Fatal error / TypeError" and explain why.

```php
<?php
declare(strict_types=1);

interface Notifiable {
    public function setNotifier(callable $notifier): void;
}

trait NotifiableTrait {
    private $notifier = null;

    public function setNotifier(callable $notifier): void {
        $this->notifier = $notifier;
    }

    protected function notify(string $message): void {
        if ($this->notifier !== null) {
            ($this->notifier)($message);
        }
    }
}

class OrderProcessor implements Notifiable {
    use NotifiableTrait;

    public function process(string $orderId): void {
        $this->notify("Order {$orderId} processed");
        echo "Order {$orderId} done\n";
    }
}

$p1 = new OrderProcessor();
$p1->process('ORD-001');

$p2 = new OrderProcessor();
$p2->setNotifier(fn(string $msg) => print("[NOTIFY] {$msg}\n"));
$p2->process('ORD-002');
```

*Your answer:*

---

**Q20.** Identify every injection pattern used in this code and explain whether each usage is correct or incorrect.

```php
interface CacheInterface { public function get(string $k): mixed; public function set(string $k, mixed $v): void; }
interface LoggerInterface { public function log(string $m): void; }
interface DatabaseInterface { public function query(string $sql): array; }

class NullCache implements CacheInterface { public function get(string $k): mixed { return null; } public function set(string $k, mixed $v): void {} }
class NullLogger implements LoggerInterface { public function log(string $m): void {} }

class DataService {
    private CacheInterface  $cache;
    private LoggerInterface $logger;

    public function __construct(
        private DatabaseInterface $db,   // A
        CacheInterface $cache = null,    // B — default null
        LoggerInterface $logger = null   // C — default null
    ) {
        $this->cache  = $cache  ?? new NullCache();
        $this->logger = $logger ?? new NullLogger();
    }

    public function setCache(CacheInterface $cache): static {  // D
        $this->cache = $cache;
        return $this;
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
| 1 | **B** | Setter injection is for optional dependencies. If the class cannot work without the dependency, it belongs in the constructor. |
| 2 | **B** | A Null Object implements the interface but does nothing — it is the "off" or "silent" state. No PHP built-in does this; you write it yourself. |
| 3 | **B** | With a Null Object always assigned, `$this->logger` is never `null` — direct calls `$this->logger->log()` are safe everywhere, eliminating the `?->` operator. |
| 4 | **C** | Fluent setters return `static` — the runtime class — so subclasses that chain setters get back the correct subtype, not the parent class. |
| 5 | **B** | The "Aware" interface declares a `set*()` method (e.g. `setLogger()`). A container that sees the service implementing it will call that method automatically. |
| 6 | **C** | PSR-3 defines `LoggerInterface`, `LoggerAwareInterface`, and `LoggerAwareTrait`. The pattern is used by Symfony, Laravel, and many other frameworks. |
| 7 | **B** | Gateway is required → constructor. Logger is optional → setter with NullLogger default. This is the exact scenario from Section 7 of the README. |
| 8 | **B** | A container that recognises `LoggerAwareInterface` calls `setLogger($logger)` automatically on any service that implements it — this is the value of interface injection. |

## Section B
| # | Answer | Explanation |
|---|--------|-------------|
| 9  | **F** | A Null Object implements the **interface**, not the concrete class. It has no relationship to the concrete implementation. |
| 10 | **T** | If a setter-injected dependency is actually required, calling a method before the setter is called leads to a null reference error or — with a NullLogger as default — silent wrong behaviour. Required deps must go in the constructor. |
| 11 | **T** | PSR-3 provides `LoggerAwareTrait` that implements `setLogger()` — any class can `use LoggerAwareTrait` to get the setter for free. |
| 12 | **T** | A NullLogger is always a valid `LoggerInterface` object. `$this->logger->log()` is safe because the property is never null — it is always either a NullLogger or a real logger. |
| 13 | **F** | Fluent setters should return `static` (not `self`) so that subclasses get the correct runtime type back from the setter. `self` always returns the class where the method is defined. |
| 14 | **F** | Interface injection does NOT require a constructor change. The setter is provided via a trait (`LoggerAwareTrait`), and the framework calls it after construction. The constructor is left untouched. |

## Section C

**Q15 — Model answer:**
With a Null Object default, every property is always a valid, callable object — `$this->logger->log()` can be called directly anywhere in the class without a null check. With `null` as the default, every call site must use `?->` to avoid a fatal error, which scatters defensive code throughout the class and creates a risk of forgetting one — leading to crashes when the dep is not injected.

**Q16 — Model answer:**
Replace the nullable default with a Null Object:
```php
private CacheInterface $cache;

public function __construct(...) {
    $this->cache = new NullCache(); // Never null
}

public function generate(int $id): array {
    $cached = $this->cache->get("report:{$id}"); // No ?-> needed
    if ($cached !== null) return $cached;
    $data = $this->db->query(...);
    $this->cache->set("report:{$id}", $data);   // No ?-> needed
    return $data;
}
```
All `?->` operators disappear. The type changes from `?CacheInterface` to `CacheInterface`.

**Q17 — Model answer:**
Setter injection means calling `setX($dep)` explicitly at the composition root — the caller decides when and whether to inject the optional dep. Interface injection means the class declares via an interface (e.g. `implements LoggerAwareInterface`) that it wants a dep — and a framework or container sees the interface and calls the setter automatically, without the caller needing to know about it. Choose interface injection for cross-cutting, framework-provided concerns (loggers, event dispatchers, request context) where you want the framework to handle wiring automatically across all services that need it.

## Section D

**Q18 — Answer:**
```
Done: task-A
[LOG] Running: task-B
Done: task-B
```
`$s1` uses the default NullLogger — `run('task-A')` logs silently and prints `"Done: task-A"`. `$s2` chains `setLogger(new ConsoleLogger())` — `run('task-B')` logs `[LOG] Running: task-B` then prints `"Done: task-B"`.

**Q19 — Answer:**
```
Order ORD-001 done
[NOTIFY] Order ORD-002 processed
Order ORD-002 done
```
`$p1` has no notifier set — `notify()` checks `$this->notifier !== null` (it is null), so nothing is printed from `notify()`. Only `"Order ORD-001 done"` is printed.
`$p2` has a closure set. `notify()` calls the closure with the message — `[NOTIFY] Order ORD-002 processed` is printed, then `"Order ORD-002 done"`.

**Q20 — Answer:**
- **A** `private DatabaseInterface $db` in constructor — **Constructor injection, correct.** `db` is required (the class queries it), so constructor is the right place.
- **B** `CacheInterface $cache = null` in constructor — **Hybrid approach, works but not ideal.** Passing nullable deps via the constructor with a null default is a valid pattern, but it blurs the line between constructor (required) and setter (optional). Preferred: remove from constructor and use a setter with NullCache default (as done in `__construct` body with `?? new NullCache()`). The `?? new NullCache()` inside the body rescues it by always assigning a valid object.
- **C** `LoggerInterface $logger = null` — same as B: functional, but conventional setter injection with a NullLogger default in the constructor body is cleaner.
- **D** `setCache(CacheInterface $cache): static` — **Setter injection, correct.** Returns `static` for fluent chaining. However, having both a constructor parameter (`C`) and a setter (`D`) for the same type of dependency creates confusion — pick one approach consistently. Remove the constructor parameter and rely solely on the setter with the NullCache default.

---

## Score Guide

| Score | Verdict |
|-------|---------|
| 18–20 | Ready for Lesson 3.4 — strong injection pattern mastery. |
| 14–17 | Re-read the README sections for any missed questions, then move on. |
| Below 14 | Re-run the examples, redo the challenge, then retake the quiz before continuing. |