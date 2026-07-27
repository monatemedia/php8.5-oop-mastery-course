# Quiz — Lesson 6.5: Factory Definitions for Complex Lifecycles
> Complete this quiz **without** looking at any example or solution files.
> Write your answers before checking the answer key at the bottom.

---

## Section A — Multiple Choice

**Q1.** Which of the following is NOT a situation where auto-wiring fails and a `factory()` definition is required?

- A) The constructor takes a `string $dsn` parameter.
- B) The class needs transient scope.
- C) The constructor result must be wrapped in a decorator.
- D) The class takes a single `LoggerInterface` typed dependency.

---

**Q2.** Which of these gives you a **new instance on every resolution** in PHP-DI?

- A) None of them — `$container->make()` is the only built-in way to get a fresh instance.
- B) `create(ShoppingCart::class)`
- C) `factory(fn() => new ShoppingCart())`
- D) `autowire(ShoppingCart::class)`

---

**Q3.** You need to wire `LoggingGateway` as a decorator around `StripeGateway`. Both implement `PaymentGatewayInterface`. `LoggingGateway`'s constructor is `__construct(PaymentGatewayInterface $inner, LoggerInterface $logger)`. What happens if you bind both `LoggingGateway` AND `StripeGateway` to `PaymentGatewayInterface` in the same container?

- A) PHP-DI automatically chooses `StripeGateway` as the inner implementation.
- B) PHP-DI throws a "duplicate binding" exception.
- C) The last binding wins — `LoggingGateway` overrides `StripeGateway`.
- D) The factory for `LoggingGateway` tries to resolve `PaymentGatewayInterface` to inject as `$inner`, which triggers itself again — an infinite recursion or circular reference error.

---

**Q4.** How do you correctly wire a decorator to avoid the circular reference in Q3?

- A) Register `StripeGateway` under its own concrete class name, then inject `StripeGateway` (not the interface) in the decorator's factory.
- B) Use `DI\lazyLoad()` on the `$inner` parameter.
- C) Register both under the interface and use `DI\tagged()` to select the inner one.
- D) Implement `DecoratorInterface` on `LoggingGateway` and resolve that instead.

---

**Q5.** A `factory()` callable declares `function(LoggerInterface $logger, ClockInterface $clock): SomeService`. What does PHP-DI do with `$logger` and `$clock`?

- A) Ignores them — factory callables cannot receive injected dependencies.
- B) Creates new instances of `LoggerInterface` and `ClockInterface` for each factory invocation.
- C) Resolves them from the container (as singletons by default) and passes them as arguments.
- D) Throws a `CannotInjectIntoFactoryException` because factories are not injectable.

---

**Q6.** Which PHP-DI syntax correctly wires a `DatabaseConnection` that needs `string $dsn` from the `DB_DSN` environment variable as a **singleton**?

- A) `factory(fn() => new DatabaseConnection(getenv('DB_DSN'), getenv('DB_USER'), getenv('DB_PASS')))`
- B) `autowire(DatabaseConnection::class)->constructor(DI\env('DB_DSN'))`
- C) `create(DatabaseConnection::class)->constructor(DI\env('DB_DSN'), DI\env('DB_USER'), DI\env('DB_PASS'))`
- D) Both A and C are correct.

---

**Q7.** An `APP_ENV` check inside a `factory()` definition selects between `SmtpMailer` and `NullMailer`. A developer proposes moving this check inside `SmtpMailer`'s constructor: "if we're in test env, skip the SMTP connection." Which course rule does this violate?

- A) Rule 3 (Type system as security) — `APP_ENV` is not typed.
- B) Rule 5 (State vs behaviour) — `SmtpMailer` would mix state and behaviour.
- C) Rule 1 (Config at entry point) — business logic classes should receive dependencies; they should never reach for `getenv()` or config directly.
- D) Rule 4 (Composition over inheritance) — the check should be in a parent class.

---

**Q8.** You register a `factory()` with a type-hinted `LoggerInterface` parameter. The `LoggerInterface` binding in the same container is itself a `factory()`. How many times is the `LoggerInterface` factory invoked if you resolve a service that depends on it five times?

