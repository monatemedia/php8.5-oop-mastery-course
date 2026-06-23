<?php
declare(strict_types=1);

/**
 * Example 01 — Singleton vs Transient: Same Class, Two Scopes
 * -------------------------------------------------------------
 * Run via PHPUnit:
 *   ./vendor/bin/phpunit module-6-object-lifecycle-and-state/lesson-6.2-transient-vs-singleton-scopes/examples/01-singleton-vs-transient.php
 *
 * This file proves the core mechanical difference between singleton and
 * transient scope using a hand-rolled container simulation — no PHP-DI
 * dependency required to understand the concept.
 *
 * Then it shows the same behaviour using real PHP-DI definitions so you
 * see exactly how the production code looks.
 *
 * Structure:
 *   PART A — The subject class (same class used in both scopes)
 *   PART B — Hand-rolled container simulation (understand the mechanism)
 *   PART C — Real PHP-DI definitions (if PHP-DI is available)
 *   PART D — Tests proving singleton identity and transient non-identity
 *   PART E — The downstream effect: singleton contamination vs transient isolation
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// PART A — The subject class
//
// RequestLog records events for "the current operation".
// Holds mutable state: $entries accumulates per call to record().
// Whether this is safe depends entirely on its scope.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Records events for the current operation (request, job, transaction).
 *
 * Has mutable state → safe only as a transient (fresh per operation).
 * As a singleton → entries from previous operations accumulate forever.
 */
class RequestLog
{
    private array $entries = [];

    public function record(string $event): void
    {
        $this->entries[] = $event;
    }

    public function getEntries(): array
    {
        return $this->entries;
    }

