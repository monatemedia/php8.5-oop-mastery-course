<?php
declare(strict_types=1);

/**
 * Example 03 — Decorator in Container
 * ---------------------------------------
 * Run via PHPUnit:
 *   ./vendor/bin/phpunit module-6-object-lifecycle-and-state/lesson-6.5-factory-definitions/examples/03-decorator-in-container.php
 *
 * The decorator pattern adds cross-cutting behaviour (logging, metrics,
 * retry logic, caching) to a real implementation without modifying it.
 * PHP-DI's factory() makes wiring the decorator clean and explicit.
 *
 * This file builds the pattern in four stages:
 *
 *   STAGE 1 — Single decorator: LoggingGateway wraps StripeGateway
 *   STAGE 2 — Stacked decorators: MetricsGateway wraps LoggingGateway wraps StripeGateway
 *   STAGE 3 — The circularity trap and why factory(ConcreteClass) avoids it
 *   STAGE 4 — Tests proving decorator behaviour and correct delegation
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// STAGE 1 — Domain classes
// ─────────────────────────────────────────────────────────────────────────────

interface PaymentGatewayInterface
{
    /**
     * @return array{success: bool, transactionId: ?string, error: ?string}
     */
    public function charge(string $userId, int $amountCents, string $currency): array;

    public function refund(string $transactionId): bool;
}

/**
 * The real Stripe implementation.
 * In production this calls the Stripe API. In tests it is replaced by a fake.
 */
class StripeGateway implements PaymentGatewayInterface
{
    public function charge(string $userId, int $amountCents, string $currency): array
    {
        // Simulated: always succeeds in tests
        return [
            'success'       => true,
            'transactionId' => 'stripe_' . substr(md5($userId . $amountCents), 0, 8),
            'error'         => null,
        ];
    }

    public function refund(string $transactionId): bool
    {
        return str_starts_with($transactionId, 'stripe_');
    }
}

/**
 * Logger interface — captures what gets logged for test observation.
 */
interface LoggerInterface
{
    public function info(string $message, array $context = []): void;
    public function error(string $message, array $context = []): void;
}

/**
 * In-memory spy logger for tests.
 */
class SpyLogger implements LoggerInterface
{
    public array $entries = [];

    public function info(string $message, array $context = []): void
    {
        $this->entries[] = ['level' => 'info', 'message' => $message, 'context' => $context];
    }

    public function error(string $message, array $context = []): void
    {
        $this->entries[] = ['level' => 'error', 'message' => $message, 'context' => $context];
    }

    public function getMessages(): array
    {
        return array_column($this->entries, 'message');
    }
}

/**
 * Decorator 1: LoggingGateway.
 * Wraps any PaymentGatewayInterface and logs every operation.
 *
 * NOTE: LoggingGateway depends on PaymentGatewayInterface (the inner implementation).
 * If both LoggingGateway AND StripeGateway were bound to PaymentGatewayInterface,
 * the container would try to inject LoggingGateway into itself — a circular reference.
 * The factory() pattern solves this by injecting StripeGateway (the concrete class)
 * rather than PaymentGatewayInterface.
 */
class LoggingGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly PaymentGatewayInterface $inner,
        private readonly LoggerInterface         $logger,
    ) {}

    public function charge(string $userId, int $amountCents, string $currency): array
    {
        $this->logger->info('Payment charge initiated', [
            'userId'       => $userId,
            'amountCents'  => $amountCents,
            'currency'     => $currency,
        ]);

        $result = $this->inner->charge($userId, $amountCents, $currency);

        if ($result['success']) {
            $this->logger->info('Payment charge succeeded', [
                'transactionId' => $result['transactionId'],
            ]);
        } else {
            $this->logger->error('Payment charge failed', [
                'error' => $result['error'],
            ]);
        }

        return $result;
    }

    public function refund(string $transactionId): bool
    {
        $this->logger->info('Refund initiated', ['transactionId' => $transactionId]);
        $result = $this->inner->refund($transactionId);
        $this->logger->info('Refund ' . ($result ? 'succeeded' : 'failed'), ['transactionId' => $transactionId]);
        return $result;
    }
}

/**
 * Decorator 2: MetricsGateway.
 * Wraps any PaymentGatewayInterface and records timing metrics.
 */
class MetricsGateway implements PaymentGatewayInterface
{
    public array $chargeTimings = [];
    public int   $chargeCount  = 0;
    public int   $refundCount  = 0;

    public function __construct(
        private readonly PaymentGatewayInterface $inner,
    ) {}

    public function charge(string $userId, int $amountCents, string $currency): array
    {
        $start  = microtime(true);
        $result = $this->inner->charge($userId, $amountCents, $currency);
        $this->chargeTimings[] = microtime(true) - $start;
        $this->chargeCount++;
        return $result;
    }

