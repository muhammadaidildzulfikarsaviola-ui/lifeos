<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';

$pageTitle = 'Finance · Ringkasan';
$currentPage = 'finance';

require LIFEOS_ROOT . '/includes/header.php';

$overview = null;
if ($financeService !== null && $dbError === null) {
    try {
        $overview = $financeService->getOverviewData();
    } catch (Throwable $e) {
        $dbError = 'Terjadi kesalahan saat memuat data: ' . $e->getMessage();
    }
}

$hasData = $overview['has_any_data'] ?? false;
$totalBalance = $overview['total_balance'] ?? 0.00;
$totalIncome = $overview['total_income'] ?? 0.00;
$totalExpenses = $overview['total_expenses'] ?? 0.00;
$netCashFlow = $overview['net_cash_flow'] ?? 0.00;
$chartData = $overview['chart_data'] ?? [];
$recentTx = $overview['recent_transactions'] ?? [];
$topAccounts = $overview['accounts_summary'] ?? [];
$budgets = $overview['budgets_summary'] ?? [];
$upcomingBills = $overview['upcoming_bills'] ?? [];
$loansSummary = $overview['loans_summary'] ?? ['active_count' => 0, 'total_principal' => 0.00, 'total_outstanding' => 0.00, 'list' => []];

// Cari nilai maksimum chart untuk kalkulasi tinggi bar (persentase)
$maxChartVal = 1.0;
foreach ($chartData as $cd) {
    $maxChartVal = max($maxChartVal, (float) $cd['income'], (float) $cd['expense']);
}
?>

