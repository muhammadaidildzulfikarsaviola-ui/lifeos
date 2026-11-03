# LIFEOS SQL MIGRATION SPECIFICATION

**Project:** LIFEOS  
**Version:** 1.0  
**Status:** SQL Implementation Specification  
**Database:** MySQL 8.0+  
**Engine:** InnoDB  
**Charset:** utf8mb4  
**Collation:** utf8mb4_unicode_ci  
**Backend:** PHP Native 8.2  
**Timezone:** UTC storage, Asia/Jakarta presentation

---

# 1. PURPOSE

Dokumen ini menjadi acuan implementasi SQL migrations LIFEOS.

Mencakup:

- Data types.
- Primary keys.
- Foreign keys.
- Unique constraints.
- Indexes.
- Nullable fields.
- Default values.
- Delete behavior.
- Migration dependencies.
- Rollback strategy.

Dokumen ini harus mengikuti keputusan dalam:

- LIFEOS_DATABASE_DESIGN.md
- LIFEOS_DATABASE_REVIEW.md
- LIFEOS_DATABASE_ERD.md

Jika terdapat konflik, gunakan keputusan terbaru pada Database Review dan ERD.

---

# 2. DATABASE CONVENTIONS

## 2.1 Naming

Gunakan:

- Table: plural snake_case.
- Column: singular snake_case.
- Primary key: id.
- Foreign key: referenced_table_singular_id.
- Timestamp: created_at, updated_at, deleted_at.
- Boolean: is_active, is_completed, is_cancelled.
- UUID: uuid.

Contoh:

tasks
task_dependencies
project_id
created_at

## 2.2 Primary Keys

Default:

BIGINT UNSIGNED AUTO_INCREMENT

UUID:

CHAR(36)

UUID digunakan untuk identifier eksternal dan referensi API.

Jangan menggunakan UUID sebagai pengganti foreign key internal.

## 2.3 Standard Ownership

Tabel milik profile menggunakan:

profile_id BIGINT UNSIGNED NOT NULL

Foreign key:

FOREIGN KEY (profile_id) REFERENCES profiles(id)

## 2.4 Standard Timestamps

Gunakan:

created_at DATETIME NOT NULL
updated_at DATETIME NOT NULL

Untuk soft delete:

deleted_at DATETIME NULL

Aplikasi harus mengatur timestamp secara konsisten dalam UTC.

## 2.5 Financial Data Types

Nominal:

DECIMAL(19,2)

Currency:

CHAR(3)

Jangan gunakan FLOAT atau DOUBLE untuk nominal uang.

## 2.6 Text Fields

Rekomendasi:

- VARCHAR(100): nama singkat.
- VARCHAR(255): judul atau nama umum.
- VARCHAR(500): ringkasan.
- TEXT: deskripsi dan catatan.
- LONGTEXT: konten panjang jurnal atau notes jika diperlukan.
- JSON: struktur data terkontrol.

Jangan menggunakan TEXT untuk field yang sering dijadikan filter atau unique key.

---

# 3. CORE TABLE SPECIFICATIONS

## 3.1 profiles

| Column | Type | Null | Rule |
|---|---|---|---|
| id | BIGINT UNSIGNED | No | PK |
| uuid | CHAR(36) | No | UNIQUE |
| name | VARCHAR(100) | No | |
| timezone | VARCHAR(64) | No | Asia/Jakarta |
| currency | CHAR(3) | No | IDR |
| created_at | DATETIME | No | |
| updated_at | DATETIME | No | |

V1 hanya membuat satu profile default.

---

# 4. FINANCE TABLES

## 4.1 accounts

| Column | Type | Null | Rule |
|---|---|---|---|
| id | BIGINT UNSIGNED | No | PK |
| profile_id | BIGINT UNSIGNED | No | FK |
| uuid | CHAR(36) | No | UNIQUE |
| name | VARCHAR(100) | No | |
| account_type | VARCHAR(30) | No | ENUM validation |
| initial_balance | DECIMAL(19,2) | No | Default 0 |
| initial_balance_date | DATE | No | |
| currency | CHAR(3) | No | IDR |
| is_active | TINYINT(1) | No | Default 1 |
| created_at | DATETIME | No | |
| updated_at | DATETIME | No | |
| deleted_at | DATETIME | Yes | |

