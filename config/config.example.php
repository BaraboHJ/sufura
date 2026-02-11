<?php

return [
    'db_host' => '127.0.0.1',
    'db_port' => '3306',
    'db_name' => 'sufura',
    'db_user' => 'root',
    'db_pass' => '',

    // Admin-triggered self-update settings.
    'update_zip_url' => 'https://github.com/BaraboHJ/sufura/archive/refs/heads/main.zip',
    'update_exclude_paths' => [
        '.env',
        'config/config.php',
        'uploads',
        'tmp',
    ],
];
