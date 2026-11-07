<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';

$pageTitle = 'Finance · Tagihan & Pengingat';
$currentPage = 'finance';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    try {
        if ($financeService === null) {
            throw new RuntimeException('Layanan database belum siap.');
        }

        if ($action === 'create' || $action === 'update') {
            $id = $action === 'update' ? (int) ($_POST['id'] ?? 0) : null;
            $data = [
                'name' => $_POST['name'] ?? '',
                'category_id' => !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null,
                'account_id' => !empty($_POST['account_id']) ? (int) $_POST['account_id'] : null,
                'expected_amount' => (float) ($_POST['expected_amount'] ?? 0),
                'frequency' => $_POST['frequency'] ?? 'monthly',
                'due_day' => !empty($_POST['due_day']) ? (int) $_POST['due_day'] : null,
                'next_due_date' => $_POST['next_due_date'] ?? date('Y-m-d'),
                'notes' => $_POST['notes'] ?? null,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ];

            $financeService->saveBill($data, $id);
            set_flash('success', $action === 'update' ? 'Pengingat tagihan berhasil diperbarui.' : 'Pengingat tagihan baru berhasil disimpan.');
            redirect('finance/bills.php');
        } elseif ($action === 'toggle_active') {
            $id = (int) ($_POST['id'] ?? 0);
            $active = (bool) (int) ($_POST['active'] ?? 0);
            $financeService->toggleBillActive($id, $active);
            set_flash('success', $active ? 'Tagihan berhasil diaktifkan kembali.' : 'Tagihan berhasil diarsipkan.');
            redirect('finance/bills.php');
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $financeService->softDeleteBill($id);
            set_flash('success', 'Pengingat tagihan berhasil dihapus.');
            redirect('finance/bills.php');
        }
    } catch (InvalidArgumentException $e) {
        set_flash('error', $e->getMessage());
        redirect('finance/bills.php');
    } catch (Throwable $e) {
        set_flash('error', 'Gagal memproses tagihan: ' . $e->getMessage());
        redirect('finance/bills.php');
    }
}

$bills = [];
$expenseCategories = [];
$accounts = [];
$activeAction = request_string($_GET['action'] ?? '');
$billId = max(0, request_integer($_GET['id'] ?? 0));
$editingBill = null;

if ($financeService !== null && $dbError === null) {
    try {
        $bills = $financeService->getBills(false);
        $expenseCategories = $financeService->getCategories('expense');
        $accounts = $financeService->getAccounts(true);

        if ($activeAction === 'edit' && $billId > 0) {
            $editingBill = $financeService->getBillById($billId);
        }
    } catch (Throwable $e) {
        $dbError = 'Gagal memuat daftar tagihan: ' . $e->getMessage();
    }
}

require LIFEOS_ROOT . '/includes/header.php';
?>