- A) 1 — the result is cached as a singleton after the first invocation (PHP-DI's default for `factory()`).
- B) 5 — the factory is called once per resolution.
- C) 0 — PHP-DI uses a shared prototype for factory dependencies.
- D) 10 — each resolution creates both the service and a new logger.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 9  | `create(Foo::class)->constructor(DI\env('KEY'))` produces a transient binding — a new instance per resolution. | |
| 10 | A `factory()` callable can itself declare type-hinted parameters, and PHP-DI will resolve and inject them from the container. | |
| 11 | It is safe to bind both `StripeGateway` and `LoggingGateway` to `PaymentGatewayInterface` if `LoggingGateway` injects `StripeGateway` (the concrete class) rather than `PaymentGatewayInterface`. | |
| 12 | The environment-based branching logic in a `factory()` definition should be extracted into the selected implementation classes so they can self-configure. | |
| 13 | Using `factory()` for a class that has only type-hinted constructor arguments (no scalars) and needs singleton scope is technically correct but wasteful — `autowire()` would achieve the same result more cleanly. | |
| 14 | Stacking two decorators (`MetricsGateway` wraps `LoggingGateway` wraps `StripeGateway`) requires three separate container bindings: one for `StripeGateway`, one for `LoggingGateway`, and one for `MetricsGateway`. | |

---

## Section C — Short Answer

**Q15.** Explain why `create(DatabaseConnection::class)->constructor(DI\env('DB_DSN'))` and `factory(fn() => new DatabaseConnection(getenv('DB_DSN')))` both produce singletons, but the `factory()` version is more powerful. Give one concrete example of what the `factory()` version can do that `create()->constructor()` cannot.

*Your answer:*

---

**Q16.** A team resolves their `RequestContext` with `$container->get()`. Describe exactly what goes wrong on the second HTTP request to a FrankenPHP worker, and explain how to fix it. (Careful: changing the definition to `factory()` is not the fix.)

*Your answer:*

---

**Q17.** Describe the composition-root principle as it applies to the environment-based binding pattern. Where does the `APP_ENV` check belong, where does it NOT belong, and why does this matter for testability?

*Your answer:*

---

## Section D — Code Reading

**Q18.** Read this PHP-DI container definitions file. Identify all mistakes and describe the correct version.

```php
return [
    // Mailer
    MailerInterface::class => factory(function(): MailerInterface {
        if (getenv('APP_ENV') === 'production') {
            return new SmtpMailer(getenv('SMTP_HOST'), (int) getenv('SMTP_PORT'));
        }
        return new LogMailer();
    }),

    // Payment gateway with decorator
    StripeGateway::class              => autowire(StripeGateway::class),
    PaymentGatewayInterface::class    => factory(function(
        PaymentGatewayInterface $inner,   // ← injecting the interface
        LoggerInterface $logger,
    ): PaymentGatewayInterface {
        return new LoggingGateway($inner, $logger);
    }),

    // Shopping cart — must be fresh per user
    ShoppingCart::class => autowire(ShoppingCart::class),

    // Password hasher — cost from env
    PasswordHasher::class => factory(function(): PasswordHasher {
        return new PasswordHasher(cost: (int) getenv('BCRYPT_COST'));
    }),
];
```

*Your answer (list each mistake, then give the corrected version):*

---

**Q19.** Read this invokable factory class. What pattern is it implementing, and when would you use an invokable class instead of a closure for a factory? What does the constructor injection on `ReportBuilderFactory` mean for how it is resolved?

```php
class ReportBuilderFactory
{
    public function __construct(
        private readonly LoggerInterface  $logger,
        private readonly ClockInterface   $clock,
        private readonly string           $defaultCurrency,
    ) {}

    public function __invoke(): ReportBuilder
    {
        return new ReportBuilder(
            logger:   $this->logger,
            clock:    $this->clock,
            currency: $this->defaultCurrency,
            title:    'Untitled Report',
        );
    }
}

// Registration:
ReportBuilderInterface::class => factory(ReportBuilderFactory::class),
```

*Your answer:*

---

**Q20.** Trace through what PHP-DI does when `$container->get(PaymentGatewayInterface::class)` is called, given this definitions file:

```php
return [
    LoggerInterface::class         => autowire(FileLogger::class),
    StripeGateway::class           => autowire(StripeGateway::class),
    PaymentGatewayInterface::class => factory(function(
        StripeGateway $stripe,
        LoggerInterface $logger,
    ): PaymentGatewayInterface {
        return new LoggingGateway($stripe, $logger);
    }),
];
```

