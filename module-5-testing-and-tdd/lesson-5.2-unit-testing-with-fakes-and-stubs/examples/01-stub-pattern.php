<?php
declare(strict_types=1);

/**
 * Example 01 — The Stub Pattern
 * ------------------------------
 * Run via PHPUnit:
 *   ./vendor/bin/phpunit module-5-testing-and-tdd/lesson-5.2-unit-testing-with-fakes-and-stubs/examples/01-stub-pattern.php
 *
 * A stub returns a fixed, predetermined value regardless of input.
 * Its only job is to CONTROL what the dependency returns so the test
 * can verify how the CLASS UNDER TEST reacts to that value.
 *
 * This example covers:
 *   A. Why the stub exists — isolating the class under test from infrastructure
 *   B. A stub for a happy path (returns success)
 *   C. A stub for a failure path (returns failure value)
 *   D. Using different stubs in different tests for the same service
 *   E. Stubs with state — returning different values on successive calls
 */

use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────────────
// Contracts (interfaces) — the seams that make stubbing possible
// ─────────────────────────────────────────────────────────────────────────────

interface WeatherServiceInterface
{
    /** @return array{temp: float, condition: string, humidity: int} */
    public function getCurrent(string $city): array;
}

interface CurrencyRateServiceInterface
{
    public function getRate(string $from, string $to): float;
}

// ─────────────────────────────────────────────────────────────────────────────
// The class under test
// WeatherReporter depends on two external services — both injectable,
// both stubbable. No real HTTP calls happen in a unit test.
// ─────────────────────────────────────────────────────────────────────────────

class WeatherReporter
{
    public function __construct(
        private WeatherServiceInterface      $weather,
        private CurrencyRateServiceInterface $rates
    ) {}

    /**
     * Returns a formatted weather summary.
     * Converts temperature from °C to °F if requested.
     */
    public function summarise(string $city, bool $fahrenheit = false): string
    {
        $data = $this->weather->getCurrent($city);

        $temp = $fahrenheit
            ? round(($data['temp'] * 9 / 5) + 32, 1)
            : $data['temp'];

        $unit = $fahrenheit ? '°F' : '°C';

        return sprintf(
            '%s: %.1f%s, %s, humidity %d%%',
            $city,
            $temp,
            $unit,
            $data['condition'],
            $data['humidity']
        );
    }

