# LIFEOS DEVELOPMENT ENVIRONMENT SPECIFICATION

**Project:** LIFEOS  
**Version:** 1.0  
**Status:** Development Environment Specification  
**Operating System:** Windows 11  
**Backend:** PHP Native 8.2  
**Database:** MySQL 8.0+  
**Web Server:** Apache  
**Environment:** XAMPP  
**Dependency Manager:** Composer

---

# 1. PURPOSE

Dokumen ini menetapkan standar lingkungan development LIFEOS.

Tujuan:

- Menyamakan konfigurasi development.
- Menentukan struktur direktori.
- Menghindari konflik versi dan port.
- Menyiapkan Apache Virtual Host.
- Menentukan konfigurasi PHP dan MySQL.
- Menyiapkan Composer dan dependency.
- Memastikan aplikasi dapat dijalankan secara lokal.
- Membuat setup environment dapat direproduksi.

---

# 2. SYSTEM REQUIREMENTS

## 2.1 Hardware

Minimum rekomendasi:

- Dual-core processor.
- RAM 8 GB.
- Storage kosong 5 GB untuk project dan development tools.

Recommended:

- Quad-core processor atau lebih.
- RAM 16 GB.
- SSD.

## 2.2 Software

| Software | Requirement |
|---|---|
| Windows | Windows 11 64-bit |
| PHP | 8.2.x |
| MySQL | 8.0+ |
| Apache | 2.4.x |
| Composer | 2.x |
| Git | Latest stable |
| Browser | Chrome / Edge / Brave |
| Editor | Visual Studio Code |

Versi dependency harus dicatat dalam project.

Jangan mengandalkan versi yang kebetulan terpasang di komputer.

---

# 3. PROJECT DIRECTORY STRUCTURE

Root:

D:\Code\lifeos

Struktur:

```text
lifeos/
│
├── app/
│   ├── Controllers/
│   ├── Services/
│   ├── Repositories/
│   ├── Models/
│   ├── Middleware/
│   ├── Validators/
│   ├── Exceptions/
│   ├── Helpers/
│   └── Providers/
│
├── bootstrap/
│   ├── app.php
│   ├── container.php
│   └── error-handler.php
│
├── config/
│   ├── app.php
│   ├── database.php
│   ├── session.php
│   ├── filesystems.php
│   └── security.php
│
├── database/
│   ├── migrations/
│   ├── seeders/
│   ├── factories/
│   └── test-data/
│
├── docs/
│
├── public/
│   ├── index.php
│   ├── .htaccess
│   └── assets/
│       ├── css/
│       ├── js/
│       ├── images/
│       └── vendor/
│
├── resources/
│   ├── views/
│   ├── components/
│   ├── layouts/
│   ├── scripts/
│   └── styles/
│
├── routes/
│   ├── web.php
│   └── api.php
│
├── storage/
│   ├── attachments/
│   ├── backups/
│   ├── exports/
│   ├── imports/
│   ├── logs/
│   └── temporary/
│
├── tests/
│   ├── Unit/
│   ├── Integration/
│   ├── Api/
│   └── Fixtures/
│
├── vendor/
│
├── .env
├── .env.example
├── .gitignore
├── composer.json
├── composer.lock
└── README.md
```

Folder `public` menjadi satu-satunya document root Apache.

Folder `storage`, `app`, `config`, `database`, dan `vendor` tidak boleh diakses langsung melalui browser.

---

# 4. XAMPP CONFIGURATION

## 4.1 Installation

XAMPP digunakan sebagai local development environment.

Komponen:

- Apache.
- MySQL.
- PHP.
- phpMyAdmin.

Jangan menganggap MySQL dan MariaDB sebagai produk yang identik.

Pastikan database engine yang digunakan benar-benar kompatibel dengan target MySQL 8.0+.

Jika distribusi XAMPP menyediakan MariaDB, verifikasi versi dan kompatibilitasnya sebelum menjadikannya environment utama.

## 4.2 Port Configuration

Default:

| Service | Port |
|---|---:|
| Apache HTTP | 80 |
| Apache HTTPS | 443 |
| MySQL | 3306 |

Jika port sudah digunakan:

1. Identifikasi process pemilik port.
2. Tentukan apakah process tersebut memang diperlukan.
3. Ubah konfigurasi salah satu service jika perlu.
4. Catat perubahan port.
5. Update koneksi database dan Virtual Host.

Jangan asal mematikan service Windows hanya untuk membebaskan port.

## 4.3 Apache Document Root

Document root harus diarahkan ke:

D:/Code/lifeos/public

Jangan arahkan Apache ke:

D:/Code/lifeos

Karena folder root berisi konfigurasi, source code, dan file internal.

---

# 5. APACHE VIRTUAL HOST

Recommended local domain:

lifeos.local

Contoh konfigurasi Apache:

