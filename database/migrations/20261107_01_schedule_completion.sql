-- Additive only: no existing rows or module tables are deleted or rewritten.
-- MariaDB DDL is atomic per statement, not for this full file. On failure the runner records the failed step.
-- Recover by reviewing the failure and rerunning with --resume; restore the pre-migration backup for full rollback.
ALTER TABLE schedule_events ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'upcoming';
ALTER TABLE schedule_events ADD COLUMN IF NOT EXISTS recurrence VARCHAR(20) NOT NULL DEFAULT 'none';
ALTER TABLE schedule_events ADD COLUMN IF NOT EXISTS recurrence_until DATE NULL;
ALTER TABLE schedule_events ADD COLUMN IF NOT EXISTS reminder_minutes INT UNSIGNED NULL;
ALTER TABLE schedule_events ADD COLUMN IF NOT EXISTS task_id BIGINT UNSIGNED NULL;
ALTER TABLE schedule_events ADD COLUMN IF NOT EXISTS shift_template_id INT UNSIGNED NULL;
ALTER TABLE schedule_events ADD INDEX IF NOT EXISTS idx_schedule_task (task_id);
ALTER TABLE schedule_events ADD CONSTRAINT fk_schedule_task FOREIGN KEY IF NOT EXISTS (task_id) REFERENCES tasks(id) ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE IF NOT EXISTS schedule_holidays (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    holiday_date DATE NOT NULL,
    name VARCHAR(200) NOT NULL,
    source_code VARCHAR(40) NOT NULL DEFAULT 'nager-date',
    source_name VARCHAR(100) NOT NULL DEFAULT 'Nager.Date',
    year SMALLINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_schedule_holiday_source_date (source_code, holiday_date, name),
    KEY idx_schedule_holiday_year (year, holiday_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schedule_holiday_sync (
    year SMALLINT UNSIGNED NOT NULL,
    synced_at DATETIME NOT NULL,
    PRIMARY KEY (year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schedule_shift_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    weekdays VARCHAR(20) NOT NULL,
    repeat_until DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_schedule_shift_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE schedule_events ADD INDEX IF NOT EXISTS idx_schedule_event_shift_template (shift_template_id);
ALTER TABLE schedule_events ADD CONSTRAINT fk_schedule_event_shift_template FOREIGN KEY IF NOT EXISTS (shift_template_id) REFERENCES schedule_shift_templates(id) ON DELETE SET NULL ON UPDATE CASCADE;

-- Portable unique key ensures generating a template twice does not duplicate an event for a day.
CREATE TABLE IF NOT EXISTS schedule_shift_occurrences (
    template_id INT UNSIGNED NOT NULL,
    shift_date DATE NOT NULL,
    event_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (template_id, shift_date),
    UNIQUE KEY uq_schedule_shift_event (event_id),
    CONSTRAINT fk_schedule_shift_occ_template FOREIGN KEY (template_id) REFERENCES schedule_shift_templates(id) ON DELETE CASCADE,
    CONSTRAINT fk_schedule_shift_occ_event FOREIGN KEY (event_id) REFERENCES schedule_events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
