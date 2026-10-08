# Laporan Keuangan Owner — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menyediakan laporan basis kas owner yang mudah dibaca, drilldown penerimaan/pengeluaran, PDF lengkap/komponen, dan Excel dengan angka yang saling cocok.

**Architecture:** Satu `FinanceReportService` konkret menyediakan angka dan baris laporan untuk web, detail, cetak, dan Excel. Form Request menormalkan periode; laporan petty cash memakai kembali sumber existing yang diperluas ke rentang tanggal. Blade/Alpine mengikuti tema dan slide-over aplikasi; tidak ada tabel pendapatan baru atau framework/dependency baru.

**Tech Stack:** Laravel 11, PHP 8.3, Blade, Tailwind CSS 3, Alpine.js, Carbon, XlsxBuilder/ZipArchive existing, cetak PDF browser existing.

**Spec:** `docs/superpowers/specs/2026-10-08-finance-owner-report-design.md` — baca bersama rencana ini.

## Global Constraints

- Basis kas; label utama Hasil Usaha Sebelum Pajak. Top-up petty cash bukan beban usaha.
- Penerimaan tunai/non-tunai berarti total penerimaan dalam periode, bukan saldo aktual uang tersedia.
- Profil wajib pajak belum diketahui. Rekap tahunan hanya pendukung pelaporan pajak, bukan perhitungan pajak otomatis, SPT, atau bukti pelaporan DJP.
- Tidak ada penambahan tabel pendapatan kedua. Tidak ada klasifikasi fiskal otomatis dari nama kategori.
- Semua komentar/pesan baru Bahasa Indonesia.
- Satu sumber query/perhitungan reusable untuk web, panel, cetak, dan Excel.
- Tidak melakukan migrate:fresh, seed, atau mengubah transaksi database utama.
- Test hanya SQLite in-memory setelah konfigurasi diverifikasi.
- Tidak mengubah RBAC sebagai efek samping redesign.
- Tidak ada font/dependency eksternal baru yang membuat LAN/offline bergantung internet.

## Review Focus

1. Pembayaran jam terakhir hari akhir dan tahun kabisat harus masuk; awal hari berikutnya tidak masuk — Task 1 dan 2.
2. Slip periode lama dibayar bulan ini, Expense staff bertanggal bulan lama, dan gaji manual tanpa link tidak boleh hilang/digandakan — Task 2.
3. Transaksi sama nominal/tanggal pada sumber berbeda tetap sah; jangan dedup dengan heuristik; legacy CASH sudah dipindahkan oleh migrasi — Task 2.
4. Panel 51+ transaksi, request balapan, nama panjang, dan total nol harus mempertahankan total seluruh filter dan data panel yang benar — Task 3 dan 4.
5. Nama/keterangan diawali `=`, `+`, `-`, `@`, serta karakter XML harus menjadi teks Excel, dan PDF tidak boleh hanya mengekspor halaman detail yang terlihat — Task 5 dan 6.

## Pemetaan file dan tanggung jawab

Create:
- `app/Http/Requests/FinanceReportRequest.php`: validasi filter dan normalisasi periode.
- `app/Services/FinanceReportService.php`: query sumber, ringkasan, baris detail yang sama untuk semua surface.
- `resources/js/financeReport.js`: state tab/panel, fetch detail, pagination, error/retry, focus dan scroll lock.
- `resources/views/reports/partials/finance-summary.blade.php`: ringkasan hasil, metode, sumber dan komponen.
- `resources/views/reports/partials/finance-detail.blade.php`: isi panel yang juga dapat dirender server sebagai fragment pagination.
- `resources/views/reports/finance-component-print.blade.php`: standalone cetak honor/staff/operasional; petty cash reuse print existing.
- `app/Exports/FinanceReportExport.php`: mapping baris laporan ke lima sheet XlsxBuilder.
- `tests/Feature/FinanceReportPeriodTest.php`, `FinanceReportCashBasisTest.php`, `FinanceReportDetailsTest.php`, `FinanceReportExportTest.php`: perilaku uang, batas tanggal, otorisasi, detail, file export.

