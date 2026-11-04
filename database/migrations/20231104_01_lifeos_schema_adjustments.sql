-- Migration: 20231104_01_lifeos_schema_adjustments
-- Versioned migration to align live DB with application schema.
-- This script is idempotent: it creates missing tables, adds missing columns,
-- and copies data from legacy tables where appropriate.

-- =============================================================
-- Finance: create new tables expected by FinanceService
-- =============================================================
CREATE TABLE IF NOT EXISTS accounts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    type ENUM('cash','bank','ewallet','savings','other') NOT NULL DEFAULT 'bank',
    account_number VARCHAR(50) NULL,
    initial_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    color VARCHAR(20) NULL DEFAULT '#344f41',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_accounts_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    type ENUM('income','expense') NOT NULL,
    icon VARCHAR(50) NULL DEFAULT '◈',
    color VARCHAR(20) NULL DEFAULT '#526f5d',
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_name_type (name, type),
    KEY idx_categories_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transactions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    type ENUM('income','expense','transfer') NOT NULL,
    status ENUM('posted','draft','void') NOT NULL DEFAULT 'posted',
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    transaction_date DATE NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    target_account_id INT UNSIGNED NULL,
    category_id INT UNSIGNED NULL,
    is_loan_principal TINYINT(1) NOT NULL DEFAULT 0,
    reference_number VARCHAR(100) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_transactions_type (type),
    KEY idx_transactions_status (status),
    KEY idx_transactions_date (transaction_date),
    KEY idx_transactions_account (account_id),
    KEY idx_transactions_target_account (target_account_id),
    KEY idx_transactions_category (category_id),
    KEY idx_transactions_deleted (deleted_at),
    CONSTRAINT fk_transactions_account FOREIGN KEY (account_id) REFERENCES accounts (id) ON UPDATE CASCADE,
    CONSTRAINT fk_transactions_target_account FOREIGN KEY (target_account_id) REFERENCES accounts (id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_transactions_category FOREIGN KEY (category_id) REFERENCES categories (id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transaction_lines (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    transaction_id INT UNSIGNED NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    direction ENUM('in','out') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    line_date DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_lines_tx (transaction_id),
    KEY idx_lines_account_dir (account_id, direction),
    KEY idx_lines_date (line_date),
    CONSTRAINT fk_lines_transaction FOREIGN KEY (transaction_id) REFERENCES transactions (id) ON DELETE CASCADE,
    CONSTRAINT fk_lines_account FOREIGN KEY (account_id) REFERENCES accounts (id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transfers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    transaction_id INT UNSIGNED NOT NULL,
    from_account_id INT UNSIGNED NOT NULL,
    to_account_id INT UNSIGNED NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_transfers_tx (transaction_id),
    CONSTRAINT fk_transfers_tx FOREIGN KEY (transaction_id) REFERENCES transactions (id) ON DELETE CASCADE,
    CONSTRAINT fk_transfers_from FOREIGN KEY (from_account_id) REFERENCES accounts (id) ON UPDATE CASCADE,
    CONSTRAINT fk_transfers_to FOREIGN KEY (to_account_id) REFERENCES accounts (id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS budgets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    period ENUM('weekly','monthly','quarterly','yearly','custom') NOT NULL DEFAULT 'monthly',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_limit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_budgets_period (period),
    KEY idx_budgets_dates (start_date, end_date),
    KEY idx_budgets_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS budget_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    budget_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    allocated_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_budget_category (budget_id, category_id),
    CONSTRAINT fk_budget_items_budget FOREIGN KEY (budget_id) REFERENCES budgets (id) ON DELETE CASCADE,
    CONSTRAINT fk_budget_items_category FOREIGN KEY (category_id) REFERENCES categories (id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fixed_bill_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    category_id INT UNSIGNED NULL,
    account_id INT UNSIGNED NULL,
    expected_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    frequency ENUM('weekly','monthly','quarterly','yearly','once') NOT NULL DEFAULT 'monthly',
    due_day TINYINT UNSIGNED NULL,
    next_due_date DATE NOT NULL,
    notes TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_bills_active (is_active),
    KEY idx_bills_due (next_due_date),
    CONSTRAINT fk_bills_category FOREIGN KEY (category_id) REFERENCES categories (id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_bills_account FOREIGN KEY (account_id) REFERENCES accounts (id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loans (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lender_name VARCHAR(150) NOT NULL,
    loan_type ENUM('borrowed','lent') NOT NULL DEFAULT 'borrowed',
    principal_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    interest_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    borrowed_date DATE NOT NULL,
    due_date DATE NULL,
    status ENUM('unpaid','partially_paid','paid') NOT NULL DEFAULT 'unpaid',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_loans_status (status),
    KEY idx_loans_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loan_payments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    loan_id INT UNSIGNED NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    principal_paid DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    interest_paid DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    payment_date DATE NOT NULL,
    notes TEXT NULL,
    principal_transaction_id INT UNSIGNED NULL,
    interest_transaction_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_payments_loan (loan_id),
    KEY idx_payments_account (account_id),
    CONSTRAINT fk_payments_loan FOREIGN KEY (loan_id) REFERENCES loans (id) ON DELETE CASCADE,
    CONSTRAINT fk_payments_account FOREIGN KEY (account_id) REFERENCES accounts (id) ON UPDATE CASCADE,
    CONSTRAINT fk_payments_principal_tx FOREIGN KEY (principal_transaction_id) REFERENCES transactions (id) ON DELETE SET NULL,
    CONSTRAINT fk_payments_interest_tx FOREIGN KEY (interest_transaction_id) REFERENCES transactions (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- Schedule: new tables expected by ScheduleService
-- =============================================================
CREATE TABLE IF NOT EXISTS schedule_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(20) NULL DEFAULT '#5a5a5a',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_schedule_category_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schedule_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    start_date DATE NOT NULL,
    start_time TIME NULL,
    end_date DATE NOT NULL,
    end_time TIME NULL,
    all_day TINYINT(1) NOT NULL DEFAULT 0,
    category_id INT UNSIGNED NULL,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_events_start (start_date, start_time),
    KEY idx_events_end (end_date, end_time),
    KEY idx_events_deleted (deleted_at),
    CONSTRAINT fk_schedule_category FOREIGN KEY (category_id) REFERENCES schedule_categories (id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrate any existing data from legacy `schedules` table if present.
INSERT IGNORE INTO schedule_events (title, description, start_date, start_time, end_date, end_time, all_day, created_at, updated_at)
SELECT
    title,
    description,
    DATE(start_at) AS start_date,
    TIME(start_at) AS start_time,
    COALESCE(DATE(end_at), DATE(start_at)) AS end_date,
    TIME(end_at) AS end_time,
    0 AS all_day,
    created_at,
    updated_at
FROM schedules;

-- =============================================================
-- Tasks: new tables and column expected by TasksService
-- =============================================================
CREATE TABLE IF NOT EXISTS task_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(20) NULL DEFAULT '#5a5a5a',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_task_category_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add category_id column to tasks if missing
ALTER TABLE tasks ADD COLUMN IF NOT EXISTS category_id INT UNSIGNED NULL;
-- Add foreign key (if not exists). MySQL 8+ supports IF NOT EXISTS for foreign keys via checking information_schema; we use a safe approach.
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND CONSTRAINT_NAME = 'fk_task_category');
PREPARE stmt FROM 'ALTER TABLE tasks ADD CONSTRAINT fk_task_category FOREIGN KEY (category_id) REFERENCES task_categories (id) ON UPDATE CASCADE ON DELETE SET NULL';
EXECUTE stmt USING @fk_exists;
DEALLOCATE PREPARE stmt;

-- Insert a default task category (Uncategorised) if none exists
INSERT IGNORE INTO task_categories (name, color) VALUES ('Uncategorised', '#5a5a5a');

-- =============================================================
-- Record migration in schema_migrations table
-- =============================================================
INSERT INTO schema_migrations (migration, checksum, status, started_at)
VALUES ('20231104_01_lifeos_schema_adjustments', MD5(NOW()), 'applied', NOW())
ON DUPLICATE KEY UPDATE status='applied', applied_at=NOW();