Describe, step by step, what PHP-DI resolves and in what order. Which classes are constructed? How many times is each constructed (assuming a cold container)?

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
| 1 | **D** | A single `LoggerInterface` typed dependency is exactly what auto-wiring handles — it resolves the type from the container and injects it. No factory needed. All other options (scalar string, transient scope, decorator wrapping) require a factory definition. |
| 2 | **A** | Every definition style is resolved once and shared: PHP-DI dropped scopes in v6, and `factory()` is a build recipe, not a lifetime instruction. Compare Q8, which asks how many times a `factory()` callable fires across five resolutions — the answer there is *once*, and it has to be the same answer here. Use `$container->make()` when you need a fresh object. |
| 3 | **D** | If both `StripeGateway` and `LoggingGateway` are bound to `PaymentGatewayInterface`, and `LoggingGateway`'s factory requests `PaymentGatewayInterface` as `$inner`, the container tries to resolve `PaymentGatewayInterface` — which triggers the `LoggingGateway` factory again — creating infinite recursion. PHP-DI detects this as a circular dependency and throws. |
| 4 | **A** | Register `StripeGateway` under its own concrete class name (`StripeGateway::class`), then declare the factory for `PaymentGatewayInterface` as `function(StripeGateway $stripe, ...)`. PHP-DI resolves `StripeGateway::class` (the concrete), not `PaymentGatewayInterface` — no circularity. |
| 5 | **C** | PHP-DI inspects the factory callable's parameter types and resolves each type-hinted parameter from the container before invoking the factory. The resolved instances are passed as arguments. This is the same parameter injection mechanism used for class constructors. |
| 6 | **D** | Both A and C are correct for a singleton. A uses `factory()` with inline `getenv()` — works, gives full validation capability. C uses `create()->constructor()` with `DI\env()` helper — cleaner for simple cases. B is wrong: `autowire()` does not have a `->constructor()` method; that is specific to `create()`. |
| 7 | **C** | Rule 1: "Config belongs at the entry point, not in core logic." `SmtpMailer` is a business/infrastructure class. It should receive what it needs via its constructor — not reach out to `getenv()` or branch on `APP_ENV` internally. The environment check belongs in the container factory, not inside the implementation. |
| 8 | **A** | PHP-DI's default for `factory()` is singleton — the factory is called once and the result is cached. The `LoggerInterface` factory is invoked once; the same logger instance is injected into every service that depends on it. If you want a new logger per consumer, you would need to explicitly register `LoggerInterface` as a transient factory. |

## Section B

| # | Answer | Explanation |
|---|--------|-------------|
| 9  | **F** | `create()` gives you one shared instance — but so does `factory()`, and so does `autowire()`. There is no transient *definition* of any kind in PHP-DI; `$container->make()` is the only way to get a new instance. `DI\env()` resolves the constructor argument's value and has nothing to do with lifetime. |
| 10 | **T** | PHP-DI inspects the factory callable's type-hinted parameters using reflection and resolves each from the container before invoking the factory. This is explicitly documented and is the mechanism shown in Sections 2 and 6 of the README. |
| 11 | **T** | This is exactly the correct pattern. `LoggingGateway`'s factory declares `function(StripeGateway $stripe, ...)` — injecting the concrete class, not the interface. `StripeGateway::class` and `PaymentGatewayInterface::class` are different keys in the container. No circular reference can form. |
| 12 | **F** | This is the opposite of the correct pattern. The environment check belongs in the factory/composition root (Rule 1). Moving it into the implementations means each class must know about `APP_ENV` — they are now coupled to the deployment environment. This makes them untestable without setting environment variables, and it violates the single-responsibility principle. |
| 13 | **T** | `factory(fn() => new Foo())` for a class with only typed constructor args is redundant — `autowire(Foo::class)` gets you the same shared instance with less code. `factory()` earns its place when you need scalar arguments, runtime branching or decorator construction. Note what is missing from that list: freshness. A `factory()` cannot give you a new instance per resolution, so "I need this to be transient" is never the reason to reach for one. |
| 14 | **F** | Stacking two decorators only requires TWO bindings: one for `StripeGateway::class` (the concrete inner) and one for `PaymentGatewayInterface::class` (which builds the full stack). The factory for `PaymentGatewayInterface` constructs both `LoggingGateway` and `MetricsGateway` inline: `$metered = new MetricsGateway(new LoggingGateway($stripe, $logger)); return $metered;`. No separate binding for `LoggingGateway` is needed unless something else depends on it directly. |

