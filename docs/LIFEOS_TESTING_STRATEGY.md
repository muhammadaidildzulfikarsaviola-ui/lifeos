# LIFEOS TESTING STRATEGY

**Project:** LIFEOS  
**Version:** 1.0  
**Status:** Quality Assurance Specification  
**Backend:** PHP Native 8.2  
**Database:** MySQL 8.0+  
**Frontend:** Vanilla JavaScript + Alpine.js  
**Environment:** Windows 11 + XAMPP

---

# 1. PURPOSE

Dokumen ini menetapkan strategi pengujian LIFEOS sebelum dan selama implementasi.

Tujuan:

- Memastikan seluruh modul berfungsi.
- Memastikan business rules diterapkan dengan benar.
- Menjaga integritas database.
- Mencegah regresi.
- Memastikan keamanan aplikasi.
- Memastikan pengalaman pengguna konsisten.
- Memastikan backup dan restore dapat diandalkan.

Testing bukan aktivitas yang hanya dilakukan setelah semua modul selesai.

Testing harus menjadi bagian dari setiap tahap development.

---

# 2. TESTING LEVELS

LIFEOS menggunakan lima level pengujian.

| Level | Purpose |
|---|---|
| Unit Testing | Menguji fungsi dan business logic terisolasi |
| Integration Testing | Menguji interaksi service, repository, dan database |
| API Testing | Menguji endpoint dan response contract |
| UI Testing | Menguji interaksi frontend |
| System Testing | Menguji keseluruhan aplikasi |

---

# 3. TESTING ENVIRONMENT

## 3.1 Development Environment

- Windows 11.
- PHP 8.2.
- MySQL 8.0+.
- Apache melalui XAMPP.
- Composer.
- Chrome.
- Edge.
- Brave.

## 3.2 Database Environment

Gunakan database testing terpisah.

Contoh:

lifeos
lifeos_test

Dilarang menjalankan automated tests yang dapat mengubah database development atau data pribadi pengguna.

Testing database harus dapat dibuat ulang dari kondisi kosong.

## 3.3 Test Data

Gunakan data dummy.

Jangan menggunakan data keuangan atau jurnal pribadi untuk automated tests.

Setiap test harus menyiapkan data yang dibutuhkan dan membersihkannya setelah selesai.

---

# 4. AUTOMATED TESTING STACK

Recommended:

- PHPUnit untuk PHP unit dan integration testing.
- Mockery jika diperlukan untuk mock dependencies.
- Browser automation menggunakan Playwright.
- JavaScript tests menggunakan Vitest jika kompleksitas frontend membutuhkannya.

Tidak semua library harus dipasang sekaligus.

Prioritaskan PHPUnit terlebih dahulu.

---

# 5. UNIT TESTING

Unit tests menguji business logic tanpa ketergantungan langsung pada database atau HTTP.

## 5.1 Finance

Test cases:

- Income menambah saldo.
- Expense mengurangi saldo.
- Transfer memindahkan saldo.
- Transfer tidak dihitung sebagai income.
- Transfer tidak dihitung sebagai expense.
- Nominal negatif ditolak.
- Principal payment mengurangi outstanding principal.
- Interest payment mengurangi outstanding interest.
- Loan tidak dapat dibayar melebihi kewajiban.
- Budget actual tidak menghitung transfer.
- Budget actual tidak menghitung principal payment.
- Recurring occurrence menghasilkan identitas unik.

## 5.2 Tasks

- Progress berada pada 0–100.
- Completed task memiliki progress 100.
- Parent progress dihitung dari subtasks aktif.
- Cancelled task tidak masuk denominator.
- Circular dependency ditolak.
- Self-dependency ditolak.
- Dependency warning muncul.
- Task tanpa project tetap valid.

## 5.3 Habits

- Daily schedule dihitung benar.
- Weekly schedule dihitung benar.
- Rest day tidak dihitung missed.
- Rest day tidak memutus streak.
- Checklist completion dihitung benar.
- Quantitative target dihitung benar.
- Duration target dihitung benar.
- Hari yang tidak dijadwalkan tidak masuk missed days.

## 5.4 Goals

- Linked items progress dihitung benar.
- Measurable target progress dihitung benar.
- Progress tampilan tidak melebihi 100%.
- Goal tanpa linked items tidak menyebabkan division by zero.

## 5.5 Projects

- Project progress berdasarkan task aktif.
- Cancelled task tidak masuk denominator.
- Deleted task tidak masuk denominator.
- Project tanpa task memiliki progress 0%.
- Actual budget menghitung expense terkait project saja.

