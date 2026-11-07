<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';

$pageTitle = 'Finance · Pinjaman Pribadi';
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
                'lender_name' => $_POST['lender_name'] ?? '',
                'loan_type' => $_POST['loan_type'] ?? 'borrowed',
                'principal_amount' => (float) ($_POST['principal_amount'] ?? 0),
                'interest_amount' => (float) ($_POST['interest_amount'] ?? 0),
                'borrowed_date' => $_POST['borrowed_date'] ?? date('Y-m-d'),
                'due_date' => !empty($_POST['due_date']) ? $_POST['due_date'] : null,
                'notes' => $_POST['notes'] ?? null,
            ];

            $financeService->saveLoan($data, $id);
            set_flash('success', $action === 'update' ? 'Data pinjaman berhasil diperbarui.' : 'Pinjaman baru berhasil dicatat.');
            redirect('finance/loans.php');
        } elseif ($action === 'record_payment') {
            $data = [
                'loan_id' => (int) ($_POST['loan_id'] ?? 0),
                'account_id' => (int) ($_POST['account_id'] ?? 0),
                'payment_date' => $_POST['payment_date'] ?? date('Y-m-d'),
                'principal_paid' => (float) ($_POST['principal_paid'] ?? 0),
                'interest_paid' => (float) ($_POST['interest_paid'] ?? 0),
                'notes' => $_POST['notes'] ?? null,
            ];

            $financeService->recordLoanPayment($data);
            set_flash('success', 'Pembayaran pinjaman berhasil dicatat dan transaksi kas keluar telah dibuat.');
            redirect('finance/loans.php?action=view&id=' . $data['loan_id']);
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $financeService->softDeleteLoan($id);
            set_flash('success', 'Data pinjaman berhasil dihapus.');
            redirect('finance/loans.php');
        }
    } catch (InvalidArgumentException $e) {
        set_flash('error', $e->getMessage());
        redirect('finance/loans.php');
    } catch (Throwable $e) {
        set_flash('error', 'Gagal memproses data pinjaman: ' . $e->getMessage());
        redirect('finance/loans.php');
    }
}

$loans = [];
$accounts = [];
$activeAction = request_string($_GET['action'] ?? '');
$loanId = max(0, request_integer($_GET['id'] ?? 0));
$editingLoan = null;
$viewLoan = null;

if ($financeService !== null && $dbError === null) {
    try {
        $loans = $financeService->getLoans();
        $accounts = $financeService->getAccounts(true);

        if ($activeAction === 'edit' && $loanId > 0) {
            $editingLoan = $financeService->getLoanById($loanId);
        } elseif ($activeAction === 'view' && $loanId > 0) {
            $viewLoan = $financeService->getLoanById($loanId);
        }
    } catch (Throwable $e) {
        $dbError = 'Gagal memuat pinjaman: ' . $e->getMessage();
    }
}

require LIFEOS_ROOT . '/includes/header.php';
?>

