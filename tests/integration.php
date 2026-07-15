<?php
declare(strict_types=1);

/**
 * End-to-end API contract tests against a running server and MySQL database.
 * Exercises the installer, printer registration/reclaim, token auth,
 * throttling and the admin login flow.
 *
 * Requires a clean checkout (no app/config.php) and an empty database.
 *
 *   php -S 127.0.0.1:8080 tests/router.php &
 *   BASE_URL=http://127.0.0.1:8080 DB_HOST=127.0.0.1 DB_NAME=tinymaker \
 *   DB_USER=root DB_PASS=root php tests/integration.php
 */

$baseUrl = rtrim((string)getenv('BASE_URL') ?: 'http://127.0.0.1:8080', '/');
$dbHost = (string)getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = (string)getenv('DB_PORT') ?: '3306';
$dbName = (string)getenv('DB_NAME') ?: 'tinymaker';
$dbUser = (string)getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') === false ? '' : (string)getenv('DB_PASS');

$configPath = dirname(__DIR__) . '/app/config.php';
if (is_file($configPath)) {
    fwrite(STDERR, "Refusing to run: app/config.php already exists. These tests install a fresh server.\n");
    exit(1);
}

$failures = 0;
$cookies = [];

function check(string $label, $expected, $actual): void
{
    global $failures;
    if ($expected === $actual) {
        echo "ok   $label\n";
        return;
    }
    $failures++;
    echo "FAIL $label\n     expected: " . var_export($expected, true) . "\n     actual:   " . var_export($actual, true) . "\n";
}

function check_contains(string $label, string $needle, string $haystack): void
{
    global $failures;
    if (strpos($haystack, $needle) !== false) {
        echo "ok   $label\n";
        return;
    }
    $failures++;
    echo "FAIL $label\n     expected body to contain: $needle\n     body starts: " . substr($haystack, 0, 200) . "\n";
}

function http_call(string $method, string $path, array $opts = []): array
{
    global $baseUrl, $cookies;
    $headers = $opts['headers'] ?? [];
    $content = null;
    if (isset($opts['form'])) {
        $content = http_build_query($opts['form']);
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }
    if ($cookies !== [] && empty($opts['no_cookies'])) {
        $pairs = [];
        foreach ($cookies as $name => $value) {
            $pairs[] = $name . '=' . $value;
        }
        $headers[] = 'Cookie: ' . implode('; ', $pairs);
    }
    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'content' => $content,
        'ignore_errors' => true,
        'follow_location' => 0,
        'timeout' => 20,
    ]]);
    $body = file_get_contents($baseUrl . $path, false, $context);
    if ($body === false) {
        fwrite(STDERR, "Could not reach $baseUrl$path — is the test server running?\n");
        exit(1);
    }
    $status = 0;
    $location = '';
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) {
            $status = (int)$m[1];
        } elseif (stripos($line, 'Set-Cookie:') === 0 && preg_match('/Set-Cookie:\s*([^=]+)=([^;]*)/i', $line, $m)) {
            $cookies[trim($m[1])] = trim($m[2]);
        } elseif (stripos($line, 'Location:') === 0) {
            $location = trim(substr($line, 9));
        }
    }
    return ['status' => $status, 'body' => (string)$body, 'location' => $location];
}

function json_body(array $response): array
{
    $data = json_decode($response['body'], true);
    return is_array($data) ? $data : [];
}

function login_csrf(): string
{
    $page = http_call('GET', '/admin.php');
    if (!preg_match('/name="csrf" value="([a-f0-9]+)"/', $page['body'], $m)) {
        fwrite(STDERR, "Could not find CSRF token on admin login page.\n");
        exit(1);
    }
    return $m[1];
}

// --- Fresh server: health reports not configured ---------------------------
$r = http_call('GET', '/health.php');
check('health: fresh server returns 503', 503, $r['status']);
check('health: database not configured', 'not_configured', json_body($r)['database'] ?? null);

$r = http_call('GET', '/api/models');
check('api: refuses requests before setup', 503, $r['status']);

