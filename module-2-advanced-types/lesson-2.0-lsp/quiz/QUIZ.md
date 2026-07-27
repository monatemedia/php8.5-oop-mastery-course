# Quiz — Lesson 2.0: Liskov Substitution Principle
> Complete this quiz **without** looking at any example or solution files.
> Write your answers down, then check the answer key at the bottom.
> Any question you get wrong is a reading target — go back to the README section that covers it.

---

## Section A — Multiple Choice

**Q1.** Which of the following is the most accurate one-sentence definition of LSP?

- A) A subclass must override every method of its parent class.
- B) A subtype must be safely replaceable anywhere its supertype is expected, without breaking the program.
- C) Subclasses should not call methods on their parent class.
- D) Interfaces must be small and focused so implementing classes are not forced to stub methods.

---

**Q2.** A class `PdfExporter` extends `Exporter`. `Exporter::export()` returns a `string`. Which return type for `PdfExporter::export()` does PHP allow?

- A) `mixed` — because mixed is always compatible.
- B) `object` — a supertype of string, which is always safe.
- C) `string` or any subclass of `string`.
- D) Exactly `string` — PHP does not allow return type changes in overrides.

---

**Q3.** You have:
```php
interface Logger {
    public function log(Throwable $exception): void;
}

class VerboseLogger implements Logger {
    public function log(Exception $exception): void { /* ... */ }
}
```
Is `VerboseLogger` valid under LSP and PHP's type rules?

- A) Yes — `Exception` is a subclass of `Throwable`, which is a narrower (more specific) type.
- B) No — narrowing a parameter type violates contravariance. PHP will throw a fatal error.
- C) Yes — PHP does not enforce parameter types on interface implementations.
- D) No — you cannot use `Throwable` in an interface.

---

**Q4.** Which of the following is a guaranteed red flag for an LSP violation?

- A) A subclass adds a new public method.
- B) An overriding method has a narrower return type than the parent.
- C) Calling code must use `instanceof` to decide which code path to take.
- D) A class implements two interfaces simultaneously.

---

**Q5.** A `Square` extends `Rectangle`. `Rectangle` has `setWidth(int $w)` and `setHeight(int $h)`. A function does:
```php
function stretchRectangle(Rectangle $r): void {
    $r->setWidth(10);
    $r->setHeight(5);
    assert($r->area() === 50);
}
```
`Square::setWidth()` sets both width AND height. What happens when a `Square` is passed?

- A) The assertion passes because Square is a Rectangle.
- B) The assertion fails — Square's override changes height when setWidth is called, so area ends up as 25.
- C) PHP throws a fatal error because Square cannot extend Rectangle.
- D) The assertion passes because PHP converts Square to Rectangle automatically.

---

**Q6.** Which of the following is a correct application of the LSP behavioural rule about preconditions?

- A) A subtype's method can require a minimum parameter value that the parent does not require.
- B) A subtype's method must accept anything the parent's method accepted, and may accept more.
- C) A subtype's method must accept exactly the same parameter values as the parent — no more, no less.
- D) Preconditions only apply to abstract classes, not interfaces.

---

**Q7.** You have an interface `AnimalFactory` with `create(): Animal`. A class `DogFactory implements AnimalFactory` overrides `create()` with return type `Dog`. PHP:

- A) Throws a fatal error — return types must match exactly.
- B) Allows it — `Dog` is a subtype of `Animal` (covariance).
- C) Allows it only if `Dog` is an abstract class.
- D) Issues a deprecation warning.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 8 | A correct NullLogger that implements `Logger` must store log messages even if it does not output them, so callers that call `getLogs()` get back what was logged. | |
| 9 | An overriding method's parameter type can be a supertype (wider) of the parent's parameter type. | |
| 10 | The presence of `instanceof` checks in calling code always means the hierarchy is correctly designed. | |
| 11 | Covariance applies to return types; contravariance applies to parameter types. | |
| 12 | If a subtype strengthens a precondition (requires more from the caller), that is an LSP violation. | |
| 13 | Splitting a large interface into smaller ones (ISP) is one of the primary tools for fixing LSP violations. | |

---

## Section C — Short Answer

**Q14.** Explain in one or two sentences: what is the difference between a **covariant** return type and a **contravariant** parameter type? Give a one-line code example of each.

*Your answer:*

---

**Q15.** A colleague says: *"My `RssRenderer` class extends `HtmlRenderer` and just throws `BadMethodCallException` in the `renderWithLayout()` method — it's fine because I catch it everywhere."* Identify the LSP principle being violated and describe the correct structural fix.

*Your answer:*

---

**Q16.** Why is a **silent no-op** override (a method that does nothing) often more dangerous than an override that throws an exception?

*Your answer:*

---

## Section D — Code Reading

**Q17.** Will the following code produce output, a fatal error, or a wrong result? Explain which LSP rule applies.

```php
<?php
declare(strict_types=1);

interface Shape {
    public function area(): float;
}

interface ThreeDShape extends Shape {
    public function volume(): float;
}

class Circle implements Shape {
    public function __construct(private float $radius) {}
    public function area(): float { return M_PI * $this->radius ** 2; }
}

class Sphere implements ThreeDShape {
    public function __construct(private float $radius) {}
    public function area(): float   { return 4 * M_PI * $this->radius ** 2; }
    public function volume(): float { return (4/3) * M_PI * $this->radius ** 3; }
}

function printArea(Shape $shape): void {
    echo round($shape->area(), 2) . "\n";
}

printArea(new Circle(5));
printArea(new Sphere(5));
```

*Your answer:*

---

**Q18.** Identify the LSP violation in this code and name the specific rule broken:

```php
class FileCache {
    public function get(string $key): mixed   { return null; }
    public function set(string $key, mixed $value): void { /* store */ }
    public function ttl(): int { return 3600; }
}

class EternalCache extends FileCache {
    public function ttl(): int {
        throw new \LogicException("Eternal cache has no TTL!");
    }
}

function printCacheTtl(FileCache $cache): void {
    echo "TTL: " . $cache->ttl() . " seconds\n";
}

printCacheTtl(new FileCache());
printCacheTtl(new EternalCache()); // What happens here?
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
| 1 | **B** | LSP is about safe substitutability — any code expecting T must work when given S (a subtype of T). |
| 2 | **C** | PHP supports covariant return types — overriding return types can be the same or a subtype (more specific). `string` has no subclasses in PHP, so only `string` itself works here. |
| 3 | **B** | `Exception` is a subtype of `Throwable`. Narrowing a parameter type (accepting less) violates contravariance. PHP throws a fatal error. |
| 4 | **C** | `instanceof` guards in calling code are the canonical symptom that the hierarchy is broken — the caller is compensating for unsafe substitution. |
| 5 | **B** | `setWidth(10)` on a Square also sets height to 10. Then `setHeight(5)` sets both to 5. Area = 5×5 = 25, not 50. The assertion fails. |
| 6 | **B** | Preconditions cannot be strengthened — the subtype must accept everything the parent accepted (and may be more permissive, but never stricter). |
| 7 | **B** | Covariant return types — narrowing the return type to a subtype is explicitly allowed in PHP 7.4+. |

## Section B
| # | Answer | Explanation |
|---|--------|-------------|
| 8  | **T** | This looks like it contradicts Lesson 1.4, where a Null Object "implements the interface and does nothing". It does not, and the difference is worth pinning down because it decides whether your null object is safe.<br><br>What a Null Object may skip is the **side effect** — writing to disk, sending the email, hitting the network. What it may not skip is any **query the interface exposes**. This `Logger` declares `getLogs(): array`, so "the message is recorded" is part of the contract, and a version that drops messages returns an empty array to a caller who just logged three things. That is a broken postcondition, and it breaks silently.<br><br>Had the interface been `log(string $m): void` and nothing else, a discarding implementation would honour it completely — there would be no observable promise to break. The rule: a null object may do nothing, but it must not *lie*. |
| 9  | **T** | Contravariance: parameter types can be widened (supertype). PHP allows this since 7.4. |
| 10 | **F** | `instanceof` checks are a red flag that substitution is NOT safe — they signal a violation, not good design. |
| 11 | **T** | Covariance = return types go down (narrower). Contravariance = parameter types go up (wider). |
| 12 | **T** | Preconditions cannot be strengthened in a subtype — this is one of the three core behavioural LSP rules. |
| 13 | **T** | ISP and LSP are deeply related. Splitting interfaces so classes only sign what they can honour is the primary structural fix for LSP violations. |

## Section C

**Q14 — Model answer:**
Covariant return type: the overriding method returns a more specific (subtype) result than the parent promised — safe because the caller got at least what was promised.
`interface F { public function make(): Animal; }` → `class G implements F { public function make(): Dog {} }` ✓
Contravariant parameter type: the overriding method accepts a broader (supertype) argument than the parent required — safe because the caller's value is always within the accepted range.
`interface H { public function handle(Dog $d): void; }` → `class I implements H { public function handle(Animal $a): void {} }` ✓

**Q15 — Model answer:**
The violated rule is the "throwing override" form of LSP: `RssRenderer` cannot honestly honour the `renderWithLayout()` contract, yet it inherits it. Catching exceptions everywhere is not a fix — it just distributes the damage. The correct fix is to split the interface: `Renderer` (with only `render()`) and `LayoutRenderer extends Renderer` (with `renderWithLayout()` added). `RssRenderer implements Renderer`, `HtmlRenderer implements LayoutRenderer`. Functions that need layouts type-hint `LayoutRenderer` — `RssRenderer` can never be passed there by mistake.

**Q16 — Model answer:**
A throwing override is immediately visible — the program crashes and the developer knows something is wrong. A silent no-op returns normally but delivers nothing, so the caller believes the operation succeeded. Data is quietly lost, state becomes inconsistent, and the bug may not surface until much later (e.g. when someone tries to read back data that was never stored). Silent failures are harder to detect, reproduce, and debug than loud ones.

## Section D

**Q17 — Answer:**
Correct output — no error, no wrong result:
```
78.54
314.16
```
`Sphere implements ThreeDShape` which extends `Shape`. `printArea()` type-hints `Shape`, so both `Circle` and `Sphere` are valid. `Sphere::area()` correctly overrides the method (same return type — no covariance needed here). LSP is satisfied: both subtypes honour the `area()` contract fully.

**Q18 — Answer:**
This is a **throwing override** violation. `FileCache::ttl()` promises to return an `int` — that is its postcondition. `EternalCache::ttl()` throws instead of returning a value, weakening the postcondition. `printCacheTtl(new EternalCache())` crashes with a `LogicException`.
The fix: if an eternal cache has no TTL concept, it should not extend `FileCache`. Either split the interface into `CacheWithTtl` and `EternalCache implements Cache` (without `ttl()`), or have `ttl()` return a sentinel value like `0` or `PHP_INT_MAX` that callers can check — as long as the return type contract is honoured.

---

## Score Guide

| Score | Verdict |
|-------|---------|
| 16–18 | Ready for Lesson 2.1 — strong LSP mastery. |
| 12–15 | Re-read the README sections for any questions you missed, then move on. |
| Below 12 | Re-run the examples, redo the challenge, then retake the quiz before continuing. |