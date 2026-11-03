# LIFEOS SECURITY SPECIFICATION

**Project:** LIFEOS  
**Version:** 1.0  
**Status:** Technical Specification  
**Backend:** PHP Native 8.2  
**Database:** MySQL 8.0+  
**Environment:** Windows + XAMPP  
**Deployment:** Local-first application  
**Authentication:** Disabled in v1  
**Timezone:** Asia/Jakarta

---

# 1. SECURITY OBJECTIVES

Keamanan LIFEOS dirancang untuk melindungi:

- Data keuangan pribadi.
- Catatan dan jurnal.
- Informasi pekerjaan dan proyek.
- File attachment.
- Database dan backup.
- Operasi import dan restore.
- Endpoint internal aplikasi.

Prinsip utama:

1. Defense in depth.
2. Least privilege.
3. Server-side validation.
4. Secure defaults.
5. Data integrity.
6. Safe file handling.
7. Explicit confirmation for destructive actions.

Tidak menggunakan login pada v1 bukan berarti aplikasi memiliki perlindungan akses pengguna. Siapa pun yang memiliki akses ke komputer dan aplikasi lokal berpotensi mengakses data LIFEOS.

---

# 2. LOCAL ENVIRONMENT SECURITY

## 2.1 Network Binding

Apache harus berjalan pada localhost untuk deployment pribadi.

Recommended binding:

127.0.0.1

Jangan membuka LIFEOS ke jaringan publik atau LAN secara default.

Jika menggunakan Virtual Host:

- DocumentRoot diarahkan ke folder `public`.
- Directory listing dinonaktifkan.
- Internal application folders tidak boleh dapat diakses melalui HTTP.
- phpMyAdmin tidak boleh diekspos ke jaringan publik.

## 2.2 Apache Configuration

Recommended:

- AllowOverride sesuai kebutuhan aplikasi.
- Options -Indexes.
- Nonaktifkan directory browsing.
- Batasi akses folder sensitif.
- Gunakan HTTPS apabila aplikasi nantinya diakses melalui jaringan selain localhost.

## 2.3 PHP Configuration

Recommended production-like settings:

display_errors = Off
log_errors = On
expose_php = Off

Konfigurasi development boleh menampilkan error secara lokal, tetapi harus dipisahkan dari konfigurasi penggunaan normal.

Jangan menyimpan konfigurasi sensitif di dalam folder `public`.

---

# 3. SESSION SECURITY

Walaupun v1 tidak memiliki login, session tetap digunakan untuk kebutuhan aplikasi seperti CSRF token dan status sementara.

Aturan:

- Session dibuat oleh backend.
- Session ID menggunakan mekanisme PHP session.
- Session cookie menggunakan HttpOnly.
- SameSite menggunakan Lax.
- Secure cookie diaktifkan ketika menggunakan HTTPS.
- Session ID diregenerasi ketika konteks keamanan berubah.
- Session tidak boleh menyimpan data keuangan atau konten jurnal secara langsung.

Karena tidak ada autentikasi pengguna, session bukan pengganti sistem login.

Akses komputer lokal tetap menjadi batas kepercayaan utama.

---

# 4. CSRF PROTECTION

Semua request yang mengubah data wajib menggunakan CSRF token.

Berlaku untuk:

- POST.
- PUT.
- PATCH.
- DELETE.

Implementasi:

1. Generate token menggunakan cryptographically secure random generator.
2. Simpan token pada session.
3. Kirim token ke frontend.
4. Sertakan token pada request.
5. Bandingkan token dengan nilai session menggunakan hash_equals().
6. Tolak request jika token tidak valid.

Token tidak boleh:

- Dihardcode.
- Disimpan permanen dalam database.
- Dikirim melalui URL.
- Dicatat ke log.

Tambahan proteksi:

- Periksa Origin atau Referer jika tersedia.
- Tolak request cross-origin yang tidak diizinkan.
- Jangan mengandalkan CORS sebagai pengganti CSRF protection.

---

# 5. INPUT VALIDATION

Semua input harus divalidasi di backend.

## 5.1 Validation Categories

- Required fields.
- Data types.
- String length.
- Numeric range.
- Decimal precision.
- Date validity.
- Date range.
- Enum values.
- UUID format.
- Foreign key existence.
- Profile ownership.
- Resource state.
- File metadata.
- JSON schema.

## 5.2 SQL Injection Prevention

Wajib menggunakan PDO prepared statements.

Dilarang:

- Menyusun SQL dengan menggabungkan input pengguna.
- Menggunakan input langsung sebagai nama tabel.
- Menggunakan input langsung sebagai nama kolom.
- Menggunakan input sorting tanpa whitelist.

