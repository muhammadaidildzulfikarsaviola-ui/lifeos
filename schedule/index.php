<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';

$pageTitle = 'Schedule · Ringkasan';
$currentPage = 'schedule';

require LIFEOS_ROOT . '/includes/header.php';

// Fetch events for overview (e.g., next 7 days)
$events = [];
if ($scheduleService !== null && $dbError === null) {
    try {
        // Simple filter: upcoming events from today
        $today = date('Y-m-d');
        $events = $scheduleService->listEvents(['date' => $today]);
    } catch (Throwable $e) {
        $dbError = 'Gagal memuat event: ' . $e->getMessage();
    }
}
?>
<div class="schedule-page">
    <?php render_schedule_nav('overview'); ?>
    <?php render_schedule_alerts($dbError); ?>

    <section class="schedule-section">
        <h2>Event Mendatang</h2>
        <?php if (empty($events)): ?>
            <div class="schedule-empty-state">
                <p>Belum ada event yang dijadwalkan.</p>
                <a href="<?= e(url('schedule/events.php?action=create')) ?>" class="schedule-btn schedule-btn-primary">Tambah Event</a>
            </div>
        <?php else: ?>
            <ul class="event-list">
                <?php foreach ($events as $ev): ?>
                    <li class="event-item">
                        <h3><?= e($ev['title']) ?></h3>
                        <p><?= e($ev['description'] ?? '') ?></p>
                        <p>
                            <?= e($ev['start_date']) ?>
                            <?php if (!$ev['all_day']): ?>
                                <?= e($ev['start_time']) ?> – <?= e($ev['end_time']) ?>
                            <?php endif; ?>
                        </p>
                        <a href="<?= e(url('schedule/events.php?id=' . $ev['id'])) ?>" class="event-link">Detail</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
<?php require LIFEOS_ROOT . '/includes/footer.php'; ?>
