<?php
declare(strict_types=1);












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