    public function count(): int
    {
        return count($this->entries);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PART B — Hand-rolled container simulation
//
// Two minimal containers that mirror exactly what PHP-DI does internally:
//   SingletonContainer  — stores a single instance and returns it every time
//   TransientContainer  — calls the factory callable every time
//
// Reading these demystifies PHP-DI's behaviour. The real PHP-DI container
// is more sophisticated (handles constructor args, interfaces, decorators),
// but the core scope logic is exactly this simple.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Minimal singleton container.
 *
 * Mirrors PHP-DI's default auto-wiring behaviour:
 *   - First call: create the object, store it
 *   - All subsequent calls: return the stored object
 */
class SingletonContainer
{
    private array $instances = [];

    /**
     * @param string   $id      Binding identifier (typically a class or interface name)
     * @param callable $factory Called once to create the instance
     */
    public function bind(string $id, callable $factory): void
    {
        // Store the factory — instance created lazily on first get()
        $this->instances[$id] = ['factory' => $factory, 'instance' => null];
    }

    public function get(string $id): object
    {
        if (!isset($this->instances[$id])) {
            throw new \RuntimeException("No binding for {$id}");
        }

        // Lazy initialisation: create once, then reuse
        if ($this->instances[$id]['instance'] === null) {
            $this->instances[$id]['instance'] = ($this->instances[$id]['factory'])();
        }

        return $this->instances[$id]['instance']; // ← SAME object every call
    }
}

/**
 * Minimal transient container.
 *
 * Mirrors PHP-DI's factory() behaviour:
 *   - Every call: invoke the factory and return a NEW object
 *   - Nothing is stored between calls
 */
class TransientContainer
{
    private array $factories = [];

    public function bind(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
    }

    public function get(string $id): object
    {
        if (!isset($this->factories[$id])) {
            throw new \RuntimeException("No binding for {$id}");
        }

        return ($this->factories[$id])(); // ← NEW object every call
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PART C — PHP-DI definitions (shown as code, tested via simulation above)
//
// These are the exact PHP-DI definitions you would write in a real project.
// The tests use the hand-rolled containers above to avoid requiring php-di
// as a Composer dependency for this example file.
//
// In a real project's container.php:
//
// use function DI\autowire;
// use function DI\factory;
//
// return [
//     // SINGLETON — stateless services that are safe to share
//     LoggerInterface::class    => autowire(FileLogger::class),
//     TaxCalculator::class      => autowire(TaxCalculator::class),
//
//     // TRANSIENT — stateful services that must be fresh per resolution
//     RequestLog::class         => factory(fn() => new RequestLog()),
//     ShoppingCart::class       => factory(fn() => new ShoppingCart()),
// ];
// ─────────────────────────────────────────────────────────────────────────────

// ─────────────────────────────────────────────────────────────────────────────
// PART D — Tests proving scope identity
// ─────────────────────────────────────────────────────────────────────────────

class SingletonVsTransientTest extends TestCase
{
    // ══════════════════════════════════════════════════════════════════════════
    // SINGLETON IDENTITY TESTS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * A singleton container returns the SAME instance every time get() is called.
     *
     * This is the defining property of singleton scope:
     *   $a === $b  (strict object identity, not just equality)
     */
    public function testSingletonContainerReturnsSameInstance(): void
    {
        $container = new SingletonContainer();
        $container->bind(RequestLog::class, fn() => new RequestLog());

        $a = $container->get(RequestLog::class);
        $b = $container->get(RequestLog::class);
        $c = $container->get(RequestLog::class);

        // All three variables point to the same object
        $this->assertSame($a, $b, 'Second resolution: same instance');
        $this->assertSame($b, $c, 'Third resolution: same instance');
        $this->assertSame($a, $c, 'First and third: same instance');
    }

    /**
     * The singleton factory is called exactly ONCE — even across many get() calls.
     * This proves the lazy initialisation: construction happens once, not per call.
     */
    public function testSingletonFactoryIsCalledOnlyOnce(): void
    {
        $callCount = 0;
        $container = new SingletonContainer();
        $container->bind(RequestLog::class, function () use (&$callCount): RequestLog {
            $callCount++;
            return new RequestLog();
        });

        $container->get(RequestLog::class);
        $container->get(RequestLog::class);
        $container->get(RequestLog::class);

        $this->assertSame(1, $callCount,
            'Factory called exactly once regardless of how many times get() is called'
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // TRANSIENT IDENTITY TESTS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * A transient container returns a DIFFERENT instance every time get() is called.
     *
     * This is the defining property of transient scope:
     *   $a !== $b  (different object identities)
     */
    public function testTransientContainerReturnsDifferentInstanceEachTime(): void
    {
        $container = new TransientContainer();
        $container->bind(RequestLog::class, fn() => new RequestLog());

        $a = $container->get(RequestLog::class);
        $b = $container->get(RequestLog::class);
        $c = $container->get(RequestLog::class);

        // All three are separate objects
        $this->assertNotSame($a, $b, 'Second resolution: different instance');
        $this->assertNotSame($b, $c, 'Third resolution: different instance');
        $this->assertNotSame($a, $c, 'First and third: different instances');
    }

    /**
     * The transient factory is called EVERY TIME get() is called.
     * Three get() calls → three factory invocations → three fresh objects.
     */
    public function testTransientFactoryIsCalledEveryTime(): void
    {
        $callCount = 0;
        $container = new TransientContainer();
        $container->bind(RequestLog::class, function () use (&$callCount): RequestLog {
            $callCount++;
            return new RequestLog();
        });

        $container->get(RequestLog::class);
        $container->get(RequestLog::class);
        $container->get(RequestLog::class);

        $this->assertSame(3, $callCount,
            'Factory called once per get() — three calls, three invocations'
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PART E — The downstream effect
    //
    // The mechanical difference (same/different instance) translates into
    // a real behavioural difference when the object holds mutable state.
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * SINGLETON + MUTABLE STATE = contamination across resolutions.
     *
     * Two collaborators both receive the same RequestLog singleton.
     * Collaborator A records some events. Collaborator B then reads the log —
     * and sees A's events mixed in with its own, even though they are meant
     * to be operating independently.
     *
     * This is the intra-request contamination problem from Lesson 6.1.
     */
    public function testSingletonMutableStateContaminatesAcrossResolutions(): void
    {
        $container = new SingletonContainer();
        $container->bind(RequestLog::class, fn() => new RequestLog());

        // Collaborator A gets the singleton and records its events
        $logForA = $container->get(RequestLog::class);
        $logForA->record('A: started processing');
        $logForA->record('A: fetched data');

        // Collaborator B gets the "same" log — intending to start fresh
        $logForB = $container->get(RequestLog::class);
        $logForB->record('B: started processing');

        // BUG: B's log already has 2 entries from A before B recorded anything
        // $logForB and $logForA are the same object — B cannot start fresh
        $this->assertSame(3, $logForB->count(),
            'BUG: B sees 3 entries (2 from A + 1 from B) — singleton contamination'
        );

        $entries = $logForB->getEntries();
        $this->assertSame('A: started processing', $entries[0],
            'BUG: A\'s entry is present in B\'s log view'
        );
    }

    /**
     * TRANSIENT + MUTABLE STATE = isolation between resolutions.
     *
     * The same two collaborators, but now the container is transient.
     * Each gets their own RequestLog instance. A's events do not appear in B's log.
     * This is exactly the fix for the contamination problem above.
     */
    public function testTransientMutableStateIsIsolatedBetweenResolutions(): void
    {
        $container = new TransientContainer();
        $container->bind(RequestLog::class, fn() => new RequestLog());

        // Collaborator A gets a fresh RequestLog and records its events
        $logForA = $container->get(RequestLog::class);
        $logForA->record('A: started processing');
        $logForA->record('A: fetched data');

        // Collaborator B gets a DIFFERENT RequestLog — completely fresh
        $logForB = $container->get(RequestLog::class);
        $logForB->record('B: started processing');

        // B's log has only 1 entry — its own
        $this->assertSame(1, $logForB->count(),
            'Transient: B\'s log has only 1 entry (its own)'
        );

        // A's log is unaffected by B's additions
        $this->assertSame(2, $logForA->count(),
            'Transient: A\'s log still has 2 entries, unaffected by B'
        );

        // The two logs are completely independent
        $this->assertNotContains('A: started processing', $logForB->getEntries(),
            'B\'s log contains no entries from A'
        );
        $this->assertNotContains('B: started processing', $logForA->getEntries(),
            'A\'s log contains no entries from B'
        );
    }

    /**
     * IMPORTANT NUANCE: the scope decision depends on the USE CASE, not just the class.
     *
     * The same RequestLog class could legitimately be a singleton if you wanted
     * a global application log that accumulates all events across all operations.
     * It should be transient if you want per-operation isolation.
     *
     * The class code is identical. The scope is the design decision.
     */
    public function testSingletonIsCorrectForGlobalAccumulationUseCase(): void
    {
        $container = new SingletonContainer();
        $container->bind(RequestLog::class, fn() => new RequestLog());

        // If the INTENT is a global application log...
        $globalLog = $container->get(RequestLog::class);
        $globalLog->record('Request 1: started');

        $sameGlobalLog = $container->get(RequestLog::class);
        $sameGlobalLog->record('Request 2: started');

        // ...then the singleton is CORRECT — we want all events in one place
        $this->assertSame(2, $globalLog->count(),
            'For a GLOBAL log, singleton is correct — accumulation is the intent'
        );

        // The key question to ask: "Should this object be shared, or should
        // each consumer get its own copy?" The answer determines the scope.
    }
}