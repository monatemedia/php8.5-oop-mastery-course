<?php
declare(strict_types=1);

/**
 * Example 04 — Interface Constants
 * ----------------------------------
 * Interfaces can declare constants. Every class that implements the interface
 * inherits those constants automatically.
 *
 * Scenario: An HTTP client library where status codes, methods, and content
 * types are defined as interface constants — not scattered magic strings.
 */


// ─────────────────────────────────────────────────────────────────────────────
// Interface with constants — the "vocabulary" of the HTTP domain
// ─────────────────────────────────────────────────────────────────────────────

interface HttpContract {
    // HTTP Methods
    const METHOD_GET    = 'GET';
    const METHOD_POST   = 'POST';
    const METHOD_PUT    = 'PUT';
    const METHOD_PATCH  = 'PATCH';
    const METHOD_DELETE = 'DELETE';

    // Status Codes
    const STATUS_OK                = 200;
    const STATUS_CREATED           = 201;
    const STATUS_NO_CONTENT        = 204;
    const STATUS_BAD_REQUEST       = 400;
    const STATUS_UNAUTHORIZED      = 401;
    const STATUS_FORBIDDEN         = 403;
    const STATUS_NOT_FOUND         = 404;
    const STATUS_INTERNAL_ERROR    = 500;

    // Content Types
    const CONTENT_JSON = 'application/json';
    const CONTENT_HTML = 'text/html';
    const CONTENT_TEXT = 'text/plain';

    // Required method (implementors must provide this)
    public function send(string $method, string $url, array $payload = []): array;
}


// ─────────────────────────────────────────────────────────────────────────────
// A concrete client — inherits all constants from HttpContract
// ─────────────────────────────────────────────────────────────────────────────

class RestApiClient implements HttpContract {
    private array $defaultHeaders = [];

    public function __construct(private string $baseUrl) {
        $this->defaultHeaders['Content-Type'] = self::CONTENT_JSON; // Using inherited constant
    }

    public function send(string $method, string $url, array $payload = []): array {
        $fullUrl = $this->baseUrl . $url;

        // Simulate a response based on method
        echo "[HTTP] {$method} {$fullUrl}\n";

        if (!empty($payload)) {
            echo "       Payload: " . json_encode($payload) . "\n";
        }

        // Simulate response using constants for status codes
        return match($method) {
            self::METHOD_GET    => ['status' => self::STATUS_OK,      'body' => ['data' => 'sample']],
            self::METHOD_POST   => ['status' => self::STATUS_CREATED,  'body' => ['id' => rand(1, 999)]],
            self::METHOD_DELETE => ['status' => self::STATUS_NO_CONTENT,'body' => []],
            default             => ['status' => self::STATUS_BAD_REQUEST,'body' => ['error' => 'Unknown method']],
        };
    }

    // Convenience wrappers — use constants to standardise the method string
    public function get(string $url): array {
        return $this->send(self::METHOD_GET, $url);
    }

    public function post(string $url, array $data): array {
        return $this->send(self::METHOD_POST, $url, $data);
    }

    public function delete(string $url): array {
        return $this->send(self::METHOD_DELETE, $url);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// A response handler — does NOT implement HttpContract
//
// ⚠️  Tempting mistake: `implements HttpContract` just to inherit the constants.
//     That would force a meaningless stub for send(), which is exactly the
//     Interface Segregation violation Lesson 1.0 Example 04 warns about.
//     Constants are accessible by interface name from anywhere — no need to sign
//     a contract you cannot honour.
// ─────────────────────────────────────────────────────────────────────────────

class ResponseHandler {
    public function isSuccess(int $statusCode): bool {
        // Uses constants via the interface name — no `implements` required
        return $statusCode >= HttpContract::STATUS_OK
            && $statusCode <  HttpContract::STATUS_BAD_REQUEST;
    }

    public function describe(int $statusCode): string {
        return match($statusCode) {
            HttpContract::STATUS_OK             => 'OK',
            HttpContract::STATUS_CREATED        => 'Created',
            HttpContract::STATUS_NO_CONTENT     => 'No Content',
            HttpContract::STATUS_BAD_REQUEST    => 'Bad Request',
            HttpContract::STATUS_UNAUTHORIZED   => 'Unauthorized',
            HttpContract::STATUS_NOT_FOUND      => 'Not Found',
            HttpContract::STATUS_INTERNAL_ERROR => 'Internal Server Error',
            default                             => 'Unknown Status',
        };
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Using the classes
// ─────────────────────────────────────────────────────────────────────────────

$client  = new RestApiClient('https://api.example.com');
$handler = new ResponseHandler();

echo "=== GET /users ===\n";
$response = $client->get('/users');
echo "   Status: {$response['status']} — " . $handler->describe($response['status']) . "\n";
echo "   Success: " . ($handler->isSuccess($response['status']) ? 'YES' : 'NO') . "\n\n";

echo "=== POST /users ===\n";
$response = $client->post('/users', ['name' => 'Alice', 'email' => 'alice@example.com']);
echo "   Status: {$response['status']} — " . $handler->describe($response['status']) . "\n";
echo "   New ID: {$response['body']['id']}\n\n";

echo "=== DELETE /users/42 ===\n";
$response = $client->delete('/users/42');
echo "   Status: {$response['status']} — " . $handler->describe($response['status']) . "\n\n";


// ─────────────────────────────────────────────────────────────────────────────
// THREE ways to access interface constants
// ─────────────────────────────────────────────────────────────────────────────

echo "=== Accessing constants three ways ===\n\n";

// 1. Via the interface name directly
echo "1. HttpContract::METHOD_GET   = " . HttpContract::METHOD_GET . "\n";

// 2. Via the implementing class name
echo "2. RestApiClient::STATUS_OK   = " . RestApiClient::STATUS_OK . "\n";

// 3. Via self:: inside an implementing class (seen in RestApiClient above)
// 4. Via an instance of an implementing class (also works, though less common)
echo "3. \$client::CONTENT_JSON      = " . $client::CONTENT_JSON . "\n";


// ─────────────────────────────────────────────────────────────────────────────
// EXPERIMENT: What if you do NOT implement the interface?
// A plain class can still ACCESS interface constants via the interface name.
// ─────────────────────────────────────────────────────────────────────────────

class StandaloneClass {
    public function run(): void {
        // Does not implement HttpContract but can still reference its constants by name
        echo "\nAccessing HttpContract constants without implementing it:\n";
        echo "  Method: "  . HttpContract::METHOD_POST   . "\n";
        echo "  Status: "  . HttpContract::STATUS_NOT_FOUND . "\n";
        echo "  Content: " . HttpContract::CONTENT_JSON  . "\n";
    }
}

(new StandaloneClass())->run();

echo "\n--- Recap ---\n";
echo "1. Interface constants are inherited by implementing classes.\n";
echo "1b. But you do NOT need to implement an interface to read its constants —\n";
echo "    implementing one just to borrow constants is an ISP violation.\n";
echo "2. Access them via InterfaceName::CONST, ClassName::CONST, self::CONST, or an instance.\n";
echo "3. Constants belong in an interface when they are part of the CONTRACT's vocabulary.\n";
echo "4. If a constant is only relevant to ONE class, keep it on that class instead.\n";