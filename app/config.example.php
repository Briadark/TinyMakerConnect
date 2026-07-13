<?php
return [
    'db' => [
        'dsn' => 'mysql:host=localhost;dbname=tinymaker_models;charset=utf8mb4',
        'user' => 'tinymaker_models',
        'pass' => 'change-me',
    ],
    'storage' => [
        'models' => __DIR__ . '/../storage/models',
        'previews' => __DIR__ . '/../storage/previews',
        'boot_animations' => __DIR__ . '/../storage/boot_animations',
        'tmp' => __DIR__ . '/../storage/tmp',
    ],
    'limits' => [
        'max_archive_bytes' => 120 * 1024 * 1024,
        'max_preview_bytes' => 2 * 1024 * 1024,
        'max_boot_animation_bytes' => 8 * 1024 * 1024,
    ],
    'security' => [
        // Change this before production. Used only for hashes stored server-side.
        'server_salt' => 'replace-with-a-long-random-secret',
    ],
];