Indexes:

- UNIQUE(profile_id, uuid)
- INDEX(profile_id, is_active)
- INDEX(profile_id, name)

## 4.2 categories

| Column | Type | Null | Rule |
|---|---|---|---|
| id | BIGINT UNSIGNED | No | PK |
| profile_id | BIGINT UNSIGNED | No | FK |
| uuid | CHAR(36) | No | UNIQUE |
| name | VARCHAR(100) | No | |
| category_type | VARCHAR(20) | No | income/expense |
| color | VARCHAR(20) | Yes | |
| icon | VARCHAR(50) | Yes | |
| parent_id | BIGINT UNSIGNED | Yes | Self FK |
| is_active | TINYINT(1) | No | Default 1 |
| created_at | DATETIME | No | |
| updated_at | DATETIME | No | |
| deleted_at | DATETIME | Yes | |

Parent category harus berasal dari profile dan category_type yang sama.

## 4.3 transactions

| Column | Type | Null | Rule |
|---|---|---|---|
| id | BIGINT UNSIGNED | No | PK |
| profile_id | BIGINT UNSIGNED | No | FK |
| uuid | CHAR(36) | No | UNIQUE |
| transaction_type | VARCHAR(20) | No | income/expense/transfer |
| description | VARCHAR(255) | No | |
| transaction_date | DATE | No | |
| category_id | BIGINT UNSIGNED | Yes | FK |
| project_id | BIGINT UNSIGNED | Yes | FK |
| recurring_template_id | BIGINT UNSIGNED | Yes | FK |
| recurring_occurrence_id | BIGINT UNSIGNED | Yes | FK |
| notes | TEXT | Yes | |
| created_at | DATETIME | No | |
| updated_at | DATETIME | No | |
| deleted_at | DATETIME | Yes | |

Indexes:

- UNIQUE(profile_id, uuid)
- INDEX(profile_id, transaction_date)
- INDEX(profile_id, transaction_type, transaction_date)
- INDEX(category_id)
- INDEX(project_id)

Category wajib sesuai transaction_type.

Transfer tidak menggunakan category income atau expense.

## 4.4 transaction_lines

| Column | Type | Null | Rule |
|---|---|---|---|
| id | BIGINT UNSIGNED | No | PK |
| transaction_id | BIGINT UNSIGNED | No | FK |
| account_id | BIGINT UNSIGNED | No | FK |
| direction | VARCHAR(3) | No | in/out |
| amount | DECIMAL(19,2) | No | Greater than 0 |
| created_at | DATETIME | No | |

Indexes:

- INDEX(transaction_id)
- INDEX(account_id, created_at)

Setiap transaction line harus menggunakan akun dalam profile yang sama dengan transaksi.

Validasi minimal satu line dan aturan jumlah line dilakukan oleh Finance Service.

## 4.5 transfers

| Column | Type | Null | Rule |
|---|---|---|---|
| id | BIGINT UNSIGNED | No | PK |
| transaction_id | BIGINT UNSIGNED | No | UNIQUE FK |
| source_account_id | BIGINT UNSIGNED | No | FK |
| destination_account_id | BIGINT UNSIGNED | No | FK |

Source dan destination tidak boleh sama.

Transfer harus memiliki dua transaction lines dengan nominal sama.

## 4.6 loans

| Column | Type | Null | Rule |
|---|---|---|---|
| id | BIGINT UNSIGNED | No | PK |
| profile_id | BIGINT UNSIGNED | No | FK |
| uuid | CHAR(36) | No | UNIQUE |
| lender_name | VARCHAR(150) | No | |
| principal_amount | DECIMAL(19,2) | No | Positive |
| interest_amount | DECIMAL(19,2) | No | Default 0 |
| borrowed_date | DATE | No | |
| due_date | DATE | Yes | |
| status | VARCHAR(30) | No | |
| notes | TEXT | Yes | |
| created_at | DATETIME | No | |
| updated_at | DATETIME | No | |
| deleted_at | DATETIME | Yes | |

