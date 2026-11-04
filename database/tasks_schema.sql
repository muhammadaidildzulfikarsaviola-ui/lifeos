-- =============================================================
-- LIFEOS TASKS MODULE SCHEMA
-- =============================================================
USE lifeos;

-- 1. Task Categories (optional)
CREATE TABLE IF NOT EXISTS task_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(20) NULL DEFAULT '#5a5a5a',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_task_category_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tasks Table
CREATE TABLE IF NOT EXISTS tasks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    status ENUM('todo','in_progress','done','cancelled') NOT NULL DEFAULT 'todo',
    priority ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
    due_date DATE NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    category_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_tasks_due (due_date),
    KEY idx_tasks_status (status),
    KEY idx_tasks_deleted (deleted_at),
    CONSTRAINT fk_task_category FOREIGN KEY (category_id) REFERENCES task_categories (id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

