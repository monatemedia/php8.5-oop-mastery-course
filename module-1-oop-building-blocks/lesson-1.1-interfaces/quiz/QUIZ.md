# Quiz — Lesson 1.1: Interfaces
> Complete this quiz **without** looking at the examples or solution files.
> Write your answers down before checking the answer key at the bottom.
> Treat any question you get wrong as a reading target — go back to the README section that covers it.

---

## Section A — Multiple Choice
*Circle or write the letter of the best answer.*

---

**Q1.** Which of the following statements about PHP interfaces is **false**?

- A) An interface can declare constants.
- B) An interface can contain a concrete method with a body.
- C) A class can implement multiple interfaces at once.
- D) An interface method is implicitly abstract.

---

**Q2.** A class `Dog` implements the interface `Animal`. Which line correctly type-hints a function parameter to accept any `Animal`, including `Dog`?

- A) `function feed(Dog $animal): void`
- B) `function feed(Animal|Dog $animal): void`
- C) `function feed(Animal $animal): void`
- D) `function feed(implements Animal $animal): void`

---

**Q3.** You define:
```php
interface Flyable {
    public function fly(): void;
}
interface Swimmable {
    public function swim(): void;
}
interface Amphibious extends Flyable, Swimmable {
    public function land(): void;
}
```
A class `Duck implements Amphibious`. How many methods must `Duck` provide?

- A) 1 — only `land()`
- B) 2 — `fly()` and `swim()`
- C) 3 — `fly()`, `swim()`, and `land()`
- D) 0 — interface methods are optional

---

**Q4.** What happens if a class declares `implements Countable` but does not provide the `count()` method?

- A) PHP silently ignores the missing method.
- B) PHP throws a fatal error when the class is loaded.
- C) PHP throws a warning but continues execution.
- D) The class inherits a default `count()` from the interface.

---

**Q5.** You have:
```php
interface Logger {
    const DEFAULT_CHANNEL = 'app';
    public function log(string $message): void;
}

class FileLogger implements Logger {
    public function log(string $message): void {
        echo $message;
    }
}
```
Which of the following lines is **valid**?

- A) `echo Logger::DEFAULT_CHANNEL;`
- B) `echo FileLogger::DEFAULT_CHANNEL;`
- C) `$logger = new FileLogger(); echo $logger::DEFAULT_CHANNEL;`
- D) All of the above.

---

**Q6.** What is the primary purpose of type-hinting a function parameter against an **interface** rather than a **concrete class**?

- A) It makes the code run faster.
- B) It allows any class implementing that interface to be passed, making the function reusable and decoupled.
- C) It prevents subclasses from overriding the method.
- D) It automatically generates the method implementation.

---

**Q7.** Consider:
```php
interface Readable {
    public function read(): string;
}
interface Writable {
    public function write(string $data): void;
}
interface ReadWritable extends Readable, Writable {}
```
A function is declared as `function process(Readable $r): void`. Which objects can be passed to it?

- A) Only objects of classes that directly implement `Readable`.
- B) Objects of classes that implement `Readable`, or `ReadWritable` (which extends `Readable`).
- C) Only objects of classes that implement `ReadWritable`.
- D) Any object — PHP does not enforce parameter types.

---

## Section B — True / False

Write **T** (true) or **F** (false) for each statement.

| # | Statement | Answer |
|---|-----------|--------|
| 8 | An interface can extend more than one interface. | |
| 9 | You can instantiate an interface directly with `new`. | |
| 10 | A class that implements an interface can also extend an abstract class. | |
| 11 | Interface constants can be accessed using the interface name directly (e.g. `MyInterface::CONST`). | |
| 12 | Adding the `abstract` keyword to an interface method is required in PHP. | |
| 13 | `$obj instanceof MyInterface` returns `true` if `$obj` is an instance of a class that implements `MyInterface`. | |

---

## Section C — Short Answer

**Q14.** In one or two sentences, explain the difference between an **interface** and an **abstract class**. When would you choose an interface over an abstract class?

*Your answer:*

---

**Q15.** Look at this code:
```php
class ReportService {
    public function generate(): void {
        $exporter = new CsvExporter();
        $exporter->export($this->data);
    }
}
```
Identify the design problem and describe how you would fix it using an interface.

*Your answer:*

---

**Q16.** You are building an application that needs to send notifications. You have `EmailNotifier`, `SmsNotifier`, and `PushNotifier`. Describe in plain English (no code required) the interface you would define and how the three classes would relate to it.

*Your answer:*

---

## Section D — Code Reading

**Q17.** What will the following code output? Write the output exactly, or write "Fatal error" and explain why.