<div class="finance-page">

    <!-- Page heading -->
    <section class="finance-heading">
        <div>
            <p class="eyebrow">MONEY MANAGEMENT</p>
            <h1>Keuangan</h1>
            <p class="finance-subtitle">
                Pantau saldo, lacak pemasukan dan pengeluaran, serta kelola anggaran dan pinjaman Anda secara lokal.
            </p>
        </div>

        <div class="finance-header-actions">
            <a href="<?= e(url('finance/transactions.php?action=create')) ?>" class="finance-btn finance-btn-primary">
                <span>＋</span> Catat transaksi
            </a>
            <a href="<?= e(url('finance/accounts.php?action=create')) ?>" class="finance-btn finance-btn-secondary">
                <span>＋</span> Tambah akun
            </a>
        </div>
    </section>

    <!-- Navigation -->
    <?php render_finance_nav('overview'); ?>

    <!-- Alerts -->
    <?php render_finance_alerts($dbError); ?>

    <!-- Summary KPI Cards -->
    <section class="finance-section">
        <div class="finance-section-heading">
            <div>
                <h2>Ringkasan Finansial</h2>
                <p>Posisi keuangan Anda secara keseluruhan per <?= date('F Y') ?>.</p>
            </div>
            <span class="finance-period">Semua Akun Aktif</span>
        </div>

        <div class="finance-summary-grid">
            <article class="finance-summary-card balance-card">
                <div class="summary-card-heading">
                    <span>Total saldo</span>
                    <span class="summary-icon">◈</span>
                </div>
                <div class="summary-value"><?= format_rupiah($totalBalance) ?></div>
                <div class="summary-caption">Dari seluruh akun kas & bank aktif</div>
            </article>

            <article class="finance-summary-card">
                <div class="summary-card-heading">
                    <span>Pemasukan</span>
                    <span class="summary-icon income-icon">↗</span>
                </div>
                <div class="summary-value text-income"><?= format_rupiah($totalIncome) ?></div>
                <div class="summary-caption">Bulan berjalan (posted)</div>
            </article>

            <article class="finance-summary-card">
                <div class="summary-card-heading">
                    <span>Pengeluaran</span>
                    <span class="summary-icon expense-icon">↙</span>
                </div>
                <div class="summary-value text-expense"><?= format_rupiah($totalExpenses) ?></div>
                <div class="summary-caption">Bulan berjalan (beban operasional)</div>
            </article>

            <article class="finance-summary-card">
                <div class="summary-card-heading">
                    <span>Arus kas bersih</span>
                    <span class="summary-icon">⌁</span>
                </div>
                <div class="summary-value <?= $netCashFlow >= 0 ? 'text-income' : 'text-expense' ?>">
                    <?= format_rupiah($netCashFlow) ?>
                </div>
                <div class="summary-caption">Pemasukan dikurangi pengeluaran</div>
            </article>
        </div>
    </section>

    <!-- Cash Flow Chart -->
    <section class="finance-section">
        <div class="finance-section-heading">
            <div>
                <h2>Arus Kas Bulanan</h2>
                <p>Perbandingan pemasukan dan pengeluaran selama 6 bulan terakhir.</p>
            </div>
            <span class="finance-period">6 Bulan</span>
        </div>

        <article class="finance-panel cashflow-panel">
            <?php
            $hasAnyCashflow = false;
            foreach ($chartData as $cd) {
                if ($cd['income'] > 0 || $cd['expense'] > 0) {
                    $hasAnyCashflow = true;
                    break;
                }
            }
            ?>

            <?php if (!$hasAnyCashflow): ?>
                <div class="finance-empty-state">
                    <div class="empty-state-icon">▥</div>
                    <h3>Belum ada riwayat arus kas</h3>
                    <p>Pemasukan dan pengeluaran Anda akan ditampilkan di sini setelah Anda mulai mencatat transaksi.</p>
                    <a href="<?= e(url('finance/transactions.php?action=create')) ?>" class="finance-btn finance-btn-secondary finance-btn-sm">
                        Catat transaksi pertama
                    </a>
                </div>
            <?php else: ?>
                <div class="chart-container">
                    <div class="chart-bars-wrap">
                        <?php foreach ($chartData as $cd): ?>
                            <?php
                            $incHeight = $maxChartVal > 0 ? max(4, round(($cd['income'] / $maxChartVal) * 130)) : 4;
                            $expHeight = $maxChartVal > 0 ? max(4, round(($cd['expense'] / $maxChartVal) * 130)) : 4;
                            ?>
                            <div class="chart-bar-group">
                                <div class="chart-bars-pair">
                                    <div
                                        class="chart-bar bar-income"
                                        data-height="<?= (int) $incHeight ?>"
                                        title="Pemasukan: <?= e(format_rupiah($cd['income'])) ?>"
                                    ></div>
                                    <div
                                        class="chart-bar bar-expense"
                                        data-height="<?= (int) $expHeight ?>"
                                        title="Pengeluaran: <?= e(format_rupiah($cd['expense'])) ?>"
                                    ></div>
                                </div>
                                <span class="chart-label"><?= e($cd['label']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="chart-legend">
                        <div class="legend-item">
                            <span class="legend-swatch extracted-style-15"></span>
                            <span>Pemasukan</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-swatch extracted-style-16"></span>
                            <span>Pengeluaran</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </article>
    </section>

    <!-- Lower Dashboard Grid -->
    <section class="finance-lower-grid">

        <!-- Recent Transactions -->
        <article class="finance-panel">
            <div class="finance-panel-heading">
                <div>
                    <h2>Transaksi Terbaru</h2>
                    <p>Aktivitas finansial terakhir yang tercatat.</p>
                </div>
                <a href="<?= e(url('finance/transactions.php')) ?>">Lihat semua ↗</a>
            </div>

            <?php if (empty($recentTx)): ?>
                <div class="finance-empty-state compact-empty">
                    <div class="empty-state-icon">↔</div>
                    <h3>Belum ada transaksi</h3>
                    <p>Semua transaksi masuk, keluar, dan transfer akan muncul di sini.</p>
                    <a href="<?= e(url('finance/transactions.php?action=create')) ?>" class="finance-btn finance-btn-secondary finance-btn-sm">
                        Tambah transaksi
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive panel-table-wrap">
                    <table class="finance-table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Deskripsi</th>
                                <th>Akun</th>
                                <th class="text-right">Nominal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentTx as $tx): ?>
                                <tr>
                                    <td class="td-date">
                                        <?= date('d M', strtotime($tx['transaction_date'])) ?>
                                    </td>
                                    <td>
                                        <div class="td-desc-title"><?= e($tx['description']) ?></div>
                                        <?php if (!empty($tx['category_name'])): ?>
                                            <span class="finance-badge badge-category">
                                                <?= e($tx['category_name']) ?>
                                            </span>
                                        <?php elseif ($tx['type'] === 'transfer'): ?>
                                            <span class="finance-badge badge-transfer">Transfer</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="td-date">
                                        <?= e($tx['account_name']) ?>
                                        <?php if ($tx['type'] === 'transfer' && !empty($tx['target_account_name'])): ?>
                                            → <?= e($tx['target_account_name']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right">
                                        <span class="tx-amount amount-<?= e($tx['type']) ?>">
                                            <?= $tx['type'] === 'income' ? '+' : ($tx['type'] === 'expense' ? '-' : '') ?>
                                            <?= format_rupiah($tx['amount']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>

        <!-- Account Summary -->
        <article class="finance-panel">
            <div class="finance-panel-heading">
                <div>
                    <h2>Akun Saya</h2>
                    <p>Dompet, rekening bank, dan akun keuangan Anda.</p>
                </div>
                <a href="<?= e(url('finance/accounts.php')) ?>">Kelola akun ↗</a>
            </div>

            <?php if (empty($topAccounts)): ?>
                <div class="finance-empty-state compact-empty">
                    <div class="empty-state-icon">▣</div>
                    <h3>Belum ada akun</h3>
                    <p>Tambahkan akun bank atau dompet tunai untuk mulai memantau saldo.</p>
                    <a href="<?= e(url('finance/accounts.php?action=create')) ?>" class="finance-btn finance-btn-secondary finance-btn-sm">
                        Tambah akun baru
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive panel-table-wrap">
                    <table class="finance-table">
                        <thead>
                            <tr>
                                <th>Nama Akun</th>
                                <th>Tipe</th>
                                <th class="text-right">Saldo Saat Ini</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topAccounts as $acc): ?>
                                <tr>
                                    <td>
                                        <div class="td-desc-title"><?= e($acc['name']) ?></div>
                                        <?php if (!empty($acc['account_number'])): ?>
                                            <span class="stat-card-sub"><?= e($acc['account_number']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="finance-badge">
                                            <?= e(strtoupper($acc['type'])) ?>
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <span class="tx-amount">
                                            <?= format_rupiah($acc['current_balance']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>

        <!-- Budget Overview -->
        <article class="finance-panel">
            <div class="finance-panel-heading">
                <div>
                    <h2>Pantauan Anggaran</h2>
                    <p>Batas pengeluaran operasional aktif.</p>
                </div>
                <a href="<?= e(url('finance/budgets.php')) ?>">Lihat anggaran ↗</a>
            </div>

            <?php if (empty($budgets)): ?>
                <div class="finance-empty-state compact-empty">
                    <div class="empty-state-icon">◫</div>
                    <h3>Belum ada anggaran</h3>
                    <p>Tentukan batas pengeluaran kategori bulanan agar keuangan tetap terkendali.</p>
                    <a href="<?= e(url('finance/budgets.php?action=create')) ?>" class="finance-btn finance-btn-secondary finance-btn-sm">
                        Buat anggaran
                    </a>
                </div>
            <?php else: ?>
                <div class="panel-table-wrap">
                    <?php foreach ($budgets as $b): ?>
                        <div class="budget-summary-item">
                            <div class="extracted-style-17">
                                <strong class="extracted-style-18"><?= e($b['name']) ?></strong>
                                <span class="finance-badge badge-<?= e($b['status_indicator'] === 'normal' ? 'active' : ($b['status_indicator'] === 'warning' ? 'warning' : 'danger')) ?>">
                                    <?= e($b['status_label']) ?> (<?= e((string) $b['percentage']) ?>%)
                                </span>
                            </div>
                            <div class="budget-progress-bar-container">
                                <div
                                    class="budget-progress-bar <?= e($b['status_indicator']) ?>"
                                    data-width="<?= min(100, max(2, (int) $b['percentage'])) ?>"
                                ></div>
                            </div>
                            <div class="budget-amounts-row">
                                <span>Terpakai: <strong><?= format_rupiah($b['total_spent']) ?></strong></span>
                                <span>Batas: <?= format_rupiah($b['total_limit']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>

        <!-- Upcoming Bills -->
        <article class="finance-panel">
            <div class="finance-panel-heading">
                <div>
                    <h2>Tagihan Mendatang</h2>
                    <p>Pengingat pembayaran rutin terjadwal.</p>
                </div>
                <a href="<?= e(url('finance/bills.php')) ?>">Kelola tagihan ↗</a>
            </div>

            <?php if (empty($upcomingBills)): ?>
                <div class="finance-empty-state compact-empty">
                    <div class="empty-state-icon">▤</div>
                    <h3>Tidak ada tagihan mendatang</h3>
                    <p>Catat pengingat tagihan listrik, internet, atau langganan rutin Anda.</p>
                    <a href="<?= e(url('finance/bills.php?action=create')) ?>" class="finance-btn finance-btn-secondary finance-btn-sm">
                        Tambah pengingat tagihan
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive panel-table-wrap">
                    <table class="finance-table">
                        <thead>
                            <tr>
                                <th>Tagihan</th>
                                <th>Jatuh Tempo</th>
                                <th class="text-right">Nominal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcomingBills as $bill): ?>
                                <tr>
                                    <td>
                                        <div class="td-desc-title"><?= e($bill['name']) ?></div>
                                        <span class="stat-card-sub"><?= e(ucfirst($bill['frequency'])) ?></span>
                                    </td>
                                    <td>
                                        <span class="finance-badge <?= $bill['urgency'] === 'overdue' ? 'badge-danger' : ($bill['urgency'] === 'due_soon' ? 'badge-warning' : '') ?>">
                                            <?= e($bill['urgency_label']) ?>
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <span class="tx-amount">
                                            <?= format_rupiah($bill['expected_amount']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>

    </section>

    <!-- Personal Loans Summary Section -->
    <section class="finance-section">
        <article class="finance-panel">
            <div class="finance-panel-heading">
                <div>
                    <h2>Pinjaman Pribadi</h2>
                    <p>Catatan pinjaman yang diterima dan kewajiban pelunasan.</p>
                </div>
                <a href="<?= e(url('finance/loans.php')) ?>">Kelola pinjaman ↗</a>
            </div>

            <?php if ($loansSummary['active_count'] === 0): ?>
                <div class="finance-empty-state compact-empty">
                    <div class="empty-state-icon">⇄</div>
                    <h3>Tidak ada pinjaman aktif</h3>
                    <p>Catat pinjaman pribadi beserta riwayat pembayaran pokok dan bunga bertahap.</p>
                    <a href="<?= e(url('finance/loans.php?action=create')) ?>" class="finance-btn finance-btn-secondary finance-btn-sm">
                        Catat pinjaman baru
                    </a>
                </div>
            <?php else: ?>
                <div class="finance-stats-grid">
                    <div class="finance-stat-card">
                        <span class="stat-card-label">Pinjaman Aktif</span>
                        <div class="stat-card-value">
                            <?= e((string) $loansSummary['active_count']) ?> Pinjaman
                        </div>
                    </div>
                    <div class="finance-stat-card">
                        <span class="stat-card-label">Total Pokok Awal</span>
                        <div class="stat-card-value">
                            <?= format_rupiah($loansSummary['total_principal']) ?>
                        </div>
                    </div>
                    <div class="finance-stat-card stat-card-danger">
                        <span class="stat-card-label label-danger">Total Sisa Kewajiban</span>
                        <div class="stat-card-value value-danger">
                            <?= format_rupiah($loansSummary['total_outstanding']) ?>
                        </div>
                    </div>
                </div>

                <div class="table-responsive panel-table-wrap">
                    <table class="finance-table">
                        <thead>
                            <tr>
                                <th>Pihak / Pemberi Pinjaman</th>
                                <th>Pokok Awal</th>
                                <th>Terbayar</th>
                                <th>Sisa Kewajiban</th>
                                <th>Status</th>
                                <th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($loansSummary['list'] as $loan): ?>
                                <tr>
                                    <td class="td-desc-title"><?= e($loan['lender_name']) ?></td>
                                    <td><?= format_rupiah($loan['principal_amount']) ?></td>
                                    <td class="text-income"><?= format_rupiah($loan['total_paid']) ?></td>
                                    <td class="tx-amount text-expense"><?= format_rupiah($loan['outstanding_total']) ?></td>
                                    <td>
                                        <span class="finance-badge <?= $loan['status'] === 'paid' ? 'badge-active' : ($loan['status'] === 'partially_paid' ? 'badge-warning' : 'badge-danger') ?>">
                                            <?= $loan['status'] === 'paid' ? 'Lunas' : ($loan['status'] === 'partially_paid' ? 'Sebagian' : 'Belum Dibayar') ?>
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <a href="<?= e(url('finance/loans.php?action=view&id=' . $loan['id'])) ?>" class="finance-btn finance-btn-secondary finance-btn-sm">
                                            Rincian & Bayar
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>
    </section>

</div>

<?php require LIFEOS_ROOT . '/includes/footer.php'; ?>
