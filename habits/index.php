<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once LIFEOS_ROOT . '/app/Modules/Habits/HabitsService.php';

use App\Modules\Habits\HabitsService;

$pageTitle = 'Habits';
$currentPage = 'habits';
$service = null;
$dbError = null;
$formError = null;
$formSubmitted = false;
$action = request_string($_GET['action'] ?? '');
$habitId = max(0, request_integer($_GET['id'] ?? 0));
$archivedView = request_string($_GET['archived'] ?? '') === '1';
$today = date('Y-m-d');
$selectedDate = request_string($_GET['date'] ?? $today);
$selectedDateObject = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $selectedDate)
    ? DateTimeImmutable::createFromFormat('!Y-m-d', $selectedDate)
    : false;
$selectedDateErrors = DateTimeImmutable::getLastErrors();
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $selectedDate)
    || $selectedDateObject === false
    || ($selectedDateErrors !== false && ($selectedDateErrors['warning_count'] > 0 || $selectedDateErrors['error_count'] > 0))
    || ($selectedDateObject !== false && $selectedDateObject->format('Y-m-d') !== $selectedDate)
    || $selectedDate > $today) {
    http_response_code(400);
    $formError = 'Tanggal yang dipilih tidak valid.';
    $selectedDate = $today;
}
$allowedActions = ['', 'create', 'edit', 'view'];
if (!in_array($action, $allowedActions, true)) {
    http_response_code(400);
    $formError = 'Tindakan Habits tidak dikenal.';
    $action = '';
}
if (in_array($action, ['edit', 'view'], true) && $habitId < 1) {
    http_response_code(400);
    $formError = 'Habit id tidak valid.';
    $action = '';
}

$formValues = [
    'name' => '', 'description' => '', 'frequency' => 'daily', 'weekdays' => [],
    'period_target' => '', 'daily_target' => '', 'unit' => '', 'start_date' => $today,
    'end_date' => '', 'color_key' => 'green',
];
$habit = null;
$stats = null;
$entries = [];
$overview = ['date' => $selectedDate, 'habits' => []];
$archivedHabits = [];

try {
    $service = new HabitsService();
    if (!$service->isSchemaReady()) {
        $dbError = 'Schema Habits belum lengkap. Database tidak diubah otomatis.';
    }
} catch (Throwable $e) {
    $dbError = 'Database Habits belum dapat dihubungkan.';
    error_log('[Habits] ' . $e->getMessage());
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();
    try {
        if ($service === null || $dbError !== null) throw new RuntimeException('Layanan Habits belum siap.');
        $postAction = request_string($_POST['action'] ?? '');
        $postedId = max(0, request_integer($_POST['id'] ?? 0));
        if ($postAction === 'archive') {
            $service->archiveHabit($postedId);
            set_flash('success', 'Habit diarsipkan. Riwayatnya tetap tersimpan.');
            redirect('habits/');
        }
        if ($postAction === 'restore') {
            $service->restoreHabit($postedId);
            set_flash('success', 'Habit dipulihkan.');
            redirect('habits/?archived=1');
        }
        if ($postAction === 'save_entry') {
            $service->saveEntry($postedId, [
                'entry_date' => request_string($_POST['entry_date'] ?? ''),
                'progress' => request_string($_POST['progress'] ?? '0'),
                'completed' => request_string($_POST['completed'] ?? '0'),
                'notes' => request_string($_POST['notes'] ?? ''),
            ]);
            set_flash('success', 'Progress habit tersimpan pada tanggal yang sama.');
            redirect('habits/?date=' . rawurlencode(request_string($_POST['entry_date'] ?? $today)));
        }
        if (!in_array($postAction, ['create', 'update'], true)) {
            http_response_code(400);
            throw new InvalidArgumentException('Tindakan Habits tidak valid.');
        }
        $formSubmitted = true;
        $days = $_POST['weekdays'] ?? [];
        $formValues = [
            'name' => request_string($_POST['name'] ?? ''),
            'description' => request_string($_POST['description'] ?? ''),
            'frequency' => request_string($_POST['frequency'] ?? ''),
            'weekdays' => is_array($days) ? array_map('strval', $days) : [],
            'period_target' => request_string($_POST['period_target'] ?? ''),
            'daily_target' => request_string($_POST['daily_target'] ?? ''),
            'unit' => request_string($_POST['unit'] ?? ''),
            'start_date' => request_string($_POST['start_date'] ?? ''),
            'end_date' => request_string($_POST['end_date'] ?? ''),
            'color_key' => request_string($_POST['color_key'] ?? 'green'),
        ];
        if ($postAction === 'update') {
            $service->updateHabit($postedId, $formValues);
            set_flash('success', 'Habit diperbarui. Riwayat completion sebelumnya tetap dipertahankan.');
        } else {
            $service->createHabit($formValues);
            set_flash('success', 'Habit berhasil dibuat.');
        }
        redirect('habits/');
    } catch (InvalidArgumentException | RuntimeException $e) {
        $formError = $e->getMessage();
    } catch (Throwable $e) {
        $formError = 'Habit tidak dapat disimpan saat ini. Periksa database lalu coba kembali.';
        error_log('[Habits] ' . $e->getMessage());
    }
}

