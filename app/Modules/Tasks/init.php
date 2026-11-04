<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once LIFEOS_ROOT . '/app/Modules/Tasks/TasksService.php';

use App\Modules\Tasks\TasksService;

$tasksService = null;
$dbError = null;

/** Execute tasks schema if missing */
function upgrade_tasks_schema(): ?string
{
    $schemaPath = LIFEOS_ROOT . '/database/tasks_schema.sql';
    if (!file_exists($schemaPath)) {
        return 'File schema tasks tidak ditemukan: ' . $schemaPath;
    }
    $pdo = db();
    $sql = file_get_contents($schemaPath);
    if ($sql === false) {
        return 'Tidak dapat membaca file schema tasks.';
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
            error_log('[TasksUpgrade] ' . $e->getMessage() . ' in: ' . $stmt);
        }
    }
    return null;
}

try {
    $tasksService = new TasksService();
    if (!$tasksService->isInstalled()) {
        $upgradeError = upgrade_tasks_schema();
        if ($upgradeError === null) {
            if (!$tasksService->isInstalled()) {
                $dbError = 'Tabel modul Tasks belum dibuat meskipun upgrade dijalankan.';
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
    ['slug' => 'tasks',   'label' => 'Task',    'path' => 'tasks.php'],
];

function render_tasks_alerts(?string $dbError = null): void
{
    $flash = get_flash();
    if ($flash): ?>
        <div class="tasks-alert alert-<?= e($flash['type'] ?? 'info') ?>" role="alert">
            <span class="alert-icon"><?= $flash['type'] === 'success' ? '✓' : 'ℹ' ?></span>
            <div class="alert-content"><?= e($flash['message']) ?></div>
        </div>
    <?php endif;
    if ($dbError !== null): ?>
        <div class="tasks-alert alert-warning" role="alert">
            <span class="alert-icon">⚠</span>
            <div class="alert-content"><strong>Status Database:</strong> <?= e($dbError) ?></div>
        </div>
    <?php endif;
}

function render_tasks_nav(string $activeSlug): void
{
    global $navigation;
    ?>
    <nav class="tasks-navigation" aria-label="Navigasi Tasks">
        <?php foreach ($navigation as $item): ?>
            <a href="<?= e(url('tasks/' . $item['path'])) ?>"
               class="<?= $activeSlug === $item['slug'] ? 'active' : '' ?>"
               <?= $activeSlug === $item['slug'] ? 'aria-current="page"' : '' ?>>
                <?= e($item['label']) ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php
}
?>