```apache
<VirtualHost *:80>
    ServerName lifeos.local

    DocumentRoot "D:/Code/lifeos/public"

    <Directory "D:/Code/lifeos/public">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require local
    </Directory>

    ErrorLog "logs/lifeos-error.log"
    CustomLog "logs/lifeos-access.log" combined
</VirtualHost>
```

Aktifkan Virtual Host configuration di Apache.

Tambahkan ke Windows hosts file:

```text
127.0.0.1 lifeos.local
```

Lokasi hosts:

```text
C:\Windows\System32\drivers\etc\hosts
```

Akses aplikasi:

http://lifeos.local

Konfigurasi `Require local` membatasi akses ke mesin lokal.

Jika menggunakan HTTPS, konfigurasi sertifikat lokal harus disiapkan secara terpisah.

---

# 6. PHP CONFIGURATION

## 6.1 Required Extensions

Pastikan extension berikut aktif:

- PDO.
- pdo_mysql.
- mbstring.
- openssl.
- fileinfo.
- json.
- curl.
- intl.
- zip.
- gd.
- exif.

Extension tambahan hanya dipasang jika ada kebutuhan nyata.

## 6.2 Recommended php.ini

```ini
display_errors = Off
log_errors = On
expose_php = Off

memory_limit = 256M
max_execution_time = 120

upload_max_filesize = 50M
post_max_size = 55M

date.timezone = Asia/Jakarta

session.cookie_httponly = 1
session.cookie_samesite = Lax
```

Catatan:

- `post_max_size` harus lebih besar dari `upload_max_filesize`.
- `session.cookie_secure` diaktifkan ketika menggunakan HTTPS.
- Konfigurasi development dapat menggunakan error reporting lebih detail melalui log.
- Jangan mengaktifkan display_errors pada penggunaan normal.

## 6.3 Verify PHP

Command:

```powershell
php -v
php -m
php --ini
```

Pastikan PHP CLI dan PHP yang digunakan Apache memiliki versi serta konfigurasi yang sesuai.

Jangan mengasumsikan keduanya otomatis menggunakan executable dan php.ini yang sama.

---

# 7. MYSQL CONFIGURATION

## 7.1 Database

Nama database:

lifeos

Database testing:

lifeos_test

Character set:

utf8mb4

Collation:

utf8mb4_unicode_ci

Storage engine:

InnoDB

## 7.2 Database Creation

Contoh:

```sql
CREATE DATABASE lifeos
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

CREATE DATABASE lifeos_test
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

## 7.3 Database User

Buat akun database khusus aplikasi.

Contoh:

```sql
CREATE USER 'lifeos_app'@'localhost'
IDENTIFIED BY 'CHANGE_THIS_PASSWORD';

GRANT SELECT, INSERT, UPDATE, DELETE
ON lifeos.*
TO 'lifeos_app'@'localhost';
```

Migration yang membutuhkan DDL harus dijalankan menggunakan akun terpisah dengan izin yang sesuai.

Jangan menggunakan root untuk koneksi aplikasi sehari-hari.

## 7.4 Database Connection

Gunakan PDO.

Konfigurasi harus berasal dari environment variables.

Contoh:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lifeos
DB_USERNAME=lifeos_app
DB_PASSWORD=CHANGE_THIS_PASSWORD
DB_CHARSET=utf8mb4
```

PDO harus menggunakan:

- Exception error mode.
- Native prepared statements jika didukung konfigurasi driver.
- Explicit charset.
- Consistent transaction handling.

---

# 8. COMPOSER CONFIGURATION

Composer digunakan untuk mengelola dependency PHP.

## 8.1 Autoloading

Gunakan PSR-4.

Contoh:

```json
{
    "autoload": {
        "psr-4": {
            "LifeOS\\": "app/"
        }
    }
}
```

Setelah konfigurasi berubah:

```powershell
composer dump-autoload
```

## 8.2 Development Dependencies

Recommended:

- PHPUnit.
- Mockery jika diperlukan.

Tambahkan dependency hanya jika ada kebutuhan.

## 8.3 Dependency Rules

- `composer.json` harus mencatat dependency.
- `composer.lock` harus masuk Git.
- Folder `vendor/` tidak perlu masuk Git.
- Jangan mengubah dependency langsung di folder vendor.
- Update dependency harus diuji.

---

# 9. ENVIRONMENT VARIABLES

Gunakan `.env` untuk konfigurasi lokal.

Contoh `.env.example`:

```env
APP_NAME=LIFEOS
APP_ENV=development
APP_DEBUG=false
APP_URL=http://lifeos.local
APP_TIMEZONE=Asia/Jakarta

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lifeos
DB_USERNAME=lifeos_app
DB_PASSWORD=

SESSION_LIFETIME=120

STORAGE_PATH=D:/Code/lifeos/storage
```

Aturan:

