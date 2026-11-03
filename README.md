# LIFEOS

Personal life management app berbasis PHP Native untuk dijalankan secara lokal melalui XAMPP.

## Stack
- PHP 8.2+
- MariaDB 10.4+ / MySQL
- PDO (prepared statements)
- Bootstrap 5.3 via CDN
- Vanilla JavaScript

Tidak menggunakan framework, Composer, router kompleks, service container, atau sistem login. LIFEOS adalah aplikasi single-user lokal. Session hanya dipakai untuk CSRF token dan state sementara, dengan cookie yang diperketat.

## Menjalankan di XAMPP
1. Clone repository ke `D:\\xampp\\htdocs\\lifeos`.
2. Jalankan Apache dan MySQL/MariaDB dari XAMPP.
3. Buka phpMyAdmin, lalu import `database/lifeos.sql`.
4. Periksa pengaturan koneksi di `config/database.php` (default XAMPP: host 127.0.0.1, port 3306, user root, password kosong).
5. Buka `http://localhost/lifeos/`.

Aplikasi ditujukan untuk localhost. Jangan expose folder ini ke internet atau jaringan publik.

## Struktur
- `config/`: konfigurasi database dan session.
- `includes/`: bootstrap, helper, header, sidebar, footer.
- `dashboard/`: dashboard.
- `finance/`, `schedule/`, `tasks/`, `habits/`, `journal/`, `notes/`, `goals/`, `projects/`, `reports/`: modul terpisah.
- `assets/`: CSS dan JavaScript.
- `database/`: SQL awal.

Setiap modul boleh memiliki halaman PHP sendiri dan memakai komponen bersama dari `includes/`. Query database ditulis dekat dengan fitur yang menggunakannya, tetap gunakan PDO prepared statements.

## Keamanan dasar
- Session cookie HttpOnly, SameSite=Strict, Secure saat HTTPS, strict mode, dan idle timeout.
- CSRF token untuk setiap request yang mengubah data.
- PDO prepared statements; escape output dengan `e()`.
- Folder konfigurasi, includes, dan database tidak boleh diakses langsung melalui web.
- Tidak ada autentikasi karena instalasi v1 hanya untuk satu pengguna di localhost.

## Pengembangan
Mulai dari dashboard dan bangun satu modul sampai CRUD-nya selesai sebelum pindah ke modul berikutnya. Tidak ada service/repository layer wajib. Jika suatu kebutuhan mulai kompleks, pecah fungsi ke file helper atau file modul terkait tanpa membuat abstraksi yang belum dibutuhkan.