## 5.6 Calendar

- Recurring daily event.
- Recurring weekly event.
- Recurring monthly event.
- Recurring yearly event.
- Occurrence override tidak mengubah series.
- Cancelled occurrence tidak ditampilkan.
- Timezone conversion konsisten.

---

# 6. INTEGRATION TESTING

Integration tests menguji interaksi antar-komponen.

## 6.1 Database

- Repository dapat membuat resource.
- Repository dapat membaca resource.
- Repository dapat memperbarui resource.
- Repository dapat melakukan soft delete.
- Foreign key bekerja.
- Unique constraints bekerja.
- Transaction rollback bekerja.
- Profile ownership diterapkan.

## 6.2 Finance Integration

Skenario:

1. Buat dua akun.
2. Buat transaksi income.
3. Verifikasi saldo akun pertama.
4. Buat transaksi expense.
5. Verifikasi saldo akun pertama.
6. Buat transfer.
7. Verifikasi saldo kedua akun.
8. Pastikan transfer tidak masuk laporan income/expense.

## 6.3 Loan Integration

Skenario:

1. Buat pinjaman.
2. Catat pembayaran sebagian.
3. Buat transaksi kas keluar.
4. Alokasikan principal dan interest.
5. Verifikasi saldo akun.
6. Verifikasi outstanding principal.
7. Verifikasi outstanding interest.
8. Verifikasi laporan expense hanya menghitung bunga.

## 6.4 Cross-Module Integration

- Task dapat dihubungkan ke project.
- Task dapat dijadwalkan.
- Goal dapat menghubungkan task.
- Goal dapat menghubungkan project.
- Project dapat menerima transaksi expense.
- Habit statistics dapat ditampilkan di dashboard.
- Journal entries dapat ditampilkan di dashboard.
- Attachment dapat dihubungkan ke notes.
- Trash dapat memulihkan resource dan relasinya.

---

# 7. API TESTING

Semua endpoint harus diuji berdasarkan API Contract.

## Required Tests

- HTTP method benar.
- Response status benar.
- Response envelope konsisten.
- JSON parsing.
- Required fields.
- Invalid field types.
- Missing CSRF.
- Invalid CSRF.
- Invalid foreign key.
- Wrong profile ownership.
- Missing resource.
- Duplicate operation.
- Pagination.
- Search.
- Sorting whitelist.
- Date filtering.
- Unexpected server error.

## API Contract Assertions

Success response harus memiliki:

- success.
- message.
- data.
- meta.

Error response harus memiliki:

- success.
- message.
- data.
- errors atau error metadata yang sesuai.

Jangan membocorkan exception internal.

---

# 8. FRONTEND TESTING

Pengujian frontend mencakup:

## Layout

- Sidebar.
- Topbar.
- Dashboard widgets.
- Modal.
- Toast.
- Dropdown.
- Table.
- Form.
- Calendar.
- Kanban.

## Interactions

- Create.
- Edit.
- Delete.
- Restore.
- Search.
- Filter.
- Sorting.
- Pagination.
- Drag and drop.
- Keyboard shortcuts.
- Global search.
- Unsaved changes warning.

## UI States

Setiap halaman data harus menangani:

- Initial loading.
- Empty state.
- Loaded state.
- Validation error.
- Network error.
- Permission error.
- Saving state.
- Success state.

Jangan membiarkan tombol terlihat aktif jika fungsinya belum tersedia.

---

# 9. RESPONSIVE TESTING

Target viewport:

| Device | Width |
|---|---:|
| Desktop | 1440px |
| Laptop | 1366px |
| Tablet | 768px |
| Mobile | 390px |
| Small Mobile | 320px |

Pengujian:

- No horizontal overflow.
- Sidebar behavior.
- Table responsiveness.
- Modal responsiveness.
- Form usability.
- Calendar readability.
- Touch target size.
- Navigation usability.

Desktop menjadi prioritas utama, tetapi fungsi inti tetap harus dapat digunakan di mobile.

---

# 10. SECURITY TESTING

Mengacu pada LIFEOS_SECURITY_SPECIFICATION.md.

Required:

- SQL injection prevention.
- XSS prevention.
- CSRF protection.
- Mass assignment prevention.
- Profile ownership.
- Path traversal prevention.
- MIME validation.
- File size validation.
- Safe download.
- JSON bridge validation.
- Backup file protection.
- Error disclosure prevention.

Gunakan payload uji yang aman dan tidak merusak data.

---

# 11. SCHEDULED JOB TESTING

Scheduled jobs harus diuji menggunakan PHP CLI.

