# Lesson 2.1 — Type Hinting & Return Types
> **Module 2: Advanced Types & Enums** · PHP 8.5 OOP Mastery Course

---

## 📁 Lesson Folder Structure

```
lesson-2.1-type-hinting/
├── README.md                              ← Theory (you are here)
│
├── examples/
│   ├── 01-scalar-types-and-strict-mode.php
│   ├── 02-nullable-and-union-types.php
│   ├── 03-void-never-mixed.php
│   ├── 04-self-static-parent.php
│   └── 05-intersection-types.php
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

## Why This Lesson Comes After LSP

In Lesson 2.0 you learned that PHP enforces LSP through covariant return types and contravariant parameter types. Those rules are only meaningful if you understand what return types and parameter types actually are — which is exactly what this lesson covers.

By the end of this lesson every type keyword in PHP will be familiar, and you will know precisely what `declare(strict_types=1)` changes and why you should put it at the top of every file you write.

---

## 1 — Scalar Types and `strict_types=1`

PHP supports four scalar type declarations for parameters and return values:

| Type | What it accepts |
|------|----------------|
| `int` | Integers: `42`, `-7`, `0` |
| `float` | Floating-point numbers: `3.14`, `-0.5` |
| `string` | Text: `"hello"`, `''` |
| `bool` | `true` or `false` |

### Without `strict_types` — coercive mode (the default)

PHP will silently convert compatible values between scalar types:

```php
function double(int $n): int {
    return $n * 2;
}

echo double(5);      // 10 — correct
echo double("5");    // 10 — PHP coerced "5" to 5 silently
echo double(2.9);    // 4  — PHP truncated 2.9 to 2 silently
```

Silent coercion hides bugs. `double("five")` would still be called without error, returning 0.

### With `strict_types=1` — strict mode

```php
<?php
declare(strict_types=1); // Must be the very first statement in the file

function double(int $n): int {
    return $n * 2;
}

echo double(5);      // 10 — correct
echo double("5");    // TypeError: must be of type int, string given
echo double(2.9);    // TypeError: must be of type int, float given
```

`declare(strict_types=1)` affects the **calling file only** — it governs how values are passed *from* that file to any function or method. It does not affect how the function itself behaves when called from other files.

**Rule: put `declare(strict_types=1)` at the top of every PHP file you write in this course.**

---

## 2 — Nullable Types

A nullable type accepts either the declared type **or** `null`. Prefix the type with `?`:

```php
function findUser(int $id): ?array {   // Returns array OR null
    $users = [1 => ['name' => 'Alice'], 2 => ['name' => 'Bob']];
    return $users[$id] ?? null;        // null if not found
}

$user = findUser(1);   // ['name' => 'Alice']
$user = findUser(99);  // null — valid return value
```

Nullable parameters work the same way:

```php
function greet(?string $name): string {
    return "Hello, " . ($name ?? 'stranger') . "!";
}

greet('Alice');  // "Hello, Alice!"
greet(null);     // "Hello, stranger!"
```

`?Type` is shorthand for `Type|null`. Both are equivalent in PHP 8+.

---

## 3 — Union Types (PHP 8.0+)

A union type accepts one of several types, separated by `|`:

```php
function formatId(int|string $id): string {
    return is_int($id) ? "ID-{$id}" : strtoupper($id);
}

formatId(42);       // "ID-42"
formatId('abc-1');  // "ABC-1"
```

Union types also work on return types:

```php
function divide(int $a, int $b): int|float {
    return $b === 0 ? 0 : $a / $b;   // int or float depending on result
}
```

**`null` in a union:** `int|string|null` is equivalent to `?int|string` — both allow `null`.

**Practical advice:** Union types are powerful but can signal that a function is doing too much. If you find yourself writing `int|string|array|null`, consider splitting the function.

---

## 4 — `void`, `never`, and `mixed`

### `void`

A function that returns nothing — not even `null` — is declared `: void`. Any attempt to return a value from a `void` function is a fatal error.

```php
function logMessage(string $message): void {
    echo "[LOG] {$message}\n";
    // return;      ← allowed (explicit return with no value)
    // return null; ← TypeError — void means no value at all
    // return 1;    ← TypeError
}
```

### `never` (PHP 8.1+)

A function that **never returns** — it always throws an exception or calls `exit()`. PHP's static analyser uses this to know that code after a call to a `never`-typed function is unreachable.

```php
function abort(int $code, string $message): never {
    throw new \RuntimeException("[{$code}] {$message}");
    // No return possible — this function always throws
}

function notFound(string $resource): never {
    http_response_code(404);
    exit("{$resource} not found.");
}
```

`never` is more specific than `void`: `void` says "I return nothing useful", `never` says "I do not return at all".

### `mixed`

Accepts or returns any type — equivalent to no type declaration at all. Use it sparingly and only when you genuinely cannot be more specific (e.g. when implementing a very generic container or wrapping a legacy API).

```php
function identity(mixed $value): mixed {
    return $value; // Accepts and returns anything
}
```

---

## 5 — `self`, `static`, and `parent` Return Types

These are special return type keywords used inside class methods.

### `self`

Returns an instance of **the class in which the method is defined** (not a subclass).

```php
class Builder {
    private array $options = [];

