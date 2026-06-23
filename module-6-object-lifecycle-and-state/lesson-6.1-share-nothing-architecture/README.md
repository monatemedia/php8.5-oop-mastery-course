# Lesson 6.1 — PHP's Share-Nothing Architecture
> **Module 6: Object Lifecycle & State Management** · PHP 8.5 OOP Mastery Course

---

## 📁 Lesson Folder Structure

```
lesson-6.1-share-nothing-architecture/
├── README.md                                  ← Theory (you are here)
│
├── examples/
│   ├── 01-share-nothing-demo.php              ← Simulates per-request fresh state vs persistent state
│   ├── 02-long-running-worker.php             ← Shows state accumulation in a simulated worker loop
│   └── 03-spotting-your-runtime.php           ← How to detect the execution context at runtime
│
├── challenge/
│   ├── CHALLENGE.md
│   ├── starter/
│   │   └── ShareNothingAuditTest.php          ← Scaffold — complete the audit tests
│   └── solution/
│       └── ShareNothingAuditTest.php          ← Full solution with commentary
│
└── quiz/
    └── QUIZ.md
```

**How to use this lesson:**
1. Read this README fully — Sections 3 and 4 (the three runtime models and how singletons break share-nothing) are the most important.
2. Run each example script with `php` or `./vendor/bin/phpunit` and read every comment carefully.
3. Work through the challenge: audit five service classes for lifecycle safety, write tests that catch the bugs, propose fixes.
4. Take the quiz cold.

---

## 1 — What "Share-Nothing" Means

In a standard PHP-FPM setup, every HTTP request is handled by a worker process that starts fresh. When the request ends, the process's entire memory is freed — every object, every variable, every accumulated state — gone. The next request gets a blank slate.

This model is called **share-nothing** because no state is shared between requests at the PHP level. It is one of PHP's most important accidental safety features: even badly written code that mutates global state is harmless across requests, because the state never survives to the next one.

```
Request 1 ──► PHP Worker ──► [fresh memory] ──► response ──► memory freed
Request 2 ──► PHP Worker ──► [fresh memory] ──► response ──► memory freed
Request 3 ──► PHP Worker ──► [fresh memory] ──► response ──► memory freed
```

Each request is hermetically sealed. Objects created in request 1 cannot contaminate request 2.

---

## 2 — The Three Lifecycles You Must Understand

Every PHP object has a **scope**: how long it lives relative to the process running it.

### Request lifecycle
The most common case. An object is created when the request begins and destroyed when the response is sent. Under PHP-FPM, this is also the worker lifecycle — the same process handles the whole request and then either reuses itself or dies.

```
[Request begins] → object created → object used → [Request ends] → object destroyed
```

### Worker lifecycle
In a long-running runtime (Swoole, FrankenPHP, RoadRunner, or a queue worker), the PHP process stays alive across many requests or jobs. An object created at worker startup can outlive thousands of requests.

```
[Worker starts] → object created
                        ↓
                 Request 1 arrives → object used → response sent  (object still alive)
                        ↓
                 Request 2 arrives → object used → response sent  (object still alive)
                        ↓
                 Request N arrives → ...
                        ↓
                 [Worker dies] → object finally destroyed
```

If that object accumulated state during request 1, request 2 sees that state. This is the source of nearly every share-nothing bug.

### CLI / script lifecycle
A long-running CLI script (queue worker, batch processor, importer) has the same problem. If you loop over 10,000 records using a service that accumulates state per call, by record 10,000 you have a very different object than you started with.

---

## 3 — The Three Runtime Models Where Share-Nothing Breaks

### Model 1 — Swoole / FrankenPHP / RoadRunner

These runtimes keep a PHP worker alive across many HTTP requests, sacrificing the share-nothing guarantee for performance (no PHP bootstrap per request). State that survives between requests in the same worker process can cause data to bleed between users.

**The tell:** your bootstrap runs once, then each request is handled via a callback or coroutine loop. Objects created before the loop (or as singletons in a container) outlive every request handled by that worker.

```php
// FrankenPHP worker mode — the handler is called once per request,
// but objects created outside it persist across all of them
$handler = static function () use ($container): void {
    $request = Request::fromGlobals();
    // $container was created ONCE — any stateful singletons inside it persist
    $response = $container->get(App::class)->handle($request);
    $response->send();
};

// This loop runs until the worker is killed
while (frankenphp_handle_request($handler)) {
    gc_collect_cycles();
}
```

### Model 2 — Queue workers

`php artisan queue:work`, `php bin/console messenger:consume`, or any bespoke worker loop. The same PHP process pops jobs off a queue indefinitely. A service that stores job-specific state between calls will leak that state from job to job.

```php
// Simplified queue worker loop
while (true) {
    $job = $queue->pop();
    if ($job === null) { sleep(1); continue; }

    // If $processor is a singleton that accumulates state,
    // job 2 starts with the state left by job 1
    $processor->handle($job);
}
```

### Model 3 — Long-running CLI scripts