## 4.7 loan_payments

| Column | Type | Null | Rule |
|---|---|---|---|
| id | BIGINT UNSIGNED | No | PK |
| loan_id | BIGINT UNSIGNED | No | FK |
| transaction_id | BIGINT UNSIGNED | No | UNIQUE FK |
| payment_date | DATE | No | |
| principal_paid | DECIMAL(19,2) | No | Default 0 |
| interest_paid | DECIMAL(19,2) | No | Default 0 |
| notes | TEXT | Yes | |

Principal dan interest tidak boleh negatif.

Total pembayaran harus sama dengan arus kas keluar yang direpresentasikan transaksi.

## 4.8 budgets

| Column | Type | Null | Rule |
|---|---|---|---|
| id | BIGINT UNSIGNED | No | PK |
| profile_id | BIGINT UNSIGNED | No | FK |
| uuid | CHAR(36) | No | UNIQUE |
| name | VARCHAR(100) | No | |
| period_type | VARCHAR(20) | No | |
| start_date | DATE | No | |
| end_date | DATE | No | |
| created_at | DATETIME | No | |
| updated_at | DATETIME | No | |
| deleted_at | DATETIME | Yes | |

## 4.9 budget_items

| Column | Type | Null | Rule |
|---|---|---|---|
| id | BIGINT UNSIGNED | No | PK |
| budget_id | BIGINT UNSIGNED | No | FK |
| category_id | BIGINT UNSIGNED | No | FK |
| limit_amount | DECIMAL(19,2) | No | Positive |

UNIQUE(budget_id, category_id).

Kategori harus bertipe expense.

---

# 5. RECURRING FINANCE TABLES

## 5.1 recurring_transaction_templates

Kolom:

- id BIGINT UNSIGNED PK
- profile_id BIGINT UNSIGNED FK
- uuid CHAR(36) UNIQUE
- name VARCHAR(150)
- transaction_type VARCHAR(20)
- amount DECIMAL(19,2)
- account_id BIGINT UNSIGNED FK
- category_id BIGINT UNSIGNED NULL FK
- frequency VARCHAR(20)
- interval_value SMALLINT UNSIGNED
- start_date DATE
- end_date DATE NULL
- next_run_at DATETIME
- behavior VARCHAR(20)
- is_active TINYINT(1)
- created_at DATETIME
- updated_at DATETIME
- deleted_at DATETIME NULL

## 5.2 recurring_occurrences

Kolom:

- id BIGINT UNSIGNED PK
- template_id BIGINT UNSIGNED FK
- occurrence_key VARCHAR(100)
- scheduled_at DATETIME
- execution_status VARCHAR(20)
- generated_transaction_id BIGINT UNSIGNED NULL FK
- generated_reminder_id BIGINT UNSIGNED NULL
- executed_at DATETIME NULL

UNIQUE(template_id, occurrence_key).

Untuk menghindari circular foreign key, tabel occurrence dibuat sebelum tabel transaksi jika diperlukan. Relasi generated_transaction_id ditambahkan setelah transactions tersedia.

---

# 6. PROJECT & TASK TABLES

## 6.1 projects

- id BIGINT UNSIGNED PK
- profile_id BIGINT UNSIGNED FK
- uuid CHAR(36) UNIQUE
- name VARCHAR(150)
- description TEXT NULL
- project_type VARCHAR(30)
- status VARCHAR(30)
- start_date DATE NULL
- deadline DATE NULL
- budget_amount DECIMAL(19,2) NULL
- created_at DATETIME
- updated_at DATETIME
- deleted_at DATETIME NULL

## 6.2 project_milestones

- id BIGINT UNSIGNED PK
- project_id BIGINT UNSIGNED FK
- title VARCHAR(150)
- description TEXT NULL
- due_date DATE NULL
- status VARCHAR(20)
- completed_at DATETIME NULL
- created_at DATETIME
- updated_at DATETIME

