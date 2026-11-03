# Desain kenaikan level kelas murid

Status: disetujui pengguna; implementasi native pada main. Migrasi operasional memerlukan MySQL Laragon aktif.
Tanggal: 2026-10-31.

## Tujuan dan keputusan pengguna

Owner/Admin dapat menaikkan level satu kelas murid berdasarkan evaluasi guru, tanpa wajib melalui ujian. Kenaikan berlaku tanggal 1 bulan berikutnya, tanpa prorata bulan berjalan. Owner/Admin dapat memilih guru, hari, jam, dan ruangan baru. Sesi reguler level lama yang sudah dibuat untuk periode mendatang diganti dengan sesi level baru. Riwayat pembelajaran lama tetap dapat diakses. Invoice bulan depan yang telah dibayar sebagian atau lunas dipertahankan dan dibuatkan tagihan selisih.

## Cakupan

- Kelas REGULER, instrumen tetap sama, tujuan boleh level mana pun yang lebih tinggi dalam urutan BASIC → L1 → L2 → L3 → L4. Level sama/turun ditolak (revisi disetujui 3 November 2026).
- Berlaku per enrollment; kelas lain milik murid tidak berubah.
- Owner/Admin melakukan pengajuan; Auditor hanya membaca riwayat sesuai akses yang sudah ada.
- Tidak mencakup ujian, kenaikan massal, pindah instrumen, perubahan tarif di tengah bulan, atau pembuatan level di atas master yang tersedia.
- Tidak mengubah master paket lama atau menimpa package_id enrollment historis.

## Kondisi kode yang menjadi dasar

Penelusuran sebelumnya menemukan struktur enrollment dengan effective_date/end_date dan relasi jadwal, sesi, cuti, serta rapor. AttendanceService mengambil paket melalui enrollment; InvoiceService membuat SPP berdasarkan paket enrollment. Modul event aktual hanya menerima Mini Concert, bukan ujian grade. Karena itu, fitur tidak bergantung pada modul ujian.

Temuan bersumber dari kode, bukan konfirmasi database aktif. Percobaan route:list sebelumnya timeout; belum ada bukti runtime fitur kenaikan level.

## Form dan konfirmasi

Aksi Naik Level berada pada kelas terpilih di tab Kelas detail murid. Gunakan slide-over Alpine sesuai pola proyek, tanpa dialog native.

Form memuat level asal, dropdown level tujuan, tanggal efektif otomatis, pilihan guru, hari/jam, ruangan, dan catatan evaluasi guru opsional (maksimal 2.000 karakter). Catatan yang tidak dikirim atau kosong disimpan sebagai string kosong tanpa mengubah skema database. Tujuan hanya paket Reguler aktif dengan instrumen sama dan level lebih tinggi, diurutkan menurut hierarki level. Default satu tingkat berikutnya; bila paket tingkat itu tidak tersedia/nonaktif, tampilkan “Pilih level tujuan” dan wajib dipilih manual, bukan otomatis melompat. L4 tidak menawarkan kenaikan. Guru/jadwal/ruang lama dapat menjadi pilihan awal, tetapi pengguna dapat menggantinya. Mengganti tujuan membatalkan pratinjau sebelumnya.

Konfirmasi memperlihatkan perubahan tarif SPP/honor, sesi yang akan dihapus dan dibuat, jadwal baru, serta penyesuaian invoice. Jangan memperlakukan tampilan pratinjau sebagai validasi final: periksa ulang keadaan saat penyimpanan.

## Transisi kelas

Gunakan enrollment penerus untuk paket level baru; enrollment lama tetap menjadi sumber riwayat. Simpan hubungan asal/penerus dan rekaman kenaikan secara eksplisit agar invoice, riwayat, dan pencegahan pengajuan ganda dapat menelusuri satu perubahan yang sama.

Pengajuan menyiapkan periode kelas baru tanpa mengganti kelas aktif bulan berjalan. Enrollment lama berakhir pada batas tanggal efektif; enrollment penerus mulai pada batas yang sama. Selaraskan arti end_date dengan kode generator yang memperlakukan batas akhir secara eksklusif.

Jadwal lama berlaku sampai batas kenaikan. Jadwal baru dan pemeriksaan konflik harus memahami tanggal efektif, sehingga tidak mematikan jadwal bulan berjalan atau salah menganggap jadwal asal sebagai konflik dengan penggantinya. Penanda primary harus menunjuk kelas yang berlaku pada waktunya, bukan langsung pindah ketika pengajuan dibuat. Peralihan harus aman dijalankan ulang dan diselaraskan sebelum generator sesi/SPP menggunakan kelas periode baru.

