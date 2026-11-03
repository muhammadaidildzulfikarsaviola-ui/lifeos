# LIFEOS DATABASE DESIGN
## Database Architecture & Schema Specification

**Project:** LIFEOS  
**Version:** 1.0  
**Status:** Design Baseline  
**Database:** MySQL 8.0+  
**Storage Engine:** InnoDB  
**Character Set:** utf8mb4  
**Collation:** utf8mb4_unicode_ci  
**Application:** PHP Native 8.2  
**Timezone:** Asia/Jakarta  
**Primary Key:** BIGINT UNSIGNED AUTO_INCREMENT

---

# 1. DATABASE PRINCIPLES

## 1.1 Architecture

LIFEOS menggunakan satu database MySQL dengan arsitektur modular.

Setiap modul memiliki tabel sendiri, tetapi relasi antar-modul diperbolehkan melalui foreign key atau relasi yang divalidasi oleh application service.

## 1.2 Naming Convention

- Nama tabel menggunakan plural snake_case.
- Nama kolom menggunakan snake_case.
- Primary key menggunakan `id`.
- Foreign key menggunakan format `[entity]_id`.
- Tanggal menggunakan `DATE`.
- Waktu menggunakan `DATETIME`.
- Nilai uang menggunakan `DECIMAL(15,2)`.
- Persentase menggunakan `DECIMAL(5,2)`.
- Boolean menggunakan `TINYINT(1)`.
- Data konfigurasi menggunakan JSON jika strukturnya memang fleksibel.

## 1.3 Standard Columns

Tabel operasional umumnya menggunakan:

```sql
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
deleted_at DATETIME NULL
```

`deleted_at` digunakan pada tabel yang mendukung soft delete.

Tidak semua tabel harus mempunyai soft delete. Tabel log, pivot, dan data turunan mengikuti aturan khusus.

## 1.4 Profile Ownership

Versi pertama hanya menggunakan satu profil lokal tanpa login.

Tabel operasional yang memiliki data milik pengguna menggunakan `profile_id`.

Semua query harus membatasi data berdasarkan profile aktif.

Struktur ini mempersiapkan pengembangan multiuser di masa depan tanpa mengaktifkan autentikasi sekarang.

---

# 2. CORE TABLES

## 2.1 profiles

Menyimpan profil lokal.

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| uuid | CHAR(36) | UNIQUE |
| name | VARCHAR(100) | NOT NULL |
| timezone | VARCHAR(64) | NOT NULL DEFAULT Asia/Jakarta |
| currency | CHAR(3) | NOT NULL DEFAULT IDR |
| is_default | BOOLEAN | DEFAULT 0 |
| created_at | DATETIME | NOT NULL |
| updated_at | DATETIME | NOT NULL |

Versi pertama hanya memiliki satu profil default.

## 2.2 app_settings

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| profile_id | BIGINT UNSIGNED | FK |
| setting_key | VARCHAR(100) | NOT NULL |
| setting_value | JSON | NULL |
| created_at | DATETIME | NOT NULL |
| updated_at | DATETIME | NOT NULL |

Unique: `(profile_id, setting_key)`.

## 2.3 categories

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| profile_id | BIGINT UNSIGNED | FK |
| name | VARCHAR(100) | NOT NULL |
| type | VARCHAR(40) | NOT NULL |
| color | VARCHAR(20) | NULL |
| icon | VARCHAR(60) | NULL |
| is_default | BOOLEAN | DEFAULT 0 |
| created_at | DATETIME | NOT NULL |
| updated_at | DATETIME | NOT NULL |
| deleted_at | DATETIME | NULL |

Jenis kategori antara lain:

- income
- expense
- task
- project
- habit
- journal
- note

Unique yang disarankan: `(profile_id, type, name)`.

## 2.4 tags

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| profile_id | BIGINT UNSIGNED | FK |
| name | VARCHAR(60) | NOT NULL |
| color | VARCHAR(20) | NULL |
| created_at | DATETIME | NOT NULL |
| updated_at | DATETIME | NOT NULL |
| deleted_at | DATETIME | NULL |

## 2.5 entity_tags

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| tag_id | BIGINT UNSIGNED | FK |
| entity_type | VARCHAR(40) | NOT NULL |
| entity_id | BIGINT UNSIGNED | NOT NULL |
| created_at | DATETIME | NOT NULL |

Unique: `(tag_id, entity_type, entity_id)`.

Entity type harus menggunakan daftar yang diizinkan oleh aplikasi.