// --- Installer: MySQL step --------------------------------------------------
$storageBase = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tinymaker-test-storage-' . getmypid();
$r = http_call('POST', '/install.php', ['form' => [
    'step' => 'mysql',
    'db_host' => $dbHost,
    'db_port' => $dbPort,
    'db_name' => $dbName,
    'db_user' => $dbUser,
    'db_pass' => $dbPass,
    'storage_base' => $storageBase,
]]);
check('installer: mysql step redirects to admin step', 302, $r['status']);
check('installer: wrote app/config.php', true, is_file($configPath));
$configBefore = (string)file_get_contents($configPath);

// --- Regression: installer must refuse to reconfigure an installed server ---
$r = http_call('POST', '/install.php', ['form' => [
    'step' => 'mysql',
    'db_host' => 'attacker.example',
    'db_port' => '3306',
    'db_name' => 'evil',
    'db_user' => 'evil',
    'db_pass' => 'evil',
    'storage_base' => $storageBase,
]]);
check_contains('installer: rejects mysql step when already configured', 'already configured', $r['body']);
check('installer: config.php unchanged after re-run attempt', $configBefore, (string)file_get_contents($configPath));

// --- Installer: first admin -------------------------------------------------
$adminPassword = 'correct-horse-battery';
$r = http_call('POST', '/install.php', ['form' => [
    'step' => 'admin',
    'username' => 'admin',
    'password' => $adminPassword,
    'password_confirm' => $adminPassword,
]]);
check('installer: admin step redirects to admin.php', 302, $r['status']);

$r = http_call('GET', '/health.php');
check('health: configured server returns 200', 200, $r['status']);
check('health: admin ready', true, json_body($r)['admin_ready'] ?? null);

$r = http_call('GET', '/install.php');
check('installer: redirects to admin once installed', 302, $r['status']);

// --- Static protections (router mirrors .htaccess) ---------------------------
$r = http_call('GET', '/app/config.php');
check('deny: app/ is not web accessible', 403, $r['status']);
$r = http_call('GET', '/tests/integration.php');
check('deny: tests/ is not web accessible', 403, $r['status']);

// --- Printer registration ----------------------------------------------------
$hardwareId = 'TEST_' . bin2hex(random_bytes(8));
$r = http_call('POST', '/api/printers/register', ['form' => [
    'hardware_id' => $hardwareId,
    'firmware_version' => '0.10.0',
    'printer_name' => 'CI Printer',
]]);
check('register: new printer returns 201', 201, $r['status']);
$printer = json_body($r);
check('register: response ok', true, $printer['ok'] ?? null);
check('register: publish token is 64 hex chars', 1, preg_match('/^[a-f0-9]{64}$/', (string)($printer['publish_token'] ?? '')));
check('register: recovery code is 64 hex chars', 1, preg_match('/^[a-f0-9]{64}$/', (string)($printer['recovery_code'] ?? '')));
$publishToken = (string)$printer['publish_token'];
$recoveryCode = (string)$printer['recovery_code'];
$publicId = (string)($printer['printer_public_id'] ?? '');

$r = http_call('POST', '/api/printers/lookup', ['form' => ['hardware_id' => $hardwareId]]);
check('lookup: printer is known', true, json_body($r)['known'] ?? null);
check('lookup: returns same public id', $publicId, json_body($r)['printer_public_id'] ?? null);

$r = http_call('POST', '/api/printers/register', ['form' => ['hardware_id' => $hardwareId]]);
check('register: known printer without token returns 409', 409, $r['status']);
check('register: reclaim_required flag set', true, json_body($r)['reclaim_required'] ?? null);

$r = http_call('POST', '/api/printers/register', ['form' => [
    'hardware_id' => $hardwareId,
    'publish_token' => $publishToken,
    'firmware_version' => '0.10.1',
]]);
check('register: known printer with token returns 200', 200, $r['status']);
check('register: keeps same public id', $publicId, json_body($r)['printer_public_id'] ?? null);

// --- Token authentication -----------------------------------------------------
$r = http_call('GET', '/api/printers/me/models', ['headers' => ['X-TinyMaker-Token: ' . $publishToken]]);
check('auth: header token accepted', 200, $r['status']);
check('auth: own models list is empty array', [], json_body($r)['items'] ?? null);

