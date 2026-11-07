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

function request_string(mixed $value, string $default = ''): string
{
    return is_string($value) ? $value : $default;
}

function request_integer(mixed $value, int $default = 0): int
{
    if (is_int($value)) {
        return $value;
    }
    if (!is_string($value) || !preg_match('/^-?\d+$/D', $value)) {
        return $default;
    }
    $validated = filter_var($value, FILTER_VALIDATE_INT);
    return $validated === false ? $default : $validated;
}

function request_date(mixed $value, string $default = ''): string
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
        return $default;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if ($date === false
        || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        || $date->format('Y-m-d') !== $value) {
        return $default;
    }
    return $value;
}

function url(string $path = ''): string
{
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    if ($base === '.' || $base === '/') {
        $base = '';
    }

    $base = preg_replace(
        '~/?\b(dashboard|finance|schedule|tasks|habits|journal|notes|goals|projects|reports)$~',
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

function set_flash(string $type, string $message): void
{
    $_SESSION['_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function get_flash(): ?array
{
    $flash = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    return is_array($flash) ? $flash : null;
}

function format_rupiah(mixed $amount, bool $prefix = true): string
{
    $num = (float) ($amount ?? 0);
    $formatted = number_format(abs($num), 0, ',', '.');
    $sign = $num < 0 ? '-' : '';
    return $sign . ($prefix ? 'Rp ' : '') . $formatted;
}
