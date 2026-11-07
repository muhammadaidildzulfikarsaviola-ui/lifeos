<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';

$pageTitle = 'Finance · Akun';
$currentPage = 'finance';

// Handle POST actions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    try {
        if ($financeService === null) {
            throw new RuntimeException('Layanan database belum siap.');
        }

        if ($action === 'create') {
            $data = [
                'name' => $_POST['name'] ?? '',
                'type' => $_POST['type'] ?? 'bank',
                'account_number' => $_POST['account_number'] ?? null,
                'initial_balance' => (float) ($_POST['initial_balance'] ?? 0),
                'color' => $_POST['color'] ?? '#344f41',
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'notes' => $_POST['notes'] ?? null,
            ];

            $financeService->saveAccount($data);
            set_flash('success', 'Akun berhasil ditambahkan.');
            redirect('finance/accounts.php');
        } elseif ($action === 'update') {
            $id = (int) ($_POST['id'] ?? 0);
            $data = [
                'name' => $_POST['name'] ?? '',
                'type' => $_POST['type'] ?? 'bank',
                'account_number' => $_POST['account_number'] ?? null,
                'initial_balance' => (float) ($_POST['initial_balance'] ?? 0),
                'color' => $_POST['color'] ?? '#344f41',
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'notes' => $_POST['notes'] ?? null,
            ];

            $financeService->saveAccount($data, $id);
            set_flash('success', 'Akun berhasil diperbarui.');
            redirect('finance/accounts.php');
        } elseif ($action === 'toggle_active') {
            $id = (int) ($_POST['id'] ?? 0);
            $active = (bool) (int) ($_POST['active'] ?? 0);
            $financeService->toggleAccountActive($id, $active);
            set_flash('success', $active ? 'Akun berhasil diaktifkan kembali.' : 'Akun berhasil diarsipkan / dinonaktifkan.');
            redirect('finance/accounts.php');
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $financeService->deleteAccount($id);
            set_flash('success', 'Akun berhasil dihapus.');
            redirect('finance/accounts.php');
        }
    } catch (InvalidArgumentException $e) {
        set_flash('error', $e->getMessage());
        redirect('finance/accounts.php');
    } catch (Throwable $e) {
        set_flash('error', 'Gagal memproses akun: ' . $e->getMessage());
        redirect('finance/accounts.php');
    }
}

// Data fetching
$accounts = [];
$activeAction = $_GET['action'] ?? '';
$accountId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$editingAccount = null;
$viewAccount = null;
$accountTransactions = [];

if ($financeService !== null && $dbError === null) {
    try {
        $accounts = $financeService->getAccounts(false);

        if ($activeAction === 'edit' && $accountId > 0) {
            $editingAccount = $financeService->getAccountById($accountId);
        } elseif ($activeAction === 'view' && $accountId > 0) {
            $viewAccount = $financeService->getAccountById($accountId);
            if ($viewAccount) {
                $accountTransactions = $financeService->getTransactions(['account_id' => $accountId], 50, 0);
            }
        }
    } catch (Throwable $e) {
        $dbError = 'Gagal memuat data akun: ' . $e->getMessage();
    }
}

require LIFEOS_ROOT . '/includes/header.php';
?>

