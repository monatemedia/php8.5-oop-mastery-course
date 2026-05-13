# Quiz — Lesson 1.3: Traits
> Complete this quiz **without** looking at any example or solution files.
> Write your answers before checking the answer key at the bottom.
> Any question you get wrong is a reading target.

---

## Section A — Multiple Choice

**Q1.** Which statement about PHP traits is **true**?

- A) A trait can be instantiated directly with `new`.
- B) A trait can be used as a type-hint in a function parameter.
- C) A class can use multiple traits simultaneously.
- D) A trait automatically implements any interfaces the host class declares.

---

**Q2.** A class uses two traits, `TraitA` and `TraitB`, both of which define a method called `process()`. What happens if you do NOT resolve the conflict?

- A) PHP silently uses `TraitA::process()` because it was listed first.
- B) PHP throws a fatal error when the class is loaded.
- C) PHP merges both implementations and calls them both.
- D) PHP throws a warning but continues using `TraitA::process()`.

---

**Q3.** You have:
```php
trait Greetable {
    public function greet(): string {
        return "Hello from " . get_class($this);
    }
}

class Person {
    use Greetable;
    public function __construct(public string $name) {}
}
```
What does `(new Person('Alice'))->greet()` return?

- A) `"Hello from Greetable"`
- B) `"Hello from Alice"`
- C) `"Hello from Person"`
- D) Fatal error — traits cannot reference `$this`.

---

**Q4.** What does the `insteadof` keyword do in a trait conflict resolution?

- A) It creates an alias for a conflicting method under a new name.
- B) It chooses one trait's method to use for a given name and discards the other's.
- C) It marks a method as final so no further overriding is allowed.
- D) It changes a method's visibility.

---

**Q5.** What does the `as` keyword do in a trait conflict resolution block?

- A) It chooses one trait's method over another for a given name.
- B) It creates an alias (new name) for a method, and/or changes its visibility.
- C) It declares a method abstract in the trait.
- D) It prevents the method from being called outside the class.

---

**Q6.** A trait declares `abstract protected function getLabel(): string;`. What does this mean for any class that uses the trait?

- A) Nothing — abstract methods in traits are optional.
- B) The class must implement `getLabel()`, or it must itself be declared `abstract`.
- C) The class automatically gets a default `getLabel()` implementation.
- D) PHP throws a fatal error because abstract methods cannot exist in traits.

---

**Q7.** Which of the following is the **correct** reason to pair an interface with a trait?

- A) Interfaces speed up trait execution.
- B) Traits need interfaces to define their properties.
- C) The interface provides a type contract for type-hints; the trait provides the default implementation — giving both type-safety and DRY code.
- D) PHP requires every trait to have a matching interface.

---

**Q8.** A trait declares `private string $status = 'draft'`. A class that uses the trait also declares `private string $status = 'active'`. What happens?

- A) The class's declaration wins silently.
- B) The trait's declaration wins silently.
- C) PHP throws a fatal error because the same property is declared with a different default value.
- D) PHP uses whichever declaration appears last in the file.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 9  | A trait can extend another trait using `extends`. | |
| 10 | `instanceof` can be used to check whether an object's class uses a particular trait. | |
| 11 | A trait method has access to `$this` and can read/write properties of the host class. | |
| 12 | You can change a trait method's visibility using `as protected` in the `use` block. | |
| 13 | If a class defines a method with the same name as a method in a used trait, the class's method takes priority. | |
| 14 | A trait can declare constants in PHP 8.2 and later. | |

---

## Section C — Short Answer

**Q15.** Explain in two sentences why a trait cannot be used as a type-hint, and what the standard solution is.

*Your answer:*

---

**Q16.** A colleague has written this code. Identify the problem and explain the fix:

```php
trait Cacheable {
    public function cache(): void {
        $key = $this->getCacheKey(); // relies on this method existing
        echo "Caching with key: {$key}\n";
    }
}

class UserProfile {
    use Cacheable;
    // getCacheKey() is not defined anywhere
}

(new UserProfile())->cache();
```

*Your answer:*

---

**Q17.** When would you choose a **trait** over an **abstract class** to share code? Give one concrete example.

*Your answer:*

---

## Section D — Code Reading

**Q18.** What will the following code output? Write the output exactly, or write "Fatal error" and explain why.

```php
<?php
declare(strict_types=1);

trait Hello {
    public function sayHi(): string { return "Hi from Hello"; }
    public function greet(): string { return "Greet from Hello"; }
}

trait Goodbye {
    public function sayBye(): string { return "Bye from Goodbye"; }
    public function greet(): string { return "Greet from Goodbye"; }
}

class Messenger {
    use Hello, Goodbye {
        Hello::greet   insteadof Goodbye;
        Goodbye::greet as farewellGreet;
    }
}

$m = new Messenger();
echo $m->sayHi() . "\n";
echo $m->sayBye() . "\n";
echo $m->greet() . "\n";
echo $m->farewellGreet() . "\n";
```

*Your answer:*

---

**Q19.** What will the following code output? Write the output exactly, or write "Fatal error" and explain why.

```php
<?php
declare(strict_types=1);

interface Serialisable {
    public function serialise(): string;
}

trait SerialisableTrait {
    public function serialise(): string {
        return get_class($this) . ':' . json_encode($this->toData());
    }
    abstract protected function toData(): array;
}

class Config implements Serialisable {
    use SerialisableTrait;

    public function __construct(private string $env, private bool $debug) {}

    protected function toData(): array {
        return ['env' => $this->env, 'debug' => $this->debug];
    }
}

function persist(Serialisable $s): void {
    echo $s->serialise() . "\n";
}

persist(new Config('production', false));
```

