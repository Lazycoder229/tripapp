<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Framework\Config\Config;
use Framework\Config\Env;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Security\Csrf;
use Framework\Security\Encrypt;
use Framework\Security\Hash;
use Framework\Security\Jwt;
use Framework\Security\Validator;
use Framework\Session\SessionInterface;
use Framework\Cache\MemoryCache;
use Framework\View\ViewEngine;
use App\Middleware\CsrfMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\EnsureApiKeyMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\SecurityHeadersMiddleware;

echo "\n========================================================\n";
echo "       RUNNING COMPREHENSIVE SECURITY TEST SUITE        \n";
echo "========================================================\n\n";

// Set test environment variables
$appKey = 'base64:' . base64_encode(random_bytes(32));
$_ENV['APP_KEY'] = $appKey;
$_ENV['JWT_SECRET'] = 'super-secret-jwt-signing-key-minimum-32-chars';
$_ENV['API_KEY'] = 'test-api-secret-key-12345';

// Mock Session implementation for testing
$mockSession = new class implements SessionInterface {
    private array $data = [];
    public function start(): void {}
    public function get(string $key, mixed $default = null): mixed { return $this->data[$key] ?? $default; }
    public function set(string $key, mixed $value): void { $this->data[$key] = $value; }
    public function has(string $key): bool { return isset($this->data[$key]); }
    public function remove(string $key): void { unset($this->data[$key]); }
    public function all(): array { return $this->data; }
    public function flash(string $key, mixed $value): void {}
    public function getFlash(string $key, mixed $default = null): mixed { return $default; }
    public function regenerate(): void {}
    public function destroy(): void { $this->data = []; }
};

// -------------------------------------------------------------
// 1. TEST CSRF PROTECTION
// -------------------------------------------------------------
echo "1. Testing CSRF Token Generation & Verification...";

$csrf = new Csrf($mockSession);
$token = $csrf->token();
assert(is_string($token) && strlen($token) === 64, 'CSRF token must be 64-character hex string');
assert($csrf->verify($token) === true, 'CSRF verify must pass for valid token');
assert($csrf->verify('invalid-token') === false, 'CSRF verify must fail for invalid token');
assert($csrf->verify('') === false, 'CSRF verify must fail for empty token');
assert($csrf->verify(null) === false, 'CSRF verify must fail for null token');

// Test CsrfMiddleware
$csrfMiddleware = new CsrfMiddleware($csrf);

// Safe methods should pass without CSRF token
$getRequest = new Request([], [], ['REQUEST_METHOD' => 'GET']);
$res = $csrfMiddleware->handle($getRequest, fn($r) => Response::json(['ok' => true]));
assert($res->getStatusCode() === 200, 'GET request must pass CSRF without token');

// POST without token should be blocked (419)
$postNoToken = new Request([], [], ['REQUEST_METHOD' => 'POST']);
$res = $csrfMiddleware->handle($postNoToken, fn($r) => Response::json(['ok' => true]));
assert($res->getStatusCode() === 419, 'POST without CSRF token must return 419');

// POST with valid token in body should pass
$postBodyToken = new Request([], ['_csrf' => $token], ['REQUEST_METHOD' => 'POST']);
$res = $csrfMiddleware->handle($postBodyToken, fn($r) => Response::json(['ok' => true]));
assert($res->getStatusCode() === 200, 'POST with valid _csrf body must return 200');

// POST with valid token in header should pass
$postHeaderToken = new Request([], [], ['REQUEST_METHOD' => 'POST'], ['x-csrf-token' => $token]);
$res = $csrfMiddleware->handle($postHeaderToken, fn($r) => Response::json(['ok' => true]));
assert($res->getStatusCode() === 200, 'POST with valid X-CSRF-Token header must return 200');

echo " PASSED\n";


// -------------------------------------------------------------
// 2. TEST AUTHENTICATED ENCRYPTION (AES-256-GCM)
// -------------------------------------------------------------
echo "2. Testing Authenticated Encryption (AES-256-GCM)...";

$encryptor = new Encrypt(base64_decode(substr($appKey, 7)));
$plainText = "Confidential customer payload 1234-5678-9012";
$encrypted = $encryptor->encrypt($plainText);

assert($encrypted !== $plainText, 'Ciphertext must not match plaintext');
assert($encryptor->decrypt($encrypted) === $plainText, 'Decryption must recover exact plaintext');

// Test tampering detection
$tampered = base64_encode(substr(base64_decode($encrypted), 0, -2) . 'XX');
$tamperFailed = false;
try {
    $encryptor->decrypt($tampered);
} catch (\Framework\Exception\DecryptException) {
    $tamperFailed = true;
}
assert($tamperFailed === true, 'Tampered ciphertext must throw DecryptException');

echo " PASSED\n";


// -------------------------------------------------------------
// 3. TEST PASSWORD HASHING (BCRYPT)
// -------------------------------------------------------------
echo "3. Testing Password Hashing (BCrypt)...";

$password = "MySecur3P@ssw0rd!";
$hash = Hash::make($password);

