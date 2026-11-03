<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Core/helpers.php';

date_default_timezone_set(app_config('timezone', 'Asia/Jakarta'));

$appName = app_config('name', 'LIFEOS');
$version = app_config('version', '0.1.0');

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($appName) ?></title>
</head>
<body>
    <main>
        <h1><?= e($appName) ?></h1>
        <p>Personal Life Management System</p>
        <small>Version <?= e($version) ?></small>
    </main>
</body>
</html>