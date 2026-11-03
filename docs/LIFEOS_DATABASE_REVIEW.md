# LIFEOS DATABASE REVIEW & DESIGN DECISIONS

**Project:** LIFEOS  
**Version:** 1.0  
**Status:** Database Design Review  
**Database:** MySQL 8.0+  
**Engine:** InnoDB  
**Charset:** utf8mb4  
**Timezone:** Asia/Jakarta

---

# 1. PURPOSE

Dokumen ini mengaudit desain database LIFEOS sebelum implementasi SQL migrations.

Tujuan:

- Menghilangkan ambiguitas struktur tabel.
- Menetapkan aturan relasi antar-modul.
- Mencegah duplikasi data.
- Menjamin integritas transaksi keuangan.
- Menetapkan strategi recurring data.
- Menetapkan aturan soft delete dan restore.
- Menentukan perilaku database yang konsisten.

Dokumen ini menjadi referensi koreksi terhadap `LIFEOS_DATABASE_DESIGN.md`.

Jika terdapat perbedaan keputusan, dokumen review ini menjadi acuan untuk implementasi database.

---

# 2. GLOBAL DATABASE RULES

## 2.1 Primary Keys

Setiap tabel utama menggunakan:

- BIGINT UNSIGNED sebagai primary key.
- UUID sebagai identifier eksternal jika diperlukan.

Internal relationship menggunakan numeric ID.

API dan JSON bridge menggunakan UUID untuk mengidentifikasi resource yang perlu direferensikan secara eksternal.

## 2.2 Profile Ownership

Setiap data milik pengguna harus memiliki `profile_id`.

Pengecualian hanya untuk data referensi global yang memang tidak dimiliki oleh satu profile, seperti kalender hari libur resmi.

Aturan:

- Setiap query data pengguna harus dibatasi profile aktif.
- Foreign key harus menjaga konsistensi relasi.
- Service harus memvalidasi ownership sebelum melakukan perubahan.
- UUID tidak menggantikan validasi ownership.

Untuk v1, hanya ada satu profile lokal.

## 2.3 Timestamps

Kolom standar:

- `created_at`
- `updated_at`
- `deleted_at` jika mendukung soft delete.

Semua timestamp aplikasi menggunakan satu konvensi: disimpan dalam UTC, kemudian dikonversi ke Asia/Jakarta saat ditampilkan dan diproses pada batas waktu lokal.

Tanggal kalender seperti `transaction_date`, `log_date`, dan `holiday_date` menggunakan DATE karena bukan timestamp.

Jangan mencampur waktu UTC dan waktu lokal dalam kolom DATETIME yang sama.

## 2.4 Financial Values

Nominal uang menggunakan:

DECIMAL(19,2)

Dilarang menggunakan FLOAT atau DOUBLE untuk perhitungan keuangan.

Currency v1:

IDR

## 2.5 Deletion

Soft delete menjadi mekanisme penghapusan default.

Hard delete hanya untuk:

- Data sementara.
- Data staging yang sudah kedaluwarsa.
- Data yang memang aman dihapus permanen.
- Permanent deletion yang sudah dikonfirmasi.

---

# 3. FINANCE DATABASE REVIEW

## 3.1 Decision: Ledger-Based Account Balance

Saldo akun tidak boleh dikelola dengan mengubah angka saldo secara terpisah setiap kali transaksi dibuat.

Saldo dihitung dari:

Initial balance + seluruh account movements

Setiap pergerakan uang dicatat sebagai ledger movement.

## 3.2 Recommended Tables

### accounts

Menyimpan akun keuangan.

Kolom penting:

- id
- profile_id
- uuid
- name
- account_type
- initial_balance
- initial_balance_date
- currency
- is_active
- created_at
- updated_at
- deleted_at

`initial_balance` adalah saldo pembuka, bukan saldo berjalan.

### transactions

Menyimpan informasi utama transaksi.

Kolom:

- id
- profile_id
- uuid
- transaction_type
- description
- transaction_date
- notes
- project_id nullable
- recurring_template_id nullable
- recurring_occurrence_key nullable
- created_at
- updated_at
- deleted_at

Jenis transaksi:

- income
- expense
- transfer

