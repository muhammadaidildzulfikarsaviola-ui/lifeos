# LIFEOS

**Your personal life management workspace.**

LIFEOS membantu kamu mengelola berbagai bagian kehidupan sehari-hari dalam satu aplikasi lokal: keuangan, jadwal, pekerjaan, kebiasaan, catatan, tujuan, dan proyek.

## Fitur

LIFEOS memiliki sembilan modul utama:

| Modul | Kegunaan |
|---|---|
| **Finance** | Catat pemasukan, pengeluaran, saldo akun, transfer, anggaran, tagihan, dan pinjaman pribadi. |
| **Schedule & Calendar** | Atur agenda, kegiatan, shift, pengingat, dan hari libur. |
| **Tasks** | Kelola pekerjaan, prioritas, tenggat, subtugas, dan progres. |
| **Habits** | Bangun kebiasaan dan pantau konsistensi harian atau mingguan. |
| **Journal** | Tulis jurnal harian, refleksi, dan suasana hati. |
| **Notes** | Simpan catatan, checklist, lampiran, dan informasi penting. |
| **Goals** | Tetapkan tujuan, milestone, dan pantau pencapaiannya. |
| **Projects** | Kelola proyek, tugas, anggaran, tenggat, dan progres. |
| **Reports** | Lihat ringkasan aktivitas, keuangan, kebiasaan, tugas, dan proyek. |

## Mulai menggunakan LIFEOS

1. Jalankan layanan lokal yang dibutuhkan oleh instalasi LIFEOS.
2. Buka alamat LIFEOS melalui browser.
3. Lengkapi preferensi awal jika diminta.
4. Mulai dengan menambahkan akun keuangan, agenda, tugas, atau kebiasaan.
5. Periksa dashboard untuk melihat ringkasan aktivitasmu.

Langkah instalasi dan konfigurasi teknis tersedia dalam dokumentasi pengembang atau panduan instalasi yang disertakan bersama distribusi aplikasi.

## Data dan privasi

LIFEOS dirancang dengan pendekatan **local-first**. Data utama disimpan di database lokal pada perangkat tempat aplikasi dijalankan. File lampiran dan backup juga disimpan pada lokasi penyimpanan yang dikonfigurasi.

Artinya:
- Data tidak otomatis dikirim ke layanan cloud.
- Kamu bertanggung jawab menjaga perangkat dan folder penyimpanan tetap aman.
- Buat backup secara berkala, terutama sebelum upgrade atau perubahan besar.
- Jangan menghapus folder database, storage, attachment, atau backup secara manual jika belum memahami dampaknya.

Jika aplikasi diakses dari perangkat lain melalui jaringan lokal, pastikan jaringan dan perangkat tersebut memang tepercaya.

## Backup dan pemulihan

Gunakan fitur backup LIFEOS untuk membuat salinan database dan file yang diperlukan. Simpan backup di lokasi yang aman, idealnya pada media penyimpanan lain.

Sebelum memulihkan backup:
1. Pastikan file backup berasal dari LIFEOS dan tidak rusak.
2. Periksa tanggal dan versi backup.
3. Buat backup kondisi data saat ini.
4. Baca informasi pada layar pemulihan dengan teliti.
5. Lanjutkan hanya jika kamu memahami bahwa proses restore dapat mengganti data saat ini.

Jangan menganggap file ZIP yang belum diverifikasi sebagai backup yang pasti bisa dipulihkan.

## Integrasi ChatGPT secara manual

Jika fitur bantuan berbasis ChatGPT tersedia, alurnya dilakukan secara manual:

1. LIFEOS menyiapkan prompt atau data yang perlu dianalisis.
2. Salin prompt tersebut ke ChatGPT.
3. Salin hasil terstruktur (misalnya JSON) dari ChatGPT.
4. Tempelkan kembali hasilnya ke LIFEOS.
5. Periksa preview dan validasi sebelum menyimpan.

ChatGPT tidak memiliki akses langsung ke database LIFEOS. Jangan menempelkan informasi sensitif ke layanan eksternal jika kamu tidak ingin informasi tersebut diproses di luar perangkat.

## Tips penggunaan

- Catat transaksi sesegera mungkin agar laporan keuangan tetap akurat.
- Gunakan kategori dan tag secara konsisten.
- Pecah pekerjaan besar menjadi subtugas.
- Jadwalkan waktu untuk tujuan dan proyek, bukan hanya tenggat akhirnya.
- Tinjau laporan mingguan atau bulanan untuk melihat pola.
- Buat backup rutin dan uji proses pemulihan sesekali.

## Catatan

Nama menu, tampilan, dan ketersediaan fitur dapat berbeda sesuai versi LIFEOS yang digunakan. README ini merupakan pengantar pengguna, bukan panduan instalasi atau dokumentasi teknis.

Untuk kendala aplikasi, catat langkah yang dilakukan, pesan error yang muncul, dan waktu kejadian. Hindari membagikan database, backup, jurnal, atau catatan pribadi saat meminta bantuan kecuali memang diperlukan dan sudah disensor.
