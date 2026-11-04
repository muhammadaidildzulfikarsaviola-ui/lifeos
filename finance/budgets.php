<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';

$pageTitle = 'Finance · Anggaran';
$currentPage = 'finance';

// Handle POST actions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    try {
        if ($financeService === null) {
            throw new RuntimeException('Layanan database belum siap.');
        }

        if ($action === 'create' || $action === 'update') {
            $id = $action === 'update' ? (int) ($_POST['id'] ?? 0) : null;
            $period = $_POST['period'] ?? 'monthly';
            $startDate = $_POST['start_date'] ?? '';
            $endDate = $_POST['end_date'] ?? '';

            // Otomatisasi rentang tanggal jika period dipilih namun tanggal kosong
            if ($startDate === '' || $endDate === '') {
                if ($period === 'monthly') {
                    $startDate = date('Y-m-01');
                    $endDate = date('Y-m-t');
                } elseif ($period === 'weekly') {
                    $startDate = date('Y-m-d', strtotime('monday this week'));
                    $endDate = date('Y-m-d', strtotime('sunday this week'));
                } elseif ($period === 'quarterly') {
                    $curMonth = (int) date('n');
                    $qStartMonth = (int) (floor(($curMonth - 1) / 3) * 3 + 1);
                    $startDate = date('Y-') . str_pad((string) $qStartMonth, 2, '0', STR_PAD_LEFT) . '-01';
                    $endDate = date('Y-m-t', strtotime($startDate . ' +2 months'));
                } elseif ($period === 'yearly') {
                    $startDate = date('Y-01-01');
                    $endDate = date('Y-12-31');
                }
            }

            $categoriesAllocated = [];
            if (!empty($_POST['categories']) && is_array($_POST['categories'])) {
                foreach ($_POST['categories'] as $catId => $amt) {
                    $num = (float) $amt;
                    if ($num > 0) {
                        $categoriesAllocated[(int) $catId] = $num;
                    }
                }
            }

            $data = [
                'name' => $_POST['name'] ?? '',
                'period' => $period,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_limit' => (float) ($_POST['total_limit'] ?? 0),
                'notes' => $_POST['notes'] ?? null,
                'categories' => $categoriesAllocated,
            ];

            $financeService->saveBudget($data, $id);
            set_flash('success', $action === 'update' ? 'Anggaran berhasil diperbarui.' : 'Anggaran baru berhasil dibuat.');
            redirect('finance/budgets.php');
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $financeService->softDeleteBudget($id);
            set_flash('success', 'Anggaran berhasil dihapus.');
            redirect('finance/budgets.php');
        }
    } catch (InvalidArgumentException $e) {
        set_flash('error', $e->getMessage());
        redirect('finance/budgets.php');
    } catch (Throwable $e) {
        set_flash('error', 'Gagal memproses anggaran: ' . $e->getMessage());
        redirect('finance/budgets.php');
    }
}

// Data fetching
$budgets = [];
$expenseCategories = [];
$activeAction = $_GET['action'] ?? '';
$budgetId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$editingBudget = null;
$viewBudget = null;

if ($financeService !== null && $dbError === null) {
    try {
        $budgets = $financeService->getBudgets();
        $expenseCategories = $financeService->getCategories('expense');

        if ($activeAction === 'edit' && $budgetId > 0) {
            $editingBudget = $financeService->getBudgetById($budgetId);
        } elseif ($activeAction === 'view' && $budgetId > 0) {
            $viewBudget = $financeService->getBudgetById($budgetId);
        }
    } catch (Throwable $e) {
        $dbError = 'Gagal memuat anggaran: ' . $e->getMessage();
    }
}

require LIFEOS_ROOT . '/includes/header.php';
?>

