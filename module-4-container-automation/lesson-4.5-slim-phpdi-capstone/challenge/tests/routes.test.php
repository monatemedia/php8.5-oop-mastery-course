<?php
declare(strict_types=1);

/**
 * tests/routes.test.php — Request Simulation Tests
 * ---------------------------------------------------
 * Tests all four routes using Slim's ServerRequestFactory.
 * No web server needed — the full Slim pipeline runs in-process.
 * No PHPUnit needed — pure assertions printed as ✓ / ✗.
 *
 * Run from project root:
 *   php challenge/tests/routes.test.php
 *
 * Course Philosophy Rule 2: Test behaviours, not layouts.
 * Each test asserts observable HTTP behaviour:
 *   - Status code
 *   - Response envelope (success field)
 *   - Data payload fields
 */

require __DIR__ . '/../vendor/autoload.php';

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use App\Http\ProductController;
use App\Http\OrderController;

// ─────────────────────────────────────────────────────────────────────────────
// Bootstrap — identical to public/index.php, but with test definitions
// ─────────────────────────────────────────────────────────────────────────────

// Test definitions override the mailer with a spy so we can assert on sends
$spyMailer = new class implements \App\Contracts\MailerInterface {
    public array $sent = [];
    public function send(string $to, string $subject, string $body): bool {
        $this->sent[] = compact('to', 'subject', 'body');
        return true;
    }
};

$builder = new ContainerBuilder();
$builder->addDefinitions(__DIR__ . '/../config/services.php');

// Override the mailer with our spy for testing
$builder->addDefinitions([
    \App\Contracts\MailerInterface::class => \DI\factory(fn() => $spyMailer),
]);

$container = $builder->build();
AppFactory::setContainer($container);
$app = AppFactory::create();
$app->addErrorMiddleware(false, false, false);
require __DIR__ . '/../config/routes.php';


// ─────────────────────────────────────────────────────────────────────────────
// Test helpers
// ─────────────────────────────────────────────────────────────────────────────

$passed = 0;
$failed = 0;

function assertThat(bool $condition, string $label, int &$passed, int &$failed): void {
    if ($condition) {
        echo "  ✓ {$label}\n";
        $passed++;
    } else {
        echo "  ✗ {$label}\n";
        $failed++;
    }
}

function get(string $uri, \Slim\App $app): array {
    $request  = (new ServerRequestFactory())->createServerRequest('GET', $uri);
    $response = $app->handle($request);
    return [
        'status' => $response->getStatusCode(),
        'body'   => json_decode((string) $response->getBody(), true) ?? [],
    ];
}

function post(string $uri, array $data, \Slim\App $app): array {
    $body    = json_encode($data);
    $stream  = (new StreamFactory())->createStream($body);
    $request = (new ServerRequestFactory())
        ->createServerRequest('POST', $uri)
        ->withBody($stream)
        ->withHeader('Content-Type', 'application/json');
    $response = $app->handle($request);
    return [
        'status' => $response->getStatusCode(),
        'body'   => json_decode((string) $response->getBody(), true) ?? [],
    ];
}


// ─────────────────────────────────────────────────────────────────────────────
// Test suite
// ─────────────────────────────────────────────────────────────────────────────

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Route Tests — Lesson 4.5 Capstone                  ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";


// ── Test 1: GET /products — list all ────────────────────────────────────────

echo "Test 1: GET /products\n";
$r = get('/products', $app);
assertThat($r['status'] === 200,                         'Status 200',               $passed, $failed);
assertThat($r['body']['success'] === true,               'success=true',             $passed, $failed);
assertThat(count($r['body']['data'] ?? []) >= 2,         'At least 2 products',      $passed, $failed);
assertThat(isset($r['body']['data'][0]['name']),          'First product has name',   $passed, $failed);
echo "\n";


// ── Test 2: GET /products?min_price=20000 — price filter ────────────────────

echo "Test 2: GET /products?min_price=20000\n";
$r = get('/products?min_price=20000', $app);
assertThat($r['status'] === 200,             'Status 200',                   $passed, $failed);
assertThat($r['body']['success'] === true,   'success=true',                 $passed, $failed);
$prices = array_column($r['body']['data'] ?? [], 'price');
assertThat(min($prices ?: [PHP_INT_MAX]) >= 20000, 'All products >= 20000 cents', $passed, $failed);
echo "\n";


// ── Test 3: GET /products/1 — get specific product ──────────────────────────

echo "Test 3: GET /products/1\n";
$r = get('/products/1', $app);
assertThat($r['status'] === 200,                         'Status 200',               $passed, $failed);
assertThat($r['body']['success'] === true,               'success=true',             $passed, $failed);
assertThat(($r['body']['data']['id'] ?? 0) === 1,        'data.id = 1',              $passed, $failed);
assertThat(isset($r['body']['data']['name']),             'data.name exists',         $passed, $failed);
assertThat(isset($r['body']['data']['price']),            'data.price exists',        $passed, $failed);
echo "\n";