## Section C

**Q15 — Model answer:**
Both `create()->constructor(DI\env('DB_DSN'))` and `factory(fn() => new DatabaseConnection(getenv('DB_DSN')))` give you one shared instance — not by default, but always: PHP-DI has no transient scope to opt into. The factory is called once and the result is cached for every later `get()`.

The `factory()` version is more powerful because it executes arbitrary PHP code at construction time. One concrete example: validation with a useful error message:

```php
factory(function(): DatabaseConnection {
    $dsn = getenv('DB_DSN') ?: throw new \RuntimeException(
        'DB_DSN is not set. Set it in your .env file before starting the application.'
    );
    $user = getenv('DB_USER') ?: throw new \RuntimeException('DB_USER is not set');
    return new DatabaseConnection($dsn, $user, getenv('DB_PASS') ?: '');
})
```

`create()->constructor(DI\env('DB_DSN'))` would throw a PHP-DI exception if `DB_DSN` is missing — but the error message would be a generic PHP-DI resolution failure, not a domain-specific diagnostic message. The `factory()` version can also branch (use a test DB if `APP_ENV=test`), retry, or call helper functions — `create()` cannot.

**Q16 — Model answer:**
With `RequestContext` as a singleton, the first HTTP request to the FrankenPHP worker constructs one `RequestContext` and stores it in the container. This context carries the first request's authenticated user (say, Alice), request ID (`req-001`), and path (`/orders`).

When the second HTTP request arrives from a different user (Bob, on `/invoices`), the container returns the cached singleton — Alice's `RequestContext`. Bob's code runs with Alice's user identity. Any call to `ctx->user->id`, `ctx->requireRole()`, `ctx->getTenantId()`, or `ctx->requestId` returns Alice's values. Bob either sees Alice's data (data breach) or is granted Alice's permissions (privilege escalation).

The fix: register `RequestContext` with transient scope:

```php
RequestContext::class => factory(function(AuthService $auth, ServerRequestInterface $request): RequestContext {
    $user = $auth->resolveFromRequest($request);
    return $user
        ? RequestContext::authenticated($user, uniqid('req-'), $request->getUri()->getPath())
        : RequestContext::anonymous(uniqid('req-'), $request->getUri()->getPath());
}),
```

Now every resolution builds a new `RequestContext` from the current request — each user gets their own context, and no state carries between requests.

**Q17 — Model answer:**
The composition-root principle (Rule 1 from COURSE_PHILOSOPHY.md) states that configuration belongs at the application's entry point, not inside business-logic classes. Applied to environment-based bindings:

WHERE it belongs — in the container factory: `factory(fn() => match(getenv('APP_ENV')) { 'production' => new SmtpMailer(...), 'test' => new NullMailer(), default => new LogMailer() })`. This is composition-root code — it runs at bootstrap, makes a wiring decision, and produces a concrete object. All environment logic is in one place.

WHERE it does NOT belong — inside `SmtpMailer`, `LogMailer`, or any other class. Those classes should be unaware of `APP_ENV`. They receive their dependencies via constructor injection and implement their single purpose.

Why this matters for testability: when a class does not read `APP_ENV`, you can test it in any environment without setting environment variables. `SmtpMailer`'s tests just construct `new SmtpMailer($host, $port)` directly. If `SmtpMailer` had an internal `getenv('APP_ENV')` check, every test would need to set `APP_ENV` to avoid accidentally skipping the SMTP connection — test setup becomes environment-dependent.

## Section D

**Q18 — Answer:**
Two mistakes:

**Mistake 1:** `PaymentGatewayInterface::class` factory injects `PaymentGatewayInterface $inner` — a circular reference. The factory for `PaymentGatewayInterface` tries to resolve `PaymentGatewayInterface` to get `$inner`, which triggers itself, causing infinite recursion.

**Fix:** inject `StripeGateway $stripe` (the concrete class) instead of `PaymentGatewayInterface $inner`.

**Mistake 2:** `ShoppingCart::class => autowire(ShoppingCart::class)` — auto-wiring produces a singleton. `ShoppingCart` has mutable state (`$items`) and must be transient to prevent cross-user contamination.

**Fix:** use `factory(fn() => new ShoppingCart())`.

There is also a minor style concern on the `MailerInterface` factory: it calls `getenv()` which could return `false` on missing vars. In production code, add a fallback or validation. Not a correctness bug if defaults are acceptable, but worth noting.

