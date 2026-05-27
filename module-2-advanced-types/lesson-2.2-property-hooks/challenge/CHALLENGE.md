# Code Challenge — Lesson 2.2: PHP 8.5 Property Hooks

> **Rewrite a class with six getter/setter pairs using property hooks**
> ⚠️  PHP 8.5.

---

## The Brief

You have been given a `BlogArticle` class written in the traditional pre-8.4 style: six private properties, six getter methods, and six setter methods — eighteen lines of method signatures before any real logic. Your job is to rewrite the class using PHP 8.4 property hooks, eliminating every getter and setter while preserving all the validation, transformation, and computed value logic exactly.

---

## What the Starter Class Does

Open `starter.php`. The `BlogArticle` class has:

| Property | Getter | Setter behaviour |
|----------|--------|-----------------|
| `$title` | `getTitle(): string` | `setTitle()` — trims whitespace |
| `$body` | `getBody(): string` | `setBody()` — trims whitespace |
| `$author` | `getAuthor(): string` | `setAuthor()` — trims, title-cases |
| `$publishedAt` | `getPublishedAt(): ?DateTimeImmutable` | `setPublishedAt()` — accepts string or DateTimeImmutable |
| `$tags` | `getTags(): array` | `setTags()` — lowercases, trims, deduplicates, sorts |
| `$slug` | `getSlug(): string` | Computed from `$title` — no setter |

---

## Your Tasks

Work in `starter.php`. Do NOT look at `solution.php` until you have made a genuine attempt.

### Task 1 — Add `declare(strict_types=1)`
First line of the file.

### Task 2 — Convert `$title` and `$body`
Both are plain strings that just need trimming on write. Use a `set` hook with arrow syntax.

### Task 3 — Convert `$author`
Needs trimming and title-casing on write (`ucwords(strtolower(...))`). Use a `set` hook.

### Task 4 — Convert `$publishedAt`
The setter accepts either a `string` (parses it with `DateTimeImmutable::createFromFormat`) or a `DateTimeImmutable` directly. With hooks, the property type is `?DateTimeImmutable`, so the setter must accept a union `string|\DateTimeImmutable` and convert strings.

Note: a property hook's `set` parameter type may be **wider** than the property's declared type — this is how you handle multi-type input. The property itself stores only `?DateTimeImmutable`.

### Task 5 — Convert `$tags`
The setter lowercases, trims, deduplicates, and sorts the tags. Use a `set` hook with block syntax.

### Task 6 — Convert `$slug` to a virtual property
`$slug` is computed from `$title` — it has no storage and cannot be set directly. Convert it to a virtual property with a `get` hook.

### Task 7 — Update the interface
Define an interface `Article` that uses property hook syntax to declare contracts:
- `$title` — readable and writable: `{ get; set; }`
- `$author` — readable and writable: `{ get; set; }`
- `$slug` — readable only: `{ get; }`
- `$tags` — readable and writable: `{ get; set; }`

Make `BlogArticle implement Article`.

### Task 8 — Update the calling code
Replace all `->getX()` and `->setX()` calls with direct property access. The output must remain identical.

---

## Acceptance Criteria

- [ ] `declare(strict_types=1)` is the first statement
- [ ] `BlogArticle` has zero getter methods and zero setter methods
- [ ] All six properties use hooks or are virtual
- [ ] `$slug` is a virtual property (no default value, no set hook)
- [ ] `$publishedAt` set hook accepts `string|DateTimeImmutable` and stores only `DateTimeImmutable`
- [ ] `$tags` set hook lowercases, trims, deduplicates, and sorts — exactly as before
- [ ] Interface `Article` is defined with property hook syntax
- [ ] `BlogArticle implements Article`
- [ ] All calling code uses direct property access — no `get*()` or `set*()` calls remain
- [ ] Output is identical to the starter file

---

## Expected Output

```
=== Article 1 ===
Title:  PHP 8.4 is Here
Author: Alice Smith
Slug:   php-8-4-is-here
Tags:   hooks, new-features, php
Published: 2024-11-21
Body preview: PHP 8.4 introduces property hooks, which replace boilerplate...

=== Article 2 ===
Title:  OOP Design Patterns
Author: Bob Jones
Slug:   oop-design-patterns
Tags:   design-patterns, oop, php
Published: (not yet published)
Body preview: Design patterns are reusable solutions to common problems...

=== Type-safe function ===
[ARTICLE] php-8-4-is-here by Alice Smith (hooks, new-features, php)
[ARTICLE] oop-design-patterns by Bob Jones (design-patterns, oop, php)
```

---

## Hints

- For `$publishedAt`: the set hook parameter can be `string|\DateTimeImmutable`. Inside the hook, check `is_string($value)` and parse it; otherwise store directly.
- For `$slug`: no default value + no set hook = virtual property. The get hook derives it from `$this->title`.
- The interface `{ get; }` syntax requires PHP 8.4 — make sure your XAMPP PHP version is correct.
- See `examples/05-hooks-in-interfaces-and-abstract.php` for the full interface pattern.