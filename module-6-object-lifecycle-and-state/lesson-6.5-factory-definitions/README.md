# Lesson 6.5 — Factory Definitions for Complex Lifecycles
> **Module 6: Object Lifecycle & State Management** · PHP 8.5 OOP Mastery Course

---

## 📁 Lesson Folder Structure

```
lesson-6.5-factory-definitions/
├── README.md                                      ← Theory (you are here)
│
├── examples/
│   ├── 01-factory-basics.php                      ← Simple factory for non-type-hinted constructor args
│   ├── 02-transient-factories.php                 ← Shopping cart and RequestContext as transients
│   ├── 03-decorator-in-container.php              ← LoggingGateway wraps StripeGateway via factory
│   └── 04-environment-bindings.php                ← Production vs test wiring via APP_ENV
│
├── challenge/
│   ├── CHALLENGE.md
│   ├── starter/
│   │   └── FactoryDefinitionsTest.php             ← Scaffold — wire four factory definitions
│   └── solution/
│       └── FactoryDefinitionsTest.php             ← Full solution with commentary
│
└── quiz/
    └── QUIZ.md
```

**How to use this lesson:**
1. Read this README fully — Sections 2 through 6 are the core.
2. Run each example with `./vendor/bin/phpunit` and read every annotation.
3. Work through the challenge before opening the solution.
4. Take the quiz cold.

---

## 1 — When Auto-Wiring Is Not Enough

PHP-DI's auto-wiring resolves constructor arguments by type. It works perfectly when every constructor argument is a type-hinted class or interface that is itself registered in the container. It breaks in four situations:

| Situation | Why auto-wiring fails | Solution |
|-----------|----------------------|----------|
| Non-type-hinted constructor arg | `string $dsn`, `int $cost`, `array $config` have no type the container can resolve | `factory()` or `create()->constructor()` |
| Transient scope | Auto-wiring defaults to singleton | `factory(fn() => new ClassName())` |
| Runtime-dependent construction | Object needs data only available at request time (current user, HTTP path) | `factory()` that reads runtime data |
| Decorator pattern | One binding must wrap another resolved from the container | `factory(function(Real $r) { return new Decorator($r); })` |

All four are solved by the `factory()` definition — a callable that PHP-DI invokes each time the binding is resolved.

---

## 2 — Factory Basics: Non-Type-Hinted Constructor Arguments

### The problem

```php
class DatabaseConnection
{
    public function __construct(string $dsn, string $user, string $password)
    {
        $this->pdo = new \PDO($dsn, $user, $password);
    }
}
```

Auto-wiring cannot resolve `string $dsn` — there is nothing in the container labelled "string". PHP-DI would throw: `"Parameter $dsn of class DatabaseConnection has no value defined or guessable."`.

### Solution A — `create()->constructor()`

```php
return [
    DatabaseConnection::class => create(DatabaseConnection::class)
        ->constructor(
            DI\env('DB_DSN'),   // reads DATABASE_URL from environment
            DI\env('DB_USER'),
            DI\env('DB_PASS'),
        ),
];
```

`create()` produces a singleton. `DI\env()` reads the environment variable at container build time and passes the value as the constructor argument.

### Solution B — `factory()` callable

```php
return [
    DatabaseConnection::class => factory(function (): DatabaseConnection {
        return new DatabaseConnection(
            dsn:      getenv('DB_DSN')  ?: throw new \RuntimeException('DB_DSN not set'),
            user:     getenv('DB_USER') ?: throw new \RuntimeException('DB_USER not set'),
            password: getenv('DB_PASS') ?: throw new \RuntimeException('DB_PASS not set'),
        );
    }),
];
```

The `factory()` callable has full PHP power: you can validate, throw, branch, or call any code. It runs each time the binding is resolved (making it transient by default in terms of invocation — but if you want singleton behaviour, use `DI\value()` or wrap in a `static` variable).

### PHP-DI's `factory()` callable argument injection

The callable passed to `factory()` can itself declare type-hinted parameters. PHP-DI resolves and injects them:

```php
factory(function (LoggerInterface $logger, ClockInterface $clock): SomeService {
    return new SomeService($logger, $clock, getenv('SOME_KEY'));
})
```

`$logger` and `$clock` are resolved from the container. `getenv('SOME_KEY')` is read inline. This is the cleanest pattern for a class that needs both injected dependencies and scalar constructor arguments.

---

## 3 — Transient Factories

As covered in Lesson 6.2, `factory()` is the PHP-DI idiom for transient scope. Every `$container->get()` call invokes the factory and returns a new instance.

```php
return [
    // Transient: new cart per resolution — prevents cross-user contamination
    ShoppingCart::class => factory(fn() => new ShoppingCart()),

    // Transient with injected dependency:
    RequestContext::class => factory(function (
        ServerRequestInterface $request,
        AuthService $auth,
    ): RequestContext {
        $user = $auth->resolveFromRequest($request);
        return $user
            ? RequestContext::authenticated($user, uniqid('req-'), $request->getUri()->getPath())
            : RequestContext::anonymous(uniqid('req-'), $request->getUri()->getPath());
    }),
];
```

### Verifying transient scope

```php
$a = $container->get(ShoppingCart::class);
$b = $container->get(ShoppingCart::class);

assert($a !== $b); // different instances — factory was invoked twice
```

### Combining transient scope with lifecycle-safe design

Transient scope solves the singleton contamination bug from Lesson 6.1–6.3. But the preferred long-term approach is to design services to be stateless (Lesson 6.4) so scope becomes irrelevant. Use transient scope when:

- The class has legitimate per-request state that cannot be eliminated (e.g. `RequestContext`, a per-request logger with a bound request ID)
- You are working with a third-party class whose statefulness you cannot change
- The class's lifecycle is complex enough to require a factory anyway

---

## 4 — The Decorator Pattern in a Container

### What it solves

You have a `PaymentGatewayInterface` with a real `StripeGateway` implementation. You want to add cross-cutting behaviour (logging, metrics, retry logic) without modifying `StripeGateway`. The decorator pattern wraps the real implementation:

```
PaymentGatewayInterface
    └─ LoggingGateway (decorator)
            └─ StripeGateway (real implementation)
```

### The challenge

Both `LoggingGateway` and `StripeGateway` implement `PaymentGatewayInterface`. If you bind `PaymentGatewayInterface` to `LoggingGateway` and `LoggingGateway` depends on `PaymentGatewayInterface`, you create a circular reference — `LoggingGateway` would receive itself.

### The solution: factory() that resolves the inner class directly

```php
return [
    // Bind the concrete inner class explicitly
    StripeGateway::class => autowire(StripeGateway::class),

    // Bind the interface to the decorator, resolved via factory
    PaymentGatewayInterface::class => factory(function (
        StripeGateway $stripe,      // concrete class — no circularity
        LoggerInterface $logger,
    ): PaymentGatewayInterface {
        return new LoggingGateway($stripe, $logger);
    }),
];
```

The key: `LoggingGateway` depends on `PaymentGatewayInterface` in its business logic, but the factory resolves `StripeGateway` (the concrete class) to construct it. No circular reference.

### Stacking decorators

```php
PaymentGatewayInterface::class => factory(function (
    StripeGateway $stripe,
    LoggerInterface $logger,
    MetricsCollector $metrics,
): PaymentGatewayInterface {
    $logged  = new LoggingGateway($stripe, $logger);     // inner
    $metered = new MetricsGateway($logged, $metrics);    // outer
    return $metered;
}),
```

The factory gives you full control over the decoration order without any PHP-DI extension.

---

## 5 — Environment-Based Bindings

### What it solves

In production, you want `SmtpMailer`. In development or test, you want `LogMailer` (writes to a log file instead of sending). In a CI pipeline, you want `NullMailer` (discards everything). All three implement `MailerInterface`.

### The solution: factory() that branches on an environment variable