Modify:
- `app/Http/Controllers/ReportController.php:29-162`: request/service bersama, detail, component print, Excel; statistik murid tidak diubah.
- `app/Services/PettyCashService.php:103-157` dan `app/Http/Controllers/PettyCashController.php` method print: sumber rentang dan migrasi caller monthly summary/running balance.
- `routes/web.php:619-629`: endpoint laporan baru tetap role Owner; petty cash existing tetap izin existing.
- `resources/views/reports/finance.blade.php`: layout tiga tab dan slide-over.
- `resources/views/reports/finance-print.blade.php`: cutover rumus/copy, periode, rincian lengkap.
- `resources/views/petty-cash/print.blade.php`: periode rentang bila diperlukan, tanpa mengganti kolom/sumber laporan.
- `resources/js/app.js:1-14`: register Alpine.data sebelum Alpine.start.
- `tests/Feature/PettyCashReportTest.php`: ganti aturan lama top-up-as-expense, bukan mematok rumus lama.
- Existing `EventFinanceTest.php`, `FinancePrintTest.php`, `ReportFinanceMethodTest.php`, `PettyCashPrintTest.php`: migrate kontrak yang berubah; hapus assertion wording/incidental implementation, pertahankan perilaku konsumen.
- Komentar terkait P&L pada `app/Models/PettyCashExpense.php`, `app/Services/PettyCashService.php`, `app/Http/Controllers/PettyCashController.php`: hilangkan definisi top-up-as-expense yang obsolete.
- `docs/superpowers/specs/2026-10-08-finance-owner-report-design.md`: status/evidence aktual; dokumentasi fitur existing yang benar-benar menyatakan rumus lama harus diperbarui, bukan menulis ulang dokumen sejarah Juli.

`XlsxBuilder.php` tidak diubah: numeric cells dan shared-string literal sudah didukung. `StaffPayrollService` tidak diubah untuk menggeser tanggal dokumen historis; paid_at menjadi sumber tanggal laporan.

## Kontrak bersama

`FinanceReportRequest::period(): array` menghasilkan:
- `start`, `end`, `comparison_start`, `comparison_end`: string `Y-m-d`.
- `mode`: month/year/range; `label`, `comparison_label`: string Bahasa Indonesia.
- `year`, `month`: integer hanya untuk mode month; null untuk mode lainnya.

`FinanceReportService::build(array $period): array` menghasilkan:
- `period`, `generated_at`, `position_at`;
- `current`, `comparison`: array `revenue`, `honor`, `staff`, `operations`, `petty`, `expenses`, `result` semua integer;
- `methods`: CASH/TRANSFER/QRIS/DEBIT integer, `cash_receipts`, `noncash_receipts`;
- `sources`: classes/event/combined integer;
- `petty_report`: summary dan mutations dari PettyCashService;
- `events`: Collection row `id`, `name`, `date`, `period_receipts`, `billed`, `collected`, `outstanding`, `posted_honor`, `costs_complete` (false jika data biaya event belum memadai);
- `receivables`: classes/event integer berdasarkan posisi saat dibuat;
- `liabilities`: honor/staff CALCULATED unpaid integer; jumlah DRAFT terpisah;
- `warnings`: daftar string yang berasal dari kondisi nyata data dan batasan laporan.

`FinanceReportService::rows(array $period, string $component, ?string $method = null): Collection`:
- `component`: receipts/honor/staff/operations/petty.
- `method` receipts: null=semua, CASH/TRANSFER/QRIS/DEBIT atau NONCASH; invalid ditolak request.
- receipts: `date`, `reference`, `source`, `party`, `event_name`, `method`, `amount`, `url`, `invoice_url`.
- honor/staff: `party`, `amount`, `documents` (referensi, tanggal bayar, nominal, URL); grouped per teacher_id/employee_id, bukan nama.
- operations: `date`, `reference`, `category`, `description`, `method`, `amount`, `url`.
- petty: mutasi existing `date`, `number`, `type`, `description`, `model`, `debit`, `credit`, `running_balance`.

