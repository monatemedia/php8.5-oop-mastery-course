# Quiz — Lesson 1.2: Abstract Classes
> Complete this quiz **without** looking at the examples or solution files.
> Write your answers before checking the answer key at the bottom.
> Any question you get wrong is a reading target — go back to the README section that covers it.

---

## Section A — Multiple Choice

**Q1.** Which statement about abstract classes is **false**?

- A) An abstract class cannot be instantiated directly.
- B) An abstract class can contain both abstract and concrete methods.
- C) An abstract class can implement one or more interfaces.
- D) An abstract class can extend more than one parent class.

---

**Q2.** What is the correct visibility for an abstract method that subclasses must override?

- A) `private abstract`
- B) `public abstract` or `protected abstract`
- C) `static abstract`
- D) Visibility is not required on abstract methods.

---

**Q3.** A concrete class extends an abstract class but does not implement one of its abstract methods. What does PHP do?

- A) PHP silently ignores the missing method.
- B) PHP throws a warning at runtime when the method is first called.
- C) PHP throws a fatal error when the class is loaded.
- D) The class automatically inherits a no-op implementation.

---

**Q4.** You mark a concrete method `final` in an abstract class. What does this enforce?

- A) Subclasses must override the method.
- B) Subclasses cannot override the method — the implementation is locked.
- C) The method becomes static.
- D) The method becomes abstract.

---

**Q5.** Which of the following best describes the **Template Method Pattern**?

- A) A method that creates objects without specifying their concrete class.
- B) An abstract class defines the skeleton of an algorithm; subclasses fill in specific steps.
- C) A method that delegates all work to a composed object.
- D) A pattern that allows multiple classes to share methods without inheritance.

---

**Q6.** You have:
```php
abstract class Base {
    public function __construct(protected string $name) {
        echo "Base: {$name}\n";
    }
    abstract public function greet(): string;
}

class Child extends Base {
    public function __construct(string $name, private int $age) {
        parent::__construct($name);
        echo "Child: age={$age}\n";
    }
    public function greet(): string { return "Hi, I'm {$this->name}, age {$this->age}"; }
}
```
What is the output of `new Child('Alice', 30)`?

- A) `Child: age=30`
- B) `Base: Alice` then `Child: age=30`
- C) `Child: age=30` then `Base: Alice`
- D) Fatal error — `parent::__construct()` is not allowed.

---

**Q7.** When should you choose an **interface** over an **abstract class**?

- A) When you have shared property values to provide to all implementors.
- B) When you need a constructor that subclasses build on.
- C) When defining a capability that unrelated classes can opt into, with no shared implementation.
- D) When you need to prevent a class from being instantiated.

---

**Q8.** A function is declared as `function process(Base $item): void`. `Base` is an abstract class. Which objects can be passed to `process()`?

- A) Only objects instantiated directly from `Base`.
- B) Only objects from classes that are declared `abstract`.
- C) Objects from any concrete class that extends `Base` (directly or indirectly).
- D) No objects — you cannot type-hint an abstract class.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 9  | A class can extend only one abstract class but can implement multiple interfaces. | |
| 10 | An abstract class can have a constructor that is called via `parent::__construct()`. | |
| 11 | An abstract method must have a method body (even if it is empty `{}`). | |
| 12 | Marking a concrete method `final` in an abstract class prevents subclasses from overriding it. | |
| 13 | A class that contains even one abstract method must itself be declared `abstract`. | |
| 14 | An abstract class can implement an interface and provide concrete implementations of the interface's methods, so that subclasses do not need to. | |

---

## Section C — Short Answer

**Q15.** Explain in two sentences: what is the difference between an **abstract method** and a **hook method** in the context of the Template Method Pattern?

*Your answer:*

---

**Q16.** A colleague says: *"I'll just use an interface for everything — abstract classes are unnecessary."* Give one concrete scenario where an abstract class is the clearly better choice and explain why.

*Your answer:*

---

**Q17.** Look at this code and identify the problem. What is wrong, and how would you fix it?

```php
abstract class Validator {
    private array $errors = [];

    abstract private function validate(mixed $value): bool;

    public function getErrors(): array { return $this->errors; }
}
```

*Your answer:*

---

## Section D — Code Reading

**Q18.** What will the following code output? Write the output exactly, or write "Fatal error" and explain why.

```php
<?php
declare(strict_types=1);

abstract class Shape {
    abstract public function area(): float;

    final public function describe(): string {
        return get_class($this) . " with area " . round($this->area(), 2);
    }
}

class Circle extends Shape {
    public function __construct(private float $radius) {}
    public function area(): float { return M_PI * $this->radius ** 2; }
}

class Rectangle extends Shape {
    public function __construct(private float $w, private float $h) {}
    public function area(): float { return $this->w * $this->h; }
}

$shapes = [new Circle(5), new Rectangle(4, 6)];
foreach ($shapes as $shape) {
    echo $shape->describe() . "\n";
}
```

*Your answer:*

---

**Q19.** What will the following code output? Write the output exactly, or write "Fatal error" and explain why.