$r = http_call('GET', '/api/printers/me/models', ['headers' => ['X-TinyMaker-Token: ' . str_repeat('0', 64)]]);
check('auth: invalid token rejected', 401, $r['status']);

// Regression: tokens in the query string are no longer accepted.
$r = http_call('GET', '/api/printers/me/models?publish_token=' . $publishToken);
check('auth: query-string token rejected', 401, $r['status']);

$r = http_call('GET', '/api/models');
check('api: public model list ok', 200, $r['status']);
check('api: public model list empty', [], json_body($r)['items'] ?? null);

// --- Reclaim and throttling -----------------------------------------------------
$r = http_call('POST', '/api/printers/reclaim', ['form' => [
    'hardware_id' => $hardwareId,
    'recovery_code' => str_repeat('f', 64),
]]);
check('reclaim: wrong recovery code rejected', 401, $r['status']);

$r = http_call('POST', '/api/printers/reclaim', ['form' => [
    'hardware_id' => $hardwareId,
    'recovery_code' => $recoveryCode,
]]);
check('reclaim: correct recovery code accepted', 200, $r['status']);
check('reclaim: returns publish token', $publishToken, json_body($r)['publish_token'] ?? null);

for ($i = 0; $i < 10; $i++) {
    $r = http_call('POST', '/api/printers/reclaim', ['form' => [
        'hardware_id' => $hardwareId,
        'recovery_code' => str_repeat('e', 64),
    ]]);
}
check('reclaim: repeated failures still return 401', 401, $r['status']);
$r = http_call('POST', '/api/printers/reclaim', ['form' => [
    'hardware_id' => $hardwareId,
    'recovery_code' => $recoveryCode,
]]);
check('reclaim: throttled after repeated failures', 429, $r['status']);

// --- Admin login flow -------------------------------------------------------------
$csrf = login_csrf();
$r = http_call('POST', '/admin.php', ['form' => [
    'action' => 'login',
    'csrf' => $csrf,
    'username' => 'admin',
    'password' => $adminPassword,
]]);
check('admin: login with valid credentials redirects', 302, $r['status']);
$r = http_call('GET', '/admin.php');
check_contains('admin: dashboard shows logged-in user', 'Logged in as', $r['body']);
if (!preg_match('/name="csrf" value="([a-f0-9]+)"/', $r['body'], $m)) {
    fwrite(STDERR, "Could not find CSRF token on admin dashboard.\n");
    exit(1);
}
$r = http_call('POST', '/admin.php', ['form' => ['action' => 'logout', 'csrf' => $m[1]]]);
check('admin: logout redirects', 302, $r['status']);
$r = http_call('GET', '/admin.php');
check_contains('admin: logged out back to login form', 'Login to manage', $r['body']);

// Regression: login without a CSRF token is refused.
$r = http_call('POST', '/admin.php', ['form' => [
    'action' => 'login',
    'username' => 'admin',
    'password' => $adminPassword,
]]);
check_contains('admin: login without csrf rejected', 'session expired', strtolower($r['body']));

// Regression: repeated failed logins are throttled.
for ($i = 0; $i < 5; $i++) {
    $csrf = login_csrf();
    $r = http_call('POST', '/admin.php', ['form' => [
        'action' => 'login',
        'csrf' => $csrf,
        'username' => 'admin',
        'password' => 'wrong-password-' . $i,
    ]]);
    check_contains('admin: wrong password attempt ' . ($i + 1) . ' rejected', 'Invalid username or password', $r['body']);
}
$csrf = login_csrf();
$r = http_call('POST', '/admin.php', ['form' => [
    'action' => 'login',
    'csrf' => $csrf,
    'username' => 'admin',
    'password' => $adminPassword,
]]);
check_contains('admin: login throttled after repeated failures', 'Too many failed login attempts', $r['body']);

// --- Result -----------------------------------------------------------------------
if ($failures > 0) {
    echo "\n$failures integration test(s) failed.\n";
    exit(1);
}
echo "\nAll integration tests passed.\n";
