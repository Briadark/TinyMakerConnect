<?php
declare(strict_types=1);

require_once __DIR__ . '/app_loader.php';
tinymaker_connect_require_app('bootstrap.php');

$result = [
    'ok' => true,
    'app' => 'TinyMaker Connect',
    'php' => PHP_VERSION,
    'config_installed' => config_is_installed(),
    'database' => null,
    'admin_ready' => false,
];

if (config_is_installed()) {
    try {
        db()->query('SELECT 1');
        $result['database'] = 'ok';
        $result['admin_ready'] = admin_count() > 0;
    } catch (Throwable $e) {
        $result['ok'] = false;
        $result['database'] = 'error';
    }
} else {
    $result['ok'] = false;
    $result['database'] = 'not_configured';
}

json_response($result, $result['ok'] ? 200 : 503);