Karena entity_id dapat menunjuk ke berbagai tabel, validasi relasi dilakukan melalui service, bukan foreign key polymorphic.

---

# 3. FINANCE TABLES

## 3.1 accounts

Menyimpan wallet dan rekening.

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| profile_id | BIGINT UNSIGNED | FK |
| name | VARCHAR(100) | NOT NULL |
| account_type | VARCHAR(40) | NOT NULL |
| initial_balance | DECIMAL(15,2) | NOT NULL DEFAULT 0 |
| currency | CHAR(3) | NOT NULL DEFAULT IDR |
| description | TEXT | NULL |
| is_active | BOOLEAN | DEFAULT 1 |
| created_at | DATETIME | NOT NULL |
| updated_at | DATETIME | NOT NULL |
| deleted_at | DATETIME | NULL |

Jenis akun:

- cash
- bank
- e_wallet
- savings
- other

Saldo saat ini dihitung dari saldo awal dan pergerakan transaksi yang sudah diposting.

Saldo tidak boleh diubah langsung tanpa adjustment transaction.

## 3.2 transactions

Menyimpan header transaksi keuangan.

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| uuid | CHAR(36) | UNIQUE |
| profile_id | BIGINT UNSIGNED | FK |
| category_id | BIGINT UNSIGNED | FK, NULL |
| transaction_type | VARCHAR(30) | NOT NULL |
| description | VARCHAR(255) | NOT NULL |
| transaction_date | DATETIME | NOT NULL |
| status | VARCHAR(20) | NOT NULL |
| reference_type | VARCHAR(40) | NULL |
| reference_id | BIGINT UNSIGNED | NULL |
| notes | TEXT | NULL |
| created_at | DATETIME | NOT NULL |
| updated_at | DATETIME | NOT NULL |
| deleted_at | DATETIME | NULL |

Jenis transaksi:

- income
- expense
- transfer
- adjustment
- loan_repayment

Status:

- draft
- posted
- void

Transaksi yang memengaruhi saldo harus berstatus `posted`.

## 3.3 transaction_lines

Menyimpan pergerakan dana.

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| transaction_id | BIGINT UNSIGNED | FK |
| account_id | BIGINT UNSIGNED | FK |
| direction | VARCHAR(10) | NOT NULL |
| amount | DECIMAL(15,2) | NOT NULL |
| created_at | DATETIME | NOT NULL |

Direction:

- debit
- credit

Aturan saldo:

- Income menambah saldo akun.
- Expense mengurangi saldo akun.
- Transfer mengurangi akun sumber dan menambah akun tujuan.
- Adjustment mencatat koreksi saldo.
- Loan repayment mengurangi saldo akun pembayaran.

Gunakan konvensi pergerakan dana yang konsisten. Validasi jumlah dan arah setiap transaksi dilakukan oleh FinanceService.

Transfer internal harus menghasilkan pergerakan yang seimbang dan tidak dihitung sebagai income atau expense.

## 3.4 transfers

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| transaction_id | BIGINT UNSIGNED | FK UNIQUE |
| source_account_id | BIGINT UNSIGNED | FK |
| destination_account_id | BIGINT UNSIGNED | FK |
| amount | DECIMAL(15,2) | NOT NULL |
| notes | TEXT | NULL |
| created_at | DATETIME | NOT NULL |

Source dan destination tidak boleh sama.

## 3.5 budgets

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| profile_id | BIGINT UNSIGNED | FK |
| name | VARCHAR(100) | NOT NULL |
| period_type | VARCHAR(20) | NOT NULL |
| start_date | DATE | NOT NULL |
| end_date | DATE | NOT NULL |
| total_limit | DECIMAL(15,2) | NULL |
| created_at | DATETIME | NOT NULL |
| updated_at | DATETIME | NOT NULL |
| deleted_at | DATETIME | NULL |

Period type:

- weekly
- monthly
- quarterly
- yearly
- custom

## 3.6 budget_items

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| budget_id | BIGINT UNSIGNED | FK |
| category_id | BIGINT UNSIGNED | FK |
| amount_limit | DECIMAL(15,2) | NOT NULL |
| created_at | DATETIME | NOT NULL |
| updated_at | DATETIME | NOT NULL |

Unique: `(budget_id, category_id)`.

