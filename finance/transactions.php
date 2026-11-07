<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';

$pageTitle = 'Finance · Transaksi';
$currentPage = 'finance';

// Handle POST actions (Create, Update, Delete)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    try {
        if ($financeService === null) {
            throw new RuntimeException('Layanan database belum siap.');
        }

        if ($action === 'create') {
            $data = [
                'type' => $_POST['type'] ?? 'expense',
                'description' => $_POST['description'] ?? '',
                'amount' => (float) ($_POST['amount'] ?? 0),
                'transaction_date' => $_POST['transaction_date'] ?? date('Y-m-d'),
                'account_id' => (int) ($_POST['account_id'] ?? 0),
                'target_account_id' => !empty($_POST['target_account_id']) ? (int) $_POST['target_account_id'] : null,
                'category_id' => !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null,
                'status' => $_POST['status'] ?? 'posted',
                'reference_number' => $_POST['reference_number'] ?? null,
                'notes' => $_POST['notes'] ?? null,
            ];

            $financeService->createTransaction($data);
            set_flash('success', 'Transaksi berhasil disimpan.');
            redirect('finance/transactions.php');
        } elseif ($action === 'update') {
            $id = (int) ($_POST['id'] ?? 0);
            $data = [
                'type' => $_POST['type'] ?? 'expense',
                'description' => $_POST['description'] ?? '',
                'amount' => (float) ($_POST['amount'] ?? 0),
                'transaction_date' => $_POST['transaction_date'] ?? date('Y-m-d'),
                'account_id' => (int) ($_POST['account_id'] ?? 0),
                'target_account_id' => !empty($_POST['target_account_id']) ? (int) $_POST['target_account_id'] : null,
                'category_id' => !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null,
                'status' => $_POST['status'] ?? 'posted',
                'reference_number' => $_POST['reference_number'] ?? null,
                'notes' => $_POST['notes'] ?? null,
            ];

            $financeService->updateTransaction($id, $data);
            set_flash('success', 'Transaksi berhasil diperbarui.');
            redirect('finance/transactions.php');
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $financeService->softDeleteTransaction($id);
            set_flash('success', 'Transaksi berhasil dihapus.');
            redirect('finance/transactions.php');
        }
    } catch (InvalidArgumentException $e) {
        set_flash('error', $e->getMessage());
        redirect('finance/transactions.php');
    } catch (Throwable $e) {
        set_flash('error', 'Gagal memproses transaksi: ' . $e->getMessage());
        redirect('finance/transactions.php');
    }
}

// Data fetching
$accounts = [];
$incomeCategories = [];
$expenseCategories = [];
$allCategories = [];
$transactions = [];
$totalTransactions = 0;
$editingTx = null;

