<?php
declare(strict_types=1);

/**
 * Example 01 — Share-Nothing Demo
 * ---------------------------------
 * Run via PHPUnit:
 *   ./vendor/bin/phpunit module-6-object-lifecycle-and-state/lesson-6.1-share-nothing-architecture/examples/01-share-nothing-demo.php
 *
 * This file simulates two contrasting memory models side by side:
 *
 *   MODEL A — Share-nothing (PHP-FPM default)
 *     Each "request" gets a completely fresh object. State from request N
 *     cannot bleed into request N+1 because the object is recreated.
 *
 *   MODEL B — Persistent worker (Swoole / FrankenPHP / queue worker)
 *     The same object instance handles every "request". State accumulated
 *     during request N is still present when request N+1 begins.
 *
 * The goal: see the same service class behave safely in model A and
 * dangerously in model B — without changing the class itself.
 * The difference is entirely in the WIRING (how long the object lives),
 * not in the service code.
 *
 * Structure:
 *   PART A — The service class (same code, two different lifetimes)
 *   PART B — The simulator helper
 *   PART C — Tests demonstrating both models
 *   PART D — The takeaway
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// PART A — The service class
//
// VisitCounter tracks page visits. Notice the private $count property.
// This is the state that will cause trouble under model B.
// The class itself is not "wrong" — it just holds state.
// Whether that is safe depends entirely on how long the instance lives.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Tracks the number of visits for the current session/request.
 *
 * Under share-nothing (FPM): safe — $count resets to 0 each request.
 * Under persistent worker: dangerous — $count never resets between requests.
 */
class VisitCounter
{
    private int $count = 0;

    /**
     * Records one visit and returns the new total.
     */
    public function record(): int
    {
        $this->count++;
        return $this->count;
    }

    /**
     * Returns the current visit count without incrementing.
     */
    public function get(): int
    {
        return $this->count;
    }