## 6.3 tasks

- id BIGINT UNSIGNED PK
- profile_id BIGINT UNSIGNED FK
- project_id BIGINT UNSIGNED NULL FK
- parent_task_id BIGINT UNSIGNED NULL FK
- uuid CHAR(36) UNIQUE
- title VARCHAR(255)
- description TEXT NULL
- status VARCHAR(30)
- priority VARCHAR(20)
- progress TINYINT UNSIGNED
- deadline DATETIME NULL
- estimated_minutes INT UNSIGNED NULL
- is_recurring TINYINT(1)
- created_at DATETIME
- updated_at DATETIME
- deleted_at DATETIME NULL

Progress harus berada dalam rentang 0–100.

Parent task harus berasal dari profile yang sama.

## 6.4 task_dependencies

- id BIGINT UNSIGNED PK
- task_id BIGINT UNSIGNED FK
- depends_on_task_id BIGINT UNSIGNED FK
- created_at DATETIME

UNIQUE(task_id, depends_on_task_id).

## 6.5 task_status_history

- id BIGINT UNSIGNED PK
- task_id BIGINT UNSIGNED FK
- old_status VARCHAR(30) NULL
- new_status VARCHAR(30)
- old_progress TINYINT UNSIGNED NULL
- new_progress TINYINT UNSIGNED NULL
- changed_at DATETIME
- note TEXT NULL

## 6.6 task_time_logs

- id BIGINT UNSIGNED PK
- task_id BIGINT UNSIGNED FK
- started_at DATETIME
- ended_at DATETIME NULL
- duration_minutes INT UNSIGNED
- description TEXT NULL
- created_at DATETIME

---

# 7. GOAL TABLES

## goals

- id BIGINT UNSIGNED PK
- profile_id BIGINT UNSIGNED FK
- uuid CHAR(36) UNIQUE
- title VARCHAR(255)
- description TEXT NULL
- goal_type VARCHAR(30)
- progress_mode VARCHAR(30)
- target_value DECIMAL(19,4) NULL
- current_value DECIMAL(19,4) NULL
- start_date DATE NULL
- deadline DATE NULL
- status VARCHAR(30)
- created_at DATETIME
- updated_at DATETIME
- deleted_at DATETIME NULL

## goal_milestones

- id BIGINT UNSIGNED PK
- goal_id BIGINT UNSIGNED FK
- title VARCHAR(150)
- target_date DATE NULL
- is_completed TINYINT(1)
- completed_at DATETIME NULL

## goal_links

- id BIGINT UNSIGNED PK
- goal_id BIGINT UNSIGNED FK
- linked_entity_type VARCHAR(30)
- linked_entity_id BIGINT UNSIGNED
- contribution_weight DECIMAL(8,4) NULL
- created_at DATETIME

UNIQUE(goal_id, linked_entity_type, linked_entity_id).

Polymorphic target divalidasi oleh service.

---

# 8. HABIT TABLES

## habits

- id BIGINT UNSIGNED PK
- profile_id BIGINT UNSIGNED FK
- uuid CHAR(36) UNIQUE
- name VARCHAR(150)
- description TEXT NULL
- tracking_type VARCHAR(20)
- target_value DECIMAL(19,4) NULL
- target_unit VARCHAR(50) NULL
- frequency VARCHAR(20)
- schedule_config JSON
- start_date DATE
- end_date DATE NULL
- is_active TINYINT(1)
- created_at DATETIME
- updated_at DATETIME
- deleted_at DATETIME NULL

## habit_items

- id BIGINT UNSIGNED PK
- habit_id BIGINT UNSIGNED FK
- name VARCHAR(150)
- sort_order INT UNSIGNED
- is_active TINYINT(1)

## habit_logs

- id BIGINT UNSIGNED PK
- habit_id BIGINT UNSIGNED FK
- log_date DATE
- completion_status VARCHAR(20)
- quantity_value DECIMAL(19,4) NULL
- duration_minutes INT UNSIGNED NULL
- notes TEXT NULL
- created_at DATETIME
- updated_at DATETIME

