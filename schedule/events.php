<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/Modules/Schedule/init.php';
$pageTitle = 'Schedule · Event';
$currentPage = 'schedule';
$requestedAction = $_GET['action'] ?? '';
$action = is_string($requestedAction) ? $requestedAction : '';
$requestedId = $_GET['id'] ?? 0;
$id = is_scalar($requestedId) ? max(0, (int)$requestedId) : 0;
$defaultEventDate = schedule_query_date($_GET['date'] ?? null);
$event = $id > 0 && $scheduleService ? $scheduleService->getEvent($id) : null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $scheduleService && !$dbError) {
    verify_csrf();
    $postAction = request_string($_POST['action'] ?? '');
    try {
        if ($postAction === 'sync_holidays') {
            $count = $scheduleService->syncHolidays((int)($_POST['year'] ?? date('Y')), true);
            set_flash('success', "Data hari libur diperbarui ({$count} hari). Sumber: Nager.Date.");
            redirect(url('schedule/index.php?date=' . (int)($_POST['year'] ?? date('Y')) . '-01-01'));
        } elseif ($postAction === 'reschedule') {
            $scheduleService->rescheduleEvent((int)($_POST['id'] ?? 0), (string)($_POST['start_date'] ?? ''), !empty($_POST['start_time']) ? (string)$_POST['start_time'] : null, !empty($_POST['occurrence_date']) ? (string)$_POST['occurrence_date'] : null);
            set_flash('success', 'Event berhasil dijadwal ulang.');
            redirect(url('schedule/index.php?date=' . rawurlencode((string)($_POST['return_date'] ?? date('Y-m-d')))));
        } elseif ($postAction === 'delete') {
            $scheduleService->softDeleteEvent((int)($_POST['id'] ?? 0));
            set_flash('success', 'Event dihapus dari kalender.');
            redirect(url('schedule/index.php'));
        } elseif (in_array($postAction, ['create', 'update'], true)) {
            $data = [
                'title'=>$_POST['title'] ?? '', 'description'=>$_POST['description'] ?? '',
                'start_date'=>$_POST['start_date'] ?? '', 'start_time'=>$_POST['start_time'] ?? '',
                'end_date'=>$_POST['end_date'] ?? '', 'end_time'=>$_POST['end_time'] ?? '',
                'all_day'=>isset($_POST['all_day']), 'category_id'=>$_POST['category_id'] ?? null,
                'status'=>$_POST['status'] ?? 'upcoming', 'recurrence'=>$_POST['recurrence'] ?? 'none',
                'recurrence_until'=>$_POST['recurrence_until'] ?? null, 'reminder_minutes'=>$_POST['reminder_minutes'] ?? null,
                'task_id'=>$_POST['task_id'] ?? null,
            ];
            if ($postAction === 'update') {
                $scheduleService->updateEvent((int)($_POST['id'] ?? 0), $data);
                set_flash('success', 'Event berhasil diperbarui.');
            } else {
                $scheduleService->createEvent($data);
                set_flash('success', 'Event berhasil dibuat.');
            }
            redirect(url('schedule/index.php?date=' . rawurlencode((string)$data['start_date'])));
        } else {
            http_response_code(400);
            $error = 'Tindakan Schedule tidak dikenali.';
        }
    } catch (Throwable $e) { $error = $e->getMessage(); }
}
$editing = in_array($action, ['create','edit'], true);
$showDetail = !$editing && $event !== null;
if ($action === 'edit' && !$event && $error === null) $error = 'Event tidak ditemukan.';
$categories = $scheduleService && !$dbError ? $scheduleService->listCategories() : [];
$tasks = $scheduleService && !$dbError ? $scheduleService->listTasks() : [];
$values = $event ?? ['title'=>'','description'=>'','start_date'=>$defaultEventDate,'start_time'=>'09:00:00','end_date'=>$defaultEventDate,'end_time'=>'10:00:00','all_day'=>0,'category_id'=>null,'status'=>'upcoming','recurrence'=>'none','recurrence_until'=>null,'reminder_minutes'=>null,'task_id'=>null];
$readEvents = !$editing && $scheduleService && !$dbError ? $scheduleService->listEvents(['start'=>date('Y-m-d', strtotime('-30 days')), 'end'=>date('Y-m-d', strtotime('+365 days'))]) : [];
require LIFEOS_ROOT . '/includes/header.php';
?>
<div class="schedule-page">
    <?php render_schedule_nav('events'); ?>
    <?php render_schedule_alerts($dbError); ?>
    <?php if ($error): ?><div class="schedule-alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if ($editing): ?>
        <header class="schedule-heading"><div><p class="eyebrow">AGENDA</p><h1><?= $event ? 'Ubah event' : 'Tambah event' ?></h1><p class="schedule-subtitle">Simpan waktu, kategori, pengulangan, pengingat, dan kaitan task.</p></div></header>
        <form class="schedule-form" method="post" action="<?= e(url('schedule/events.php')) ?>" data-schedule-form>
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="<?= $event ? 'update' : 'create' ?>"><?php if ($event): ?><input type="hidden" name="id" value="<?= (int)$event['id'] ?>"><?php endif; ?>
            <label>Judul *<input type="text" name="title" maxlength="200" required value="<?= e($values['title']) ?>"></label>
            <label class="field-wide">Deskripsi<textarea name="description" rows="3"><?= e($values['description'] ?? '') ?></textarea></label>
            <label>Tanggal mulai *<input type="date" name="start_date" required value="<?= e($values['start_date']) ?>"></label>
            <label>Tanggal selesai *<input type="date" name="end_date" required value="<?= e($values['end_date']) ?>"></label>
            <label>Jam mulai<input type="time" name="start_time" data-time-field value="<?= e(substr((string)$values['start_time'],0,5)) ?>"></label>
            <label>Jam selesai<input type="time" name="end_time" data-time-field value="<?= e(substr((string)$values['end_time'],0,5)) ?>"></label>
            <label class="checkbox-field"><input type="checkbox" name="all_day" value="1" data-all-day <?= !empty($values['all_day']) ? 'checked' : '' ?>> Sepanjang hari</label>
            <label>Kategori<select name="category_id"><option value="">Tanpa kategori</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= (int)($values['category_id'] ?? 0)===(int)$category['id']?'selected':'' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></label>
            <label>Status<select name="status"><option value="upcoming" <?= $values['status']==='upcoming'?'selected':'' ?>>Akan datang</option><option value="completed" <?= $values['status']==='completed'?'selected':'' ?>>Selesai</option><option value="cancelled" <?= $values['status']==='cancelled'?'selected':'' ?>>Dibatalkan</option></select></label>
            <label>Pengulangan<select name="recurrence" data-recurrence><option value="none">Tidak berulang</option><?php foreach (['daily'=>'Harian','weekly'=>'Mingguan','monthly'=>'Bulanan','yearly'=>'Tahunan'] as $value=>$label): ?><option value="<?= $value ?>" <?= $values['recurrence']===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label>
            <label data-recurrence-until>Tanggal akhir pengulangan<input type="date" name="recurrence_until" value="<?= e($values['recurrence_until'] ?? '') ?>"></label>
            <label>Pengingat<select name="reminder_minutes"><option value="">Tidak ada pengingat</option><?php foreach ([0=>'Saat event dimulai',5=>'5 menit sebelumnya',15=>'15 menit sebelumnya',30=>'30 menit sebelumnya',60=>'1 jam sebelumnya',1440=>'1 hari sebelumnya'] as $minutes=>$label): ?><option value="<?= $minutes ?>" <?= (string)($values['reminder_minutes'] ?? '')===(string)$minutes?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
            <label>Task terkait<select name="task_id"><option value="">Tanpa task</option><?php foreach ($tasks as $task): ?><option value="<?= (int)$task['id'] ?>" <?= (int)($values['task_id'] ?? 0)===(int)$task['id']?'selected':'' ?>><?= e($task['title']) ?></option><?php endforeach; ?></select></label>
            <div class="schedule-form-actions field-wide"><a class="schedule-btn schedule-btn-secondary" href="<?= e(url('schedule/index.php?date=' . rawurlencode($values['start_date']))) ?>">Batal</a><button class="schedule-btn schedule-btn-primary" type="submit">Simpan event</button></div>
        </form>
    <?php elseif ($showDetail): ?>
        <header class="schedule-heading"><div><p class="eyebrow">DETAIL AGENDA</p><h1><?= e($event['title']) ?></h1><p class="schedule-subtitle"><?= e($event['description'] ?? '') ?></p></div><div class="schedule-row-actions"><a class="schedule-btn schedule-btn-secondary" href="<?= e(url('schedule/events.php?action=edit&id=' . $event['id'])) ?>">Ubah</a><a class="schedule-btn schedule-btn-secondary" href="<?= e(url('schedule/index.php?date=' . rawurlencode($event['start_date']))) ?>">Kalender</a></div></header>
        <article class="schedule-detail-card"><dl><dt>Status</dt><dd><?= e(['upcoming'=>'Akan datang','completed'=>'Selesai','cancelled'=>'Dibatalkan'][$event['status']] ?? $event['status']) ?></dd><dt>Waktu</dt><dd><?= e($event['start_date']) ?><?= !$event['all_day'] ? ' · ' . e(substr((string)$event['start_time'],0,5)) . '–' . e(substr((string)$event['end_time'],0,5)) : ' · Sepanjang hari' ?></dd><dt>Kategori</dt><dd><?= e($event['category_name'] ?? 'Tanpa kategori') ?></dd><dt>Pengulangan</dt><dd><?= e(['none'=>'Tidak berulang','daily'=>'Harian','weekly'=>'Mingguan','monthly'=>'Bulanan','yearly'=>'Tahunan'][$event['recurrence']] ?? $event['recurrence']) ?><?= !empty($event['recurrence_until']) ? ' sampai ' . e($event['recurrence_until']) : '' ?></dd><dt>Pengingat</dt><dd><?= $event['reminder_minutes'] === null ? 'Tidak aktif' : e((string)$event['reminder_minutes']) . ' menit sebelum event' ?></dd><dt>Task terkait</dt><dd><?php if (!empty($event['task_id']) && !empty($event['task_title'])): ?><?= e($event['task_title']) ?> · <a href="<?= e(url('tasks/index.php?action=view&id=' . (int)$event['task_id'])) ?>">Buka Tasks</a><?php elseif (!empty($event['task_id'])): ?>Task terkait tidak tersedia<?php else: ?>Tidak ada<?php endif; ?></dd></dl><form method="post" action="<?= e(url('schedule/events.php')) ?>" data-confirm="Hapus event ini?"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$event['id'] ?>"><button class="schedule-btn schedule-btn-danger" type="submit">Hapus event</button></form></article>
    <?php else: ?>
        <header class="schedule-heading"><div><p class="eyebrow">AGENDA</p><h1>Event</h1><p class="schedule-subtitle">Kelola agenda dan lihat keterkaitan dengan task.</p></div><a class="schedule-btn schedule-btn-primary" href="<?= e(url('schedule/events.php?action=create')) ?>">＋ Tambah event</a></header>
        <div class="schedule-event-list">
            <?php if (!$readEvents): ?><div class="schedule-empty-state"><p>Belum ada event.</p></div><?php endif; ?>
            <?php foreach ($readEvents as $item): ?><article class="schedule-event-row"><div><span class="event-status status-<?= e($item['status']) ?>"><?= e(['upcoming'=>'Akan datang','completed'=>'Selesai','cancelled'=>'Dibatalkan'][$item['status']] ?? $item['status']) ?></span><h2><a href="<?= e(url('schedule/events.php?id=' . $item['id'])) ?>"><?= e($item['title']) ?></a></h2><p><?= e($item['start_date']) ?><?= !$item['all_day'] ? ' · ' . e(substr((string)$item['start_time'],0,5)) . '–' . e(substr((string)$item['end_time'],0,5)) : ' · Sepanjang hari' ?><?php if (!empty($item['category_name'])): ?> · <?= e($item['category_name']) ?><?php endif; ?><?php if (!empty($item['task_title'])): ?> · Task: <?= e($item['task_title']) ?><?php endif; ?></p></div><div class="schedule-row-actions"><a class="schedule-btn schedule-btn-secondary" href="<?= e(url('schedule/events.php?action=edit&id=' . $item['id'])) ?>">Ubah</a><form method="post" action="<?= e(url('schedule/events.php')) ?>" data-confirm="Hapus event ini?"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="schedule-btn schedule-btn-danger" type="submit">Hapus</button></form></div></article><?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require LIFEOS_ROOT . '/includes/footer.php'; ?>
