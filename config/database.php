<?php
declare(strict_types=1);
return [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => (int)(getenv('DB_PORT') ?: 3306),
    'database' => getenv('DB_DATABASE') ?: 'lifeos',
    'username' => getenv('DB_USERNAME') ?: 'root',
    'password' => getenv('DB_PASSWORD') !== false ? (string)getenv('DB_PASSWORD') : '',
    'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
];
