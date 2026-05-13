# Code Challenge — Lesson 1.3: Traits

> **Extract three cross-cutting concerns into traits across two unrelated class hierarchies**

---

## The Brief

You have been handed a small e-commerce application with two completely separate class hierarchies: one for **content** (blog posts and landing pages) and one for **commerce** (products and orders). Both hierarchies have the same three cross-cutting concerns duplicated inside every class:

1. **Logging** — every class logs its own actions to a local array and can print them.
2. **Timestamps** — every class tracks `created_at` and `updated_at`.
3. **JSON serialisation** — every class can serialise itself to JSON.

The code is fully working, but every concern is copy-pasted across four classes. Any change — say, a new field in the timestamp log — requires editing all four files.

Your job is to extract all three concerns into traits, wire them up with interfaces for type safety, and reduce each class to only what is unique to it.

---

## What is Wrong With the Starter Code

Open `starter.php`. You will find four classes — `BlogPost`, `LandingPage`, `Product`, and `Order` — each with **identical** blocks of code for logging, timestamps, and JSON serialisation. The differences are only in the business-logic methods unique to each class.

Specific duplications:
- `$log = []`, `addLog()`, `getLogs()`, `printLogs()` — identical in all four classes
- `$createdAt`, `$updatedAt`, `initTimestamps()`, `touchUpdatedAt()`, `getCreatedAt()`, `getUpdatedAt()` — identical in all four classes
- `toJson()` — identical in all four classes; `toArray()` differs per class (intentionally)

---

## Your Tasks

Work in `starter.php`. Do NOT look at `solution.php` until you have made a genuine attempt.

### Task 1 — Create the `Loggable` interface and `LoggableTrait`
**Interface** `Loggable`:
- `addLog(string $action, array $context = []): void`
- `getLogs(): array`
- `printLogs(): void`

**Trait** `LoggableTrait` — full implementation of all three methods:
- `addLog()` stores `['action', 'context', 'timestamp']` entries in a `$log` array
- `getLogs()` returns the array
- `printLogs()` echoes each entry formatted as `[timestamp] action {json context}`

### Task 2 — Create the `Timestampable` interface and `TimestampableTrait`
**Interface** `Timestampable`:
- `getCreatedAt(): string`
- `getUpdatedAt(): string`
- `touchUpdatedAt(): void`

**Trait** `TimestampableTrait` — full implementation:
- `initTimestamps()` — sets both to `date('Y-m-d H:i:s')` (called from constructor)
- Implements all three interface methods

### Task 3 — Create the `JsonSerialisable` interface and `JsonSerialisableTrait`
**Interface** `JsonSerialisable`:
- `toArray(): array`
- `toJson(): string`

**Trait** `JsonSerialisableTrait`:
- `toJson()` — calls `$this->toArray()` and returns `json_encode` with `JSON_PRETTY_PRINT`
- Does NOT implement `toArray()` — each class defines its own

### Task 4 — Refactor all four classes
Each class must:
- `implement Loggable, Timestampable, JsonSerialisable`
- `use LoggableTrait, TimestampableTrait, JsonSerialisableTrait`
- Remove all duplicated code — keep only business-logic methods and `toArray()`
- Call `$this->initTimestamps()` and `$this->addLog('created')` from each constructor

### Task 5 — Add two type-safe functions
```php
function printEntityLog(Loggable $entity): void { ... }
function exportToJson(JsonSerialisable $entity): string { ... }
```
Call both functions on each of the four models at the bottom of the file.

---

## Acceptance Criteria

- [ ] Three interfaces defined — `Loggable`, `Timestampable`, `JsonSerialisable`
- [ ] Three traits defined — `LoggableTrait`, `TimestampableTrait`, `JsonSerialisableTrait`
- [ ] All four classes implement all three interfaces and use all three traits
- [ ] Zero duplicated logging, timestamp, or `toJson()` code inside the classes
- [ ] `toArray()` remains unique per class (it is intentionally different)
- [ ] `printEntityLog()` and `exportToJson()` are type-hinted against interfaces — not trait names
- [ ] Adding a fifth model class only requires: `implement` the interfaces + `use` the traits + write `toArray()` — no changes to traits or interfaces

---

## Expected Output (abbreviated)

```
=== BlogPost ===
Logs:
  [Y-m-d H:i:s] created {}
  [Y-m-d H:i:s] published {"slug":"my-first-post"}
JSON:
{
    "title": "My First Post",
    "slug": "my-first-post",
    "author": "Alice",
    "created_at": "Y-m-d H:i:s",
    "updated_at": "Y-m-d H:i:s"
}

=== Product ===
Logs:
  [Y-m-d H:i:s] created {}
  [Y-m-d H:i:s] stock_updated {"from":100,"to":85}
JSON:
{
    "sku": "WDG-001",
    "name": "Widget Pro",
    "price": 299,
    "stock": 85,
    "created_at": "Y-m-d H:i:s",
    "updated_at": "Y-m-d H:i:s"
}
```

---

## Hints

- `JsonSerialisableTrait` does **not** implement `toArray()` — the interface requires it, so the host class provides it. The trait only provides `toJson()`.
- `TimestampableTrait` needs an `initTimestamps()` method that is called from the constructor — this is a good use of a concrete (non-abstract) method in the trait.
- `LoggableTrait::addLog()` should use `date('Y-m-d H:i:s')` for the timestamp.
- See `examples/04-traits-with-interfaces.php` for the full interface + trait pattern.