Controller detail mem-paginate Collection per 50 item (LengthAwarePaginator); total jumlah seluruh filter dihitung sebelum slice, dengan key `total_amount`. PDF/Excel memakai rows tanpa pagination. Collection ini cocok skala studio; tidak membuat engine export streaming spekulatif.

## Task 1: Periode sah dan sumber petty cash rentang

**Files:** FinanceReportRequest; PettyCashService; PettyCashController method print; petty-cash print; FinanceReportPeriodTest; PettyCashPrintTest.

**Interfaces:** menghasilkan `period()` di atas; `PettyCashService::getRangeReport(string $start, string $end): array{summary:array,mutations:Collection}`.

- [ ] Jalankan references LSP untuk method petty cash yang akan diganti dan telusuri caller. Migrasikan caller `getMonthSummary`, `getOpeningBalanceForMonth`, `getMutationsWithRunningBalance` ke `getRangeReport`; hapus method yang obsolete setelah semua caller migrated. `getMutations(year, month)` tetap jika masih digunakan index operasional.
- [ ] Tambah regression `test_period_boundaries_and_comparison`: Februari 2028 → start 2028-02-01, end 2028-02-29, comparison 2028-01-01..31; range 2026-09-10..12 → pembanding 2026-09-07..09; year 2026 → 2026-01-01..12-31 dan tahun 2025.
- [ ] Tambah `test_invalid_period_and_component_rejected`: month 13, tahun di luar 1900..9999, tanggal tidak sah, start>end, metode/component tidak dikenal ditolak dengan error, bukan fallback bulan lain. Preset `mode` default month; query year/month existing tetap diterima; mode year memakai year, mode range memerlukan start/end; mode tidak sesuai ditolak. Parameter tampilan `tab` hanya results/cash/events.
- [ ] Tambah `test_petty_range_opening_and_running_balance`: top-up sebelum range 1.000.000; dalam range top-up 500.000 dan expense 200.000; sesudah range expense 50.000 → opening 1.000.000, closing 1.300.000, expense 200.000. Same-date order deterministik date ASC, top-up sebelum expense, kemudian id ASC.
- [ ] Verifikasi phpunit.xml DB_CONNECTION sqlite dan DB_DATABASE :memory:, termasuk konfigurasi cache yang mungkin menimpa test. Run focused red: `php artisan test tests/Feature/FinanceReportPeriodTest.php`; catat kegagalan assertion yang diharapkan, bukan kegagalan bootstrap.
- [ ] Implement request: `authorize(): bool`, `rules(): array`, `messages(): array`, `period(): array`; tanggal datetime query memakai awal hari dan batas end+1 hari exclusive untuk paid_at, date fields inklusif. Role route tetap pengaman utama.
- [ ] Implement range petty source; saldo opening memakai tanggal sebelum start, seluruh rows sampai end. Migrate print existing dengan label periode rentang; GET year/month existing tetap valid. Reuse audit PRINT existing.
- [ ] Run focused green: `php artisan test --filter='FinanceReportPeriodTest|PettyCashPrintTest'`; PASS. Commit file task ini setelah runtime smoke range melalui kernel HTTP in-memory.

Assertion inti `test_petty_range_opening_and_running_balance`, setelah fixture di atas:
```php
$report = app(PettyCashService::class)->getRangeReport('2026-09-10', '2026-09-12');
$this->assertSame(1_000_000, $report['summary']['opening_balance']);
$this->assertSame(200_000, $report['summary']['total_expense']);
$this->assertSame(1_300_000, $report['summary']['closing_balance']);
$this->assertSame(1_300_000, $report['mutations']->last()->running_balance);
```