## 3.7 recurring_transaction_templates

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| profile_id | BIGINT UNSIGNED | FK |
| account_id | BIGINT UNSIGNED | FK |
| category_id | BIGINT UNSIGNED | FK, NULL |
| name | VARCHAR(150) | NOT NULL |
| transaction_type | VARCHAR(20) | NOT NULL |
| amount | DECIMAL(15,2) | NOT NULL |
| frequency | VARCHAR(20) | NOT NULL |
| interval_value | INT UNSIGNED | DEFAULT 1 |
| start_date | DATE | NOT NULL |
| end_date | DATE | NULL |
| next_run_at | DATETIME | NULL |
| behavior | VARCHAR(20) | NOT NULL |
| is_active | BOOLEAN | DEFAULT 1 |
| created_at | DATETIME | NOT NULL |
| updated_at | DATETIME | NOT NULL |

Behavior:

- automatic
- reminder
- both

Template tidak boleh menghasilkan transaksi duplikat pada periode yang sama.

## 3.8 fixed_bill_templates

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| profile_id | BIGINT UNSIGNED | FK |
| name | VARCHAR(150) | NOT NULL |
| expected_amount | DECIMAL(15,2) | NULL |
| frequency | VARCHAR(20) | NOT NULL |
| reminder_day | TINYINT UNSIGNED | NULL |
| next_reminder_date | DATE | NULL |
| notes | TEXT | NULL |
| is_active | BOOLEAN | DEFAULT 1 |
| created_at | DATETIME | NOT NULL |
| updated_at | DATETIME | NOT NULL |
| deleted_at | DATETIME | NULL |

Template hanya menghasilkan pengingat.

Tagihan aktual dicatat melalui transaksi manual.

## 3.9 loans

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| profile_id | BIGINT UNSIGNED | FK |
| lender_name | VARCHAR(150) | NOT NULL |
| principal_amount | DECIMAL(15,2) | NOT NULL |
| interest_amount | DECIMAL(15,2) | NOT NULL DEFAULT 0 |
| borrowed_date | DATE | NOT NULL |
| due_date | DATE | NULL |
| notes | TEXT | NULL |
| status | VARCHAR(20) | NOT NULL |
| created_at | DATETIME | NOT NULL |
| updated_at | DATETIME | NOT NULL |
| deleted_at | DATETIME | NULL |

Status:

- unpaid
- partially_paid
- paid

## 3.10 loan_payments

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| loan_id | BIGINT UNSIGNED | FK |
| transaction_id | BIGINT UNSIGNED | FK UNIQUE |
| payment_date | DATETIME | NOT NULL |
| principal_paid | DECIMAL(15,2) | NOT NULL |
| interest_paid | DECIMAL(15,2) | NOT NULL DEFAULT 0 |
| notes | TEXT | NULL |
| created_at | DATETIME | NOT NULL |

Pembayaran bersifat sekali bayar sesuai kebutuhan pengguna. Tabel tetap mendukung histori lebih dari satu pembayaran apabila terjadi pelunasan bertahap.

Sisa pinjaman dihitung dari pokok, bunga, dan total pembayaran.

---

# 4. SCHEDULE TABLES

## 4.1 calendar_events

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| profile_id | BIGINT UNSIGNED | FK |
| title | VARCHAR(200) | NOT NULL |
| description | TEXT | NULL |
| start_at | DATETIME | NOT NULL |
| end_at | DATETIME | NULL |
| all_day | BOOLEAN | DEFAULT 0 |
| event_type | VARCHAR(30) | NOT NULL |
| status | VARCHAR(20) | NOT NULL |
| recurrence_rule | JSON | NULL |
| location | VARCHAR(255) | NULL |
| created_at | DATETIME | NOT NULL |
| updated_at | DATETIME | NOT NULL |
| deleted_at | DATETIME | NULL |

Event type:

- personal
- work
- task
- project
- reminder
- holiday
- shift

Status:

- scheduled
- completed
- cancelled

Recurring event harus mendukung pengecualian tanggal dan perubahan satu occurrence tanpa merusak seluruh rangkaian.

## 4.2 event_reminders

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| event_id | BIGINT UNSIGNED | FK |
| reminder_type | VARCHAR(20) | NOT NULL |
| offset_minutes | INT | NULL |
| custom_at | DATETIME | NULL |
| channel | VARCHAR(20) | NOT NULL |
| is_enabled | BOOLEAN | DEFAULT 1 |
| created_at | DATETIME | NOT NULL |

Channel:

- in_app
- browser

## 4.3 shifts

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| profile_id | BIGINT UNSIGNED | FK |
| event_id | BIGINT UNSIGNED | FK, NULL |
| name | VARCHAR(100) | NOT NULL |
| shift_date | DATE | NOT NULL |
| start_time | TIME | NULL |
| end_time | TIME | NULL |
| notes | TEXT | NULL |
| created_at | DATETIME | NOT NULL |
| updated_at | DATETIME | NOT NULL |
| deleted_at | DATETIME | NULL |