Pembayaran pinjaman tidak menjadi jenis transaksi terpisah. Pembayaran merupakan transaksi expense atau transfer sesuai jenis pergerakan kas, dengan relasi tambahan ke loan payment.

### transaction_lines

Menyimpan pergerakan akun.

Kolom:

- id
- transaction_id
- account_id
- direction
- amount
- created_at

Direction:

- in
- out

Interpretasi:

- in = uang masuk ke akun.
- out = uang keluar dari akun.

Amount harus selalu positif.

Saldo akun:

Initial balance + total in - total out

### categories

Menyimpan kategori income dan expense.

Kategori memiliki `category_type`:

- income
- expense

Kategori tidak boleh digunakan lintas tipe transaksi secara sembarangan.

### transfers

Menyimpan relasi akun sumber dan tujuan.

Kolom:

- id
- transaction_id
- source_account_id
- destination_account_id

Transfer harus menghasilkan dua transaction lines:

- Source account: out.
- Destination account: in.

Kedua pergerakan menggunakan nominal yang sama untuk v1.

Transfer tidak dihitung sebagai income atau expense.

### loans

Menyimpan pinjaman pribadi.

Kolom:

- id
- profile_id
- uuid
- lender_name
- principal_amount
- interest_amount
- borrowed_date
- due_date nullable
- status
- notes

Status:

- active
- partially_paid
- paid
- cancelled

### loan_payments

Menyimpan pembayaran pinjaman.

Kolom:

- id
- loan_id
- transaction_id
- payment_date
- principal_paid
- interest_paid
- notes

Satu pembayaran harus memiliki transaksi keuangan yang merepresentasikan uang keluar.

Principal dan interest dicatat secara terpisah.

Pembayaran pinjaman tidak boleh menghasilkan pengeluaran ganda.

Untuk laporan:

- Arus kas keluar mencerminkan seluruh pembayaran.
- Beban bunga dicatat sebagai expense.
- Pembayaran pokok mengurangi saldo pinjaman, bukan menjadi expense kedua.

### budgets

Menyimpan anggaran berdasarkan periode.

### budget_items

Menyimpan batas anggaran per kategori.

Hanya kategori expense yang boleh digunakan.

Actual spending dihitung dari transaksi expense yang valid pada periode tersebut.

Transfer dan pembayaran pokok pinjaman tidak masuk actual expense.

---

# 4. FINANCE BUSINESS RULES

1. Setiap transaksi income memiliki pergerakan uang masuk.
2. Setiap transaksi expense memiliki pergerakan uang keluar.
3. Transfer memiliki dua pergerakan akun.
4. Transfer tidak masuk laporan income atau expense.
5. Loan payment memiliki satu transaksi kas keluar.
6. Pembayaran pokok mengurangi outstanding principal.
7. Beban bunga masuk kategori expense.
8. Saldo akun dihitung dari initial balance dan ledger.
9. Transaksi tidak boleh dihapus jika masih memiliki relasi yang belum ditangani.
10. Semua operasi keuangan multi-tabel menggunakan database transaction.

## Outstanding Loan

Outstanding principal:

Principal amount - total principal paid

Outstanding interest:

Interest amount - total interest paid

Nilai outstanding tidak boleh negatif.

Jika pinjaman sudah lunas, status menjadi `paid`.

---

# 5. RECURRING TRANSACTION REVIEW

## 5.1 Recurring Templates

Tabel:

`recurring_transaction_templates`

Kolom:

- id
- profile_id
- uuid
- name
- transaction_type
- amount
- account_id
- category_id
- frequency
- interval_value
- start_date
- end_date nullable
- next_run_at
- behavior
- is_active
- created_at
- updated_at

Behavior:

- automatic
- reminder
- both

## 5.2 Idempotency

Setiap occurrence harus memiliki identitas unik.

Gunakan:

`recurring_occurrences`

Kolom:

- id
- template_id
- occurrence_key
- scheduled_at
- execution_status
- generated_transaction_id nullable
- generated_reminder_id nullable
- executed_at nullable

Unique constraint:

UNIQUE(template_id, occurrence_key)

Dengan demikian, satu occurrence tidak dapat diproses dua kali.

## 5.3 Execution Rules