<div class="finance-page">

    <!-- Page Heading -->
    <section class="finance-heading">
        <div>
            <p class="eyebrow">MONEY MANAGEMENT</p>
            <h1>Batas Anggaran</h1>
            <p class="finance-subtitle">
                Atur alokasi pengeluaran per kategori. Pengeluaran aktual dihitung murni dari transaksi pengeluaran operasional yang valid.
            </p>
        </div>

        <div class="finance-header-actions">
            <button
                type="button"
                class="finance-btn finance-btn-primary"
                onclick="document.getElementById('modalBudget').classList.add('active');"
            >
                <span>＋</span> Buat anggaran
            </button>
        </div>
    </section>

    <!-- Navigation -->
    <?php render_finance_nav('budgets'); ?>

    <!-- Alerts -->
    <?php render_finance_alerts($dbError); ?>

    <!-- View Budget Details -->
    <?php if ($viewBudget !== null): ?>
        <article class="finance-panel finance-detail-panel">
            <div class="finance-panel-heading">
                <div>
                    <h2>Detail Anggaran: <?= e($viewBudget['name']) ?></h2>
                    <p>
                        Periode: <?= e(ucfirst($viewBudget['period'])) ?>
                        (<?= date('d M Y', strtotime($viewBudget['start_date'])) ?> s/d <?= date('d M Y', strtotime($viewBudget['end_date'])) ?>)
                    </p>
                </div>
                <div class="btn-group">
                    <a
                        href="<?= e(url('finance/budgets.php?action=edit&id=' . $viewBudget['id'])) ?>"
                        class="finance-btn finance-btn-secondary finance-btn-sm"
                    >
                        Edit Anggaran
                    </a>
                    <a href="<?= e(url('finance/budgets.php')) ?>" class="finance-btn finance-btn-secondary finance-btn-sm">
                        ← Kembali ke Daftar
                    </a>
                </div>
            </div>

            <div class="finance-section">
                <div class="budget-status-row">
                    <span class="budget-status-title">Status Pemakaian:</span>
                    <span class="finance-badge badge-<?= e($viewBudget['status_indicator'] === 'normal' ? 'active' : ($viewBudget['status_indicator'] === 'warning' ? 'warning' : 'danger')) ?>">
                        <?= e($viewBudget['status_label']) ?> (<?= e((string) $viewBudget['percentage']) ?>%)
                    </span>
                </div>
                <div class="budget-progress-bar-container h-10">
                    <div
                        class="budget-progress-bar <?= e($viewBudget['status_indicator']) ?>"
                        style="width: <?= min(100, max(2, (int) $viewBudget['percentage'])) ?>%;"
                    ></div>
                </div>
                <div class="budget-amounts-row text-sm">
                    <span>Realisasi Pengeluaran: <strong><?= format_rupiah($viewBudget['total_spent']) ?></strong></span>
                    <span>Sisa Anggaran: <strong class="<?= $viewBudget['remaining'] >= 0 ? 'text-income' : 'text-expense' ?>"><?= format_rupiah($viewBudget['remaining']) ?></strong></span>
                    <span>Total Batas: <strong><?= format_rupiah($viewBudget['total_limit']) ?></strong></span>
                </div>
            </div>

            <h3 class="section-subtitle">
                Rincian Alokasi per Kategori
            </h3>

            <?php if (empty($viewBudget['items'])): ?>
                <p class="budget-card-subtitle">Anggaran ini tidak membatasi per kategori spesifik (berlaku global).</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="finance-table">
                        <thead>
                            <tr>
                                <th>Kategori</th>
                                <th class="text-right">Alokasi Batas</th>
                                <th class="text-right">Realisasi Keluar</th>
                                <th class="text-right">Sisa Alokasi</th>
                                <th class="col-progress">Progres</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($viewBudget['items'] as $item): ?>
                                <tr>
                                    <td class="fw-600">
                                        <span class="mr-1"><?= e($item['category_icon'] ?? '◈') ?></span>
                                        <?= e($item['category_name']) ?>
                                    </td>
                                    <td class="text-right"><?= format_rupiah($item['allocated_amount']) ?></td>
                                    <td class="text-right text-expense"><?= format_rupiah($item['actual_spent']) ?></td>
                                    <td class="text-right fw-600 <?= $item['remaining'] >= 0 ? 'text-income' : 'text-expense' ?>">
                                        <?= format_rupiah($item['remaining']) ?>
                                    </td>
                                    <td>
                                        <div class="progress-wrapper">
                                            <div class="budget-progress-bar-container flex-1 h-6 m-0">
                                                <div
                                                    class="budget-progress-bar <?= $item['percentage'] >= 100 ? 'danger' : ($item['percentage'] >= 80 ? 'warning' : '') ?>"
                                                    style="width: <?= min(100, max(2, (int) $item['percentage'])) ?>%;"
                                                ></div>
                                            </div>
                                            <span class="progress-pct-label"><?= e((string) $item['percentage']) ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>
    <?php endif; ?>

    <!-- Budgets List Grid -->
    <section class="finance-section">
        <div class="finance-section-heading">
            <div>
                <h2>Daftar Anggaran</h2>
                <p>Total <?= count($budgets) ?> anggaran tercatat.</p>
            </div>
        </div>

        <?php if (empty($budgets)): ?>
            <div class="finance-panel">
                <div class="finance-empty-state">
                    <div class="empty-state-icon">◫</div>
                    <h3>Belum ada anggaran dibuat</h3>
                    <p>Buat rencana anggaran bulanan untuk mengendalikan belanja dan memantau realisasi pengeluaran.</p>
                    <button
                        type="button"
                        class="finance-btn finance-btn-primary finance-btn-sm"
                        onclick="document.getElementById('modalBudget').classList.add('active');"
                    >
                        Buat anggaran baru
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="budgets-grid">
                <?php foreach ($budgets as $b): ?>
                    <article class="budget-card">
                        <div class="budget-card-header">
                            <div>
                                <h3 class="budget-card-title">
                                    <?= e($b['name']) ?>
                                </h3>
                                <div class="budget-card-subtitle">
                                    <?= e(ucfirst($b['period'])) ?> ·
                                    <?= date('d M', strtotime($b['start_date'])) ?> - <?= date('d M Y', strtotime($b['end_date'])) ?>
                                </div>
                            </div>
                            <span class="finance-badge badge-<?= e($b['status_indicator'] === 'normal' ? 'active' : ($b['status_indicator'] === 'warning' ? 'warning' : 'danger')) ?>">
                                <?= e($b['status_label']) ?>
                            </span>
                        </div>

                        <div class="budget-progress-bar-container">
                            <div
                                class="budget-progress-bar <?= e($b['status_indicator']) ?>"
                                style="width: <?= min(100, max(2, (int) $b['percentage'])) ?>%;"
                            ></div>
                        </div>

                        <div class="budget-amounts-row">
                            <span>Terpakai: <strong><?= format_rupiah($b['total_spent']) ?></strong></span>
                            <span>Sisa: <strong><?= format_rupiah($b['remaining']) ?></strong></span>
                        </div>

                        <div class="budget-card-limit">
                            Batas Alokasi: <strong><?= format_rupiah($b['total_limit']) ?></strong> (<?= e((string) $b['percentage']) ?>%)
                        </div>

                        <div class="budget-card-actions">
                            <a
                                href="<?= e(url('finance/budgets.php?action=view&id=' . $b['id'])) ?>"
                                class="finance-btn finance-btn-secondary finance-btn-sm"
                            >
                                Rincian
                            </a>
                            <a
                                href="<?= e(url('finance/budgets.php?action=edit&id=' . $b['id'])) ?>"
                                class="finance-btn finance-btn-secondary finance-btn-sm"
                            >
                                Edit
                            </a>
                            <form
                                method="POST"
                                action="<?= e(url('finance/budgets.php')) ?>"
                                class="inline-form"
                                onsubmit="return confirm('Hapus anggaran ini?');"
                            >
                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= e((string) $b['id']) ?>">
                                <button type="submit" class="finance-btn finance-btn-danger finance-btn-sm">
                                    Hapus
                                </button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

