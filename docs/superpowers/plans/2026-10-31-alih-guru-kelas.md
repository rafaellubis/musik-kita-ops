# Alih Guru Kelas Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengalihkan guru tetap pada enrollment yang sama sejak tanggal berlaku, dengan perubahan jadwal opsional, tanpa merusak sesi, honor, atau laporan sebelumnya.

**Architecture:** Simpan pengalihan bertanggal efektif dan snapshot sebelum/sesudah; jangan membuat enrollment pengganti. Gunakan satu service untuk pratinjau, konfirmasi, dan penerapan. Generator serta pemeriksaan slot harus memahami periode pengalihan sehingga pemesanan bulan depan dapat menggunakan guru baru sebelum tanggal penerapan tanpa mengubah guru bulan berjalan.

**Tech Stack:** Laravel 11, PHP 8.3, MySQL produksi, SQLite :memory: pengujian, Blade, Tailwind CSS 3, Alpine.js.

**Spec:** docs/superpowers/specs/2026-10-31-alih-guru-kelas-design.md

## Global Constraints
- Enrollment, murid, paket, grade, harga SPP, primary enrollment, dan riwayat cuti tetap sama. Tidak membuat enrollment atau invoice tambahan.
- Default tanggal berlaku adalah tanggal 1 bulan depan; tanggal bulan berjalan diperbolehkan untuk pengalihan mendadak. Tidak mengubah pengajaran yang sudah berlangsung.
- Penanggung jawab laporan bulan pengalihan menjadi guru baru sejak pengalihan berlaku, termasuk jika laporan sudah berisi penilaian. Isi penilaian yang sudah ada dipertahankan, bukan dikosongkan atau dibuat ulang.
- Blokir perubahan sesi yang memengaruhi honor yang sudah dibayar. Tidak menambah mekanisme koreksi selisih antarbulan dalam lingkup ini.
- Semua pengujian database wajib SQLite :memory:, bukan MySQL utama.
- Form Request, pesan validasi dan komentar Bahasa Indonesia; write Owner/Admin, read Owner/Admin/Auditor; tanpa dependency baru.
- Slide-over mengikuti pola Alpine x-show pada wrapper dan panel; tidak menggunakan dialog native.

## Review Focus
- Generator bulan depan sebelum tanggal pengalihan menggunakan penugasan baru; generator ulang tidak menghidupkan slot lama.
- Pengalihan tengah bulan yang mengganti hari mempertahankan hak sesi, tidak sekadar mengambil empat tanggal baru.
- Konfirmasi bersamaan dengan pembayaran honor atau perubahan sesi menolak snapshot yang sudah tidak aman.
- Laporan SUBMITTED berpindah penanggung jawab tetapi isi, nomor, status, dan penguncian submit tetap utuh.
- Guru lama tidak membuat ulang laporan periode pengalihan setelah tanggung jawab berpindah; periode lama tidak menjadi milik guru baru.

## Struktur file
Baru: model EnrollmentTransfer, migration enrollment_transfers, EnrollmentTransferService, StoreEnrollmentTransferRequest, EnrollmentTransferController, command ApplyEnrollmentTransfers, partial transfer-panel, modul JS enrollment-transfer-panel, dan tests/Feature/EnrollmentTransferTest.php.

Ubah sesuai tanggung jawab: Enrollment (relasi dan pengaturan bertanggal), ScheduleConflictDetector (reservasi periode), SessionGeneratorService (pengaturan per tanggal), EnrollmentPromotionService dan ScheduleController (guard perubahan bertumpuk), GuruController (penanggung jawab laporan per periode), routes/web.php, routes/console.php, resources/views/students/partials/tab-kelas.blade.php, resources/views/students/show.blade.php, resources/js/app.js. HonorCalculationService hanya diubah jika API yang ada tidak dapat menghitung ulang satu guru/periode dengan komponen manual tetap. Dokumen spesifikasi diperbarui dengan hasil implementasi.

Sebelum mengubah simbol exported, baca references melalui LSP jika tersedia. Baca ulang kode aktual; jangan mengandalkan nomor baris dari analisis sebelumnya.

### Task 1: Pengalihan bertanggal efektif dan reservasi slot

**Interfaces:** EnrollmentTransferService::preview(Enrollment $enrollment, array $data): array; ::schedule(Enrollment $enrollment, array $data, User $actor): EnrollmentTransfer; ::applyDue(?CarbonInterface $date = null): int. Enrollment::teachingAssignmentOn(CarbonInterface $date): array menghasilkan teacher_id, day_of_week, start_time, end_time, room_id. Semua konsumen memakai resolver yang sama, bukan cabang guru/jadwal versi masing-masing.

