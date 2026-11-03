<?php

declare(strict_types=1);

function app_config(string $key, mixed $default = null): mixed
{
    static $config = null;

    if ($config === null) {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
    }

    return $config[$key] ?? $default;
}

function e(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}