## Task 2: Perhitungan kas studio dan cutover laporan

**Files:** FinanceReportService; ReportController finance/financePrint; finance dan finance-print kontrak data baru; FinanceReportCashBasisTest; PettyCashReportTest; existing finance tests; komentar petty cash obsolete.

**Interfaces:** menghasilkan build/rows kontrak bersama; controller finance/financePrint memakai FinanceReportRequest.

- [ ] Tambah `test_payments_and_expenses_reconcile_without_duplicates` dengan receipts Cash 100.000 + event QRIS 200.000; void payment 900.000 diabaikan; honor 50.000; staff 30.000 plus linked Expense 30.000 diabaikan operasi; standalone Expense GAJI_STAFF 5.000 tetap operasi; operasi lain 20.000; petty expense 10.000; top-up 500.000 tidak masuk beban. Assert revenue=300.000, expenses=115.000, result=185.000, cash=100.000, noncash=200.000, sources event=200.000.
- [ ] Tambah `test_paid_at_cross_period_and_end_of_day`: slip Agustus dibayar 30 September 23:59:59 dihitung September; slip September dibayar 1 Oktober tidak. Expense linked bertanggal Agustus tidak menyebabkan duplikasi/hilangnya pembayaran September. Slip PAID tanpa paid_at masuk warning, tanpa tanggal rekaan.
- [ ] Tambah `test_distinct_same_amount_expenses_are_not_heuristically_deduplicated`: dua Expense 10.000 referensi berbeda, plus petty 10.000, semuanya masuk 30.000. Migrasi 2026_06_13_000003 sudah copy legacy CASH lalu DELETE asal; UI Expense store hanya TRANSFER, petty create hanya PettyCashExpense. Hitung Expense residual yang sah tanpa heuristik atau migrasi data baru; warn jika ada residual CASH yang memerlukan review sumber.
- [ ] Tambah `test_void_invoice_and_mixed_invoice_sources`: valid payment ke invoice void tidak dihitung; invoice non-event mixed masuk combined; event invoice banyak item dihitung sekali. Preservasi batas tanggal receipt, methods zero, liabilities CALCULATED versus DRAFT, dan seluruh posisi diberi waktu generated_at.
- [ ] Run red: `php artisan test --filter=FinanceReportCashBasisTest`.
- [ ] Implement build dan rows dari query sumber bersama. SUM integer; honor/staff group ID, noncash tiga metode; exclude Expense melalui staffPayrollSlip relation tanpa menghapus seluruh kategori GAJI_STAFF. Query event period mengikuti Payment, summary all-time event memakai sumber existing; tidak ada margin laba palsu.
- [ ] Cutover ReportController dan kedua view ke kontrak baru; hapus buildFinanceData yang obsolete, migrate viewData tests secara menyeluruh. Replace test PettyCashReportTest aturan lama: fixture top-up 500.000, operasi 200.000, petty 100.000 menghasilkan -300.000, bukan -700.000. Jangan mempertahankan alias labaBersih dengan rumus lama.
- [ ] Catatan kelengkapan selalu membedakan kas, akuntansi/fiskal, biaya event belum terhubung; kategorisasi aset/modal/pribadi tidak diasumsikan. Tampilkan warning kategori campuran yang perlu review; angka hasil usaha disebut pengeluaran tercakup, tidak klaim laporan fiskal final.
- [ ] Run green: `php artisan test --filter='FinanceReportCashBasisTest|PettyCashReportTest|EventFinanceTest|ReportFinanceMethodTest|FinancePrintTest'`; PASS. Smoke route finance dan print dengan fixture in-memory untuk jumlah yang sama. Commit.