$activeAction = $_GET['action'] ?? '';
$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$filterType = $_GET['type'] ?? '';
$filterAccount = !empty($_GET['account_id']) ? (int) $_GET['account_id'] : null;
$filterCategory = !empty($_GET['category_id']) ? (int) $_GET['category_id'] : null;
$filterStartDate = $_GET['start_date'] ?? '';
$filterEndDate = $_GET['end_date'] ?? '';
$filterSearch = trim((string) ($_GET['search'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

if ($financeService !== null && $dbError === null) {
    try {
        $accounts = $financeService->getAccounts(true);
        $incomeCategories = $financeService->getCategories('income');
        $expenseCategories = $financeService->getCategories('expense');
        $allCategories = $financeService->getCategories();

        if ($activeAction === 'edit' && $editId > 0) {
            $editingTx = $financeService->getTransactionById($editId);
        }

        $filters = array_filter([
            'type' => $filterType,
            'account_id' => $filterAccount,
            'category_id' => $filterCategory,
            'start_date' => $filterStartDate,
            'end_date' => $filterEndDate,
            'search' => $filterSearch,
        ]);

        $transactions = $financeService->getTransactions($filters, $limit, $offset);
        $totalTransactions = $financeService->countTransactions($filters);
    } catch (Throwable $e) {
        $dbError = 'Gagal memuat data transaksi: ' . $e->getMessage();
    }
}

$totalPages = $limit > 0 ? (int) ceil($totalTransactions / $limit) : 1;

require LIFEOS_ROOT . '/includes/header.php';
?>

<div class="finance-page">

    <!-- Page Heading -->
    <section class="finance-heading">
        <div>
            <p class="eyebrow">MONEY MANAGEMENT</p>
            <h1>Transaksi</h1>
            <p class="finance-subtitle">
                Catat seluruh arus kas keluar, pemasukan, serta transfer antar-akun dengan pencatatan ledger ganda.
            </p>
        </div>

        <div class="finance-header-actions">
            <button
                type="button"
                class="finance-btn finance-btn-primary"
                data-open-modal="modalTransaction"
            >
                <span>＋</span> Catat transaksi
            </button>
        </div>
    </section>

    <!-- Navigation -->
    <?php render_finance_nav('transactions'); ?>

    <!-- Alerts -->
    <?php render_finance_alerts($dbError); ?>

    <!-- Filter Bar -->
    <form method="GET" action="<?= e(url('finance/transactions.php')) ?>" class="finance-filter-bar">
        <input
            type="text"
            name="search"
            class="filter-input filter-search"
            placeholder="Cari deskripsi, catatan, atau referensi..."
            value="<?= e($filterSearch) ?>"
        >

        <select name="type" class="filter-select">
            <option value="">Semua Jenis</option>
            <option value="income" <?= $filterType === 'income' ? 'selected' : '' ?>>Pemasukan</option>
            <option value="expense" <?= $filterType === 'expense' ? 'selected' : '' ?>>Pengeluaran</option>
            <option value="transfer" <?= $filterType === 'transfer' ? 'selected' : '' ?>>Transfer</option>
        </select>

        <select name="account_id" class="filter-select">
            <option value="">Semua Akun</option>
            <?php foreach ($accounts as $acc): ?>
                <option value="<?= $acc['id'] ?>" <?= $filterAccount === (int) $acc['id'] ? 'selected' : '' ?>>
                    <?= e($acc['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="category_id" class="filter-select">
            <option value="">Semua Kategori</option>
            <?php foreach ($allCategories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $filterCategory === (int) $cat['id'] ? 'selected' : '' ?>>
                    <?= e($cat['name']) ?> (<?= $cat['type'] === 'income' ? 'Masuk' : 'Keluar' ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <input
            type="date"
            name="start_date"
            class="filter-input"
            value="<?= e($filterStartDate) ?>"
            title="Dari tanggal"
        >

        <input
            type="date"
            name="end_date"
            class="filter-input"
            value="<?= e($filterEndDate) ?>"
            title="Sampai tanggal"
        >

        <button type="submit" class="finance-btn finance-btn-secondary finance-btn-sm">
            Terapkan
        </button>

        <?php if (!empty($filterType) || !empty($filterAccount) || !empty($filterCategory) || !empty($filterStartDate) || !empty($filterEndDate) || !empty($filterSearch)): ?>
            <a href="<?= e(url('finance/transactions.php')) ?>" class="finance-btn finance-btn-secondary finance-btn-sm filter-reset-link">
                Reset
            </a>
        <?php endif; ?>
    </form>

    <!-- Transactions List Panel -->
    <article class="finance-panel">
        <div class="finance-panel-heading">
            <div>
                <h2>Riwayat Transaksi</h2>
                <p>Menampilkan <?= count($transactions) ?> dari <?= $totalTransactions ?> transaksi.</p>
            </div>
        </div>

        <?php if (empty($transactions)): ?>
            <div class="finance-empty-state">
                <div class="empty-state-icon">↔</div>
                <h3>Belum ada transaksi ditemukan</h3>
                <p>Tidak ada transaksi yang sesuai dengan kriteria filter saat ini, atau Anda belum pernah mencatat transaksi.</p>
                <button
                    type="button"
                    class="finance-btn finance-btn-primary finance-btn-sm"
                    data-open-modal="modalTransaction"
                >
                    Catat transaksi baru
                </button>
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
                            <th>Akun</th>
                            <th class="text-right">Nominal</th>
                            <th>Status</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $tx): ?>
                            <tr>
                                <td class="td-date">
                                    <?= date('d M Y', strtotime($tx['transaction_date'])) ?>
                                </td>
                                <td>
                                    <span class="finance-badge badge-<?= e($tx['type']) ?>">
                                        <?= $tx['type'] === 'income' ? '↗ Masuk' : ($tx['type'] === 'expense' ? '↙ Keluar' : '⇄ Transfer') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="td-desc-title"><?= e($tx['description']) ?></div>
                                    <?php if (!empty($tx['notes'])): ?>
                                        <div class="td-desc-notes"><?= e($tx['notes']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($tx['category_name'])): ?>
                                        <span class="finance-badge badge-category">
                                            <?= e($tx['category_name']) ?>
                                        </span>
                                    <?php elseif ((int) $tx['is_loan_principal'] === 1): ?>
                                        <span class="finance-badge badge-principal">
                                            Pokok Pinjaman
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted-dash">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="td-desc-title">
                                        <?= e($tx['account_name']) ?>
                                        <?php if ($tx['type'] === 'transfer' && !empty($tx['target_account_name'])): ?>
                                            <span class="stat-card-sub">→ <?= e($tx['target_account_name']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-right">
                                    <span class="tx-amount amount-<?= e($tx['type']) ?>">
                                        <?= $tx['type'] === 'income' ? '+' : ($tx['type'] === 'expense' ? '-' : '') ?>
                                        <?= format_rupiah($tx['amount']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="finance-badge badge-status-<?= e($tx['status']) ?>">
                                        <?= ucfirst($tx['status']) ?>
                                    </span>
                                </td>
                                <td class="td-actions">
                                    <a
                                        href="<?= e(url('finance/transactions.php?action=edit&id=' . $tx['id'])) ?>"
                                        class="finance-btn finance-btn-secondary finance-btn-sm"
                                        title="Edit Transaksi"
                                    >
                                        Edit
                                    </a>
                                    <form
                                        method="POST"
                                        action="<?= e(url('finance/transactions.php')) ?>"
                                        class="inline-form"
                                        data-confirm="Apakah Anda yakin ingin menghapus transaksi ini?"
                                    >
                                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= e((string) $tx['id']) ?>">
                                        <button type="submit" class="finance-btn finance-btn-danger finance-btn-sm" title="Hapus Transaksi">
                                            Hapus
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Controls -->
            <?php if ($totalPages > 1): ?>
                <div class="finance-pagination">
                    <span>Halaman <?= $page ?> dari <?= $totalPages ?></span>
                    <div class="pagination-controls">
                        <?php if ($page > 1): ?>
                            <a
                                href="<?= e(url('finance/transactions.php?' . http_build_query(array_merge($_GET, ['page' => $page - 1])))) ?>"
                                class="finance-btn finance-btn-secondary finance-btn-sm"
                            >
                                ← Sebelumnya
                            </a>
                        <?php endif; ?>

                        <?php if ($page < $totalPages): ?>
                            <a
                                href="<?= e(url('finance/transactions.php?' . http_build_query(array_merge($_GET, ['page' => $page + 1])))) ?>"
                                class="finance-btn finance-btn-secondary finance-btn-sm"
                            >
                                Selanjutnya →
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </article>

</div>

<!-- Modal / Form Tambah / Edit Transaksi -->
<div
    class="finance-modal-backdrop <?= ($activeAction === 'create' || $editingTx !== null) ? 'active' : '' ?>"
    id="modalTransaction"
>
    <div class="finance-modal">
        <div class="finance-modal-header">
            <h3><?= $editingTx !== null ? 'Edit Transaksi' : 'Catat Transaksi Baru' ?></h3>
            <button
                type="button"
                class="modal-close-btn"
                data-redirect="<?= e(url('finance/transactions.php')) ?>"
                aria-label="Tutup"
            >
                ✕
            </button>
        </div>

        <form method="POST" action="<?= e(url('finance/transactions.php')) ?>">
            <div class="finance-modal-body">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="<?= $editingTx !== null ? 'update' : 'create' ?>">
                <?php if ($editingTx !== null): ?>
                    <input type="hidden" name="id" value="<?= e((string) $editingTx['id']) ?>">
                <?php endif; ?>

                <div class="finance-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="tx_type">Jenis Transaksi *</label>
                            <select
                                name="type"
                                id="tx_type"
                                class="form-select"
                                required
                                data-handle-tx-type="1"
                            >
                                <?php $currentType = $editingTx['type'] ?? 'expense'; ?>
                                <option value="expense" <?= $currentType === 'expense' ? 'selected' : '' ?>>Pengeluaran</option>
                                <option value="income" <?= $currentType === 'income' ? 'selected' : '' ?>>Pemasukan</option>
                                <option value="transfer" <?= $currentType === 'transfer' ? 'selected' : '' ?>>Transfer Antar Akun</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="tx_date">Tanggal Transaksi *</label>
                            <input
                                type="date"
                                name="transaction_date"
                                id="tx_date"
                                class="form-control"
                                value="<?= e($editingTx['transaction_date'] ?? date('Y-m-d')) ?>"
                                required
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="tx_desc">Deskripsi *</label>
                        <input
                            type="text"
                            name="description"
                            id="tx_desc"
                            class="form-control"
                            placeholder="Contoh: Belanja mingguan, Gaji bulanan"
                            value="<?= e($editingTx['description'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="tx_amount">Nominal (Rp) *</label>
                            <input
                                type="number"
                                step="any"
                                min="0.01"
                                name="amount"
                                id="tx_amount"
                                class="form-control"
                                placeholder="0"
                                value="<?= e((string) ($editingTx['amount'] ?? '')) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="tx_status">Status *</label>
                            <select name="status" id="tx_status" class="form-select" required>
                                <?php $currentStatus = $editingTx['status'] ?? 'posted'; ?>
                                <option value="posted" <?= $currentStatus === 'posted' ? 'selected' : '' ?>>Posted (Aktif)</option>
                                <option value="draft" <?= $currentStatus === 'draft' ? 'selected' : '' ?>>Draft</option>
                                <option value="void" <?= $currentStatus === 'void' ? 'selected' : '' ?>>Void (Batal)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group" id="group_source_account">
                            <label for="tx_account" id="label_source_account">Akun Sumber *</label>
                            <select name="account_id" id="tx_account" class="form-select" required>
                                <option value="">-- Pilih Akun --</option>
                                <?php foreach ($accounts as $acc): ?>
                                    <option
                                        value="<?= $acc['id'] ?>"
                                        <?= (isset($editingTx['account_id']) && (int) $editingTx['account_id'] === (int) $acc['id']) ? 'selected' : '' ?>
                                    >
                                        <?= e($acc['name']) ?> (Saldo: <?= format_rupiah($acc['current_balance']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group" id="group_target_account" class="extracted-style-20">
                            <label for="tx_target_account">Akun Tujuan (Transfer) *</label>
                            <select name="target_account_id" id="tx_target_account" class="form-select">
                                <option value="">-- Pilih Akun Tujuan --</option>
                                <?php foreach ($accounts as $acc): ?>
                                    <option
                                        value="<?= $acc['id'] ?>"
                                        <?= (isset($editingTx['target_account_id']) && (int) $editingTx['target_account_id'] === (int) $acc['id']) ? 'selected' : '' ?>
                                    >
                                        <?= e($acc['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group" id="group_category">
                            <label for="tx_category">Kategori</label>
                            <select name="category_id" id="tx_category" class="form-select">
                                <option value="">-- Tanpa Kategori --</option>
                                <optgroup label="Pengeluaran" id="optgroup_expense">
                                    <?php foreach ($expenseCategories as $cat): ?>
                                        <option
                                            value="<?= $cat['id'] ?>"
                                            <?= (isset($editingTx['category_id']) && (int) $editingTx['category_id'] === (int) $cat['id']) ? 'selected' : '' ?>
                                        >
                                            <?= e($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="Pemasukan" id="optgroup_income">
                                    <?php foreach ($incomeCategories as $cat): ?>
                                        <option
                                            value="<?= $cat['id'] ?>"
                                            <?= (isset($editingTx['category_id']) && (int) $editingTx['category_id'] === (int) $cat['id']) ? 'selected' : '' ?>
                                        >
                                            <?= e($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="tx_notes">Catatan Tambahan (Opsional)</label>
                        <textarea
                            name="notes"
                            id="tx_notes"
                            class="form-textarea"
                            placeholder="Catatan kecil mengenai transaksi ini..."
                        ><?= e($editingTx['notes'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="finance-modal-footer">
                <button
                    type="button"
                    class="finance-btn finance-btn-secondary"
                    data-redirect="<?= e(url('finance/transactions.php')) ?>"
                >
                    Batal
                </button>
                <button type="submit" class="finance-btn finance-btn-primary">
                    <?= $editingTx !== null ? 'Simpan Perubahan' : 'Catat Transaksi' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require LIFEOS_ROOT . '/includes/footer.php'; ?>
