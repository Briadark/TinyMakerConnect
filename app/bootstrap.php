<?php
declare(strict_types=1);

require_once __DIR__ . '/Migrations.php';

function cors_headers(): void
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-TinyMaker-Token');
}

function config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $path = __DIR__ . '/config.php';
    if (!is_file($path)) {
        $path = __DIR__ . '/config.example.php';
    }
    $config = require $path;
    return $config;
}

function config_path(): string
{
    return __DIR__ . '/config.php';
}

function config_is_installed(): bool
{
    return is_file(config_path());
}

function pdo_from_config(array $config): PDO
{
    $db = $config['db'];
    return new PDO($db['dsn'], $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = pdo_from_config(config());
    migrate_database($pdo);
    return $pdo;
}

function ensure_storage(): void
{
    foreach (config()['storage'] as $path) {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    cors_headers();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function error_response(string $message, int $status): void
{
    json_response(['ok' => false, 'error' => $message], $status);
}

function request_method(): string
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($method === 'POST' && isset($_POST['_method'])) {
        $method = strtoupper((string)$_POST['_method']);
    }
    return $method;
}

function route_path(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $script = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    if ($script !== '/' && str_starts_with($uri, $script)) {
        $uri = substr($uri, strlen($script));
    }
    return '/' . trim($uri, '/');
}

function public_id(int $bytes = 8): string
{
    return bin2hex(random_bytes($bytes));
}

function token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

function clean_string(string $value, int $maxLen): string
{
    $value = trim($value);
    $value = preg_replace('/[[:cntrl:]]/', '', $value) ?? '';
    if (strlen($value) > $maxLen) {
        $value = substr($value, 0, $maxLen);
    }
    return $value;
}

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect_to(string $path): void
{
    header('Location: ' . $path, true, 302);
    exit;
}

function web_require_installed(): void
{
    if (!config_is_installed()) {
        redirect_to('/install.php');
    }
}

function table_exists(string $table): bool
{
    try {
        $stmt = db()->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function admin_count(): int
{
    if (!table_exists('admins')) {
        return 0;
    }
    return (int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
}

function require_printer(): array
{
    $token = printer_token_from_request();
    $token = trim((string)$token);
    if ($token === '') {
        error_response('missing publish token', 401);
    }

    $stmt = db()->prepare('SELECT * FROM printers WHERE publish_token = ? LIMIT 1');
    $stmt->execute([$token]);
    $printer = $stmt->fetch();
    if (!$printer) {
        error_response('invalid publish token', 401);
    }
    if ((int)$printer['blocked'] === 1) {
        error_response('printer blocked', 403);
    }
    return $printer;
}

function optional_printer(): ?array
{
    $token = trim((string)printer_token_from_request());
    if ($token === '') {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM printers WHERE publish_token = ? LIMIT 1');
    $stmt->execute([$token]);
    $printer = $stmt->fetch();
    if (!$printer || (int)$printer['blocked'] === 1) {
        return null;
    }
    return $printer;
}

function printer_token_from_request(): string
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    return (string)($headers['X-TinyMaker-Token'] ?? $headers['x-tinymaker-token'] ?? ($_POST['publish_token'] ?? ($_GET['publish_token'] ?? '')));
}

function model_to_api(array $row): array
{
    return [
        'public_id' => $row['public_id'],
        'model_name' => $row['model_name'],
        'original_credits' => $row['original_credits'],
        'layers' => (int)$row['layers'],
        'height_mm' => (float)$row['height_mm'],
        'resin_ml' => $row['resin_ml'] === null ? null : (float)$row['resin_ml'],
        'file_size' => (int)$row['file_size'],
        'download_count' => (int)($row['download_count'] ?? 0),
        'rating_count' => (int)($row['rating_count'] ?? 0),
        'rating_average' => (int)($row['rating_count'] ?? 0) > 0 ? round((int)$row['rating_sum'] / (int)$row['rating_count'], 2) : null,
        'bookmark_count' => (int)($row['bookmark_count'] ?? 0),
        'checksum_sha256' => $row['checksum_sha256'],
        'preview_url' => $row['preview_path'] ? '/preview.php?id=' . rawurlencode($row['public_id']) : null,
        'download_url' => '/api/models/' . rawurlencode($row['public_id']) . '/download',
        'status' => $row['status'],
        'created_at' => $row['created_at'],
    ];
}

function ip_hash(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return hash('sha256', config()['security']['server_salt'] . '|' . $ip);
}