1. Scheduled job mencari occurrence yang jatuh tempo.
2. Job membuat atau mengambil occurrence record.
3. Database menjamin occurrence tidak digandakan.
4. Jika behavior automatic, buat transaksi.
5. Jika behavior reminder, buat reminder.
6. Jika behavior both, buat keduanya.
7. Tandai hasil eksekusi.
8. Update next_run_at.

Semua proses harus aman jika job dijalankan ulang setelah gagal.

---

# 6. FIXED BILL REMINDER REVIEW

Tabel:

`bill_templates`

Kolom:

- id
- profile_id
- uuid
- name
- expected_amount nullable
- due_day nullable
- frequency
- start_date
- end_date nullable
- reminder_enabled
- is_active
- created_at
- updated_at

Tabel:

`bill_reminders`

Kolom:

- id
- profile_id
- bill_template_id
- occurrence_key
- due_date
- status
- completed_at nullable

Unique constraint:

UNIQUE(bill_template_id, occurrence_key)

Fixed bill reminder hanya membuat pengingat.

Transaksi pembayaran tetap dibuat manual oleh pengguna.

Satu reminder tidak otomatis mengubah saldo akun.

---

# 7. CALENDAR RECURRENCE REVIEW

## 7.1 calendar_events

Menyimpan event utama.

Kolom penting:

- id
- profile_id
- uuid
- title
- description
- event_type
- start_at
- end_at nullable
- all_day
- status
- recurrence_rule_id nullable
- created_at
- updated_at
- deleted_at

## 7.2 event_recurrence_rules

Menyimpan aturan pengulangan.

Kolom:

- id
- event_id
- frequency
- interval_value
- weekdays
- month_day nullable
- end_date nullable
- occurrence_count nullable

Frequency:

- daily
- weekly
- monthly
- yearly

## 7.3 event_occurrence_overrides

Menyimpan perubahan untuk satu occurrence tanpa mengubah seluruh series.

Kolom:

- id
- event_id
- original_start_at
- override_start_at nullable
- override_end_at nullable
- override_title nullable
- override_status nullable
- is_cancelled

Unique constraint:

UNIQUE(event_id, original_start_at)

Contoh:

Event setiap Senin pukul 09.00.

Pada satu minggu tertentu, pengguna mengubah waktunya menjadi 10.00.

Perubahan hanya berlaku untuk occurrence tersebut.

Jangan membuat event series baru untuk setiap occurrence.

---

# 8. SHIFT DATABASE DECISION

Gunakan `shifts` sebagai sumber data utama shift.

Kolom:

- id
- profile_id
- uuid
- shift_name
- shift_date
- start_at nullable
- end_at nullable
- notes
- created_at
- updated_at
- deleted_at

Calendar dapat menampilkan shift dengan mengambil data dari tabel shifts.

Jangan menyimpan shift yang sama secara independen di `calendar_events`.

Jika diperlukan integrasi, gunakan adapter atau calendar projection dari shift.

Dengan demikian tidak ada dua sumber data yang saling bertentangan.

---

# 9. TASK & PROJECT RELATIONSHIPS

## 9.1 Task Ownership

Satu task hanya dapat memiliki satu project pada v1.

Kolom:

`tasks.project_id`

Boleh null.

Task tanpa project tetap valid.

## 9.2 Task Schedule

Gunakan:

`task_schedule_links`

Kolom:

- id
- task_id
- calendar_event_id
- created_at

Satu task boleh memiliki banyak schedule sessions.

Satu schedule event tidak boleh terhubung ke beberapa task sekaligus pada v1.

Task dan calendar event tetap merupakan resource berbeda.

Event bertipe task tidak boleh menjadi duplikasi dari task utama.

## 9.3 Dependencies

Gunakan:

`task_dependencies`

Kolom:

- id
- task_id
- depends_on_task_id
- created_at

Constraint:

- Tidak boleh self-reference.
- Tidak boleh dependency duplikat.
- Harus berada dalam profile yang sama.
- Siklus dependency harus dicegah melalui service validation.

Dependency hanya menghasilkan warning.

Dependency tidak mengunci penyelesaian task.

## 9.4 Project Progress

Project progress dihitung dari task yang aktif dalam project.

Gunakan satu aturan perhitungan konsisten:

Average progress dari task aktif yang terhubung ke project.

Task yang sudah dihapus atau dibatalkan tidak masuk denominator.