```php
<?php
declare(strict_types=1);

abstract class Logger {
    abstract protected function write(string $message): void;

    final public function log(string $level, string $message): void {
        $formatted = "[{$level}] " . date('H:i') . " — {$message}";
        $this->write($formatted);
    }
}

class ConsoleLogger extends Logger {
    public function log(string $level, string $message): void {
        echo "OVERRIDDEN\n";
    }

    protected function write(string $message): void {
        echo $message . "\n";
    }
}

$logger = new ConsoleLogger();
$logger->log('INFO', 'App started');
```

*Your answer:*

---

**Q20.** What will the following code output? Write the output exactly, or write "Fatal error" and explain why.

```php
<?php
declare(strict_types=1);

abstract class Animal {
    public function __construct(protected string $name) {}
    abstract public function speak(): string;

    public function introduce(): string {
        return "I am {$this->name} and I say: " . $this->speak();
    }
}

class Dog extends Animal {
    public function speak(): string { return "Woof!"; }
}

class Cat extends Animal {
    public function __construct(string $name, private bool $indoor) {
        parent::__construct($name);
    }
    public function speak(): string {
        return $this->indoor ? "Meow (quietly)" : "MEOW!";
    }
}

echo (new Dog('Rex'))->introduce() . "\n";
echo (new Cat('Luna', true))->introduce() . "\n";
echo (new Cat('Tiger', false))->introduce() . "\n";
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
| 1 | **D** | PHP supports only single inheritance. A class (abstract or not) can extend exactly one parent. |
| 2 | **B** | Abstract methods must be `public` or `protected`. `private abstract` is illegal — private methods cannot be overridden. |
| 3 | **C** | Fatal error at class load time: *"Class X contains 1 abstract method and must therefore be declared abstract or implement the remaining methods."* |
| 4 | **B** | `final` locks the method — no subclass can override it. Useful in the Template Method Pattern to protect the pipeline skeleton. |
| 5 | **B** | The Template Method Pattern defines an algorithm skeleton in the abstract class; abstract "step" methods let subclasses customise each step without changing the order. |
| 6 | **B** | `parent::__construct($name)` is called first → "Base: Alice". Then the rest of `Child::__construct()` runs → "Child: age=30". |
| 7 | **C** | Interfaces model capabilities ("can-do"). Abstract classes model identity with shared code ("is-a" + shared implementation). |
| 8 | **C** | Concrete subclasses of an abstract class can be type-hinted against the abstract class — just like an interface. Abstract class itself cannot be instantiated. |

## Section B
| # | Answer | Explanation |
|---|--------|-------------|
| 9  | **T** | Single inheritance — one `extends`. Multiple interface implementation — unlimited `implements`. |
| 10 | **T** | Abstract class constructors are invoked via `parent::__construct()` in the subclass. |
| 11 | **F** | Abstract methods have NO body — no curly braces. They end with a semicolon. |
| 12 | **T** | `final` on a concrete method in any class (abstract or not) prevents subclasses from overriding it. |
| 13 | **T** | If any method in a class is abstract, the class must be declared `abstract` or PHP throws a fatal error. |
| 14 | **T** | An abstract class can provide concrete implementations of interface methods, relieving subclasses of that requirement. |

## Section C

**Q15 — Model answer:**
An abstract method has no implementation — every subclass is required to provide its own version (PHP enforces this at load time). A hook method is a concrete method with a default (often empty) implementation that subclasses *may* override but do not *have* to — it is an optional extension point in the template method's pipeline.

**Q16 — Model answer:**
Example: a base `HttpController` class. Every controller needs the same `requireAuth()` and `jsonResponse()` methods — writing them in every controller would be repetition. An abstract class puts those concrete methods in one place and requires each controller to implement `handle()`. An interface cannot provide the shared code; you would be forced to repeat or use a trait as a workaround.

**Q17 — Model answer:**
The abstract method is declared `private`. Private methods cannot be overridden by subclasses — this is a contradiction, since abstract methods *must* be overridden. PHP will throw a fatal error: *"Abstract function Validator::validate() cannot be declared private."* Fix: change `private` to `protected` (visible to the class and subclasses only).

## Section D

**Q18 — Answer:**
```
Circle with area 78.54
Rectangle with area 24
```
`describe()` is `final` and calls `$this->area()` — which dispatches to each concrete subclass's implementation. `round(M_PI * 25, 2)` = 78.54. `4 * 6` = 24.

**Q19 — Answer:**
Fatal error. `log()` is marked `final` in `Logger`. `ConsoleLogger` attempts to override it. PHP throws: *"Cannot override final method Logger::log()"*. The `log('INFO', 'App started')` call never executes.

**Q20 — Answer:**
```
I am Rex and I say: Woof!
I am Luna and I say: Meow (quietly)
I am Tiger and I say: MEOW!
```
`Dog` calls `parent::__construct($name)` implicitly (no constructor defined — PHP uses the inherited one). `Cat` calls `parent::__construct($name)` explicitly, then stores `$indoor`. `introduce()` is inherited from `Animal` and calls `$this->speak()` — which dispatches polymorphically to each subclass.

---

## Score Guide

| Score | Verdict |
|-------|---------|
| 18–20 | Ready for Lesson 1.3 — strong abstract class mastery. |
| 14–17 | Re-read the README sections for any questions you missed, then move on. |
| Below 14 | Re-run the examples, redo the challenge, then retake the quiz before continuing. |