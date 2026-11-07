<?php
declare(strict_types=1);

/**
 * Execute a MySQL schema file safely.
 *
 * The MySQL PDO driver with PDO::MYSQL_ATTR_MULTI_STATEMENTS set to true
 * allows us to run the whole file in a single exec() call.
 * If any statement fails, PDO throws a PDOException which we catch and
 * return as a readable error message. No statements are silently skipped.
 *
 * @param string $schemaPath Absolute path to the .sql file.
 * @return string|null Error message on failure, null on success.
 */
function run_mysql_schema(string $schemaPath): ?string
{
    if (!file_exists($schemaPath)) {
        return "File schema tidak ditemukan: $schemaPath";
    }

    $pdo = db();
    $sql = file_get_contents($schemaPath);
    if ($sql === false) {
        return "Tidak dapat membaca file schema: $schemaPath";
    }

    try {
        $pdo->exec($sql);
    } catch (Throwable $e) {
        return "Migration error: " . $e->getMessage();
    }
    return null;
}
?>