    public function refund(string $transactionId): bool
    {
        $result = $this->inner->refund($transactionId);
        $this->refundCount++;
        return $result;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Container for this example (same as Example 02)
// ─────────────────────────────────────────────────────────────────────────────

class DecoratorContainer
{
    private array $definitions = [];
    private array $singletons  = [];

    public function singleton(string $id, callable $factory): void
    {
        $this->definitions[$id] = ['factory' => $factory, 'transient' => false];
    }

    public function transient(string $id, callable $factory): void
    {
        $this->definitions[$id] = ['factory' => $factory, 'transient' => true];
    }

    public function get(string $id): object
    {
        if (!isset($this->definitions[$id])) {
            throw new \RuntimeException("No definition for: {$id}");
        }

        $def = $this->definitions[$id];

        if (!$def['transient'] && isset($this->singletons[$id])) {
            return $this->singletons[$id];
        }

        $instance = $this->invoke($def['factory']);

        if (!$def['transient']) {
            $this->singletons[$id] = $instance;
        }

        return $instance;
    }

    private function invoke(callable $factory): object
    {
        $rf   = new \ReflectionFunction(
            $factory instanceof \Closure ? $factory : \Closure::fromCallable($factory)
        );
        $args = [];
        foreach ($rf->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $args[] = $this->get($type->getName());
            }
        }
        return $factory(...$args);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// STAGE 3 — The circularity trap (documentation — not executed)
//
// ❌ WRONG: this would cause a circular reference
//
//   $container->singleton(PaymentGatewayInterface::class, fn() => new StripeGateway());
//   // Now override with the decorator:
//   $container->singleton(PaymentGatewayInterface::class, function(PaymentGatewayInterface $inner) {
//       return new LoggingGateway($inner, ...); // ← CIRCULAR: resolves itself
//   });
//
// ✅ CORRECT: bind StripeGateway as its own concrete class, then inject it
//
//   $container->singleton(StripeGateway::class, fn() => new StripeGateway());
//   $container->singleton(PaymentGatewayInterface::class, function(
//       StripeGateway $stripe,         ← concrete class, not the interface
//       LoggerInterface $logger,
//   ): PaymentGatewayInterface {
//       return new LoggingGateway($stripe, $logger);
//   });
// ─────────────────────────────────────────────────────────────────────────────

// ─────────────────────────────────────────────────────────────────────────────
// STAGE 4 — Tests
// ─────────────────────────────────────────────────────────────────────────────

class DecoratorContainerTest extends TestCase
{
    /**
     * STAGE 1: Single decorator — LoggingGateway wraps StripeGateway.
     *
     * Container wiring:
     *   StripeGateway::class           → singleton (concrete implementation)
     *   LoggerInterface::class         → singleton (spy logger for observation)
     *   PaymentGatewayInterface::class → factory that wraps StripeGateway in LoggingGateway
     *
     * Code that depends on PaymentGatewayInterface receives LoggingGateway,
     * which transparently delegates to StripeGateway.
     */
    public function testSingleDecoratorWrapsInnerImplementation(): void
    {
        $spyLogger = new SpyLogger();

        $container = new DecoratorContainer();
        $container->singleton(StripeGateway::class,   fn() => new StripeGateway());
        $container->singleton(LoggerInterface::class,  fn() => $spyLogger);

        // The key factory definition: inject StripeGateway (concrete), not the interface
        $container->singleton(
            PaymentGatewayInterface::class,
            function (StripeGateway $stripe, LoggerInterface $logger): PaymentGatewayInterface {
                return new LoggingGateway($stripe, $logger);
            }
        );

        // Any code that depends on PaymentGatewayInterface gets the decorated version
        $gateway = $container->get(PaymentGatewayInterface::class);

        $this->assertInstanceOf(LoggingGateway::class, $gateway,
            'Resolved instance is the decorator (LoggingGateway)'
        );

        // Call charge — both the result and the logging should occur
        $result = $gateway->charge('user-alice', 5000, 'GBP');

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['transactionId']);

        // Logger received at least 2 entries (charge initiated + charge succeeded)
        $this->assertGreaterThanOrEqual(2, count($spyLogger->entries),
            'Logger received entries from the charge operation'
        );

        $messages = $spyLogger->getMessages();
        $this->assertContains('Payment charge initiated',  $messages);
        $this->assertContains('Payment charge succeeded', $messages);
    }

    /**
     * The decorator correctly delegates to the inner implementation.
     * Prove this by using a fake inner implementation and verifying
     * the decorator passes through both arguments and result.
     */
    public function testDecoratorDelegatesCorrectlyToInnerImplementation(): void
    {
        // Spy on the inner gateway — record all calls made to it
        $spyInner = new class implements PaymentGatewayInterface {
            public array $chargeCalls = [];
            public array $refundCalls = [];

            public function charge(string $userId, int $amountCents, string $currency): array {
                $this->chargeCalls[] = compact('userId', 'amountCents', 'currency');
                return ['success' => true, 'transactionId' => 'fake_txn_001', 'error' => null];
            }

            public function refund(string $transactionId): bool {
                $this->refundCalls[] = $transactionId;
                return true;
            }
        };

        $logger  = new SpyLogger();
        $gateway = new LoggingGateway($spyInner, $logger);

        // Make a charge
        $result = $gateway->charge('user-bob', 1000, 'USD');

        // Decorator passed the correct arguments to the inner gateway
        $this->assertCount(1, $spyInner->chargeCalls);
        $this->assertSame('user-bob', $spyInner->chargeCalls[0]['userId']);
        $this->assertSame(1000,       $spyInner->chargeCalls[0]['amountCents']);
        $this->assertSame('USD',      $spyInner->chargeCalls[0]['currency']);

        // Decorator returned the inner gateway's result unchanged
        $this->assertTrue($result['success']);
        $this->assertSame('fake_txn_001', $result['transactionId']);

        // Make a refund
        $refunded = $gateway->refund('fake_txn_001');
        $this->assertTrue($refunded);
        $this->assertCount(1, $spyInner->refundCalls);
        $this->assertSame('fake_txn_001', $spyInner->refundCalls[0]);
    }

    /**
     * STAGE 2: Stacked decorators.
     *
     * MetricsGateway wraps LoggingGateway wraps StripeGateway.
     * The outermost decorator (MetricsGateway) is what consumers receive.
     * Each layer adds its own behaviour transparently.
     *
     * Factory wiring:
     *   StripeGateway → singleton (concrete)
     *   PaymentGatewayInterface → factory that builds the full stack:
     *     new MetricsGateway(new LoggingGateway(StripeGateway, Logger))
     */
    public function testStackedDecoratorsAllReceiveTheDelegatedCall(): void
    {
        $spyLogger = new SpyLogger();

        $container = new DecoratorContainer();
        $container->singleton(StripeGateway::class,   fn() => new StripeGateway());
        $container->singleton(LoggerInterface::class,  fn() => $spyLogger);

        // Stacked factory: build the full decoration chain
        $container->singleton(
            PaymentGatewayInterface::class,
            function (StripeGateway $stripe, LoggerInterface $logger): PaymentGatewayInterface {
                // Inner: Stripe (real implementation)
                // Middle: LoggingGateway (adds logging)
                // Outer: MetricsGateway (adds metrics) — this is what consumers get
                $logged  = new LoggingGateway($stripe, $logger);
                $metered = new MetricsGateway($logged);
                return $metered;
            }
        );

        /** @var MetricsGateway $gateway */
        $gateway = $container->get(PaymentGatewayInterface::class);
        $this->assertInstanceOf(MetricsGateway::class, $gateway);

        // Make two charges
        $gateway->charge('user-a', 1000, 'USD');
        $gateway->charge('user-b', 2000, 'USD');

        // MetricsGateway recorded both charges
        $this->assertSame(2, $gateway->chargeCount,
            'MetricsGateway recorded 2 charges'
        );
        $this->assertCount(2, $gateway->chargeTimings,
            'MetricsGateway recorded 2 timing entries'
        );

        // LoggingGateway also ran — spy logger has entries
        $this->assertGreaterThanOrEqual(4, count($spyLogger->entries),
            'LoggingGateway logged at least 4 entries (2 initiated + 2 succeeded)'
        );
    }

    /**
     * The decorator pattern preserves Liskov Substitution Principle:
     * any consumer that depends on PaymentGatewayInterface works correctly
     * regardless of whether it receives the raw implementation or the decorated one.
     */
    public function testDecoratorPreservesLiskovSubstitutionPrinciple(): void
    {
        // Consumer: accepts any PaymentGatewayInterface
        $processPayment = function (PaymentGatewayInterface $gateway, string $userId, int $cents): bool {
            $result = $gateway->charge($userId, $cents, 'USD');
            return $result['success'];
        };

        // With raw StripeGateway — works
        $this->assertTrue($processPayment(new StripeGateway(), 'user-a', 500));

        // With LoggingGateway wrapping StripeGateway — works identically
        $this->assertTrue($processPayment(
            new LoggingGateway(new StripeGateway(), new SpyLogger()),
            'user-b', 500
        ));

        // With fully stacked decorators — still works identically
        $this->assertTrue($processPayment(
            new MetricsGateway(new LoggingGateway(new StripeGateway(), new SpyLogger())),
            'user-c', 500
        ));
    }
}