    /**
     * Returns true if this is the first visit (count == 1).
     * A common use-case: show a "welcome" message on first visit.
     */
    public function isFirstVisit(): bool
    {
        return $this->count === 1;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PART B — Simulator helper
//
// We cannot literally start and stop PHP-FPM worker processes in a unit test,
// but we can simulate the two models by controlling how the VisitCounter
// instance is scoped relative to the "requests".
//
// ShareNothingSimulator — creates a fresh VisitCounter for each request.
// PersistentWorkerSimulator — creates one VisitCounter and reuses it.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Simulates PHP-FPM's share-nothing model.
 *
 * Every call to handleRequest() creates a FRESH VisitCounter.
 * This mirrors what FPM does: each worker process starts with a blank slate.
 */
class ShareNothingSimulator
{
    /**
     * @return array{counter: VisitCounter, result: int}
     */
    public function handleRequest(): array
    {
        // KEY LINE: new VisitCounter() — fresh object every time.
        // Under PHP-FPM, this happens automatically because the script
        // re-runs from the top for every request.
        $counter = new VisitCounter();
        $result  = $counter->record();

        return ['counter' => $counter, 'result' => $result];
    }
}

/**
 * Simulates a persistent worker (Swoole / FrankenPHP / queue worker).
 *
 * The VisitCounter is created ONCE in the constructor and reused across
 * all requests. This mirrors what happens when a PHP process stays alive
 * and a DI container returns the same singleton instance on every request.
 */
class PersistentWorkerSimulator
{
    // KEY LINE: the counter is a property — created once at construction time.
    // In a real persistent worker, this would be a singleton registered in
    // the DI container at bootstrap time.
    private VisitCounter $counter;

    public function __construct()
    {
        $this->counter = new VisitCounter();
    }

    /**
     * @return array{counter: VisitCounter, result: int}
     */
    public function handleRequest(): array
    {
        // SAME counter object every time — state accumulated in previous
        // requests is still present here.
        $result = $this->counter->record();

        return ['counter' => $this->counter, 'result' => $result];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PART C — Tests demonstrating both models
// ─────────────────────────────────────────────────────────────────────────────

class ShareNothingDemoTest extends TestCase
{
    // ════════════════════════════════════════════════════════════
    // MODEL A — Share-nothing
    // ════════════════════════════════════════════════════════════

    /**
     * Under share-nothing, every request starts with count == 0.
     * Each request is hermetically sealed.
     */
    public function testShareNothingGivesFreshCounterEveryRequest(): void
    {
        $simulator = new ShareNothingSimulator();

        // Simulate 3 separate requests
        $req1 = $simulator->handleRequest();
        $req2 = $simulator->handleRequest();
        $req3 = $simulator->handleRequest();

        // Every request sees count == 1 — they each started from zero
        $this->assertSame(1, $req1['result'], 'Request 1 should see count 1');
        $this->assertSame(1, $req2['result'], 'Request 2 should see count 1 (fresh object)');
        $this->assertSame(1, $req3['result'], 'Request 3 should see count 1 (fresh object)');
    }

    /**
     * Under share-nothing, isFirstVisit() is ALWAYS true for the first
     * page load — because every request is a "first" visit to that object.
     *
     * This is the expected, safe behaviour.
     */
    public function testShareNothingIsFirstVisitAlwaysTrueOnFirstCallPerRequest(): void
    {
        $simulator = new ShareNothingSimulator();

        $req1 = $simulator->handleRequest();
        $req2 = $simulator->handleRequest();

        $this->assertTrue($req1['counter']->isFirstVisit());
        $this->assertTrue($req2['counter']->isFirstVisit());
    }

    /**
     * Under share-nothing, the VisitCounter instances are DIFFERENT objects.
     * This confirms fresh construction per request.
     */
    public function testShareNothingProducesDifferentObjectInstances(): void
    {
        $simulator = new ShareNothingSimulator();

        $req1 = $simulator->handleRequest();
        $req2 = $simulator->handleRequest();

        // Different object instances — not the same reference
        $this->assertNotSame($req1['counter'], $req2['counter']);
    }

    // ════════════════════════════════════════════════════════════
    // MODEL B — Persistent worker
    // ════════════════════════════════════════════════════════════

    /**
     * Under a persistent worker, state ACCUMULATES across requests.
     * Request 1 leaves count at 1. Request 2 finds count at 1 and
     * increments to 2. Request 3 finds count at 2 and increments to 3.
     *
     * This is the dangerous behaviour.
     */
    public function testPersistentWorkerAccumulatesCountAcrossRequests(): void
    {
        $simulator = new PersistentWorkerSimulator();

        $req1 = $simulator->handleRequest();
        $req2 = $simulator->handleRequest();
        $req3 = $simulator->handleRequest();

        // Count grows — state from previous requests is still present
        $this->assertSame(1, $req1['result'], 'Request 1: count is 1');
        $this->assertSame(2, $req2['result'], 'Request 2: count is 2 (request 1 state leaked in)');
        $this->assertSame(3, $req3['result'], 'Request 3: count is 3 (requests 1+2 state leaked in)');
    }

    /**
     * Under a persistent worker, isFirstVisit() returns TRUE for request 1
     * but FALSE for all subsequent requests — because the object remembers
     * that it already recorded a visit.
     *
     * If this VisitCounter were used to show a "welcome" message to first-time
     * visitors, only the very first request to the worker would see it.
     * Every subsequent visitor would be treated as a returning user — a silent
     * personalisation bug that is almost impossible to reproduce in development.
     */
    public function testPersistentWorkerIsFirstVisitOnlyTrueOnceEver(): void
    {
        $simulator = new PersistentWorkerSimulator();

        $req1 = $simulator->handleRequest(); // count → 1
        $req2 = $simulator->handleRequest(); // count → 2
        $req3 = $simulator->handleRequest(); // count → 3

        $this->assertTrue($req1['counter']->isFirstVisit(),  'Request 1: correctly identified as first visit');
        $this->assertFalse($req2['counter']->isFirstVisit(), 'Request 2: WRONG — told it is not a first visit due to leaked state');
        $this->assertFalse($req3['counter']->isFirstVisit(), 'Request 3: WRONG — same bug');
    }

    /**
     * Under a persistent worker, all requests use the SAME object instance.
     * This confirms the source of the contamination.
     */
    public function testPersistentWorkerReusesTheSameObjectInstance(): void
    {
        $simulator = new PersistentWorkerSimulator();

        $req1 = $simulator->handleRequest();
        $req2 = $simulator->handleRequest();

        // The SAME object — mutations from request 1 are visible in request 2
        $this->assertSame($req1['counter'], $req2['counter']);
    }

    // ════════════════════════════════════════════════════════════
    // COMPARING THE TWO MODELS SIDE BY SIDE
    // ════════════════════════════════════════════════════════════

    /**
     * This test runs the same three "requests" through both models
     * and shows the divergence directly. The class is identical — only
     * the object lifetime differs.
     */
    public function testSameClassBehavesDifferentlyUnderEachModel(): void
    {
        $shareNothing = new ShareNothingSimulator();
        $persistent   = new PersistentWorkerSimulator();

        $snResults  = [];
        $pwResults  = [];

        for ($i = 0; $i < 5; $i++) {
            $snResults[] = $shareNothing->handleRequest()['result'];
            $pwResults[]  = $persistent->handleRequest()['result'];
        }

        // Share-nothing: always 1 — each request is isolated
        $this->assertSame([1, 1, 1, 1, 1], $snResults, 'Share-nothing: always 1');

        // Persistent worker: 1, 2, 3, 4, 5 — state accumulates
        $this->assertSame([1, 2, 3, 4, 5], $pwResults,  'Persistent worker: accumulates');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PART D — Takeaway
//
// The VisitCounter class is not "wrong". It is a simple, reasonable class.
// The danger comes entirely from HOW IT IS WIRED — specifically, how long
// the instance lives.
//
// Under PHP-FPM share-nothing: safe. Object is re-created per request.
// Under persistent worker: dangerous. Object accumulates state across requests.
//
// This is why Module 6 exists. By the end of it, you will know:
//   1. How to identify which of your services hold state (Lesson 6.3)
//   2. How to redesign them to be stateless (Lesson 6.4)
//   3. How to use PHP-DI's scoping to create fresh instances where needed (Lesson 6.2)
//   4. How to use factory definitions for the cases that cannot be stateless (Lesson 6.5)
//
// The fix for VisitCounter in a persistent worker context would be one of:
//   a) Make it stateless — take a $count parameter, return a new count:
//      public function record(int $currentCount): int { return $currentCount + 1; }
//
//   b) Use transient scope — tell the DI container to create a fresh
//      VisitCounter for each request (covered in Lesson 6.2)
//
//   c) Push state to an external store — Redis, session, DB — so the
//      in-memory object is always fresh (covered in Lesson 6.4)
// ─────────────────────────────────────────────────────────────────────────────