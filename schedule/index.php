<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Modules/Schedule/init.php';
$pageTitle = 'Schedule · Kalender';
$currentPage = 'schedule';
$today = new DateTimeImmutable('today');
$selectedDate = DateTimeImmutable::createFromFormat('!Y-m-d', (string)($_GET['date'] ?? $today->format('Y-m-d'))) ?: $today;
$view = in_array($_GET['view'] ?? 'month', ['month','week','day'], true) ? $_GET['view'] : 'month';
$monthStart = $selectedDate->modify('first day of this month');
$monthGridStart = $monthStart->modify('monday this week');
$rangeStart = match ($view) { 'week' => $selectedDate->modify('monday this week'), 'day' => $selectedDate, default => $monthGridStart };
$rangeEnd = match ($view) { 'week' => $rangeStart->modify('+6 days'), 'day' => $selectedDate, default => $monthGridStart->modify('+41 days') };
$events = $holidays = $categories = $tasks = [];
if ($scheduleService && !$dbError) {
    try {
        try { $scheduleService->syncHolidays((int)$selectedDate->format('Y')); } catch (Throwable $e) { $holidayWarning = $e->getMessage(); }
        $events = $scheduleService->listEvents(['start'=>$rangeStart->format('Y-m-d'), 'end'=>$rangeEnd->format('Y-m-d')]);
        $holidays = $scheduleService->listHolidays($rangeStart->format('Y-m-d'), $rangeEnd->format('Y-m-d'));
        $categories = $scheduleService->listCategories();
        $tasks = $scheduleService->listTasks();
    } catch (Throwable $e) { $dbError = 'Gagal memuat kalender: ' . $e->getMessage(); }
}
$eventsByDate = [];
foreach ($events as $event) $eventsByDate[$event['occurrence_date']][] = $event;
$holidaysByDate = [];
foreach ($holidays as $holiday) $holidaysByDate[$holiday['start_date']][] = $holiday;
$monthNames = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$weekdayNames = [1=>'Sen','Sel','Rab','Kam','Jum','Sab','Min'];
$weekdayLong = [1=>'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];
$reminders = [];
$now = new DateTimeImmutable();
$reminderEvents = $events;
if ($scheduleService && !$dbError) {
    try { $reminderEvents = $scheduleService->listEvents(['start'=>$today->format('Y-m-d'), 'end'=>$today->modify('+1 day')->format('Y-m-d')]); } catch (Throwable) { }
}
foreach ($reminderEvents as $event) {
    if ($event['reminder_minutes'] === null || $event['status'] !== 'upcoming') continue;
    $startAt = new DateTimeImmutable($event['occurrence_date'] . ' ' . ($event['start_time'] ?: '00:00:00'));
    $remindAt = $startAt->modify('-' . (int)$event['reminder_minutes'] . ' minutes');
    if ($now >= $remindAt && $now <= $startAt) $reminders[] = $event;
}
$prevDate = match ($view) { 'week' => $selectedDate->modify('-7 days'), 'day' => $selectedDate->modify('-1 day'), default => $selectedDate->modify('first day of this month')->modify('-1 month') };
$nextDate = match ($view) { 'week' => $selectedDate->modify('+7 days'), 'day' => $selectedDate->modify('+1 day'), default => $selectedDate->modify('first day of next month') };
require LIFEOS_ROOT . '/includes/header.php';
?>
<div class="schedule-page">
    <?php render_schedule_nav('calendar'); ?>
    <?php render_schedule_alerts($dbError); ?>
    <?php if (!empty($holidayWarning)): ?><div class="schedule-alert alert-warning" role="status"><?= e($holidayWarning) ?></div><?php endif; ?>
    <?php foreach ($reminders as $reminder): ?><div class="schedule-alert alert-reminder" role="status">Pengingat: <strong><?= e($reminder['title']) ?></strong> dimulai <?= $reminder['all_day'] ? 'hari ini' : 'pukul ' . e(substr((string)$reminder['start_time'],0,5)) ?>.</div><?php endforeach; ?>
    <header class="schedule-heading">
        <div><p class="eyebrow">PERSONAL PLANNING</p><h1>Kalender</h1><p class="schedule-subtitle">Atur agenda, jadwal berulang, dan pengingat dalam satu tampilan.</p></div>
        <a class="schedule-btn schedule-btn-primary" href="<?= e(url('schedule/events.php?action=create&date=' . $selectedDate->format('Y-m-d'))) ?>">＋ Tambah event</a>
    </header>
    <section class="schedule-calendar" data-calendar-view="<?= e($view) ?>">
        <div class="calendar-toolbar">
            <div class="calendar-date-controls">
                <a class="schedule-btn schedule-btn-secondary" href="<?= e(url('schedule/index.php?view=' . $view . '&date=' . $prevDate->format('Y-m-d'))) ?>" aria-label="Periode sebelumnya">‹</a>
                <a class="schedule-btn schedule-btn-secondary" href="<?= e(url('schedule/index.php?view=' . $view . '&date=' . $today->format('Y-m-d'))) ?>">Hari ini</a>
                <a class="schedule-btn schedule-btn-secondary" href="<?= e(url('schedule/index.php?view=' . $view . '&date=' . $nextDate->format('Y-m-d'))) ?>" aria-label="Periode berikutnya">›</a>
                <h2><?= $view === 'month' ? e($monthNames[(int)$selectedDate->format('n')] . ' ' . $selectedDate->format('Y')) : e($rangeStart->format('d') . ' ' . $monthNames[(int)$rangeStart->format('n')]) . ($view === 'day' ? '' : ' – ' . e($rangeEnd->format('d') . ' ' . $monthNames[(int)$rangeEnd->format('n')] . ' ' . $rangeEnd->format('Y'))) ?></h2>
            </div>
            <div class="calendar-view-switch" aria-label="Tampilan kalender">
                <?php foreach (['month'=>'Bulanan','week'=>'Mingguan','day'=>'Harian'] as $key=>$label): ?><a class="<?= $view === $key ? 'active' : '' ?>" href="<?= e(url('schedule/index.php?view=' . $key . '&date=' . $selectedDate->format('Y-m-d'))) ?>" <?= $view === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
            </div>
        </div>
        <?php if ($view === 'month'): ?>
            <div class="calendar-grid calendar-month">
                <?php foreach ($weekdayNames as $day): ?><div class="calendar-weekday"><?= $day ?></div><?php endforeach; ?>
                <?php for ($i=0; $i<42; $i++): $day=$monthGridStart->modify('+' . $i . ' days'); $key=$day->format('Y-m-d'); ?>
                    <div class="calendar-day <?= $day->format('m') !== $selectedDate->format('m') ? 'is-outside' : '' ?> <?= $key === $today->format('Y-m-d') ? 'is-today' : '' ?> <?= $key === $selectedDate->format('Y-m-d') ? 'is-selected' : '' ?>" data-drop-date="<?= e($key) ?>">
                        <a class="calendar-day-number" href="<?= e(url('schedule/index.php?view=month&date=' . $key)) ?>"><?= $day->format('j') ?></a>
                        <?php foreach ($holidaysByDate[$key] ?? [] as $holiday): ?><span class="calendar-event calendar-holiday" title="Hari libur · <?= e($holiday['source_name']) ?>"><?= e($holiday['title']) ?></span><?php endforeach; ?>
                        <?php foreach ($eventsByDate[$key] ?? [] as $event): ?><a class="calendar-event category-event status-<?= e($event['status']) ?>" draggable="true" data-event-id="<?= (int)$event['id'] ?>" data-event-date="<?= e($event['occurrence_date']) ?>" href="<?= e(url('schedule/events.php?id=' . $event['id'])) ?>" title="<?= e($event['title']) ?>"><?php if (!empty($event['category_name'])): ?><span class="event-category-label"><?= e($event['category_name']) ?> · </span><?php endif; ?><?= $event['all_day'] ? '' : e(substr((string)$event['start_time'],0,5) . ' ') ?><?= e($event['title']) ?></a><?php endforeach; ?>
                    </div>
                <?php endfor; ?>
            </div>
        <?php else: ?>
            <div class="calendar-agenda <?= $view === 'week' ? 'agenda-week' : 'agenda-day' ?>">
                <?php $days = $view === 'week' ? 7 : 1; for ($i=0;$i<$days;$i++): $day=$rangeStart->modify('+' . $i . ' days'); $key=$day->format('Y-m-d'); ?>
                    <section class="agenda-day <?= $key === $today->format('Y-m-d') ? 'is-today' : '' ?>" data-drop-date="<?= e($key) ?>">
                        <h3><a href="<?= e(url('schedule/index.php?view=day&date=' . $key)) ?>"><?= e($weekdayLong[(int)$day->format('N')] . ', ' . $day->format('d') . ' ' . $monthNames[(int)$day->format('n')]) ?></a></h3>
                        <?php foreach ($holidaysByDate[$key] ?? [] as $holiday): ?><div class="agenda-event calendar-holiday"><?= e($holiday['title']) ?> <small>Hari libur nasional</small></div><?php endforeach; ?>
                        <?php foreach ($eventsByDate[$key] ?? [] as $event): ?><a class="agenda-event category-event status-<?= e($event['status']) ?>" draggable="true" data-event-id="<?= (int)$event['id'] ?>" data-event-date="<?= e($event['occurrence_date']) ?>" href="<?= e(url('schedule/events.php?id=' . $event['id'])) ?>"><strong><?= $event['all_day'] ? 'Sepanjang hari' : e(substr((string)$event['start_time'],0,5)) ?> · <?= e($event['title']) ?></strong><?php if (!empty($event['category_name'])): ?><small>Kategori: <?= e($event['category_name']) ?></small><?php endif; ?><?php if (!empty($event['task_title'])): ?><small>Task: <?= e($event['task_title']) ?></small><?php endif; ?></a><?php endforeach; ?>
                        <?php if (empty($eventsByDate[$key]) && empty($holidaysByDate[$key])): ?><p class="agenda-empty">Tidak ada agenda.</p><?php endif; ?>
                    </section>
                    <?php if ($view === 'day'): ?><div class="day-time-grid" aria-label="Pindah event ke jam lain"><?php for ($hour=0; $hour<24; $hour++): ?><div class="day-time-slot" data-drop-date="<?= e($key) ?>" data-drop-time="<?= sprintf('%02d:00', $hour) ?>"><span><?= sprintf('%02d:00', $hour) ?></span><span class="drop-hint">Letakkan event di sini untuk mengubah jam</span></div><?php endfor; ?></div><?php endif; ?>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
        <p class="calendar-help">Seret event ke tanggal lain untuk menjadwal ulang. Pada event berulang, tindakan ini memindahkan seluruh rangkaian.</p>
    </section>
    <form class="visually-hidden" method="post" action="<?= e(url('schedule/events.php')) ?>" id="reschedule-form"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="reschedule"><input type="hidden" name="id" id="reschedule-id"><input type="hidden" name="start_date" id="reschedule-date"><input type="hidden" name="occurrence_date" id="reschedule-occurrence"><input type="hidden" name="start_time" id="reschedule-time"><input type="hidden" name="return_date" value="<?= e($selectedDate->format('Y-m-d')) ?>"></form>
    <form method="post" action="<?= e(url('schedule/events.php')) ?>" class="holiday-sync-form"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="sync_holidays"><input type="hidden" name="year" value="<?= (int)$selectedDate->format('Y') ?>"><button class="text-button" type="submit">Perbarui data hari libur</button><span>Sumber pihak ketiga: Nager.Date · diperbarui otomatis maksimal tiap 7 hari saat terhubung ke internet.</span></form>
</div>
<?php require LIFEOS_ROOT . '/includes/footer.php'; ?>