Assertion inti `test_payments_and_expenses_reconcile_without_duplicates`:
```php
$report = $response->viewData('report');
$this->assertSame(300_000, $report['current']['revenue']);
$this->assertSame(115_000, $report['current']['expenses']);
$this->assertSame(185_000, $report['current']['result']);
$this->assertSame(100_000, $report['cash_receipts']);
$this->assertSame(200_000, $report['noncash_receipts']);
```
Kontrak view untuk kedua route adalah `report` array; bukan alias variabel rumus lama.

## Task 3: Endpoint rincian dan otorisasi

**Files:** ReportController; routes/web.php; FinanceReportDetailsTest; finance-detail partial.

**Interfaces:** `financeDetail(FinanceReportRequest $request): JsonResponse`; response `{title,period_label,total_amount,html,page,last_page,total_rows}`. Request component/method/page diwariskan periode; page integer >=1. `financeComponentPrint(FinanceReportRequest $request): View` pada Task 5.

- [ ] Tambah `test_receipt_detail_filters_class_and_event_and_full_total`: 51 pembayaran Transfer, 50 @1.000 kelas +1 @5.000 event; detail page1 rows50, page2 rows1, kedua response total55.000. Cash tidak tercampur. NONCASH menggabungkan tiga metode, bukan CASH.
- [ ] Tambah `test_honor_and_staff_group_by_identity_not_name`: dua guru nama sama total terpisah; guru sama dua slip periode berbeda dibayar dalam range menjumlah per ID; tautan source slip benar. Operasi dan petty detail sesuai rows kontrak; kosong total0.
- [ ] Tambah `test_finance_endpoints_owner_only`: guest redirect login; Admin/Auditor/Guru 403 untuk report/detail/new print/export; Owner200. Jalur petty-cash existing tetap izin existing. Tidak memperluas izin karena bisa menebak URL detail.
- [ ] Run red: `php artisan test --filter=FinanceReportDetailsTest`.
- [ ] Daftarkan GET `reports/finance/detail` → reports.finance.detail di group role Owner yang sama. Response html render partial dengan user text escaped; source route URL dari router, bukan markup pengguna. Pagination50, total seluruh filter. Validation422 untuk filter tidak sah.
- [ ] Run green focused. Smoke GET detail melalui HTTP kernel in-memory dan lihat tanggal/referensi/nominal sebenarnya; commit.

Assertion inti detail pagination memakai response masing-masing halaman:
```php
$pageOne->assertJsonPath('total_amount', 55_000)->assertJsonPath('total_rows', 51);
$pageOne->assertJsonPath('page', 1)->assertJsonPath('last_page', 2);
$pageTwo->assertJsonPath('total_amount', 55_000)->assertJsonPath('page', 2);
```

## Task 4: UI owner dan slide-over

**Files:** finance.blade.php; finance-summary partial; finance-detail partial; financeReport.js; app.js; app.css hanya jika utility baru perlu theme override.

**Interfaces:** export default `financeReport(config)` sebagai factory Alpine; register `Alpine.data('financeReport', financeReport)` sebelum start. Config route detail, periode query, tab awal. Method `openDetail(component, method = null)`, `loadPage(page)`, `close()`, `closeFromEscape()`; state tab/panelOpen/title/loading/error/html/total_amount/page/last_page.