Jam kerja bersifat opsional.

## 4.4 public_holidays

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| holiday_date | DATE | NOT NULL |
| name | VARCHAR(200) | NOT NULL |
| description | TEXT | NULL |
| source | VARCHAR(100) | NULL |
| source_identifier | VARCHAR(150) | NULL |
| is_official | BOOLEAN | DEFAULT 0 |
| created_at | DATETIME | NOT NULL |
| updated_at | DATETIME | NOT NULL |

Unique: `(holiday_date, name)`.

Data dapat diperbarui dari sumber online dan impor manual.

## 4.5 task_schedule_links

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| task_id | BIGINT UNSIGNED | FK |
| event_id | BIGINT UNSIGNED | FK |
| created_at | DATETIME | NOT NULL |

Unique: `(task_id, event_id)`.

Satu task dapat memiliki beberapa sesi jadwal.

---

# 5. TASK MANAGEMENT TABLES

## 5.1 tasks

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| uuid | CHAR(36) | UNIQUE |
| profile_id | BIGINT UNSIGNED | FK |
| project_id | BIGINT UNSIGNED | FK, NULL |
| category_id | BIGINT UNSIGNED | FK, NULL |
| parent_task_id | BIGINT UNSIGNED | FK, NULL |
| title | VARCHAR(200) | NOT NULL |
| description | TEXT | NULL |
| priority | VARCHAR(20) | NOT NULL |
| status | VARCHAR(30) | NOT NULL |
| progress | DECIMAL(5,2) | NOT NULL DEFAULT 0 |
| start_date | DATETIME | NULL |
| deadline | DATETIME | NULL |
| estimated_minutes | INT UNSIGNED | NULL |
| actual_minutes | INT UNSIGNED | NOT NULL DEFAULT 0 |
| is_recurring | BOOLEAN | DEFAULT 0 |
| recurrence_rule | JSON | NULL |
| completed_at | DATETIME | NULL |
| created_at | DATETIME | NOT NULL |
| updated_at | DATETIME | NOT NULL |
| deleted_at | DATETIME | NULL |

Priority:

- low
- medium
- high
- urgent

Status:

- todo
- in_progress
- review
- completed
- cancelled

Parent progress dihitung dari anak-anaknya dan tidak boleh menjadi nilai yang bertentangan dengan hasil kalkulasi.

## 5.2 task_dependencies

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| task_id | BIGINT UNSIGNED | FK |
| depends_on_task_id | BIGINT UNSIGNED | FK |
| created_at | DATETIME | NOT NULL |

Unique: `(task_id, depends_on_task_id)`.

Task tidak boleh menjadi dependency dirinya sendiri.

Siklus dependency harus ditolak oleh service.

## 5.3 task_time_logs

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| task_id | BIGINT UNSIGNED | FK |
| profile_id | BIGINT UNSIGNED | FK |
| started_at | DATETIME | NOT NULL |
| ended_at | DATETIME | NOT NULL |
| duration_minutes | INT UNSIGNED | NOT NULL |
| description | TEXT | NULL |
| created_at | DATETIME | NOT NULL |

Tracking dilakukan manual.

## 5.4 task_comments

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| task_id | BIGINT UNSIGNED | FK |
| profile_id | BIGINT UNSIGNED | FK |
| content | TEXT | NOT NULL |
| created_at | DATETIME | NOT NULL |
| updated_at | DATETIME | NOT NULL |
| deleted_at | DATETIME | NULL |

## 5.5 task_status_history

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| task_id | BIGINT UNSIGNED | FK |
| old_status | VARCHAR(30) | NULL |
| new_status | VARCHAR(30) | NOT NULL |
| old_progress | DECIMAL(5,2) | NULL |
| new_progress | DECIMAL(5,2) | NULL |
| changed_at | DATETIME | NOT NULL |

---

# 6. HABIT TABLES

## 6.1 habits

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| profile_id | BIGINT UNSIGNED | FK |
| category_id | BIGINT UNSIGNED | FK, NULL |
| name | VARCHAR(150) | NOT NULL |
| description | TEXT | NULL |
| tracking_type | VARCHAR(20) | NOT NULL |
| target_value | DECIMAL(15,2) | NULL |
| target_unit | VARCHAR(40) | NULL |
| frequency | VARCHAR(20) | NOT NULL |
| reminder_time | TIME | NULL |
| is_active | BOOLEAN | DEFAULT 1 |
| created_at | DATETIME | NOT NULL |
| updated_at | DATETIME | NOT NULL |
| deleted_at | DATETIME | NULL |