Project yang tidak memiliki task memiliki progress 0%.

---

# 10. HABIT LOG DECISION

Gunakan satu ringkasan log per habit per tanggal.

Tabel:

`habit_logs`

Kolom:

- id
- habit_id
- log_date
- completion_status
- quantity_value nullable
- duration_minutes nullable
- notes
- created_at
- updated_at

Unique constraint:

UNIQUE(habit_id, log_date)

Jika habit membutuhkan checklist dengan banyak item, gunakan tabel:

`habit_log_items`

Kolom:

- id
- habit_log_id
- habit_item_id
- is_completed
- quantity_value nullable

Habit streak dihitung berdasarkan jadwal habit.

Rest day:

- Tidak dihitung sebagai missed day.
- Tidak masuk denominator completion.
- Tidak memutus streak.

---

# 11. GOAL LINKS

Tabel:

`goal_links`

Kolom:

- id
- goal_id
- linked_entity_type
- linked_entity_id
- contribution_weight nullable
- created_at

Entity types:

- project
- task
- habit
- finance

Karena relasi bersifat polymorphic, foreign key database tidak dapat menjamin keberadaan seluruh target.

Service wajib:

1. Memvalidasi entity type.
2. Memvalidasi target ID.
3. Memvalidasi profile ownership.
4. Memastikan target tidak dihapus.
5. Mencegah duplicate link.

Jika target dihapus, link harus ditangani melalui soft-delete policy.

---

# 12. TAGS & ATTACHMENTS

## 12.1 Tags

Gunakan:

- tags
- entity_tags

`entity_tags` adalah polymorphic relation.

Service harus memvalidasi entity type dan entity ID.

Tag tidak boleh menghubungkan data antar-profile.

## 12.2 Attachments

Gunakan:

- attachments
- entity_attachments

Attachment metadata harus menyimpan:

- original_filename
- stored_filename
- storage_path
- mime_type
- extension
- file_size
- checksum
- upload_status

File fisik berada di luar public web root.

Penghapusan resource tidak boleh langsung menghapus file jika resource masih dapat di-restore.

---

# 13. TRASH & RESTORE DECISION

Jangan menggunakan snapshot `original_data` sebagai satu-satunya sumber pemulihan.

Sumber data utama tetap record asli yang menggunakan soft delete.

Gunakan tabel:

`trash_items`

Kolom:

- id
- profile_id
- entity_type
- entity_id
- deleted_at
- deleted_by nullable
- restore_status
- metadata JSON nullable

Metadata hanya berisi informasi tambahan yang diperlukan.

## Restore Rules

1. Pastikan record masih tersedia.
2. Periksa relasi parent.
3. Periksa unique constraints.
4. Periksa konflik data.
5. Pulihkan record.
6. Pulihkan attachment jika relevan.
7. Catat restore history.

Permanent deletion hanya boleh dilakukan jika seluruh dependency sudah ditangani.

---

# 14. NOTIFICATION & REMINDER DEDUPLICATION

Gunakan tabel:

`notifications`

dan:

`notification_deliveries`

Setiap notification harus memiliki sumber yang dapat diidentifikasi.

Unique key dapat menggunakan:

- profile_id
- source_type
- source_id
- occurrence_key
- notification_type

Tujuannya mencegah reminder yang sama dibuat berulang.

Delivery status:

- pending
- processing
- sent
- failed
- cancelled

Scheduled job harus memiliki mekanisme claim/locking agar dua proses tidak mengirim notification yang sama secara bersamaan.

Browser notification dan in-app notification harus dianggap sebagai delivery channel berbeda.

---

# 15. IMPORT JOBS & STAGING

Gunakan:

`import_jobs`

Kolom:

- id
- profile_id
- uuid
- import_type
- source_filename
- status
- total_rows
- valid_rows
- invalid_rows
- created_at
- completed_at nullable

Gunakan:

`import_job_rows`

Kolom:

- id
- import_job_id
- row_number
- raw_data JSON
- normalized_data JSON nullable
- validation_errors JSON nullable
- processing_status

Flow:

1. Upload.
2. Parse.
3. Normalize.
4. Validate.
5. Save staging rows.
6. Display preview.
7. Confirm.
8. Commit valid rows.
9. Mark import job completed.