assert(str_starts_with($hash, '$2y$'), 'BCrypt hash must start with $2y$');
assert(Hash::verify($password, $hash) === true, 'Hash verify must pass for matching password');
assert(Hash::verify('WrongPassword', $hash) === false, 'Hash verify must fail for incorrect password');
assert(Hash::needsRehash($hash) === false, 'Hash with current cost should not need rehash');

echo " PASSED\n";


// -------------------------------------------------------------
// 4. TEST JWT (HMAC-SHA256 SIGNING & EXPIRY)
// -------------------------------------------------------------
echo "4. Testing JWT HS256 Token Signing & Expiry...";

$jwt = new Jwt('test-jwt-secret-key-that-is-very-long', defaultTtl: 3600);
$token = $jwt->encode(['user_id' => 42, 'role' => 'admin']);

$decoded = $jwt->decode($token);
assert($decoded['user_id'] === 42, 'Decoded user_id mismatch');
assert($decoded['role'] === 'admin', 'Decoded role mismatch');
assert(isset($decoded['exp']), 'Expiry claim must be set');

// Test Bearer header parsing
$decodedBearer = $jwt->decode("Bearer {$token}");
assert($decodedBearer['user_id'] === 42, 'Bearer prefix token must be decoded properly');

// Test Signature Tampering
$tokenParts = explode('.', $token);
$tamperedPayload = base64_encode(json_encode(['user_id' => 42, 'role' => 'superadmin']));
$tamperedToken = $tokenParts[0] . '.' . $tamperedPayload . '.' . $tokenParts[2];
$jwtTamperCaught = false;
try {
    $jwt->decode($tamperedToken);
} catch (\Framework\Exception\InvalidTokenException) {
    $jwtTamperCaught = true;
}
assert($jwtTamperCaught === true, 'Tampered JWT signature must be rejected');

// Test Expired Token
$expiredJwt = new Jwt('test-jwt-secret-key-that-is-very-long', defaultTtl: -10);
$expiredToken = $expiredJwt->encode(['user_id' => 1]);
$expiredCaught = false;
try {
    $expiredJwt->decode($expiredToken);
} catch (\Framework\Exception\InvalidTokenException) {
    $expiredCaught = true;
}
assert($expiredCaught === true, 'Expired JWT token must throw InvalidTokenException');

echo " PASSED\n";


// -------------------------------------------------------------
// 5. TEST INPUT VALIDATOR & SQL INJECTION PROTECTION
// -------------------------------------------------------------
echo "5. Testing Input Validation & Identifier Sanitization...";

$validator = Validator::make([
    'email' => 'user@example.com',
    'age' => '25',
    'ip' => '192.168.1.1',
    'password' => 'secret123',
    'password_confirmation' => 'secret123',
], [
    'email' => 'required|email',
    'age' => 'required|integer|min:18',
    'ip' => 'required|ipv4',
    'password' => 'required|confirmed',
]);

assert($validator->passes() === true, 'Valid payload must pass validation');

// Test Invalid Data
$badValidator = Validator::make([
    'email' => 'not-an-email',
    'age' => '15',
    'ip' => '999.999.999.999',
    'password' => 'secret123',
    'password_confirmation' => 'mismatch',
], [
    'email' => 'required|email',
    'age' => 'required|integer|min:18',
    'ip' => 'required|ipv4',
    'password' => 'required|confirmed',
]);

assert($badValidator->fails() === true, 'Bad payload must fail validation');
$errors = $badValidator->errors();
assert(isset($errors['email']), 'Email error missing');
assert(isset($errors['age']), 'Age error missing');
assert(isset($errors['ip']), 'IP error missing');
assert(isset($errors['password']), 'Password confirmed error missing');

echo " PASSED\n";


// -------------------------------------------------------------
// 6. TEST CORS SECURITY & CREDENTIALS ISOLATION
// -------------------------------------------------------------
echo "6. Testing CORS Policy & Credential Isolation...";

$corsMiddleware = new CorsMiddleware();

// Test Preflight OPTIONS
$optionsRequest = new Request([], [], ['REQUEST_METHOD' => 'OPTIONS'], ['origin' => 'https://example.com']);
$optionsRes = $corsMiddleware->handle($optionsRequest, fn($r) => Response::json([]));
assert($optionsRes->getStatusCode() === 204, 'OPTIONS preflight must return 204');
assert($optionsRes->getHeader('Access-Control-Allow-Origin') === '*', 'Wildcard origin should return * when credentials false');
assert($optionsRes->getHeader('Access-Control-Allow-Headers') !== null, 'Allowed headers must be present');

echo " PASSED\n";


// -------------------------------------------------------------
// 7. TEST SECURITY HEADERS
// -------------------------------------------------------------
echo "7. Testing Security Headers Middleware...";

$securityHeadersMiddleware = new SecurityHeadersMiddleware();
$request = new Request([], [], ['REQUEST_METHOD' => 'GET', 'HTTPS' => 'on']);
$res = $securityHeadersMiddleware->handle($request, fn($r) => Response::json(['status' => 'ok']));