Jangan memakai alur penghentian kelas biasa yang dapat memundurkan murid. Status murid tetap mengikuti kelas yang benar-benar berjalan. Tolak pengajuan ganda terhadap kelas/periode yang sudah memiliki kenaikan terjadwal.

## Sesi dan jadwal mendatang

- Sesi bulan berjalan dan sebelumnya tidak diubah.
- Hapus sesi reguler otomatis asal mulai tanggal efektif yang belum memiliki absensi/catatan operasional. Jangan membatasi pemeriksaan hanya pada satu bulan bila sesi lebih jauh sudah tergenerate.
- Buat sesi level baru untuk periode pengganti yang sebelumnya sudah tergenerate, minimal bulan efektif, memakai guru/jadwal/ruang pilihan serta aturan kalender akademik, kuota sesi, dan cuti yang berlaku. Periode lebih jauh yang belum digenerate tetap mengikuti proses generator normal.
- Sesi LIBUR otomatis beserta pengganti kalendernya ditangani sebagai satu rangkaian. Hilangkan rangkaian lama yang aman diganti dan hitung kembali honor libur berdasarkan jadwal/paket baru. Honor LIBUR otomatis saja bukan bukti sesi telah berlangsung; slip PAID yang mencakupnya tetap harus dilindungi.
- Jangan menghapus sesi yang sudah mempunyai absensi, catatan pembelajaran, atau keterkaitan honor dibayar. Tampilkan konflik dan tolak seluruh pengajuan sampai diperiksa.
- Sesi rapel/reschedule dari hak periode lama tetap tersimpan pada kelas lama dengan dasar tarif asal, walaupun tanggal pelaksanaannya setelah kenaikan. Periksa konflik sesi tersebut dengan jadwal baru.
- Sesi tambahan manual periode mendatang menjadi konflik untuk diperiksa; jangan ikut menghapus otomatis.
- Cuti terjadwal/berjalan yang melintasi batas kenaikan tidak boleh hilang atau menghasilkan sesi pada tanggal cuti. Sebagai batas aman rancangan awal, tolak pengajuan bila kelas memiliki cuti yang menyentuh periode sejak tanggal efektif; selesaikan atau sesuaikan cuti melalui alur yang sah terlebih dahulu. Tidak melakukan migrasi riwayat cuti diam-diam.

## Tagihan

- Invoice periode sebelum kenaikan, pembayaran, dan kuitansi tetap utuh.
- Bila invoice SPP periode baru belum ada, generator membuat SPP level baru satu kali.
- Bila invoice periode baru belum memiliki pembayaran, diskon, atau denda, perbarui item SPP pada invoice yang sama ke harga baru, arahkan enrollment ke kelas penerus, dan hitung ulang total/status. Pertahankan item lain dan nomor invoice.
- Bila invoice belum dibayar tetapi memiliki diskon/denda pada SPP, tolak pengajuan dengan penjelasan agar penyesuaian diperiksa terlebih dahulu; jangan menghapus diskon/denda otomatis.
- Bila invoice periode baru telah dibayar sebagian atau lunas, pertahankan invoice/pembayaran asal. Buat invoice penyesuaian terpisah sebesar selisih harga paket baru dikurangi harga paket lama. Sisa utang invoice asal tetap berlaku; diskon lama tidak dihitung ulang atau dibatalkan otomatis.
- Tagihan selisih terhubung ke enrollment penerus, invoice asal, periode, dan rekaman kenaikan. Tampilkan deskripsi penyesuaian, bukan SPP bulanan penuh atau cicilan Reguler. Pakai mekanisme item/metadata yang sudah ada bila memadai.
- Untuk periode yang sudah memiliki invoice asal plus penyesuaian, generator tidak boleh menerbitkan SPP penuh tambahan untuk enrollment penerus. Periode berikutnya kembali memakai SPP penuh level baru.
- Selisih nol tidak membuat invoice nol. Bila master paket tujuan lebih murah, tolak pengajuan untuk pemeriksaan harga; fitur ini tidak mencakup refund/kredit.
- Periode dan jatuh tempo mengikuti aturan SPP periode yang disesuaikan. Tidak menghasilkan tagihan registrasi baru.