<div class="finance-page">

    <!-- Page Heading -->
    <section class="finance-heading">
        <div>
            <p class="eyebrow">MONEY MANAGEMENT</p>
            <h1>Akun Keuangan</h1>
            <p class="finance-subtitle">
                Kelola kas tunai, rekening bank, tabungan, dan e-wallet. Saldo dihitung otomatis dari mutasi ledger.
            </p>
        </div>

        <div class="finance-header-actions">
            <button
                type="button"
                class="finance-btn finance-btn-primary"
                data-open-modal="modalAccount"
            >
                <span>＋</span> Tambah akun
            </button>
        </div>
    </section>

    <!-- Navigation -->
    <?php render_finance_nav('accounts'); ?>

    <!-- Alerts -->
    <?php render_finance_alerts($dbError); ?>

    <!-- View Account Details Mode -->
    <?php if ($viewAccount !== null): ?>
        <article class="finance-panel finance-detail-panel">
            <div class="finance-panel-heading">
                <div>
                    <h2>Detail Akun: <?= e($viewAccount['name']) ?></h2>
                    <p>
                        Tipe: <?= e(strtoupper($viewAccount['type'])) ?>
                        <?php if (!empty($viewAccount['account_number'])): ?>
                            · No. Rek: <?= e($viewAccount['account_number']) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="btn-group">
                    <a
                        href="<?= e(url('finance/accounts.php?action=edit&id=' . $viewAccount['id'])) ?>"
                        class="finance-btn finance-btn-secondary finance-btn-sm"
                    >
                        Edit Akun
                    </a>
                    <a href="<?= e(url('finance/accounts.php')) ?>" class="finance-btn finance-btn-secondary finance-btn-sm">
                        ← Kembali ke Daftar Akun
                    </a>
                </div>
            </div>

            <div class="finance-stats-grid">
                <div class="finance-stat-card">
                    <span class="stat-card-label">Saldo Awal</span>
                    <div class="stat-card-value">
                        <?= format_rupiah($viewAccount['initial_balance']) ?>
                    </div>
                </div>
                <div class="finance-stat-card">
                    <span class="stat-card-label label-success">Total Dana Masuk</span>
                    <div class="stat-card-value value-success">
                        +<?= format_rupiah($viewAccount['total_incoming']) ?>
                    </div>
                </div>
                <div class="finance-stat-card">
                    <span class="stat-card-label label-danger">Total Dana Keluar</span>
                    <div class="stat-card-value value-danger">
                        -<?= format_rupiah($viewAccount['total_outgoing']) ?>
                    </div>
                </div>
                <div class="finance-stat-card stat-card-success">
                    <span class="stat-card-label label-primary">Saldo Saat Ini</span>
                    <div class="stat-card-value">
                        <?= format_rupiah($viewAccount['current_balance']) ?>
                    </div>
                </div>
            </div>

            <!-- Transaction History for this Account -->
            <h3 class="finance-subheading">
                Riwayat Transaksi pada Akun Ini
            </h3>

            <?php if (empty($accountTransactions)): ?>
                <div class="finance-empty-state compact-empty">
                    <p>Belum ada transaksi yang menggunakan akun ini.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="finance-table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Jenis</th>
                                <th>Deskripsi</th>
                                <th>Kategori</th>
                                <th class="text-right">Nominal</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($accountTransactions as $tx): ?>
                                <tr>
                                    <td class="td-date"><?= date('d M Y', strtotime($tx['transaction_date'])) ?></td>
                                    <td>
                                        <span class="finance-badge badge-<?= e($tx['type']) ?>">
                                            <?= $tx['type'] === 'income' ? '↗ Masuk' : ($tx['type'] === 'expense' ? '↙ Keluar' : '⇄ Transfer') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="td-desc-title"><?= e($tx['description']) ?></div>
                                        <?php if ($tx['type'] === 'transfer'): ?>
                                            <div class="stat-card-sub">
                                                <?= e($tx['account_name']) ?> → <?= e($tx['target_account_name'] ?? '') ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($tx['category_name'] ?? '—') ?></td>
                                    <td class="text-right">
                                        <span class="tx-amount amount-<?= e($tx['type']) ?>">
                                            <?= format_rupiah($tx['amount']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="finance-badge badge-status-<?= e($tx['status']) ?>">
                                            <?= ucfirst($tx['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>
    <?php endif; ?>

    <!-- Accounts Cards Grid -->
    <section class="finance-section">
        <div class="finance-section-heading">
            <div>
                <h2>Daftar Akun Keuangan</h2>
                <p>Total <?= count($accounts) ?> akun terdaftar.</p>
            </div>
        </div>

        <?php if (empty($accounts)): ?>
            <div class="finance-panel">
                <div class="finance-empty-state">
                    <div class="empty-state-icon">▣</div>
                    <h3>Belum ada akun keuangan</h3>
                    <p>Tambahkan akun seperti rekening bank, dompet kas tunai, atau dompet digital untuk mulai mencatat keuangan.</p>
                    <button
                        type="button"
                        class="finance-btn finance-btn-primary finance-btn-sm"
                        data-open-modal="modalAccount"
                    >
                        Tambah akun pertama
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="accounts-grid">
                <?php foreach ($accounts as $acc): ?>
                    <article class="account-card <?= (int) $acc['is_active'] === 0 ? 'is-inactive' : '' ?>">
                        <div>
                            <div class="account-card-header">
                                <div>
                                    <h3 class="account-card-title"><?= e($acc['name']) ?></h3>
                                    <div class="account-card-type">
                                        <?= strtoupper(e($acc['type'])) ?>
                                        <?php if (!empty($acc['account_number'])): ?>
                                            · <?= e($acc['account_number']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="finance-badge <?= (int) $acc['is_active'] === 1 ? 'badge-active' : 'badge-inactive' ?>">
                                    <?= (int) $acc['is_active'] === 1 ? 'Aktif' : 'Arsip' ?>
                                </span>
                            </div>

                            <div class="extracted-style-1">
                                <div class="account-balance-label">Saldo Saat Ini</div>
                                <div class="account-balance-val">
                                    <?= format_rupiah($acc['current_balance']) ?>
                                </div>
                            </div>

                            <div class="account-meta-stats">
                                <span>Saldo Awal: <?= format_rupiah($acc['initial_balance']) ?></span>
                                <span>·</span>
                                <span><?= e((string) $acc['transaction_count']) ?> mutasi</span>
                            </div>
                        </div>

                        <div class="account-card-actions">
                            <a
                                href="<?= e(url('finance/accounts.php?action=view&id=' . $acc['id'])) ?>"
                                class="finance-btn finance-btn-secondary finance-btn-sm"
                            >
                                Detail & Mutasi
                            </a>
                            <a
                                href="<?= e(url('finance/accounts.php?action=edit&id=' . $acc['id'])) ?>"
                                class="finance-btn finance-btn-secondary finance-btn-sm"
                            >
                                Edit
                            </a>

                            <form method="POST" action="<?= e(url('finance/accounts.php')) ?>" class="inline-form">
                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="id" value="<?= e((string) $acc['id']) ?>">
                                <input type="hidden" name="active" value="<?= (int) $acc['is_active'] === 1 ? '0' : '1' ?>">
                                <button
                                    type="submit"
                                    class="finance-btn finance-btn-secondary finance-btn-sm"
                                    title="<?= (int) $acc['is_active'] === 1 ? 'Arsipkan Akun' : 'Aktifkan Kembali' ?>"
                                >
                                    <?= (int) $acc['is_active'] === 1 ? 'Arsipkan' : 'Aktifkan' ?>
                                </button>
                            </form>

                            <?php if ((int) $acc['transaction_count'] === 0): ?>
                                <form
                                    method="POST"
                                    action="<?= e(url('finance/accounts.php')) ?>"
                                    class="inline-form"
                                    data-confirm="Hapus akun ini secara permanen?"
                                >
                                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= e((string) $acc['id']) ?>">
                                    <button type="submit" class="finance-btn finance-btn-danger finance-btn-sm" title="Hapus Akun">
                                        Hapus
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

</div>

<!-- Modal Tambah / Edit Akun -->
<div
    class="finance-modal-backdrop <?= ($activeAction === 'create' || $editingAccount !== null) ? 'active' : '' ?>"
    id="modalAccount"
>
    <div class="finance-modal">
        <div class="finance-modal-header">
            <h3><?= $editingAccount !== null ? 'Edit Akun' : 'Tambah Akun Baru' ?></h3>
            <button
                type="button"
                class="modal-close-btn"
                data-redirect="<?= e(url('finance/accounts.php')) ?>"
                aria-label="Tutup"
            >
                ✕
            </button>
        </div>

        <form method="POST" action="<?= e(url('finance/accounts.php')) ?>">
            <div class="finance-modal-body">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="<?= $editingAccount !== null ? 'update' : 'create' ?>">
                <?php if ($editingAccount !== null): ?>
                    <input type="hidden" name="id" value="<?= e((string) $editingAccount['id']) ?>">
                <?php endif; ?>

                <div class="finance-form">
                    <div class="form-group">
                        <label for="acc_name">Nama Akun *</label>
                        <input
                            type="text"
                            name="name"
                            id="acc_name"
                            class="form-control"
                            placeholder="Contoh: Rekening BCA, Dompet Tunai, GoPay"
                            value="<?= e($editingAccount['name'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="acc_type">Tipe Akun *</label>
                            <select name="type" id="acc_type" class="form-select" required>
                                <?php $currentType = $editingAccount['type'] ?? 'bank'; ?>
                                <option value="bank" <?= $currentType === 'bank' ? 'selected' : '' ?>>Rekening Bank</option>
                                <option value="cash" <?= $currentType === 'cash' ? 'selected' : '' ?>>Kas Tunai (Dompet)</option>
                                <option value="ewallet" <?= $currentType === 'ewallet' ? 'selected' : '' ?>>E-Wallet / Dompet Digital</option>
                                <option value="savings" <?= $currentType === 'savings' ? 'selected' : '' ?>>Tabungan Khusus</option>
                                <option value="other" <?= $currentType === 'other' ? 'selected' : '' ?>>Lainnya</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="acc_number">Nomor Rekening / Akun (Opsional)</label>
                            <input
                                type="text"
                                name="account_number"
                                id="acc_number"
                                class="form-control"
                                placeholder="Nomor akun atau rekening"
                                value="<?= e($editingAccount['account_number'] ?? '') ?>"
                            >
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="acc_initial_balance">Saldo Awal (Rp)</label>
                            <input
                                type="number"
                                step="any"
                                name="initial_balance"
                                id="acc_initial_balance"
                                class="form-control"
                                placeholder="0"
                                value="<?= e((string) ($editingAccount['initial_balance'] ?? 0)) ?>"
                            >
                            <p class="form-hint">Saldo mula-mula saat akun ini dicatat pertama kali.</p>
                        </div>

                        <div class="form-group">
                            <label for="acc_color">Warna Label</label>
                            <input
                                type="color"
                                name="color"
                                id="acc_color"
                                class="form-control input-color-picker"
                                value="<?= e($editingAccount['color'] ?? '#344f41') ?>"
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="acc_notes">Catatan (Opsional)</label>
                        <textarea
                            name="notes"
                            id="acc_notes"
                            class="form-textarea"
                            placeholder="Catatan tambahan mengenai akun ini..."
                        ><?= e($editingAccount['notes'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group form-checkbox-label">
                        <input
                            type="checkbox"
                            name="is_active"
                            id="acc_active"
                            value="1"
                            <?= (!isset($editingAccount) || (int) $editingAccount['is_active'] === 1) ? 'checked' : '' ?>
                        >
                        <label for="acc_active">
                            Akun Aktif (tampilkan pada pilihan transaksi dan ringkasan)
                        </label>
                    </div>
                </div>
            </div>

            <div class="finance-modal-footer">
                <button
                    type="button"
                    class="finance-btn finance-btn-secondary"
                    data-redirect="<?= e(url('finance/accounts.php')) ?>"
                >
                    Batal
                </button>
                <button type="submit" class="finance-btn finance-btn-primary">
                    <?= $editingAccount !== null ? 'Simpan Perubahan' : 'Tambah Akun' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require LIFEOS_ROOT . '/includes/footer.php'; ?>
