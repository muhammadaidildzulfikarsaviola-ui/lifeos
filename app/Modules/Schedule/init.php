<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once LIFEOS_ROOT . '/app/Modules/Schedule/ScheduleService.php';

use App\Modules\Schedule\ScheduleService;

$scheduleService = null;
$dbError = null;

/**
 * Execute the schedule schema if tables are missing.
 */
function upgrade_schedule_schema(): ?string
{
    $schemaPath = LIFEOS_ROOT . '/database/schedule_schema.sql';
    if (!file_exists($schemaPath)) {
        return 'File schema schedule tidak ditemukan: ' . $schemaPath;
    }
    $pdo = db();
    $sql = file_get_contents($schemaPath);
    if ($sql === false) {
        return 'Tidak dapat membaca file schema schedule.';
    }
    $statements = preg_split('/;(\s*\R)+/', $sql);
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || str_starts_with($stmt, '--')) {
            continue;
        }
        try {
            $pdo->exec($stmt);
        } catch (Throwable $e) {
            error_log('[ScheduleUpgrade] ' . $e->getMessage() . ' in: ' . $stmt);
        }
    }
    return null;
}

try {
    $scheduleService = new ScheduleService();
    if (!$scheduleService->isInstalled()) {
        $upgradeError = upgrade_schedule_schema();
        if ($upgradeError === null) {
            // re‑check after upgrade
            if (!$scheduleService->isInstalled()) {
                $dbError = 'Tabel modul Schedule belum dibuat meskipun upgrade dijalankan.';
            }
        } else {
            $dbError = $upgradeError;
        }
    }
} catch (Throwable $e) {
    $dbError = 'Database LIFEOS belum terhubung: ' . $e->getMessage();
}

$navigation = [
    ['slug' => 'overview', 'label' => 'Ringkasan', 'path' => 'index.php'],
    ['slug' => 'events',   'label' => 'Event',    'path' => 'events.php'],
];

function render_schedule_alerts(?string $dbError = null): void
{
    $flash = get_flash();
    if ($flash): ?>
        <div class="schedule-alert alert-<?= e($flash['type'] ?? 'info') ?>" role="alert">
            <span class="alert-icon"><?= $flash['type'] === 'success' ? '✓' : 'ℹ' ?></span>
            <div class="alert-content"><?= e($flash['message']) ?></div>
        </div>
    <?php endif;
    if ($dbError !== null): ?>
        <div class="schedule-alert alert-warning" role="alert">
            <span class="alert-icon">⚠</span>
            <div class="alert-content"><strong>Status Database:</strong> <?= e($dbError) ?></div>
        </div>
    <?php endif;
}

function render_schedule_nav(string $activeSlug): void
{
    global $navigation;
    ?>
    <nav class="schedule-navigation" aria-label="Navigasi Schedule">
        <?php foreach ($navigation as $item): ?>
            <a href="<?= e(url('schedule/' . $item['path'])) ?>"
               class="<?= $activeSlug === $item['slug'] ? 'active' : '' ?>"
               <?= $activeSlug === $item['slug'] ? 'aria-current="page"' : '' ?>>
                <?= e($item['label']) ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php
}
?>
