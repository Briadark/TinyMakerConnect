<?php
declare(strict_types=1);

function tinymaker_connect_require_app(string $file): void
{
    $candidates = [
        __DIR__ . '/app/' . $file,
        dirname(__DIR__) . '/app/' . $file,
    ];

    foreach ($candidates as $path) {
        if (is_file($path)) {
            require_once $path;
            return;
        }
    }

    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'TinyMaker Connect app folder not found. Upload app/ beside the public PHP files or one level above them.';
    exit;
}