Commit harus menggunakan database transaction.

---

# 16. PUBLIC HOLIDAYS

Official public holiday data dapat menjadi data referensi global.

Manual holiday entries harus memiliki informasi sumber.

Kolom yang disarankan:

- id
- holiday_date
- name
- source_type
- external_reference nullable
- is_active
- created_at
- updated_at

Source type:

- official_api
- manual_import
- manual_entry

Hindari duplicate saat sinkronisasi.

Gunakan normalized identity berdasarkan tanggal, sumber, dan identifier eksternal jika tersedia.

Data manual tidak boleh tertimpa otomatis oleh sinkronisasi online tanpa aturan konflik.

---

# 17. DATABASE CONSTRAINTS

Implementasikan constraint berikut:

- Foreign keys.
- Unique constraints.
- NOT NULL.
- CHECK constraints jika didukung dan sesuai.
- Indexes untuk filter umum.
- Indexes untuk foreign keys.
- Indexes untuk profile ownership.
- Indexes untuk tanggal transaksi dan jadwal.

Contoh unique constraints:

- accounts: profile_id + normalized_name
- categories: profile_id + normalized_name + category_type
- recurring_occurrences: template_id + occurrence_key
- bill_reminders: bill_template_id + occurrence_key
- habit_logs: habit_id + log_date
- event_occurrence_overrides: event_id + original_start_at

Nama dan definisi constraint harus disesuaikan dengan aturan soft delete dan kebutuhan data historis.

---

# 18. INDEXING STRATEGY

Prioritaskan index pada:

- profile_id.
- uuid.
- transaction_date.
- account_id.
- category_id.
- project_id.
- task status.
- task deadline.
- event start_at.
- habit log_date.
- notification status.
- recurring next_run_at.
- deleted_at.

Hindari membuat index berlebihan tanpa kebutuhan query.

Validasi menggunakan EXPLAIN untuk query yang berpotensi berat.

---

# 19. RECOMMENDED MIGRATION ORDER

1. profiles
2. categories
3. accounts
4. projects
5. goals
6. tags
7. tasks
8. task_dependencies
9. task_status_history
10. transactions
11. transaction_lines
12. transfers
13. loans
14. loan_payments
15. budgets
16. budget_items
17. recurring_transaction_templates
18. recurring_occurrences
19. bill_templates
20. bill_reminders
21. calendar_events
22. event_recurrence_rules
23. event_occurrence_overrides
24. shifts
25. task_schedule_links
26. habits
27. habit_items
28. habit_logs
29. habit_log_items
30. journal_entries
31. journal_moods
32. notes
33. note_folders
34. attachments
35. entity_attachments
36. entity_tags
37. goal_links
38. notifications
39. notification_deliveries
40. trash_items
41. change_logs
42. import_jobs
43. import_job_rows
44. public_holidays

Urutan final harus mengikuti dependency foreign key yang benar.

---

# 20. DATABASE ACCEPTANCE CRITERIA

Database siap masuk tahap SQL migration jika:

1. Seluruh tabel memiliki ownership yang jelas.
2. Semua relasi memiliki aturan foreign key atau validasi service.
3. Saldo akun dapat dihitung konsisten.
4. Transfer tidak menggandakan income/expense.
5. Loan repayment tidak menghitung principal sebagai expense.
6. Recurring execution idempotent.
7. Calendar recurrence mendukung exception.
8. Shift memiliki satu sumber data.
9. Task-project relationship konsisten.
10. Habit streak menghormati rest day.
11. Trash mendukung restore.
12. Attachment tidak hilang saat soft delete.
13. Notification tidak terduplikasi.
14. Import memiliki preview dan staging.
15. Seluruh operasi multi-tabel penting bersifat atomic.

---

# 21. FINAL DESIGN PRINCIPLE

Database LIFEOS harus mengutamakan:

- Data integrity.
- Predictable relationships.
- Atomic operations.
- Idempotent scheduled jobs.
- Consistent ownership.
- Recoverable deletion.
- Auditable financial changes.
- Maintainable schema.

Jangan menambahkan kompleksitas enterprise yang tidak dibutuhkan v1.

Namun jangan menyederhanakan bagian yang berpotensi merusak integritas data.

---

# END OF DATABASE REVIEW