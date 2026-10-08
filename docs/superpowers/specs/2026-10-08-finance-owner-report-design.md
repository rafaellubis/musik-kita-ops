# Laporan Keuangan Owner Musik KITA

Tanggal: 8 Oktober 2026  
Status: desain percakapan disetujui; spesifikasi tertulis menunggu review pengguna.  
Mode UI: Operate. Stack: Laravel, Blade, Tailwind, Alpine; tidak mengganti stack atau tema aplikasi.

## 1. Tujuan dan keputusan pengguna

Owner dapat memahami hasil usaha, penyebab perubahan, sumber penerimaan kelas/event, metode penerimaan, dan rincian pengeluaran. Layar dan export harus memakai periode serta definisi angka yang sama dan dapat ditelusuri ke dokumen sumber.

Keputusan yang disetujui:
- Tiga tab: Hasil Usaha, Kas & Kewajiban, Event.
- Basis kas; label utama Hasil Usaha Sebelum Pajak. Top-up petty cash bukan beban usaha.
- PDF dan Excel untuk laporan keuangan.
- Penerimaan tunai/non-tunai berarti total penerimaan dalam periode, bukan saldo aktual uang tersedia.
- Nominal Cash, Transfer, QRIS, Debit, dan kelompok non-tunai/tunai dapat diklik untuk melihat transaksi, termasuk kelas dan event.
- Komponen pengeluaran dapat diklik: Honor Guru, Gaji Staff, Operasional, Petty Cash. Setiap komponen memiliki PDF sendiri.
- Profil wajib pajak belum diketahui. Rekap tahunan hanya pendukung pelaporan pajak, bukan perhitungan pajak otomatis, SPT, atau bukti pelaporan DJP.

## 2. Bukti kondisi kode

- `ReportController::buildFinanceData()` sudah menghitung pembayaran valid berdasarkan payment_date, termasuk pembayaran event. Invoice banyak item MC tidak membuat total pembayaran berulang.
- Honor guru dibayar mengikuti paid_at; gaji staff saat ini mengikuti periode slip yang berstatus PAID, sehingga perlu cutover ke paid_at untuk laporan basis kas.
- Perhitungan lama mengurangi top-up petty cash, sesuai perspektif kas owner pada spesifikasi Juli. Desain ini mengganti definisinya menjadi hasil usaha basis kas studio, bukan mempertahankan top-up sebagai beban.
- `EventFinanceService::summary()` menyediakan tagihan, penerimaan, piutang, estimasi/final honor. cash_margin mengurangi honor POSTED, bukan honor terbukti dibayar; tidak boleh digunakan sebagai laba bersih event.
- `Expense` terhubung ke slip staff melalui expense_id. `Expense` dan `PettyCashExpense` belum menyediakan event_id pada field model yang diperiksa; biaya operasional per event tidak boleh ditebak dari teks deskripsi.
- `StaffPayrollService::markPaid()` membuat Expense dengan tanggal akhir periode slip, sementara paid_at mengikuti saat dibayar. Laporan memakai paid_at untuk slip terkait, tidak mengandalkan expense_date tersebut.
- `resources/views/petty-cash/print.blade.php` sudah menyediakan saldo awal, total top-up, total pengeluaran, saldo akhir, dan mutasi debit/kredit/saldo berjalan. Gunakan kembali laporan dan sumber datanya.
- `app/Exports/XlsxBuilder.php` sudah menghasilkan workbook multi-sheet; gunakan kembali, tanpa dependency spreadsheet baru hanya untuk export ini.
- Tema aktual pada tailwind.config.js adalah mahoni/mint, dengan Hanken Grotesk/DM Sans dan Playfair Display. Pertahankan token/theme runtime; jangan mengganti palet global berdasarkan rekomendasi dashboard generik.

## 3. Batas lingkup

Termasuk redesign layar laporan, penyelarasan perhitungan basis kas, rincian transaksi, PDF lengkap/komponen, Excel, filter bulanan/tahunan/rentang tanggal, dan perbandingan periode.

Tidak termasuk jurnal akuntansi umum, basis akrual, neraca, saldo/rekening bank aktual, rekonsiliasi bank, integrasi Coretax/DJP, tarif pajak, pengiriman SPT, atau input pajak yang diasumsikan. Tidak ada penambahan tabel pendapatan kedua. Tidak ada klasifikasi fiskal otomatis dari nama kategori.