## Riwayat sesi dan laporan

Enrollment lama, sesi, absensi, catatan, rapor, dan nominal honor tersimpan tetap utuh. Riwayat murid harus dapat dibuka lintas enrollment, termasuk enrollment historis, serta dibedakan berdasarkan instrumen, level, dan periode.

Laporan bulan sebelum kenaikan tetap menggunakan kelas lama; bulan efektif memakai kelas baru. Sesi rapel kelas lama tetap dikenali sebagai bagian kelas asal. Daftar riwayat tidak boleh hanya mengambil kelas ACTIVE.

Telusuri tampilan/cetak laporan sesi, rapor, dan honor yang membaca master terkini. Untuk data baru yang membutuhkan identitas historis, simpan snapshot minimum level/paket/harga relevan pada rekaman kenaikan atau dokumen terkait. Jangan mengklaim snapshot yang tidak tersedia pada data lama dan jangan mengarang nilai historis saat migrasi. Kenaikan sendiri tidak boleh mengubah identitas enrollment lama.

## Validasi, transaksi, dan audit

Validasi melalui Form Request dengan pesan Bahasa Indonesia. Validasi kepemilikan enrollment, akses Owner/Admin, kelas aktif Reguler, paket tujuan aktif dengan level lebih tinggi di instrumen sama, kecocokan guru/ruang, konflik waktu efektif, dan ketiadaan pengajuan ganda. Hierarki level menentukan kenaikan, bukan harga atau urutan alfabet; harga tujuan lebih rendah tetap ditolak karena fitur tidak mencakup refund/kredit.

Perubahan enrollment, rekaman kenaikan, jadwal, penggantian sesi, invoice, dan audit harus atomik. Kunci data yang relevan dan periksa ulang pembayaran/absensi/konflik saat menyimpan. Jika pembuatan sesi baru gagal, sesi lama serta invoice harus tetap seperti semula. Pengajuan yang berhasil diproses tidak boleh menghasilkan kelas/sesi/tagihan ganda jika dikirim ulang.

Audit mencatat pelaksana, catatan evaluasi, tanggal efektif, asal/penerus, level/harga, guru/jadwal/ruang sebelum-sesudah, sesi yang dihapus/dibuat, dan invoice yang diperbarui atau dibuat. Metadata sesi yang dihapus harus cukup untuk menjelaskan perubahan jadwal, bukan sekadar jumlah baris.

## Koreksi pengajuan melalui pembatalan

- Owner/Admin dapat membatalkan kenaikan berstatus SCHEDULED hanya sebelum tanggal berlaku, cukup melalui konfirmasi tanpa mengisi alasan. Kenaikan APPLIED atau yang sudah memasuki tanggal berlaku tidak dapat dibatalkan.
- Pembatalan bukan pengeditan enrollment historis. Rekaman kenaikan tetap tersimpan sebagai CANCELLED beserta pelaksana dan waktu; catatan sistem dipakai untuk audit/void invoice, bukan alasan yang diminta dari pengguna. Enrollment penerus tetap INACTIVE, jadwalnya dinonaktifkan, dan tidak dimasukkan ke daftar kelas berhenti.
- Dalam satu transaksi, hapus sesi penerus yang masih aman, pulihkan snapshot sesi asal dengan ID/relasi/honor semula, kosongkan batas akhir kelas asal, dan hitung ulang slip honor belum dibayar yang terdampak. Primary kelas berjalan tidak berubah. Konflik slot lama, absensi/catatan/perubahan sesi, laporan/cuti/pengalihan penerus, atau honor PAID menolak seluruh pembatalan.
- Invoice yang diperbarui dipulihkan hanya bila snapshot lengkap dan isinya belum berubah. Invoice selisih yang belum dibayar di-void, bukan dihapus; pembayaran serta invoice asal berbayar tetap utuh. Pembayaran pada invoice terdampak, termasuk riwayat pembayaran yang sudah di-void, atau perubahan diskon/denda/item/header menolak pembatalan otomatis.
- Invoice lain yang terbit untuk penerus setelah pengajuan harus diperiksa dan di-void melalui alur existing terlebih dahulu. Kenaikan lama dengan mode UPDATE tanpa snapshot pemulihan ditolak; mode DIFFERENCE lama hanya dapat dibatalkan bila tagihan cocok dengan bentuk penyesuaian asli yang aman.
- Setelah berhasil, tombol Naik Level tersedia kembali pada kelas asal. Pengajuan baru membuat rekaman berbeda, tanpa menghapus percobaan yang dibatalkan. Migration `2026_11_03_000001_allow_cancelled_enrollment_promotions.php` mengganti unique source enrollment dengan index biasa; unique successor tetap dipertahankan. Rollback migration ditolak bila sudah terdapat beberapa rekaman untuk sumber yang sama.

