# LIFEOS SOFTWARE ARCHITECTURE

**Version:** 1.0  
**Status:** Architecture Decision  
**Architecture:** Modular Monolith  
**Runtime:** PHP Native 8.2  
**Database:** MariaDB 10.4.32  
**Environment:** Windows + XAMPP  
**Deployment:** Localhost only

## 1. Architecture Overview

LIFEOS menggunakan arsitektur Modular Monolith. Seluruh modul berjalan dalam satu aplikasi PHP dan satu database, tetapi memiliki batas tanggung jawab yang jelas.

Tidak menggunakan microservices, server API terpisah, maupun AI API.

## 2. Technology Stack

- PHP Native 8.2
- Composer PSR-4
- MariaDB 10.4.32
- PDO
- Apache
- Bootstrap 5.3
- Vanilla JavaScript
- Alpine.js
- FullCalendar
- SortableJS
- Chart.js

Database harus kompatibel dengan MariaDB 10.4. Hindari ketergantungan pada fitur eksklusif MySQL 8.

## 3. Application Layers

### Presentation Layer
Menangani halaman, layout, komponen UI, dan interaksi pengguna.

### Controller Layer
Menerima HTTP request, memvalidasi struktur request, memanggil service, dan mengembalikan response.

Controller tidak boleh berisi business logic atau query SQL langsung.

### Service Layer
Menangani use case, business rules, validasi domain, transaksi database, dan koordinasi antar-modul.

### Repository Layer
Menangani seluruh akses database menggunakan PDO prepared statements.

### Infrastructure Layer
Menyediakan database connection, logging, filesystem, session, security, backup, dan konfigurasi aplikasi.

## 4. Module Boundaries

Modul utama:

1. Finance
2. Schedule & Calendar
3. Tasks
4. Habits
5. Journal
6. Notes
7. Goals
8. Projects
9. Reports

Setiap modul memiliki Controller, Service, Repository, dan View sesuai kebutuhannya.

Modul tidak boleh mengakses repository internal modul lain secara langsung. Integrasi dilakukan melalui service atau kontrak bersama.

## 5. Directory Structure

```text
lifeos/
├── app/
│   ├── Core/
│   ├── Infrastructure/
│   ├── Shared/
│   └── Modules/
│       ├── Finance/
│       ├── Schedule/
│       ├── Tasks/
│       ├── Habits/
│       ├── Journal/
│       ├── Notes/
│       ├── Goals/
│       ├── Projects/
│       └── Reports/
├── bootstrap/
├── config/
├── database/
│   ├── migrations/
│   └── seeders/
├── public/
│   ├── assets/
│   ├── index.php
│   └── .htaccess
├── resources/
│   └── views/
├── routes/
├── storage/
│   ├── attachments/
│   ├── backups/
│   ├── exports/
│   ├── imports/
│   ├── logs/
│   └── temporary/
├── tests/
├── vendor/
├── .env
├── .env.example
├── .gitignore
├── composer.json
└── README.md
```

## 6. Request Lifecycle

1. Browser mengirim HTTP request.
2. Apache meneruskan request ke public/index.php.
3. Bootstrap memuat konfigurasi dan dependency.
4. Router menentukan Controller.
5. Controller memanggil Service.
6. Service menjalankan business rules.
7. Repository mengakses database melalui PDO.
8. Service mengembalikan hasil.
9. Controller mengirim HTML atau JSON response.

## 7. Database Architecture

- Satu database MariaDB.
- Seluruh tabel utama menggunakan InnoDB.
- Charset utf8mb4.
- Nominal keuangan menggunakan DECIMAL.
- Timestamp disimpan dalam UTC.
- Calendar-only dates menggunakan DATE.
- Saldo akun dihitung dari ledger.
- Soft delete menjadi mekanisme penghapusan default.
- Foreign key dan ownership validation wajib diterapkan.

## 8. Security Architecture

- Aplikasi hanya tersedia melalui localhost.
- Document root Apache menunjuk ke public/.
- File konfigurasi dan storage berada di luar public/.
- Semua query menggunakan prepared statements.
- Semua perubahan data dilindungi CSRF.
- Input divalidasi di backend.
- Output HTML di-escape sesuai konteks.
- Session menggunakan cookie HttpOnly dan SameSite.
- Upload divalidasi berdasarkan MIME, extension, dan ukuran.
- Error teknis disimpan dalam log, bukan ditampilkan kepada pengguna.

## 9. Frontend Architecture

- Bootstrap menangani layout dan komponen dasar.
- Vanilla JavaScript menangani interaksi aplikasi.
- Alpine.js digunakan untuk interaksi reaktif ringan.
- FullCalendar menangani tampilan kalender.
- SortableJS menangani drag and drop.
- Chart.js menangani visualisasi laporan.

Business logic tetap berada di backend.

## 10. Application Profile

V1 menggunakan satu profile lokal tanpa login.

profile_id tetap digunakan untuk ownership dan kompatibilitas pengembangan multi-profile di masa depan.

Session tidak dianggap sebagai sistem autentikasi.

## 11. Integration Rules

- Finance menyediakan data transaksi untuk Reports.
- Projects membaca transaksi melalui kontrak Finance.
- Tasks terhubung ke Projects dan Schedule.
- Goals dapat menghubungkan resource dari modul lain.
- Dashboard dan Reports bersifat read-only.
- Shared services menangani Trash, Attachments, Notifications, Backup, Import, dan Change History.

## 12. Development Principles

1. Foundation before features.
2. Business logic before UI refinement.
3. Database integrity before module integration.
4. Explicit module boundaries.
5. No duplicated sources of truth.
6. No direct database access from views.
7. No direct repository access across modules.
8. Every write operation must be validated.
9. Critical multi-row operations must be atomic.
10. Scheduled operations must be idempotent.
11. No dead buttons or fake functionality.
12. No AI API integration.

## 13. Deployment

Development environment:

- Windows
- XAMPP Apache
- PHP 8.2
- MariaDB 10.4.32
- Composer 2.x

Apache document root must point to the public directory.

Storage, configuration, logs, backups, and database migrations must remain outside public access.

## 14. Architecture Acceptance Criteria

Architecture dianggap siap ketika:

- Composer PSR-4 autoload berjalan.
- Front controller berjalan.
- Router dapat memproses request.
- PDO dapat terhubung ke MariaDB.
- Internal directories tidak dapat diakses melalui browser.
- Error handling dan logging tersedia.
- Session dan CSRF berjalan.
- Struktur modular dapat dikembangkan tanpa ketergantungan silang yang tidak terkendali.

**END OF ARCHITECTURE DOCUMENT**