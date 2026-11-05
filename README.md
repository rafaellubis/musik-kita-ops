<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Cuti per enrollment

- Pada detail murid, pilih **Ajukan / Perpanjang Cuti Kelas**, lalu pilih kelas.
  Murid tetap Aktif jika kelas lainnya masih berjalan. Periode dan tombol
  kembali aktif tersedia di tab **Kelas**.
- Biaya Rp100.000 per kelas per pengajuan/perpanjangan. Pengajuan awal maksimal
  31 hari inklusif; perpanjangan sekali hingga total 62 hari.
- Tanggal akhir masih termasuk cuti; kelas aktif kembali mulai hari berikutnya.
  Pengajuan untuk tanggal mendatang tidak menghentikan kelas hari ini.
- SPP bulan parsial tetap penuh. Bulan yang seluruh harinya tercakup cuti kelas
  tidak ditagih otomatis. Invoice yang sudah terbit tidak diubah.
- Sesi kelas lain tidak dibatalkan. Sesi otomatis, manual, dan reschedule
  tidak boleh dibuat pada tanggal cuti kelas yang dipilih.
- Riwayat `enrollment_leaves` tetap disimpan setelah kembali aktif.

**Penerapan:** jalankan `php artisan migrate` setelah backup database.
Migrasi `2026_10_08_000001_create_enrollment_leaves_table.php` menyalin periode
cuti murid lama ke kelas terkait tanpa menghapus kolom atau invoice lama.
Pastikan Task Scheduler menjalankan `php artisan schedule:run` setiap menit.
Job cuti berjalan pukul 00:00 dan pada waktu yang diatur di halaman Scheduler.
Pengujian wajib memakai SQLite `:memory:` sesuai `phpunit.xml`, bukan database studio.

## Pencarian invoice

Kolom **No. Invoice / nama** mencari nomor invoice, nama lengkap, nickname,
atau kode murid, termasuk sebagian teks. Filter bulan dan status tetap berlaku.

## Reminder WA dari invoice

- Owner/Admin membuka **Reminder WA** pada daftar atau detail invoice. Default
  pesan hanya memuat invoice yang diklik. **Sertakan tagihan belum lunas lainnya**
  membuka pilihan invoice milik murid yang sama; tidak otomatis memilih semuanya.
  Pada detail invoice, tombol reminder berada di kelompok aksi kanan.
- Panel menampilkan **Penerima: nama** beserta tujuan **Orang Tua/Murid** dan nomor
  WA, lalu **Detail Tagihan**. Nomor orang tua diutamakan, lalu nomor murid jika
  tidak tersedia atau tidak valid. Perbaikan kontak melalui data murid.
- Pesan boleh diedit untuk satu pengiriman saja; template utama tidak berubah.
  **Kembalikan ke template** membuat preview baru. Edit yang belum dikirim
  meminta konfirmasi sebelum dibuang.
- **Riwayat** menampilkan pesan/nomor/pengirim/waktu yang tercatat saat pengiriman.
  **Siapkan kirim ulang** membuat preview baru dari invoice yang masih belum lunas,
  bukan langsung mengirim pesan lama.
- Preview berlaku 30 menit. Jika data tagihan/kontak berubah, buat ulang pesan dan
  tinjau kembali. Reminder sukses hari ini meminta konfirmasi sebelum dikirim lagi.
- **Dikirim ke provider** bukan bukti WA diterima atau dibaca. Jika hasil belum
  pasti, periksa riwayat dan WhatsApp terlebih dahulu; tidak ada retry otomatis.
  Proteksi klik ganda berlaku untuk tindakan/token yang sama, bukan deduplikasi
  seluruh pengiriman dari panel/browser berbeda.
- Link PDF bertanda tangan berlaku 30 hari, tetapi host aplikasi tetap harus
  dapat diakses perangkat penerima. Host LAN/localhost tidak dapat dianggap
  bisa dibuka dari luar jaringan studio.
- Panel memakai provider yang sudah dikonfigurasi. Tidak perlu migrasi database.
  Reminder massal dan scheduler tetap memakai alur lama; filter/status pengiriman
  lama pada kedua alur tersebut tidak diubah oleh fitur ini.

## Riwayat sesi lintas level — portal guru

- **Riwayat Sesi** mencakup enrollment sesi yang dibuka dan rantai level sebelumnya
  dengan kenaikan berstatus `APPLIED`, termasuk catatan guru terdahulu.
  Kelas paralel dan kenaikan yang dibatalkan tidak digabungkan.
- Catatan level lama diberi label instrumen dan level. Akses tetap melalui sesi
  milik guru utama/pengganti; izin membaca tidak memberikan izin mengedit catatan lama.
- Pilihan bulan tetap bulan sesi dan dua bulan sebelumnya; hanya sesi `HADIR`
  atau `HADIR_TERLAMBAT` yang ditampilkan, selain sesi yang sedang dibuka.
- Laporan progress tetap mengambil catatan dari enrollment dan bulan atribusinya
  sendiri. Riwayat lintas level hanya tampilan, tanpa memindahkan catatan atau
  mengubah snapshot laporan.

## Catatan pengujian — 4 November 2026

Atas permintaan Owner, 12 tes yang gagal dihapus: dua daftar absensi,
dua label split reschedule, empat reset password publik, dua registrasi
publik, dan dua wording navigasi guru. Kode aplikasi tidak diubah.
Penghapusan ini mengurangi cakupan tes, bukan memperbaiki perilaku aplikasi;
penyebab dua kegagalan daftar absensi belum dikonfirmasi penuh.
Suite tersisa dijalankan dengan SQLite `:memory:`: 1.082 tes lulus,
4.869 assertions. Peringatan metadata doc-comment PHPUnit masih ada.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