## Kriteria penerimaan dan verifikasi implementasi

1. Owner/Admin dapat menjadwalkan kenaikan satu kelas untuk awal bulan depan dengan guru, slot, dan ruang baru; Auditor tidak dapat menulis.
2. Kelas bulan berjalan dan primary aktif tidak berubah prematur; transisi tanggal efektif tidak memundurkan murid.
3. Sesi mendatang asal yang aman diganti hilang dan sesi baru tepat, tanpa duplikasi; kegagalan pembuatan membatalkan seluruh perubahan.
4. Konflik guru/ruang, sesi berisi catatan, slip PAID, sesi manual, dan cuti lintas batas ditolak tanpa kehilangan data.
5. Rapel/reschedule lama tetap dapat dilaksanakan dan memakai dasar tarif lama, termasuk ketika guru baru berbeda.
6. Invoice tanpa pembayaran sederhana diperbarui; invoice berbayar dipertahankan dengan satu tagihan selisih. Pembayaran parsial, diskon, selisih nol, dan pencegahan SPP ganda terverifikasi.
7. History layar/cetak, catatan sesi, rapor, dan honor lama tetap dapat diakses serta tidak berganti ke level/guru baru akibat kenaikan.
8. Generator sesi/SPP dan pengajuan ulang tidak menggandakan kelas, sesi, atau tagihan.
9. Verifikasi melalui skenario runtime serta pengujian perilaku relevan menggunakan SQLite in-memory; jangan menjalankan tes terhadap MySQL utama. Verifikasi UI aktual untuk alur panel dan history.

## Batas persetujuan

Keputusan inti dan rencana implementasi disetujui melalui percakapan. Pengguna memilih implementasi native langsung pada main.

## Keputusan implementasi

- Satu `EnrollmentPromotionService` mengoordinasikan pengajuan dan transisi; generator lama dipakai melalui jalur per enrollment. Tidak dibuat repository, event bus, atau dependency baru.
- Klasifikasi/penghapusan sesi berada di service kenaikan, bukan `purgeFutureSessions()` yang memiliki kontrak cutoff berbeda. Rapel mengikuti attribution/origin sesi lama, termasuk setelah kelas asal COMPLETED; reschedule kelas penerus masih dikunci sebelum tanggal berlaku.
- Generator sesi/SPP memakai scope periode half-open. Perbandingan tanggal memakai `whereDate`; slot waktu dinormalisasi ke detik. Konflik sesi konkret dengan `schedule_id=NULL` tetap diperiksa.
- Invoice aktivasi legacy tanpa enrollment dikenali melalui student dan metadata package. Pembayaran valid dan jumlah item menjadi sumber saldo, bukan status/cache lama. Invoice selisih tidak dianggap sebagai SPP penuh.
- Sebelum void invoice asal, Owner harus void tagihan selisih terlebih dahulu melalui alur existing, termasuk void pembayaran selisih bila ada. Ini mencegah tagihan selisih aktif tertinggal saat SPP penuh digenerate ulang. Void selisih saja tidak memicu duplikat SPP penuh; tidak ada refund/recovery otomatis.
- Mutasi jadwal, penghentian, cuti, sesi manual, dan reschedule mengambil lock murid di dalam transaksi sebelum memeriksa kenaikan terjadwal. Pengajuan mengunci guru/ruang/sesi/invoice; SQLite tidak membuktikan perilaku lock MySQL.
- Middleware sinkronisasi hanya dijalankan untuk pengguna terautentikasi. Halaman tamu tidak memerlukan akses tabel kenaikan.
- Panel mengikuti tema aktual mahogany-mint tunggal, bukan toggle dark/light yang sudah tidak dipakai layout. Logic Alpine berada di modul JavaScript yang didaftarkan pada `app.js`.
- Dokumen hasil pengujian dan petunjuk deploy berada di `docs/walkthrough_deploy.md`. Tidak ada perubahan pada konfigurasi WhatsApp milik pengguna atau commit otomatis.
