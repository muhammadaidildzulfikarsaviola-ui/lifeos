<?php
declare(strict_types=1);
return [
    'name' => getenv('APP_NAME') ?: 'LIFEOS',
    'version' => '0.1.0',
    'environment' => getenv('APP_ENV') ?: 'development',
    'debug' => filter_var(getenv('APP_DEBUG') !== false ? getenv('APP_DEBUG') : 'true', FILTER_VALIDATE_BOOLEAN),
    'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Jakarta',
    'locale' => 'id_ID',
];
