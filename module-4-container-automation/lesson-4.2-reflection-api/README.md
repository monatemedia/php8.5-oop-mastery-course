# Lesson 4.2 — PHP Reflection API
> **Module 4: Container Automation with PHP-DI** · PHP 8.5 OOP Mastery Course

---

## 📁 Lesson Folder Structure

```
lesson-4.2-reflection-api/
├── README.md                              ← Theory (you are here)
│
├── examples/
│   ├── 01-reflection-basics.php          ← ReflectionClass, methods, properties
│   ├── 02-reading-constructor-params.php ← ReflectionParameter + type inspection
│   ├── 03-handling-edge-cases.php        ← Primitives, nullable, union, no constructor
│   └── 04-caching-reflection.php         ← Why and how to cache results
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

## 1 — Why This Lesson Exists

In Lesson 4.1 you built a `SimpleContainer` where every binding required a manually written factory:

```php
$container->singleton(OrderService::class, fn($c) => new OrderService(
    $c->get(ProductRepositoryInterface::class),
    $c->get(DatabaseInterface::class),
    $c->get(MailerInterface::class),
    $c->get(LoggerInterface::class)
));
```

This is correct, but verbose. At 50+ services, it becomes the hardest file in the codebase to maintain.

The alternative — **auto-wiring** — reads the constructor type hints automatically and resolves dependencies without any manual factory. To do this, the container needs to inspect class signatures at runtime. That is exactly what PHP's **Reflection API** provides.

Reflection is what separates a manual container from PHP-DI.

---

## 2 — What Is the Reflection API?

PHP's Reflection API is a set of built-in classes that let you inspect the structure of any class, interface, method, function, or property **at runtime** — without instantiating it.

```php
$ref = new ReflectionClass(OrderService::class);

echo $ref->getName();           // 'OrderService'
echo $ref->isAbstract();       // false
echo $ref->isInterface();      // false
echo count($ref->getMethods()); // e.g. 3
```

The Reflection API does not run the class — it reads its metadata. This is how PHP-DI knows that `OrderService` needs a `PaymentGatewayInterface` and a `LoggerInterface` before it ever calls `new OrderService(...)`.

---

## 3 — The Core Reflection Loop for Auto-Wiring

The container needs one piece of information: **the type hint of each constructor parameter**. Here is the exact loop every auto-wiring container uses:

```php
$refClass = new ReflectionClass(OrderService::class);
$refCtor  = $refClass->getConstructor();

if ($refCtor === null) {
    // No constructor — instantiate directly
    return new OrderService();
}

$deps = [];
foreach ($refCtor->getParameters() as $param) {
    $type = $param->getType();

    if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
        // It's a class or interface — resolve it from the container
        $deps[] = $container->get($type->getName());
    } else {
        // It's a primitive (string, int, bool) or has no type hint
        // Cannot auto-wire — need an explicit binding
        throw new \RuntimeException(
            "Cannot auto-wire '\${$param->getName()}' in " . $refClass->getName()
        );
    }
}

return $refClass->newInstanceArgs($deps);
```

This loop is the entire engine of auto-wiring. PHP-DI runs this (with caching and more edge case handling) for every class it resolves.

---

## 4 — Key Classes in the Reflection API

| Class | What it represents | Key methods |
|-------|--------------------|-------------|
| `ReflectionClass` | A class or interface | `getConstructor()`, `getMethods()`, `getProperties()`, `isAbstract()`, `isInterface()` |
| `ReflectionMethod` | A method | `getParameters()`, `isPublic()`, `isAbstract()` |
| `ReflectionParameter` | One constructor/method parameter | `getName()`, `getType()`, `isOptional()`, `hasDefaultValue()`, `getDefaultValue()` |
| `ReflectionNamedType` | A single type hint (e.g. `string`, `LoggerInterface`) | `getName()`, `isBuiltin()`, `allowsNull()` |
| `ReflectionUnionType` | A union type (e.g. `int\|string`) | `getTypes()` |
| `ReflectionIntersectionType` | An intersection type (e.g. `Countable&Traversable`) | `getTypes()` |

---

## 5 — `isBuiltin()` — The Auto-Wiring Boundary

`isBuiltin()` on a `ReflectionNamedType` returns `true` for PHP's scalar types (`string`, `int`, `float`, `bool`, `array`, `callable`, `void`, `null`, `mixed`, `never`) and `false` for class/interface names.

This is the auto-wiring boundary:

```php
// Constructor: __construct(DatabaseInterface $db, string $dsn, int $port)