Corrected version:
```php
return [
    MailerInterface::class => factory(function(): MailerInterface {
        return match(getenv('APP_ENV') ?: 'development') {
            'production' => new SmtpMailer(
                getenv('SMTP_HOST') ?: 'localhost',
                (int)(getenv('SMTP_PORT') ?: 587)
            ),
            'test'  => new NullMailer(),
            default => new LogMailer(),
        };
    }),

    StripeGateway::class => autowire(StripeGateway::class),

    PaymentGatewayInterface::class => factory(function(
        StripeGateway $stripe,      // ← concrete class, not interface
        LoggerInterface $logger,
    ): PaymentGatewayInterface {
        return new LoggingGateway($stripe, $logger);
    }),

    ShoppingCart::class => factory(fn() => new ShoppingCart()), // still SHARED —
    //   resolve with $container->make(ShoppingCart::class) where a fresh cart matters

    PasswordHasher::class => factory(function(): PasswordHasher {
        return new PasswordHasher(cost: (int)(getenv('BCRYPT_COST') ?: 12));
    }),
];
```

**Q19 — Answer:**
The invokable class pattern implements the same thing as a factory closure, but encapsulates the construction logic in a dedicated class. PHP-DI supports this: when you pass a class name string to `factory()`, it instantiates the class (using its own auto-wiring) and calls its `__invoke()` method.

You use an invokable factory class instead of a closure when the construction logic is complex enough to deserve its own file, its own tests, and its own dependencies. In this case, `ReportBuilderFactory` takes three constructor arguments — including a `string $defaultCurrency` which must come from configuration. The factory class is itself auto-wired by PHP-DI: `LoggerInterface` and `ClockInterface` are resolved from the container; `string $defaultCurrency` would need to be provided via a `create()->constructor()` definition on `ReportBuilderFactory` itself.

The constructor injection on `ReportBuilderFactory` means: when PHP-DI instantiates `ReportBuilderFactory` (to call its `__invoke()`), it will resolve `LoggerInterface` and `ClockInterface` from the container. The `string $defaultCurrency` cannot be auto-wired — it requires an explicit `create(ReportBuilderFactory::class)->constructor(DI\env('DEFAULT_CURRENCY'))` definition, or it can be provided inline:

```php
ReportBuilderInterface::class => factory(
    create(ReportBuilderFactory::class)->constructor(
        DI\get(LoggerInterface::class),
        DI\get(ClockInterface::class),
        DI\env('DEFAULT_CURRENCY'),
    )
),
```

**Q20 — Answer:**
Step-by-step resolution of `$container->get(PaymentGatewayInterface::class)` on a cold container:

1. **Look up `PaymentGatewayInterface::class`** — finds the `factory()` definition; factory needs `StripeGateway $stripe` and `LoggerInterface $logger`.

2. **Resolve `StripeGateway::class`** — finds `autowire(StripeGateway::class)`. `StripeGateway`'s constructor has no unresolvable args (assume it has none or only typed deps). **Constructs `StripeGateway` once.** Caches as singleton.

3. **Resolve `LoggerInterface::class`** — finds `autowire(FileLogger::class)`. Constructs `FileLogger` (assume no unresolvable args). **Constructs `FileLogger` once.** Caches as singleton.

4. **Invoke the factory** with `$stripe` (the `StripeGateway` instance) and `$logger` (the `FileLogger` instance). Factory executes `new LoggingGateway($stripe, $logger)`. **Constructs `LoggingGateway` once.**

5. **Cache the result** under `PaymentGatewayInterface::class` (since `factory()` is singleton by default).

6. **Return `LoggingGateway` instance.**

Total constructions on cold container: `StripeGateway` × 1, `FileLogger` × 1, `LoggingGateway` × 1.

On subsequent `get(PaymentGatewayInterface::class)` calls: the cached `LoggingGateway` is returned immediately — no reconstruction. `StripeGateway` and `FileLogger` are also cached singletons and would not be reconstructed even if resolved independently.

---

## Score Guide

| Score | Verdict |
|-------|---------|
| 18–20 | Factory definitions mastered. Module 6 complete — review the module checklist. |
| 14–17 | Re-read README Sections 3–5 and re-examine Examples 03 and 04 before moving on. |
| Below 14 | Work through the challenge solution with the WHY comments and retake before proceeding. |