<?php
declare(strict_types=1);

require_once __DIR__ . '/app_loader.php';
tinymaker_connect_require_app('Api.php');

try {
    cors_headers();
    if (request_method() === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    if (!config_is_installed() || admin_count() < 1) {
        error_response('server setup required', 503);
    }

    $path = route_path();
    $method = request_method();
    $parts = array_values(array_filter(explode('/', trim($path, '/'))));

    if ($parts === ['api', 'printers', 'register'] && $method === 'POST') {
        api_register_printer();
    }

    if ($parts === ['api', 'printers', 'me', 'models'] && $method === 'GET') {
        api_list_models(true);
    }

    if ($parts === ['api', 'printers', 'me', 'bookmarks'] && $method === 'GET') {
        api_list_bookmarks();
    }

    if ($parts === ['api', 'leaderboard'] && $method === 'GET') {
        api_leaderboard();
    }

    if ($parts === ['api', 'models'] && $method === 'GET') {
        api_list_models(false);
    }

    if ($parts === ['api', 'models'] && $method === 'POST') {
        api_publish_model();
    }

    if (count($parts) === 3 && $parts[0] === 'api' && $parts[1] === 'models' && $method === 'GET') {
        api_get_model($parts[2]);
    }

    if (count($parts) === 4 && $parts[0] === 'api' && $parts[1] === 'models' && $parts[3] === 'download' && $method === 'GET') {
        api_download_model($parts[2]);
    }

    if (count($parts) === 4 && $parts[0] === 'api' && $parts[1] === 'models' && $parts[3] === 'rating' && $method === 'POST') {
        api_rate_model($parts[2]);
    }

    if (count($parts) === 4 && $parts[0] === 'api' && $parts[1] === 'models' && $parts[3] === 'bookmark' && $method === 'POST') {
        api_bookmark_model($parts[2], true);
    }

    if (count($parts) === 4 && $parts[0] === 'api' && $parts[1] === 'models' && $parts[3] === 'bookmark' && $method === 'DELETE') {
        api_bookmark_model($parts[2], false);
    }

    if (count($parts) === 3 && $parts[0] === 'api' && $parts[1] === 'models' && $method === 'PATCH') {
        api_update_model($parts[2]);
    }

    if (count($parts) === 3 && $parts[0] === 'api' && $parts[1] === 'models' && $method === 'DELETE') {
        api_remove_model($parts[2]);
    }

    error_response('not found', 404);
} catch (Throwable $e) {
    error_response('server error', 500);
}