Tracking type:

- checklist
- quantity
- duration

## 6.2 habit_schedules

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| habit_id | BIGINT UNSIGNED | FK |
| weekday | TINYINT UNSIGNED | NULL |
| schedule_date | DATE | NULL |
| created_at | DATETIME | NOT NULL |

Weekday mengikuti ISO-8601: Senin = 1, Minggu = 7.

Jadwal khusus dan jadwal mingguan harus divalidasi agar tidak saling bertentangan.

## 6.3 habit_logs

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| habit_id | BIGINT UNSIGNED | FK |
| log_date | DATE | NOT NULL |
| status | VARCHAR(20) | NOT NULL |
| actual_value | DECIMAL(15,2) | NULL |
| notes | TEXT | NULL |
| completed_at | DATETIME | NULL |
| created_at | DATETIME | NOT NULL |
| updated_at | DATETIME | NOT NULL |

Status:

- completed
- partial
- missed

Unique: `(habit_id, log_date)`.

## 6.4 habit_rest_days

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| habit_id | BIGINT UNSIGNED | FK |
| rest_date | DATE | NOT NULL |
| reason | VARCHAR(255) | NULL |
| created_at | DATETIME | NOT NULL |

Unique: `(habit_id, rest_date)`.

Rest day tidak dihitung sebagai completion dan tidak memutus streak.

---

# 7. JOURNAL & NOTES TABLES

## 7.1 journal_entries

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| uuid | CHAR(36) | UNIQUE |
| profile_id | BIGINT UNSIGNED | FK |
| category_id | BIGINT UNSIGNED | FK, NULL |
| title | VARCHAR(200) | NULL |
| content | LONGTEXT | NOT NULL |
| content_format | VARCHAR(20) | NOT NULL |
| entry_date | DATE | NOT NULL |
| created_at | DATETIME | NOT NULL |
| updated_at | DATETIME | NOT NULL |
| deleted_at | DATETIME | NULL |

Content format:

- markdown
- rich_text

Multiple entries per day diperbolehkan.

## 7.2 journal_moods

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| journal_entry_id | BIGINT UNSIGNED | FK |
| mood | VARCHAR(30) | NOT NULL |
| created_at | DATETIME | NOT NULL |

Mood:

- happy
- neutral
- sad
- angry
- anxious
- excited
- tired

## 7.3 journal_templates

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| profile_id | BIGINT UNSIGNED | FK |
| name | VARCHAR(150) | NOT NULL |
| content | LONGTEXT | NOT NULL |
| content_format | VARCHAR(20) | NOT NULL |
| is_default | BOOLEAN | DEFAULT 0 |
| created_at | DATETIME | NOT NULL |
| updated_at | DATETIME | NOT NULL |

## 7.4 note_folders

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| profile_id | BIGINT UNSIGNED | FK |
| parent_folder_id | BIGINT UNSIGNED | FK, NULL |
| name | VARCHAR(120) | NOT NULL |
| created_at | DATETIME | NOT NULL |
| updated_at | DATETIME | NOT NULL |
| deleted_at | DATETIME | NULL |

## 7.5 notes

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| uuid | CHAR(36) | UNIQUE |
| profile_id | BIGINT UNSIGNED | FK |
| folder_id | BIGINT UNSIGNED | FK, NULL |
| title | VARCHAR(200) | NOT NULL |
| content | LONGTEXT | NULL |
| content_format | VARCHAR(20) | NOT NULL |
| is_pinned | BOOLEAN | DEFAULT 0 |
| is_archived | BOOLEAN | DEFAULT 0 |
| created_at | DATETIME | NOT NULL |
| updated_at | DATETIME | NOT NULL |
| deleted_at | DATETIME | NULL |

## 7.6 note_checklist_items

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| note_id | BIGINT UNSIGNED | FK |
| content | VARCHAR(500) | NOT NULL |
| is_completed | BOOLEAN | DEFAULT 0 |
| sort_order | INT | DEFAULT 0 |
| created_at | DATETIME | NOT NULL |
| updated_at | DATETIME | NOT NULL |