Contoh:

$stmt = $pdo->prepare(
    "SELECT * FROM tasks WHERE id = :id"
);

$stmt->execute([
    'id' => $taskId
]);

## 5.3 Mass Assignment Prevention

Backend hanya boleh menerima field yang diizinkan untuk setiap operasi.

Jangan langsung memasukkan seluruh request payload ke query UPDATE atau INSERT.

Contoh:

Pengguna tidak boleh mengubah:

- profile_id.
- created_at.
- deleted_at.
- system flags.
- internal status fields.

kecuali operasi tersebut memang secara eksplisit mengizinkannya.

---

# 6. XSS PROTECTION

Semua data yang ditampilkan harus diperlakukan sebagai data tidak tepercaya.

Aturan:

- Escape output HTML.
- Jangan memasukkan input pengguna ke innerHTML tanpa sanitasi.
- Hindari inline JavaScript yang berisi data pengguna.
- Sanitasi konten Markdown dan rich text.
- Gunakan HTML sanitizer dengan allowlist tag dan atribut.
- Blokir script, event handler, iframe, dan URL berbahaya.

Rich text tidak boleh dianggap aman hanya karena berasal dari editor internal.

## Content Security Policy

Terapkan Content Security Policy secara bertahap.

Baseline yang direkomendasikan:

default-src 'self';
script-src 'self';
style-src 'self';
img-src 'self' data: blob:;
font-src 'self';
connect-src 'self';
object-src 'none';
base-uri 'self';
frame-ancestors 'none';
form-action 'self';

Sesuaikan jika library frontend atau kebutuhan lokal memerlukan resource tambahan.

Hindari penggunaan unsafe-inline dan unsafe-eval jika memungkinkan.

---

# 7. FILE STORAGE SECURITY

## 7.1 Storage Location

Semua attachment disimpan di luar public web root.

Contoh:

storage/
    attachments/
    backups/
    imports/
    exports/
    logs/
    temporary/

Folder storage tidak boleh diakses langsung melalui URL.

File harus diunduh melalui endpoint yang memeriksa metadata dan status aksesnya.

## 7.2 Upload Validation

Batas ukuran:

50 MB per file.

Allowed extensions:

- JPG
- JPEG
- PNG
- WEBP
- PDF
- DOCX
- XLSX
- TXT
- ZIP

Validasi:

1. Periksa ukuran file.
2. Periksa extension.
3. Deteksi MIME menggunakan server-side file inspection.
4. Bandingkan extension dengan MIME yang diizinkan.
5. Generate nama file internal secara acak.
6. Simpan metadata file ke database.
7. Simpan file di storage non-public.

Jangan mempercayai:

- Nama file asli.
- MIME dari browser.
- Extension saja.
- Path yang dikirim pengguna.

Nama file internal tidak boleh menggunakan nama file asli sebagai path penyimpanan.

## 7.3 Download Security

Endpoint download harus:

- Memastikan attachment masih tersedia.
- Memvalidasi relasi attachment dengan resource.
- Memastikan file berada di storage yang ditentukan.
- Mencegah path traversal.
- Mengatur Content-Disposition.
- Mengatur Content-Type berdasarkan metadata server.
- Menggunakan X-Content-Type-Options: nosniff.

File HTML, SVG, dan executable tidak termasuk format upload yang diizinkan.

---

# 8. DATABASE SECURITY

## 8.1 Database Credentials

- Jangan menyimpan password database di repository.
- Gunakan file konfigurasi lokal yang tidak dapat diakses melalui HTTP.
- Jangan memasukkan kredensial ke log.
- Gunakan akun database khusus LIFEOS.
- Hindari menggunakan akun root untuk aplikasi.

## 8.2 Database Permissions

Akun aplikasi hanya diberi hak yang dibutuhkan.

Hak yang dibutuhkan untuk operasi normal:

- SELECT
- INSERT
- UPDATE
- DELETE

Operasi migration dapat menggunakan akun terpisah dengan hak DDL.

## 8.3 Database Integrity

- Gunakan InnoDB.
- Gunakan foreign key.
- Gunakan unique constraints.
- Gunakan transaksi database untuk operasi multi-tabel.
- Gunakan DECIMAL untuk nominal uang.
- Hindari FLOAT dan DOUBLE untuk nominal keuangan.

## 8.4 Profile Ownership

Semua data milik pengguna harus terikat dengan profile_id secara konsisten.

Setiap operasi harus memastikan resource memang dimiliki oleh profile aktif.

Jangan hanya mengandalkan UUID sebagai kontrol akses.