```php
return [
    MailerInterface::class => factory(function (LoggerInterface $logger): MailerInterface {
        return match(getenv('APP_ENV')) {
            'production' => new SmtpMailer(
                host: getenv('SMTP_HOST') ?: 'localhost',
                port: (int) (getenv('SMTP_PORT') ?: 587),
            ),
            'test'       => new NullMailer(),
            default      => new LogMailer($logger),  // development
        };
    }),
];
```

This is wiring logic — it belongs at the composition root in the container definition, not inside `SmtpMailer`, `LogMailer`, or any business-logic class. The `APP_ENV` check is infrastructure; the services themselves are unaware of the environment.

### The `APP_ENV` contract

```
APP_ENV=production   → real external services (SMTP, Stripe, Redis)
APP_ENV=development  → log-based or in-memory fakes
APP_ENV=test         → null or in-memory implementations; no network calls
```

This contract is enforced in `01-factory-basics.php` and `04-environment-bindings.php`.

---

## 6 — The `factory()` Callable Signature Options

PHP-DI supports several factory callable forms:

```php
// Zero-arg closure — no injected dependencies
factory(fn() => new ShoppingCart())

// Type-hinted closure — PHP-DI injects named args
factory(function (LoggerInterface $logger, ClockInterface $clock): SomeService {
    return new SomeService($logger, $clock, getenv('KEY'));
})

// Invokable class — factory logic in a dedicated class
factory(ShoppingCartFactory::class)
// PHP-DI calls new ShoppingCartFactory() then ShoppingCartFactory::__invoke($injectedDeps)

// Array callable — static method
factory([SomeFactory::class, 'create'])
```

In practice, the type-hinted closure is the most readable for simple cases; an invokable factory class is appropriate when the construction logic is complex enough to deserve its own file and tests.

---

## 7 — Quick Reference

```
When to use factory():
  - Constructor has non-type-hinted scalar args (string, int, array)
  - Class needs transient scope (new instance per resolution)
  - Construction requires runtime data (APP_ENV, request data)
  - Decorator pattern: wrapping one implementation in another

PHP-DI factory syntax:
  factory(fn() => new Foo())
  factory(function(LoggerInterface $l): Foo { return new Foo($l, getenv('X')); })
  factory(FooFactory::class)            // invokable class

Alternative for scalar args (singleton only):
  create(Foo::class)->constructor(DI\env('DB_DSN'), DI\env('DB_USER'))

Decorator pattern:
  BarInterface::class => factory(function(ConcreteBar $bar, LoggerInterface $l) {
      return new LoggingBar($bar, $l);
  })
  // Always resolve the CONCRETE inner class, not the interface

Environment branching:
  SomeInterface::class => factory(function() {
      return match(getenv('APP_ENV')) {
          'production' => new RealImpl(...),
          'test'       => new NullImpl(),
          default      => new FakeImpl(),
      };
  })

Verifying singleton vs transient:
  Singleton: assertSame($a, $b)        // same object
  Transient: assertNotSame($a, $b)     // different objects
```

---

## ✅ Lesson Checklist

- [ ] Read this README fully — Sections 2 through 5 are the core content
- [ ] Run `examples/01-factory-basics.php` — non-type-hinted args and singleton factory
- [ ] Run `examples/02-transient-factories.php` — transient scope verification
- [ ] Run `examples/03-decorator-in-container.php` — logging decorator wiring
- [ ] Run `examples/04-environment-bindings.php` — APP_ENV-driven implementation selection
- [ ] Read `challenge/CHALLENGE.md` before opening the starter file
- [ ] Complete `challenge/starter/FactoryDefinitionsTest.php`
- [ ] Only open `challenge/solution/FactoryDefinitionsTest.php` after all tests pass
- [ ] Complete `quiz/QUIZ.md` cold

---

*This is the final lesson of Module 6. After completing the quiz, review the Module 6 checklist in `module-6-object-lifecycle-and-state/README.md` and ensure all lessons, challenges, and quizzes are marked complete.*