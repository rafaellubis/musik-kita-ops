# Edit Kelas

## Tujuan
Mengalihkan tanggung jawab kelas/enrollment yang sama dari guru A ke guru B secara tetap sejak tanggal berlaku, dengan opsi sekaligus mengganti hari, jam, dan ruangan. Desain dan pelaksanaan native disetujui pengguna.

## Lingkup
- Aksi `Edit Kelas` di header Kelas Berjalan, tepat di kanan `+ Tambah Kelas` dengan gaya tombol yang sama; hanya Owner/Admin dapat mengajukan perubahan, Auditor hanya membaca. Klik tombol untuk membuka dropdown kelas; memilih kelas langsung membuka panel edit. Dropdown ditutup dengan klik di luar, klik tombol lagi, atau Escape.
- Enrollment, murid, paket, grade, harga SPP, primary enrollment, dan riwayat cuti tetap sama. Tidak membuat enrollment atau invoice tambahan.
- Guru tujuan wajib berbeda dari guru asal, aktif, dan mendukung instrumen kelas.
- Default tanggal berlaku adalah tanggal 1 bulan depan; tanggal bulan berjalan diperbolehkan untuk pengalihan mendadak. Tidak mengubah pengajaran yang sudah berlangsung.
- Hari, jam mulai, dan ruangan terisi pengaturan sekarang dan dapat diubah bersamaan. Jam selesai dihitung dari durasi paket.
- Alasan pengalihan wajib, tersimpan bersama pelaku, waktu, serta nilai sebelum/sesudah.
- Pergantian guru sementara per sesi tetap memakai alur guru pengganti, bukan fitur ini.

## Alur
1. Pilih kelas dan buka slide-over mengikuti pola Alpine x-show + x-transition proyek, tanpa elemen dialog native.
2. Isi guru tujuan, tanggal berlaku, alasan, dan perubahan jadwal opsional.
3. Pratinjau menampilkan perubahan, sesi terdampak, sesi khusus yang dipertahankan, konflik, serta dampak honor/laporan.
4. Konfirmasi memvalidasi ulang keadaan terbaru dan menyimpan perubahan secara atomik. Konflik tidak boleh menghasilkan penyimpanan parsial.
5. Sebelum tanggal berlaku, guru/jadwal rutin lama tetap berlaku. Sejak tanggal berlaku, pengaturan baru dipakai seterusnya, termasuk oleh generator sesi bulan berikutnya.
6. Tampilkan pengalihan terjadwal dan riwayatnya. Cegah pengajuan bertumpuk atau konflik dengan kenaikan level terjadwal; pengguna menyelesaikan perubahan yang sudah terjadwal terlebih dahulu.

## Sesi dan konflik
- Sesi historis dan sesi yang sudah diabsen tidak diubah otomatis.
- Sesi reguler mendatang berstatus SCHEDULED tanpa catatan atau penyesuaian khusus dapat disesuaikan sejak tanggal berlaku.
- Jika hanya guru berubah, tanggal sesi tetap. Jika hari berubah, susun kembali tanggal sesi terdampak tanpa menambah jatah atau menghilangkan hak sesi bulan berjalan.
- Hormati cuti kelas inklusif, kalender libur, tanggal pengganti, dan batas sesi bulanan.
- Rapel, reschedule, split, sesi manual, guru pengganti yang sudah ditentukan, dan sesi berisi catatan tidak ditimpa otomatis. Tampilkan sebagai pengecualian; perubahan yang tidak dapat menjaga konsistensinya diblokir dengan alasan konkret.
- Sesi LIBUR perlu dievaluasi beserta honor dan sesi penggantinya; bukan diperlakukan seperti SCHEDULED biasa.
- Validasi konflik guru, murid, dan ruangan terhadap jadwal rutin maupun sesi konkret. Ikuti kapasitas, dukungan instrumen, serta aturan berbagi slot yang memang sudah berlaku pada kode aktual.
- Generator ulang tidak boleh menghidupkan kembali slot lama atau menggandakan sesi setelah pengalihan.

## Honor
- Hak honor ditentukan oleh pengajar pada masing-masing sesi; riwayat guru lama tidak diganti dengan guru rutin terbaru.
- Hitung ulang slip guru asal/tujuan yang terdampak jika belum PAID, tanpa menghapus komponen honor manual.
- Jangan menganggap sesi mendatang telah terlaksana; kelayakan honor tetap mengikuti aturan status sesi yang berlaku.
- Blokir perubahan sesi yang memengaruhi honor yang sudah dibayar. Tidak menambah mekanisme koreksi selisih antarbulan dalam lingkup ini.
- Pratinjau dan konfirmasi harus memeriksa ulang status pembayaran agar perubahan tidak melewati penguncian PAID.

