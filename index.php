<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Overview';
$currentPage = 'dashboard';
$dbConnected = false;
$dbError = null;

try {
    db()->query('SELECT 1');
    $dbConnected = true;
} catch (Throwable $exception) {
    $dbError = $exception->getMessage();
}

require __DIR__ . '/includes/header.php';
?>
<section class="welcome">
    <p class="eyebrow">PERSONAL WORKSPACE</p>
    <h1>Selamat datang di LIFEOS.</h1>
    <p>Satu tempat untuk mengelola hal-hal yang penting dalam hidup.</p>
    <div class="status-row">
        <span class="status-dot"></span>
        <strong>Application is running</strong>
        <span class="muted">PHP <?= e(PHP_VERSION) ?></span>
    </div>
</section>
<section class="section-heading"><div><p class="eyebrow">WORKSPACE</p><h2>Modul LIFEOS</h2></div></section>
<div class="module-grid">
<?php
$modules = [
    ['finance', 'Finance', 'Pemasukan, pengeluaran, akun, anggaran, dan tagihan.', '↗'],
    ['schedule', 'Schedule', 'Agenda, kalender, shift, dan pengingat.', '▦'],
    ['tasks', 'Tasks', 'Pekerjaan, prioritas, subtugas, dan progres.', '☷'],
    ['habits', 'Habits', 'Rutinitas dan konsistensi harian.', '◷'],
    ['journal', 'Journal', 'Jurnal harian dan refleksi.', '▤'],
    ['notes', 'Notes', 'Catatan, checklist, dan lampiran.', '▧'],
    ['goals', 'Goals', 'Tujuan dan milestone.', '◎'],
    ['projects', 'Projects', 'Proyek, tenggat, dan progres.', '◇'],
    ['reports', 'Reports', 'Ringkasan dan analisis aktivitas.', '▥'],
];
foreach ($modules as [$slug, $name, $description, $icon]):
?>
<a class="module-card" href="<?= e(url($slug . '/')) ?>">
    <span class="module-icon"><?= e($icon) ?></span>
    <span class="module-name"><?= e($name) ?></span>
    <span class="module-description"><?= e($description) ?></span>
    <span class="module-arrow">Open ↗</span>
</a>
<?php endforeach; ?>
</div>
<section class="system-card">
    <div><h2>Local environment</h2><p class="muted">Database connection status</p></div>
    <?php if ($dbConnected): ?>
        <span class="status-pill success">Connected</span>
    <?php else: ?>
        <span class="status-pill warning">Not connected</span>
    <?php endif; ?>
    <?php if (!$dbConnected): ?><p class="system-help">Periksa database LIFEOS dan konfigurasi di <code>config/database.php</code>. Detail teknis tersedia di PHP error log.</p><?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