Pembelian aset, modal, pengambilan pribadi owner, pajak, atau kategori pengeluaran campuran tidak otomatis dianggap beban operasional final. Data yang belum mendukung pemisahan tersebut diberi catatan eksplisit, bukan klaim laporan akuntansi/fiskal lengkap. Desain tidak membuat perkiraan nominal untuk menutup data yang hilang.

## 4. Periode dan sumber perhitungan

- Filter default bulan berjalan. Preset bulan dan tahun serta rentang tanggal dengan batas inklusif; tanggal mulai tidak boleh setelah tanggal akhir.
- Request memakai validasi terpusat dengan pesan Bahasa Indonesia. Web, detail, cetak, dan Excel memakai filter yang sama. URL bulan/tahun yang sudah ada tetap menentukan periode yang sah.
- Perbandingan bulanan memakai bulan kalender sebelumnya; tahunan memakai tahun sebelumnya; rentang khusus memakai jumlah hari sama tepat sebelum tanggal awal. Nama/tanggal kedua periode selalu terlihat.
- Perubahan rupiah = periode terpilih dikurangi pembanding. Persentase hanya ditampilkan bila nilai pembanding positif; nol atau negatif memakai tanda em dash dan penjelasan, bukan persentase menyesatkan.
- Total, rincian, dan export berasal dari satu perhitungan bersama. Jangan memanggil ringkasan all-time event lalu menjumlahkannya kembali sebagai penerimaan periode.
- Nominal uang dihitung sebagai integer rupiah, bukan float untuk penjumlahan.

### 4.1 Penerimaan

Sumber: Payment berdasarkan payment_date dalam periode, tidak void. Invoice void tidak boleh menjadi penerimaan laporan; kebijakan ini konsisten dengan invoice valid pada ringkasan event.

Setiap pembayaran masuk total tepat sekali. Metode tersedia: CASH, TRANSFER, QRIS, DEBIT, termasuk metode tanpa transaksi bernilai nol.

- Tunai = CASH.
- Non-tunai = TRANSFER + QRIS + DEBIT.
- Kelompok ini merupakan penerimaan bruto menurut catatan pembayaran, bukan saldo rekening, bukan nominal settlement QRIS/debit neto.
- Empat metode harus menjumlah ke total penerimaan. Kelompok non-tunai/tunai bukan pendapatan tambahan.
- Event dikenali melalui hubungan invoice.event_id. Pembayaran invoice event menjadi penerimaan event; event_id adalah dimensi sumber, bukan nominal kedua.
- Invoice non-event satu jenis item selain DISKON dapat dikelompokkan menurut jenis yang sudah ada. Invoice campuran non-event memakai Penerimaan Gabungan, tanpa memaksa alokasi ke SPP/REG atau membagi payment secara arbitrer.
- Sumber layar: Kelas & biaya terkait, Event, Penerimaan Gabungan. Rincian jenis item boleh ditampilkan di panel. Ketiga kelompok ini harus menjumlah ke total.
- Rincian memuat tanggal pembayaran, nomor kuitansi, murid/pihak terkait, metode, jenis/sumber, event jika ada, invoice, dan nominal; referensi menautkan dokumen sumber dengan RBAC yang sama.

### 4.2 Pengeluaran

Hasil usaha sebelum pajak = penerimaan valid - honor guru dibayar - gaji staff dibayar - operasional tercakup - pengeluaran petty cash tercakup.

- Honor guru: slip PAID dengan paid_at dalam periode; jumlah total_honor termasuk komponen event, transport, dan lain-lain. Bukan hanya slip berperiode bulan terpilih.
- Staff: slip PAID dengan paid_at dalam periode; jumlah net_salary. Expense yang terlink slip ini tidak dimasukkan lagi ke operasional, termasuk pembayaran slip periode lain.
- Operasional: transaksi Expense dalam periode yang tidak sudah dihitung lewat slip staff atau sumber petty cash. Jangan mengecualikan transaksi gaji manual hanya karena nama kategori; gunakan hubungan sumber untuk mencegah hitungan ganda dan jelaskan transaksi yang tidak terlink.
- Petty cash: pengeluaran aktual berdasarkan expense_date; top-up ditampilkan sebagai mutasi internal dan tidak mengurangi hasil usaha.
- Jangan menghapus pembayaran CASH Expense dari laporan tanpa memastikan sumber/cutover pencatatannya. Sebelum implementasi, telusuri seluruh jalur Expense CASH dan PettyCashExpense beserta migrasi legacy untuk mencegah duplikasi. Deduplication memakai referensi/hubungan yang terbukti; tidak menebak dari nominal/tanggal/deskripsi yang kebetulan sama.
- Data slip PAID tanpa paid_at tidak diberi tanggal buatan; tampilkan peringatan data yang memerlukan koreksi sehingga owner mengetahui keterbatasan total.

