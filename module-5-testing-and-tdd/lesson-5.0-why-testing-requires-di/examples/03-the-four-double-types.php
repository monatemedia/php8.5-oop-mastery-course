<?php
declare(strict_types=1);

/**
 * Example 03 — The Four Test Double Types
 * ========================================
 *
 *     php 03-the-four-double-types.php
 *
 * Fake, Stub, Spy, Null Object — the four this course uses, side by side against
 * one service, so the difference is visible rather than described.
 *
 * A fifth name you will meet elsewhere is the MOCK. Lesson 5.2 Section 7 covers
 * why this course reaches for a spy instead; the short version is that a spy
 * records and lets you assert afterwards, while a mock is told its expectations
 * up front and fails at the point of the wrong call. Spies give clearer failure
 * output and produce less brittle tests.
 */

interface PaymentGateway { public function charge(int $cents): bool; }
interface Mailer        { public function send(string $to, string $subject): bool; }
interface Basket        { public function itemsFor(int $userId): array; }
interface AuditLog      { public function record(string $event): void; }

final class CheckoutService
{
    public function __construct(
        private readonly Basket         $basket,
        private readonly PaymentGateway $gateway,
        private readonly Mailer         $mailer,
        private readonly AuditLog       $audit,
    ) {}

    public function checkout(int $userId, string $email): string
    {
        $items = $this->basket->itemsFor($userId);
        if ($items === []) {
            return 'empty';
        }

        $total = array_sum(array_column($items, 'cents'));
        $this->audit->record("checkout.attempted:{$userId}");

        if (!$this->gateway->charge($total)) {
            return 'declined';
        }

        $this->mailer->send($email, 'Your order is confirmed');
        return 'confirmed';
    }
}

$pass = 0; $fail = 0;
function check(string $l, bool $ok): void {
    global $pass, $fail;
    echo '    ' . ($ok ? 'PASS' : 'FAIL') . "  {$l}\n";
    $ok ? $pass++ : $fail++;
}


// ═════════════════════════════════════════════════════════════════════════════
// 1. FAKE — a working implementation with simplified mechanics
// ═════════════════════════════════════════════════════════════════════════════

echo "=== 1. FAKE ===\n";
echo "  A real implementation, simplified. An array instead of a database.\n";
echo "  Use it when the collaborator must genuinely BEHAVE for the test to mean\n";
echo "  anything — here, different users must get different baskets.\n\n";

$fakeBasket = new class implements Basket {
    /** @var array<int, list<array{sku: string, cents: int}>> */
    private array $rows = [
        1 => [['sku' => 'WIDGET', 'cents' => 2999], ['sku' => 'CABLE', 'cents' => 999]],
        2 => [],
    ];

    public function itemsFor(int $userId): array
    {
        return $this->rows[$userId] ?? [];   // real lookup logic, fake storage
    }
};

check('user 1 has two items', count($fakeBasket->itemsFor(1)) === 2);
check('user 2 has an empty basket', $fakeBasket->itemsFor(2) === []);
check('an unknown user gets an empty basket', $fakeBasket->itemsFor(77) === []);
echo "\n  Note it answers differently per input. That is what makes it a fake\n";
echo "  rather than a stub.\n\n";


// ═════════════════════════════════════════════════════════════════════════════
// 2. STUB — a canned answer, no logic
// ═════════════════════════════════════════════════════════════════════════════

echo "=== 2. STUB ===\n";
echo "  Returns what you tell it to, ignoring its input. Use it to control what\n";
echo "  the collaborator RETURNS so you can test how your class reacts.\n\n";

$approvingGateway = new class implements PaymentGateway {
    public function charge(int $cents): bool { return true; }
};

$decliningGateway = new class implements PaymentGateway {
    public function charge(int $cents): bool { return false; }
};

$nullMailer = new class implements Mailer {
    public function send(string $to, string $subject): bool { return true; }
};
$nullAudit = new class implements AuditLog {
    public function record(string $event): void {}
};

$svc = new CheckoutService($fakeBasket, $approvingGateway, $nullMailer, $nullAudit);
check('an approved payment confirms the order', $svc->checkout(1, 'a@b.com') === 'confirmed');

$svc = new CheckoutService($fakeBasket, $decliningGateway, $nullMailer, $nullAudit);
check('a declined payment is reported', $svc->checkout(1, 'a@b.com') === 'declined');