*Your answer:*

---

**Q20.** What will the following code output? Write the output exactly, or write "Fatal error" and explain why.

```php
<?php
declare(strict_types=1);

trait Counter {
    private int $count = 0;

    public function increment(): void { $this->count++; }
    public function getCount(): int   { return $this->count; }
}

class PageView {
    use Counter;
    public function __construct(public string $page) {}
}

class ApiCall {
    use Counter;
    private int $count = 0; // Re-declares with same type and same default
    public function __construct(public string $endpoint) {}
}

$view = new PageView('/home');
$view->increment();
$view->increment();
echo $view->getCount() . "\n";

$api = new ApiCall('/api/users');
$api->increment();
echo $api->getCount() . "\n";
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
| 1 | **C** | A class can use any number of traits. Traits cannot be instantiated, cannot be type-hinted, and do not automatically implement interfaces. |
| 2 | **B** | PHP throws a fatal error at class load time when a method name conflict is not resolved with `insteadof`. |
| 3 | **C** | `get_class($this)` returns the class of the object at runtime — which is `Person`, not `Greetable`. `$this` always refers to the host object. |
| 4 | **B** | `insteadof` chooses one trait's version and discards the conflicting one entirely for that method name. |
| 5 | **B** | `as` creates an alias so both versions are accessible, and/or changes the method's visibility in the using class. |
| 6 | **B** | An abstract method in a trait behaves like an abstract method in a class — the host class must implement it, or it must be declared `abstract` itself. |
| 7 | **C** | The interface gives the type system a contract to enforce; the trait gives the class a free default implementation. Together they produce type-safe, DRY code. |
| 8 | **C** | PHP throws a fatal error if the same property is declared in both the class and the used trait with a different default value or visibility. |

## Section B
| # | Answer | Explanation |
|---|--------|-------------|
| 9  | **F** | Traits cannot extend other traits. A trait can `use` another trait (composition), but `extends` is for classes only. |
| 10 | **F** | `instanceof` checks against classes and interfaces — not traits. Traits are not part of PHP's type system. Use `in_array(SomeTrait::class, class_uses($obj))` instead. |
| 11 | **T** | Trait methods are injected into the host class and have full access to `$this`, including all of the class's properties and other methods. |
| 12 | **T** | `use MyTrait { myMethod as protected; }` changes the visibility of `myMethod` to `protected` in the using class only. |
| 13 | **T** | If the class defines a method with the same name as a trait method, the class's own method takes precedence — it overrides the trait's version silently. |
| 14 | **T** | PHP 8.2 added support for constants in traits. |

## Section C

**Q15 — Model answer:**
Traits are not types — they are a code-injection mechanism, and PHP's type system has no concept of "uses trait X". Type-hints in PHP only accept class names, interface names, and built-in types. The standard solution is to pair the trait with an interface: the interface defines the contract for type hints, and the trait provides the default implementation. Classes then `implement` the interface and `use` the trait.

**Q16 — Model answer:**
The `Cacheable` trait calls `$this->getCacheKey()`, but `UserProfile` does not define that method and the trait does not declare it as `abstract`. At runtime PHP throws an error: *"Call to undefined method UserProfile::getCacheKey()"*. The fix is to declare `abstract protected function getCacheKey(): string;` inside the trait. This forces any class that uses `Cacheable` to provide `getCacheKey()`, or be declared `abstract` itself. PHP will then catch the omission at class-load time rather than at runtime.

**Q17 — Model answer:**
Choose a trait when you need to share behaviour across classes that belong to different, unrelated inheritance hierarchies. Example: a `SoftDeletable` trait with `delete()`, `restore()`, and `isDeleted()` can be used by both `ProductModel` (which extends `EcommerceModel`) and `ArticleModel` (which extends `CmsModel`). An abstract class cannot serve both hierarchies because PHP only allows single inheritance — you cannot extend two different abstract classes.

## Section D

**Q18 — Answer:**
```
Hi from Hello
Bye from Goodbye
Greet from Hello
Greet from Goodbye
```
`Hello::greet insteadof Goodbye` means `greet()` resolves to Hello's version. `Goodbye::greet as farewellGreet` keeps Goodbye's version under the alias. `sayHi()` comes from Hello, `sayBye()` from Goodbye — no conflicts there.

**Q19 — Answer:**
```
Config:{"env":"production","debug":false}
```
`Config implements Serialisable` and `use SerialisableTrait` — the trait's `serialise()` satisfies the interface. `persist()` is type-hinted against `Serialisable` — `Config` qualifies. `toData()` is declared abstract in the trait and implemented in `Config`. `get_class($this)` returns `"Config"` at runtime.

**Q20 — Answer:**
```
2
1
```
`PageView` uses `Counter` cleanly — no conflict. After two `increment()` calls, `getCount()` returns 2.
`ApiCall` re-declares `private int $count = 0` with the **same** type and **same** default value — PHP 8.1+ allows this (same-type, same-default redeclaration is not a fatal error; it was tightened further in 8.1 but remains valid when compatible). `increment()` increments `$count` to 1, so `getCount()` returns 1.
Note: if `ApiCall` declared `private int $count = 5` (different default), that would be a fatal error.

---

## Score Guide

| Score | Verdict |
|-------|---------|
| 18–20 | Module 1 complete — ready for Lesson 2.0 (LSP). |
| 14–17 | Re-read the README sections for any missed questions, then move on. |
| Below 14 | Re-run the examples, redo the challenge, then retake the quiz before continuing. |