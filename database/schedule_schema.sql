-- =============================================================
-- LIFEOS SCHEDULE MODULE SCHEMA
-- =============================================================
USE lifeos;

-- 1. Event Categories (optional, can be seeded later)
CREATE TABLE IF NOT EXISTS schedule_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(20) NULL DEFAULT '#5a5a5a',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_schedule_category_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Events Table
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
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_events_start (start_date, start_time),
    KEY idx_events_end (end_date, end_time),
    KEY idx_events_deleted (deleted_at),
    CONSTRAINT fk_schedule_category FOREIGN KEY (category_id) REFERENCES schedule_categories (id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Event Participants (optional future feature, placeholder)
-- CREATE TABLE IF NOT EXISTS schedule_event_participants (
--     id INT UNSIGNED NOT NULL AUTO_INCREMENT,
--     event_id INT UNSIGNED NOT NULL,
--     user_name VARCHAR(100) NOT NULL,
--     PRIMARY KEY (id),
--     CONSTRAINT fk_event_participant_event FOREIGN KEY (event_id) REFERENCES schedule_events (id) ON DELETE CASCADE
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

