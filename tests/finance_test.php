<?php
declare(strict_types=1);

define('LIFEOS_ROOT', dirname(__DIR__));
require_once LIFEOS_ROOT . '/function.php';
require_once LIFEOS_ROOT . '/app/Modules/Finance/FinanceService.php';

use App\Modules\Finance\FinanceService;

class FinanceTestRunner
{
    private PDO $db;
    private FinanceService $service;
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    public function __construct()
    {
        $this->db = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->initSchema();
        $this->service = new FinanceService($this->db);
    }

    private function initSchema(): void
    {
        $schema = "
            CREATE TABLE accounts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                type TEXT NOT NULL DEFAULT 'bank',
                account_number TEXT NULL,
                initial_balance REAL NOT NULL DEFAULT 0.00,
                color TEXT NULL DEFAULT '#344f41',
                is_active INTEGER NOT NULL DEFAULT 1,
                notes TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                type TEXT NOT NULL,
                icon TEXT NULL DEFAULT '◈',
                color TEXT NULL DEFAULT '#526f5d',
                is_system INTEGER NOT NULL DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (name, type)
            );

            CREATE TABLE transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'posted',
                description TEXT NOT NULL,
                amount REAL NOT NULL,
                transaction_date TEXT NOT NULL,
                account_id INTEGER NOT NULL,
                target_account_id INTEGER NULL,
                category_id INTEGER NULL,
                is_loan_principal INTEGER NOT NULL DEFAULT 0,
                reference_number TEXT NULL,
                notes TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                deleted_at TEXT NULL DEFAULT NULL
            );

            CREATE TABLE transaction_lines (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                transaction_id INTEGER NOT NULL,
                account_id INTEGER NOT NULL,
                direction TEXT NOT NULL,
                amount REAL NOT NULL,
                line_date TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE transfers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                transaction_id INTEGER NOT NULL,
                from_account_id INTEGER NOT NULL,
                to_account_id INTEGER NOT NULL,
                amount REAL NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE budgets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                period TEXT NOT NULL DEFAULT 'monthly',
                start_date TEXT NOT NULL,
                end_date TEXT NOT NULL,
                total_limit REAL NOT NULL DEFAULT 0.00,
                notes TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                deleted_at TEXT NULL DEFAULT NULL
            );

            CREATE TABLE budget_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                budget_id INTEGER NOT NULL,
                category_id INTEGER NOT NULL,
                allocated_amount REAL NOT NULL DEFAULT 0.00,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (budget_id, category_id)
            );

            CREATE TABLE fixed_bill_templates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                category_id INTEGER NULL,
                account_id INTEGER NULL,
                expected_amount REAL NOT NULL DEFAULT 0.00,
                frequency TEXT NOT NULL DEFAULT 'monthly',
                due_day INTEGER NULL,
                next_due_date TEXT NOT NULL,
                notes TEXT NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                deleted_at TEXT NULL DEFAULT NULL
            );

            CREATE TABLE loans (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                lender_name TEXT NOT NULL,
                loan_type TEXT NOT NULL DEFAULT 'borrowed',
                principal_amount REAL NOT NULL DEFAULT 0.00,
                interest_amount REAL NOT NULL DEFAULT 0.00,
                borrowed_date TEXT NOT NULL,
                due_date TEXT NULL,
                status TEXT NOT NULL DEFAULT 'unpaid',
                notes TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                deleted_at TEXT NULL DEFAULT NULL
            );

            CREATE TABLE loan_payments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                loan_id INTEGER NOT NULL,
                account_id INTEGER NOT NULL,
                principal_paid REAL NOT NULL DEFAULT 0.00,
                interest_paid REAL NOT NULL DEFAULT 0.00,
                payment_date TEXT NOT NULL,
                notes TEXT NULL,
                principal_transaction_id INTEGER NULL,
                interest_transaction_id INTEGER NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ";

        $this->db->exec($schema);
    }

    private function assert(bool $condition, string $testName, string $message = ''): void
    {
        if ($condition) {
            $this->passed++;
            echo "  [PASS] {$testName}\n";
        } else {
            $this->failed++;
            $errorMsg = $message !== '' ? " - {$message}" : '';
            $this->failures[] = "{$testName}{$errorMsg}";
            echo "  [FAIL] {$testName}{$errorMsg}\n";
        }
    }

    public function run(): void
    {
        echo "========================================================\n";
        echo "LIFEOS FINANCE MODULE - COMPREHENSIVE AUTOMATED TESTS\n";
        echo "========================================================\n\n";

        $this->testSchemaAndDefaultCategories();
        $this->testAccountLifecycleAndBalance();
        $this->testIncomeTransaction();
        $this->testExpenseTransaction();
        $this->testTransferIntegrity();
        $this->testTransactionSearchAndAccountFilters();
        $this->testTransactionEditAndNoMovementDuplication();
        $this->testSoftDeleteExclusion();
        $this->testBudgetSpendingCalculation();
        $this->testLoanRepaymentPrincipalAndInterestSplit();
        $this->testBillRemindersDoNotAffectBalance();
        $this->testOverviewCalculations();

        echo "\n--------------------------------------------------------\n";
        echo "TEST SUMMARY: {$this->passed} Passed, {$this->failed} Failed\n";
        echo "--------------------------------------------------------\n";

        if ($this->failed > 0) {
            echo "Failures:\n";
            foreach ($this->failures as $f) {
                echo "  - {$f}\n";
            }
            exit(1);
        } else {
            echo "All tests passed successfully!\n";
            exit(0);
        }
    }

    private function testSchemaAndDefaultCategories(): void
    {
        echo "[1] Schema & Default Categories:\n";
        $installed = $this->service->isInstalled();
        $this->assert($installed, "Tabel finance terdeteksi via isInstalled()");

        $categories = $this->service->getCategories();
        $this->assert(count($categories) >= 14, "Seeding kategori default idempotent (total: " . count($categories) . ")");

        $incomeCats = $this->service->getCategories('income');
        $this->assert(count($incomeCats) >= 4, "Kategori pemasukan tersedia");

        $expenseCats = $this->service->getCategories('expense');
        $this->assert(count($expenseCats) >= 9, "Kategori pengeluaran tersedia");
    }

    private function testAccountLifecycleAndBalance(): void
    {
        echo "\n[2] Account Lifecycle & Ledger Balance:\n";

        $bcaId = $this->service->saveAccount([
            'name' => 'Rekening BCA',
            'type' => 'bank',
            'initial_balance' => 5000000.00,
            'is_active' => 1,
        ]);
        $this->assert($bcaId > 0, "Tambah Akun BCA dengan saldo awal Rp 5.000.000");

        $tunaiId = $this->service->saveAccount([
            'name' => 'Dompet Tunai',
            'type' => 'cash',
            'initial_balance' => 500000.00,
            'is_active' => 1,
        ]);
        $this->assert($tunaiId > 0, "Tambah Akun Dompet Tunai dengan saldo awal Rp 500.000");

        $bca = $this->service->getAccountById($bcaId);
        $this->assert((float) $bca['current_balance'] === 5000000.00, "Saldo awal BCA akurat: " . format_rupiah($bca['current_balance']));

        $tunai = $this->service->getAccountById($tunaiId);
        $this->assert((float) $tunai['current_balance'] === 500000.00, "Saldo awal Tunai akurat: " . format_rupiah($tunai['current_balance']));
    }

    private function testIncomeTransaction(): void
    {
        echo "\n[3] Income Transaction Ledger Flow:\n";

        $bca = $this->getAccountByName('Rekening BCA');
        $bcaId = (int) $bca['id'];
        $categories = $this->service->getCategories('income');
        $catId = (int) $categories[0]['id'];

        $txId = $this->service->createTransaction([
            'type' => 'income',
            'description' => 'Gaji Pokok November',
            'amount' => 10000000.00,
            'transaction_date' => date('Y-m-d'),
            'account_id' => $bcaId,
            'category_id' => $catId,
            'status' => 'posted',
        ]);
        $this->assert($txId > 0, "Catat Pemasukan Gaji Rp 10.000.000 ke BCA");

        $bcaAfter = $this->service->getAccountById($bcaId);
        $expectedBalance = 5000000.00 + 10000000.00;
        $this->assert((float) $bcaAfter['current_balance'] === $expectedBalance, "Pemasukan menambah saldo BCA menjadi " . format_rupiah($bcaAfter['current_balance']));

        $stmt = $this->db->prepare("SELECT * FROM transaction_lines WHERE transaction_id = ?");
        $stmt->execute([$txId]);
        $lines = $stmt->fetchAll();
        $this->assert(count($lines) === 1 && $lines[0]['direction'] === 'in' && (float) $lines[0]['amount'] === 10000000.00, "Ledger movement masuk (in) tercatat persis 1 baris");
    }

    private function testExpenseTransaction(): void
    {
        echo "\n[4] Expense Transaction Ledger Flow:\n";

        $tunai = $this->getAccountByName('Dompet Tunai');
        $tunaiId = (int) $tunai['id'];
        $categories = $this->service->getCategories('expense');
        $catId = (int) $categories[0]['id'];

        $txId = $this->service->createTransaction([
            'type' => 'expense',
            'description' => 'Makan Siang & Kopi',
            'amount' => 200000.00,
            'transaction_date' => date('Y-m-d'),
            'account_id' => $tunaiId,
            'category_id' => $catId,
            'status' => 'posted',
        ]);
        $this->assert($txId > 0, "Catat Pengeluaran Rp 200.000 dari Dompet Tunai");

        $tunaiAfter = $this->service->getAccountById($tunaiId);
        $expected = 500000.00 - 200000.00;
        $this->assert((float) $tunaiAfter['current_balance'] === $expected, "Pengeluaran mengurangi saldo Tunai menjadi " . format_rupiah($tunaiAfter['current_balance']));

        $stmt = $this->db->prepare("SELECT * FROM transaction_lines WHERE transaction_id = ?");
        $stmt->execute([$txId]);
        $lines = $stmt->fetchAll();
        $this->assert(count($lines) === 1 && $lines[0]['direction'] === 'out' && (float) $lines[0]['amount'] === 200000.00, "Ledger movement keluar (out) tercatat persis 1 baris");
    }

    private function testTransferIntegrity(): void
    {
        echo "\n[5] Transfer Integrity & Non-Income/Expense Principle:\n";

        $bca = $this->getAccountByName('Rekening BCA');
        $tunai = $this->getAccountByName('Dompet Tunai');
        $bcaInitial = (float) $bca['current_balance'];
        $tunaiInitial = (float) $tunai['current_balance'];

        $transferAmount = 1000000.00;

        $txId = $this->service->createTransaction([
            'type' => 'transfer',
            'description' => 'Tarik Tunai dari Rekening BCA ke Dompet',
            'amount' => $transferAmount,
            'transaction_date' => date('Y-m-d'),
            'account_id' => (int) $bca['id'],
            'target_account_id' => (int) $tunai['id'],
            'status' => 'posted',
        ]);
        $this->assert($txId > 0, "Buat Transfer Rp 1.000.000 dari BCA ke Tunai");

        $bcaAfter = $this->service->getAccountById((int) $bca['id']);
        $tunaiAfter = $this->service->getAccountById((int) $tunai['id']);

        $this->assert((float) $bcaAfter['current_balance'] === $bcaInitial - $transferAmount, "Saldo BCA berkurang: " . format_rupiah($bcaAfter['current_balance']));
        $this->assert((float) $tunaiAfter['current_balance'] === $tunaiInitial + $transferAmount, "Saldo Tunai bertambah: " . format_rupiah($tunaiAfter['current_balance']));

        $stmt = $this->db->prepare("SELECT * FROM transaction_lines WHERE transaction_id = ? ORDER BY direction ASC");
        $stmt->execute([$txId]);
        $lines = $stmt->fetchAll();
        $this->assert(count($lines) === 2, "Transfer menghasilkan tepat 2 ledger movements");
        $this->assert($lines[0]['direction'] === 'in' && (int) $lines[0]['account_id'] === (int) $tunai['id'], "Line masuk ke akun target");
        $this->assert($lines[1]['direction'] === 'out' && (int) $lines[1]['account_id'] === (int) $bca['id'], "Line keluar dari akun sumber");

        $overview = $this->service->getOverviewData();
        $this->assert((float) $overview['total_income'] === 10000000.00, "Transfer TIDAK dihitung sebagai Income (tetap Rp 10.000.000)");
        $this->assert((float) $overview['total_expenses'] === 200000.00, "Transfer TIDAK dihitung sebagai Expense (tetap Rp 200.000)");
    }

    private function testTransactionEditAndNoMovementDuplication(): void
    {
        echo "\n[6] Transaction Edit & No Movement Duplication:\n";

        $tunai = $this->getAccountByName('Dompet Tunai');
        $tunaiId = (int) $tunai['id'];

        $stmt = $this->db->prepare("SELECT id FROM transactions WHERE description = 'Makan Siang & Kopi'");
        $stmt->execute();
        $txId = (int) $stmt->fetchColumn();

        $this->service->updateTransaction($txId, [
            'amount' => 250000.00,
            'description' => 'Makan Siang & Kopi (Revisi)',
        ]);

        $stmtLines = $this->db->prepare("SELECT COUNT(*) FROM transaction_lines WHERE transaction_id = ?");
        $stmtLines->execute([$txId]);
        $lineCount = (int) $stmtLines->fetchColumn();
        $this->assert($lineCount === 1, "Ledger lines setelah update tidak mengganda (tetap 1 baris)");

        $tunaiAfter = $this->service->getAccountById($tunaiId);
        $this->assert((float) $tunaiAfter['current_balance'] === 1250000.00, "Saldo akun terupdate akurat setelah edit: " . format_rupiah($tunaiAfter['current_balance']));
    }

    private function testTransactionSearchAndAccountFilters(): void
    {
        echo "\n[6a] Transaction Search & Account Filters:\n";

        $account = $this->getAccountByName('Rekening BCA');
        $filters = ['account_id' => (int) $account['id'], 'search' => 'Gaji Pokok'];
        $rows = $this->service->getTransactions($filters, 20, 0);
        $count = $this->service->countTransactions($filters);
        $this->assert(count($rows) === 1 && str_contains($rows[0]['description'], 'Gaji Pokok'), 'Search and account filters bind correctly with native PDO placeholders');
        $this->assert($count === 1, 'Filtered transaction count matches filtered transaction rows');
    }

    private function testSoftDeleteExclusion(): void
    {
        echo "\n[7] Soft Delete Transaction Exclusion:\n";

        $tunai = $this->getAccountByName('Dompet Tunai');
        $tunaiId = (int) $tunai['id'];

        $stmt = $this->db->prepare("SELECT id FROM transactions WHERE description LIKE 'Makan Siang%'");
        $stmt->execute();
        $txId = (int) $stmt->fetchColumn();

        $this->service->softDeleteTransaction($txId);

        $tunaiAfter = $this->service->getAccountById($tunaiId);
        $this->assert((float) $tunaiAfter['current_balance'] === 1500000.00, "Soft-deleted transaksi keluar dari saldo (saldo kembali menjadi " . format_rupiah($tunaiAfter['current_balance']) . ")");

        $overview = $this->service->getOverviewData();
        $this->assert((float) $overview['total_expenses'] === 0.00, "Soft-deleted transaksi keluar dari laporan pengeluaran (total expenses = 0)");
    }

    private function testBudgetSpendingCalculation(): void
    {
        echo "\n[8] Budget Spending Calculation:\n";

        $categories = $this->service->getCategories('expense');
        $catMakan = null;
        foreach ($categories as $c) {
            if ($c['name'] === 'Makanan & Minuman') {
                $catMakan = $c;
                break;
            }
        }
        $catMakanId = $catMakan ? (int) $catMakan['id'] : (int) $categories[0]['id'];

        $budgetId = $this->service->saveBudget([
            'name' => 'Anggaran Bulanan Operasional',
            'period' => 'monthly',
            'start_date' => date('Y-m-01'),
            'end_date' => date('Y-m-t'),
            'total_limit' => 3000000.00,
            'categories' => [
                $catMakanId => 1000000.00,
            ],
        ]);
        $this->assert($budgetId > 0, "Buat anggaran Rp 3.000.000 (Kategori Makanan: Rp 1.000.000)");

        $tunai = $this->getAccountByName('Dompet Tunai');

        $this->service->createTransaction([
            'type' => 'expense',
            'description' => 'Makan Malam Restoran',
            'amount' => 400000.00,
            'transaction_date' => date('Y-m-d'),
            'account_id' => (int) $tunai['id'],
            'category_id' => $catMakanId,
            'status' => 'posted',
        ]);

        $budget = $this->service->getBudgetById($budgetId);
        $this->assert((float) $budget['total_spent'] === 400000.00, "Realisasi pengeluaran anggaran dihitung akurat: " . format_rupiah($budget['total_spent']));
        $this->assert((float) $budget['remaining'] === 2600000.00, "Sisa anggaran dihitung akurat: " . format_rupiah($budget['remaining']));
        $this->assert($budget['status_indicator'] === 'normal', "Status indikator normal (<80%)");
    }

    private function testLoanRepaymentPrincipalAndInterestSplit(): void
    {
        echo "\n[9] Loan Repayment Principal & Interest Separation:\n";

        $loanId = $this->service->saveLoan([
            'lender_name' => 'Koperasi Simpan Pinjam Sejahtera',
            'principal_amount' => 2000000.00,
            'interest_amount' => 200000.00,
            'borrowed_date' => date('Y-m-d'),
        ]);
        $this->assert($loanId > 0, "Catat pinjaman Rp 2.000.000 pokok + Rp 200.000 bunga");

        $bca = $this->getAccountByName('Rekening BCA');
        $bcaBalanceBefore = (float) $bca['current_balance'];

        $paymentId = $this->service->recordLoanPayment([
            'loan_id' => $loanId,
            'account_id' => (int) $bca['id'],
            'payment_date' => date('Y-m-d'),
            'principal_paid' => 1000000.00,
            'interest_paid' => 100000.00,
            'notes' => 'Cicilan 1',
        ]);
        $this->assert($paymentId > 0, "Bayar angsuran 1: Pokok Rp 1.000.000 + Bunga Rp 100.000");

        $loanAfter = $this->service->getLoanById($loanId);
        $this->assert((float) $loanAfter['outstanding_principal'] === 1000000.00, "Sisa pokok berkurang menjadi Rp 1.000.000");
        $this->assert((float) $loanAfter['outstanding_interest'] === 100000.00, "Sisa bunga berkurang menjadi Rp 100.000");
        $this->assert($loanAfter['status'] === 'partially_paid', "Status pinjaman: partially_paid");

        $bcaAfter = $this->service->getAccountById((int) $bca['id']);
        $this->assert((float) $bcaAfter['current_balance'] === $bcaBalanceBefore - 1100000.00, "Kas keluar akun BCA berkurang Rp 1.100.000: " . format_rupiah($bcaAfter['current_balance']));

        $overview = $this->service->getOverviewData();
        $this->assert((float) $overview['total_expenses'] === 500000.00, "Pokok pinjaman BUKAN expense, Bunga pinjaman ADALAH expense (Total Expenses = Rp 500.000)");

        $this->service->recordLoanPayment([
            'loan_id' => $loanId,
            'account_id' => (int) $bca['id'],
            'payment_date' => date('Y-m-d'),
            'principal_paid' => 1000000.00,
            'interest_paid' => 100000.00,
            'notes' => 'Cicilan 2 Pelunasan',
        ]);
        $loanFinal = $this->service->getLoanById($loanId);
        $this->assert($loanFinal['status'] === 'paid', "Setelah lunas, status pinjaman menjadi: paid");
    }

    private function testBillRemindersDoNotAffectBalance(): void
    {
        echo "\n[10] Fixed Bill Reminders (No Balance Impact):\n";

        $bca = $this->getAccountByName('Rekening BCA');
        $bcaBalanceBefore = (float) $bca['current_balance'];

        $billId = $this->service->saveBill([
            'name' => 'Langganan Internet IndiHome',
            'expected_amount' => 450000.00,
            'frequency' => 'monthly',
            'next_due_date' => date('Y-m-d', strtotime('+5 days')),
        ]);
        $this->assert($billId > 0, "Tambah pengingat tagihan Rp 450.000");

        $bcaAfter = $this->service->getAccountById((int) $bca['id']);
        $this->assert((float) $bcaAfter['current_balance'] === $bcaBalanceBefore, "Pengingat tagihan SAMA SEKALI TIDAK mengurangi saldo akun");

        $stmt = $this->db->query("SELECT COUNT(*) FROM transactions WHERE description LIKE '%IndiHome%'");
        $this->assert((int) $stmt->fetchColumn() === 0, "Pengingat tagihan TIDAK membuat transaksi otomatis");
    }

    private function testOverviewCalculations(): void
    {
        echo "\n[11] Overview Data & Multi-Account Balance Aggregation:\n";

        $overview = $this->service->getOverviewData();
        $this->assert(isset($overview['total_balance']), "Total balance terdefinisi");
        $this->assert(isset($overview['total_income']), "Total income terdefinisi");
        $this->assert(isset($overview['total_expenses']), "Total expenses terdefinisi");
        $this->assert(isset($overview['net_cash_flow']), "Net cash flow terdefinisi");

        $expectedNet = $overview['total_income'] - $overview['total_expenses'];
        $this->assert((float) $overview['net_cash_flow'] === $expectedNet, "Net cash flow konsisten dengan Income - Expenses");
    }

    private function getAccountByName(string $name): array
    {
        $stmt = $this->db->prepare("SELECT id FROM accounts WHERE name = ?");
        $stmt->execute([$name]);
        $id = (int) $stmt->fetchColumn();
        return $this->service->getAccountById($id);
    }
}

$runner = new FinanceTestRunner();
$runner->run();