Walaupun v1 hanya memiliki satu profile, pemeriksaan ownership tetap diwajibkan untuk mempersiapkan multi-profile.

---

# 9. FINANCIAL DATA SECURITY

Operasi keuangan memiliki perlindungan tambahan.

Aturan:

- Semua perubahan transaksi dicatat.
- Transfer harus atomic.
- Pembayaran pinjaman harus atomic.
- Penghapusan transaksi tidak boleh menghilangkan jejak historis.
- Perubahan saldo manual harus memiliki alasan.
- Transaksi yang sudah digunakan dalam relasi tidak boleh dihapus secara fisik tanpa pemeriksaan dependency.

## Financial Integrity

Satu operasi keuangan multi-tabel harus menggunakan database transaction.

Jika salah satu proses gagal:

ROLLBACK seluruh operasi.

Jangan menyimpan saldo akun secara terpisah tanpa mekanisme rekonsiliasi dengan ledger transaksi.

---

# 10. BACKUP SECURITY

Backup dapat berisi seluruh informasi pribadi LIFEOS.

## 10.1 Backup Rules

- Simpan backup di luar public web root.
- Gunakan nama file unik.
- Catat waktu pembuatan.
- Catat ukuran file.
- Catat versi format backup.
- Catat status validasi.
- Jangan menimpa backup lama secara otomatis.
- Jangan menyimpan password database di dalam backup.

## 10.2 Backup Encryption

Enkripsi backup direkomendasikan.

Jika diaktifkan:

- Gunakan algoritma enkripsi terautentikasi seperti AES-256-GCM.
- Kunci enkripsi tidak boleh disimpan bersama backup.
- Gunakan password atau key management yang terpisah.
- Jangan menyimpan password backup dalam plaintext.
- Jangan mengklaim backup terenkripsi jika hanya menggunakan ZIP password biasa tanpa validasi implementasi.

## 10.3 Restore Safety

Restore harus memiliki tahapan:

1. Pilih backup.
2. Validasi format.
3. Validasi integritas.
4. Periksa versi backup.
5. Tampilkan ringkasan isi.
6. Buat backup kondisi database saat ini.
7. Minta konfirmasi eksplisit.
8. Jalankan restore.
9. Verifikasi hasil.
10. Catat aktivitas restore.

Restore gagal harus ditangani tanpa meninggalkan database dalam kondisi setengah terpulihkan.

---

# 11. IMPORT & EXPORT SECURITY

## Import

- Validasi extension dan MIME.
- Batasi ukuran file.
- Validasi struktur kolom.
- Validasi tipe data.
- Tolak formula atau konten yang tidak diharapkan.
- Tampilkan preview sebelum commit.
- Gunakan database transaction saat commit.
- Cegah duplicate import melalui import tracking.

## Export

- Validasi filter.
- Batasi jumlah data.
- Hindari mengekspos data internal.
- Escape data CSV yang dapat ditafsirkan sebagai formula spreadsheet.

Karakter yang berpotensi menjadi formula spreadsheet harus ditangani sebelum export CSV.

---

# 12. JSON BRIDGE SECURITY

JSON dari ChatGPT dianggap sebagai input eksternal.

Tidak ada bagian JSON yang boleh dieksekusi sebagai kode.

Aturan:

- Parse JSON dengan parser standar.
- Validasi schema_version.
- Validasi struktur.
- Validasi UUID.
- Validasi ownership.
- Validasi status.
- Validasi progress.
- Batasi panjang note.
- Tolak update ke task yang telah dihapus.
- Tampilkan preview.
- Minta konfirmasi sebelum commit.
- Catat hasil perubahan.

Jangan menerima SQL, PHP, JavaScript, shell command, atau instruksi eksekusi dari JSON bridge.

---

# 13. TRASH & DELETION SECURITY

LIFEOS menggunakan soft delete sebagai default.

Aturan:

- Penghapusan membuat metadata trash.
- Resource yang dihapus tidak muncul dalam daftar normal.
- Restore harus memeriksa konflik.
- Permanent delete memerlukan konfirmasi eksplisit.
- Attachment tidak boleh hilang jika resource masih dapat dipulihkan.
- Penghapusan parent harus memeriksa relasi child.
- Perubahan dicatat dalam history.

Permanent deletion harus membedakan penghapusan record dan penghapusan file fisik.

File fisik baru boleh dihapus ketika tidak ada referensi aktif atau proses restore yang masih membutuhkannya.

---

# 14. CHANGE HISTORY & LOGGING

Catat aktivitas penting:

- Create.
- Update.
- Delete.
- Restore.
- Permanent delete.
- Financial adjustment.
- Backup.
- Restore.
- Import.
- Export.