UNIQUE(habit_id, log_date).

## habit_log_items

- id BIGINT UNSIGNED PK
- habit_log_id BIGINT UNSIGNED FK
- habit_item_id BIGINT UNSIGNED FK
- is_completed TINYINT(1)
- quantity_value DECIMAL(19,4) NULL

UNIQUE(habit_log_id, habit_item_id).

---

# 9. CALENDAR TABLES

## calendar_events

- id BIGINT UNSIGNED PK
- profile_id BIGINT UNSIGNED FK
- uuid CHAR(36) UNIQUE
- title VARCHAR(255)
- description TEXT NULL
- event_type VARCHAR(30)
- start_at DATETIME
- end_at DATETIME NULL
- all_day TINYINT(1)
- status VARCHAR(20)
- created_at DATETIME
- updated_at DATETIME
- deleted_at DATETIME NULL

## event_recurrence_rules

- id BIGINT UNSIGNED PK
- event_id BIGINT UNSIGNED UNIQUE FK
- frequency VARCHAR(20)
- interval_value SMALLINT UNSIGNED
- weekdays JSON NULL
- month_day TINYINT UNSIGNED NULL
- end_date DATE NULL
- occurrence_count INT UNSIGNED NULL

## event_occurrence_overrides

- id BIGINT UNSIGNED PK
- event_id BIGINT UNSIGNED FK
- original_start_at DATETIME
- override_start_at DATETIME NULL
- override_end_at DATETIME NULL
- override_title VARCHAR(255) NULL
- override_status VARCHAR(20) NULL
- is_cancelled TINYINT(1)

UNIQUE(event_id, original_start_at).

## shifts

- id BIGINT UNSIGNED PK
- profile_id BIGINT UNSIGNED FK
- uuid CHAR(36) UNIQUE
- shift_name VARCHAR(100)
- shift_date DATE
- start_at DATETIME NULL
- end_at DATETIME NULL
- notes TEXT NULL
- created_at DATETIME
- updated_at DATETIME
- deleted_at DATETIME NULL

## task_schedule_links

- id BIGINT UNSIGNED PK
- task_id BIGINT UNSIGNED FK
- calendar_event_id BIGINT UNSIGNED FK
- created_at DATETIME

UNIQUE(task_id, calendar_event_id).

---

# 10. JOURNAL & NOTES TABLES

## journal_entries

- id BIGINT UNSIGNED PK
- profile_id BIGINT UNSIGNED FK
- uuid CHAR(36) UNIQUE
- title VARCHAR(255) NULL
- content LONGTEXT
- entry_date DATE
- created_at DATETIME
- updated_at DATETIME
- deleted_at DATETIME NULL

INDEX(profile_id, entry_date).

## journal_moods

- id BIGINT UNSIGNED PK
- journal_entry_id BIGINT UNSIGNED FK
- mood VARCHAR(30)
- created_at DATETIME

## note_folders

- id BIGINT UNSIGNED PK
- profile_id BIGINT UNSIGNED FK
- parent_id BIGINT UNSIGNED NULL FK
- name VARCHAR(150)
- sort_order INT UNSIGNED
- created_at DATETIME
- updated_at DATETIME
- deleted_at DATETIME NULL

## notes

- id BIGINT UNSIGNED PK
- profile_id BIGINT UNSIGNED FK
- folder_id BIGINT UNSIGNED NULL FK
- uuid CHAR(36) UNIQUE
- title VARCHAR(255)
- content LONGTEXT
- is_pinned TINYINT(1)
- created_at DATETIME
- updated_at DATETIME
- deleted_at DATETIME NULL

---

# 11. TAGS & ATTACHMENTS

## tags

- id BIGINT UNSIGNED PK
- profile_id BIGINT UNSIGNED FK
- name VARCHAR(100)
- normalized_name VARCHAR(100)
- created_at DATETIME

UNIQUE(profile_id, normalized_name).

## entity_tags