### 4.3 Kas dan kewajiban

- Penerimaan tunai/non-tunai mengikuti periode laporan dan dapat diklik seperti tab Hasil Usaha.
- Saldo petty cash awal, top-up, pengeluaran, saldo akhir berasal dari sumber petty cash existing dan periode yang sama.
- Piutang dan honor/gaji belum dibayar merupakan posisi saat laporan dibuat, bukan snapshot historis akhir periode. Label tersebut harus muncul pada layar dan export.
- Kewajiban honor/gaji ditetapkan dari slip CALCULATED yang belum dibayar; slip DRAFT dipisahkan sebagai belum final, tidak diam-diam dianggap kewajiban final.
- Tidak menampilkan saldo bank atau cash owner karena saldo awal dan seluruh mutasi belum tercakup.

## 5. Struktur UI dan interaksi

### 5.1 Header dan ringkasan

Header Laporan Keuangan, periode, filter pembanding, basis kas/sebelum pajak, tombol PDF dan Excel. Tetap memakai tiga tab bisnis; pratinjau PDF pada mockup adalah alat review, bukan tab bisnis keempat wajib.

Tab Hasil Usaha dimulai dengan tabel Penerimaan, Pengeluaran Usaha, Hasil Usaha Sebelum Pajak: periode terpilih, pembanding, perubahan rupiah/persen. Tidak membuat grafik yang menduplikasi tabel.

Urutan berikutnya: penerimaan menurut metode/kelompok tunai-non-tunai, sumber penerimaan, pengeluaran usaha, catatan kelengkapan. Angka utama dan label dimensi terlihat tanpa membuka panel.

Tab Kas & Kewajiban dimulai dengan total penerimaan non-tunai/tunai, lalu mutasi petty cash dan posisi piutang/kewajiban. Tab Event memisahkan penerimaan dalam periode laporan dari ringkasan sepanjang event.

### 5.2 Panel penerimaan per metode

Klik nominal membuka slide-over berjudul metode/kelompok terpilih dan periode aktif. Daftar hanya memuat pembayaran yang membentuk nominal tersebut, termasuk kelas dan event. Nominal nol tetap dapat dibuka dan menampilkan keadaan kosong dengan total nol.

Klik total non-tunai membuka tiga metode non-cash; klik total tunai hanya CASH; klik total penerimaan membuka semua metode. Total panel harus sama dengan angka asalnya. Daftar panjang memakai pagination server dengan total agregat seluruh hasil filter, bukan hanya halaman terlihat.

### 5.3 Panel pengeluaran dan PDF komponen

Label dan nominal komponen sama-sama dapat diklik. Setiap panel memiliki tombol Export PDF Komponen dengan periode yang diwariskan dari laporan.

| Komponen | Isi panel | Isi PDF |
| --- | --- | --- |
| Honor Guru | Daftar nama guru dan total dibayar per guru dalam periode; rincian/tautan slip sumber dapat ditelusuri | Daftar seluruh guru, total per guru, jumlah keseluruhan, periode, basis paid_at |
| Gaji Staff | Daftar nama karyawan non-guru dan total dibayar per karyawan; referensi slip sumber | Daftar seluruh staff, total per staff, jumlah keseluruhan, periode, basis paid_at |
| Operasional | Tanggal, nomor pengeluaran, kategori, keterangan, metode, nominal, referensi sumber | Seluruh transaksi sesuai filter dan total operasional |
| Petty Cash | Laporan existing: saldo awal, isi saldo, pengeluaran, saldo akhir, tanggal, referensi, tipe, keterangan, kategori, debit, kredit, saldo berjalan | Gunakan kembali format/sumber cetak petty cash existing dengan periode yang sama |

PDF komponen mencakup seluruh hasil filter, bukan hanya halaman panel yang terbuka. Total petty cash pada komponen pengeluaran sama dengan total pengeluaran petty cash di laporannya, bukan top-up atau saldo akhir.

Pola panel produksi wajib: Alpine.data di app.js, x-show pada wrapper DAN panel, transisi masuk/keluar, bukan dialog.showModal(). Data tetap terlihat saat animasi keluar. Scroll lock, focus masuk/kembali, keyboard/focus containment, backdrop, dan Escape mengikuti pola repository; Escape tidak menutup panel jika ada dialog native lain di atasnya.