echo "\n  Two stubs, two branches. Making a REAL gateway decline on demand is\n";
echo "  somewhere between hard and impossible — which is how failure paths end\n";
echo "  up untested in a codebase without doubles.\n\n";


// ═════════════════════════════════════════════════════════════════════════════
// 3. SPY — records what happened so the test can assert on it
// ═════════════════════════════════════════════════════════════════════════════

echo "=== 3. SPY ===\n";
echo "  Records the calls made to it. Use it when the outbound call IS the\n";
echo "  behaviour under test — that an email was sent, that an event fired.\n\n";

$spyMailer = new class implements Mailer {
    /** @var list<array{to: string, subject: string}> */
    public array $sent = [];

    public function send(string $to, string $subject): bool
    {
        $this->sent[] = ['to' => $to, 'subject' => $subject];
        return true;
    }
};

$svc = new CheckoutService($fakeBasket, $approvingGateway, $spyMailer, $nullAudit);
$svc->checkout(1, 'alice@example.com');

check('exactly one email was sent', count($spyMailer->sent) === 1);
check('it went to the right address', $spyMailer->sent[0]['to'] === 'alice@example.com');
check('with the right subject', $spyMailer->sent[0]['subject'] === 'Your order is confirmed');

// A spy is equally good at proving something did NOT happen.
$spyMailer2 = new class implements Mailer {
    public array $sent = [];
    public function send(string $to, string $subject): bool { $this->sent[] = $to; return true; }
};
$svc = new CheckoutService($fakeBasket, $decliningGateway, $spyMailer2, $nullAudit);
$svc->checkout(1, 'alice@example.com');
check('a declined payment sends NO email', $spyMailer2->sent === []);

echo "\n  That last assertion is the one people forget. \"It did not happen\" is a\n";
echo "  behaviour, and it is worth testing — charging a card and then failing to\n";
echo "  confirm is a support ticket; confirming an order you never charged is a\n";
echo "  refund.\n\n";


// ═════════════════════════════════════════════════════════════════════════════
// 4. NULL OBJECT — silence, on purpose
// ═════════════════════════════════════════════════════════════════════════════

echo "=== 4. NULL OBJECT ===\n";
echo "  Satisfies the interface and does nothing. Use it when the collaborator\n";
echo "  is irrelevant to the behaviour under test and you want it out of the way.\n\n";

$nullLog = new class implements AuditLog {
    public function record(string $event): void { /* deliberately nothing */ }
};

$svc = new CheckoutService($fakeBasket, $approvingGateway, $nullMailer, $nullLog);
check('checkout works with auditing silenced', $svc->checkout(1, 'a@b.com') === 'confirmed');
check('an empty basket short-circuits', $svc->checkout(2, 'a@b.com') === 'empty');

echo "\n  The null object says \"this is not what I am testing\". A stub returning\n";
echo "  a value would work too, but it invites the reader to wonder why THAT\n";
echo "  value — and there is no answer, because it does not matter.\n\n";
echo "  One caveat carried over from Lesson 2.0: a null object may skip the side\n";
echo "  effect, but it must not lie. If the interface exposes a query — a\n";
echo "  getEntries() that callers read back — a version that silently drops\n";
echo "  everything breaks the contract. Do nothing, but do not misreport.\n\n";


// ═════════════════════════════════════════════════════════════════════════════

echo str_repeat('=', 74), "\n";
echo "  {$pass} passed, {$fail} failed\n";
echo str_repeat('=', 74), "\n\n";

printf("  %-14s%s\n", 'FAKE',        'real behaviour, simplified mechanics');
printf("  %-14s%s\n", 'STUB',        'canned return value, no logic');
printf("  %-14s%s\n", 'SPY',         'records calls; you assert afterwards');
printf("  %-14s%s\n", 'NULL OBJECT', 'does nothing; keeps the irrelevant out of the way');
echo "\n";
printf("  %-14s%s\n", '(MOCK)',      'expectations declared up front, fails at the call.');
printf("  %-14s%s\n", '',            'Lesson 5.2 explains why this course prefers a spy.');

echo "\n  Every double above is an anonymous class: no file, no name, defined where\n";
echo "  it is used, and impossible to accidentally share with another test.\n";
echo "  Lesson 2.4 introduced the syntax; this is what it was for.\n";
