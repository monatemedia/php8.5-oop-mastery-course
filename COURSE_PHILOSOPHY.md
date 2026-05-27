# Course Philosophy — Golden Rules
> PHP 8.5 OOP Mastery Course

These rules apply across **every module**. They were crystallised during course design and should be kept visible as you work through the material. When you are unsure whether a design decision is correct, run it through this list first.

---

## Rule 1 — Config Belongs at the Entry Point, Not in Core Logic

Your DI container, environment variables, database DSNs, and API keys belong in one place: the **composition root** (your `index.php` or bootstrap file). Business-logic classes should receive dependencies — they should never reach for the container, `getenv()`, or config files directly.

**In practice:**
```
✅  class OrderService { public function __construct(private GatewayInterface $gw) {} }
❌  class OrderService { public function __construct() { $this->gw = $container->get(Gateway::class); } }
```

A class that calls `$container->get(...)` inside itself has turned the container into a Service Locator — a global dependency registry that hides coupling rather than eliminating it. The container is infrastructure; business logic is core. They must not mix.

**Modules where this rule surfaces:**
Module 3 (composition root), Module 4 (PHP-DI wiring), Module 5 (test bootstrap vs application bootstrap).

---

## Rule 2 — Test Behaviours, Not Layouts

A test that asserts *how* a class is structured (how many methods it has, which properties exist, what the class name is) breaks every time you refactor — even when the behaviour is identical. Tests should assert **what the system does**, not **how it is built**.

**In practice:**
```
✅  Assert that placing an order sends exactly one email to the customer.
✅  Assert that an invalid email throws InvalidArgumentException.
❌  Assert that OrderService has a $mailer property.
❌  Assert that the constructor has exactly 3 parameters.
```

This rule also applies to which injection pattern you use. A test that checks whether a logger was injected via constructor or setter is testing implementation layout. A test that asserts what was logged is testing behaviour.

**Modules where this rule surfaces:**
Module 2 (anonymous class stubs), Module 3 (spy patterns), Module 5 (TDD cycle, anti-pattern lesson).

---

## Rule 3 — The Type System Is a Security Layer

PHP's type system — strict types, interface type hints, union types, never return types — is not just documentation. It is an **active enforcement mechanism** that catches category errors at the boundary of your system, before they become runtime bugs or security issues.

**In practice:**
- `declare(strict_types=1)` in every file prevents silent numeric coercions
- Type-hinting parameters against interfaces prevents wrong implementations from being passed
- `never` return types prevent unreachable code from being treated as reachable
- Backed enums typed as parameters prevent magic strings from entering your domain
- Property hooks (PHP 8.4) enforce validation at the data entry point

A class with `string $status` anywhere in its signature is a vulnerability — "panding", "SHIPPED", or `""` are all valid strings. A class with `OrderStatus $status` where `OrderStatus` is a backed enum accepts exactly the cases you defined. **Use the type system aggressively.**

**Modules where this rule surfaces:**
Module 2 (type hinting, enums, property hooks), Module 3 (interface type hints), Module 5 (testing typed boundaries).

---

## Rule 4 — Favour Composition Over Inheritance

When you need to share behaviour between classes, ask: **does a "is-a" relationship genuinely exist, or do I just want to reuse some code?**

- If a genuine "is-a" exists and the parent contract can never be violated → use inheritance
- If you want to reuse code or combine capabilities → use composition (inject collaborators) or traits (horizontal code sharing)

Deep inheritance trees make the DIP impossible, LSP harder, and testing painful. They also make DI harder — you cannot inject a dependency that is hard-coded in a parent constructor three levels up.

**The practical test:** If you can replace `extends ParentClass` with `private ParentClass $parent` (a field) and call `$this->parent->method()`, you probably should.

**Modules where this rule surfaces:**
Lesson 1.4 (full lesson), Module 2 (LSP explains why deep trees break), Module 3 (DI is composition applied to services).

---

## Rule 5 — Objects Either Hold State or Perform Work — Rarely Both

An object that holds data (a model, a DTO, a value object) should not also contain complex business logic. An object that performs work (a service, a repository, a gateway) should ideally be **stateless** — all inputs arrive via method parameters, all outputs are return values.

Mixing state and behaviour in one class makes testing harder (you must set up state before every test), makes concurrency bugs more likely, and makes the class harder to reuse.

**In practice:**
```
✅  class Money { public function __construct(private int $cents, private string $currency) {} }
    // Money is a value object — it holds state, has minimal behaviour, is immutable

✅  class TaxCalculator { public function calculate(Money $amount, float $rate): Money {} }
    // TaxCalculator is stateless — same inputs always produce same outputs

❌  class TaxCalculator {
        private Money $lastCalculation; // ← State in a service = complexity
        public function calculate(...) { $this->lastCalculation = ...; }
    }
```

**Modules where this rule surfaces:**
Module 6 (full module — stateless services, singleton traps, lifecycle management).

---

## Rule 6 — Read the Failing Test Before Reading the Code

When something breaks, the test failure message tells you *what* broke. Reading the class code first tells you *how it is built* — which is often irrelevant to the failure. Train yourself to read test output first, then only open source files to understand *why* the expectation was violated.

This is the TDD mindset applied to debugging: the test describes the intended behaviour. The code is just one possible implementation of it.

**Modules where this rule surfaces:**
Module 5 (TDD cycle, red-green-refactor discipline).

---

## How These Rules Connect

```
Rule 4 (Composition)
   ↓ enables
Rule 1 (Config at entry point) + Rule 3 (Type system)
   ↓ enables
Rule 2 (Test behaviours)
   ↓ enables
Rule 6 (Read the failing test)
   ↓ sustained by
Rule 5 (State vs behaviour separation)
```

These are not independent rules — they form a chain. Composition enables DI. DI enables testability. Testability enables TDD. TDD sustains good design. Stateless services make all of it durable.

---

## Where Each Rule Appears in the Course

| Rule | Module 1 | Module 2 | Module 3 | Module 4 | Module 5 | Module 6 |
|------|----------|----------|----------|----------|----------|----------|
| 1 — Config at entry | | | ⭐ | ⭐ | ✓ | |
| 2 — Test behaviours | | ✓ | ✓ | | ⭐ | |
| 3 — Type system as security | ✓ | ⭐ | ✓ | | ✓ | |
| 4 — Composition over inheritance | ⭐ | ✓ | ⭐ | | ✓ | |
| 5 — State vs behaviour | ✓ | | | ✓ | | ⭐ |
| 6 — Read the test first | | | | | ⭐ | |

⭐ = primary coverage · ✓ = reinforced