- id BIGINT UNSIGNED PK
- tag_id BIGINT UNSIGNED FK
- entity_type VARCHAR(30)
- entity_id BIGINT UNSIGNED
- created_at DATETIME

UNIQUE(tag_id, entity_type, entity_id).

## attachments

- id BIGINT UNSIGNED PK
- profile_id BIGINT UNSIGNED FK
- uuid CHAR(36) UNIQUE
- original_filename VARCHAR(255)
- stored_filename VARCHAR(255)
- storage_path VARCHAR(500)
- mime_type VARCHAR(150)
- extension VARCHAR(20)
- file_size BIGINT UNSIGNED
- checksum CHAR(64)
- upload_status VARCHAR(20)
- created_at DATETIME
- deleted_at DATETIME NULL

## entity_attachments

- id BIGINT UNSIGNED PK
- attachment_id BIGINT UNSIGNED FK
- entity_type VARCHAR(30)
- entity_id BIGINT UNSIGNED
- created_at DATETIME

---

# 12. SYSTEM TABLES

## notifications

- id BIGINT UNSIGNED PK
- profile_id BIGINT UNSIGNED FK
- uuid CHAR(36) UNIQUE
- source_type VARCHAR(50)
- source_id BIGINT UNSIGNED NULL
- occurrence_key VARCHAR(100) NULL
- notification_type VARCHAR(50)
- title VARCHAR(255)
- message TEXT
- scheduled_at DATETIME NULL
- created_at DATETIME
- deleted_at DATETIME NULL

## notification_deliveries

- id BIGINT UNSIGNED PK
- notification_id BIGINT UNSIGNED FK
- channel VARCHAR(30)
- delivery_status VARCHAR(20)
- attempts SMALLINT UNSIGNED
- last_attempt_at DATETIME NULL
- delivered_at DATETIME NULL
- error_message TEXT NULL

UNIQUE(notification_id, channel).

## trash_items

- id BIGINT UNSIGNED PK
- profile_id BIGINT UNSIGNED FK
- entity_type VARCHAR(50)
- entity_id BIGINT UNSIGNED
- deleted_at DATETIME
- restore_status VARCHAR(20)
- metadata JSON NULL

## change_logs

- id BIGINT UNSIGNED PK
- profile_id BIGINT UNSIGNED FK
- entity_type VARCHAR(50)
- entity_id BIGINT UNSIGNED
- action VARCHAR(30)
- old_values JSON NULL
- new_values JSON NULL
- created_at DATETIME

## import_jobs

- id BIGINT UNSIGNED PK
- profile_id BIGINT UNSIGNED FK
- uuid CHAR(36) UNIQUE
- import_type VARCHAR(50)
- source_filename VARCHAR(255)
- status VARCHAR(30)
- total_rows INT UNSIGNED
- valid_rows INT UNSIGNED
- invalid_rows INT UNSIGNED
- created_at DATETIME
- completed_at DATETIME NULL

## import_job_rows

- id BIGINT UNSIGNED PK
- import_job_id BIGINT UNSIGNED FK
- row_number INT UNSIGNED
- raw_data JSON
- normalized_data JSON NULL
- validation_errors JSON NULL
- processing_status VARCHAR(30)

UNIQUE(import_job_id, row_number).

## public_holidays

- id BIGINT UNSIGNED PK
- holiday_date DATE
- name VARCHAR(255)
- source_type VARCHAR(30)
- external_reference VARCHAR(255) NULL
- is_active TINYINT(1)
- created_at DATETIME
- updated_at DATETIME

---

# 13. BILL REMINDER TABLES

## bill_templates

- id BIGINT UNSIGNED PK
- profile_id BIGINT UNSIGNED FK
- uuid CHAR(36) UNIQUE
- name VARCHAR(150)
- expected_amount DECIMAL(19,2) NULL
- due_day TINYINT UNSIGNED NULL
- frequency VARCHAR(20)
- start_date DATE
- end_date DATE NULL
- reminder_enabled TINYINT(1)
- is_active TINYINT(1)
- created_at DATETIME
- updated_at DATETIME
- deleted_at DATETIME NULL