### 5.4 Event

Daftar penerimaan event dalam periode memuat nama event, nominal diterima dalam periode, dan periode/tanggal event. Pembayaran untuk event bulan lain tetap masuk berdasarkan payment_date.

Ringkasan sepanjang event memuat tagihan valid, penerimaan valid, piutang, honor ditetapkan, dan status kelengkapan biaya. Jangan menggunakan cash_margin existing sebagai laba bersih atau menyamakan POSTED dengan PAID.

Biaya selain honor yang belum terhubung ke event ditandai belum tersedia/terklasifikasi; jangan mengalokasikannya dari deskripsi atau menganggap nol. Jangan menampilkan laba bersih event lengkap ketika biaya tidak lengkap. Tautan event tetap memungkinkan owner membuka rincian sumber yang sudah tersedia.

## 6. Export

### 6.1 PDF lengkap

Pertahankan jalur cetak browser existing: tampilan standalone tanpa sidebar, dengan Cetak / Simpan PDF. Bukan janji endpoint binary PDF jika mekanisme existing memakai window.print().

PDF memiliki identitas studio, periode dan pembanding, basis kas/sebelum pajak, waktu pembuatan, ringkasan hasil usaha, penerimaan metode/jenis, rincian pengeluaran, event, dan catatan. Nominal rata kanan, header tabel berulang, baris tidak terpotong, nomor halaman bila mekanisme cetak mendukung. Tidak memaksa semua rincian ke satu halaman.

PDF komponen menggunakan prinsip yang sama, hanya komponen yang dipilih. Semua referensi dan catatan kelengkapan tetap terbawa. Informasi metode bank/tunai tidak diberi label saldo.

### 6.2 Excel

Workbook multi-sheet: Ringkasan, Pendapatan, Beban, Event, Catatan.

- Ringkasan: periode, pembanding, hasil usaha, subtotal penerimaan metode, kelompok tunai/non-tunai, dan posisi kas/kewajiban dengan label waktunya.
- Pendapatan: satu baris per pembayaran valid, tanggal, kuitansi/invoice, pihak terkait, jenis/sumber, event, metode, nominal integer.
- Beban: referensi unik sumber, komponen, tanggal aktual, guru/staff/kategori, keterangan, nominal. Staff/honor tidak digandakan lewat Expense terlink.
- Event: penerimaan periode dan ringkasan sepanjang event pada kolom terpisah; status kelengkapan biaya.
- Catatan: basis, periode/waktu posisi kewajiban, batasan data, gabungan belum terurai, event belum lengkap, status profil pajak belum diketahui.

Sel nominal berupa numeric cell, bukan string Rp. Kode dokumen tetap string. Teks pengguna ditulis sebagai teks literal, bukan formula spreadsheet. Urutan deterministik; periode dan pembanding konsisten dengan layar. Tidak menambahkan library baru jika builder existing mencukupi; penguatan kemampuan builder hanya sesuai kebutuhan nyata export.

File memakai nama deterministik seperti Musik-KITA-Keuangan-2026-09.xlsx dan Musik-KITA-Honor-Guru-2026-09.pdf saat pengguna menyimpan cetakan. Rentang khusus mencantumkan tanggal awal/akhir.

### 6.3 Pendukung pajak

Preset tahun menyediakan rekap pendapatan kelas/event, metode, pengeluaran, dan referensi. Label Rekap Pendukung Pelaporan Pajak. Tidak ada tarif, pajak terutang otomatis, koreksi fiskal otomatis, atau klaim bisa langsung diunggah ke DJP. Kebutuhan format resmi merupakan desain terpisah setelah profil wajib pajak diketahui dan sumber DJP terkini diverifikasi.

## 7. Visual, akses, dan keadaan

Mahoni/mint sesuai tema aktual, tabel rata kanan dengan angka tabular, teks badan terbaca, judul Playfair sesuai aplikasi, kontrol minimal 44 px. Tidak ada font/dependency eksternal baru yang membuat LAN/offline bergantung internet.

Desktop memisahkan sumber penerimaan dan pengeluaran dalam dua kolom; mobile satu kolom. Tabel lebar memakai scroll lokal, bukan overflow seluruh halaman. PDF putih dan jelas saat cetak grayscale. Theme yang didukung aplikasi tetap dapat dipakai; tidak mengubah token global tanpa kebutuhan.

