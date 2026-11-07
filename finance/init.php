<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once LIFEOS_ROOT . '/app/Modules/Finance/FinanceService.php';

use App\Modules\Finance\FinanceService;

$financeService = null;
$dbError = null;

/**
 * Execute the finance schema SQL file in an idempotent way.
 * Uses CREATE TABLE IF NOT EXISTS statements, so it is safe to run multiple times.
 */
function upgrade_finance_schema(): ?string
{
    $schemaPath = LIFEOS_ROOT . '/database/finance_schema.sql';
    if (!file_exists($schemaPath)) {
        return 'File schema tidak ditemukan: ' . $schemaPath;
    }

    $pdo = db();
    $sql = file_get_contents($schemaPath);
    if ($sql === false) {
        return 'Gagal membaca file schema.';
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
            error_log('[FinanceUpgrade] ' . $e->getMessage() . ' in statement: ' . $stmt);
        }
    }
    return null;
}

try {
    $financeService = new FinanceService();
    if (!$financeService->isInstalled()) {
        $upgradeError = upgrade_finance_schema();
        if ($upgradeError === null) {
            if (!$financeService->isInstalled()) {
                $dbError = 'Tabel modul Finance belum dibuat di database meskipun upgrade dijalankan.';
            }
        } else {
            $dbError = $upgradeError;
        }
    }
} catch (Throwable $e) {
    $dbError = 'Database LIFEOS belum terhubung: ' . $e->getMessage();
}

$financeNavigation = [
    ['slug' => 'overview', 'label' => 'Ringkasan', 'path' => 'index.php'],
    ['slug' => 'transactions', 'label' => 'Transaksi', 'path' => 'transactions.php'],
    ['slug' => 'accounts', 'label' => 'Akun', 'path' => 'accounts.php'],
    ['slug' => 'budgets', 'label' => 'Anggaran', 'path' => 'budgets.php'],
    ['slug' => 'loans', 'label' => 'Pinjaman', 'path' => 'loans.php'],
    ['slug' => 'bills', 'label' => 'Tagihan', 'path' => 'bills.php'],
];

/**
 * Helper untuk render notifikasi flash dan error sistem.
 */
function render_finance_alerts(?string $dbError = null): void
{
    $flash = get_flash();
    if ($flash): ?>
        <div class="finance-alert alert-<?= e($flash['type'] ?? 'info') ?>" role="alert">
            <span class="alert-icon"><?= $flash['type'] === 'success' ? '✓' : 'ℹ' ?></span>
            <div class="alert-content"><?= e($flash['message']) ?></div>
        </div>
    <?php endif;

    if ($dbError !== null): ?>
        <div class="finance-alert alert-warning" role="alert">
            <span class="alert-icon">⚠</span>
            <div class="alert-content">
                <strong>Status Database:</strong> <?= e($dbError) ?>
            </div>
        </div>
    <?php endif;
}

/**
 * Helper untuk render navigasi sub-halaman Finance.
 */
function render_finance_nav(string $activeSlug): void
{
    global $financeNavigation;
    ?>
    <nav class="finance-navigation" aria-label="Navigasi Finance">
        <?php foreach ($financeNavigation as $item): ?>
            <a
                href="<?= e(url('finance/' . $item['path'])) ?>"
                class="<?= $activeSlug === $item['slug'] ? 'active' : '' ?>"
                <?= $activeSlug === $item['slug'] ? 'aria-current="page"' : '' ?>
            >
                <?= e($item['label']) ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php
}