// DatabaseInterface — isBuiltin() = false → auto-wire (look up in container)
// string $dsn       — isBuiltin() = true  → cannot auto-wire (need explicit binding)
// int $port         — isBuiltin() = true  → cannot auto-wire (need explicit binding)
```

**Course Philosophy Rule 3** — the type system as security layer — pays off directly here: because you type-hint your constructor parameters against interfaces (not `mixed` or strings), the container can resolve them automatically. Parameters typed as `string` or `int` cannot be auto-wired and require explicit factory definitions (covered in Lesson 4.4).

---

## 6 — Performance: Why Real Containers Cache Reflection

`ReflectionClass` and `ReflectionParameter` are not free. Reflecting on a class reads its opcache representation and creates PHP objects. For a class that is resolved thousands of times per second in a high-throughput application, this adds up.

Real containers solve this in two ways:

**1. In-memory cache (per-request):**
```php
private array $reflectionCache = [];

private function getConstructorParams(string $class): array {
    if (isset($this->reflectionCache[$class])) {
        return $this->reflectionCache[$class];
    }
    $ref    = new ReflectionClass($class);
    $params = $ref->getConstructor()?->getParameters() ?? [];
    return $this->reflectionCache[$class] = $params;
}
```

**2. Compiled container (PHP-DI production mode):**
PHP-DI can compile the entire container to a plain PHP file (`var/cache/CompiledContainer.php`) that contains no Reflection calls at all — just direct `new ClassName(...)` calls. This is what `ContainerBuilder::enableCompilation()` does in Lesson 4.4.

For this lesson, an in-memory cache is sufficient.

---

## 7 — Quick Reference

```php
// Inspect a class
$ref = new ReflectionClass(MyService::class);
$ref->getName();           // fully-qualified class name
$ref->getConstructor();    // ReflectionMethod|null
$ref->isAbstract();
$ref->isInterface();
$ref->newInstanceArgs([$dep1, $dep2]); // instantiate with resolved deps

// Inspect constructor parameters
foreach ($ref->getConstructor()->getParameters() as $param) {
    $param->getName();            // 'db', 'logger', etc.
    $param->getType();            // ReflectionNamedType|ReflectionUnionType|null
    $param->isOptional();         // true if has default value
    $param->hasDefaultValue();    // true if default is defined
    $param->getDefaultValue();    // the actual default value
}

// Inspect a type hint
$type = $param->getType();
if ($type instanceof ReflectionNamedType) {
    $type->getName();     // 'LoggerInterface', 'string', 'int', etc.
    $type->isBuiltin();   // true = scalar, false = class/interface
    $type->allowsNull();  // true if ?LoggerInterface
}

// Union type (int|string)
if ($type instanceof ReflectionUnionType) {
    foreach ($type->getTypes() as $t) { /* each ReflectionNamedType */ }
}
```

---

## ✅ Lesson Checklist

- [ ] Read this README fully — especially Sections 3 (the core loop) and 5 (`isBuiltin()`)
- [ ] Run and study `examples/01-reflection-basics.php`
- [ ] Run and study `examples/02-reading-constructor-params.php`
- [ ] Run and study `examples/03-handling-edge-cases.php`
- [ ] Run and study `examples/04-caching-reflection.php`
- [ ] Read `challenge/CHALLENGE.md` and complete `challenge/starter.php`
- [ ] Check your work against `challenge/solution.php`
- [ ] Complete `quiz/QUIZ.md` without looking at any files

---

*Next lesson: **4.3 — Auto-wiring** — use the Reflection loop to resolve entire dependency graphs automatically.*