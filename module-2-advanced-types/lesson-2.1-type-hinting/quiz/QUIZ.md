# Quiz — Lesson 2.1: Type Hinting & Return Types
> Complete this quiz **without** looking at any example or solution files.
> Write your answers before checking the answer key at the bottom.
> Any question you get wrong is a reading target.

---

## Section A — Multiple Choice

**Q1.** `declare(strict_types=1)` is placed at the top of a file. What does it affect?

- A) All files in the same directory.
- B) All calls made from this file to any function or method.
- C) All function definitions anywhere in the codebase.
- D) Only functions defined inside this file.

---

**Q2.** Without `strict_types=1`, what does PHP do when a function typed `int` receives the string `"42"`?

- A) Throws a `TypeError`.
- B) Silently coerces `"42"` to the integer `42`.
- C) Returns `null`.
- D) Throws a warning but continues.

---

**Q3.** What is the difference between `?string` and `string|null`?

- A) `?string` only works in PHP 7, `string|null` requires PHP 8.
- B) `?string` accepts `null` but `string|null` does not.
- C) They are completely equivalent — `?string` is shorthand for `string|null`.
- D) `string|null` allows empty strings; `?string` does not.

---

**Q4.** A function is declared `: void`. Which of the following does PHP reject?

- A) The function has a `return;` statement with no value.
- B) The function falls off the end without a `return` statement.
- C) The function returns `null` explicitly: `return null;`
- D) The function returns an empty array: `return [];`

---

**Q5.** What is the `never` return type?

- A) A function that returns `null` by default.
- B) A function that always throws an exception or calls `exit()` — it never returns normally.
- C) A function whose return value must never be used.
- D) An alias for `void` — both mean the function returns nothing.

---

**Q6.** You have a class `QueryBuilder` with a method `where(): self`. A subclass `UserQueryBuilder extends QueryBuilder` calls `where()`. What type does the static analyser believe `where()` returns?

- A) `UserQueryBuilder` — `self` always refers to the runtime class.
- B) `QueryBuilder` — `self` refers to the class where the method is defined.
- C) `static` — PHP resolves `self` at runtime.
- D) `mixed` — PHP cannot determine the type from `self`.

---

**Q7.** Which of the following is a valid intersection type declaration in PHP 8.1+?

- A) `int&string`
- B) `?Countable&Traversable`
- C) `Countable&Traversable`
- D) `array&Countable`

---

**Q8.** A function is declared `function process(Loggable&Serialisable $entity): void`. Which objects can be passed?

- A) Objects that implement `Loggable` only.
- B) Objects that implement `Serialisable` only.
- C) Objects that implement both `Loggable` and `Serialisable`.
- D) Any object — the intersection is checked at runtime only.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 9  | `declare(strict_types=1)` must be the very first statement in the file, before any other code including `namespace`. | |
| 10 | A function typed `: never` that sometimes returns a value and sometimes throws is valid PHP. | |
| 11 | `static` as a return type refers to the runtime class (late static binding), while `self` always refers to the class where the method is written. | |
| 12 | `mixed` is equivalent to having no type declaration — it accepts and returns any type. | |
| 13 | Intersection types can include `null` using `?A&B` syntax. | |
| 14 | A union type `int|float` will accept an integer value in strict mode. | |

---

## Section C — Short Answer

**Q15.** Explain in two sentences why `static` is almost always preferable to `self` for return types in methods that are designed to be inherited.

*Your answer:*

---

**Q16.** A colleague writes this function:
```php
function getUser(int $id): mixed {
    $users = [1 => 'Alice', 2 => 'Bob'];
    return $users[$id] ?? null;
}
```
What two improvements should they make to the return type, and why?

*Your answer:*

---

**Q17.** What is the practical difference between `void` and `never`? Give a one-line code example of each.

*Your answer:*

---

## Section D — Code Reading

**Q18.** What will the following code output? Write the output exactly, or write "Fatal error / TypeError" and explain why.

```php
<?php
declare(strict_types=1);

function multiply(int $a, int $b): int {
    return $a * $b;
}

echo multiply(4, 5) . "\n";
echo multiply(4.0, 5) . "\n";
```

*Your answer:*

---

**Q19.** What will the following code output? Write the output exactly, or write "Fatal error / TypeError" and explain why.

```php
<?php
declare(strict_types=1);

class Builder {
    protected array $parts = [];

    public function add(string $part): static {
        $this->parts[] = $part;
        return $this;
    }

    public function build(): string {
        return implode(', ', $this->parts);
    }
}

class HtmlBuilder extends Builder {
    public function wrap(string $tag): static {
        $this->parts = array_map(
            fn(string $p) => "<{$tag}>{$p}</{$tag}>",
            $this->parts
        );
        return $this;
    }
}

$result = (new HtmlBuilder())
    ->add('one')
    ->add('two')
    ->wrap('span')
    ->build();

echo $result . "\n";
```

*Your answer:*

---

**Q20.** What will the following code output? Write the output exactly, or write "Fatal error / TypeError" and explain why.

