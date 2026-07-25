# Code Challenge — Lesson 2.0: Liskov Substitution Principle

> **Identify and fix three LSP violations in a content publishing system**

---

## The Brief

You have been handed a content management system that *runs without errors* but is riddled with LSP violations. The original developer used `instanceof` guards everywhere, some classes throw `BadMethodCallException` for methods they can't support, and one subclass silently swallows data. Your job is to restructure the hierarchy so that every class can be safely substituted for any type it claims to be.

---

## What is Wrong With the Starter Code

Open `starter.php` and read the three sections. You will find:

**Violation 1 — Content Renderer hierarchy**
`RssRenderer` extends `HtmlRenderer` but throws on `renderWithLayout()` because RSS has no concept of a layout. Callers get a runtime crash if they pass an `RssRenderer` where an `HtmlRenderer` is expected.

**Violation 2 — Storage backend hierarchy**
`InMemoryStorage` extends `DatabaseStorage` but `persist()` silently discards data (no-op). A caller that saves data and later reads it back will find nothing — the postcondition is broken silently.

**Violation 3 — Notification sender hierarchy**
`SmsNotificationSender` extends `EmailNotificationSender` but cannot support `addCcRecipient()`. It throws an exception. The caller has to add an `instanceof` guard to avoid it.

---

## Your Tasks

Work in `starter.php`. Do NOT look at `solution.php` until you have made a genuine attempt.

### Task 1 — Fix the Renderer hierarchy
Define a focused `Renderer` interface with only `render(string $content): string`.
Define a separate `LayoutRenderer` interface (extends `Renderer`) with `renderWithLayout(string $content, string $layout): string`.
Implement `HtmlRenderer implements LayoutRenderer` and `RssRenderer implements Renderer`.
Update the calling functions to type-hint at the correct level.

### Task 2 — Fix the Storage hierarchy
Define a `ReadableStorage` interface: `find(int $id): ?array`.
Define a `WritableStorage` interface: `persist(array $record): int`.
Define a `Storage` interface extending both (for backends that support both).
`DatabaseStorage implements Storage`. `InMemoryStorage implements Storage` — but this time, actually store the data in memory (an array property) so the postcondition is honoured.
`ReadOnlyFileStorage implements ReadableStorage` only — it cannot write.

### Task 3 — Fix the Notification hierarchy
Define a `BasicNotificationSender` interface: `send(string $to, string $message): bool`.
Define a `RichNotificationSender` interface (extends `BasicNotificationSender`) that adds `addCcRecipient(string $cc): void`.
`EmailNotificationSender implements RichNotificationSender` (email supports CC).
`SmsNotificationSender implements BasicNotificationSender` (SMS does not).
Remove all `instanceof` guards from calling code.

### Task 4 — Update the calling code
At the bottom of the file, wire up all the fixed classes and call the functions. Confirm that no `instanceof` guards are needed anywhere.

---

## Acceptance Criteria

- [ ] No class throws `BadMethodCallException` or similar for a method it signed in a contract.
- [ ] No class has a no-op method that should meaningfully store or return data.
- [ ] No calling function contains an `instanceof` check.
- [ ] `InMemoryStorage::persist()` actually stores data and `find()` can retrieve it.
- [ ] `SmsNotificationSender` cannot be passed to any function that calls `addCcRecipient()` — PHP prevents it at the type level, not via a runtime exception.
- [ ] All four renderers, storage backends, and notification senders work correctly.

---

## Hints

- The pattern is always the same: if a class cannot honestly implement a method, it should not be in a contract that includes that method. Split the interface.
- Look at `examples/02-fix-the-hierarchy.php` for the Bird/Gateway/Logger patterns.
- Look at `examples/03-covariance.php` for how interface hierarchies compose.
- For the NullObject Logger fix analogy — `InMemoryStorage` should actually store data in `$this->records[]`, not silently drop it.

---

## Expected Output

```
=== Renderers ===
[HTML] <div class="content">Hello World</div>
[RSS] <item><description>Hello World</description></item>
[HTML+LAYOUT] <html><body><main>Hello World</main></body></html>

=== Storage ===
[DB] Persisted record with id=1
[DB] Found: {"name":"Alice","id":1}
[MEMORY] Persisted record with id=1
[MEMORY] Found: {"name":"Alice","id":1}
[FILE] Read record id=1: {"id":1,"name":"Alice (from file)"}
(ReadOnlyFileStorage cannot be passed to persistRecord() — PHP prevents it)

=== Notifications ===
[EMAIL] To: alice@example.com | CC: manager@example.com | Msg: Hello Alice
[SMS]   To: +27821234567 | Msg: Hello Bob
[EMAIL] To: bob@example.com | CC: manager@example.com | Msg: Hello from basic sender
(SmsNotificationSender cannot be passed to sendRichNotification() — PHP prevents it)
```