    public function set(string $key, mixed $value): self {
        $this->options[$key] = $value;
        return $this; // Always returns a Builder instance
    }
}
```

### `static` (PHP 8.0+)

Returns an instance of **the class that was actually called** at runtime — the "late static binding" type. This is the correct return type for fluent builder methods in class hierarchies.

```php
class QueryBuilder {
    protected array $wheres = [];

    public function where(string $column, mixed $value): static {
        $this->wheres[] = [$column, $value];
        return $this; // Returns the actual runtime class, not just QueryBuilder
    }
}

class UserQueryBuilder extends QueryBuilder {
    public function active(): static {
        return $this->where('status', 'active');
    }
}

// With :self, active() would return QueryBuilder — where() chaining would break.
// With :static, active() returns UserQueryBuilder — full chain works.
$query = (new UserQueryBuilder())->active()->where('role', 'admin');
```

**Rule:** Prefer `: static` over `: self` in any method that is designed to be inherited.

### `parent`

Returns an instance of the parent class. Rarely used directly — it creates confusion because the method returns a broader type than the class itself. Avoid unless you have a very specific reason.

---

## 6 — Intersection Types (PHP 8.1+)

An intersection type requires a value to satisfy **all** listed types simultaneously, using `&`:

```php
function processCollection(Countable&Traversable $collection): void {
    echo "Count: " . count($collection) . "\n";
    foreach ($collection as $item) {
        echo "  - {$item}\n";
    }
}
```

The parameter must implement **both** `Countable` **and** `Traversable`. Passing something that is only one of the two is a `TypeError`.

**Intersection vs union:**

```
int|string       — accepts int OR string (either one)
Countable&Iterator — must implement BOTH Countable AND Iterator (both required)
```

**Practical uses for intersection types:**

```php
// Accept any object that is both loggable and serialisable
function persist(Loggable&Serialisable $entity): void { ... }

// Accept any collection that supports both counting and iteration
function paginate(Countable&Traversable $items, int $perPage): array { ... }

// A repository that is both readable and cacheable
function warmCache(Readable&Cacheable $repo): void { ... }
```

**Intersection types cannot include `null`** — use a union with null instead: `(Countable&Traversable)|null`.

---

## 7 — Enforcing Strict Typing Across a Module

For a codebase to benefit from strict types, **every file** must declare them. One file without `strict_types=1` is a weak link.

**Checklist for each PHP file:**

```php
<?php
declare(strict_types=1);    // ← Line 2, always

// All functions, classes, and methods below are now strictly typed
```

**Common pitfalls:**

```php
// ❌ Wrong position — declare must be the FIRST statement
echo "debug";
declare(strict_types=1); // ParseError: strict_types declaration must be the very first statement

// ❌ Wrong value
declare(strict_types=2); // Only 0 and 1 are valid

// ❌ Affects this file only — other files calling your functions
//    still use coercive mode unless they also have strict_types=1
```

**Return type best practices:**

```php
// Always declare return types — even for void
public function save(): void { ... }

// Prefer specific types over mixed
public function find(int $id): ?User { ... }   // ✓ specific
public function find(int $id): mixed { ... }   // ✗ vague

// Use union types when the function genuinely has multiple valid return types
public function parse(string $input): int|float { ... }

// Use never for functions that always throw or exit
public function fail(string $reason): never {
    throw new \LogicException($reason);
}
```

---

## 8 — Quick Reference

```
PARAMETER TYPES
───────────────────────────────────────────────────────
int         string          float         bool
?int        ?string         ?float        ?bool       (nullable)
int|string  string|null     int|float                 (union)
mixed                                                  (anything)
Countable&Iterator                                    (intersection)
ClassName   InterfaceName                             (object types)

RETURN TYPES
───────────────────────────────────────────────────────
All of the above, plus:
void        — returns nothing
never       — does not return (throws / exits)
self        — returns this exact class
static      — returns the runtime class (use in hierarchies)
parent      — returns the parent class (rare)

STRICT MODE
───────────────────────────────────────────────────────
declare(strict_types=1);   // Must be first statement
                           // Affects the CALLING file only
                           // Disables silent coercion for scalars
```

---

## ✅ Lesson Checklist

- [ ] Read this README fully — especially the `self` vs `static` distinction (Section 5)
- [ ] Run and study `examples/01-scalar-types-and-strict-mode.php`
- [ ] Run and study `examples/02-nullable-and-union-types.php`
- [ ] Run and study `examples/03-void-never-mixed.php`
- [ ] Run and study `examples/04-self-static-parent.php`
- [ ] Run and study `examples/05-intersection-types.php`
- [ ] Read `challenge/CHALLENGE.md` and complete `challenge/starter.php`
- [ ] Check your work against `challenge/solution.php`
- [ ] Complete `quiz/QUIZ.md` without looking at any files

---

*Next lesson: **2.2 — PHP 8.5 Property Hooks** — replacing boilerplate getters and setters.*