# LIFEOS DOMAIN RULES

**Project:** LIFEOS  
**Version:** 1.0  
**Status:** Business Logic Specification  
**Scope:** All Release 1 Modules

---

# 1. PURPOSE

Dokumen ini menjadi sumber kebenaran untuk seluruh aturan bisnis LIFEOS.

Semua aturan dalam dokumen ini harus diterapkan secara konsisten pada:

- Controllers.
- Services.
- Repositories.
- Scheduled jobs.
- Frontend interactions.
- API validation.
- Automated tests.

Business rules tidak boleh hanya diterapkan di frontend.

Backend Service merupakan sumber kebenaran utama.

---

# 2. GLOBAL DOMAIN RULES

## 2.1 Profile Ownership

- Semua data personal dimiliki satu profile.
- Setiap operasi wajib memvalidasi ownership.
- Data antar-profile tidak boleh tercampur.
- V1 hanya menggunakan satu profile lokal.

## 2.2 Soft Delete

- Penghapusan default menggunakan soft delete.
- Resource terhapus tidak tampil dalam daftar normal.
- Restore harus memeriksa konflik.
- Permanent delete memerlukan konfirmasi.
- History tidak boleh hilang akibat soft delete.

## 2.3 Data Consistency

- Operasi multi-tabel harus atomic.
- Scheduled jobs harus idempotent.
- Duplicate execution harus dicegah.
- Semua nominal keuangan menggunakan DECIMAL.
- Perubahan penting harus tercatat.

## 2.4 Timezone

- Penyimpanan timestamp menggunakan UTC.
- Tampilan menggunakan Asia/Jakarta.
- Tanggal kalender menggunakan DATE.
- Konversi timezone harus dilakukan secara konsisten.

---

# 3. FINANCE DOMAIN

## 3.1 Transaction Types

Jenis transaksi:

- Income.
- Expense.
- Transfer.

### Income

- Menambah saldo akun.
- Memerlukan minimal satu pergerakan uang masuk.
- Dapat memiliki kategori income.

### Expense

- Mengurangi saldo akun.
- Memerlukan minimal satu pergerakan uang keluar.
- Dapat memiliki kategori expense.

### Transfer

- Memindahkan uang antar-akun internal.
- Memerlukan satu akun sumber dan satu akun tujuan.
- Tidak boleh menggunakan akun yang sama.
- Memerlukan dua ledger movements.
- Tidak dihitung sebagai income atau expense.

## 3.2 Account Balance

Saldo dihitung:

Initial Balance + Total Incoming - Total Outgoing

Saldo tidak boleh diperbarui secara independen dari ledger.

Account yang diarsipkan tetap mempertahankan transaksi historis.

Account yang memiliki transaksi tidak boleh dihapus secara fisik melalui operasi normal.

## 3.3 Categories

- Income hanya menggunakan kategori income.
- Expense hanya menggunakan kategori expense.
- Transfer tidak membutuhkan kategori income atau expense.
- Kategori yang sudah digunakan tidak boleh dihapus secara fisik sembarangan.

## 3.4 Budget

Actual expense dihitung dari transaksi expense yang valid.

Tidak termasuk:

- Transfer.
- Pembayaran pokok pinjaman.
- Transaksi yang telah di-soft-delete.

Beban bunga pinjaman dapat masuk actual expense.

Budget tidak boleh memiliki periode dengan tanggal akhir sebelum tanggal awal.

## 3.5 Personal Loans

- Satu pinjaman memiliki satu lender.
- Due date bersifat opsional.
- Pembayaran dapat dilakukan dalam beberapa kali pembayaran.
- Principal dan interest dicatat terpisah.
- Pembayaran harus memiliki transaksi kas keluar.
- Principal mengurangi outstanding principal.
- Interest menjadi beban expense.
- Total pembayaran tidak boleh melebihi kewajiban yang tersisa.
- Pinjaman lunas memiliki status paid.

Outstanding principal:

Principal Amount - Total Principal Paid

Outstanding interest:

Interest Amount - Total Interest Paid

## 3.6 Recurring Transactions

Behavior:

- automatic.
- reminder.
- both.

Setiap occurrence hanya boleh diproses satu kali.

Recurring transaction tidak boleh menghasilkan transaksi duplikat ketika scheduled job dijalankan ulang.

Jika template dinonaktifkan, occurrence berikutnya tidak boleh dibuat.

Transaksi yang sudah dihasilkan tetap dipertahankan.

## 3.7 Fixed Bills

- Template hanya menghasilkan reminder.
- Pembayaran dimasukkan manual.
- Reminder tidak mengubah saldo akun.
- Reminder dapat ditandai selesai.
- Template dapat diarsipkan.
- History reminder tetap dipertahankan.

---

# 4. SCHEDULE DOMAIN

## 4.1 Calendar Views

Tampilan:

- Month.
- Week.
- Day.
- Agenda.

Aturan:

- Minggu dimulai hari Senin.
- Waktu ditampilkan dalam format 12 jam.
- Timezone Asia/Jakarta.
- Event dapat dibuat manual.
- Event dapat diulang.
- Event dapat diubah atau dibatalkan per occurrence.

## 4.2 Recurring Events

Frequency:

- Daily.
- Weekly.
- Monthly.
- Yearly.

Perubahan satu occurrence tidak boleh mengubah seluruh series kecuali pengguna memilih edit entire series.

## 4.3 Reminders

Pilihan:

- At event time.
- 5 minutes.
- 15 minutes.
- 30 minutes.
- 1 hour.
- 1 day.
- Custom.

Reminder yang sama tidak boleh dibuat berulang.

Browser notification membutuhkan permission.

Jika permission tidak tersedia, gunakan in-app notification.

## 4.4 Shifts

- Shift dibuat manual.
- Shift memiliki nama.
- Shift memiliki tanggal.
- Start dan end time bersifat opsional.
- Shift menjadi sumber data utama jadwal kerja.
- Shift tidak diduplikasi menjadi calendar event tersendiri.

## 4.5 Public Holidays

- Data dapat disinkronkan dari sumber online.
- Data disimpan dalam cache lokal.
- Pengguna dapat mengimpor data manual.
- Data manual tidak boleh tertimpa otomatis tanpa aturan konflik.

---

# 5. TASK DOMAIN

## 5.1 Task Status

Status:

- Not started.
- In progress.
- On hold.
- Completed.
- Cancelled.

Progress berada dalam rentang 0–100.

Task completed harus memiliki progress 100%.

Jika task dibuka kembali, status dan progress harus disesuaikan melalui service.

## 5.2 Priority

Priority:

- Low.
- Medium.
- High.
- Urgent.

## 5.3 Subtasks

- Task dapat memiliki parent task.
- Subtask dapat memiliki subtask lain.
- Parent dan child harus berada dalam profile yang sama.
- Circular parent relationship dilarang.
- Parent progress dihitung dari rata-rata subtasks aktif.
- Parent tanpa subtask menggunakan progress manual.

## 5.4 Dependencies

- Task dapat memiliki prerequisite.
- Self-dependency dilarang.
- Circular dependency dilarang.
- Dependency hanya menghasilkan warning.
- Dependency tidak mengunci penyelesaian task.

## 5.5 Schedule Sessions

- Satu task dapat memiliki beberapa schedule sessions.
- Schedule event dan task merupakan resource berbeda.
- Schedule session dapat dihapus tanpa menghapus task.

## 5.6 Time Tracking

- Time tracking dilakukan manual.
- Timer yang aktif harus teridentifikasi.
- Satu task tidak boleh memiliki beberapa timer aktif secara tidak sengaja.
- Duration dihitung berdasarkan waktu mulai dan selesai.
- Data time log yang sudah selesai tidak boleh berubah tanpa pencatatan history.

## 5.7 Task History

Perubahan penting mencakup:

- Status.
- Progress.
- Priority.
- Deadline.
- Project assignment.
- Parent assignment.

---

# 6. HABIT DOMAIN

## 6.1 Tracking Types

- Checklist.
- Quantitative.
- Duration.

## 6.2 Schedule

Habit dapat dijadwalkan:

- Daily.
- Specific weekdays.
- Custom weekly schedule.

Streak dihitung berdasarkan hari yang memang dijadwalkan.

## 6.3 Completion

Habit completion ditentukan oleh tracking type.

Checklist:

Semua item wajib selesai untuk status complete.

Quantitative:

Nilai aktual harus mencapai target.

Duration:

Durasi aktual harus mencapai target.

## 6.4 Rest Days

Rest day:

- Tidak masuk denominator completion.
- Tidak dihitung missed.
- Tidak memutus streak.

## 6.5 Statistics

Statistik:

- Current streak.
- Longest streak.
- Completion percentage.
- Missed days.
- Weekly summary.
- Monthly summary.

Hari yang belum dijadwalkan tidak boleh dianggap missed.

---

# 7. JOURNAL DOMAIN

## 7.1 Journal Entries

- Satu tanggal dapat memiliki banyak journal entries.
- Entry dapat memiliki title.
- Entry dapat memiliki konten panjang.
- Entry dapat memiliki mood.
- Entry dapat memiliki template.
- Entry dapat dicari berdasarkan tanggal, mood, dan kategori.

## 7.2 Mood

Pilihan mood:

- Happy.
- Neutral.
- Sad.
- Angry.
- Anxious.
- Excited.
- Tired.

Mood tidak boleh menjadi pengganti isi jurnal.

## 7.3 Editing

- Manual save.
- Unsaved changes warning.
- Soft delete.
- Restore.
- History perubahan.

---

# 8. NOTES DOMAIN