Test cases:

- Job berjalan ketika dijadwalkan.
- Job tidak berjalan dua kali untuk occurrence yang sama.
- Job aman dijalankan ulang.
- Lock mencegah concurrent execution.
- Failed job dapat dicoba kembali.
- Retry tidak menghasilkan duplicate notification.
- Recurring template nonaktif tidak menghasilkan occurrence baru.

Jangan menggunakan browser sebagai satu-satunya mekanisme pengujian scheduled jobs.

---

# 12. BACKUP & RESTORE TESTING

## Backup

- Backup database berhasil.
- Backup attachment berhasil.
- Backup metadata konsisten.
- Backup tidak dapat diakses publik.
- Backup memiliki identifier dan timestamp.
- Backup dapat divalidasi.

## Restore

- Restore dari backup valid.
- Restore dari backup rusak ditolak.
- Restore versi tidak kompatibel ditolak.
- Restore preview menampilkan ringkasan.
- Backup kondisi sebelum restore dibuat.
- Restore tidak meninggalkan data setengah pulih.

## Recovery Drill

Lakukan pengujian pemulihan dari database kosong.

Targetnya adalah membuktikan aplikasi dapat dipulihkan dari backup, bukan sekadar berhasil membuat file backup.

---

# 13. PERFORMANCE TESTING

Target awal, bukan jaminan universal:

| Operation | Target |
|---|---:|
| Standard page load | Under 2 seconds |
| Common API request | Under 500 ms |
| Dashboard summary | Under 1 second |
| Search response | Under 500 ms |
| Database migration | Under 30 seconds |

Pengukuran dilakukan pada perangkat development yang sama dan menggunakan dataset pengujian yang tercatat.

Uji dataset:

- 100 tasks.
- 1,000 tasks.
- 10,000 tasks.
- 10,000 transactions.
- 1,000 journal entries.
- 1,000 notifications.

Gunakan EXPLAIN untuk query database yang lambat.

Hindari optimasi prematur sebelum profiling.

---

# 14. REGRESSION TESTING

Setiap perubahan harus menjalankan test yang relevan.

Regression testing wajib setelah:

- Database migration.
- Finance Service changes.
- Task progress changes.
- Calendar recurrence changes.
- Habit streak changes.
- Backup/restore changes.
- Shared frontend component changes.
- API response changes.

Perubahan pada satu modul tidak boleh merusak modul lain.

---

# 15. TEST DATA MANAGEMENT

Gunakan factory atau fixture untuk membuat data dummy.

Data harus memiliki skenario:

- Normal.
- Empty.
- Boundary values.
- Invalid values.
- Duplicate.
- Soft-deleted.
- Cross-profile.
- Historical.

Seed data development tidak boleh otomatis masuk ke database produksi atau database pribadi.

---

# 16. BUG SEVERITY

| Severity | Definition |
|---|---|
| Critical | Data loss, financial corruption, security breach |
| High | Core functionality unusable |
| Medium | Important feature malfunction |
| Low | Minor visual or interaction issue |

Critical dan High harus diselesaikan sebelum release.

Medium harus memiliki keputusan eksplisit sebelum release.

Low dapat dimasukkan ke backlog.

---

# 17. RELEASE QUALITY GATES

Release tidak boleh dilanjutkan jika:

1. Critical tests gagal.
2. High severity bugs masih terbuka.
3. Finance ledger tidak konsisten.
4. Backup/restore belum diuji.
5. Migration gagal dari database kosong.
6. API contract tidak konsisten.
7. Security baseline gagal.
8. Modul inti memiliki tombol tanpa fungsi.
9. Data antar-profile tercampur.
10. Ada regresi pada fungsi inti.

---

# 18. TEST REPORT FORMAT

Setiap pengujian release harus mencatat:

- Test run ID.
- Date and time.
- Application version.
- Database migration version.
- Environment.
- Test suite.
- Total tests.
- Passed.
- Failed.
- Skipped.
- Critical issues.
- High issues.
- Release recommendation.

Release recommendation:

- Approved.
- Approved with known issues.
- Rejected.

---

# 19. FINAL TESTING PRINCIPLES

1. Test business logic before UI polish.
2. Test financial integrity before finance release.
3. Test database migration before module implementation.
4. Test restore, not just backup creation.
5. Test recurring jobs for duplicate execution.
6. Test ownership across all modules.
7. Automate repeatable tests.
8. Keep manual test scenarios documented.
9. Run regression tests after shared changes.
10. Never use personal data as disposable test data.

---

# END OF TESTING STRATEGY