## Laporan
- Catatan per sesi tetap melekat pada sesi dan penulis aslinya. Laporan periode sebelum bulan pengalihan tidak dialihkan atau ditulis ulang.
- Laporan bulanan memiliki student_id, enrollment_id, teacher_id, bulan, dan tahun. Sinkronisasi rinciannya berdasarkan enrollment serta periode atribusi, bukan guru rutin terbaru.
- Penanggung jawab laporan bulan pengalihan menjadi guru baru sejak pengalihan berlaku, termasuk jika laporan sudah berisi penilaian. Isi penilaian yang sudah ada dipertahankan, bukan dikosongkan atau dibuat ulang.
- Guru baru mendapat akses laporan bulan pengalihan sebagai penanggung jawab. Pergantian penanggung jawab dicatat dalam audit; tidak mengubah identitas penulis catatan sesi guru lama.
- Akses terhadap laporan periode sebelumnya tetap mengikuti izin yang ada; pengalihan tidak otomatis memperluas akses seluruh riwayat.

## Acuan kode yang telah ditelusuri
- app/Http/Controllers/ScheduleController.php: update jadwal dan sinkronisasi sebagian sesi mendatang.
- app/Http/Controllers/SessionController.php: edit satu sesi SCHEDULED.
- app/Services/SessionGeneratorService.php: generator berdasarkan enrollment/jadwal dan tanggal.
- app/Services/EnrollmentPromotionService.php: pola pratinjau, perubahan terjadwal, perlindungan sesi, dan audit.
- app/Services/HonorCalculationService.php: agregasi honor serta penguncian slip PAID.
- app/Services/SessionNoteSyncService.php: rincian laporan berdasarkan enrollment dan bulan atribusi.
- app/Models/ProgressReport.php dan app/Models/SessionTeacherNote.php: identitas laporan dan penulis catatan.

## Verifikasi implementasi yang diperlukan
- Pengalihan bulan depan tidak mengubah sesi bulan berjalan; pengalihan tengah bulan mempertahankan sesi sebelum batas.
- Sesi sudah ter-generate berubah sesuai lingkup tanpa duplikasi setelah generator dijalankan lagi.
- Konflik konkret/rutin menolak seluruh transaksi; sesi khusus, cuti, libur, dan hak sesi tetap terlindungi.
- Guru lama/baru mendapat honor yang tepat; komponen manual tetap; slip PAID tidak berubah.
- Riwayat catatan/laporan mempertahankan guru aslinya; akses tidak meluas tanpa keputusan eksplisit.
- Smoke melalui UI aktual untuk pratinjau, konfirmasi, dan hasil pengalihan; build aset setelah perubahan utility/JS/CSS.
- Semua pengujian database wajib SQLite :memory:, bukan MySQL utama.

## Bukan lingkup
Perubahan paket/grade, invoice baru, koreksi data historis, penyesuaian honor dibayar, serta refactor umum sistem penjadwalan.

## Penggunaan dan operasional
1. Buka detail murid → **Kelas** → **Edit Kelas** pada header Kelas Berjalan, lalu pilih kelas dari dropdown yang muncul. Kelas harus aktif dan memiliki satu jadwal aktif.
2. Pilih guru baru dan tanggal berlaku; ubah hari, jam, atau ruangan bila diperlukan, lalu isi alasan.
3. Klik **Periksa pengalihan**, tinjau sesi terdampak serta pengecualiannya, lalu **Konfirmasi pengalihan**. Mengubah input membatalkan pratinjau sebelumnya.
4. Riwayat kelas menampilkan guru asal/tujuan dan tanggal berlaku. Jadwal rutin lama tetap terlihat sebelum tanggal tersebut; sesi bulan depan dapat disiapkan dengan guru baru.
5. Scheduler menjalankan `php artisan enrollments:apply-transfers` setiap hari pukul 00:00. Penerapan juga diperiksa pada request terautentikasi dan generator bulanan.
6. Bila guru/ruang menjadi tidak tersedia sebelum penerapan, pengalihan tetap terjadwal dan alasan penghambat tampil di riwayat. Perbaiki ketersediaan sumber daya sebelum mencoba penerapan lagi.

Pengalihan tidak mengubah rincian honor berbayar, termasuk penambahan honor LIBUR pada slip guru tujuan yang sudah PAID. Koreksi honor berbayar bukan bagian fitur ini. Laporan SUBMITTED tetap terkunci meskipun penanggung jawabnya berpindah.

## Bukti verifikasi
- Pengujian regresi pengalihan, enrollment, dan jadwal: 45 pengujian / 149 assertion lulus setelah perbaikan review.
- Smoke browser memakai SQLite `:memory:`: pratinjau empat sesi November, konfirmasi, riwayat terjadwal, penerapan dengan clock 1 November, serta guru rutin dan penanggung jawab laporan berubah ke Guru B. Rating 4 dan catatan Guru A tetap tersimpan.
- Konflik ruangan menolak pratinjau. Panel diperiksa pada viewport 1440×1000 dan 390×844; tidak ada overflow horizontal panel, Escape mengembalikan fokus dan melepas scroll lock.
- Build Vite berhasil. Migrasi database utama, commit, dan push tidak dijalankan.
- Suite penuh terakhir: **1.023 lulus, 17 gagal, 4.642 assertion**. Kegagalan berada pada AbsensiControllerTest (2), CheckOverdueStudentsTest (3), SplitRescheduleTest (2), PasswordResetTest (4), RegistrationTest (2), GuruNavigationTest (2), dan KalenderControllerTest (2). Belum ada pembandingan baseline untuk menyatakan kegagalan tersebut sudah ada sebelumnya; tidak mengubah perilaku modul lain untuk meloloskannya.