<div class="finance-page">


    <section class="finance-heading">
        <div>
            <p class="eyebrow">MONEY MANAGEMENT</p>
            <h1>Pinjaman Pribadi</h1>
            <p class="finance-subtitle">
                Pantau pinjaman uang yang Anda terima, kewajiban pelunasan pokok dan beban bunga, serta riwayat pembayaran bertahap.
            </p>
        </div>

        <div class="finance-header-actions">
            <button
                type="button"
                class="finance-btn finance-btn-primary"
                data-open-modal="modalLoan"
            >
                <span>＋</span> Catat pinjaman baru
            </button>
        </div>
    </section>


    <?php render_finance_nav('loans'); ?>


    <?php render_finance_alerts($dbError); ?>


    <?php if ($viewLoan !== null): ?>
        <article class="finance-panel finance-detail-panel">
            <div class="finance-panel-heading">
                <div>
                    <h2>Detail Pinjaman: <?= e($viewLoan['lender_name']) ?></h2>
                    <p>
                        Tanggal Pinjam: <?= date('d M Y', strtotime($viewLoan['borrowed_date'])) ?>
                        <?php if (!empty($viewLoan['due_date'])): ?>
                            · Jatuh Tempo: <?= date('d M Y', strtotime($viewLoan['due_date'])) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="btn-group">
                    <?php if ($viewLoan['status'] !== 'paid'): ?>
                        <button
                            type="button"
                            class="finance-btn finance-btn-primary finance-btn-sm"
                            data-open-modal="modalPayment"
                        >
                            ＋ Catat Pembayaran
                        </button>
                    <?php endif; ?>
                    <a
                        href="<?= e(url('finance/loans.php?action=edit&id=' . $viewLoan['id'])) ?>"
                        class="finance-btn finance-btn-secondary finance-btn-sm"
                    >
                        Edit Pinjaman
                    </a>
                    <a href="<?= e(url('finance/loans.php')) ?>" class="finance-btn finance-btn-secondary finance-btn-sm">
                        ← Kembali ke Daftar
                    </a>
                </div>
            </div>


            <div class="finance-stats-grid">
                <div class="loan-stat-item">
                    <span class="loan-stat-label">Pokok Awal Pinjaman</span>
                    <div class="loan-stat-value">
                        <?= format_rupiah($viewLoan['principal_amount']) ?>
                    </div>
                </div>

                <div class="loan-stat-item">
                    <span class="loan-stat-label">Bunga Disepakati</span>
                    <div class="loan-stat-value">
                        <?= format_rupiah($viewLoan['interest_amount']) ?>
                    </div>
                </div>

                <div class="loan-stat-item">
                    <span class="loan-stat-label income">Total Terbayar</span>
                    <div class="loan-stat-value income">
                        <?= format_rupiah($viewLoan['total_paid']) ?>
                    </div>
                    <div class="loan-stat-breakdown">
                        Pokok: <?= format_rupiah($viewLoan['total_principal_paid']) ?> · Bunga: <?= format_rupiah($viewLoan['total_interest_paid']) ?>
                    </div>
                </div>

                <div class="loan-stat-item danger">
                    <span class="loan-stat-label danger">Sisa Kewajiban</span>
                    <div class="loan-stat-value danger">
                        <?= format_rupiah($viewLoan['outstanding_total']) ?>
                    </div>
                    <div class="loan-stat-breakdown danger">
                        Sisa Pokok: <?= format_rupiah($viewLoan['outstanding_principal']) ?> · Sisa Bunga: <?= format_rupiah($viewLoan['outstanding_interest']) ?>
                    </div>
                </div>
            </div>


            <div class="budget-status-row my-3">
                <h3 class="section-subtitle m-0">
                    Riwayat Pembayaran Cicilan
                </h3>
                <span class="finance-badge <?= $viewLoan['status'] === 'paid' ? 'badge-active' : ($viewLoan['status'] === 'partially_paid' ? 'badge-warning' : 'badge-danger') ?>">
                    Status: <?= $viewLoan['status'] === 'paid' ? 'Lunas' : ($viewLoan['status'] === 'partially_paid' ? 'Dibayar Sebagian' : 'Belum Dibayar') ?>
                </span>
            </div>

            <?php if (empty($viewLoan['payments'])): ?>
                <div class="finance-empty-state compact-empty">
                    <p>Belum ada catatan pembayaran untuk pinjaman ini.</p>
                    <button
                        type="button"
                        class="finance-btn finance-btn-secondary finance-btn-sm"
                        data-open-modal="modalPayment"
                    >
                        Catat pembayaran pertama
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="finance-table">
                        <thead>
                            <tr>
                                <th>Tanggal Bayar</th>
                                <th>Akun Sumber Kas</th>
                                <th class="text-right">Pokok Terbayar</th>
                                <th class="text-right">Bunga Terbayar</th>
                                <th class="text-right">Total Angsuran</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($viewLoan['payments'] as $p): ?>
                                <tr>
                                    <td class="td-date">
                                        <?= date('d M Y', strtotime($p['payment_date'])) ?>
                                    </td>
                                    <td class="fw-600"><?= e($p['account_name']) ?></td>
                                    <td class="text-right text-muted-dark">
                                        <?= format_rupiah($p['principal_paid']) ?>
                                    </td>
                                    <td class="text-right text-expense">
                                        <?= format_rupiah($p['interest_paid']) ?>
                                    </td>
                                    <td class="text-right fw-700">
                                        <?= format_rupiah((float) $p['principal_paid'] + (float) $p['interest_paid']) ?>
                                    </td>
                                    <td class="td-notes">
                                        <?= e($p['notes'] ?? '—') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>
    <?php endif; ?>


    <section class="finance-section">
        <div class="finance-section-heading">
            <div>
                <h2>Daftar Pinjaman</h2>
                <p>Total <?= count($loans) ?> catatan pinjaman.</p>
            </div>
        </div>

        <?php if (empty($loans)): ?>
            <div class="finance-panel">
                <div class="finance-empty-state">
                    <div class="empty-state-icon">⇄</div>
                    <h3>Belum ada catatan pinjaman</h3>
                    <p>Catat pinjaman dana beserta pelunasan bertahap untuk menjaga akurasi kewajiban finansial.</p>
                    <button
                        type="button"
                        class="finance-btn finance-btn-primary finance-btn-sm"
                        data-open-modal="modalLoan"
                    >
                        Catat pinjaman pertama
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="loans-grid">
                <?php foreach ($loans as $l): ?>
                    <article class="loan-card <?= $l['status'] === 'paid' ? 'is-paid' : '' ?>">
                        <div class="loan-card-header">
                            <div>
                                <h3 class="loan-card-title">
                                    <?= e($l['lender_name']) ?>
                                </h3>
                                <div class="loan-card-subtitle">
                                    Pinjam: <?= date('d M Y', strtotime($l['borrowed_date'])) ?>
                                    <?php if (!empty($l['due_date'])): ?>
                                        · Tempo: <?= date('d M Y', strtotime($l['due_date'])) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="finance-badge <?= $l['status'] === 'paid' ? 'badge-active' : ($l['status'] === 'partially_paid' ? 'badge-warning' : 'badge-danger') ?>">
                                <?= $l['status'] === 'paid' ? 'Lunas' : ($l['status'] === 'partially_paid' ? 'Sebagian' : 'Belum Dibayar') ?>
                            </span>
                        </div>

                        <div class="loan-obligations-box">
                            <div class="obligation-row">
                                <span class="obligation-row-label-muted">Pokok Pinjaman:</span>
                                <strong><?= format_rupiah($l['principal_amount']) ?></strong>
                            </div>
                            <?php if ((float) $l['interest_amount'] > 0): ?>
                                <div class="obligation-row">
                                    <span class="obligation-row-label-muted">Beban Bunga:</span>
                                    <span><?= format_rupiah($l['interest_amount']) ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="obligation-row">
                                <span class="text-income">Sudah Dibayar:</span>
                                <span class="text-income"><?= format_rupiah($l['total_paid']) ?></span>
                            </div>
                            <div class="obligation-row obligation-remaining">
                                <span class="obligation-row-label-danger">Sisa Kewajiban:</span>
                                <strong class="obligation-row-val-danger"><?= format_rupiah($l['outstanding_total']) ?></strong>
                            </div>
                        </div>

                        <div class="loan-card-actions">
                            <a
                                href="<?= e(url('finance/loans.php?action=view&id=' . $l['id'])) ?>"
                                class="finance-btn finance-btn-secondary finance-btn-sm"
                            >
                                Rincian & Riwayat
                            </a>
                            <a
                                href="<?= e(url('finance/loans.php?action=edit&id=' . $l['id'])) ?>"
                                class="finance-btn finance-btn-secondary finance-btn-sm"
                            >
                                Edit
                            </a>
                            <form
                                method="POST"
                                action="<?= e(url('finance/loans.php')) ?>"
                                class="inline-form"
                                data-confirm="Hapus catatan pinjaman ini?"
                            >
                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= e((string) $l['id']) ?>">
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