```php
<?php
declare(strict_types=1);

function orNull(int|string $value): ?string {
    if (is_int($value)) {
        return null;
    }
    return strtoupper($value);
}

var_dump(orNull(42));
var_dump(orNull('hello'));
var_dump(orNull(3.14));
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
| 1 | **B** | `strict_types=1` governs calls made *from* this file to any function — it is per-calling-file, not per-definition-file. |
| 2 | **B** | In coercive mode (default), PHP silently converts `"42"` to `42`. No error, no warning. |
| 3 | **C** | `?string` is exact shorthand for `string|null`. They are fully equivalent in PHP 8+. |
| 4 | **C** | `return null;` returns a value from a `void` function. PHP rejects it **at compile time**, which is worth being exact about — this is not a runtime `TypeError`, it is a fatal that stops the file being run at all: *"A void function must not return a value (did you mean \"return;\" instead of \"return null;\"?)"*. A bare `return;` and falling off the end of the function are both fine. |
| 5 | **B** | `never` means the function never returns normally — it always throws or calls `exit()`. Useful for static analysis to mark subsequent code as unreachable. |
| 6 | **B** | `self` is resolved at compile time to the class where the method is *defined* — `QueryBuilder`. Static analysers see `where()` as returning `QueryBuilder`, not `UserQueryBuilder`. |
| 7 | **C** | Only `Countable&Traversable` is valid. `int&string` fails (scalars cannot be intersected), `?Countable&Traversable` is a parse error (use `(Countable&Traversable)|null`), and `array` is not a named type. |
| 8 | **C** | Intersection requires ALL listed types — the object must implement both `Loggable` and `Serialisable`. |

## Section B
| # | Answer | Explanation |
|---|--------|-------------|
| 9  | **T** | It must be the first *statement* in the file. Comments and the opening `<?php` tag may precede it; `namespace` may not — that ordering is a fatal error, *"strict_types declaration must be the very first statement in the script"*. Note the wording: first **statement**, not first line. |
| 10 | **F** | A `never`-typed function must NEVER return. If a code path exists that returns normally, PHP throws a compile-time error. |
| 11 | **T** | `static` uses late static binding — it resolves to the actual runtime class. `self` resolves to the class where the method is written. |
| 12 | **F** | At a call site the two behave alike — both accept anything. They are not the same declaration, and inheritance is where the difference shows. `mixed` is a real type and participates in variance checks, so a parent declaring `: mixed` **cannot** be overridden with `: void` (*"Declaration of B::f(): void must be compatible with A::f(): mixed"*), while a parent with no return type at all can be. `void` is not a subtype of `mixed`; it is not a type of value.<br><br>The distinction matters beyond the trivia. Writing `mixed` says "I considered the type and any value is genuinely acceptable here". Writing nothing says "no one has decided yet". Those communicate opposite things to the next reader, which is Golden Rule 3 in miniature. |
| 13 | **F** | `?A&B` is a parse error. The correct form is `(A&B)|null` — a DNF (Disjunctive Normal Form) type, available from PHP 8.2+. |
| 14 | **T** | `int` is within the `int|float` union. An integer value satisfies the `int` alternative. |

## Section C

**Q15 — Model answer:**
`self` resolves to the class where the method is physically written, so a subclass calling `where()` gets back the parent class — breaking method chaining. `static` resolves at runtime to whichever class was actually instantiated, so `(new UserQueryBuilder())->where(...)` correctly returns a `UserQueryBuilder`, preserving the full method chain.

**Q16 — Model answer:**
The return type should be `?string` (or `string|null`) rather than `mixed`. `mixed` gives callers no information — they do not know whether to expect a string, an integer, an array, or anything else. `?string` tells callers that the function either returns a string or `null`, enabling them to handle both cases with confidence. Additionally, `mixed` bypasses strict type checking entirely.

**Q17 — Model answer:**
`void` means the function completes normally and returns no useful value. `never` means the function does not complete — execution leaves via an exception or `exit()`.
```php
function logAndReturn(): void  { echo "done\n"; }         // completes, returns nothing
function bail(string $msg): never { throw new \Exception($msg); } // never returns
```

## Section D

**Q18 — Answer:**
```
20
```
Then: `TypeError: multiply(): Argument #1 ($a) must be of type int, float given`

`multiply(4, 5)` is valid — outputs `20`. `multiply(4.0, 5)` passes a `float` where `int` is required. With `strict_types=1`, PHP throws a `TypeError` instead of coercing `4.0` to `4`.

**Q19 — Answer:**
```
<span>one</span>, <span>two</span>
```
`add()` returns `static` — so calling it on `HtmlBuilder` returns `HtmlBuilder` back. This means `wrap()` (which only exists on `HtmlBuilder`) is available in the chain. If `add()` had returned `self` (i.e. `Builder`), `->wrap()` would be a `TypeError` from the static analyser's perspective (though it might still work at runtime). The output is the two wrapped strings joined by `, `.

**Q20 — Answer:**
```
NULL
string(5) "HELLO"
TypeError
```
`orNull(42)` — `42` is an `int`, matches the union, returns `null` → `var_dump` prints `NULL`.
`orNull('hello')` — `'hello'` is a `string`, matches the union, `strtoupper` returns `"HELLO"` → `var_dump` prints `string(5) "HELLO"`.
`orNull(3.14)` — `3.14` is a `float`, which is NOT in the `int|string` union. With `strict_types=1`, PHP throws a `TypeError` — no coercion to `int` occurs.

---

## Score Guide

| Score | Verdict |
|-------|---------|
| 18–20 | Ready for Lesson 2.2 — strong type system mastery. |
| 14–17 | Re-read the README sections for any missed questions, then move on. |
| Below 14 | Re-run the examples, redo the challenge, then retake the quiz before continuing. |