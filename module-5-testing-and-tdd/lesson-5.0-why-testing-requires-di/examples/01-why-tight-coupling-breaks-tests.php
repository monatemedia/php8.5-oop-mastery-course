<?php
declare(strict_types=1);

/**
 * Example 01 — Why Tight Coupling Breaks Tests
 * =============================================
 *
 *     php 01-why-tight-coupling-breaks-tests.php
 *
 * Lesson 5.0 comes before PHPUnit, so there is no PHPUnit here. Assertions are
 * plain `if` statements — which is all an assertion has ever been.
 *
 * The claim this module rests on: you cannot test a class that builds its own
 * collaborators. Not "it is harder". You cannot. This file demonstrates why by
 * trying, and failing, for reasons the type system will show you.
 */

// ─────────────────────────────────────────────────────────────────────────────
// The collaborators. Each one announces itself, standing in for the real cost:
// a TCP connection, an SMTP handshake, a file handle.
// ─────────────────────────────────────────────────────────────────────────────

final class MySqlConnection
{
    public function __construct()
    {
        echo "      [MySqlConnection] connecting to db.production.internal:3306...\n";
        // A real one would throw here if the host were unreachable.
    }

    public function findCustomerEmail(int $id): string
    {
        echo "      [MySqlConnection] SELECT email FROM customers WHERE id = {$id}\n";
        return 'alice@example.com';
    }
}

final class SmtpMailer
{
    public function __construct()
    {
        echo "      [SmtpMailer] opening SMTP session with smtp.sendgrid.net:587...\n";
    }

    public function send(string $to, string $subject): bool
    {
        echo "      [SmtpMailer] *** REAL EMAIL SENT to {$to}: {$subject} ***\n";
        return true;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// The class under test. Note the constructor: it takes nothing, and it decides
// everything.
// ─────────────────────────────────────────────────────────────────────────────

final class OrderConfirmationService
{
    private MySqlConnection $db;
    private SmtpMailer      $mailer;

    public function __construct()
    {
        $this->db     = new MySqlConnection();   // ← the whole problem
        $this->mailer = new SmtpMailer();        // ← and again
    }

    /** The logic we actually want to test: does a large order say "priority"? */
    public function confirm(int $customerId, int $totalCents): string
    {
        $email   = $this->db->findCustomerEmail($customerId);
        $subject = $totalCents >= 100_00
            ? 'Your priority order is confirmed'
            : 'Your order is confirmed';

        $this->mailer->send($email, $subject);

        return $subject;
    }
}


echo "=== Attempting to test OrderConfirmationService ===\n\n";

echo "  The behaviour under test is one line of logic:\n";
echo "    an order of R100 or more should be a PRIORITY order.\n\n";

echo "  To reach that line, the test must construct the service:\n\n";

$service = new OrderConfirmationService();

echo "\n  Look at what has already happened, before a single assertion:\n";
echo "    - a database connection was opened\n";
echo "    - an SMTP session was opened\n\n";

echo "  Now the test itself:\n\n";

$subject = $service->confirm(customerId: 1, totalCents: 250_00);

$passed = $subject === 'Your priority order is confirmed';
echo "\n  " . ($passed ? 'PASS' : 'FAIL') . "  large order is marked priority\n";

echo "\n";
echo "  The assertion passed. The test is still worthless, and here is why:\n\n";
echo "    1. It sent a real email. Run this suite a thousand times in CI and you\n";
echo "       have sent a thousand emails to a real customer.\n";
echo "    2. It needs a reachable database. On a laptop with no VPN, on a build\n";
echo "       agent, at 3am when the host is down — the test fails for a reason\n";
echo "       that has nothing to do with the code being wrong.\n";
echo "    3. It cannot test the failure paths at all. How do you make the real\n";
echo "       mailer fail on demand? You cannot, so those branches stay untested.\n";
echo "    4. It is slow. Two network round trips to check one comparison.\n";


// ─────────────────────────────────────────────────────────────────────────────
// The structural proof — this is the part worth remembering
// ─────────────────────────────────────────────────────────────────────────────

echo "\n=== Why no amount of effort fixes this ===\n\n";

$ctor   = (new ReflectionClass(OrderConfirmationService::class))->getConstructor();
$params = $ctor?->getParameters() ?? [];

echo "  OrderConfirmationService::__construct() takes "
   . count($params) . " parameter(s).\n\n";

echo "  That number is the whole story. A test substitutes a dependency by\n";
echo "  PASSING one in. With no parameters there is nowhere to pass anything,\n";
echo "  so there is no seam — no point at which the test can get between the\n";
echo "  class and the things it talks to.\n\n";

echo "  You cannot subclass your way out either: the properties are private and\n";
echo "  assigned in the constructor, so by the time any override could run, the\n";
echo "  connections are already open.\n\n";

echo "  The dependencies are not merely hard to replace. They are unreachable.\n";

echo "\n";
echo "  ---------------------------------------------------------------\n";
echo "  Testability is not a property you add to a class afterwards.\n";
echo "  It is a consequence of how the class receives its dependencies.\n";
echo "  ---------------------------------------------------------------\n";
echo "\n  Continue to 02-di-makes-testing-possible.php — same logic, one change.\n";