</div>

<!-- Modal Tambah / Edit Anggaran -->
<div
    class="finance-modal-backdrop <?= ($activeAction === 'create' || $editingBudget !== null) ? 'active' : '' ?>"
    id="modalBudget"
>
    <div class="finance-modal modal-lg">
        <div class="finance-modal-header">
            <h3><?= $editingBudget !== null ? 'Edit Anggaran' : 'Buat Anggaran Baru' ?></h3>
            <button
                type="button"
                class="modal-close-btn"
                onclick="window.location.href = '<?= e(url('finance/budgets.php')) ?>';"
                aria-label="Tutup"
            >
                ✕
            </button>
        </div>

        <form method="POST" action="<?= e(url('finance/budgets.php')) ?>">
            <div class="finance-modal-body">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="<?= $editingBudget !== null ? 'update' : 'create' ?>">
                <?php if ($editingBudget !== null): ?>
                    <input type="hidden" name="id" value="<?= e((string) $editingBudget['id']) ?>">
                <?php endif; ?>

                <div class="finance-form">
                    <div class="form-group">
                        <label for="b_name">Nama Anggaran *</label>
                        <input
                            type="text"
                            name="name"
                            id="b_name"
                            class="form-control"
                            placeholder="Contoh: Anggaran Operasional November 2026"
                            value="<?= e($editingBudget['name'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="b_period">Periode *</label>
                            <select name="period" id="b_period" class="form-select" required onchange="adjustBudgetDates(this.value);">
                                <?php $curPeriod = $editingBudget['period'] ?? 'monthly'; ?>
                                <option value="monthly" <?= $curPeriod === 'monthly' ? 'selected' : '' ?>>Bulanan (Monthly)</option>
                                <option value="weekly" <?= $curPeriod === 'weekly' ? 'selected' : '' ?>>Mingguan (Weekly)</option>
                                <option value="quarterly" <?= $curPeriod === 'quarterly' ? 'selected' : '' ?>>Kuartalan (Quarterly)</option>
                                <option value="yearly" <?= $curPeriod === 'yearly' ? 'selected' : '' ?>>Tahunan (Yearly)</option>
                                <option value="custom" <?= $curPeriod === 'custom' ? 'selected' : '' ?>>Kustom (Custom)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="b_limit">Total Batas Anggaran (Rp) *</label>
                            <input
                                type="number"
                                step="any"
                                min="1"
                                name="total_limit"
                                id="b_limit"
                                class="form-control"
                                placeholder="0"
                                value="<?= e((string) ($editingBudget['total_limit'] ?? '')) ?>"
                                required
                            >
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="b_start">Tanggal Mulai *</label>
                            <input
                                type="date"
                                name="start_date"
                                id="b_start"
                                class="form-control"
                                value="<?= e($editingBudget['start_date'] ?? date('Y-m-01')) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="b_end">Tanggal Selesai *</label>
                            <input
                                type="date"
                                name="end_date"
                                id="b_end"
                                class="form-control"
                                value="<?= e($editingBudget['end_date'] ?? date('Y-m-t')) ?>"
                                required
                            >
                        </div>
                    </div>

                    <!-- Category Allocation Section -->
                    <div class="form-group">
                        <label>Alokasi per Kategori Pengeluaran (Opsional)</label>
                        <p class="form-hint mb-2">
                            Anda dapat membagi batas total ke kategori belanja tertentu.
                        </p>

                        <div class="category-allocations-box">
                            <?php
                            $allocatedMap = [];
                            if (!empty($editingBudget['items'])) {
                                foreach ($editingBudget['items'] as $item) {
                                    $allocatedMap[(int) $item['category_id']] = (float) $item['allocated_amount'];
                                }
                            }
                            ?>
                            <?php foreach ($expenseCategories as $cat): ?>
                                <div class="allocation-row">
                                    <span class="allocation-name">
                                        <?= e($cat['icon'] ?? '◈') ?> <?= e($cat['name']) ?>
                                    </span>
                                    <input
                                        type="number"
                                        step="any"
                                        min="0"
                                        name="categories[<?= $cat['id'] ?>]"
                                        class="form-control input-category-allocation"
                                        placeholder="0"
                                        value="<?= e((string) ($allocatedMap[(int) $cat['id']] ?? '')) ?>"
                                    >
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="b_notes">Catatan (Opsional)</label>
                        <textarea
                            name="notes"
                            id="b_notes"
                            class="form-textarea"
                            placeholder="Keterangan mengenai target anggaran..."
                        ><?= e($editingBudget['notes'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="finance-modal-footer">
                <button
                    type="button"
                    class="finance-btn finance-btn-secondary"
                    onclick="window.location.href = '<?= e(url('finance/budgets.php')) ?>';"
                >
                    Batal
                </button>
                <button type="submit" class="finance-btn finance-btn-primary">
                    <?= $editingBudget !== null ? 'Simpan Perubahan' : 'Simpan Anggaran' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function adjustBudgetDates(period) {
    const startInput = document.getElementById('b_start');
    const endInput = document.getElementById('b_end');
    const now = new Date();

    if (period === 'monthly') {
        const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
        const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
        startInput.value = firstDay.toISOString().split('T')[0];
        endInput.value = lastDay.toISOString().split('T')[0];
    } else if (period === 'yearly') {
        const firstDay = new Date(now.getFullYear(), 0, 1);
        const lastDay = new Date(now.getFullYear(), 11, 31);
        startInput.value = firstDay.toISOString().split('T')[0];
        endInput.value = lastDay.toISOString().split('T')[0];
    }
}
</script>

<?php require LIFEOS_ROOT . '/includes/footer.php'; ?>
