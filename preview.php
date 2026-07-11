<?php
declare(strict_types=1);

require_once __DIR__ . '/app_loader.php';
tinymaker_connect_require_app('bootstrap.php');

web_require_installed();
if (admin_count() < 1) {
    http_response_code(503);
    exit;
}

$id = clean_string((string)($_GET['id'] ?? ''), 16);
if ($id === '') {
    http_response_code(404);
    exit;
}

$stmt = db()->prepare('SELECT preview_path FROM models WHERE public_id = ? AND status = "published" LIMIT 1');
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row || !$row['preview_path']) {
    http_response_code(404);
    exit;
}

$path = rtrim(config()['storage']['previews'], '/\\') . DIRECTORY_SEPARATOR . $row['preview_path'];
if (!is_file($path)) {
    http_response_code(404);
    exit;
}

$type = mime_content_type($path) ?: 'image/png';
if (!in_array($type, ['image/png', 'image/jpeg'], true)) {
    http_response_code(415);
    exit;
}

header('Content-Type: ' . $type);
header('Content-Length: ' . filesize($path));
readfile($path);
