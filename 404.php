<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

http_response_code(404);
$pageTitle = 'Halaman tidak ditemukan';
$currentPage = '';

require LIFEOS_ROOT . '/includes/header.php';
?>
<section class="module-placeholder" aria-labelledby="not-found-title">
    <p class="eyebrow">404 · PAGE NOT FOUND</p>
    <h1 id="not-found-title">Halaman tidak ditemukan</h1>
    <p class="module-description">Alamat yang Anda buka tidak tersedia di LIFEOS. Periksa kembali alamatnya atau kembali ke dashboard.</p>
    <p><a class="not-found-link" href="<?= e(url('')) ?>">Kembali ke Dashboard</a></p>
</section>
<?php require LIFEOS_ROOT . '/includes/footer.php'; ?>
