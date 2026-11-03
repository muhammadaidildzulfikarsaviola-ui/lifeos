# LIFEOS Architecture (Simple Local PHP)

## Prinsip
LIFEOS adalah aplikasi personal single-user yang berjalan lokal melalui XAMPP. Pilih struktur PHP Native yang mudah dibaca dan diedit langsung. Tidak menggunakan framework, Composer, dependency injection, service container, repository layer, atau sistem login pada v1.

## Stack
- PHP 8.2+
- MariaDB 10.4+ / MySQL
- PDO dengan prepared statements
- Bootstrap 5.3 dan CSS aplikasi
- Vanilla JavaScript

## Struktur
```
lifeos/
├── assets/
│   ├── css/app.css
│   └── js/app.js
├── config/
│   ├── database.php
│   └── session.php
├── dashboard/
├── finance/
├── schedule/
├── tasks/
├── habits/
├── journal/
├── notes/
├── goals/
├── projects/
├── reports/
├── database/lifeos.sql
├── includes/
│   ├── bootstrap.php
│   ├── header.php
│   └── footer.php
├── function.php
└── index.php
```

Setiap modul memiliki folder dan file PHP sendiri. Gunakan partial dari includes untuk layout bersama. Letakkan query dan fungsi yang spesifik dekat dengan modulnya. Pecah file hanya ketika ukurannya memang membutuhkan.

## Database
Koneksi tunggal dibuat secara lazy melalui `db()` di `function.php`. Gunakan PDO prepared statements untuk seluruh input pengguna. Simpan nominal keuangan sebagai DECIMAL, tanggal kalender sebagai DATE, dan timestamp sebagai DATETIME/TIMESTAMP sesuai kebutuhan. Jangan membangun semua tabel modul sebelum modul terkait mulai dikerjakan.

## Session tanpa login
Session hanya digunakan untuk CSRF token dan state sementara. Cookie menggunakan HttpOnly, SameSite=Strict, Secure saat HTTPS, strict mode, cookie-only, ID regeneration saat sesi baru, dan idle timeout 30 menit. Tidak ada user identity atau autentikasi.

## Keamanan
- Instalasi v1 hanya localhost.
- Folder config, includes, database, dan storage tidak boleh diakses langsung melalui HTTP.
- Escape output HTML dengan `e()`.
- Gunakan prepared statements.
- Verifikasi CSRF pada setiap operasi yang mengubah data.
- Validasi input di server.
- Jangan simpan upload di folder publik.

## Cara berkembang
Bangun dashboard dasar, lalu implementasikan satu modul sampai CRUD berjalan dan diuji. Hindari membuat abstraksi sebelum ada kebutuhan nyata. Backup, import/export, trash, dan laporan lanjutan ditambahkan saat modul yang membutuhkannya sudah ada.
