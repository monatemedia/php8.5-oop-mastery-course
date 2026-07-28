<?php
declare(strict_types=1);

/**
 * Example 02 — Dependency Injection Makes Testing Possible
 * ========================================================
 *
 *     php 02-di-makes-testing-possible.php
 *
 * The same service as Example 01 and the same logic. One change: the
 * collaborators arrive through the constructor instead of being built inside it.
 *
 * Watch what that single change makes possible.
 */

// ─────────────────────────────────────────────────────────────────────────────
// Interfaces first. This is the seam — the point where a test can get in.
// ─────────────────────────────────────────────────────────────────────────────

interface CustomerDirectory
{
    public function findCustomerEmail(int $id): string;
}

interface Mailer
{
    public function send(string $to, string $subject): bool;
}


// ─────────────────────────────────────────────────────────────────────────────
// Production implementations — unchanged in spirit from Example 01
// ─────────────────────────────────────────────────────────────────────────────

final class MySqlCustomerDirectory implements CustomerDirectory
{
    public function __construct()
    {
        echo "      [MySql] connecting to db.production.internal:3306...\n";
    }

    public function findCustomerEmail(int $id): string
    {
        return 'alice@example.com';
    }
}

final class SmtpMailer implements Mailer
{
    public function send(string $to, string $subject): bool
    {
        echo "      [Smtp] *** REAL EMAIL SENT to {$to} ***\n";
        return true;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// The service. Compare this constructor with Example 01's.
// ─────────────────────────────────────────────────────────────────────────────

final class OrderConfirmationService
{
    public function __construct(
        private readonly CustomerDirectory $directory,
        private readonly Mailer            $mailer,
    ) {}

    public function confirm(int $customerId, int $totalCents): string
    {
        $email   = $this->directory->findCustomerEmail($customerId);
        $subject = $totalCents >= 100_00
            ? 'Your priority order is confirmed'
            : 'Your order is confirmed';

        $this->mailer->send($email, $subject);

        return $subject;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// The test. No database, no network, no waiting.
// ─────────────────────────────────────────────────────────────────────────────

echo "=== Testing OrderConfirmationService ===\n\n";

$passed = 0;
$failed = 0;

function check(string $label, bool $ok): void
{
    global $passed, $failed;
    echo '  ' . ($ok ? 'PASS' : 'FAIL') . "  {$label}\n";
    $ok ? $passed++ : $failed++;
}

// A fake directory: real behaviour, simplified mechanics (an array, not MySQL).
$fakeDirectory = new class implements CustomerDirectory {
    public function findCustomerEmail(int $id): string
    {
        return match ($id) {
            1       => 'alice@example.com',
            2       => 'bob@example.com',
            default => throw new InvalidArgumentException("No customer {$id}"),
        };
    }
};

// A spy mailer: records what it was asked to do so the test can assert on it.
$spyMailer = new class implements Mailer {
    /** @var list<array{to: string, subject: string}> */
    public array $sent = [];

    public function send(string $to, string $subject): bool
    {
        $this->sent[] = ['to' => $to, 'subject' => $subject];
        return true;
    }
};

$service = new OrderConfirmationService($fakeDirectory, $spyMailer);

// ── The behaviour we could barely reach in Example 01 ────────────────────────
$subject = $service->confirm(customerId: 1, totalCents: 250_00);
check('an order of R100+ is marked priority', $subject === 'Your priority order is confirmed');

$subject = $service->confirm(customerId: 2, totalCents: 99_99);
check('an order under R100 is not', $subject === 'Your order is confirmed');

// ── Boundary — the sort of case nobody tests when tests are painful ──────────
$subject = $service->confirm(customerId: 1, totalCents: 100_00);
check('exactly R100 IS priority (>= not >)', $subject === 'Your priority order is confirmed');

// ── Side effects, now observable ─────────────────────────────────────────────
check('one email per confirmation', count($spyMailer->sent) === 3);
check('the second went to the right customer', $spyMailer->sent[1]['to'] === 'bob@example.com');

// ── A failure path, which Example 01 could not reach at all ──────────────────
$threw = false;
try {
    $service->confirm(customerId: 99, totalCents: 50_00);
} catch (InvalidArgumentException) {
    $threw = true;
}
check('an unknown customer is rejected', $threw);

echo "\n  {$passed} passed, {$failed} failed\n";


// ─────────────────────────────────────────────────────────────────────────────

echo "\n=== What changed ===\n\n";

$ctor = (new ReflectionClass(OrderConfirmationService::class))->getConstructor();
echo "  __construct() now takes " . count($ctor?->getParameters() ?? []) . " parameters:\n";
foreach ($ctor?->getParameters() ?? [] as $p) {
    echo "    - \$" . $p->getName() . ' : ' . $p->getType() . "\n";
}

echo "\n  Both are interfaces, so anything implementing them is acceptable — and\n";
echo "  the test supplied objects that exist only inside this file.\n\n";

echo "  Six assertions ran. No email was sent, no database was contacted, and\n";
echo "  the whole thing finished in microseconds. Three of those assertions\n";
echo "  (the boundary case, the call count, the rejected customer) were simply\n";
echo "  unreachable in Example 01.\n\n";

echo "  The logic is identical. Only the direction of the arrow changed: the\n";
echo "  class stopped reaching out for its collaborators and started being\n";
echo "  handed them.\n";

echo "\n";
echo "  ---------------------------------------------------------------\n";
echo "  This is why Modules 1-4 came first. Dependency injection is not\n";
echo "  a testing technique that happens to be good design. It is good\n";
echo "  design that happens to make testing possible.\n";
echo "  ---------------------------------------------------------------\n";
echo "\n  Continue to 03-the-four-double-types.php.\n";