<div
    class="finance-modal-backdrop <?= ($activeAction === 'create' || $editingLoan !== null) ? 'active' : '' ?>"
    id="modalLoan"
>
    <div class="finance-modal">
        <div class="finance-modal-header">
            <h3><?= $editingLoan !== null ? 'Edit Pinjaman' : 'Catat Pinjaman Baru' ?></h3>
            <button
                type="button"
                class="modal-close-btn"
                data-redirect="<?= e(url('finance/loans.php')) ?>"
                aria-label="Tutup"
            >
                ✕
            </button>
        </div>

        <form method="POST" action="<?= e(url('finance/loans.php')) ?>">
            <div class="finance-modal-body">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="<?= $editingLoan !== null ? 'update' : 'create' ?>">
                <?php if ($editingLoan !== null): ?>
                    <input type="hidden" name="id" value="<?= e((string) $editingLoan['id']) ?>">
                <?php endif; ?>

                <div class="finance-form">
                    <div class="form-group">
                        <label for="l_name">Nama Pemberi / Pihak Pinjaman *</label>
                        <input
                            type="text"
                            name="lender_name"
                            id="l_name"
                            class="form-control"
                            placeholder="Contoh: Budi, Bank Mandiri KTA"
                            value="<?= e($editingLoan['lender_name'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="l_principal">Nominal Pokok Pinjaman (Rp) *</label>
                            <input
                                type="number"
                                step="any"
                                min="1"
                                name="principal_amount"
                                id="l_principal"
                                class="form-control"
                                placeholder="0"
                                value="<?= e((string) ($editingLoan['principal_amount'] ?? '')) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="l_interest">Total Bunga (Rp, Opsional)</label>
                            <input
                                type="number"
                                step="any"
                                min="0"
                                name="interest_amount"
                                id="l_interest"
                                class="form-control"
                                placeholder="0"
                                value="<?= e((string) ($editingLoan['interest_amount'] ?? 0)) ?>"
                            >
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="l_borrowed_date">Tanggal Terima Pinjaman *</label>
                            <input
                                type="date"
                                name="borrowed_date"
                                id="l_borrowed_date"
                                class="form-control"
                                value="<?= e($editingLoan['borrowed_date'] ?? date('Y-m-d')) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="l_due_date">Tanggal Jatuh Tempo (Opsional)</label>
                            <input
                                type="date"
                                name="due_date"
                                id="l_due_date"
                                class="form-control"
                                value="<?= e($editingLoan['due_date'] ?? '') ?>"
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="l_notes">Catatan (Opsional)</label>
                        <textarea
                            name="notes"
                            id="l_notes"
                            class="form-textarea"
                            placeholder="Keterangan tambahan..."
                        ><?= e($editingLoan['notes'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="finance-modal-footer">
                <button
                    type="button"
                    class="finance-btn finance-btn-secondary"
                    data-redirect="<?= e(url('finance/loans.php')) ?>"
                >
                    Batal
                </button>
                <button type="submit" class="finance-btn finance-btn-primary">
                    <?= $editingLoan !== null ? 'Simpan Perubahan' : 'Catat Pinjaman' ?>
                </button>
            </div>
        </form>
    </div>
</div>


<?php if ($viewLoan !== null): ?>
    <div class="finance-modal-backdrop" id="modalPayment">
        <div class="finance-modal">
            <div class="finance-modal-header">
                <h3>Catat Pembayaran Pinjaman</h3>
                <button
                    type="button"
                    class="modal-close-btn"
                    data-close-modal="modalPayment"
                    aria-label="Tutup"
                >
                    ✕
                </button>
            </div>

            <form method="POST" action="<?= e(url('finance/loans.php')) ?>">
                <div class="finance-modal-body">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="record_payment">
                    <input type="hidden" name="loan_id" value="<?= e((string) $viewLoan['id']) ?>">

                    <div class="finance-form">
                        <div class="form-group">
                            <label>Pinjaman Terkait</label>
                            <input
                                type="text"
                                class="form-control"
                                value="<?= e($viewLoan['lender_name']) ?>"
                                readonly
                                class="extracted-style-19"
                            >
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="pay_account">Akun Sumber Pembayaran *</label>
                                <select name="account_id" id="pay_account" class="form-select" required>
                                    <option value="">-- Pilih Akun --</option>
                                    <?php foreach ($accounts as $acc): ?>
                                        <option value="<?= $acc['id'] ?>">
                                            <?= e($acc['name']) ?> (Saldo: <?= format_rupiah($acc['current_balance']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="pay_date">Tanggal Bayar *</label>
                                <input
                                    type="date"
                                    name="payment_date"
                                    id="pay_date"
                                    class="form-control"
                                    value="<?= date('Y-m-d') ?>"
                                    required
                                >
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="pay_principal">Bayar Pokok (Rp)</label>
                                <input
                                    type="number"
                                    step="any"
                                    min="0"
                                    max="<?= (float) $viewLoan['outstanding_principal'] ?>"
                                    name="principal_paid"
                                    id="pay_principal"
                                    class="form-control"
                                    placeholder="0"
                                    value="<?= (float) $viewLoan['outstanding_principal'] ?>"
                                >
                                <p class="form-hint">Maks: <?= format_rupiah($viewLoan['outstanding_principal']) ?></p>
                            </div>

                            <div class="form-group">
                                <label for="pay_interest">Bayar Bunga (Rp)</label>
                                <input
                                    type="number"
                                    step="any"
                                    min="0"
                                    max="<?= (float) $viewLoan['outstanding_interest'] ?>"
                                    name="interest_paid"
                                    id="pay_interest"
                                    class="form-control"
                                    placeholder="0"
                                    value="0"
                                >
                                <p class="form-hint">Maks: <?= format_rupiah($viewLoan['outstanding_interest']) ?></p>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="pay_notes">Catatan Pembayaran (Opsional)</label>
                            <textarea
                                name="notes"
                                id="pay_notes"
                                class="form-textarea"
                                placeholder="Contoh: Cicilan ke-1 melalui transfer BCA"
                            ></textarea>
                        </div>
                    </div>
                </div>

                <div class="finance-modal-footer">
                    <button
                        type="button"
                        class="finance-btn finance-btn-secondary"
                        data-close-modal="modalPayment"
                    >
                        Batal
                    </button>
                    <button type="submit" class="finance-btn finance-btn-primary">
                        Simpan Pembayaran
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php require LIFEOS_ROOT . '/includes/footer.php'; ?>
