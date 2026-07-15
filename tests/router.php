<?php
declare(strict_types=1);

/**
 * Router for the PHP built-in web server, mirroring the rewrite rules
 * in the root .htaccess. Used by CI and local integration testing:
 *
 *   php -S 127.0.0.1:8080 tests/router.php
 */

$root = dirname(__DIR__);
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Mirror the .htaccess deny rules.
if (preg_match('#^/(app|storage|tests)(/|$)#', $uri)
    || preg_match('#^/(README\.md|schema\.sql|app_loader\.php)$#', $uri)) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}

// Mirror the .htaccess rewrites.
if (preg_match('#^/api(/|$)#', $uri)) {
    require $root . '/api.php';
    return true;
}
if (preg_match('#^/(admin|install|health)/?$#', $uri, $m)) {
    require $root . '/' . $m[1] . '.php';
    return true;
}

// Existing files (including direct .php hits) are served by the built-in server.
$file = realpath($root . $uri);
if ($file !== false && is_file($file)) {
    return false;
}

// Everything else falls through to the public site front controller.
require $root . '/index.php';
return true;
