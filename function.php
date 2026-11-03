<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {
        $pdo = require LIFEOS_ROOT . '/config/database.php';
        return $pdo;
    } catch (Throwable $exception) {
        error_log('[LIFEOS][DB] ' . $exception->getMessage());
        throw new RuntimeException('Database LIFEOS belum dapat dihubungkan.', 0, $exception);
    }
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    if ($base === '.' || $base === '/') {
        $base = '';
    }

    $base = preg_replace(
        '~/(dashboard|finance|schedule|tasks|habits|journal|notes|goals|projects|reports)$~',
        '',
        $base
    ) ?? $base;

    return $base . '/' . ltrim($path, '/');
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['_csrf'];
}

function verify_csrf(): void
{
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Permintaan tidak valid atau sesi telah kedaluwarsa. Muat ulang halaman.');
    }
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}