- Notes dapat berada dalam folder.
- Notes dapat berdiri tanpa folder.
- Folder dapat memiliki parent folder.
- Folder tidak boleh circular.
- Notes dapat dipin.
- Notes dapat diarsipkan.
- Notes dapat memiliki tags.
- Notes dapat memiliki attachments.
- Konten mendukung Markdown dan rich text.
- Manual save.
- Unsaved changes warning.

---

# 9. GOALS DOMAIN

## 9.1 Goal Types

- Daily.
- Weekly.
- Monthly.
- Yearly.
- Long-term.

## 9.2 Progress Modes

Setiap goal memilih salah satu:

### Linked Items

Progress dihitung dari item terkait.

### Measurable Target

Progress dihitung berdasarkan nilai aktual dan target.

Progress tidak boleh melebihi 100% pada tampilan normal.

Nilai aktual boleh disimpan sesuai kebutuhan pengukuran, tetapi persentase tampilan dibatasi 0–100%.

## 9.3 Relationships

Goal dapat terhubung dengan:

- Project.
- Task.
- Habit.
- Finance.

Target yang dihapus harus ditangani dengan aturan orphan reference.

---

# 10. PROJECT DOMAIN

## 10.1 Project Types

- Personal.
- Professional.

## 10.2 Project Status

- Not started.
- Planning.
- In progress.
- On hold.
- Completed.
- Cancelled.

## 10.3 Progress

Progress project dihitung dari task aktif.

Task completed bernilai 100%.

Task cancelled dan deleted tidak masuk denominator.

Project tanpa task memiliki progress 0%.

## 10.4 Budget

Project budget terdiri dari:

- Planned budget.
- Actual budget.

Actual budget dihitung dari transaksi expense yang terhubung ke project.

Transfer tidak dihitung sebagai actual expense.

---

# 11. REPORTS DOMAIN

Report periods:

- Daily.
- Weekly.
- Monthly.
- Quarterly.
- Yearly.
- Custom range.

Report categories:

- Finance.
- Income versus expense.
- Budget.
- Task completion.
- Habit statistics.
- Project progress.
- Goal progress.
- Time tracking.
- Annual summary.

Semua laporan harus menggunakan sumber data utama yang sama dengan modul terkait.

Report tidak boleh menghitung ulang aturan bisnis dengan logika yang berbeda.

---

# 12. DASHBOARD DOMAIN

Dashboard menampilkan:

- Finance summary.
- Today's schedule.
- Upcoming tasks.
- Habit progress.
- Project progress.
- Quick notes.
- Goals overview.
- Recent journal entries.
- Upcoming bills.
- Loan installments.
- Monthly analytics.

Dashboard hanya merupakan agregasi data.

Dashboard tidak menjadi sumber data utama.

Widget yang gagal dimuat tidak boleh menyebabkan seluruh dashboard gagal.

---

# 13. IMPORT & JSON BRIDGE DOMAIN

## Import

- Upload.
- Parse.
- Validate.
- Preview.
- Confirm.
- Commit.

Data invalid harus ditampilkan dengan alasan yang jelas.

## ChatGPT Bridge

- JSON dihasilkan melalui prompt manual.
- Pengguna menyalin prompt ke ChatGPT.
- ChatGPT mengembalikan JSON.
- Pengguna memasukkan JSON ke LIFEOS.
- Backend memvalidasi payload.
- LIFEOS menampilkan preview.
- Pengguna mengonfirmasi perubahan.

Tidak ada AI API atau direct database access.

---

# 14. CROSS-MODULE RULES

## Finance ↔ Projects

Transaksi expense dapat dihubungkan ke satu project.

## Tasks ↔ Projects

Task dapat memiliki satu project.

## Tasks ↔ Schedule

Task dapat memiliki banyak schedule sessions.

## Goals ↔ Projects/Tasks/Habits/Finance

Goal dapat menghubungkan resource yang relevan.

## Habits ↔ Dashboard/Reports

Habit statistics digunakan oleh dashboard dan reports.

## Journal ↔ Dashboard

Dashboard menampilkan recent journal entries tanpa mengubah data jurnal.

## Attachments ↔ Resources

Attachment dapat terhubung ke resource yang diizinkan melalui entity attachment service.

Semua cross-module relationship harus menjaga profile ownership.

---

# 15. DOMAIN ACCEPTANCE CRITERIA

Domain rules dianggap siap jika:

1. Semua status dan enum sudah ditetapkan.
2. Semua perhitungan memiliki satu definisi.
3. Tidak ada aturan bisnis yang hanya berada di frontend.
4. Finance tidak menghasilkan perhitungan ganda.
5. Recurring jobs idempotent.
6. Calendar recurrence memiliki aturan exception.
7. Task dependencies bebas cycle.
8. Habit streak mengikuti schedule.
9. Goal progress menggunakan satu mode.
10. Project progress konsisten.
11. Reports menggunakan sumber data utama.
12. Dashboard tidak menjadi sumber data utama.
13. Cross-module ownership selalu divalidasi.

---

# END OF DOMAIN RULES