# Lesson 4.3 — Auto-wiring
> **Module 4: Container Automation with PHP-DI** · PHP 8.5 OOP Mastery Course

---

## 📁 Lesson Folder Structure

```
lesson-4.3-auto-wiring/
├── README.md                              ← Theory (you are here)
│
├── examples/
│   ├── 01-basic-autowiring.php            ← Resolve a 2-level dependency chain
│   ├── 02-recursive-resolution.php        ← Resolve a 4-level chain automatically
│   ├── 03-circular-detection.php          ← Detect and report circular dependencies
│   └── 04-explicit-fallback.php           ← Explicit binding overrides auto-wiring
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

## 1 — What Auto-wiring Is

**Auto-wiring** is the ability of a container to resolve a class's dependencies without any manual `bind()` call for that class. The container reads the constructor type hints using Reflection (Lesson 4.2) and resolves each dependency recursively.

The two lessons before this one built the two halves:
- **Lesson 4.1** — a container that stores bindings and resolves them
- **Lesson 4.2** — the Reflection API that reads constructor type hints

This lesson combines them into a single `AutowiringContainer` that can resolve entire dependency graphs from a minimal set of interface→concrete bindings.

---

## 2 — The Auto-wiring Algorithm

```
resolve(id):
  ┌─ Is there an explicit binding for 'id'?
  │    YES → use the explicit binding (factory or singleton)
  │    NO  ↓
  ├─ Is 'id' in the singleton cache?
  │    YES → return cached instance
  │    NO  ↓
  ├─ Is 'id' currently being resolved? (circular check)
  │    YES → throw CircularDependencyException
  │    NO  ↓
  ├─ Mark 'id' as "being resolved"
  ├─ Reflect on the constructor of 'id'
  ├─ For each constructor parameter:
  │    ├─ Has a non-builtin type hint → resolve(param type) recursively
  │    ├─ Is optional → use default value
  │    └─ Required + builtin/untyped → throw UnresolvableParameterException
  ├─ Instantiate 'id' with resolved deps
  ├─ Unmark 'id' from "being resolved"
  ├─ Store in singleton cache
  └─ Return instance
```

---

## 3 — Explicit Bindings + Auto-wiring

Auto-wiring handles concrete classes automatically. Interfaces require one explicit binding each, because the container cannot know which concrete class to use for `DatabaseInterface` without being told.

```php
$container = new AutowiringContainer();

// Explicit bindings: interface → concrete (the container must be told these)
$container->bind(DatabaseInterface::class, InMemoryDatabase::class);
$container->bind(LoggerInterface::class,   ConsoleLogger::class);
$container->bind(MailerInterface::class,   ConsoleMailer::class);

// No binding needed for OrderService, ProductRepository, etc.
// The container reads their constructors and wires them automatically.
$service = $container->get(OrderService::class); // fully auto-wired ✓
```

This is the key insight: **you register interfaces, not services**. Every service class with only interface-typed constructor params is resolved automatically. The explicit binding list shrinks to just the interface→concrete mappings.

---

## 4 — Circular Dependency Detection

A circular dependency occurs when class A needs class B, and class B (directly or indirectly) needs class A:

```
ClassA → ClassB → ClassC → ClassA  ← circular!
```

Without detection, this would cause infinite recursion and a PHP fatal error (stack overflow). A proper container tracks which classes are currently being resolved and throws a clear error if it encounters one it is already resolving.

```
CircularDependencyException: Circular dependency detected:
  ClassA → ClassB → ClassC → ClassA
```

The "resolving stack" is just a `Set` of class names currently in-flight during recursive resolution.

---

## 5 — When Auto-wiring Fails

Auto-wiring cannot resolve:

| Situation | Why | Fix |
|-----------|-----|-----|
| Constructor requires `string $dsn` | `isBuiltin() = true` — no class to resolve | Register an explicit factory |
| Constructor requires `int $port` | Same | Register an explicit factory |
| Constructor requires an interface | Multiple implementations exist | Register explicit binding |
| Circular dependency | Infinite recursion | Redesign the classes |
| Abstract class or interface as a concrete target | Not instantiable | Register explicit binding |

**Course Philosophy Rule 3:** The more you type your constructor params as interfaces (not primitives), the more the container can auto-wire. Untyped or primitive constructor params are the main reason auto-wiring fails.

---

## 6 — How This Leads to PHP-DI

The `AutowiringContainer` built in this lesson does everything PHP-DI does for typical service resolution:

1. Reads constructor type hints via Reflection ✓
2. Resolves dependencies recursively ✓
3. Caches singletons ✓
4. Detects circular dependencies ✓
5. Supports explicit bindings that override auto-wiring ✓

PHP-DI adds on top:
- Factory definitions (`\DI\factory(callable)`) for classes needing primitives
- Transient scope for per-request objects
- Compiled container (zero Reflection overhead in production)
- PSR-11 compliance
- Framework integrations (Slim, Symfony, Laravel)

---

## 7 — Quick Reference

```php
$container = new AutowiringContainer();

// Explicit: interface → concrete class name (not an instance)
$container->bind(DatabaseInterface::class, InMemoryDatabase::class);

// Explicit: pre-built instance (for classes needing primitives)
$container->instance(DatabaseInterface::class, new MySQLDatabase(getenv('DB_DSN')));

// Auto-wiring: just call get() — no bind() needed for service classes
$service = $container->get(OrderService::class);

// The container resolves the full graph:
//   OrderService(DatabaseInterface $db, MailerInterface $mailer, LoggerInterface $logger)
//   → each resolved from bindings or auto-wired recursively
```

---

## ✅ Lesson Checklist

- [ ] Read this README fully — especially Sections 2 (the algorithm) and 3 (explicit + auto)
- [ ] Run and study `examples/01-basic-autowiring.php`
- [ ] Run and study `examples/02-recursive-resolution.php`
- [ ] Run and study `examples/03-circular-detection.php`
- [ ] Run and study `examples/04-explicit-fallback.php`
- [ ] Read `challenge/CHALLENGE.md` and complete `challenge/starter.php`
- [ ] Check your work against `challenge/solution.php`
- [ ] Complete `quiz/QUIZ.md` without looking at any files

---

*Next lesson: **4.4 — PHP-DI Library** — the production-grade container that does all of the above, plus factory definitions, scopes, and environment-based configuration.*