## 7.7 attachments

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| profile_id | BIGINT UNSIGNED | FK |
| entity_type | VARCHAR(30) | NOT NULL |
| entity_id | BIGINT UNSIGNED | NOT NULL |
| original_name | VARCHAR(255) | NOT NULL |
| stored_name | VARCHAR(255) | NOT NULL |
| relative_path | VARCHAR(500) | NOT NULL |
| mime_type | VARCHAR(150) | NOT NULL |
| extension | VARCHAR(15) | NOT NULL |
| file_size | BIGINT UNSIGNED | NOT NULL |
| created_at | DATETIME | NOT NULL |
| deleted_at | DATETIME | NULL |

Maksimal ukuran file: 50 MB.

Penyimpanan file berada di luar public web root.

---

# 8. GOALS & PROJECT TABLES

## 8.1 goals

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| profile_id | BIGINT UNSIGNED | FK |
| category_id | BIGINT UNSIGNED | FK, NULL |
| title | VARCHAR(200) | NOT NULL |
| description | TEXT | NULL |
| goal_type | VARCHAR(20) | NOT NULL |
| progress_method | VARCHAR(20) | NOT NULL |
| target_value | DECIMAL(15,2) | NULL |
| current_value | DECIMAL(15,2) | NULL |
| unit | VARCHAR(40) | NULL |
| start_date | DATE | NULL |
| deadline | DATE | NULL |
| status | VARCHAR(20) | NOT NULL |
| created_at | DATETIME | NOT NULL |
| updated_at | DATETIME | NOT NULL |
| deleted_at | DATETIME | NULL |

Goal type:

- daily
- weekly
- monthly
- yearly
- long_term

Progress method:

- linked
- measurable

## 8.2 goal_milestones

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| goal_id | BIGINT UNSIGNED | FK |
| title | VARCHAR(200) | NOT NULL |
| target_value | DECIMAL(15,2) | NULL |
| current_value | DECIMAL(15,2) | NULL |
| deadline | DATE | NULL |
| status | VARCHAR(20) | NOT NULL |
| sort_order | INT | DEFAULT 0 |
| created_at | DATETIME | NOT NULL |
| updated_at | DATETIME | NOT NULL |

## 8.3 goal_links

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| goal_id | BIGINT UNSIGNED | FK |
| entity_type | VARCHAR(30) | NOT NULL |
| entity_id | BIGINT UNSIGNED | NOT NULL |
| weight | DECIMAL(8,4) | NULL |
| created_at | DATETIME | NOT NULL |

Entity type:

- task
- project
- habit
- finance_target

Relasi harus divalidasi berdasarkan entity_type.

## 8.4 goal_measurements

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| goal_id | BIGINT UNSIGNED | FK |
| measured_value | DECIMAL(15,2) | NOT NULL |
| measured_at | DATETIME | NOT NULL |
| notes | TEXT | NULL |
| created_at | DATETIME | NOT NULL |

## 8.5 projects

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| profile_id | BIGINT UNSIGNED | FK |
| category_id | BIGINT UNSIGNED | FK, NULL |
| name | VARCHAR(200) | NOT NULL |
| description | TEXT | NULL |
| status | VARCHAR(30) | NOT NULL |
| start_date | DATE | NULL |
| deadline | DATE | NULL |
| progress | DECIMAL(5,2) | NOT NULL DEFAULT 0 |
| notes | LONGTEXT | NULL |
| created_at | DATETIME | NOT NULL |
| updated_at | DATETIME | NOT NULL |
| deleted_at | DATETIME | NULL |

Status:

- not_started
- planning
- in_progress
- on_hold
- completed
- cancelled

Progress dihitung berdasarkan task terkait.

## 8.6 project_milestones

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| project_id | BIGINT UNSIGNED | FK |
| title | VARCHAR(200) | NOT NULL |
| description | TEXT | NULL |
| deadline | DATE | NULL |
| status | VARCHAR(20) | NOT NULL |
| sort_order | INT | DEFAULT 0 |
| created_at | DATETIME | NOT NULL |
| updated_at | DATETIME | NOT NULL |

## 8.7 project_budgets

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| project_id | BIGINT UNSIGNED | FK |
| planned_amount | DECIMAL(15,2) | NOT NULL DEFAULT 0 |
| currency | CHAR(3) | NOT NULL DEFAULT IDR |
| notes | TEXT | NULL |
| created_at | DATETIME | NOT NULL |
| updated_at | DATETIME | NOT NULL |

Actual budget dihitung dari transaksi keuangan yang dikaitkan dengan project.

Untuk itu, transactions perlu memiliki `project_id` nullable sebagai referensi project.

---

# 9. SYSTEM TABLES