<div class="finance-page">


    <section class="finance-heading">
        <div>
            <p class="eyebrow">MONEY MANAGEMENT</p>
            <h1>Tagihan & Pengingat</h1>
            <p class="finance-subtitle">
                Atur jadwal tagihan rutin (listrik, internet, sewa, langganan). Pengingat ini tidak otomatis mengurangi saldo Anda.
            </p>
        </div>

        <div class="finance-header-actions">
            <button
                type="button"
                class="finance-btn finance-btn-primary"
                data-open-modal="modalBill"
            >
                <span>＋</span> Tambah tagihan
            </button>
        </div>
    </section>


    <?php render_finance_nav('bills'); ?>


    <?php render_finance_alerts($dbError); ?>


    <div class="extracted-style-2">
        <span class="extracted-style-3">ℹ</span>
        <span>
            <strong>Informasi Kebijakan Finansial:</strong> Tagihan berulang di LIFEOS berfungsi sebagai pengingat kalender. Saldo kas/bank Anda <strong>tidak pernah dipotong otomatis</strong>. Silakan catat pembayaran sebenarnya melalui menu Transaksi saat Anda telah membayarnya.
        </span>
    </div>


    <article class="finance-panel">
        <div class="finance-panel-heading">
            <div>
                <h2>Daftar Tagihan Rutin</h2>
                <p>Total <?= count($bills) ?> tagihan terdaftar.</p>
            </div>
        </div>

        <?php if (empty($bills)): ?>
            <div class="finance-empty-state">
                <div class="empty-state-icon">▤</div>
                <h3>Belum ada tagihan dicatat</h3>
                <p>Tambahkan pengingat tagihan rutin bulanan agar pembayaran Anda tidak pernah terlewat.</p>
                <button
                    type="button"
                    class="finance-btn finance-btn-primary finance-btn-sm"
                    data-open-modal="modalBill"
                >
                    Tambah pengingat tagihan
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="finance-table">
                    <thead>
                        <tr>
                            <th>Nama Tagihan</th>
                            <th>Kategori</th>
                            <th>Frekuensi</th>
                            <th>Jatuh Tempo</th>
                            <th class="text-right">Estimasi Nominal</th>
                            <th>Status Pengingat</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bills as $b): ?>
                            <tr class="<?= (int) $b['is_active'] === 0 ? 'bill-row-inactive' : '' ?>">
                                <td>
                                    <div class="extracted-style-4"><?= e($b['name']) ?></div>
                                    <?php if (!empty($b['notes'])): ?>
                                        <div class="extracted-style-5"><?= e($b['notes']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($b['category_name'])): ?>
                                        <span class="finance-badge extracted-style-6">
                                            <?= e($b['category_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="extracted-style-7">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="extracted-style-8">
                                        <?= e(ucfirst($b['frequency'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div><?= date('d M Y', strtotime($b['next_due_date'])) ?></div>
                                </td>
                                <td class="text-right">
                                    <span class="tx-amount extracted-style-9">
                                        <?= format_rupiah($b['expected_amount']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ((int) $b['is_active'] === 0): ?>
                                        <span class="finance-badge badge-inactive">Arsip</span>
                                    <?php else: ?>
                                        <span class="finance-badge <?= $b['urgency'] === 'overdue' ? 'badge-danger' : ($b['urgency'] === 'due_soon' || $b['urgency'] === 'due_today' ? 'badge-warning' : 'badge-active') ?>">
                                            <?= e($b['urgency_label']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right extracted-style-10">
                                    <?php if ((int) $b['is_active'] === 1): ?>
                                        <a
                                            href="<?= e(url('finance/transactions.php?action=create&description=' . urlencode('Bayar ' . $b['name']) . '&amount=' . urlencode((string) $b['expected_amount']) . '&category_id=' . urlencode((string) ($b['category_id'] ?? '')))) ?>"
                                            class="finance-btn finance-btn-primary finance-btn-sm"
                                            title="Buka form pencatatan pengeluaran dengan data tagihan ini"
                                        >
                                            Catat Pembayaran
                                        </a>
                                    <?php endif; ?>

                                    <a
                                        href="<?= e(url('finance/bills.php?action=edit&id=' . $b['id'])) ?>"
                                        class="finance-btn finance-btn-secondary finance-btn-sm"
                                    >
                                        Edit
                                    </a>

                                    <form method="POST" action="<?= e(url('finance/bills.php')) ?>" class="extracted-style-11">
                                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="id" value="<?= e((string) $b['id']) ?>">
                                        <input type="hidden" name="active" value="<?= (int) $b['is_active'] === 1 ? '0' : '1' ?>">
                                        <button
                                            type="submit"
                                            class="finance-btn finance-btn-secondary finance-btn-sm"
                                            title="<?= (int) $b['is_active'] === 1 ? 'Arsipkan Tagihan' : 'Aktifkan Kembali' ?>"
                                        >
                                            <?= (int) $b['is_active'] === 1 ? 'Arsip' : 'Aktifkan' ?>
                                        </button>
                                    </form>

                                    <form
                                        method="POST"
                                        action="<?= e(url('finance/bills.php')) ?>"
                                        class="extracted-style-12"
                                        data-confirm="Hapus pengingat tagihan ini?"
                                    >
                                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= e((string) $b['id']) ?>">
                                        <button type="submit" class="finance-btn finance-btn-danger finance-btn-sm">
                                            Hapus
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>

</div>


<div
    class="finance-modal-backdrop <?= ($activeAction === 'create' || $editingBill !== null) ? 'active' : '' ?>"
    id="modalBill"
>
    <div class="finance-modal">
        <div class="finance-modal-header">
            <h3><?= $editingBill !== null ? 'Edit Pengingat Tagihan' : 'Tambah Pengingat Tagihan' ?></h3>
            <button
                type="button"
                class="modal-close-btn"
                data-redirect="<?= e(url('finance/bills.php')) ?>"
                aria-label="Tutup"
            >
                ✕
            </button>
        </div>

        <form method="POST" action="<?= e(url('finance/bills.php')) ?>">
            <div class="finance-modal-body">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="<?= $editingBill !== null ? 'update' : 'create' ?>">
                <?php if ($editingBill !== null): ?>
                    <input type="hidden" name="id" value="<?= e((string) $editingBill['id']) ?>">
                <?php endif; ?>

                <div class="finance-form">
                    <div class="form-group">
                        <label for="bill_name">Nama Tagihan *</label>
                        <input
                            type="text"
                            name="name"
                            id="bill_name"
                            class="form-control"
                            placeholder="Contoh: Listrik PLN, Tagihan Internet IndiHome, Netflix"
                            value="<?= e($editingBill['name'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="bill_amount">Estimasi Nominal (Rp) *</label>
                            <input
                                type="number"
                                step="any"
                                min="1"
                                name="expected_amount"
                                id="bill_amount"
                                class="form-control"
                                placeholder="0"
                                value="<?= e((string) ($editingBill['expected_amount'] ?? '')) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="bill_frequency">Frekuensi *</label>
                            <select name="frequency" id="bill_frequency" class="form-select" required>
                                <?php $curFreq = $editingBill['frequency'] ?? 'monthly'; ?>
                                <option value="monthly" <?= $curFreq === 'monthly' ? 'selected' : '' ?>>Bulanan (Monthly)</option>
                                <option value="weekly" <?= $curFreq === 'weekly' ? 'selected' : '' ?>>Mingguan (Weekly)</option>
                                <option value="quarterly" <?= $curFreq === 'quarterly' ? 'selected' : '' ?>>Kuartalan (Quarterly)</option>
                                <option value="yearly" <?= $curFreq === 'yearly' ? 'selected' : '' ?>>Tahunan (Yearly)</option>
                                <option value="once" <?= $curFreq === 'once' ? 'selected' : '' ?>>Satu Kali (Once)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="bill_due_date">Tanggal Jatuh Tempo Berikutnya *</label>
                            <input
                                type="date"
                                name="next_due_date"
                                id="bill_due_date"
                                class="form-control"
                                value="<?= e($editingBill['next_due_date'] ?? date('Y-m-d', strtotime('+7 days'))) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="bill_category">Kategori Pengeluaran</label>
                            <select name="category_id" id="bill_category" class="form-select">
                                <option value="">-- Pilih Kategori --</option>
                                <?php foreach ($expenseCategories as $cat): ?>
                                    <option
                                        value="<?= $cat['id'] ?>"
                                        <?= (isset($editingBill['category_id']) && (int) $editingBill['category_id'] === (int) $cat['id']) ? 'selected' : '' ?>
                                    >
                                        <?= e($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="bill_account">Akun Pembayaran yang Direkomendasikan (Opsional)</label>
                        <select name="account_id" id="bill_account" class="form-select">
                            <option value="">-- Rekomendasi Akun Bebas --</option>
                            <?php foreach ($accounts as $acc): ?>
                                <option
                                    value="<?= $acc['id'] ?>"
                                    <?= (isset($editingBill['account_id']) && (int) $editingBill['account_id'] === (int) $acc['id']) ? 'selected' : '' ?>
                                >
                                    <?= e($acc['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="bill_notes">Catatan (Nomor Pelanggan / ID Tagihan)</label>
                        <textarea
                            name="notes"
                            id="bill_notes"
                            class="form-textarea"
                            placeholder="Contoh: No. Pelanggan PLN 531234567890"
                        ><?= e($editingBill['notes'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group extracted-style-13">
                        <input
                            type="checkbox"
                            name="is_active"
                            id="bill_active"
                            value="1"
                            <?= (!isset($editingBill) || (int) $editingBill['is_active'] === 1) ? 'checked' : '' ?>
                        >
                        <label for="bill_active" class="extracted-style-14">
                            Pengingat Aktif (tampilkan di dashboard dan peringatan mendatang)
                        </label>
                    </div>
                </div>
            </div>

            <div class="finance-modal-footer">
                <button
                    type="button"
                    class="finance-btn finance-btn-secondary"
                    data-redirect="<?= e(url('finance/bills.php')) ?>"
                >
                    Batal
                </button>
                <button type="submit" class="finance-btn finance-btn-primary">
                    <?= $editingBill !== null ? 'Simpan Perubahan' : 'Simpan Tagihan' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require LIFEOS_ROOT . '/includes/footer.php'; ?>
