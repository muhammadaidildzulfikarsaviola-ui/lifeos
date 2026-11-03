<?php
declare(strict_types=1);

/*
 * Konfigurasi default untuk XAMPP.
 * Bisa dioverride melalui environment variables.
 */
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int) (getenv('DB_PORT') ?: 3306);
$name = getenv('DB_DATABASE') ?: 'lifeos';
$user = getenv('DB_USERNAME') ?: 'root';
$password = getenv('DB_PASSWORD') !== false ? (string) getenv('DB_PASSWORD') : '';
$charset = 'utf8mb4';

if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
    throw new RuntimeException('Nama database tidak valid.');
}

$dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

return new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_PERSISTENT => false,
]);