## 9.1 trash_items

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| profile_id | BIGINT UNSIGNED | FK |
| entity_type | VARCHAR(40) | NOT NULL |
| entity_id | BIGINT UNSIGNED | NOT NULL |
| original_data | JSON | NULL |
| deleted_at | DATETIME | NOT NULL |
| restored_at | DATETIME | NULL |
| permanently_deleted_at | DATETIME | NULL |

Shared trash untuk seluruh modul.

Data yang memiliki histori atau relasi penting tidak boleh dihapus permanen tanpa pemeriksaan dependensi.

## 9.2 change_logs

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| profile_id | BIGINT UNSIGNED | FK |
| entity_type | VARCHAR(40) | NOT NULL |
| entity_id | BIGINT UNSIGNED | NOT NULL |
| action | VARCHAR(30) | NOT NULL |
| old_values | JSON | NULL |
| new_values | JSON | NULL |
| changed_at | DATETIME | NOT NULL |

Action:

- created
- updated
- deleted
- restored
- status_changed

## 9.3 backup_logs

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| profile_id | BIGINT UNSIGNED | FK |
| filename | VARCHAR(255) | NOT NULL |
| relative_path | VARCHAR(500) | NOT NULL |
| file_size | BIGINT UNSIGNED | NULL |
| checksum | VARCHAR(128) | NULL |
| backup_type | VARCHAR(20) | NOT NULL |
| status | VARCHAR(20) | NOT NULL |
| created_at | DATETIME | NOT NULL |
| validated_at | DATETIME | NULL |

Backup type:

- manual
- automatic

## 9.4 import_jobs

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| profile_id | BIGINT UNSIGNED | FK |
| module | VARCHAR(40) | NOT NULL |
| filename | VARCHAR(255) | NOT NULL |
| file_type | VARCHAR(10) | NOT NULL |
| status | VARCHAR(20) | NOT NULL |
| total_rows | INT UNSIGNED | DEFAULT 0 |
| valid_rows | INT UNSIGNED | DEFAULT 0 |
| invalid_rows | INT UNSIGNED | DEFAULT 0 |
| imported_rows | INT UNSIGNED | DEFAULT 0 |
| created_at | DATETIME | NOT NULL |
| completed_at | DATETIME | NULL |

## 9.5 import_errors

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| import_job_id | BIGINT UNSIGNED | FK |
| row_number | INT UNSIGNED | NOT NULL |
| field_name | VARCHAR(100) | NULL |
| error_message | TEXT | NOT NULL |
| row_data | JSON | NULL |
| created_at | DATETIME | NOT NULL |

## 9.6 notification_logs

| Column | Type | Constraint |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| profile_id | BIGINT UNSIGNED | FK |
| entity_type | VARCHAR(40) | NOT NULL |
| entity_id | BIGINT UNSIGNED | NOT NULL |
| title | VARCHAR(200) | NOT NULL |
| message | TEXT | NULL |
| channel | VARCHAR(20) | NOT NULL |
| scheduled_at | DATETIME | NOT NULL |
| sent_at | DATETIME | NULL |
| status | VARCHAR(20) | NOT NULL |
| created_at | DATETIME | NOT NULL |

---

# 10. ADDITIONAL INDEXES

Index yang harus tersedia:

- `transactions(profile_id, transaction_date)`
- `transactions(profile_id, status)`
- `transactions(project_id)`
- `transaction_lines(account_id, transaction_id)`
- `tasks(profile_id, status, deadline)`
- `tasks(project_id, status)`
- `tasks(parent_task_id)`
- `calendar_events(profile_id, start_at, end_at)`
- `habit_logs(habit_id, log_date)`
- `journal_entries(profile_id, entry_date)`
- `notes(profile_id, folder_id, updated_at)`
- `loans(profile_id, status, due_date)`
- `change_logs(entity_type, entity_id, changed_at)`
- `notification_logs(status, scheduled_at)`

Index tambahan dibuat setelah melihat pola query aktual.

Jangan menambahkan index ke setiap kolom tanpa alasan karena index juga memiliki biaya penyimpanan dan penulisan.

---

# 11. FOREIGN KEY RULES

Gunakan InnoDB.

Aturan umum:

- Relasi data utama menggunakan foreign key.
- Gunakan `RESTRICT` untuk data finansial dan histori.
- Gunakan `CASCADE` hanya untuk data turunan yang memang aman dihapus bersama induknya.
- Jangan menggunakan cascade untuk menghapus histori transaksi.
- Soft delete tidak boleh menghapus relasi fisik.
- Polymorphic relationship harus divalidasi di application service.

