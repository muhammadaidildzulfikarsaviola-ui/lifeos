<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_once LIFEOS_ROOT . '/app/Modules/Schedule/ScheduleService.php';

use App\Modules\Schedule\ScheduleService;

$scheduleService = null;
$dbError = null;

try {
    $scheduleService = new ScheduleService();
    if (!$scheduleService->isInstalled()) {
        $dbError = 'Schema Schedule belum tersedia. Pasang database/schedule_schema.sql terlebih dahulu.';
    } elseif (!$scheduleService->isSchemaReady()) {
        $dbError = 'Database Schedule perlu migration fitur terbaru sebelum fitur kalender dapat dipakai.';
    }
} catch (Throwable $e) {
    $dbError = 'Database LIFEOS belum terhubung: ' . $e->getMessage();
}

$navigation = [
    ['slug' => 'calendar', 'label' => 'Kalender', 'path' => 'index.php'],
    ['slug' => 'events', 'label' => 'Event', 'path' => 'events.php'],
    ['slug' => 'shifts', 'label' => 'Shift kerja', 'path' => 'shifts.php'],
];

function schedule_query_date(mixed $value, ?string $fallback = null): string
{
    $fallback ??= date('Y-m-d');
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $fallback;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if ($date === false
        || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        || $date->format('Y-m-d') !== $value) {
        return $fallback;
    }

    return $value;
}

function render_schedule_alerts(?string $dbError = null): void
{
    $flash = get_flash();
    if ($flash): ?>
        <div class="schedule-alert alert-<?= e($flash['type'] ?? 'info') ?>" role="alert"><span><?= $flash['type'] === 'success' ? '✓' : 'ℹ' ?></span><div><?= e($flash['message']) ?></div></div>
    <?php endif;
    if ($dbError !== null): ?>
        <div class="schedule-alert alert-warning" role="alert"><span>⚠</span><div><strong>Status:</strong> <?= e($dbError) ?></div></div>
    <?php endif;
}

function render_schedule_nav(string $activeSlug): void
{
    global $navigation;
    ?>
    <nav class="schedule-navigation" aria-label="Navigasi Schedule">
        <?php foreach ($navigation as $item): ?>
            <a href="<?= e(url('schedule/' . $item['path'])) ?>" class="<?= $activeSlug === $item['slug'] ? 'active' : '' ?>" <?= $activeSlug === $item['slug'] ? 'aria-current="page"' : '' ?>><?= e($item['label']) ?></a>
        <?php endforeach; ?>
    </nav>
    <?php
}
?>
