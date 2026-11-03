<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Core/Database.php';

use LifeOS\Core\Database;

try {
    $db = Database::connection();

    $result = $db->query(
        'SELECT VERSION() AS version, DATABASE() AS database_name'
    )->fetch();

    echo '<h1>LIFEOS Database Connected!</h1>';
    echo '<pre>';
    print_r($result);
    echo '</pre>';

} catch (Throwable $exception) {
    http_response_code(500);

    echo 'Database connection failed.';
    error_log($exception->getMessage());
}