<?php
declare(strict_types=1);

namespace App\Modules\Finance;

use PDO;
use Throwable;
use RuntimeException;
use InvalidArgumentException;

class FinanceService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    public function getDb(): PDO
    {
        return $this->db;
    }

    /**
     * Cek apakah tabel finance sudah terpasang di database.
     */
    public function isInstalled(): bool
    {
        try {
            $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $stmt = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='accounts'");
                return (bool) $stmt->fetchColumn();
            }
            $stmt = $this->db->query("SHOW TABLES LIKE 'accounts'");
            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    // =========================================================================
    // AKUN (ACCOUNTS)
    // =========================================================================

    /**
     * Ambil semua akun beserta saldo saat ini yang dihitung dari ledger.
     * Saldo = Initial Balance + Total Masuk - Total Keluar.
     */
    public function getAccounts(bool $onlyActive = false): array
    {
        $where = $onlyActive ? 'WHERE a.is_active = 1' : '';

        $sql = "
            SELECT 
                a.*,
                COALESCE(m.total_in, 0.00) AS total_incoming,
                COALESCE(m.total_out, 0.00) AS total_outgoing,
                (a.initial_balance + COALESCE(m.total_in, 0.00) - COALESCE(m.total_out, 0.00)) AS current_balance,
                COALESCE(m.tx_count, 0) AS transaction_count
            FROM accounts a
            LEFT JOIN (
                SELECT 
                    tl.account_id,
                    SUM(CASE WHEN tl.direction = 'in' THEN tl.amount ELSE 0 END) AS total_in,
                    SUM(CASE WHEN tl.direction = 'out' THEN tl.amount ELSE 0 END) AS total_out,
                    COUNT(DISTINCT tl.transaction_id) AS tx_count
                FROM transaction_lines tl
                INNER JOIN transactions t ON t.id = tl.transaction_id
                WHERE t.status = 'posted' AND t.deleted_at IS NULL
                GROUP BY tl.account_id
            ) m ON m.account_id = a.id
            {$where}
            ORDER BY a.is_active DESC, a.name ASC
        ";

        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Ambil satu akun berdasarkan ID dengan saldo saat ini.
     */
    public function getAccountById(int $id): ?array
    {
        $sql = "
            SELECT 
                a.*,
                COALESCE(m.total_in, 0.00) AS total_incoming,
                COALESCE(m.total_out, 0.00) AS total_outgoing,
                (a.initial_balance + COALESCE(m.total_in, 0.00) - COALESCE(m.total_out, 0.00)) AS current_balance,
                COALESCE(m.tx_count, 0) AS transaction_count
            FROM accounts a
            LEFT JOIN (
                SELECT 
                    tl.account_id,
                    SUM(CASE WHEN tl.direction = 'in' THEN tl.amount ELSE 0 END) AS total_in,
                    SUM(CASE WHEN tl.direction = 'out' THEN tl.amount ELSE 0 END) AS total_out,
                    COUNT(DISTINCT tl.transaction_id) AS tx_count
                FROM transaction_lines tl
                INNER JOIN transactions t ON t.id = tl.transaction_id
                WHERE t.status = 'posted' AND t.deleted_at IS NULL
                GROUP BY tl.account_id
            ) m ON m.account_id = a.id
            WHERE a.id = :id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $account = $stmt->fetch();

        return $account ?: null;
    }

    /**
     * Simpan akun (tambah baru atau perbarui).
     */
    public function saveAccount(array $data, ?int $id = null): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        $type = (string) ($data['type'] ?? 'bank');
        $accountNumber = trim((string) ($data['account_number'] ?? ''));
        $initialBalance = (float) ($data['initial_balance'] ?? 0.00);
        $color = trim((string) ($data['color'] ?? '#344f41'));
        $isActive = isset($data['is_active']) ? (int) (bool) $data['is_active'] : 1;
        $notes = trim((string) ($data['notes'] ?? ''));

        if ($name === '') {
            throw new InvalidArgumentException('Nama akun wajib diisi.');
        }

        $validTypes = ['cash', 'bank', 'ewallet', 'savings', 'other'];
        if (!in_array($type, $validTypes, true)) {
            $type = 'other';
        }

        if ($id !== null && $id > 0) {
            $stmt = $this->db->prepare("
                UPDATE accounts
                SET name = :name,
                    type = :type,
                    account_number = :account_number,
                    initial_balance = :initial_balance,
                    color = :color,
                    is_active = :is_active,
                    notes = :notes
                WHERE id = :id
            ");
            $stmt->execute([
                'name' => $name,
                'type' => $type,
                'account_number' => $accountNumber !== '' ? $accountNumber : null,
                'initial_balance' => $initialBalance,
                'color' => $color !== '' ? $color : '#344f41',
                'is_active' => $isActive,
                'notes' => $notes !== '' ? $notes : null,
                'id' => $id,
            ]);
            return $id;
        }

        $stmt = $this->db->prepare("
            INSERT INTO accounts (name, type, account_number, initial_balance, color, is_active, notes)
            VALUES (:name, :type, :account_number, :initial_balance, :color, :is_active, :notes)
        ");
        $stmt->execute([
            'name' => $name,
            'type' => $type,
            'account_number' => $accountNumber !== '' ? $accountNumber : null,
            'initial_balance' => $initialBalance,
            'color' => $color !== '' ? $color : '#344f41',
            'is_active' => $isActive,
            'notes' => $notes !== '' ? $notes : null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Hapus atau arsipkan akun.
     * Akun yang memiliki riwayat transaksi TIDAK boleh dihapus secara fisik.
     */
    public function deleteAccount(int $id): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM transaction_lines 
            WHERE account_id = :id
        ");
        $stmt->execute(['id' => $id]);
        $hasMovements = (int) $stmt->fetchColumn() > 0;

        if ($hasMovements) {
            throw new InvalidArgumentException(
                'Akun memiliki histori transaksi dan tidak boleh dihapus. Anda dapat menonaktifkannya.'
            );
        }

        $stmt = $this->db->prepare("DELETE FROM accounts WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function toggleAccountActive(int $id, bool $isActive): bool
    {
        $stmt = $this->db->prepare("UPDATE accounts SET is_active = :active WHERE id = :id");
        return $stmt->execute([
            'active' => $isActive ? 1 : 0,
            'id' => $id,
        ]);
    }

    // =========================================================================
    // KATEGORI (CATEGORIES)
    // =========================================================================

    public function getCategories(?string $type = null): array
    {
        $this->ensureDefaultCategories();

        if ($type !== null && in_array($type, ['income', 'expense'], true)) {
            $stmt = $this->db->prepare("SELECT * FROM categories WHERE type = :type ORDER BY name ASC");
            $stmt->execute(['type' => $type]);
            return $stmt->fetchAll();
        }

        return $this->db->query("SELECT * FROM categories ORDER BY type ASC, name ASC")->fetchAll();
    }

    public function ensureDefaultCategories(): void
    {
        static $seeded = false;
        if ($seeded) {
            return;
        }

        try {
            $stmt = $this->db->query("SELECT COUNT(*) FROM categories");
            if ((int) $stmt->fetchColumn() === 0) {
                $categories = [
                    ['Gaji & Pendapatan', 'income', '↗', '#2f6846'],
                    ['Penjualan & Bisnis', 'income', '◫', '#396b52'],
                    ['Investasi & Dividen', 'income', '◈', '#3b705e'],
                    ['Hadiah & Bonus', 'income', '◇', '#4a7c68'],
                    ['Pendapatan Lain', 'income', '＋', '#53826e'],
                    ['Makanan & Minuman', 'expense', '☕', '#8c594d'],
                    ['Transportasi', 'expense', '→', '#7a6352'],
                    ['Tempat Tinggal & Utilitas', 'expense', '⌂', '#636b75'],
                    ['Kebutuhan Rumah Tangga', 'expense', '▦', '#6b705c'],
                    ['Tagihan & Langganan', 'expense', '▤', '#7e5a6f'],
                    ['Kesehatan & Medis', 'expense', '✚', '#8f4f4f'],
                    ['Pendidikan & Pengembangan', 'expense', '✎', '#546b82'],
                    ['Hiburan & Hobi', 'expense', '♬', '#825f78'],
                    ['Beban Bunga Pinjaman', 'expense', '％', '#a35050'],
                    ['Pengeluaran Lain', 'expense', '↙', '#787878'],
                ];

                $insert = $this->db->prepare("
                    INSERT INTO categories (name, type, icon, color, is_system)
                    VALUES (:name, :type, :icon, :color, 1)
                ");

                foreach ($categories as [$name, $type, $icon, $color]) {
                    $insert->execute([
                        'name' => $name,
                        'type' => $type,
                        'icon' => $icon,
                        'color' => $color,
                    ]);
                }
            }
            $seeded = true;
        } catch (Throwable) {
            // Abaikan jika tabel belum ada saat pengecekan awal
        }
    }

    // =========================================================================
    // TRANSAKSI (TRANSACTIONS & LEDGER)
    // =========================================================================

    /**
     * Ambil daftar transaksi dengan filter, pencarian, dan pagination.
     */
    public function getTransactions(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $conditions = ['t.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['type'])) {
            $conditions[] = 't.type = :type';
            $params['type'] = $filters['type'];
        }

        if (!empty($filters['account_id'])) {
            $conditions[] = '(t.account_id = :account_id OR t.target_account_id = :account_id)';
            $params['account_id'] = (int) $filters['account_id'];
        }

        if (!empty($filters['category_id'])) {
            $conditions[] = 't.category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }

        if (!empty($filters['start_date'])) {
            $conditions[] = 't.transaction_date >= :start_date';
            $params['start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $conditions[] = 't.transaction_date <= :end_date';
            $params['end_date'] = $filters['end_date'];
        }

        if (!empty($filters['status'])) {
            $conditions[] = 't.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(t.description LIKE :search OR t.notes LIKE :search OR t.reference_number LIKE :search)';
            $params['search'] = '%' . trim((string) $filters['search']) . '%';
        }

        $whereClause = 'WHERE ' . implode(' AND ', $conditions);

        $sql = "
            SELECT 
                t.*,
                a.name AS account_name,
                a.type AS account_type,
                a.color AS account_color,
                ta.name AS target_account_name,
                c.name AS category_name,
                c.icon AS category_icon,
                c.color AS category_color
            FROM transactions t
            INNER JOIN accounts a ON a.id = t.account_id
            LEFT JOIN accounts ta ON ta.id = t.target_account_id
            LEFT JOIN categories c ON c.id = t.category_id
            {$whereClause}
            ORDER BY t.transaction_date DESC, t.id DESC
            LIMIT " . (int) $limit . " OFFSET " . (int) $offset . "
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countTransactions(array $filters = []): int
    {
        $conditions = ['t.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['type'])) {
            $conditions[] = 't.type = :type';
            $params['type'] = $filters['type'];
        }

        if (!empty($filters['account_id'])) {
            $conditions[] = '(t.account_id = :account_id OR t.target_account_id = :account_id)';
            $params['account_id'] = (int) $filters['account_id'];
        }

        if (!empty($filters['category_id'])) {
            $conditions[] = 't.category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }

        if (!empty($filters['start_date'])) {
            $conditions[] = 't.transaction_date >= :start_date';
            $params['start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $conditions[] = 't.transaction_date <= :end_date';
            $params['end_date'] = $filters['end_date'];
        }

        if (!empty($filters['status'])) {
            $conditions[] = 't.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(t.description LIKE :search OR t.notes LIKE :search OR t.reference_number LIKE :search)';
            $params['search'] = '%' . trim((string) $filters['search']) . '%';
        }

        $whereClause = 'WHERE ' . implode(' AND ', $conditions);

        $sql = "
            SELECT COUNT(*) 
            FROM transactions t
            INNER JOIN accounts a ON a.id = t.account_id
            {$whereClause}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getTransactionById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT 
                t.*,
                a.name AS account_name,
                ta.name AS target_account_name,
                c.name AS category_name
            FROM transactions t
            INNER JOIN accounts a ON a.id = t.account_id
            LEFT JOIN accounts ta ON ta.id = t.target_account_id
            LEFT JOIN categories c ON c.id = t.category_id
            WHERE t.id = :id AND t.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $tx = $stmt->fetch();

        return $tx ?: null;
    }

    /**
     * Buat transaksi finansial dan buat ledger movement yang konsisten.
     * Menggunakan Database Transaction.
     */
    public function createTransaction(array $data): int
    {
        $type = (string) ($data['type'] ?? '');
        $description = trim((string) ($data['description'] ?? ''));
        $amount = (float) ($data['amount'] ?? 0.00);
        $date = trim((string) ($data['transaction_date'] ?? date('Y-m-d')));
        $accountId = (int) ($data['account_id'] ?? 0);
        $targetAccountId = !empty($data['target_account_id']) ? (int) $data['target_account_id'] : null;
        $categoryId = !empty($data['category_id']) ? (int) $data['category_id'] : null;
        $status = (string) ($data['status'] ?? 'posted');
        $notes = trim((string) ($data['notes'] ?? ''));
        $referenceNumber = trim((string) ($data['reference_number'] ?? ''));
        $isLoanPrincipal = (int) (!empty($data['is_loan_principal']));

        $this->validateTransactionInput($type, $description, $amount, $date, $accountId, $targetAccountId, $categoryId, $status);

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("
                INSERT INTO transactions (
                    type, status, description, amount, transaction_date,
                    account_id, target_account_id, category_id,
                    is_loan_principal, reference_number, notes
                ) VALUES (
                    :type, :status, :description, :amount, :transaction_date,
                    :account_id, :target_account_id, :category_id,
                    :is_loan_principal, :reference_number, :notes
                )
            ");
            $stmt->execute([
                'type' => $type,
                'status' => $status,
                'description' => $description,
                'amount' => $amount,
                'transaction_date' => $date,
                'account_id' => $accountId,
                'target_account_id' => $targetAccountId,
                'category_id' => $categoryId,
                'is_loan_principal' => $isLoanPrincipal,
                'reference_number' => $referenceNumber !== '' ? $referenceNumber : null,
                'notes' => $notes !== '' ? $notes : null,
            ]);

            $txId = (int) $this->db->lastInsertId();

            $this->createLedgerLines($txId, $type, $amount, $date, $accountId, $targetAccountId);

            if ($type === 'transfer' && $targetAccountId !== null) {
                $stmtTransfer = $this->db->prepare("
                    INSERT INTO transfers (transaction_id, from_account_id, to_account_id, amount)
                    VALUES (:tx_id, :from_id, :to_id, :amount)
                ");
                $stmtTransfer->execute([
                    'tx_id' => $txId,
                    'from_id' => $accountId,
                    'to_id' => $targetAccountId,
                    'amount' => $amount,
                ]);
            }

            $this->db->commit();
            return $txId;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Update transaksi finansial.
     * Membersihkan ledger movements lama dan membuat ledger movements baru.
     */
    public function updateTransaction(int $id, array $data): bool
    {
        $existing = $this->getTransactionById($id);
        if (!$existing) {
            throw new InvalidArgumentException('Transaksi tidak ditemukan.');
        }

        $type = (string) ($data['type'] ?? $existing['type']);
        $description = trim((string) ($data['description'] ?? $existing['description']));
        $amount = isset($data['amount']) ? (float) $data['amount'] : (float) $existing['amount'];
        $date = trim((string) ($data['transaction_date'] ?? $existing['transaction_date']));
        $accountId = isset($data['account_id']) ? (int) $data['account_id'] : (int) $existing['account_id'];
        $targetAccountId = array_key_exists('target_account_id', $data)
            ? (!empty($data['target_account_id']) ? (int) $data['target_account_id'] : null)
            : ($existing['target_account_id'] !== null ? (int) $existing['target_account_id'] : null);
        $categoryId = array_key_exists('category_id', $data)
            ? (!empty($data['category_id']) ? (int) $data['category_id'] : null)
            : ($existing['category_id'] !== null ? (int) $existing['category_id'] : null);
        $status = (string) ($data['status'] ?? $existing['status']);
        $notes = trim((string) ($data['notes'] ?? ($existing['notes'] ?? '')));
        $referenceNumber = trim((string) ($data['reference_number'] ?? ($existing['reference_number'] ?? '')));
        $isLoanPrincipal = isset($data['is_loan_principal']) ? (int) (!empty($data['is_loan_principal'])) : (int) $existing['is_loan_principal'];

        $this->validateTransactionInput($type, $description, $amount, $date, $accountId, $targetAccountId, $categoryId, $status);

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("
                UPDATE transactions
                SET type = :type,
                    status = :status,
                    description = :description,
                    amount = :amount,
                    transaction_date = :transaction_date,
                    account_id = :account_id,
                    target_account_id = :target_account_id,
                    category_id = :category_id,
                    is_loan_principal = :is_loan_principal,
                    reference_number = :reference_number,
                    notes = :notes
                WHERE id = :id
            ");
            $stmt->execute([
                'type' => $type,
                'status' => $status,
                'description' => $description,
                'amount' => $amount,
                'transaction_date' => $date,
                'account_id' => $accountId,
                'target_account_id' => $targetAccountId,
                'category_id' => $categoryId,
                'is_loan_principal' => $isLoanPrincipal,
                'reference_number' => $referenceNumber !== '' ? $referenceNumber : null,
                'notes' => $notes !== '' ? $notes : null,
                'id' => $id,
            ]);

            // Hapus ledger lines & transfer metadata lama
            $this->db->prepare("DELETE FROM transaction_lines WHERE transaction_id = :id")->execute(['id' => $id]);
            $this->db->prepare("DELETE FROM transfers WHERE transaction_id = :id")->execute(['id' => $id]);

            // Buat kembali ledger lines yang konsisten
            $this->createLedgerLines($id, $type, $amount, $date, $accountId, $targetAccountId);

            if ($type === 'transfer' && $targetAccountId !== null) {
                $stmtTransfer = $this->db->prepare("
                    INSERT INTO transfers (transaction_id, from_account_id, to_account_id, amount)
                    VALUES (:tx_id, :from_id, :to_id, :amount)
                ");
                $stmtTransfer->execute([
                    'tx_id' => $id,
                    'from_id' => $accountId,
                    'to_id' => $targetAccountId,
                    'amount' => $amount,
                ]);
            }

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Soft delete transaksi (tidak menghapus fisik agar jejak audit terjaga).
     * Transaksi soft-deleted secara otomatis dikeluarkan dari perhitungan saldo dan laporan.
     */
    public function softDeleteTransaction(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE transactions 
            SET deleted_at = CURRENT_TIMESTAMP 
            WHERE id = :id AND deleted_at IS NULL
        ");
        return $stmt->execute(['id' => $id]);
    }

    private function validateTransactionInput(
        string $type,
        string $description,
        float $amount,
        string $date,
        int $accountId,
        ?int $targetAccountId,
        ?int $categoryId,
        string $status
    ): void {
        if (!in_array($type, ['income', 'expense', 'transfer'], true)) {
            throw new InvalidArgumentException('Jenis transaksi tidak valid.');
        }

        if ($description === '') {
            throw new InvalidArgumentException('Deskripsi transaksi wajib diisi.');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('Nominal transaksi harus lebih besar dari nol.');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new InvalidArgumentException('Format tanggal tidak valid (YYYY-MM-DD).');
        }

        if ($accountId <= 0) {
            throw new InvalidArgumentException('Akun transaksi wajib dipilih.');
        }

        $sourceAccount = $this->getAccountById($accountId);
        if (!$sourceAccount || (int) $sourceAccount['is_active'] !== 1) {
            throw new InvalidArgumentException('Akun yang dipilih tidak valid atau tidak aktif.');
        }

        if ($type === 'transfer') {
            if ($targetAccountId === null || $targetAccountId <= 0) {
                throw new InvalidArgumentException('Akun tujuan transfer wajib dipilih.');
            }
            if ($targetAccountId === $accountId) {
                throw new InvalidArgumentException('Akun sumber dan akun tujuan transfer tidak boleh sama.');
            }
            $targetAccount = $this->getAccountById($targetAccountId);
            if (!$targetAccount || (int) $targetAccount['is_active'] !== 1) {
                throw new InvalidArgumentException('Akun tujuan tidak valid atau tidak aktif.');
            }
        } else {
            if ($categoryId !== null && $categoryId > 0) {
                $stmtCat = $this->db->prepare("SELECT type FROM categories WHERE id = :id");
                $stmtCat->execute(['id' => $categoryId]);
                $catType = $stmtCat->fetchColumn();
                if ($catType && $catType !== $type) {
                    throw new InvalidArgumentException('Kategori yang dipilih tidak sesuai dengan jenis transaksi.');
                }
            }
        }

        if (!in_array($status, ['posted', 'draft', 'void'], true)) {
            throw new InvalidArgumentException('Status transaksi tidak valid.');
        }
    }

    private function createLedgerLines(
        int $txId,
        string $type,
        float $amount,
        string $date,
        int $accountId,
        ?int $targetAccountId
    ): void {
        $stmtLine = $this->db->prepare("
            INSERT INTO transaction_lines (transaction_id, account_id, direction, amount, line_date)
            VALUES (:tx_id, :account_id, :direction, :amount, :line_date)
        ");

        if ($type === 'income') {
            $stmtLine->execute([
                'tx_id' => $txId,
                'account_id' => $accountId,
                'direction' => 'in',
                'amount' => $amount,
                'line_date' => $date,
            ]);
        } elseif ($type === 'expense') {
            $stmtLine->execute([
                'tx_id' => $txId,
                'account_id' => $accountId,
                'direction' => 'out',
                'amount' => $amount,
                'line_date' => $date,
            ]);
        } elseif ($type === 'transfer') {
            // Transfer menghasilkan 2 ledger movements: keluar dari sumber, masuk ke tujuan
            $stmtLine->execute([
                'tx_id' => $txId,
                'account_id' => $accountId,
                'direction' => 'out',
                'amount' => $amount,
                'line_date' => $date,
            ]);
            $stmtLine->execute([
                'tx_id' => $txId,
                'account_id' => $targetAccountId,
                'direction' => 'in',
                'amount' => $amount,
                'line_date' => $date,
            ]);
        }
    }

    // =========================================================================
    // ANGGARAN (BUDGETS)
    // =========================================================================

    public function getBudgets(): array
    {
        $sql = "
            SELECT b.*
            FROM budgets b
            WHERE b.deleted_at IS NULL
            ORDER BY b.start_date DESC, b.id DESC
        ";
        $budgets = $this->db->query($sql)->fetchAll();

        foreach ($budgets as &$budget) {
            $this->enrichBudgetDetails($budget);
        }
        unset($budget);

        return $budgets;
    }

    public function getBudgetById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT b.* 
            FROM budgets b 
            WHERE b.id = :id AND b.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $budget = $stmt->fetch();

        if (!$budget) {
            return null;
        }

        $this->enrichBudgetDetails($budget);
        return $budget;
    }

    private function enrichBudgetDetails(array &$budget): void
    {
        $budgetId = (int) $budget['id'];
        $startDate = $budget['start_date'];
        $endDate = $budget['end_date'];

        // Ambil item kategori anggaran
        $stmtItems = $this->db->prepare("
            SELECT 
                bi.*,
                c.name AS category_name,
                c.icon AS category_icon,
                c.color AS category_color
            FROM budget_items bi
            INNER JOIN categories c ON c.id = bi.category_id
            WHERE bi.budget_id = :budget_id
            ORDER BY c.name ASC
        ");
        $stmtItems->execute(['budget_id' => $budgetId]);
        $items = $stmtItems->fetchAll();

        $totalSpent = 0.00;

        foreach ($items as &$item) {
            $catId = (int) $item['category_id'];
            // Spending kategori: expense posted valid, bukan pokok pinjaman, bukan soft-deleted
            $stmtSpent = $this->db->prepare("
                SELECT COALESCE(SUM(amount), 0.00)
                FROM transactions
                WHERE type = 'expense'
                  AND is_loan_principal = 0
                  AND status = 'posted'
                  AND deleted_at IS NULL
                  AND category_id = :category_id
                  AND transaction_date BETWEEN :start_date AND :end_date
            ");
            $stmtSpent->execute([
                'category_id' => $catId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);
            $spent = (float) $stmtSpent->fetchColumn();
            $item['actual_spent'] = $spent;
            $item['remaining'] = (float) $item['allocated_amount'] - $spent;
            $item['percentage'] = (float) $item['allocated_amount'] > 0
                ? round(($spent / (float) $item['allocated_amount']) * 100, 1)
                : 0.0;
            $totalSpent += $spent;
        }
        unset($item);

        $totalLimit = (float) $budget['total_limit'];
        $budget['items'] = $items;
        $budget['total_spent'] = $totalSpent;
        $budget['remaining'] = $totalLimit - $totalSpent;
        $budget['percentage'] = $totalLimit > 0
            ? round(($totalSpent / $totalLimit) * 100, 1)
            : 0.0;

        if ($budget['percentage'] >= 100.0) {
            $budget['status_indicator'] = 'exceeded'; // Melebihi limit
            $budget['status_label'] = 'Melebihi Limit';
        } elseif ($budget['percentage'] >= 80.0) {
            $budget['status_indicator'] = 'warning';  // Mendekati limit
            $budget['status_label'] = 'Mendekati Limit';
        } else {
            $budget['status_indicator'] = 'normal';   // Aman
            $budget['status_label'] = 'Terkendali';
        }
    }

    public function saveBudget(array $data, ?int $id = null): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        $period = (string) ($data['period'] ?? 'monthly');
        $startDate = trim((string) ($data['start_date'] ?? ''));
        $endDate = trim((string) ($data['end_date'] ?? ''));
        $notes = trim((string) ($data['notes'] ?? ''));
        $categoryAllocations = $data['categories'] ?? []; // [category_id => amount]

        if ($name === '') {
            throw new InvalidArgumentException('Nama anggaran wajib diisi.');
        }

        $validPeriods = ['weekly', 'monthly', 'quarterly', 'yearly', 'custom'];
        if (!in_array($period, $validPeriods, true)) {
            $period = 'monthly';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            throw new InvalidArgumentException('Format tanggal mulai atau selesai tidak valid.');
        }

        if ($startDate > $endDate) {
            throw new InvalidArgumentException('Tanggal mulai tidak boleh lebih besar dari tanggal selesai.');
        }

        // Hitung total limit: jika user mengisi total_limit > 0, gunakan nilai tersebut.
        // Jika tidak, hitung dari akumulasi alokasi kategori.
        $userLimit = isset($data['total_limit']) ? (float) $data['total_limit'] : 0.00;
        $categoriesSum = 0.00;
        $validAllocations = [];
        foreach ($categoryAllocations as $catId => $amount) {
            $numAmount = (float) $amount;
            if ($numAmount > 0) {
                $validAllocations[(int) $catId] = $numAmount;
                $categoriesSum += $numAmount;
            }
        }

        $totalLimit = $userLimit > 0 ? $userLimit : $categoriesSum;

        if ($totalLimit <= 0) {
            throw new InvalidArgumentException('Total batas anggaran harus lebih besar dari 0.');
        }

        $this->db->beginTransaction();

        try {
            if ($id !== null && $id > 0) {
                $stmt = $this->db->prepare("
                    UPDATE budgets
                    SET name = :name,
                        period = :period,
                        start_date = :start_date,
                        end_date = :end_date,
                        total_limit = :total_limit,
                        notes = :notes
                    WHERE id = :id AND deleted_at IS NULL
                ");
                $stmt->execute([
                    'name' => $name,
                    'period' => $period,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'total_limit' => $totalLimit,
                    'notes' => $notes !== '' ? $notes : null,
                    'id' => $id,
                ]);
                $budgetId = $id;

                $this->db->prepare("DELETE FROM budget_items WHERE budget_id = :id")->execute(['id' => $budgetId]);
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO budgets (name, period, start_date, end_date, total_limit, notes)
                    VALUES (:name, :period, :start_date, :end_date, :total_limit, :notes)
                ");
                $stmt->execute([
                    'name' => $name,
                    'period' => $period,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'total_limit' => $totalLimit,
                    'notes' => $notes !== '' ? $notes : null,
                ]);
                $budgetId = (int) $this->db->lastInsertId();
            }

            if (!empty($validAllocations)) {
                $stmtItem = $this->db->prepare("
                    INSERT INTO budget_items (budget_id, category_id, allocated_amount)
                    VALUES (:budget_id, :category_id, :amount)
                ");
                foreach ($validAllocations as $catId => $allocAmount) {
                    $stmtItem->execute([
                        'budget_id' => $budgetId,
                        'category_id' => $catId,
                        'amount' => $allocAmount,
                    ]);
                }
            }

            $this->db->commit();
            return $budgetId;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function softDeleteBudget(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE budgets
            SET deleted_at = CURRENT_TIMESTAMP
            WHERE id = :id AND deleted_at IS NULL
        ");
        return $stmt->execute(['id' => $id]);
    }

    // =========================================================================
    // PINJAMAN PRIBADI (LOANS & PAYMENTS)
    // =========================================================================

    public function getLoans(?string $status = null): array
    {
        $where = ['l.deleted_at IS NULL'];
        $params = [];

        if ($status !== null && in_array($status, ['unpaid', 'partially_paid', 'paid'], true)) {
            $where[] = 'l.status = :status';
            $params['status'] = $status;
        }

        $sql = "
            SELECT 
                l.*,
                COALESCE(p.total_principal_paid, 0.00) AS total_principal_paid,
                COALESCE(p.total_interest_paid, 0.00) AS total_interest_paid,
                COALESCE(p.total_paid, 0.00) AS total_paid,
                (l.principal_amount - COALESCE(p.total_principal_paid, 0.00)) AS outstanding_principal,
                (l.interest_amount - COALESCE(p.total_interest_paid, 0.00)) AS outstanding_interest,
                ((l.principal_amount + l.interest_amount) - COALESCE(p.total_paid, 0.00)) AS outstanding_total
            FROM loans l
            LEFT JOIN (
                SELECT 
                    lp.loan_id,
                    SUM(lp.principal_paid) AS total_principal_paid,
                    SUM(lp.interest_paid) AS total_interest_paid,
                    SUM(lp.principal_paid + lp.interest_paid) AS total_paid
                FROM loan_payments lp
                GROUP BY lp.loan_id
            ) p ON p.loan_id = l.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY (l.status = 'paid') ASC, l.borrowed_date DESC, l.id DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getLoanById(int $id): ?array
    {
        $sql = "
            SELECT 
                l.*,
                COALESCE(p.total_principal_paid, 0.00) AS total_principal_paid,
                COALESCE(p.total_interest_paid, 0.00) AS total_interest_paid,
                COALESCE(p.total_paid, 0.00) AS total_paid,
                (l.principal_amount - COALESCE(p.total_principal_paid, 0.00)) AS outstanding_principal,
                (l.interest_amount - COALESCE(p.total_interest_paid, 0.00)) AS outstanding_interest,
                ((l.principal_amount + l.interest_amount) - COALESCE(p.total_paid, 0.00)) AS outstanding_total
            FROM loans l
            LEFT JOIN (
                SELECT 
                    lp.loan_id,
                    SUM(lp.principal_paid) AS total_principal_paid,
                    SUM(lp.interest_paid) AS total_interest_paid,
                    SUM(lp.principal_paid + lp.interest_paid) AS total_paid
                FROM loan_payments lp
                GROUP BY lp.loan_id
            ) p ON p.loan_id = l.id
            WHERE l.id = :id AND l.deleted_at IS NULL
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $loan = $stmt->fetch();

        if (!$loan) {
            return null;
        }

        // Ambil riwayat pembayaran
        $stmtPayments = $this->db->prepare("
            SELECT 
                lp.*,
                a.name AS account_name
            FROM loan_payments lp
            INNER JOIN accounts a ON a.id = lp.account_id
            WHERE lp.loan_id = :loan_id
            ORDER BY lp.payment_date DESC, lp.id DESC
        ");
        $stmtPayments->execute(['loan_id' => $id]);
        $loan['payments'] = $stmtPayments->fetchAll();

        return $loan;
    }

    public function saveLoan(array $data, ?int $id = null): int
    {
        $lenderName = trim((string) ($data['lender_name'] ?? ''));
        $loanType = (string) ($data['loan_type'] ?? 'borrowed');
        $principal = (float) ($data['principal_amount'] ?? 0.00);
        $interest = (float) ($data['interest_amount'] ?? 0.00);
        $borrowedDate = trim((string) ($data['borrowed_date'] ?? date('Y-m-d')));
        $dueDate = !empty($data['due_date']) ? trim((string) $data['due_date']) : null;
        $notes = trim((string) ($data['notes'] ?? ''));

        if ($lenderName === '') {
            throw new InvalidArgumentException('Nama pihak peminjam / pemberi pinjaman wajib diisi.');
        }

        if ($principal <= 0) {
            throw new InvalidArgumentException('Nominal pokok pinjaman harus lebih besar dari nol.');
        }

        if ($interest < 0) {
            throw new InvalidArgumentException('Nominal bunga tidak boleh negatif.');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $borrowedDate)) {
            throw new InvalidArgumentException('Format tanggal pinjam tidak valid.');
        }

        if ($dueDate !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            throw new InvalidArgumentException('Format tanggal jatuh tempo tidak valid.');
        }

        if ($id !== null && $id > 0) {
            $stmt = $this->db->prepare("
                UPDATE loans
                SET lender_name = :lender_name,
                    loan_type = :loan_type,
                    principal_amount = :principal,
                    interest_amount = :interest,
                    borrowed_date = :borrowed_date,
                    due_date = :due_date,
                    notes = :notes
                WHERE id = :id AND deleted_at IS NULL
            ");
            $stmt->execute([
                'lender_name' => $lenderName,
                'loan_type' => $loanType,
                'principal' => $principal,
                'interest' => $interest,
                'borrowed_date' => $borrowedDate,
                'due_date' => $dueDate,
                'notes' => $notes !== '' ? $notes : null,
                'id' => $id,
            ]);
            $this->updateLoanStatus($id);
            return $id;
        }

        $stmt = $this->db->prepare("
            INSERT INTO loans (
                lender_name, loan_type, principal_amount, interest_amount,
                borrowed_date, due_date, status, notes
            ) VALUES (
                :lender_name, :loan_type, :principal, :interest,
                :borrowed_date, :due_date, 'unpaid', :notes
            )
        ");
        $stmt->execute([
            'lender_name' => $lenderName,
            'loan_type' => $loanType,
            'principal' => $principal,
            'interest' => $interest,
            'borrowed_date' => $borrowedDate,
            'due_date' => $dueDate,
            'notes' => $notes !== '' ? $notes : null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Catat pembayaran pinjaman.
     * Aturan bisnis:
     * - Pokok pinjaman mengurangi kewajiban, bukan expense (is_loan_principal = 1).
     * - Bunga pinjaman adalah expense (kategori Beban Bunga Pinjaman).
     * - Setiap pembayaran menghasilkan arus kas keluar pada akun yang konsisten.
     * - Pembayaran bersifat atomic (DB Transaction).
     */
    public function recordLoanPayment(array $data): int
    {
        $loanId = (int) ($data['loan_id'] ?? 0);
        $accountId = (int) ($data['account_id'] ?? 0);
        $paymentDate = trim((string) ($data['payment_date'] ?? date('Y-m-d')));
        $principalPaid = (float) ($data['principal_paid'] ?? 0.00);
        $interestPaid = (float) ($data['interest_paid'] ?? 0.00);
        $notes = trim((string) ($data['notes'] ?? ''));

        $loan = $this->getLoanById($loanId);
        if (!$loan) {
            throw new InvalidArgumentException('Data pinjaman tidak ditemukan.');
        }

        $totalPaid = $principalPaid + $interestPaid;
        if ($totalPaid <= 0) {
            throw new InvalidArgumentException('Total pembayaran harus lebih besar dari 0.');
        }

        if ($principalPaid < 0 || $interestPaid < 0) {
            throw new InvalidArgumentException('Nominal pembayaran tidak boleh negatif.');
        }

        $outstandingPrincipal = (float) $loan['outstanding_principal'];
        $outstandingInterest = (float) $loan['outstanding_interest'];

        if ($principalPaid > $outstandingPrincipal + 0.001) {
            throw new InvalidArgumentException(
                'Pembayaran pokok melebihi sisa pokok pinjaman (' . format_rupiah($outstandingPrincipal) . ').'
            );
        }

        if ($interestPaid > $outstandingInterest + 0.001) {
            throw new InvalidArgumentException(
                'Pembayaran bunga melebihi sisa bunga pinjaman (' . format_rupiah($outstandingInterest) . ').'
            );
        }

        $account = $this->getAccountById($accountId);
        if (!$account || (int) $account['is_active'] !== 1) {
            throw new InvalidArgumentException('Akun pembayaran tidak valid atau tidak aktif.');
        }

        $this->db->beginTransaction();

        try {
            $principalTxId = null;
            $interestTxId = null;

            // 1. Jika ada pembayaran pokok: buat transaksi kas keluar (is_loan_principal = 1)
            if ($principalPaid > 0) {
                $desc = 'Pembayaran Pokok Pinjaman: ' . $loan['lender_name'];
                $stmtTx = $this->db->prepare("
                    INSERT INTO transactions (
                        type, status, description, amount, transaction_date,
                        account_id, is_loan_principal, notes
                    ) VALUES (
                        'expense', 'posted', :desc, :amount, :date,
                        :account_id, 1, :notes
                    )
                ");
                $stmtTx->execute([
                    'desc' => $desc,
                    'amount' => $principalPaid,
                    'date' => $paymentDate,
                    'account_id' => $accountId,
                    'notes' => $notes !== '' ? $notes : null,
                ]);
                $principalTxId = (int) $this->db->lastInsertId();

                $this->createLedgerLines($principalTxId, 'expense', $principalPaid, $paymentDate, $accountId, null);
            }

            // 2. Jika ada pembayaran bunga: buat transaksi kas keluar beban bunga
            if ($interestPaid > 0) {
                $bungaCatId = $this->getOrCreateInterestCategory();
                $desc = 'Beban Bunga Pinjaman: ' . $loan['lender_name'];
                $stmtTx = $this->db->prepare("
                    INSERT INTO transactions (
                        type, status, description, amount, transaction_date,
                        account_id, category_id, is_loan_principal, notes
                    ) VALUES (
                        'expense', 'posted', :desc, :amount, :date,
                        :account_id, :cat_id, 0, :notes
                    )
                ");
                $stmtTx->execute([
                    'desc' => $desc,
                    'amount' => $interestPaid,
                    'date' => $paymentDate,
                    'account_id' => $accountId,
                    'cat_id' => $bungaCatId,
                    'notes' => $notes !== '' ? $notes : null,
                ]);
                $interestTxId = (int) $this->db->lastInsertId();

                $this->createLedgerLines($interestTxId, 'expense', $interestPaid, $paymentDate, $accountId, null);
            }

            // 3. Catat di loan_payments
            $stmtPayment = $this->db->prepare("
                INSERT INTO loan_payments (
                    loan_id, account_id, principal_paid, interest_paid,
                    payment_date, notes, principal_transaction_id, interest_transaction_id
                ) VALUES (
                    :loan_id, :account_id, :principal_paid, :interest_paid,
                    :payment_date, :notes, :principal_tx_id, :interest_tx_id
                )
            ");
            $stmtPayment->execute([
                'loan_id' => $loanId,
                'account_id' => $accountId,
                'principal_paid' => $principalPaid,
                'interest_paid' => $interestPaid,
                'payment_date' => $paymentDate,
                'notes' => $notes !== '' ? $notes : null,
                'principal_tx_id' => $principalTxId,
                'interest_tx_id' => $interestTxId,
            ]);
            $paymentId = (int) $this->db->lastInsertId();

            // 4. Update status pinjaman
            $newOutstandingPrincipal = $outstandingPrincipal - $principalPaid;
            $newOutstandingInterest = $outstandingInterest - $interestPaid;

            $newStatus = ($newOutstandingPrincipal <= 0.001 && $newOutstandingInterest <= 0.001)
                ? 'paid'
                : 'partially_paid';

            $stmtUpdateLoan = $this->db->prepare("UPDATE loans SET status = :status WHERE id = :id");
            $stmtUpdateLoan->execute([
                'status' => $newStatus,
                'id' => $loanId,
            ]);

            $this->db->commit();
            return $paymentId;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function getOrCreateInterestCategory(): int
    {
        $stmt = $this->db->prepare("SELECT id FROM categories WHERE name = 'Beban Bunga Pinjaman' AND type = 'expense' LIMIT 1");
        $stmt->execute();
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int) $id;
        }

        $stmt = $this->db->prepare("
            INSERT INTO categories (name, type, icon, color, is_system)
            VALUES ('Beban Bunga Pinjaman', 'expense', '％', '#a35050', 1)
        ");
        $stmt->execute();
        return (int) $this->db->lastInsertId();
    }

    private function updateLoanStatus(int $loanId): void
    {
        $loan = $this->getLoanById($loanId);
        if (!$loan) {
            return;
        }

        $principalAmount = (float) $loan['principal_amount'];
        $interestAmount = (float) $loan['interest_amount'];
        $principalPaid = (float) $loan['total_principal_paid'];
        $interestPaid = (float) $loan['total_interest_paid'];

        $remainingPrincipal = $principalAmount - $principalPaid;
        $remainingInterest = $interestAmount - $interestPaid;

        if ($remainingPrincipal <= 0.001 && $remainingInterest <= 0.001) {
            $status = 'paid';
        } elseif ($principalPaid > 0 || $interestPaid > 0) {
            $status = 'partially_paid';
        } else {
            $status = 'unpaid';
        }

        $stmt = $this->db->prepare("UPDATE loans SET status = :status WHERE id = :id");
        $stmt->execute(['status' => $status, 'id' => $loanId]);
    }

    public function softDeleteLoan(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE loans
            SET deleted_at = CURRENT_TIMESTAMP
            WHERE id = :id AND deleted_at IS NULL
        ");
        return $stmt->execute(['id' => $id]);
    }

    // =========================================================================
    // TAGIHAN & PENGINGAT (FIXED BILL REMINDERS)
    // =========================================================================

    public function getBills(bool $onlyActive = false): array
    {
        $where = ['b.deleted_at IS NULL'];
        if ($onlyActive) {
            $where[] = 'b.is_active = 1';
        }

        $sql = "
            SELECT 
                b.*,
                c.name AS category_name,
                c.icon AS category_icon,
                c.color AS category_color,
                a.name AS account_name
            FROM fixed_bill_templates b
            LEFT JOIN categories c ON c.id = b.category_id
            LEFT JOIN accounts a ON a.id = b.account_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY b.is_active DESC, b.next_due_date ASC, b.id ASC
        ";

        $bills = $this->db->query($sql)->fetchAll();
        $today = date('Y-m-d');

        foreach ($bills as &$bill) {
            $dueDate = $bill['next_due_date'];
            $daysDiff = (int) (strtotime($dueDate) - strtotime($today)) / 86400;

            if ($daysDiff < 0) {
                $bill['urgency'] = 'overdue';
                $bill['urgency_label'] = 'Terlambat ' . abs($daysDiff) . ' hari';
            } elseif ($daysDiff === 0) {
                $bill['urgency'] = 'due_today';
                $bill['urgency_label'] = 'Jatuh tempo hari ini';
            } elseif ($daysDiff <= 3) {
                $bill['urgency'] = 'due_soon';
                $bill['urgency_label'] = $daysDiff . ' hari lagi';
            } else {
                $bill['urgency'] = 'upcoming';
                $bill['urgency_label'] = date('d M Y', strtotime($dueDate));
            }
        }
        unset($bill);

        return $bills;
    }

    public function getBillById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT 
                b.*,
                c.name AS category_name,
                a.name AS account_name
            FROM fixed_bill_templates b
            LEFT JOIN categories c ON c.id = b.category_id
            LEFT JOIN accounts a ON a.id = b.account_id
            WHERE b.id = :id AND b.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $bill = $stmt->fetch();
        return $bill ?: null;
    }

    public function saveBill(array $data, ?int $id = null): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        $categoryId = !empty($data['category_id']) ? (int) $data['category_id'] : null;
        $accountId = !empty($data['account_id']) ? (int) $data['account_id'] : null;
        $expectedAmount = (float) ($data['expected_amount'] ?? 0.00);
        $frequency = (string) ($data['frequency'] ?? 'monthly');
        $dueDay = !empty($data['due_day']) ? (int) $data['due_day'] : null;
        $nextDueDate = trim((string) ($data['next_due_date'] ?? ''));
        $notes = trim((string) ($data['notes'] ?? ''));
        $isActive = isset($data['is_active']) ? (int) (bool) $data['is_active'] : 1;

        if ($name === '') {
            throw new InvalidArgumentException('Nama tagihan wajib diisi.');
        }

        if ($expectedAmount <= 0) {
            throw new InvalidArgumentException('Estimasi nominal tagihan harus lebih besar dari nol.');
        }

        $validFrequencies = ['weekly', 'monthly', 'quarterly', 'yearly', 'once'];
        if (!in_array($frequency, $validFrequencies, true)) {
            $frequency = 'monthly';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $nextDueDate)) {
            throw new InvalidArgumentException('Tanggal jatuh tempo berikutnya tidak valid (YYYY-MM-DD).');
        }

        if ($id !== null && $id > 0) {
            $stmt = $this->db->prepare("
                UPDATE fixed_bill_templates
                SET name = :name,
                    category_id = :category_id,
                    account_id = :account_id,
                    expected_amount = :expected_amount,
                    frequency = :frequency,
                    due_day = :due_day,
                    next_due_date = :next_due_date,
                    notes = :notes,
                    is_active = :is_active
                WHERE id = :id AND deleted_at IS NULL
            ");
            $stmt->execute([
                'name' => $name,
                'category_id' => $categoryId,
                'account_id' => $accountId,
                'expected_amount' => $expectedAmount,
                'frequency' => $frequency,
                'due_day' => $dueDay,
                'next_due_date' => $nextDueDate,
                'notes' => $notes !== '' ? $notes : null,
                'is_active' => $isActive,
                'id' => $id,
            ]);
            return $id;
        }

        $stmt = $this->db->prepare("
            INSERT INTO fixed_bill_templates (
                name, category_id, account_id, expected_amount,
                frequency, due_day, next_due_date, notes, is_active
            ) VALUES (
                :name, :category_id, :account_id, :expected_amount,
                :frequency, :due_day, :next_due_date, :notes, :is_active
            )
        ");
        $stmt->execute([
            'name' => $name,
            'category_id' => $categoryId,
            'account_id' => $accountId,
            'expected_amount' => $expectedAmount,
            'frequency' => $frequency,
            'due_day' => $dueDay,
            'next_due_date' => $nextDueDate,
            'notes' => $notes !== '' ? $notes : null,
            'is_active' => $isActive,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function toggleBillActive(int $id, bool $isActive): bool
    {
        $stmt = $this->db->prepare("UPDATE fixed_bill_templates SET is_active = :active WHERE id = :id");
        return $stmt->execute([
            'active' => $isActive ? 1 : 0,
            'id' => $id,
        ]);
    }

    public function softDeleteBill(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE fixed_bill_templates
            SET deleted_at = CURRENT_TIMESTAMP
            WHERE id = :id AND deleted_at IS NULL
        ");
        return $stmt->execute(['id' => $id]);
    }

    // =========================================================================
    // OVERVIEW & ANALYTICS
    // =========================================================================

    public function getOverviewData(): array
    {
        // 1. Total saldo seluruh akun aktif
        $accounts = $this->getAccounts(true);
        $totalBalance = 0.00;
        foreach ($accounts as $acc) {
            $totalBalance += (float) $acc['current_balance'];
        }

        // 2. Bulan berjalan
        $startOfMonth = date('Y-m-01');
        $endOfMonth = date('Y-m-t');

        // Total Income bulan berjalan (posted, non-deleted)
        $stmtInc = $this->db->prepare("
            SELECT COALESCE(SUM(amount), 0.00)
            FROM transactions
            WHERE type = 'income'
              AND status = 'posted'
              AND deleted_at IS NULL
              AND transaction_date BETWEEN :start_date AND :end_date
        ");
        $stmtInc->execute(['start_date' => $startOfMonth, 'end_date' => $endOfMonth]);
        $totalIncome = (float) $stmtInc->fetchColumn();

        // Total Expense bulan berjalan (posted, non-deleted, bukan transfer, bukan pokok pinjaman)
        $stmtExp = $this->db->prepare("
            SELECT COALESCE(SUM(amount), 0.00)
            FROM transactions
            WHERE type = 'expense'
              AND is_loan_principal = 0
              AND status = 'posted'
              AND deleted_at IS NULL
              AND transaction_date BETWEEN :start_date AND :end_date
        ");
        $stmtExp->execute(['start_date' => $startOfMonth, 'end_date' => $endOfMonth]);
        $totalExpenses = (float) $stmtExp->fetchColumn();

        // Net cash flow
        $netCashFlow = $totalIncome - $totalExpenses;

        // 3. Cash flow chart 6 bulan terakhir
        $chartData = $this->getMonthlyCashFlowChart(6);

        // 4. Transaksi terbaru (maks 6)
        $recentTransactions = $this->getTransactions([], 6, 0);

        // 5. Akun aktif ringkasan (maks 4)
        $topAccounts = array_slice($accounts, 0, 4);

        // 6. Anggaran aktif ringkasan
        $budgets = $this->getBudgets();
        $budgetsSummary = array_slice($budgets, 0, 3);

        // 7. Tagihan mendatang (hanya aktif, maks 4)
        $bills = $this->getBills(true);
        $upcomingBills = array_slice($bills, 0, 4);

        // 8. Ringkasan pinjaman pribadi
        $loans = $this->getLoans('unpaid');
        $partiallyPaidLoans = $this->getLoans('partially_paid');
        $activeLoans = array_merge($loans, $partiallyPaidLoans);

        $totalLoanPrincipal = 0.00;
        $totalLoanOutstanding = 0.00;
        foreach ($activeLoans as $l) {
            $totalLoanPrincipal += (float) $l['principal_amount'];
            $totalLoanOutstanding += (float) $l['outstanding_total'];
        }

        return [
            'total_balance' => $totalBalance,
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'net_cash_flow' => $netCashFlow,
            'chart_data' => $chartData,
            'recent_transactions' => $recentTransactions,
            'accounts_summary' => $topAccounts,
            'budgets_summary' => $budgetsSummary,
            'upcoming_bills' => $upcomingBills,
            'loans_summary' => [
                'active_count' => count($activeLoans),
                'total_principal' => $totalLoanPrincipal,
                'total_outstanding' => $totalLoanOutstanding,
                'list' => array_slice($activeLoans, 0, 3),
            ],
            'has_any_data' => (count($accounts) > 0 || count($recentTransactions) > 0 || count($budgets) > 0 || count($bills) > 0 || count($activeLoans) > 0),
        ];
    }

    private function getMonthlyCashFlowChart(int $months = 6): array
    {
        $result = [];
        $indonesianMonths = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agt',
            9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ];

        for ($i = $months - 1; $i >= 0; $i--) {
            $timestamp = strtotime("-{$i} month");
            $startDate = date('Y-m-01', $timestamp);
            $endDate = date('Y-m-t', $timestamp);
            $monthNum = (int) date('n', $timestamp);
            $year = date('Y', $timestamp);
            $label = ($indonesianMonths[$monthNum] ?? date('M', $timestamp)) . ' ' . $year;

            // Income
            $stmtInc = $this->db->prepare("
                SELECT COALESCE(SUM(amount), 0.00)
                FROM transactions
                WHERE type = 'income'
                  AND status = 'posted'
                  AND deleted_at IS NULL
                  AND transaction_date BETWEEN :start_date AND :end_date
            ");
            $stmtInc->execute(['start_date' => $startDate, 'end_date' => $endDate]);
            $inc = (float) $stmtInc->fetchColumn();

            // Expense (excluding transfer and loan principal)
            $stmtExp = $this->db->prepare("
                SELECT COALESCE(SUM(amount), 0.00)
                FROM transactions
                WHERE type = 'expense'
                  AND is_loan_principal = 0
                  AND status = 'posted'
                  AND deleted_at IS NULL
                  AND transaction_date BETWEEN :start_date AND :end_date
            ");
            $stmtExp->execute(['start_date' => $startDate, 'end_date' => $endDate]);
            $exp = (float) $stmtExp->fetchColumn();

            $result[] = [
                'label' => $label,
                'income' => $inc,
                'expense' => $exp,
                'net' => $inc - $exp,
            ];
        }

        return $result;
    }
}
