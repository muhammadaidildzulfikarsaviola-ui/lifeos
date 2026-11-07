<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}

$options = getopt('', ['backup:', 'resume']);
$backupPath = isset($options['backup']) ? realpath((string)$options['backup']) : false;
$migrationName = '20261107_01_schedule_completion';
$migrationFile = __DIR__ . '/20261107_01_schedule_completion.sql';
$recorded = false;

try {
    if ($backupPath === false || !is_file($backupPath) || filesize($backupPath) < 5000) {
        throw new RuntimeException('Berikan --backup dengan dump MariaDB penuh yang sudah dibuat sebelum migration.');
    }
    $backupText = file_get_contents($backupPath, false, null, 0, 4096);
    if ($backupText === false || !preg_match('/-- (?:MariaDB|MySQL) dump/i', $backupText)) {
        throw new RuntimeException('File backup tidak dikenali sebagai dump MariaDB/MySQL.');
    }
    if (!is_file($migrationFile)) throw new RuntimeException('File migration Schedule tidak ditemukan.');

    $pdo = require dirname(__DIR__, 2) . '/config/database.php';
    $identity = $pdo->query('SELECT DATABASE() AS db_name, VERSION() AS server_version')->fetch(PDO::FETCH_ASSOC);
    if (($identity['db_name'] ?? null) !== 'lifeos') throw new RuntimeException('Target database bukan lifeos; migration dibatalkan.');
    if (!str_contains((string)$identity['server_version'], '10.4.32-MariaDB')) throw new RuntimeException('Server harus MariaDB 10.4.32; ditemukan ' . (string)$identity['server_version']);
    if (filemtime($backupPath) >= time()) throw new RuntimeException('Backup harus sudah selesai dibuat sebelum migration.');

    $dbName = (string)$identity['db_name'];
    $columnType = static function (PDO $pdo, string $table, string $column, string $dbName): ?string {
        $stmt = $pdo->prepare('SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=:schema AND TABLE_NAME=:table AND COLUMN_NAME=:column');
        $stmt->execute(['schema'=>$dbName, 'table'=>$table, 'column'=>$column]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : strtolower((string)$value);
    };
    foreach ([['schedule_events','id'], ['schedule_categories','id'], ['tasks','id'], ['schedule_events','category_id']] as [$table,$column]) {
        if ($columnType($pdo,$table,$column,$dbName) !== 'bigint(20) unsigned') throw new RuntimeException("Kolom {$table}.{$column} tidak cocok dengan schema yang diaudit; migration dibatalkan.");
    }
    $categoryFk = $pdo->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA='lifeos' AND TABLE_NAME='schedule_events' AND COLUMN_NAME='category_id' AND REFERENCED_TABLE_NAME='schedule_categories' AND REFERENCED_COLUMN_NAME='id'")->fetchColumn();
    if ((int)$categoryFk !== 1) throw new RuntimeException('Relasi kategori Schedule berbeda dari dump yang diaudit.');

    $sql = file_get_contents($migrationFile);
    if ($sql === false) throw new RuntimeException('Gagal membaca migration.');
    $checksum = hash('sha256', $sql);
    $existingTables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='lifeos' AND TABLE_TYPE='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    $beforeCounts = [];
    foreach ($existingTables as $table) {
        $safeTable = str_replace('`', '``', (string)$table);
        $beforeCounts[$table] = (int)$pdo->query("SELECT COUNT(*) FROM `{$safeTable}`")->fetchColumn();
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
        migration_name VARCHAR(190) NOT NULL,
        checksum CHAR(64) NOT NULL,
        status ENUM('running','applied','failed') NOT NULL DEFAULT 'running',
        error_message TEXT NULL,
        started_at DATETIME NOT NULL,
        finished_at DATETIME NULL,
        PRIMARY KEY (migration_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $ledger = $pdo->prepare('SELECT checksum,status FROM schema_migrations WHERE migration_name=:name');
    $ledger->execute(['name'=>$migrationName]);
    $state = $ledger->fetch(PDO::FETCH_ASSOC);
    if ($state && !hash_equals((string)$state['checksum'], $checksum)) throw new RuntimeException('Checksum migration berubah setelah pencatatan; perlu audit manual.');
    if (($state['status'] ?? null) === 'applied') {
        echo "Migration sudah tercatat applied; tidak dijalankan ulang.\n";
        exit(0);
    }
    if ($state && !isset($options['resume'])) throw new RuntimeException('Migration tercatat ' . $state['status'] . '; audit status lalu jalankan --resume secara eksplisit.');
    if ($state) {
        $update = $pdo->prepare("UPDATE schema_migrations SET status='running',error_message=NULL,started_at=NOW(),finished_at=NULL WHERE migration_name=:name");
        $update->execute(['name'=>$migrationName]);
    } else {
        $insert = $pdo->prepare("INSERT INTO schema_migrations (migration_name,checksum,status,started_at) VALUES (:name,:checksum,'running',NOW())");
        $insert->execute(['name'=>$migrationName,'checksum'=>$checksum]);
    }
    $recorded = true;

    $statements = preg_split('/;\s*(?:\R|$)/', $sql);
    $step = 0;
    foreach ($statements as $statement) {
        $statement = trim(preg_replace('/^--.*$/m', '', $statement));
        if ($statement === '') continue;
        $step++;
        try { $pdo->exec($statement); }
        catch (Throwable $e) { throw new RuntimeException("Gagal pada statement {$step}: " . $e->getMessage(), 0, $e); }
    }

    foreach ($beforeCounts as $table=>$count) {
        $safeTable = str_replace('`', '``', (string)$table);
        $after = (int)$pdo->query("SELECT COUNT(*) FROM `{$safeTable}`")->fetchColumn();
        if ($after !== $count) throw new RuntimeException("Jumlah baris tabel existing {$table} berubah ({$count} -> {$after}). Periksa kemungkinan penulisan bersamaan.");
    }
    $checkColumns = ['status','recurrence','recurrence_until','reminder_minutes','task_id','shift_template_id'];
    $actualColumns = $pdo->query('SHOW COLUMNS FROM schedule_events')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($checkColumns as $column) if (!in_array($column,$actualColumns,true)) throw new RuntimeException("Verifikasi gagal: schedule_events.{$column} tidak ditemukan.");
    foreach (['schedule_holidays','schedule_holiday_sync','schedule_shift_templates','schedule_shift_occurrences'] as $table) {
        $pdo->query('SELECT 1 FROM `' . $table . '` LIMIT 0');
    }
    $newFks = $pdo->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA='lifeos' AND ((TABLE_NAME='schedule_events' AND CONSTRAINT_NAME IN ('fk_schedule_task','fk_schedule_event_shift_template')) OR (TABLE_NAME='schedule_shift_occurrences' AND CONSTRAINT_NAME IN ('fk_schedule_shift_occ_template','fk_schedule_shift_occ_event')))")->fetchColumn();
    if ((int)$newFks !== 4) throw new RuntimeException('Verifikasi foreign key Schedule tidak lengkap.');

    $finish = $pdo->prepare("UPDATE schema_migrations SET status='applied',error_message=NULL,finished_at=NOW() WHERE migration_name=:name");
    $finish->execute(['name'=>$migrationName]);
    echo "Migration {$migrationName} applied and verified on {$dbName} / {$identity['server_version']}.\n";
    echo 'Existing table row counts unchanged: ' . count($beforeCounts) . ".\n";
} catch (Throwable $e) {
    if ($recorded && isset($pdo)) {
        try {
            $fail = $pdo->prepare("UPDATE schema_migrations SET status='failed',error_message=:error,finished_at=NOW() WHERE migration_name=:name");
            $fail->execute(['error'=>substr($e->getMessage(),0,60000),'name'=>$migrationName]);
        } catch (Throwable) { }
    }
    fwrite(STDERR, 'Migration gagal: ' . $e->getMessage() . PHP_EOL);
    fwrite(STDERR, "DDL MariaDB tidak bisa dibatalkan sebagai satu transaksi. Audit schema, rerun dengan --resume hanya setelah koreksi; full rollback gunakan backup sebelum ada penulisan baru.\n");
    exit(1);
}