- `.env` tidak boleh masuk Git.
- `.env.example` boleh masuk Git.
- Password dan secret tidak boleh masuk repository.
- Jangan menggunakan environment variable sebagai pengganti validasi konfigurasi.
- Aplikasi harus gagal dengan pesan konfigurasi yang jelas jika nilai wajib tidak tersedia.

---

# 10. GIT CONFIGURATION

Recommended `.gitignore`:

```gitignore
/vendor/
/.env
/storage/attachments/*
/storage/backups/*
/storage/exports/*
/storage/imports/*
/storage/logs/*
/storage/temporary/*

!/storage/attachments/.gitkeep
!/storage/backups/.gitkeep
!/storage/exports/.gitkeep
!/storage/imports/.gitkeep
!/storage/logs/.gitkeep
!/storage/temporary/.gitkeep

.phpunit.cache/
.phpunit.result.cache
coverage/
```

Jangan commit:

- Database dump pribadi.
- Attachment pengguna.
- Backup.
- Environment secrets.
- Temporary import files.
- Runtime logs.

---

# 11. FRONTEND DEPENDENCIES

Baseline:

| Dependency | Purpose |
|---|---|
| Bootstrap 5.3 | Layout and UI components |
| Vanilla JavaScript | Application logic |
| Alpine.js | Lightweight reactive interactions |
| FullCalendar | Calendar interface |
| SortableJS | Drag and drop |
| Chart.js | Charts |

Dependency frontend harus disimpan secara lokal.

Hindari ketergantungan pada CDN saat aplikasi berjalan offline.

Gunakan versi dependency yang dipatok dan dicatat.

Jangan memasukkan library yang memiliki fungsi tumpang tindih tanpa alasan.

---

# 12. LOCAL STORAGE & FILE PERMISSIONS

Folder storage harus memiliki akses baca/tulis bagi akun Windows yang menjalankan Apache.

Folder internal tidak boleh memiliki izin akses yang lebih luas dari kebutuhan.

Jangan memberikan Full Control kepada Everyone hanya agar upload berhasil.

Pisahkan:

- Application source.
- User-generated files.
- Backup files.
- Temporary files.
- Logs.

Backup dan attachment tidak boleh menjadi bagian dari public document root.

---

# 13. SCHEDULED JOB ENVIRONMENT

Gunakan Windows Task Scheduler.

Scheduled job dijalankan melalui PHP CLI.

Contoh command:

```powershell
D:\xampp\php\php.exe D:\Code\lifeos\scripts\scheduled\run.php
```

Aturan:

- Gunakan absolute paths.
- Gunakan executable PHP yang benar.
- Catat output dan error ke log.
- Gunakan locking.
- Pastikan job idempotent.
- Hindari menjalankan dua instance job yang sama bersamaan.

Task Scheduler harus menggunakan akun Windows dengan izin yang cukup, tetapi tidak berlebihan.

---

# 14. INITIAL SETUP CHECKLIST

## System

- [ ] Windows 11 siap.
- [ ] XAMPP terpasang.
- [ ] PHP 8.2 aktif.
- [ ] MySQL 8.0+ tersedia.
- [ ] Composer tersedia.
- [ ] Git tersedia.
- [ ] Browser tersedia.

## Apache

- [ ] Apache dapat berjalan.
- [ ] Virtual Host dibuat.
- [ ] Hosts file diperbarui.
- [ ] Document root menunjuk public.
- [ ] Directory listing dinonaktifkan.
- [ ] Internal folders tidak dapat diakses.

## PHP

- [ ] PHP CLI benar.
- [ ] PHP Apache benar.
- [ ] Required extensions aktif.
- [ ] php.ini dikonfigurasi.
- [ ] Upload limit sesuai.

## Database

- [ ] Database lifeos dibuat.
- [ ] Database lifeos_test dibuat.
- [ ] Database user khusus dibuat.
- [ ] PDO connection berhasil.
- [ ] Character set benar.
- [ ] InnoDB tersedia.

## Project

- [ ] Directory structure tersedia.
- [ ] Composer autoload aktif.
- [ ] `.env` tersedia.
- [ ] `.env` masuk gitignore.
- [ ] Storage directories tersedia.
- [ ] PHPUnit dapat dijalankan.

---

# 15. ENVIRONMENT ACCEPTANCE CRITERIA

Environment dianggap siap apabila:

1. `lifeos.local` dapat dibuka.
2. Apache hanya menerima koneksi lokal.
3. PHP CLI dan Apache menggunakan konfigurasi yang diketahui.
4. Database connection berhasil.
5. PDO prepared statements berjalan.
6. Composer autoload bekerja.
7. Folder internal tidak dapat diakses browser.
8. Upload dapat disimpan di storage non-public.
9. Automated test dapat dijalankan.
10. Environment dapat dibangun ulang dari dokumentasi ini.

---

# END OF DEVELOPMENT ENVIRONMENT SPECIFICATION
