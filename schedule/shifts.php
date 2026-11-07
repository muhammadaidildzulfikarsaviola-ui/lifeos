<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/Modules/Schedule/init.php';
$pageTitle = 'Schedule · Shift kerja';
$currentPage = 'schedule';
$error = null;
$editingId = (int)($_GET['edit'] ?? 0);
$editingTemplate = null;
if ($editingId && $scheduleService && !$dbError) {
    foreach ($scheduleService->listShiftTemplates() as $row) if ((int)$row['id'] === $editingId) $editingTemplate = $row;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $scheduleService && !$dbError) {
    verify_csrf();
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0) ?: null;
            $scheduleService->saveShiftTemplate($_POST, $id);
            set_flash('success', $id ? 'Template shift diperbarui.' : 'Template shift dibuat.');
        } elseif ($action === 'delete') {
            $scheduleService->deleteShiftTemplate((int)($_POST['id'] ?? 0));
            set_flash('success', 'Template shift dihapus. Event yang sudah dibuat tetap tersimpan.');
        } elseif ($action === 'generate') {
            $count = $scheduleService->generateShiftEvents((int)($_POST['template_id'] ?? 0), (string)($_POST['from'] ?? ''), (string)($_POST['to'] ?? ''));
            set_flash('success', "{$count} shift baru ditambahkan. Event yang sudah ada tidak diduplikasi.");
        }
        redirect(url('schedule/shifts.php'));
    } catch (Throwable $e) { $error = $e->getMessage(); }
}
$templates = $scheduleService && !$dbError ? $scheduleService->listShiftTemplates() : [];
$weekdays = ['1'=>'Senin','2'=>'Selasa','3'=>'Rabu','4'=>'Kamis','5'=>'Jumat','6'=>'Sabtu','7'=>'Minggu'];
$selectedDays = $editingTemplate ? explode(',', $editingTemplate['weekdays']) : ['1','2','3','4','5'];
require LIFEOS_ROOT . '/includes/header.php';
?>
<div class="schedule-page">
    <?php render_schedule_nav('shifts'); ?>
    <?php render_schedule_alerts($dbError); ?>
    <?php if ($error): ?><div class="schedule-alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <header class="schedule-heading"><div><p class="eyebrow">TEMPLATE BERULANG</p><h1>Shift kerja</h1><p class="schedule-subtitle">Gunakan template untuk membuat event shift pada hari kerja pilihan.</p></div></header>
    <form class="schedule-form shift-template-form" method="post" action="<?= e(url('schedule/shifts.php')) ?>">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $editingTemplate ? (int)$editingTemplate['id'] : '' ?>">
        <label>Nama shift *<input name="name" maxlength="120" required value="<?= e($editingTemplate['name'] ?? '') ?>" placeholder="Contoh: Shift pagi"></label>
        <label>Mulai *<input type="time" name="start_time" required value="<?= e(substr((string)($editingTemplate['start_time'] ?? '09:00'),0,5)) ?>"></label>
        <label>Selesai *<input type="time" name="end_time" required value="<?= e(substr((string)($editingTemplate['end_time'] ?? '17:00'),0,5)) ?>"></label>
        <fieldset class="weekday-picker field-wide"><legend>Hari kerja</legend><?php foreach ($weekdays as $day=>$label): ?><label><input type="checkbox" name="weekdays[]" value="<?= $day ?>" <?= in_array($day, $selectedDays, true) ? 'checked' : '' ?>> <?= e($label) ?></label><?php endforeach; ?></fieldset>
        <label>Tanggal akhir pengulangan<input type="date" name="repeat_until" value="<?= e($editingTemplate['repeat_until'] ?? '') ?>"></label>
        <div class="schedule-form-actions field-wide"><?php if ($editingTemplate): ?><a class="schedule-btn schedule-btn-secondary" href="<?= e(url('schedule/shifts.php')) ?>">Batal ubah</a><?php endif; ?><button class="schedule-btn schedule-btn-primary" type="submit"><?= $editingTemplate ? 'Simpan perubahan' : 'Simpan template' ?></button></div>
    </form>
    <section class="shift-template-list"><h2>Template tersimpan</h2>
        <?php if (!$templates): ?><div class="schedule-empty-state"><p>Belum ada template shift.</p></div><?php endif; ?>
        <?php foreach ($templates as $template): ?><article class="shift-template-card"><div><h3><?= e($template['name']) ?></h3><p><?= e(substr($template['start_time'],0,5)) ?>–<?= e(substr($template['end_time'],0,5)) ?> · <?= e(implode(', ', array_map(static fn($d) => $weekdays[$d] ?? '', explode(',', $template['weekdays'])))) ?></p></div><div class="shift-template-actions"><a class="schedule-btn schedule-btn-secondary" href="<?= e(url('schedule/shifts.php?edit=' . $template['id'])) ?>">Ubah</a><form method="post" action="<?= e(url('schedule/shifts.php')) ?>" data-confirm="Hapus template ini? Event yang sudah dibuat tetap tersimpan."><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$template['id'] ?>"><button type="submit" class="schedule-btn schedule-btn-danger">Hapus</button></form></div><form class="shift-generate-form" method="post" action="<?= e(url('schedule/shifts.php')) ?>"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="generate"><input type="hidden" name="template_id" value="<?= (int)$template['id'] ?>"><label>Buat shift dari<input type="date" name="from" required value="<?= e(date('Y-m-d')) ?>"></label><label>sampai<input type="date" name="to" required value="<?= e(date('Y-m-d', strtotime('+30 days'))) ?>"></label><button type="submit" class="schedule-btn schedule-btn-primary">Buat event</button></form></article><?php endforeach; ?>
    </section>
</div>
<?php require LIFEOS_ROOT . '/includes/footer.php'; ?>