- [ ] Tambahkan pengujian perilaku: pengalihan 1 Desember tetap memakai guru A pada 30 November, guru B pada 1 Desember, enrollment dan paket tidak berubah; pemanggilan applyDue dua kali menghasilkan satu penerapan/audit.
- [ ] Jalankan `php artisan test --filter=EnrollmentTransferTest` setelah memastikan phpunit.xml memaksa SQLite :memory:; uji baru harus gagal karena fitur belum ada.
- [ ] Buat migration/model: enrollment_id, source/target_teacher_id, effective_date, status SCHEDULED/APPLIED, before/after snapshot, reason, created_by, applied_at, timestamps. Gunakan lock murid/enrollment dan pemeriksaan pending dalam transaksi untuk mencegah dua perubahan terjadwal. Simpan baseline agar resolver tetap dapat menjawab tanggal historis setelah penerapan.
- [ ] Implementasikan service dan resolver; hanya kelas aktif, guru tujuan berbeda/aktif/mendukung instrumen; waktu selesai dari durasi paket. Tanggal lampau ditolak. Tanggal hari ini hanya memengaruhi sesi yang belum berlangsung; kondisi yang melanggar diblokir, bukan dilewati diam-diam.
- [ ] Tambahkan pengujian slot: guru tujuan tersedia hari ini tetapi sudah terpesan pada periode efektif; tolak. Pastikan slot guru lama sebelum tanggal efektif tetap dipesan dan slot sesudahnya menggunakan guru baru. Terapkan aturan kapasitas/DUO/Kids yang sudah ada.
- [ ] Integrasikan reservasi bertanggal ke detector dan guard perubahan jadwal/kenaikan level. Konflik dengan perubahan terjadwal ditolak dua arah.
- [ ] Jalankan pengujian fitur pengalihan dan suite konflik terkait sekali setelah perubahan task selesai.

### Task 2: Sesi ter-generate, kalender, dan honor

**Consumes:** resolver dan service Task 1. **Produces:** preview dengan before/after, affected_sessions, preserved_sessions, blockers, affected_honor_periods; konfirmasi memvalidasi ulang seluruh pratinjau di bawah lock.

- [ ] Tambahkan pengujian: generator November sebelum penerapan menghasilkan sesi Desember milik guru B; generate ulang tidak menambah atau menghidupkan slot guru A; sesi November dan catatannya tetap.
- [ ] Tambahkan pengujian tengah bulan: dua hak sesi sudah terlaksana sebelum batas; perubahan hari hanya memindahkan hak sesi tersisa. Verifikasi tanggal, guru, sequence/attribution, bukan hanya jumlah baris.
- [ ] Tambahkan kasus libur dengan/tanpa pengganti, cuti inklusif, dan sesi rapel periode lama. Sesi khusus dipertahankan jika tidak konflik; jika konsistensi/hak sesi tidak dapat dipenuhi, kembalikan blocker dengan sesi dan alasan.
- [ ] Jalankan filter pengalihan untuk mengamati kegagalan baru.
- [ ] Ubah generator agar memakai assignment per tanggal/periode, termasuk generateForMonth dan generateForEnrollment. Jangan mengubah state guru rutin lebih awal atau membuat jadwal aktif paralel tanpa batas periode. Bangun rencana sesi sebelum menulis; pertahankan identitas sesi yang dapat diperbarui dengan aman. Perubahan tanggal harus memeriksa seluruh tautan/catatan sebelum pembaruan.
- [ ] Konfirmasi rencana secara atomik; jangan menghapus sesi berisi absensi/catatan. Periksa slot konkret guru/murid/ruangan dan periode rutin, bukan hanya tanggal yang sudah ter-generate.
- [ ] Tambahkan pengujian honor: guru A mempertahankan sesi lama, guru B mendapat sesi yang dialihkan sesuai status honor; slip CALCULATED dihitung ulang dengan komponen manual identik. Sesi LIBUR berhonor dalam slip PAID menolak transaksi tanpa perubahan sesi/slip.
- [ ] Gunakan kalkulasi honor yang sudah ada untuk guru/periode terdampak; jangan membuat slip baru yang belum diperlukan hanya karena pengalihan. Lock dan periksa ulang PAID pada konfirmasi/penerapan.
- [ ] Jalankan suite pengalihan, generator konflik, dan honor terkait setelah task selesai.

### Task 3: Penanggung jawab laporan dan penerapan otomatis

**Consumes:** transfer efektif Task 1, rencana aman Task 2. **Produces:** command `enrollments:apply-transfers` serta perpindahan laporan periode pengalihan dan periode berikutnya tanpa menyentuh periode sebelumnya.