    /**
     * Returns a trip cost estimate in the target currency.
     * Budget is in ZAR; converts to destination currency.
     */
    public function estimateTripCost(float $zarBudget, string $destinationCurrency): array
    {
        $rate = $this->rates->getRate('ZAR', $destinationCurrency);

        if ($rate <= 0) {
            throw new \RuntimeException("Invalid exchange rate for {$destinationCurrency}: {$rate}");
        }

        $converted = round($zarBudget * $rate, 2);

        return [
            'budget_zar'             => $zarBudget,
            'budget_destination'     => $converted,
            'destination_currency'   => $destinationCurrency,
            'rate_used'              => $rate,
        ];
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// The test class
// ─────────────────────────────────────────────────────────────────────────────

class StubPatternExampleTest extends TestCase
{
    // ═══════════════════════════════════════════════════════════
    // PART A — Why the stub exists
    // ═══════════════════════════════════════════════════════════

    /**
     * Without a stub, testing WeatherReporter requires:
     *   - A real HTTP connection to a weather API
     *   - A live API key
     *   - A network that works at test time
     *   - Weather conditions that are predictable
     *
     * All of these make the test slow, flaky, and environment-dependent.
     *
     * The stub replaces the real service with a controlled double:
     *   - Returns exactly the data the test specifies
     *   - Works offline
     *   - Never changes unexpectedly
     *   - Runs in microseconds
     */
    public function testSummariseReturnsCelsiusFormattedString(): void
    {
        // ── Arrange: stub the weather service ────────────────────────────────
        $stubWeather = new class implements WeatherServiceInterface {
            public function getCurrent(string $city): array {
                // Always returns this — regardless of which city was passed
                return ['temp' => 22.5, 'condition' => 'Partly Cloudy', 'humidity' => 65];
            }
        };

        $stubRates = new class implements CurrencyRateServiceInterface {
            public function getRate(string $from, string $to): float { return 1.0; }
        };

        $reporter = new WeatherReporter($stubWeather, $stubRates);

        // ── Act ───────────────────────────────────────────────────────────────
        $result = $reporter->summarise('Cape Town');

        // ── Assert: test WeatherReporter's formatting logic, not the API ─────
        $this->assertSame('Cape Town: 22.5°C, Partly Cloudy, humidity 65%', $result);
    }

    // ═══════════════════════════════════════════════════════════
    // PART B — Stub for the happy path
    // ═══════════════════════════════════════════════════════════

    /**
     * The stub controls the input so the test can verify the transformation.
     * Here we test the °C → °F conversion logic inside WeatherReporter.
     * The stub gives us a known input; the assertion checks the math.
     */
    public function testSummariseConvertsCelsiusToFahrenheitWhenRequested(): void
    {
        $stubWeather = new class implements WeatherServiceInterface {
            public function getCurrent(string $city): array {
                return ['temp' => 0.0, 'condition' => 'Clear', 'humidity' => 40];
                // 0°C = 32°F — easy to verify mentally
            }
        };

        $stubRates = new class implements CurrencyRateServiceInterface {
            public function getRate(string $from, string $to): float { return 1.0; }
        };

        $reporter = new WeatherReporter($stubWeather, $stubRates);

        $result = $reporter->summarise('Johannesburg', fahrenheit: true);

        $this->assertSame('Johannesburg: 32.0°F, Clear, humidity 40%', $result);
    }

    public function testSummariseHandlesHighTemperatureConversion(): void
    {
        $stubWeather = new class implements WeatherServiceInterface {
            public function getCurrent(string $city): array {
                return ['temp' => 100.0, 'condition' => 'Hot', 'humidity' => 10];
                // 100°C = 212°F
            }
        };

        $stubRates = new class implements CurrencyRateServiceInterface {
            public function getRate(string $from, string $to): float { return 1.0; }
        };

        $reporter = new WeatherReporter($stubWeather, $stubRates);
        $result   = $reporter->summarise('Sahara', fahrenheit: true);

        $this->assertSame('Sahara: 212.0°F, Hot, humidity 10%', $result);
    }

    // ═══════════════════════════════════════════════════════════
    // PART C — Stub controlling the rate service
    // ═══════════════════════════════════════════════════════════

    /**
     * The rate stub controls what exchange rate WeatherReporter receives.
     * This lets us test the currency conversion arithmetic without a real
     * API call. The rate is fixed by the stub — we verify the math.
     */
    public function testEstimateTripCostConvertsZarToDestinationCurrency(): void
    {
        $stubWeather = new class implements WeatherServiceInterface {
            public function getCurrent(string $city): array { return ['temp' => 20.0, 'condition' => 'Sunny', 'humidity' => 50]; }
        };

        // Stub: ZAR → USD rate is 0.054
        $stubRates = new class implements CurrencyRateServiceInterface {
            public function getRate(string $from, string $to): float {
                return 0.054;
            }
        };

        $reporter = new WeatherReporter($stubWeather, $stubRates);

        $result = $reporter->estimateTripCost(zarBudget: 10000.00, destinationCurrency: 'USD');

        $this->assertSame(10000.00, $result['budget_zar']);
        $this->assertSame(540.0,    $result['budget_destination']);  // 10000 × 0.054
        $this->assertSame('USD',    $result['destination_currency']);
        $this->assertSame(0.054,    $result['rate_used']);
    }

    public function testEstimateTripCostRoundsToTwoDecimalPlaces(): void
    {
        $stubWeather = new class implements WeatherServiceInterface {
            public function getCurrent(string $city): array { return ['temp' => 20.0, 'condition' => 'Sunny', 'humidity' => 50]; }
        };

        // Rate that will produce a repeating decimal
        $stubRates = new class implements CurrencyRateServiceInterface {
            public function getRate(string $from, string $to): float {
                return 1.0 / 3.0; // 0.333...
            }
        };

        $reporter = new WeatherReporter($stubWeather, $stubRates);
        $result   = $reporter->estimateTripCost(zarBudget: 100.00, destinationCurrency: 'EUR');

        // 100 × (1/3) = 33.333... → rounded to 33.33
        $this->assertEqualsWithDelta(33.33, $result['budget_destination'], delta: 0.005);
    }

    // ═══════════════════════════════════════════════════════════
    // PART D — Different stubs in different tests for the same service
    // ═══════════════════════════════════════════════════════════

    /**
     * The failure path: the rate service returns zero.
     * WeatherReporter should throw a RuntimeException.
     * The stub controls WHAT the dependency returns; the test verifies
     * how WeatherReporter REACTS.
     */
    public function testEstimateTripCostThrowsWhenRateIsZero(): void
    {
        $stubWeather = new class implements WeatherServiceInterface {
            public function getCurrent(string $city): array { return ['temp' => 20.0, 'condition' => 'Sunny', 'humidity' => 50]; }
        };

        // Failure stub — returns an invalid rate
        $zeroRateStub = new class implements CurrencyRateServiceInterface {
            public function getRate(string $from, string $to): float {
                return 0.0; // Invalid — triggers WeatherReporter's guard
            }
        };

        $reporter = new WeatherReporter($stubWeather, $zeroRateStub);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid exchange rate');

        $reporter->estimateTripCost(zarBudget: 1000.00, destinationCurrency: 'XYZ');
    }

    public function testEstimateTripCostThrowsWhenRateIsNegative(): void
    {
        $stubWeather = new class implements WeatherServiceInterface {
            public function getCurrent(string $city): array { return ['temp' => 20.0, 'condition' => 'Sunny', 'humidity' => 50]; }
        };

        $negativeRateStub = new class implements CurrencyRateServiceInterface {
            public function getRate(string $from, string $to): float {
                return -1.0; // Also invalid
            }
        };

        $reporter = new WeatherReporter($stubWeather, $negativeRateStub);

        $this->expectException(\RuntimeException::class);

        $reporter->estimateTripCost(zarBudget: 1000.00, destinationCurrency: 'XYZ');
    }

    // ═══════════════════════════════════════════════════════════
    // PART E — Stubs with state: returning different values per call
    // ═══════════════════════════════════════════════════════════

    /**
     * Sometimes a stub needs to behave differently on successive calls.
     * Add a counter or a queue to the anonymous class.
     *
     * Here we simulate a rate service that returns different rates for
     * different currency pairs by inspecting the $to argument.
     */
    public function testRateStubCanReturnDifferentRatesPerCurrencyPair(): void
    {
        $stubWeather = new class implements WeatherServiceInterface {
            public function getCurrent(string $city): array { return ['temp' => 20.0, 'condition' => 'Sunny', 'humidity' => 50]; }
        };

        // Conditional stub — returns different rates per currency
        $multiRateStub = new class implements CurrencyRateServiceInterface {
            public function getRate(string $from, string $to): float {
                return match ($to) {
                    'USD' => 0.054,
                    'EUR' => 0.051,
                    'GBP' => 0.044,
                    default => throw new \InvalidArgumentException("Unsupported currency: {$to}"),
                };
            }
        };

        $reporter = new WeatherReporter($stubWeather, $multiRateStub);

        $usd = $reporter->estimateTripCost(10000, 'USD');
        $eur = $reporter->estimateTripCost(10000, 'EUR');
        $gbp = $reporter->estimateTripCost(10000, 'GBP');

        $this->assertSame(540.0, $usd['budget_destination']);
        $this->assertSame(510.0, $eur['budget_destination']);
        $this->assertSame(440.0, $gbp['budget_destination']);
    }

    /**
     * Call-count stub — returns a different value each successive call.
     * Useful when testing code that calls a dependency multiple times.
     */
    public function testStubWithCallCounter(): void
    {
        // Stub that returns 22.5 on first call, 18.0 on second
        $sequentialStub = new class implements WeatherServiceInterface {
            private int $callCount = 0;
            private array $responses = [
                ['temp' => 22.5, 'condition' => 'Sunny',  'humidity' => 60],
                ['temp' => 18.0, 'condition' => 'Cloudy', 'humidity' => 75],
            ];
            public function getCurrent(string $city): array {
                return $this->responses[$this->callCount++] ?? end($this->responses);
            }
        };

        $stubRates = new class implements CurrencyRateServiceInterface {
            public function getRate(string $from, string $to): float { return 1.0; }
        };

        $reporter = new WeatherReporter($sequentialStub, $stubRates);

        $first  = $reporter->summarise('Cape Town');
        $second = $reporter->summarise('Cape Town');

        $this->assertStringContainsString('22.5', $first);
        $this->assertStringContainsString('18.0', $second);
    }
}