// ── Test 4: GET /products/99 — not found ────────────────────────────────────

echo "Test 4: GET /products/99 (not found)\n";
$r = get('/products/99', $app);
assertThat($r['status'] === 404,             'Status 404',    $passed, $failed);
assertThat($r['body']['success'] === false,  'success=false', $passed, $failed);
assertThat(isset($r['body']['error']),       'error field set', $passed, $failed);
echo "\n";


// ── Test 5: POST /orders — valid order ──────────────────────────────────────

echo "Test 5: POST /orders (valid)\n";
$mailsBefore = count($spyMailer->sent);
$r = post('/orders', [
    'product_id' => 1,
    'quantity'   => 2,
    'email'      => 'alice@example.com',
], $app);
assertThat($r['status'] === 201,                          'Status 201',              $passed, $failed);
assertThat($r['body']['success'] === true,                'success=true',            $passed, $failed);
assertThat(isset($r['body']['data']['id']),               'data.id exists',          $passed, $failed);
assertThat(($r['body']['data']['quantity'] ?? 0) === 2,   'quantity=2',              $passed, $failed);
assertThat(count($spyMailer->sent) === $mailsBefore + 1,  'Confirmation email sent', $passed, $failed);
assertThat($spyMailer->sent[$mailsBefore]['to'] === 'alice@example.com', 'Email sent to alice', $passed, $failed);
echo "\n";


// ── Test 6: POST /orders — missing email (422) ──────────────────────────────

echo "Test 6: POST /orders (missing email)\n";
$r = post('/orders', ['product_id' => 1, 'quantity' => 1], $app);
assertThat($r['status'] === 422,             'Status 422',    $passed, $failed);
assertThat($r['body']['success'] === false,  'success=false', $passed, $failed);
assertThat(isset($r['body']['error']),       'error field set', $passed, $failed);
echo "\n";


// ── Test 7: POST /orders — product not found (422) ──────────────────────────

echo "Test 7: POST /orders (product not found)\n";
$r = post('/orders', ['product_id' => 99, 'quantity' => 1, 'email' => 'bob@example.com'], $app);
assertThat($r['status'] === 422,             'Status 422',    $passed, $failed);
assertThat($r['body']['success'] === false,  'success=false', $passed, $failed);
echo "\n";


// ── Test 8: POST /orders — invalid email (422) ──────────────────────────────

echo "Test 8: POST /orders (invalid email)\n";
$r = post('/orders', ['product_id' => 1, 'quantity' => 1, 'email' => 'not-an-email'], $app);
assertThat($r['status'] === 422,             'Status 422',    $passed, $failed);
assertThat($r['body']['success'] === false,  'success=false', $passed, $failed);
echo "\n";


// ── Test 9: GET /orders/1 — get order placed in Test 5 ──────────────────────

echo "Test 9: GET /orders/1\n";
$r = get('/orders/1', $app);
assertThat($r['status'] === 200,                          'Status 200',              $passed, $failed);
assertThat($r['body']['success'] === true,                'success=true',            $passed, $failed);
assertThat(($r['body']['data']['id'] ?? 0) === 1,         'data.id = 1',             $passed, $failed);
assertThat(($r['body']['data']['email'] ?? '') === 'alice@example.com', 'Correct email', $passed, $failed);
echo "\n";


// ── Test 10: GET /orders/99 — not found ─────────────────────────────────────

echo "Test 10: GET /orders/99 (not found)\n";
$r = get('/orders/99', $app);
assertThat($r['status'] === 404,             'Status 404',    $passed, $failed);
assertThat($r['body']['success'] === false,  'success=false', $passed, $failed);
assertThat(isset($r['body']['error']),       'error field set', $passed, $failed);
echo "\n";


// ── Rule 1 audit ─────────────────────────────────────────────────────────────

echo "Rule 1 audit — no getenv() or container->get() in src/:\n";

$srcDir   = __DIR__ . '/../src';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir));
$violations = [];

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') continue;
    $contents = file_get_contents($file->getPathname());
    $relPath  = str_replace($srcDir . '/', '', $file->getPathname());

    if (str_contains($contents, 'getenv(')) {
        $violations[] = "  ✗ getenv() found in src/{$relPath}";
    }
    if (str_contains($contents, '->get(')) {
        $violations[] = "  ✗ ->get() found in src/{$relPath}";
    }
}

if (empty($violations)) {
    echo "  ✓ No getenv() or ->get() calls found in src/\n";
    $passed++;
} else {
    foreach ($violations as $v) {
        echo $v . "\n";
        $failed++;
    }
}

echo "\n";


// ── Results ──────────────────────────────────────────────────────────────────

$total = $passed + $failed;
echo "══════════════════════════════════════════════════════\n";
echo "Results: {$passed}/{$total} passed";
if ($failed === 0) {
    echo " — All tests PASSED ✓\n";
} else {
    echo " — {$failed} test(s) FAILED ✗\n";
}
echo "══════════════════════════════════════════════════════\n";