- [ ] Implement tiga tab dan filter month/year/range + pembanding; deep-link query persist. Copy/tabel sesuai spesifikasi dan mockup v4; tidak ship angka ilustrasi atau DOM companion. Seluruh label/metode/nominal actionable minimal44px.
- [ ] Implement AJAX panel: total/detail title, pending/error/retry, pagination, kosong, links sumber, tombol PDF komponen. AbortController membatalkan request lama dan mencegah response lama menimpa pilihan terbaru. Escape/backdrop menutup; tidak mengosongkan html saat animasi keluar; focus kembali ke trigger; x-show wrapper dan panel, scroll lock, guard dialog[open], focus containment dan prefers-reduced-motion.
- [ ] Implement Event period/all-time dan biaya belum lengkap; Kas/Kewajiban memakai posisi saat laporan dibuat. Comparison zero/negative em dash; status tidak hanya warna. Satu kolom mobile dan scroll lokal tabel.
- [ ] Jalankan `npm run build` setelah utility/JS berubah. Buka actual report surface di browser dengan fixture in-memory router throwaway, bukan database MySQL, pada 390px dan1440px. Periksa semua tab, empat metode + group, empat komponen, 51-row pagination, nama panjang, kosong, negative result, error/retry; gunakan request interception untuk membuktikan response balapan tidak menimpa panel terakhir.
- [ ] Uji keyboard focus awal/kembali, Escape guard, backdrop, scroll lock, dan tidak overflow page. Inspect desktop/mobile dalam satu batch, perbaiki dalam satu batch, satu confirmation maksimal. Tidak membuat tests source text/Alpine wiring; browser smoke menjadi bukti interaksi. Commit.

## Task 5: PDF lengkap dan komponen

**Files:** ReportController financePrint/financeComponentPrint; finance-print; finance-component-print; petty-cash print reuse; routes; FinanceReportExportTest (bagian print); FinancePrintTest/PettyCashPrintTest.

**Interfaces:** GET `reports/finance/print/component` → reports.finance.component-print; component honor/staff/operations/petty, role Owner. Full print existing route preserved. Petty component merender print existing dengan range summary/mutations, bukan copy rumus baru.

- [ ] Tambah `test_component_print_contains_all_filtered_rows`: fixture operasi51 transaksi; print berisi51 referensi dan total, bukan50 yang terlihat di panel. Honor/staff grouped totals cocok summary; petty opening/topup/expense/closing/running balance cocok source. Invalid component ditolak; zero menghasilkan laporan kosong yang sah.
- [ ] Run red: `php artisan test --filter=FinanceReportExportTest` (kasus print saja sebelum Excel ditulis).
- [ ] Implement standalone print, identitas, judul komponen/periode, basis kas, timestamp, data lengkap, catatan; window.print dan tombol Cetak / Simpan PDF. Actions/sidebar absent print, header tabel repeat, baris tidak terpotong. AuditLog::ACTION_PRINT pola existing, entity FinanceReport dengan filter/component pada metadata yang diizinkan model.
- [ ] Link dari panel ke route dengan filter lengkap, bukan data page slice; request cetak membangun ulang laporan sumber. Full PDF memuat metode dan tunai/non-tunai, rincian semua komponen/event/catatan. Petty reuse layout existing; label top-up transfer internal.
- [ ] Run green focused print; browser smoke buka full dan empat component URL, buat PDF via browser.pdf dari runtime fixture, baca hasil PDF untuk memastikan referensi terakhir/total dan cetakan bersih. Periksa grayscale/A4, nama panjang dan multipage; commit.

Assertion inti PDF semua baris, setelah operasi 51 referensi dibuat:
```php
$response->assertOk()->assertSee('EXP/2026/09/0051');
$this->assertSame(51, $response->viewData('rows')->count());
```
View standalone komponen menerima `report`, `component`, `rows`, `title`; petty cash tetap menerima kontrak existing `summary`/`mutations` dan label periode.

## Task 6: Excel, verifikasi terpadu, dan handoff

**Files:** FinanceReportExport; ReportController financeExport; routes; FinanceReportExportTest; dokumentasi aktual sesuai cutover.

**Interfaces:** `FinanceReportExport::sheets(array $report, Collection $receipts, Collection $honor, Collection $staff, Collection $operations): array` menghasilkan `[['name'=>string,'rows'=>array],...]` tepat lima sheet Ringkasan/Pendapatan/Beban/Event/Catatan. Controller memakai new XlsxBuilder()->build(sheets); GET reports/finance/export → reports.finance.export, role Owner.

