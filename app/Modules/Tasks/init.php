<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_once LIFEOS_ROOT . '/app/Modules/Tasks/TasksService.php';

use App\Modules\Tasks\TasksService;

$tasksService = null;
$dbError = null;
$categories = [];

try {
    $tasksService = new TasksService();
    if (!$tasksService->isInstalled()) {
        $dbError = 'Tabel Tasks belum tersedia. Database tidak diubah otomatis.';
    } elseif (!$tasksService->isSchemaReady()) {
        $dbError = 'Schema Tasks belum lengkap atau tidak sesuai. Tidak ada perubahan schema yang dilakukan.';
    } else {
        $categories = $tasksService->listCategories();
    }
} catch (Throwable $e) {
    $dbError = 'Database LIFEOS belum terhubung: ' . $e->getMessage();
}

function render_tasks_alerts(?string $dbError = null): void
{
    $flash = get_flash();
    if ($flash): ?>
        <div class="tasks-alert alert-<?= e($flash['type'] ?? 'info') ?>" role="alert">
            <span class="alert-icon"><?= ($flash['type'] ?? '') === 'success' ? '✓' : 'ℹ' ?></span>
            <div class="alert-content"><?= e($flash['message'] ?? '') ?></div>
        </div>
    <?php endif;
    if ($dbError !== null): ?>
        <div class="tasks-alert alert-warning" role="alert">
            <span class="alert-icon">⚠</span>
            <div class="alert-content"><strong>Status Database:</strong> <?= e($dbError) ?></div>
        </div>
    <?php endif;
}