- [ ] Tambahkan pengujian laporan DRAFT yang sudah berisi rating/catatan: saat pengalihan berlaku, teacher_id menjadi guru B; nomor, rating, catatan, rincian sesi, dan identitas penulis sesi tetap. Guru B dapat melanjutkan draft; guru A tidak lagi dapat mengeditnya.
- [ ] Tambahkan kasus SUBMITTED: penanggung jawab berubah tetapi status/submitted_at dan penguncian edit tetap. Laporan periode sebelumnya tidak berpindah. Pembuatan laporan baru memakai guru penanggung jawab periode, bukan sekadar enrollment.teacher_id hari ini.
- [ ] Jalankan filter pengalihan untuk melihat kegagalan baru.
- [ ] Implementasikan perpindahan laporan dan audit saat applyDue. Sesuaikan laporanStore dan daftar pilihan periode/kepemilikan di GuruController agar guru lama tidak dapat membuat ulang laporan yang dialihkan dan guru baru tidak otomatis memiliki periode lama. Jangan mengganti penulis SessionTeacherNote atau menjalankan sinkronisasi destruktif hanya untuk mengganti teacher_id laporan.
- [ ] Daftarkan command harian 00:00 di routes/console.php mengikuti pola promotions; hubungkan applyDue pada entrypoint generator yang relevan tanpa rekursi. Jika keadaan berubah menjadi tidak aman pada hari penerapan, pertahankan konsistensi transaksi dan tampilkan blocker yang dapat ditindaklanjuti; jangan menandai APPLIED.
- [ ] Uji command dengan waktu dibekukan di SQLite dan ulangi pemanggilan untuk membuktikan idempotensi; jalankan suite laporan guru terkait.

### Task 4: Form, pratinjau, riwayat, dan bukti end-to-end

**Interfaces:** POST students/{student}/enrollments/{enrollment}/transfer-preview dan POST .../transfers; nama route students.enrollments.transfer-preview dan students.enrollments.transfers.store. Controller memeriksa ownership dan menggunakan Form Request/service, bukan menduplikasi logika domain.

- [ ] Tambahkan pengujian endpoint: Admin/Owner dapat mengajukan; Auditor/Guru ditolak; enrollment murid lain ditolak; guru tidak sesuai instrumen dan snapshot basi tidak tersimpan.
- [ ] Jalankan filter pengalihan untuk kegagalan endpoint baru.
- [ ] Buat Form Request, controller, route, dan panel `Alih Guru Kelas`. Field: guru asal readonly, guru tujuan, tanggal berlaku default awal bulan depan, hari/jam/ruang terisi data sekarang, alasan. Tampilkan dampak sesi/laporan/honor serta blocker sebelum konfirmasi. Perubahan input membatalkan pratinjau lama.
- [ ] Tambahkan modul Alpine melalui app.js; focus trap, pengembalian fokus, scroll lock, Escape guard, state tidak dikosongkan selama animasi keluar, error Bahasa Indonesia. Tampilkan pengalihan terjadwal dan riwayat pada kelas, tanpa memindahkan file lama atau mengganti UI lain.
- [ ] Jalankan `npm run build`; jalankan seluruh suite PHP satu kali dengan SQLite :memory: setelah integrasi selesai.
- [ ] Smoke aplikasi aktual menggunakan database SQLite sementara yang terisolasi, bukan menulis database utama: jadwalkan pengalihan bulan depan, lihat pratinjau/riwayat, terapkan dengan clock terkontrol, periksa jadwal guru B dan laporan berisi penilaian; pastikan guru A kehilangan akses edit laporan tersebut dan sesi lama tetap miliknya. Lakukan satu percobaan konflik yang ditolak. Ambil bukti visual panel dan hasil, lalu tutup browser.
- [ ] Perbarui spesifikasi dengan cara penggunaan, perintah scheduler, batas honor PAID, serta hasil verifikasi aktual. Hapus hanya scaffolding/smoke sementara yang dibuat untuk pekerjaan ini. Jangan melakukan migrasi database utama atau commit/push tanpa instruksi pengguna.

## Hasil pelaksanaan
Implementasi native dilakukan di checkout utama sesuai pilihan pengguna. Task 1–4 diimplementasikan; rincian bukti dan batas operasional ada pada bagian akhir spesifikasi. Review menemukan lima masalah (opsi ruang reaktif, honor PAID guru tujuan, berbagi slot konkret, reservasi murid lintas kelas, serta pasangan sesi libur terlindungi); semuanya diperbaiki dan diverifikasi.

Pengujian terkait: 45 lulus / 149 assertion. Suite penuh terakhir: 1.023 lulus, 17 gagal / 4.642 assertion pada tujuh kelompok pengujian lain; suite global belum hijau. Build dan smoke UI SQLite terisolasi berhasil. Tidak menjalankan migrasi utama, commit, atau push; config/session_report_wa.php milik pengguna tidak diubah.