$editing = in_array($action, ['create', 'edit'], true);
$viewing = $action === 'view';
if ($service !== null && $dbError === null) {
    try {
        if ($habitId > 0 && ($editing || $viewing)) {
            $habit = $service->getHabit($habitId, true);
            if ($habit === null) {
                http_response_code(404);
                $formError = 'Habit tidak ditemukan.';
                $editing = false;
                $viewing = false;
            } elseif ($editing && !$formSubmitted) {
                $mask = (int) $habit['weekday_mask'];
                $days = [];
                for ($day = 1; $day <= 7; $day++) if (($mask & (1 << ($day - 1))) !== 0) $days[] = (string) $day;
                $formValues = [
                    'name' => (string) $habit['name'],
                    'description' => (string) ($habit['description'] ?? ''),
                    'frequency' => (string) $habit['frequency'],
                    'weekdays' => $days,
                    'period_target' => (string) ($habit['period_target'] ?? ''),
                    'daily_target' => (string) ($habit['daily_target'] ?? ''),
                    'unit' => (string) ($habit['unit'] ?? ''),
                    'start_date' => (string) $habit['start_date'],
                    'end_date' => (string) ($habit['end_date'] ?? ''),
                    'color_key' => (string) $habit['color_key'],
                ];
            } elseif ($viewing) {
                $stats = $service->getStats($habitId, $selectedDate);
                $monthStart = (new DateTimeImmutable($selectedDate))->modify('first day of this month');
                $monthEnd = $monthStart->modify('last day of this month');
                $entryStart = max($monthStart, new DateTimeImmutable($habit['start_date']));
                $entryEnd = min($monthEnd, new DateTimeImmutable($today));
                $entries = $entryStart <= $entryEnd ? $service->listEntries($habitId, $entryStart->format('Y-m-d'), $entryEnd->format('Y-m-d')) : [];
            }
        }
        if (!$editing && !$viewing) {
            $overview = $service->getOverviewData($selectedDate, $archivedView);
        }
        $archivedHabits = $service->listHabits(true);
    } catch (Throwable $e) {
        $dbError = 'Gagal memuat data Habits.';
        error_log('[Habits] ' . $e->getMessage());
    }
}