Batch processors, importers, and exporters are usually written as a simple loop. Even under PHP-FPM's share-nothing model, a single CLI invocation can process thousands of records with the same object instances throughout.

```php
// Batch importer — runs once but processes 50,000 rows
foreach ($csvReader->rows() as $row) {
    // If $importer accumulates state per row (errors, stats, intermediate results),
    // it grows without bound and row 50,000 behaves very differently from row 1
    $importer->import($row);
}
```

---

## 4 — How PHP-DI Singletons Break Share-Nothing Even Under FPM

Here is the subtlety that surprises experienced PHP developers: **you do not need Swoole for share-nothing to break**. PHP-DI's default scope is singleton — one instance per container lifetime. If your container lives for the whole request, that is fine. But if your container is created once and reused across requests (a common optimisation), singletons persist.

More importantly, even within a single request, a singleton service that is used by multiple collaborators will share state across all of them:

```php
// Under normal FPM, this is safe — the container is per-request.
// But the bug is still visible within one request if two services
// both receive the same singleton instance and one of them mutates it.

$reportService = $container->get(ReportService::class); // singleton
$dashboardService = $container->get(DashboardService::class);
// DashboardService also holds a reference to the same ReportService instance

$reportService->addResult(['user' => 'Alice', 'score' => 99]);
// DashboardService::render() calls $this->reportService->getResults()
// and unexpectedly sees Alice's result — it was never meant to be there
```

The share-nothing guarantee protects you **between requests**. Within a request, singletons can still cause subtle cross-contamination between collaborators.

---

## 5 — How to Tell Which Runtime You Are In

PHP does not have a built-in "am I in a long-running process?" flag, but several reliable signals exist:

| Signal | How to check | What it means |
|--------|-------------|---------------|
| `SWOOLE_VERSION` constant | `defined('SWOOLE_VERSION')` | Swoole is loaded |
| `FRANKENPHP_VERSION` constant | `defined('FRANKENPHP_VERSION')` | FrankenPHP is loaded |
| `$_SERVER['APP_RUNTIME']` | Check for `'swoole'`, `'roadrunner'` etc. | Framework-set flag |
| Process uptime | `/proc/self/stat` on Linux | Long uptime = persistent worker |
| `php_sapi_name()` | `'cli'` for CLI, `'fpm-fcgi'` for FPM | Basic mode detection |
| Environment variable | `APP_WORKER_MODE=persistent` | Explicit deployment config |

In practice, the most reliable approach is an **explicit deployment contract**: your team decides "we run in persistent worker mode" and sets an environment variable that all lifecycle-sensitive code can check.

---

## 6 — What This Means for Your Code Right Now

Even if you are not using Swoole today, designing for lifecycle safety costs almost nothing and protects you from:

1. **Surprise migrations** — you add FrankenPHP to your stack in six months and your stateful services immediately break in production
2. **Intra-request contamination** — stateful singletons cause subtle bugs within a single request when shared between services
3. **Test isolation failures** — tests that share a container instance can pollute each other through stateful singletons

The rules that make your code lifecycle-safe are the same rules that make it testable (Module 5) and well-designed (Modules 1–4): stateless services, injected dependencies, and no hidden mutable state.

---

## 7 — Quick Reference

```
The three lifecycles:
  Request lifecycle   → object lives for one HTTP request (PHP-FPM default)
  Worker lifecycle    → object lives until the worker process dies (Swoole, FrankenPHP, queue workers)
  Script lifecycle    → object lives until the CLI script exits (batch jobs, importers)

Share-nothing breaks when:
  - Long-running runtimes (Swoole, FrankenPHP, RoadRunner) keep workers alive across requests
  - Queue workers process many jobs in one PHP process
  - A PHP-DI container is reused across requests (uncommon under FPM, common in optimised setups)
  - Stateful singletons are shared between collaborators within a single request

How to detect the runtime:
  - defined('SWOOLE_VERSION') / defined('FRANKENPHP_VERSION')
  - php_sapi_name() === 'cli'
  - Explicit APP_WORKER_MODE environment variable

The safe default:
  - Assume your code will eventually run in a persistent worker
  - Design services to be stateless (Module 6.4)
  - Use transient scope for anything that must be fresh per request (Module 6.2)
```

---

## ✅ Lesson Checklist

- [ ] Read this README fully — Sections 3, 4, and 6 are the most important
- [ ] Run `examples/01-share-nothing-demo.php` and read every comment
- [ ] Run `examples/02-long-running-worker.php` and observe state accumulation
- [ ] Run `examples/03-spotting-your-runtime.php` and understand the detection signals
- [ ] Read `challenge/CHALLENGE.md` before opening the starter file
- [ ] Complete `challenge/starter/ShareNothingAuditTest.php`
- [ ] Only open `challenge/solution/ShareNothingAuditTest.php` after all tests pass
- [ ] Complete `quiz/QUIZ.md` cold

---

*Next lesson: **6.2 — Transient vs Singleton Scopes in PHP-DI** — learn to declare the right lifetime for every object in your container.*