Setiap foreign key harus memiliki tipe data yang sama dengan kolom referensinya.

---

# 12. MIGRATION ORDER

Migration dilakukan berdasarkan dependency.

01. `profiles`
02. `app_settings`
03. `categories`
04. `tags`
05. `accounts`
06. `transactions`
07. `transaction_lines`
08. `transfers`
09. `budgets`
10. `budget_items`
11. `recurring_transaction_templates`
12. `fixed_bill_templates`
13. `loans`
14. `loan_payments`
15. `projects`
16. `project_milestones`
17. `project_budgets`
18. `tasks`
19. `task_dependencies`
20. `task_time_logs`
21. `task_comments`
22. `task_status_history`
23. `calendar_events`
24. `event_reminders`
25. `shifts`
26. `public_holidays`
27. `task_schedule_links`
28. `habits`
29. `habit_schedules`
30. `habit_logs`
31. `habit_rest_days`
32. `goals`
33. `goal_milestones`
34. `goal_links`
35. `goal_measurements`
36. `journal_entries`
37. `journal_moods`
38. `journal_templates`
39. `note_folders`
40. `notes`
41. `note_checklist_items`
42. `attachments`
43. `entity_tags`
44. `trash_items`
45. `change_logs`
46. `backup_logs`
47. `import_jobs`
48. `import_errors`
49. `notification_logs`

Urutan migration aktual harus disesuaikan jika ada foreign key tambahan yang membutuhkan tabel lain dibuat lebih dahulu.

---

# 13. SEED DATA

Data awal yang disarankan:

- Default local profile.
- Default finance categories.
- Default task categories.
- Default project categories.
- Default habit categories.
- Default journal categories.
- Default currency IDR.
- Default timezone Asia/Jakarta.
- Default reminder preferences.
- Default application settings.

Seed harus idempotent: menjalankannya kembali tidak boleh menggandakan data default.

---

# 14. IMPORTANT BUSINESS LOGIC

## Finance
- Saldo dihitung dari posted transactions.
- Transfer tidak memengaruhi income/expense report.
- Adjustment harus memiliki histori.
- Loan payment harus terhubung dengan transaction.
- Actual project budget dihitung dari transaksi terkait project.

## Task
- Parent progress dihitung dari anak aktif.
- Dependency tidak memblokir penyelesaian.
- Siklus dependency ditolak.
- Perubahan status dicatat di history.

## Habit
- Rest day tidak dihitung sebagai completed atau missed.
- Streak mengikuti jadwal habit.
- Satu habit hanya memiliki satu log per tanggal.

## Goals
- Linked progress dihitung dari item yang terhubung.
- Measurable progress dihitung dari current value dan target.
- Progress dibatasi antara 0 dan 100 untuk tampilan pencapaian.

## Projects
- Progress dihitung dari task terkait.
- Planned budget berasal dari project budget.
- Actual budget berasal dari transaksi terkait.

## Trash
- Soft delete sebagai default.
- Restore harus memeriksa konflik dan relasi.
- Permanent delete harus memeriksa dependensi.
- Retensi tidak dibatasi waktu.

---

# 15. SECURITY AND VALIDATION

- Gunakan PDO prepared statements.
- Validasi semua foreign key pada service.
- Validasi nilai uang harus lebih besar dari atau sama dengan nol.
- Validasi tanggal dan rentang waktu.
- Validasi UUID pada pertukaran data.
- Validasi JSON sebelum diproses.
- File upload menggunakan MIME detection.
- Validasi extension dan ukuran file.
- File disimpan di luar public web root.
- Jangan mempercayai nama file dari pengguna.
- Semua perubahan penting dicatat di change_logs.

---

# 16. IMPLEMENTATION NOTES

Dokumen ini adalah spesifikasi desain, bukan SQL migration yang langsung dijalankan.

Sebelum membuat migration, lakukan pemeriksaan:

1. Pastikan seluruh foreign key sudah memiliki target tabel.
2. Pastikan unique constraints tidak menghalangi data yang sah.
3. Pastikan nullable columns sesuai business logic.
4. Pastikan indeks mendukung query utama.
5. Pastikan transaksi keuangan konsisten.
6. Pastikan penghapusan tidak merusak histori.
7. Pastikan seed data dapat dijalankan berulang.
8. Pastikan restore dapat memulihkan seluruh tabel terkait.

Database harus diuji dengan sample data dan integration tests sebelum digunakan oleh seluruh modul.

---

**END OF DATABASE DESIGN**