## bill_reminders

- id BIGINT UNSIGNED PK
- profile_id BIGINT UNSIGNED FK
- bill_template_id BIGINT UNSIGNED FK
- occurrence_key VARCHAR(100)
- due_date DATE
- status VARCHAR(20)
- completed_at DATETIME NULL

UNIQUE(bill_template_id, occurrence_key).

---

# 14. FOREIGN KEY DELETE POLICIES

Default policy:

- Core resources: RESTRICT.
- Child records with no independent meaning: CASCADE only after explicit review.
- Optional references: SET NULL where historical integrity allows it.
- Soft delete: handled by application service, not foreign key cascade.

Examples:

- Transaction → Transaction Lines: RESTRICT for physical deletion.
- Account → Transaction Lines: RESTRICT.
- Project → Tasks: RESTRICT.
- Task → Task Dependencies: RESTRICT or explicit cleanup service.
- Loan → Loan Payments: RESTRICT.
- Attachment → Entity Attachments: RESTRICT until references are removed.
- Profile → User Data: RESTRICT.

Never apply broad ON DELETE CASCADE to financial or historical data.

---

# 15. MIGRATION ORDER

Migration order must respect foreign key dependencies.

Recommended batches:

## Batch 1: Foundation

- profiles
- categories
- accounts
- tags

## Batch 2: Projects & Goals

- projects
- project_milestones
- goals
- goal_milestones
- goal_links

## Batch 3: Tasks

- tasks
- task_dependencies
- task_status_history
- task_time_logs

## Batch 4: Finance

- transactions
- transaction_lines
- transfers
- loans
- loan_payments
- budgets
- budget_items

## Batch 5: Recurring Finance

- recurring_transaction_templates
- recurring_occurrences
- bill_templates
- bill_reminders

Add deferred foreign keys after dependent tables exist.

## Batch 6: Calendar

- calendar_events
- event_recurrence_rules
- event_occurrence_overrides
- shifts
- task_schedule_links

## Batch 7: Habits

- habits
- habit_items
- habit_logs
- habit_log_items

## Batch 8: Journal & Notes

- journal_entries
- journal_moods
- note_folders
- notes

## Batch 9: Attachments & Tags

- attachments
- entity_attachments
- entity_tags

## Batch 10: System

- notifications
- notification_deliveries
- trash_items
- change_logs
- import_jobs
- import_job_rows
- public_holidays

---

# 16. MIGRATION IMPLEMENTATION RULES

1. Each migration must be independently identifiable.
2. Migration execution must be tracked.
3. Migrations must run in deterministic order.
4. Migration must fail visibly if a required dependency is missing.
5. Rollback must reverse the migration safely.
6. Destructive migrations require explicit approval.
7. Existing user data must never be silently dropped.
8. Schema changes must be tested on a fresh database.
9. Schema changes must also be tested against an existing database.
10. SQL must be compatible with the configured MySQL version.

Use versioned migration files.

Example:

202611030001_create_profiles_table.php

Do not use a single giant SQL file as the only migration mechanism.

---

# 17. REQUIRED DATABASE TESTS

- Fresh installation.
- Migration ordering.
- Migration rollback.
- Foreign key enforcement.
- Unique constraint enforcement.
- Profile ownership.
- Transaction line integrity.
- Transfer balance integrity.
- Loan payment integrity.
- Recurring occurrence idempotency.
- Calendar recurrence exceptions.
- Task dependency cycle prevention.
- Habit log uniqueness.
- Trash restoration.
- Attachment reference integrity.
- Import staging and commit.

---

# 18. FINAL IMPLEMENTATION GATE

SQL migration implementation may begin after:

- All table names are finalized.
- All foreign key dependencies are finalized.
- Finance ledger rules are implemented consistently.
- Recurrence behavior is defined.
- Soft-delete behavior is defined.
- Profile ownership is consistent.
- Migration order is verified.
- Critical business rules have test cases.

This document is a migration specification, not executable SQL.

---

# END OF SQL MIGRATION SPECIFICATION