```php
<?php
declare(strict_types=1);

interface Shape {
    public function area(): float;
}

class Circle implements Shape {
    public function __construct(private float $radius) {}
    public function area(): float {
        return M_PI * $this->radius ** 2;
    }
}

class Square implements Shape {
    public function __construct(private float $side) {}
    public function area(): float {
        return $this->side ** 2;
    }
}

function printArea(Shape $shape): void {
    echo round($shape->area(), 2) . "\n";
}

printArea(new Circle(5));
printArea(new Square(4));
```

*Your answer:*

---

**Q18.** What will the following code output? Write the output exactly, or write "Fatal error" and explain why.

```php
<?php
declare(strict_types=1);

interface Greetable {
    public function greet(): string;
}

class HelloGreeter implements Greetable {
    public function greet(): string {
        return "Hello!";
    }
}

class BrokenGreeter implements Greetable {
    // greet() is intentionally missing
}

$g = new HelloGreeter();
echo $g->greet() . "\n";
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
&nbsp;

---

## Section A
| Q | Answer | Explanation |
|---|--------|-------------|
| 1 | **B** | Interfaces cannot contain concrete method bodies. Only method signatures (and constants) are allowed. Default implementations belong in abstract classes or traits. |
| 2 | **C** | Type-hint against the interface name. Option A over-specifies (only `Dog`). Option B is a union type that adds nothing. Option D is not valid PHP syntax. |
| 3 | **C** | `Duck` must implement all three: `fly()` and `swim()` from the parent interfaces, plus `land()` from `Amphibious` itself. |
| 4 | **B** | PHP throws a **fatal error** at class load time, before a single line of the class runs: *"Class X contains 1 abstract method and must therefore be declared abstract or implement the remaining method (Countable::count)"*. Note that the engine names the method it is missing — the message tells you exactly what to write. |
| 5 | **D** | Constants declared in an interface are accessible via the interface name, the implementing class name, or an instance of the implementing class. All three are valid. |
| 6 | **B** | Polymorphism: any class implementing the interface can be passed. The function is decoupled from concrete implementations. |
| 7 | **B** | `ReadWritable extends Readable`, so any class implementing `ReadWritable` also satisfies `Readable`. Both are accepted. |

## Section B
| # | Answer | Explanation |
|---|--------|-------------|
| 8  | **T** | `interface C extends A, B {}` is valid PHP. |
| 9  | **F** | Interfaces cannot be instantiated. `new Flyable()` is a fatal error. |
| 10 | **T** | `class Foo extends Bar implements Baz {}` is perfectly valid. |
| 11 | **T** | `MyInterface::CONST` is the canonical way to access interface constants. |
| 12 | **F** | Interface methods are **implicitly** abstract, and writing `abstract` is not merely redundant — PHP rejects it outright: *"Interface method I::f() must not be abstract"*. A separate rule governs visibility: interface methods must be `public`, and anything else gives a different message, *"Access type for interface method I2::f() must be public"*. Writing `public` explicitly is allowed and is purely a matter of taste. |
| 13 | **T** | `instanceof` checks the full contract chain, including implemented interfaces. |

## Section C

**Q14 — Model answer:**
An interface is a pure contract: it defines method signatures with no implementation. An abstract class can contain both abstract methods (no body) and concrete methods (with a body). Choose an interface when you want to define a capability that unrelated classes can share without inheriting behaviour (e.g. `Printable`, `Serializable`). Choose an abstract class when you have shared implementation logic that subclasses should inherit.

**Q15 — Model answer:**
The problem is tight coupling: `ReportService` creates its own `CsvExporter` with `new`, making it impossible to swap exporters or test without producing CSV output. Fix: define an `Exporter` interface with an `export(array $data): void` method. Inject an `Exporter` into `ReportService`'s constructor. Now any class implementing `Exporter` (CSV, JSON, XML) can be passed in without touching `ReportService`.

**Q16 — Model answer:**
Define a `Notifier` interface with a single `send(string $to, string $message): void` method. Each of the three classes (`EmailNotifier`, `SmsNotifier`, `PushNotifier`) implements `Notifier` and provides its own version of `send()`. Any code that needs to send a notification accepts a `Notifier` parameter and calls `send()` — it does not need to know which channel is being used.

## Section D

**Q17 — Answer:**
```
78.54
16
```
`round(M_PI * 25, 2)` = `78.54`. `4 ** 2` = `16`. Both classes implement `Shape`, so `printArea()` accepts both.

**Q18 — Answer:**
```
Fatal error
```
`BrokenGreeter` declares `implements Greetable` but does not provide `greet()`. PHP throws a fatal error **when the file is loaded** — before even reaching `new HelloGreeter()`. The first `echo` never executes.

---

## Score Guide

| Score | Verdict |
|-------|---------|
| 16–18 | Ready for Lesson 1.2 — strong interface mastery. |
| 12–15 | Re-read the README sections for any questions you missed, then move on. |
| Below 12 | Re-run the examples, redo the challenge, then retake the quiz before continuing. |