- [ ] Tambah `test_excel_numeric_amounts_literal_strings_and_all_sources`: unzip response xlsx dengan ZipArchive, parse XML DOM; numeric amount300000 memakai numeric cell, nama `=SUM(1,2)`/`+cmd`/`@x`/`-x`/`A&B <studio>` berupa shared string literal, tanpa formula `<f>`; semua receipt unique refs termasuk event dan51st row, semua beban sekali, source totals/period sama screen; workbook tepat lima nama sheet.
- [ ] Tambah `test_export_year_and_custom_range_match_report`: leap boundary/previous-period, annual label pendukung pajak, data noncash/cash, generated position note; pengeluaran asset/event tidak diberi status final tanpa bukti. Header download MIME benar, filename tanggal deterministik; exception builder menghasilkan error bukan blank attachment200.
- [ ] Run red export focused.
- [ ] Implement mapping sheets dari kontrak service, nominal int, codes string, no formulas, konsisten urutan date/ref. Reuse XlsxBuilder tanpa dependency baru. Error penulisan/archive ditangani agar tidak mengirim sukses palsu; perubahan kecil builder hanya bila failure path existing membutuhkan pengecekan ZipArchive/temp-file cleanup.
- [ ] Run focused green dan satu kali seluruh suite setelah integrasi: `php artisan test`. Pastikan safety SQLite :memory: tetap aktif. Jangan memperbaiki kegagalan unrelated tanpa menjelaskan scope; failure yang dilaporkan pengguna tidak perlu direrun untuk konfirmasi.
- [ ] Smoke end-to-end pakai throwaway PHP router di luar public aplikasi: bootstrap Laravel dengan env testing + DB_CONNECTION sqlite + DB_DATABASE :memory: dan fixture deterministik pada setiap request, autentikasi fixture Owner, dispatch HTTP kernel ke routes asli. Jangan menjalankan migrasi/seeder terhadap MySQL; env ditetapkan sebelum bootstrap, assert koneksi driver dan database sebelum schema creation. Gunakan browser untuk detail, PDF dan download Excel; throwaway CLI kernel smoke untuk boundary/comparison, tanpa menjadikannya product scaffolding.
- [ ] Cocokkan jumlah screen→detail→full/component PDF→XLSX dengan fixture; pastikan topup bukan beban, staff linked tidak duplicate, event payment tidak duplicate. Hapus router/fixture/temp verification setelah proof. File mockup companion tetap artifact desain, tidak dipindah ke product.
- [ ] Perbarui dokumentasi aktual rumus basis kas dan label non-tunai/tunai, catatan posisi kewajiban/pajak, dan status spesifikasi. Jangan menghapus dokumen sejarah atau file pengguna. Request whole-branch review correctness/security sebelum handoff; commit perubahan final yang sudah terbukti, bukan automatic push.

Assertion inti workbook, setelah ZIP/XML response dibuka sebagai DOMDocument:
```php
$this->assertSame(0, $worksheet->getElementsByTagName('f')->length);
$this->assertStringContainsString('=SUM(1,2)', $sharedStrings->textContent);
$this->assertStringContainsString('A&B <studio>', $sharedStrings->textContent);
```
Tambahkan assert numeric cell dan semua sumber ke fixture export yang sama; jangan membuat test terpisah untuk setiap string atau salinan header.

## Urutan dan metode eksekusi

Task1→2→3→4→5→6 merupakan satu alur report dengan shared contract; tidak ada manfaat fanout implementasi yang saling menulis ReportController/routes. Rekomendasi: **Native**, implementasi dalam sesi ini memakai executing-plans, lalu reviewer independen untuk keseluruhan perubahan. Subagent-driven tetap dapat dipilih dengan review tiap task; jangan spawn slice kecil atau setup tugas palsu.

Rencana ini belum dijalankan. Pengguna mereview rencana dan memilih metode eksekusi sebelum kode aplikasi ditulis.