$entryByDate = [];
foreach ($entries as $entry) $entryByDate[$entry['entry_date']] = $entry;
$weekdayLabels = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
$frequencyLabels = ['daily' => 'Every day', 'weekdays' => 'Selected weekdays', 'weekly_target' => 'Weekly target', 'monthly_target' => 'Monthly target'];
$selected = new DateTimeImmutable($selectedDate);
$previousDate = $selected->modify('-1 day')->format('Y-m-d');
$nextDate = $selected->modify('+1 day')->format('Y-m-d');
$monthStart = $viewing && $habit ? (new DateTimeImmutable($selectedDate))->modify('first day of this month') : null;
$calendarStart = $monthStart ? $monthStart->modify('monday this week') : null;
$calendarEnd = $monthStart ? $monthStart->modify('last day of this month')->modify('sunday this week') : null;
require LIFEOS_ROOT . '/includes/header.php';
?>
<div class="habits-page">
    <header class="habits-heading">
        <div>
            <p class="eyebrow">CONSISTENCY & PROGRESS</p>
            <h1><?= $editing ? ($habit ? 'Edit habit' : 'Create habit') : ($viewing && $habit ? e($habit['name']) : 'Habits') ?></h1>
            <p class="habits-subtitle">Build routines, record progress, and review your consistency over time.</p>
        </div>
        <?php if (!$editing && !$viewing && !$archivedView && $dbError === null): ?><a class="habits-button habits-button-primary" href="<?= e(url('habits/index.php?action=create')) ?>">＋ New habit</a><?php endif; ?>
    </header>

    <?php if ($dbError !== null): ?><div class="habits-alert alert-warning" role="alert"><?= e($dbError) ?></div><?php endif; ?>
    <?php if ($formError !== null): ?><div class="habits-alert alert-error" role="alert"><?= e($formError) ?></div><?php endif; ?>
    <?php if ($flash = get_flash()): ?><div class="habits-alert alert-<?= e($flash['type'] ?? 'info') ?>" role="status"><?= e($flash['message'] ?? '') ?></div><?php endif; ?>

    <?php if ($editing): ?>
        <form class="habits-form" method="post" action="<?= e(url('habits/index.php' . ($habit ? '?action=edit&id=' . (int) $habit['id'] : '?action=create'))) ?>">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="<?= $habit ? 'update' : 'create' ?>">
            <?php if ($habit): ?><input type="hidden" name="id" value="<?= (int) $habit['id'] ?>"><?php endif; ?>
            <label class="field-wide">Habit name *<input name="name" maxlength="160" required value="<?= e($formValues['name']) ?>" autofocus></label>
            <label class="field-wide">Description<textarea name="description" rows="3" maxlength="10000"><?= e($formValues['description']) ?></textarea></label>
            <label>Frequency<select name="frequency" data-habit-frequency><?php foreach ($frequencyLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= $formValues['frequency'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
            <fieldset class="habits-weekdays field-wide" data-habit-weekdays><legend>Scheduled weekdays</legend><div><?php foreach ($weekdayLabels as $day => $label): ?><label><input type="checkbox" name="weekdays[]" value="<?= $day ?>" <?= in_array((string) $day, $formValues['weekdays'], true) ? 'checked' : '' ?>><?= e($label) ?></label><?php endforeach; ?></div></fieldset>
            <label data-habit-period>Frequency target<input type="number" name="period_target" min="1" max="31" step="1" value="<?= e($formValues['period_target']) ?>"><small>Unique completed days per week (max 7) or month (max 31).</small></label>
            <label>Daily measurement target<input type="number" name="daily_target" min="0.01" max="99999999.99" step="0.01" value="<?= e($formValues['daily_target']) ?>" placeholder="Optional"><small>Separate from frequency target; progress may exceed this value.</small></label>
            <label>Unit<input name="unit" maxlength="32" value="<?= e($formValues['unit']) ?>" placeholder="glasses, pages, minutes"></label>
            <label>Start date<input type="date" name="start_date" required value="<?= e($formValues['start_date']) ?>"></label>
            <label>End date<input type="date" name="end_date" value="<?= e($formValues['end_date']) ?>"></label>
            <label>Color<select name="color_key"><?php foreach (['green', 'blue', 'amber', 'rose', 'violet'] as $color): ?><option value="<?= e($color) ?>" <?= $formValues['color_key'] === $color ? 'selected' : '' ?>><?= ucfirst($color) ?></option><?php endforeach; ?></select></label>
            <div class="habits-form-actions field-wide"><a class="habits-button habits-button-secondary" href="<?= e(url('habits/')) ?>">Cancel</a><button class="habits-button habits-button-primary" type="submit"><?= $habit ? 'Save changes' : 'Create habit' ?></button></div>
        </form>
    <?php elseif ($viewing && $habit !== null && $stats !== null): ?>
        <div class="habits-detail-actions"><a class="habits-button habits-button-secondary" href="<?= e(url('habits/index.php?action=edit&id=' . (int) $habit['id'])) ?>">Edit</a><a class="habits-button habits-button-secondary" href="<?= e(url('habits/')) ?>">Back to habits</a></div>
        <section class="habits-stat-grid" aria-label="Habit statistics">
            <article><span>Current streak</span><strong><?= $stats['current_streak'] ?></strong><small><?= $habit['frequency'] === 'weekly_target' || $habit['frequency'] === 'monthly_target' ? 'periods' : 'scheduled days' ?></small></article>
            <article><span>Longest streak</span><strong><?= $stats['longest_streak'] ?></strong><small><?= $habit['frequency'] === 'weekly_target' || $habit['frequency'] === 'monthly_target' ? 'periods' : 'scheduled days' ?></small></article>
            <article><span>Completed days</span><strong><?= $stats['total_completed_days'] ?></strong><small>recorded through <?= e($selectedDate) ?></small></article>
            <article><span>Completion rate</span><strong><?= e((string) $stats['completion_rate']) ?>%</strong><small>scheduled days or closed target periods</small></article>
        </section>
        <section class="habits-detail-card">
            <div class="habits-detail-heading"><div><p class="eyebrow">HABIT CONFIGURATION</p><h2><?= e($frequencyLabels[$habit['frequency']] ?? $habit['frequency']) ?></h2></div><span class="habit-color-dot color-<?= e($habit['color_key']) ?>"></span></div>
            <p><?= $habit['description'] ? nl2br(e($habit['description'])) : 'No description.' ?></p>
            <dl class="habits-config-grid"><dt>Active from</dt><dd><?= e($habit['start_date']) ?><?= $habit['end_date'] ? ' to ' . e($habit['end_date']) : '' ?></dd><?php if ($habit['frequency'] === 'weekdays'): ?><dt>Weekdays</dt><dd><?php $days=[]; for($d=1;$d<=7;$d++) if(((int)$habit['weekday_mask'] & (1<<($d-1)))!==0)$days[]=$weekdayLabels[$d]; echo e(implode(', ', $days)); ?></dd><?php endif; ?><?php if ($habit['period_target']): ?><dt>Frequency target</dt><dd><?= (int) $habit['period_target'] ?> days per <?= $habit['frequency'] === 'weekly_target' ? 'week' : 'month' ?></dd><?php endif; ?><?php if ($habit['daily_target'] !== null): ?><dt>Daily target</dt><dd><?= e((string) $habit['daily_target']) ?> <?= e($habit['unit'] ?? '') ?></dd><?php endif; ?><dt>This week</dt><dd><?= $stats['weekly_progress']['completed_days'] ?> / <?= $stats['weekly_progress']['target'] ?> days · <?= e((string) $stats['weekly_progress']['percent']) ?>%</dd><dt>This month</dt><dd><?= $stats['monthly_progress']['completed_days'] ?> / <?= $stats['monthly_progress']['target'] ?> days · <?= e((string) $stats['monthly_progress']['percent']) ?>%</dd></dl>
            <p class="habits-muted">Changing a schedule keeps all existing entries. Historical statistics are interpreted using the current schedule settings.</p>
        </section>
        <section class="habits-history-card">
            <div class="habits-history-heading"><div><p class="eyebrow">ACTIVITY</p><h2><?= e($monthStart->format('F Y')) ?></h2></div><nav aria-label="History month"><a class="habits-button habits-button-secondary" href="<?= e(url('habits/index.php?action=view&id=' . (int) $habit['id'] . '&date=' . $monthStart->modify('-1 month')->format('Y-m-d'))) ?>">← Previous</a><a class="habits-button habits-button-secondary" href="<?= e(url('habits/index.php?action=view&id=' . (int) $habit['id'] . '&date=' . $today)) ?>">Current month</a></nav></div>
            <div class="habit-calendar"><div class="habit-calendar-weekdays"><?php foreach ($weekdayLabels as $label): ?><span><?= e($label) ?></span><?php endforeach; ?></div><div class="habit-calendar-days"><?php for ($day = $calendarStart; $day <= $calendarEnd; $day = $day->modify('+1 day')): ?><?php $key=$day->format('Y-m-d');$entry=$entryByDate[$key]??null;$scheduled=$service->isTrackingDay($habit,$day);$isFuture=$key>$today;$periodTarget=in_array($habit['frequency'],['weekly_target','monthly_target'],true);$state=$isFuture?'future':(!$scheduled?'unscheduled':(($entry && (int)$entry['completed']===1)?'complete':(($entry && (float)$entry['progress']>0)?'in-progress':(!$periodTarget && $key<$today?'missed':'available')))); ?><a class="habit-calendar-day state-<?= e($state) ?> <?= $day->format('m') !== $monthStart->format('m') ? 'outside-month' : '' ?>" href="<?= e(url('habits/index.php?action=view&id=' . (int) $habit['id'] . '&date=' . $key)) ?>" aria-label="<?= e($key . ' · ' . $state) ?>"><span><?= $day->format('j') ?></span><?php if ($entry && (float) $habit['daily_target'] > 0): ?><small><?= e((string) $entry['progress']) ?></small><?php endif; ?></a><?php endfor; ?></div></div>
            <div class="habit-calendar-legend"><span class="state-complete">Completed</span><span class="state-missed">Missed scheduled day</span><span class="state-unscheduled">Unscheduled</span><span class="state-available">Available target day</span></div>
            <h3>Recorded history</h3>
            <?php if (!$entries): ?><p class="habits-muted">No entries recorded in this month. Missed days are shown from the schedule and are not stored as entries.</p><?php else: ?><ul class="habit-history-list"><?php foreach (array_reverse($entries) as $entry): ?><li><a href="<?= e(url('habits/index.php?action=view&id=' . (int) $habit['id'] . '&date=' . $entry['entry_date'])) ?>"><strong><?= e($entry['entry_date']) ?></strong><span><?= (int) $entry['completed'] === 1 ? 'Completed' : 'In progress' ?><?php if ($habit['daily_target'] !== null): ?> · <?= e((string) $entry['progress']) ?> / <?= e((string) $habit['daily_target']) ?> <?= e($habit['unit'] ?? '') ?><?php endif; ?></span></a><?php if ($entry['notes']): ?><p><?= e($entry['notes']) ?></p><?php endif; ?></li><?php endforeach; ?></ul><?php endif; ?>
        </section>
    <?php else: ?>
        <div class="habits-date-nav"><a class="habits-button habits-button-secondary" href="<?= e(url('habits/?date=' . $previousDate . ($archivedView ? '&archived=1' : ''))) ?>">← Previous day</a><form method="get" action="<?= e(url('habits/')) ?>"><label for="habit-view-date">Showing date</label><input id="habit-view-date" type="date" name="date" max="<?= e($today) ?>" value="<?= e($selectedDate) ?>"><?php if ($archivedView): ?><input type="hidden" name="archived" value="1"><?php endif; ?><button class="habits-button habits-button-secondary" type="submit">Go</button></form><?php if ($selectedDate < $today): ?><a class="habits-button habits-button-secondary" href="<?= e(url('habits/?date=' . $nextDate . ($archivedView ? '&archived=1' : ''))) ?>">Next day →</a><?php else: ?><span class="habits-button habits-button-disabled" aria-disabled="true">Today</span><?php endif; ?></div>
        <?php if ($archivedView): ?><div class="habits-section-heading"><div><p class="eyebrow">HISTORY PRESERVED</p><h2>Archived habits</h2></div><a class="habits-button habits-button-secondary" href="<?= e(url('habits/')) ?>">Active habits</a></div><?php elseif ($service !== null && $dbError === null): ?>
            <?php $scheduledItems=array_values(array_filter($overview['habits'],static fn(array $item):bool=>in_array($item['frequency'],['daily','weekdays'],true)&&$item['is_scheduled']));$periodItems=array_values(array_filter($overview['habits'],static fn(array $item):bool=>in_array($item['frequency'],['weekly_target','monthly_target'],true)));$doneCount=count(array_filter($scheduledItems,static fn(array $item):bool=>(bool)($item['today_entry']['completed']??false)));$rate=count($scheduledItems)>0?round($doneCount/count($scheduledItems)*100):0; ?>
            <section class="habits-today-summary"><div><span>Scheduled on <?= e($selectedDate) ?></span><strong><?= $doneCount ?> / <?= count($scheduledItems) ?> complete</strong></div><progress class="habits-progress-track" max="100" value="<?= $rate ?>" aria-label="Today's scheduled habit completion"><?= $rate ?>%</progress><b><?= $rate ?>%</b></section>
            <section class="habits-section">
                <div class="habits-section-heading"><div><p class="eyebrow">DAILY ROUTINES</p><h2>Scheduled habits</h2></div><a class="habits-button habits-button-secondary" href="<?= e(url('habits/?archived=1')) ?>">View archive (<?= count($archivedHabits) ?>)</a></div>
                <?php if (!$scheduledItems): ?><div class="habits-empty-state"><span aria-hidden="true">◷</span><h3>No habits scheduled on this date</h3><p>Add an everyday habit or choose a weekday schedule. Period targets are listed separately.</p><?php if (!$overview['habits']): ?><a class="habits-button habits-button-primary" href="<?= e(url('habits/index.php?action=create')) ?>">Create your first habit</a><?php endif; ?></div><?php else: ?><div class="habits-list"><?php foreach ($scheduledItems as $item): ?><?php $entry=$item['today_entry']; ?><article class="habit-card color-border-<?= e($item['color_key']) ?>"><div class="habit-card-main"><div class="habit-card-title"><span class="habit-color-dot color-<?= e($item['color_key']) ?>"></span><a href="<?= e(url('habits/index.php?action=view&id=' . (int) $item['id'] . '&date=' . $selectedDate)) ?>"><?= e($item['name']) ?></a><?php if ($item['stats']['current_streak']>0): ?><span class="habit-streak">🔥 <?= (int) $item['stats']['current_streak'] ?> streak</span><?php endif; ?></div><?php if ($item['description']): ?><p><?= e($item['description']) ?></p><?php endif; ?><small><?= e($frequencyLabels[$item['frequency']]) ?><?php if ($item['daily_target']!==null): ?> · <?= e((string) $item['daily_target']) ?> <?= e($item['unit']??'') ?> target<?php endif; ?></small><?php if ($item['daily_target']!==null): ?><div class="habit-progress-copy"><?= e((string)($entry['progress']??'0.00')) ?> / <?= e((string)$item['daily_target']) ?> <?= e($item['unit']??'') ?> · <?= e((string)$item['progress_percent']) ?>%</div><?php endif; ?></div><form class="habit-entry-form" method="post" action="<?= e(url('habits/')) ?>"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_entry"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="entry_date" value="<?= e($selectedDate) ?>"><?php if ($item['daily_target']!==null): ?><label>Progress<input type="number" name="progress" min="0" max="99999999.99" step="0.01" value="<?= e((string)($entry['progress']??'0.00')) ?>" aria-label="Progress <?= e($item['name']) ?>" data-progress-target="<?= e((string)$item['daily_target']) ?>"></label><?php endif; ?><label class="habit-complete-toggle"><input type="hidden" name="completed" value="0"><input type="checkbox" name="completed" value="1" <?= !empty($entry['completed'])?'checked':'' ?>>Complete</label><button class="habits-button <?= !empty($entry['completed'])?'habits-button-secondary':'habits-button-primary' ?>" type="submit"><?= !empty($entry['completed'])?'Save / undo':'Save progress' ?></button></form><form method="post" action="<?= e(url('habits/')) ?>" data-confirm="Archive this habit? Its history will be kept."><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="archive"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="habits-button habits-button-secondary" type="submit">Archive</button></form></article><?php endforeach; ?></div><?php endif; ?>
            </section>
            <section class="habits-section"><div class="habits-section-heading"><div><p class="eyebrow">FLEXIBLE FREQUENCY</p><h2>Weekly and monthly targets</h2></div></div><?php if (!$periodItems): ?><p class="habits-muted">No period targets configured.</p><?php else: ?><div class="habits-list"><?php foreach($periodItems as $item): ?><?php $period=$item['frequency']==='weekly_target'?$item['stats']['weekly_progress']:$item['stats']['monthly_progress'];$entry=$item['today_entry']; ?><article class="habit-card color-border-<?= e($item['color_key']) ?>"><div class="habit-card-main"><div class="habit-card-title"><span class="habit-color-dot color-<?= e($item['color_key']) ?>"></span><a href="<?= e(url('habits/index.php?action=view&id='.(int)$item['id'].'&date='.$selectedDate)) ?>"><?= e($item['name']) ?></a></div><p><?= e($frequencyLabels[$item['frequency']]) ?> · <?= (int)$item['period_target'] ?> unique days per <?= $item['frequency']==='weekly_target'?'Monday-start week':'calendar month' ?></p><small>This period: <?= (int)$period['completed_days'] ?> / <?= (int)$period['target'] ?> · <?= e((string)$period['percent']) ?>%</small></div><form class="habit-entry-form" method="post" action="<?= e(url('habits/')) ?>"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_entry"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="entry_date" value="<?= e($selectedDate) ?>"><?php if($item['daily_target']!==null): ?><label>Progress<input type="number" name="progress" min="0" max="99999999.99" step="0.01" value="<?= e((string)($entry['progress']??'0.00')) ?>" data-progress-target="<?= e((string)$item['daily_target']) ?>" aria-label="Progress <?= e($item['name']) ?>"></label><?php endif; ?><label class="habit-complete-toggle"><input type="hidden" name="completed" value="0"><input type="checkbox" name="completed" value="1" <?= !empty($entry['completed'])?'checked':'' ?>>Count this day</label><button class="habits-button <?= !empty($entry['completed'])?'habits-button-secondary':'habits-button-primary' ?>" type="submit"><?= !empty($entry['completed'])?'Update day':'Record day' ?></button></form><form method="post" action="<?= e(url('habits/')) ?>" data-confirm="Archive this habit? Its history will be kept."><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="archive"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="habits-button habits-button-secondary" type="submit">Archive</button></form></article><?php endforeach; ?></div><?php endif; ?></section>
            <?php if ($overview['habits']): ?><section class="habits-section"><div class="habits-section-heading"><div><p class="eyebrow">CONSISTENCY</p><h2>Current streaks</h2></div></div><div class="habits-streak-grid"><?php foreach($overview['habits'] as $item): if($item['stats']['current_streak']<1)continue; ?><a href="<?= e(url('habits/index.php?action=view&id='.(int)$item['id'].'&date='.$selectedDate)) ?>"><span><?= e($item['name']) ?></span><strong>🔥 <?= (int)$item['stats']['current_streak'] ?></strong></a><?php endforeach; ?></div></section><?php endif; ?>
        <?php endif; ?>
        <?php if ($archivedView): ?><?php if (!$archivedHabits): ?><div class="habits-empty-state"><h3>No archived habits</h3><p>Archived habits and their records will appear here.</p></div><?php else: ?><div class="habits-list"><?php foreach($archivedHabits as $item): ?><article class="habit-card"><div class="habit-card-main"><div class="habit-card-title"><a href="<?= e(url('habits/index.php?action=view&id='.(int)$item['id'].'&date='.$selectedDate)) ?>"><?= e($item['name']) ?></a></div><small>Archived <?= e($item['archived_at']) ?> · records are preserved</small></div><form method="post" action="<?= e(url('habits/')) ?>"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="restore"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="habits-button habits-button-primary" type="submit">Restore</button></form></article><?php endforeach; ?></div><?php endif; ?><?php endif; ?>
    <?php endif; ?>
</div>
<?php require LIFEOS_ROOT . '/includes/footer.php'; ?>