assert($res->getHeader('X-Content-Type-Options') === 'nosniff', 'X-Content-Type-Options header missing');
assert($res->getHeader('X-Frame-Options') === 'DENY', 'X-Frame-Options header missing');
assert($res->getHeader('X-XSS-Protection') === '0', 'X-XSS-Protection header missing');
assert($res->getHeader('Referrer-Policy') === 'strict-origin-when-cross-origin', 'Referrer-Policy header missing');
assert($res->getHeader('Strict-Transport-Security') !== null, 'HSTS header missing on HTTPS');

echo " PASSED\n";


// -------------------------------------------------------------
// 8. TEST API KEY AUTHENTICATION
// -------------------------------------------------------------
echo "8. Testing EnsureApiKeyMiddleware (Constant-Time Auth)...";

$apiKeyMiddleware = new EnsureApiKeyMiddleware();

// Unauthenticated request should return 401
$unauthReq = new Request([], [], ['REQUEST_METHOD' => 'GET']);
$unauthRes = $apiKeyMiddleware->handle($unauthReq, fn($r) => Response::json(['ok' => true]));
assert($unauthRes->getStatusCode() === 401, 'Request without API key must return 401');

// Header X-API-Key should authenticate
$authReq = new Request([], [], ['REQUEST_METHOD' => 'GET'], ['x-api-key' => 'test-api-secret-key-12345']);
$authRes = $apiKeyMiddleware->handle($authReq, fn($r) => Response::json(['ok' => true]));
assert($authRes->getStatusCode() === 200, 'Request with valid X-API-Key must return 200');

// Bearer token should authenticate
$authBearerReq = new Request([], [], ['REQUEST_METHOD' => 'GET'], ['authorization' => 'Bearer test-api-secret-key-12345']);
$authBearerRes = $apiKeyMiddleware->handle($authBearerReq, fn($r) => Response::json(['ok' => true]));
assert($authBearerRes->getStatusCode() === 200, 'Request with valid Bearer API key must return 200');

echo " PASSED\n";


// -------------------------------------------------------------
// 9. TEST RATE LIMITING
// -------------------------------------------------------------
echo "9. Testing RateLimitMiddleware...";

$cache = new class implements \Framework\Session\CacheInterface {
    private array $store = [];
    public function get(string $key, mixed $default = null): mixed { return $this->store[$key] ?? $default; }
    public function set(string $key, mixed $value, ?int $ttl = null): bool { $this->store[$key] = $value; return true; }
    public function has(string $key): bool { return isset($this->store[$key]); }
    public function forget(string $key): bool { unset($this->store[$key]); return true; }
    public function remember(string $key, ?int $ttl, callable $callback): mixed {
        if ($this->has($key)) return $this->get($key);
        $val = $callback();
        $this->set($key, $val, $ttl);
        return $val;
    }
    public function flush(): bool { $this->store = []; return true; }
};
$rateLimiter = new RateLimitMiddleware($cache);

$clientReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REMOTE_ADDR' => '10.0.0.1', 'REQUEST_URI' => '/api/test']);

// Request should pass and attach rate limit headers
$rateRes = $rateLimiter->handle($clientReq, fn($r) => Response::json(['ok' => true]));
assert($rateRes->getStatusCode() === 200, 'Initial rate limited request must succeed');
assert($rateRes->getHeader('X-RateLimit-Limit') !== null, 'X-RateLimit-Limit header missing');
assert($rateRes->getHeader('X-RateLimit-Remaining') !== null, 'X-RateLimit-Remaining header missing');

echo " PASSED\n";


// -------------------------------------------------------------
// 10. TEST XSS PROTECTION IN VIEW ENGINE
// -------------------------------------------------------------
echo "10. Testing XSS Protection & Escaping in View Engine...";

$testViewsDir = dirname(__DIR__) . '/storage/test_sec_views';
$testCacheDir = dirname(__DIR__) . '/storage/cache/test_sec_views';
if (!is_dir($testViewsDir)) mkdir($testViewsDir, 0775, true);
if (!is_dir($testCacheDir)) mkdir($testCacheDir, 0775, true);

$viewEngine = new ViewEngine($testViewsDir, $testCacheDir);
file_put_contents($testViewsDir . '/xss.php', "User: {{ \$input }}");

$xssPayload = '<script>alert("XSS")</script>';
$rendered = $viewEngine->render('xss', ['input' => $xssPayload]);

assert(!str_contains($rendered, '<script>alert("XSS")</script>'), 'Raw XSS script must NOT be rendered');
assert(str_contains($rendered, '&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;'), 'XSS payload must be HTML-escaped');

// Cleanup
@unlink($testViewsDir . '/xss.php');
foreach (glob($testCacheDir . '/*.php') ?: [] as $f) @unlink($f);
@rmdir($testViewsDir);
@rmdir($testCacheDir);

echo " PASSED\n";


echo "\n========================================================\n";
echo "        ALL 10 SECURITY TEST SUITES PASSED!            \n";
echo "========================================================\n\n";