Keadaan wajib: data kosong, komponen nol, hasil negatif, pembanding nol/negatif, rentang tidak sah, detail panjang, nama panjang, transaksi void, pembayaran lintas periode, export gagal, dan pengguna tidak berizin. Error menyebut masalah dan tindakan perbaikan; export gagal tidak mengirim file kosong seolah berhasil.

Gunakan middleware/otorisasi existing laporan dan dokumen sumber; endpoint detail/export tidak memperluas akses. Tidak mengubah RBAC sebagai efek samping redesign. Event PDF dan payroll tetap menghormati akses yang berlaku. Audit PRINT sesuai pola existing untuk tindakan cetak/export yang dicatat repository.

## 8. Batas implementasi dan cutover

Satu sumber query/perhitungan reusable untuk web, panel, cetak, dan Excel. Ekstraksi ke service konkret boleh bila kebutuhan reuse lintas report/petty-cash nyata; tidak menambah interface/factory generik.

Gunakan controller/routes/views existing; tambah partial atau endpoint detail/export hanya ketika diperlukan. Reuse sumber petty cash, nomor dokumen, tautan, dan XlsxBuilder. Semua komentar/pesan baru Bahasa Indonesia.

Sebelum implementasi: trace seluruh pembuat Expense CASH/PettyCashExpense dan pemakai hasil laporan, serta referensi simbol yang akan diubah. Cutover seluruh caller, cetak, dan test lama yang mengasumsikan top-up sebagai beban. Jangan mempertahankan rumus lama berlabel Laba Bersih sebagai alias. Dokumen historis Juli tetap sejarah; dokumen aktual menjelaskan penggantian definisi oleh spesifikasi ini.

Tidak melakukan migrate:fresh, seed, atau mengubah transaksi database utama. Jika data legacy ternyata butuh migrasi destruktif/koreksi transaksi, berhenti dan minta persetujuan tersendiri; jangan menebak hasil deduplication.

## 9. Bukti mockup dan penerimaan

Mockup review tersimpan lokal di `.superpowers/brainstorm/1124-1791456133/content/finance-expense-details-v4.html`; bukan kode produksi dan seluruh transaksi/nominal berlabel ilustrasi. Tidak memasukkan token akses companion ke commit.

Verifikasi yang sudah dilakukan pada desain:
- Desktop dan mobile 390 px; halaman tidak melebar melebihi viewport.
- Klik empat metode membuka transaksi terpilih termasuk event dan total ilustrasi yang cocok; kelompok non-tunai menjumlah tepat.
- Klik empat komponen pengeluaran membuka isi panel yang diminta.
- PDF contoh honor, staff, operasional, dan petty cash berhasil dihasilkan melalui browser; judul dan rincian komponen diperiksa.
- Existing EventFinanceTest: 7 lulus, 44 assertions dengan konfigurasi SQLite :memory:; ini bukti integrasi penerimaan event existing, bukan bukti implementasi redesign.

Kriteria penerimaan implementasi:
1. Penerimaan kelas dan event dihitung sekali; void dikecualikan; metode dan kelompok tunai/non-tunai menjumlah tepat.
2. Honor dan staff lintas bulan mengikuti paid_at; expense staff terlink tidak masuk operasional lagi.
3. Top-up tidak mengurangi hasil usaha; pengeluaran petty cash actual masuk tepat sekali; saldo awal + top-up - pengeluaran = saldo akhir.
4. Panel nama guru/staff dan total per nama menjumlah ke komponen; operasional menunjukkan transaksi sumber; petty cash sesuai laporan existing.
5. PDF lengkap/empat komponen dan Excel sesuai filter, seluruh rincian dan totalnya cocok; angka Excel numeric, bukan formula dari input pengguna.
6. Periode pembanding dan label posisi kewajiban tidak menyesatkan; tidak mengklaim saldo bank/laba fiskal/biaya event lengkap.
7. Runtime smoke melalui route sebenarnya mencakup pembayaran kelas/event, empat metode, pembayaran lintas periode, void, detail, petty cash, dan export; visual desktop/mobile serta cetak diperiksa.
8. Test hanya SQLite in-memory setelah konfigurasi diverifikasi. Regression test permanen untuk batas tanggal, double-counting, void, dan total uang; bukan test yang mematok wording incidental.

## 10. Gerbang berikutnya

Pengguna mereview dan menyetujui spesifikasi tertulis ini. Setelah itu gunakan writing-plans untuk rencana implementasi, lalu pengguna mereview rencana dan memilih metode eksekusi sebelum kode aplikasi ditulis.