Log minimal berisi:

- Event type.
- Resource type.
- Resource identifier.
- Timestamp.
- Operation status.
- Ringkasan perubahan.

Jangan mencatat:

- Database password.
- CSRF token.
- Session ID.
- Encryption key.
- Isi lengkap jurnal pribadi jika tidak diperlukan.
- Konten attachment.
- Data sensitif yang tidak relevan.

Log harus memiliki batas ukuran dan mekanisme rotasi agar tidak terus membesar tanpa kontrol.

---

# 15. SCHEDULED TASK SECURITY

Windows Task Scheduler menjalankan pekerjaan berkala melalui PHP CLI.

Aturan:

- Gunakan PHP executable yang ditentukan.
- Gunakan absolute path untuk script.
- Batasi hak akses akun Windows.
- Jangan menjalankan script melalui folder yang dapat ditulis sembarang pengguna.
- Catat hasil eksekusi.
- Gunakan lock untuk mencegah job berjalan bersamaan.
- Pastikan retry tidak menggandakan transaksi atau notifikasi.

Scheduled jobs tidak boleh mengandalkan request browser untuk menyelesaikan pekerjaan.

---

# 16. DEPENDENCY SECURITY

- Gunakan Composer untuk dependency PHP.
- Catat dependency dan versinya.
- Jangan memasang package tanpa kebutuhan.
- Periksa kompatibilitas versi PHP.
- Periksa vulnerability dependency secara berkala.
- Hindari mengambil script dari CDN saat runtime.
- Simpan asset frontend secara lokal jika memungkinkan.
- Jangan menjalankan package yang tidak dikenal tanpa pemeriksaan.

---

# 17. ERROR & EXCEPTION HANDLING

Mode development:

- Error dapat ditampilkan secara lokal.
- Detail exception dapat digunakan untuk debugging.

Mode normal:

- display_errors dinonaktifkan.
- Error dicatat di log.
- Pengguna menerima pesan yang aman dan mudah dipahami.
- Stack trace tidak dikirim ke browser.
- SQL query dan path internal tidak ditampilkan.

Semua exception yang tidak ditangani harus menghasilkan response error standar.

---

# 18. SECURITY TESTING CHECKLIST

## Application

- [ ] CSRF token valid.
- [ ] CSRF token invalid ditolak.
- [ ] SQL injection dicegah.
- [ ] XSS dicegah.
- [ ] Output HTML di-escape.
- [ ] Rich text disanitasi.
- [ ] Mass assignment dicegah.
- [ ] Profile ownership divalidasi.
- [ ] Sorting menggunakan whitelist.

## Files

- [ ] File terlalu besar ditolak.
- [ ] Extension palsu ditolak.
- [ ] MIME tidak cocok ditolak.
- [ ] Path traversal dicegah.
- [ ] File tidak dapat diakses langsung.
- [ ] Download melalui endpoint tervalidasi.

## Database

- [ ] Foreign key berjalan.
- [ ] Database transaction berjalan.
- [ ] Rollback berjalan.
- [ ] Saldo akun konsisten.
- [ ] Transfer tidak masuk laporan income/expense.
- [ ] Restore menjaga integritas relasi.

## Backup

- [ ] Backup berhasil dibuat.
- [ ] Backup dapat divalidasi.
- [ ] Backup tidak dapat diakses publik.
- [ ] Restore memiliki preview.
- [ ] Restore meminta konfirmasi.
- [ ] Backup kondisi sebelum restore tersedia.

## Local Environment

- [ ] Apache hanya bind ke localhost.
- [ ] Folder internal tidak dapat diakses melalui browser.
- [ ] Directory listing dinonaktifkan.
- [ ] phpMyAdmin tidak terekspos ke jaringan publik.
- [ ] Konfigurasi rahasia tidak masuk repository.

---

# 19. SECURITY ACCEPTANCE CRITERIA

Security baseline LIFEOS dianggap terpenuhi apabila:

1. Tidak ada SQL query yang menggunakan input pengguna secara langsung.
2. Semua write endpoints dilindungi CSRF.
3. Semua input divalidasi backend.
4. Semua output pengguna di-escape atau disanitasi.
5. File upload disimpan di luar public web root.
6. Database credentials tidak tersedia melalui HTTP.
7. Finance operations bersifat atomic.
8. Profile ownership diperiksa.
9. Backup dan restore memiliki validasi.
10. Error internal tidak bocor ke UI.
11. JSON bridge tidak dapat mengeksekusi kode.
12. Aplikasi tidak terekspos ke jaringan secara default.

---

# END OF SECURITY SPECIFICATION