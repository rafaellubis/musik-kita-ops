# UAT Checklist & User Manual Admin — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Generate dua file .docx siap pakai — UAT Checklist untuk Owner dan User Manual untuk Admin — menggunakan Python + python-docx.

**Architecture:** Dua script Python terpisah (`generate_uat.py` dan `generate_manual.py`), masing-masing berdiri sendiri dan re-runnable. Tidak ada dependensi antar script. Output di root project berdampingan dengan BRD/SAD yang sudah ada.

**Tech Stack:** Python 3.13 (Laragon: `C:\laragon\bin\python\python-3.13\python.exe`), python-docx 1.x, Windows path

---

## File Structure

| Action | Path | Responsibility |
|--------|------|----------------|
| Create | `docs/generate-docs/generate_uat.py` | Generator UAT Checklist — ~70 skenario M01–M08 + Import + Kids Bundle |
| Create | `docs/generate-docs/generate_manual.py` | Generator User Manual Admin — 5 bab, langkah operasional |
| Create | `UAT-Musik-KITA-v1.0.docx` | Output UAT (root project) |
| Create | `UserManual-Admin-Musik-KITA-v1.0.docx` | Output User Manual (root project) |

---

## Task 1: Setup — Buat Direktori dan Install python-docx

**Files:**
- Create: `docs/generate-docs/` (directory)

- [ ] **Step 1: Buat direktori docs/generate-docs**

```powershell
New-Item -ItemType Directory -Force "C:\laragon\www\musik-kita-ops\docs\generate-docs"
```

Expected: Directory created (atau sudah ada, tidak error)

- [ ] **Step 2: Install python-docx**

```powershell
C:\laragon\bin\python\python-3.13\python.exe -m pip install python-docx
```

Expected output berisi:
```
Successfully installed python-docx-1.x.x lxml-5.x.x
```
(Jika sudah terinstal: `Requirement already satisfied: python-docx`)

- [ ] **Step 3: Verifikasi instalasi**

```powershell
C:\laragon\bin\python\python-3.13\python.exe -c "import docx; print('python-docx OK:', docx.__version__)"
```

Expected: `python-docx OK: 1.x.x` (tanpa error)

- [ ] **Step 4: Commit setup**

```bash
git add docs/generate-docs/
git commit -m "Docs: Buat folder docs/generate-docs untuk generator UAT dan User Manual"
```

---

## Task 2: Script generate_uat.py — UAT Checklist (~70 Skenario)

**Files:**
- Create: `docs/generate-docs/generate_uat.py`

- [ ] **Step 1: Tulis script generate_uat.py**

Buat file `docs/generate-docs/generate_uat.py` dengan konten berikut (lengkap, ~70 skenario):

```python
"""
Generate UAT-Musik-KITA-v1.0.docx
Jalankan: C:\laragon\bin\python\python-3.13\python.exe docs/generate-docs/generate_uat.py
Output  : UAT-Musik-KITA-v1.0.docx (di root project)
"""
from docx import Document
from docx.shared import Pt, Cm, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_ALIGN_VERTICAL
from docx.oxml.ns import qn
from docx.oxml import OxmlElement
from datetime import date
import os

OUTPUT_PATH = os.path.join(os.path.dirname(__file__), '..', '..', 'UAT-Musik-KITA-v1.0.docx')

# ─── Helpers ──────────────────────────────────────────────────────────────────

def set_font(run, size=10, bold=False, color=None):
    run.font.name = 'Calibri'
    run.font.size = Pt(size)
    run.bold = bold
    if color:
        run.font.color.rgb = RGBColor(*color)

def add_heading(doc, text, level=1):
    p = doc.add_heading(text, level=level)
    for run in p.runs:
        run.font.name = 'Calibri'
        run.font.size = Pt(14 if level == 1 else 12)
        run.font.color.rgb = RGBColor(0x1F, 0x49, 0x7D)
    return p

def set_col_width(table, col_widths_cm):
    for row in table.rows:
        for i, cell in enumerate(row.cells):
            if i < len(col_widths_cm):
                cell.width = Cm(col_widths_cm[i])

def shade_header_row(table):
    """Warna biru muda untuk baris header tabel."""
    tr = table.rows[0]._tr
    trPr = tr.get_or_add_trPr()
    shd = OxmlElement('w:shd')
    shd.set(qn('w:val'), 'clear')
    shd.set(qn('w:color'), 'auto')
    shd.set(qn('w:fill'), 'BDD7EE')
    trPr.append(shd)

def add_scenario_table(doc, module_name, scenarios):
    """
    Tambah satu tabel modul.
    scenarios: list of tuples (no, skenario, langkah_uji, expected_result)
    """
    add_heading(doc, module_name, level=2)
    headers = ['No', 'Skenario', 'Langkah Uji', 'Expected Result', 'Status (✓/✗)', 'Catatan']
    widths  = [1.0, 3.5, 5.5, 4.5, 1.5, 2.0]  # total ~18cm

    table = doc.add_table(rows=1, cols=6)
    table.style = 'Table Grid'
    shade_header_row(table)

    # Header row
    hdr = table.rows[0].cells
    for i, h in enumerate(headers):
        p = hdr[i].paragraphs[0]
        run = p.add_run(h)
        set_font(run, size=9, bold=True)
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER

    # Data rows
    for sc in scenarios:
        row = table.add_row()
        values = [str(sc[0]), sc[1], sc[2], sc[3], '', '']
        for i, val in enumerate(values):
            p = row.cells[i].paragraphs[0]
            run = p.add_run(val)
            set_font(run, size=9)
            if i == 0:
                p.alignment = WD_ALIGN_PARAGRAPH.CENTER

    set_col_width(table, widths)
    doc.add_paragraph()  # spasi antar tabel


# ─── Data Skenario ─────────────────────────────────────────────────────────────

M01_SCENARIOS = [
    (1,  'Tambah instrumen baru',
         '1. Master Data → Instrumen → Tambah\n2. Isi nama instrumen, simpan',
         'Instrumen muncul di daftar, bisa dipilih saat buat paket'),
    (2,  'Edit instrumen',
         '1. Pilih instrumen → Edit\n2. Ubah nama → Simpan',
         'Perubahan tersimpan dan tampil di daftar'),
    (3,  'Tambah paket baru (Reguler)',
         '1. Master Data → Paket → Tambah\n2. Isi kode, instrumen, class_type=REGULER, grade, durasi, harga\n3. Simpan',
         'Paket muncul di daftar dengan harga benar'),
    (4,  'Tambah paket baru (Hobby 45 menit)',
         '1. Buat paket class_type=HOBBY, durasi 45 menit, harga 450.000\n2. Simpan',
         'Paket tersimpan dengan durasi dan harga sesuai'),
    (5,  'Edit harga paket (Owner only)',
         '1. Login sebagai Owner → Paket → Edit → Ubah harga\n2. Simpan',
         'Harga berubah. Admin tidak bisa akses menu edit harga'),
    (6,  'Tambah data guru baru',
         '1. Master Data → Guru → Tambah\n2. Isi nama, kode, instrumen yang diajar, info bank\n3. Simpan',
         'Guru muncul di daftar, bisa dipilih saat daftar murid'),
    (7,  'Edit data guru (info bank)',
         '1. Pilih guru → Edit\n2. Ubah nama bank / nomor rekening → Simpan',
         'Info bank tersimpan, tampil di header slip honor cetak'),
    (8,  'Nonaktifkan guru (ada sesi historis)',
         '1. Guru dengan sesi historis → Nonaktifkan\n2. Konfirmasi',
         'Guru berubah status tidak aktif. Tidak bisa dihapus jika ada sesi historis'),
    (9,  'Tambah ruangan baru',
         '1. Master Data → Ruangan → Tambah\n2. Isi kode, nama, kapasitas, instrumen yang didukung\n3. Simpan',
         'Ruangan muncul di daftar dan bisa dipilih saat atur jadwal'),
    (10, 'Tambah hari libur nasional (tanpa pengganti)',
         '1. Master Data → Hari Libur → Tambah\n2. Tipe: Nasional, tanpa replacement_date\n3. Simpan',
         'Hari libur tersimpan. Sesi yang jatuh pada tanggal ini berstatus LIBUR'),
    (11, 'Tambah hari libur nasional (dengan tanggal pengganti)',
         '1. Tambah hari libur\n2. Isi replacement_date (minggu ke-5 bulan yang sama)\n3. Simpan',
         'Saat generate sesi, ada sesi LIBUR + sesi pengganti di tanggal replacement'),
    (12, 'Tambah hari libur Internal (Konser KITA)',
         '1. Tipe: Internal, is_honor_paid=false\n2. replacement_date dibiarkan kosong\n3. Simpan',
         'Hari libur tersimpan. Honor guru Rp 0 untuk sesi ini. Field pengganti tidak bisa diisi'),
]

M02_SCENARIOS = [
    (13, 'Daftar murid baru (status Calon)',
         '1. Murid → Tambah Murid\n2. Isi semua data wajib (nama, gender, orang tua, dll)\n3. Simpan',
         'Murid tersimpan dengan status Calon, kode M-YYYY-NNNN terotomatis'),
    (14, 'Jadwalkan sesi trial',
         '1. Detail murid (Calon) → Jadwalkan Trial\n2. Isi tanggal, jam, guru, paket diminati\n3. Simpan',
         'Status murid berubah ke Trial. Sesi trial muncul di jadwal hari tsb'),
    (15, 'Input absensi trial — murid HADIR',
         '1. Absensi → cari sesi trial → set HADIR\n2. Simpan',
         'Honor guru terbayar penuh sesuai paket. Sesi berstatus HADIR'),
    (16, 'Input absensi trial — murid NO-SHOW',
         '1. Set sesi trial → HANGUS (tidak hadir tanpa konfirmasi)\n2. Simpan',
         'Honor guru Rp 0 (TRIAL_NS). Sesi berstatus HANGUS'),
    (17, 'Konversi Trial → Aktif',
         '1. Detail murid (Trial) → Konversi ke Aktif\n2. Isi paket, guru, ruang, jadwal mingguan\n3. Konfirmasi',
         'Status murid Aktif. Invoice registrasi Rp 250.000 + SPP bulan berjalan ter-generate otomatis'),
    (18, 'Skip trial — langsung Aktif (walk_in)',
         '1. Murid (Calon) → Skip Trial → pilih alasan: walk_in\n2. Isi paket, guru, jadwal\n3. Konfirmasi',
         'Status langsung Aktif. Reason tersimpan di histori status. Invoice registrasi + SPP ter-generate'),
    (19, 'Murid tidak melanjutkan setelah trial',
         '1. Detail murid (Trial) → Mundurkan\n2. Isi alasan → Konfirmasi',
         'Status murid berubah ke Mengundurkan Diri. Histori tercatat'),
]

M03_SCENARIOS = [
    (20, 'Generate sesi bulanan — bulan normal (tidak ada libur)',
         '1. Penjadwalan → Generate Sesi → pilih bulan\n2. Konfirmasi generate',
         'Setiap murid Aktif mendapat 4 sesi sesuai jadwal mingguan tetap'),
    (21, 'Generate sesi bulanan — ada hari libur nasional tanpa pengganti',
         '1. Pastikan ada holiday tanpa replacement_date di bulan tsb\n2. Generate sesi',
         'Murid yang jadwal hariannya libur mendapat sesi LIBUR (tidak diganti). Total bisa 3 sesi bulan itu'),
    (22, 'Generate sesi bulanan — ada hari libur nasional dengan pengganti',
         '1. Pastikan ada holiday dengan replacement_date di bulan tsb\n2. Generate sesi',
         'Ada sesi LIBUR + sesi pengganti di tanggal replacement. Total tetap 4 sesi'),
    (23, 'Generate sesi — bulan ke-5 tidak ditambah secara reguler',
         '1. Generate sesi bulan yang punya 5 minggu hari tersebut\n2. Cek hasil',
         'Maksimal 4 sesi per murid. Minggu ke-5 tidak ter-generate kecuali sebagai sesi pengganti'),
    (24, 'Cek sesi murid cuti tidak ter-generate',
         '1. Ajukan cuti untuk murid Aktif\n2. Generate sesi bulan cuti',
         'Murid yang sedang cuti tidak mendapat sesi di bulan tsb'),
    (25, 'Pindah jadwal mingguan tetap (dalam bulan berjalan)',
         '1. Detail murid → Jadwal → Edit jadwal\n2. Ubah hari/jam (dalam bulan yang sama)\n3. Simpan',
         'Jadwal baru berlaku. Sesi bulan berjalan menyesuaikan'),
]

M04_SCENARIOS = [
    (26, 'Input absensi HADIR',
         '1. Absensi → cari sesi hari ini → set HADIR\n2. Simpan',
         'Sesi berstatus HADIR. Honor terhitung otomatis (H_REG)'),
    (27, 'Input absensi HADIR_TERLAMBAT',
         '1. Set sesi → HADIR_TERLAMBAT, isi menit terlambat\n2. Simpan',
         'Sesi berstatus HADIR_TERLAMBAT. Honor tetap penuh (H_REG)'),
    (28, 'Input absensi IZIN_RESCHEDULE (izin pertama bulan ini, info ≥5 jam)',
         '1. Set sesi → IZIN_RESCHEDULE\n2. Konfirmasi info ≥5 jam + izin pertama\n3. Simpan',
         'Sesi berstatus IZIN_RESCHEDULE. Tombol buat sesi pengganti aktif. Honor sesi ini Rp 0 (H_IZIN)'),
    (29, 'Buat sesi pengganti (reschedule) via mini-modal',
         '1. Klik "Buat Sesi Pengganti" pada sesi IZIN_RESCHEDULE\n2. Isi tanggal, jam, ruang pengganti\n3. Konfirmasi',
         'Sesi pengganti terbuat. Konflik guru+ruang dicek otomatis. Honor dibayar di sesi pengganti'),
    (30, 'Input absensi HANGUS (no-show tanpa info)',
         '1. Set sesi → HANGUS\n2. Simpan',
         'Sesi berstatus HANGUS. Honor guru tetap dibayar (H_HANGUS)'),
    (31, 'Input absensi IZIN_RESCHEDULE ke-2 (bulan yang sama)',
         '1. Murid sudah punya 1 izin di bulan ini\n2. Set sesi ke-2 → sistem tidak izinkan reschedule\n3. Sistem saran IZIN_VIDEO',
         'Izin ke-2 tidak bisa reschedule. Diarahkan ke IZIN_VIDEO'),
    (32, 'Input absensi DIGANTI — guru pengganti',
         '1. Set sesi → DIGANTI\n2. Pilih guru pengganti dari dropdown\n3. Simpan',
         'Honor dialihkan ke guru pengganti (H_PENG). Guru utama honor Rp 0'),
    (33, 'Sesi LIBUR — honor guru',
         '1. Cek sesi yang berstatus LIBUR (hari libur nasional)\n2. Lihat honor_amount di detail sesi',
         'Honor guru Rp penuh (H_LIBUR). Bukan Rp 0'),
    (34, 'Absensi Kids Class — input per murid dalam grup',
         '1. Absensi → cari sesi Kids Class\n2. Input status per murid (bisa beda-beda)\n3. Simpan',
         'Setiap murid punya status absensi sendiri. Honor guru = jumlah murid hadir × Rp 42.500'),
]

M05_SCENARIOS = [
    (35, 'Generate SPP bulanan (tanggal 1)',
         '1. Keuangan → Generate SPP → pilih bulan/tahun → Konfirmasi',
         'Invoice SPP ter-generate untuk semua murid Aktif dengan primary enrollment. Invoice duplikat tidak dibuat'),
    (36, 'Generate SPP — skip murid Kids Class Bundle',
         '1. Generate SPP bulan berjalan\n2. Cek murid Kids Class Bundle di list',
         'Murid Kids Class Bundle tidak mendapat invoice SPP bulanan (sudah tercover cicilan)'),
    (37, 'Catat pembayaran SPP (CASH)',
         '1. Detail invoice → Catat Pembayaran\n2. Pilih metode CASH, isi nominal, tanggal\n3. Simpan',
         'Pembayaran tercatat. Kuitansi KW/YYYY/MM/NNNN ter-generate. Status invoice UPDATE'),
    (38, 'Catat pembayaran (TRANSFER)',
         '1. Catat pembayaran metode TRANSFER\n2. Upload foto bukti transfer\n3. Simpan',
         'Pembayaran tersimpan dengan bukti foto. Status invoice update'),
    (39, 'Catat pembayaran (QRIS)',
         '1. Catat pembayaran metode QRIS\n2. Simpan',
         'QRIS tercatat sebagai metode. Status invoice update'),
    (40, 'Apply denda keterlambatan (tanggal 11+)',
         '1. Keuangan → Apply Denda → pilih bulan (invoice unpaid sudah lewat tanggal 10)\n2. Konfirmasi',
         'Item DENDA Rp 5.000 × hari terlambat ditambah ke invoice. Total tagihan bertambah'),
    (41, 'Cetak invoice / kuitansi (format A4)',
         '1. Detail invoice → Cetak\n2. Browser membuka halaman print\n3. Ctrl+P → simpan PDF',
         'Halaman cetak A4 tanpa navigasi. Tombol cetak muncul, tersembunyi saat print'),
    (42, 'Tambah item manual ke invoice (dari katalog)',
         '1. Detail invoice (UNPAID) → Tambah Item\n2. Pilih item dari dropdown katalog (misal CUTI)\n3. Simpan',
         'Item baru ditambahkan ke invoice. Total tagihan bertambah'),
    (43, 'Tambah diskon NOMINAL ke item invoice',
         '1. Detail invoice → pilih item yang akan didiskon → Tambah Diskon\n2. Tipe NOMINAL, isi nominal, wajib isi alasan\n3. Simpan',
         'Item DISKON terbuat sebagai child item. Total invoice berkurang sesuai nominal'),
    (44, 'Tambah diskon PERCENT ke item invoice',
         '1. Tipe PERCENT → isi persentase, wajib isi alasan → Simpan',
         'Diskon dihitung dari amount item parent. Total invoice berkurang'),
    (45, 'Void pembayaran (Owner only)',
         '1. Login Owner → Detail invoice → klik void pada payment\n2. Isi alasan void → Konfirmasi',
         'Pembayaran di-void. Status invoice kembali UNPAID/PARTIAL. Admin tidak bisa void'),
    (46, 'Generate 3 invoice cicilan Kids Class Bundle',
         '1. Detail murid (Aktif, KIDS_CLASS_BUNDLE) → Generate Cicilan Bundle\n2. Isi tanggal mulai program\n3. Konfirmasi',
         '3 invoice cicilan terbuat: bulan 1, 2, 4. Total 3 × 726.xxx = Rp 2.180.000. Tidak bisa generate ulang'),
    (47, 'Cek status tagihan outstanding',
         '1. Keuangan → Daftar Invoice → filter status UNPAID\n2. Lihat daftar dan aging',
         'Invoice UNPAID tampil dengan info overdue. Murid tunggakan >1 bulan muncul warning'),
]

M06_SCENARIOS = [
    (48, 'Kalkulasi honor guru bulanan (H-2 sebelum akhir bulan)',
         '1. Honor → Kalkulasi Honor → pilih bulan/tahun\n2. Konfirmasi',
         'Honor terhitung untuk semua guru dari sesi yang sudah ada status final. Slip DRAFT terbuat'),
    (49, 'Lihat rincian slip honor guru',
         '1. Honor → pilih guru → Detail Slip\n2. Lihat per sesi dan per murid',
         'Rincian sesi tampil: tanggal, murid, honor_code, honor_amount. Total akurat'),
    (50, 'Edit komponen honor manual (transport, lain-lain)',
         '1. Slip honor (DRAFT/CALCULATED) → Edit\n2. Isi honor transport, honor lain-lain + keterangan\n3. Simpan',
         'Nilai tersimpan. Total honor = base + event + transport + lain-lain'),
    (51, 'Cetak slip honor guru',
         '1. Detail slip honor → Cetak\n2. Halaman A4 print terbuka',
         'Slip cetak berisi nama guru, periode, info bank, rincian sesi, dan total honor'),
    (52, 'Tandai honor dibayar (Owner only)',
         '1. Slip honor (CALCULATED) → Tandai Dibayar\n2. Konfirmasi',
         'Status slip berubah ke PAID. Slip terkunci dari edit. Admin tidak bisa tandai dibayar'),
    (53, 'Slip honor Kids Class — honor flat per murid',
         '1. Lihat slip honor guru ICA (Kids Class)\n2. Cek honor per sesi',
         'Honor per sesi = jumlah murid Kids Class × Rp 42.500. Honor_code = H_KIDS'),
]

M07_SCENARIOS = [
    (54, 'Catat pengeluaran baru',
         '1. Pengeluaran → Tambah\n2. Isi kategori (Sewa/Listrik/dll), nominal, tanggal\n3. Simpan',
         'Pengeluaran tersimpan. Muncul di rekap pengeluaran bulan tsb'),
    (55, 'Lihat rekap pengeluaran bulanan',
         '1. Pengeluaran → filter bulan\n2. Lihat total per kategori',
         'Rekap menampilkan total per kategori dan grand total bulan tsb'),
]

M08_SCENARIOS = [
    (56, 'Buat event Mini Concert baru',
         '1. Event → Tambah Event\n2. Isi nama, tanggal, tipe (Mini Concert)\n3. Simpan',
         'Event terbuat dengan status DRAFT. Siap untuk pendaftaran peserta'),
    (57, 'Daftar murid ke event (Ujian + Tampil)',
         '1. Detail event → Daftar Peserta → Tambah\n2. Pilih murid, tipe: Ujian + Tampil (Rp 395.000)\n3. Simpan',
         'Murid terdaftar. Invoice event ter-generate dengan nominal Rp 395.000'),
    (58, 'Daftar murid ke event (Tampil saja)',
         '1. Pilih murid, tipe: Tampil saja (Rp 295.000)\n2. Simpan',
         'Murid terdaftar. Invoice Rp 295.000 ter-generate'),
    (59, 'Assign guru pendamping Konser KITA',
         '1. Detail event (DRAFT) → Peserta → pilih murid → Assign Guru Pendamping\n2. Pilih guru dari dropdown\n3. Simpan',
         'Guru pendamping tercatat di tabel event_participants. Bisa diubah selama event DRAFT'),
    (60, 'Input hasil ujian dan naik grade',
         '1. Event → peserta yang ikut ujian → Input Hasil\n2. Set hasil: Lulus, grade before, grade after\n3. Simpan',
         'Jika Lulus, grade murid di enrollment naik otomatis ke grade berikutnya'),
]

IMPORT_SCENARIOS = [
    (61, 'Upload file Excel template (dry-run preview)',
         '1. Import → Upload File Excel → pilih file template\n2. Submit tanpa konfirmasi (dry-run)',
         'Tabel preview tampil: data valid (hijau) dan error (merah). Belum ada data tersimpan'),
    (62, 'Konfirmasi import setelah preview bersih',
         '1. Setelah dry-run tidak ada error → klik Konfirmasi Import\n2. Tunggu proses',
         'Data murid, enrollment, dan jadwal tersimpan ke database. Laporan hasil tampil'),
    (63, 'Upload file Excel dengan baris error (nama kosong)',
         '1. Upload file dengan baris yang nama muridnya kosong\n2. Dry-run',
         'Baris error ditandai merah. Pesan error jelas. Baris valid tetap hijau'),
    (64, 'Upload file Excel dengan guru tidak dikenal',
         '1. Upload file dengan kode guru tidak ada di master data\n2. Dry-run',
         'Error: "Guru [kode] tidak ditemukan". Baris tersebut tidak diimport saat konfirmasi'),
    (65, 'Import murid Kids Class Bundle',
         '1. Upload file Excel dengan murid Kids Class Bundle\n2. Dry-run → preview\n3. Konfirmasi',
         'Murid Kids Class Bundle terimport. Jadwal mereka terbuat (tidak konflik meski guru sama)'),
    (66, 'Import murid duplikat (student_code sudah ada)',
         '1. Import ulang file yang muridnya sudah ada di database\n2. Dry-run',
         'Baris duplikat ditandai sebagai skip/warning, tidak error fatal. Murid tidak terduplikasi'),
]

KIDS_BUNDLE_SCENARIOS = [
    (67, 'Generate cicilan Kids Bundle — first time',
         '1. Detail murid (Aktif, KIDS_CLASS_BUNDLE) → Generate Cicilan Bundle\n2. Isi tanggal mulai program (YYYY-MM-DD)\n3. Konfirmasi',
         '3 invoice cicilan terbuat: termin 1 (bulan 1), termin 2 (bulan 2), termin 3 (bulan 4). Nominal total = harga paket'),
    (68, 'Generate cicilan Kids Bundle — sudah pernah dibuat',
         '1. Murid yang sudah punya cicilan → coba Generate Bundle lagi\n2. Konfirmasi',
         'Sistem menolak. Pesan: "Invoice cicilan sudah pernah dibuat untuk murid ini"'),
    (69, 'Catat pembayaran cicilan termin 1',
         '1. Detail invoice cicilan termin 1 → Catat Pembayaran\n2. Nominal sesuai tagihan → Simpan',
         'Termin 1 berstatus PAID. Panel progress cicilan menampilkan 1 dari 3 lunas'),
    (70, 'Lihat panel progress cicilan di detail invoice',
         '1. Buka salah satu invoice cicilan → lihat panel Progress Cicilan\n2. Lihat 3 termin',
         'Panel menampilkan 3 termin dengan status masing-masing (UNPAID/PARTIAL/PAID) dan due date'),
]


# ─── Main ──────────────────────────────────────────────────────────────────────

def build_document():
    doc = Document()

    # ── Margin halaman ──
    for section in doc.sections:
        section.top_margin    = Cm(2.0)
        section.bottom_margin = Cm(2.0)
        section.left_margin   = Cm(2.0)
        section.right_margin  = Cm(2.0)

    # ── Cover ──
    doc.add_paragraph()
    title = doc.add_paragraph()
    title.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r = title.add_run('UAT Checklist\nSistem Administrasi Musik KITA')
    set_font(r, size=18, bold=True, color=(0x1F, 0x49, 0x7D))

    doc.add_paragraph()
    meta_items = [
        ('Versi',    'v1.0'),
        ('Tanggal',  date.today().strftime('%d %B %Y')),
        ('Tester',   'Owner'),
        ('Lingkup',  'M01 – M08, Import Excel, Kids Class Bundle'),
        ('Dikecualikan', 'Kids Class Bundle & Monthly — dikelola manual sementara'),
    ]
    for label, value in meta_items:
        p = doc.add_paragraph()
        r1 = p.add_run(f'{label:<16}: ')
        set_font(r1, size=11, bold=True)
        r2 = p.add_run(value)
        set_font(r2, size=11)

    doc.add_paragraph()
    note = doc.add_paragraph()
    r = note.add_run(
        'PETUNJUK: Isi kolom Status dengan ✓ (lulus) atau ✗ (gagal) setelah '
        'menjalankan setiap skenario. Kolom Catatan diisi jika ada temuan penting.'
    )
    set_font(r, size=10)
    note.paragraph_format.space_after = Pt(12)

    # ── Halaman baru sebelum skenario ──
    doc.add_page_break()

    # ── Tabel per modul ──
    modules = [
        ('M01 — Master Data',                     M01_SCENARIOS),
        ('M02 — Pendaftaran & Trial',              M02_SCENARIOS),
        ('M03 — Penjadwalan',                      M03_SCENARIOS),
        ('M04 — Absensi',                          M04_SCENARIOS),
        ('M05 — Keuangan',                         M05_SCENARIOS),
        ('M06 — Honor Guru',                       M06_SCENARIOS),
        ('M07 — Pengeluaran',                      M07_SCENARIOS),
        ('M08 — Event',                            M08_SCENARIOS),
        ('Import Excel',                           IMPORT_SCENARIOS),
        ('KIDS Bundle — Cicilan',                  KIDS_BUNDLE_SCENARIOS),
    ]

    for module_name, scenarios in modules:
        add_scenario_table(doc, module_name, scenarios)

    # ── Footer: tanda tangan ──
    doc.add_page_break()
    add_heading(doc, 'Tanda Tangan Penguji', level=2)
    doc.add_paragraph()
    sign_table = doc.add_table(rows=3, cols=2)
    sign_table.style = 'Table Grid'
    labels = [
        ['Penguji / Tester', 'Tanggal UAT'],
        ['', ''],
        ['(                                    )', ''],
    ]
    for r_idx, row_vals in enumerate(labels):
        for c_idx, val in enumerate(row_vals):
            p = sign_table.rows[r_idx].cells[c_idx].paragraphs[0]
            run = p.add_run(val)
            set_font(run, size=10, bold=(r_idx == 0))

    return doc


if __name__ == '__main__':
    doc = build_document()
    out = os.path.abspath(OUTPUT_PATH)
    doc.save(out)
    total = sum(len(s[1]) for s in [
        ('', M01_SCENARIOS), ('', M02_SCENARIOS), ('', M03_SCENARIOS),
        ('', M04_SCENARIOS), ('', M05_SCENARIOS), ('', M06_SCENARIOS),
        ('', M07_SCENARIOS), ('', M08_SCENARIOS), ('', IMPORT_SCENARIOS),
        ('', KIDS_BUNDLE_SCENARIOS),
    ])
    print(f'✅ UAT Checklist berhasil dibuat: {out}')
    print(f'   Total skenario: {total}')
```

- [ ] **Step 2: Jalankan script untuk verifikasi**

```powershell
cd C:\laragon\www\musik-kita-ops
C:\laragon\bin\python\python-3.13\python.exe docs/generate-docs/generate_uat.py
```

Expected output:
```
✅ UAT Checklist berhasil dibuat: C:\laragon\www\musik-kita-ops\UAT-Musik-KITA-v1.0.docx
   Total skenario: 70
```

Buka file `UAT-Musik-KITA-v1.0.docx` dan verifikasi:
- Cover page tampil dengan metadata lengkap
- 10 tabel modul (M01–M08, Import, Kids Bundle)
- Kolom: No, Skenario, Langkah Uji, Expected Result, Status (✓/✗), Catatan
- Header tabel berwarna biru muda
- Halaman tanda tangan di akhir

- [ ] **Step 3: Commit script dan output**

```bash
git add docs/generate-docs/generate_uat.py UAT-Musik-KITA-v1.0.docx
git commit -m "Docs: Tambah generator UAT Checklist + output UAT-Musik-KITA-v1.0.docx (70 skenario)"
```

---

## Task 3: Script generate_manual.py — User Manual Admin (5 Bab)

**Files:**
- Create: `docs/generate-docs/generate_manual.py`

- [ ] **Step 1: Tulis script generate_manual.py**

Buat file `docs/generate-docs/generate_manual.py` dengan konten berikut:

```python
"""
Generate UserManual-Admin-Musik-KITA-v1.0.docx
Jalankan: C:\laragon\bin\python\python-3.13\python.exe docs/generate-docs/generate_manual.py
Output  : UserManual-Admin-Musik-KITA-v1.0.docx (di root project)
"""
from docx import Document
from docx.shared import Pt, Cm, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml.ns import qn
from docx.oxml import OxmlElement
from datetime import date
import os

OUTPUT_PATH = os.path.join(os.path.dirname(__file__), '..', '..', 'UserManual-Admin-Musik-KITA-v1.0.docx')

# ─── Helpers ──────────────────────────────────────────────────────────────────

def set_font(run, size=10.5, bold=False, color=None, italic=False):
    run.font.name = 'Calibri'
    run.font.size = Pt(size)
    run.bold = bold
    run.italic = italic
    if color:
        run.font.color.rgb = RGBColor(*color)

def add_heading(doc, text, level=1):
    p = doc.add_heading(text, level=level)
    sizes = {1: 16, 2: 13, 3: 11}
    for run in p.runs:
        run.font.name = 'Calibri'
        run.font.size = Pt(sizes.get(level, 11))
        run.font.color.rgb = RGBColor(0x1F, 0x49, 0x7D)
    return p

def add_body(doc, text, indent=False):
    p = doc.add_paragraph()
    if indent:
        p.paragraph_format.left_indent = Cm(0.5)
    run = p.add_run(text)
    set_font(run)
    return p

def add_step(doc, number, text):
    p = doc.add_paragraph(style='List Number')
    p.paragraph_format.left_indent = Cm(1.0)
    run = p.add_run(text)
    set_font(run)

def add_note(doc, text):
    p = doc.add_paragraph()
    p.paragraph_format.left_indent = Cm(0.5)
    r1 = p.add_run('ℹ️  Catatan: ')
    set_font(r1, bold=True, color=(0x00, 0x70, 0xC0))
    r2 = p.add_run(text)
    set_font(r2, italic=True, color=(0x00, 0x70, 0xC0))

def add_warning(doc, text):
    p = doc.add_paragraph()
    p.paragraph_format.left_indent = Cm(0.5)
    r1 = p.add_run('⚠️  Perhatian: ')
    set_font(r1, bold=True, color=(0xC0, 0x00, 0x00))
    r2 = p.add_run(text)
    set_font(r2, color=(0xC0, 0x00, 0x00))

def shade_header_row(table):
    tr = table.rows[0]._tr
    trPr = tr.get_or_add_trPr()
    shd = OxmlElement('w:shd')
    shd.set(qn('w:val'), 'clear')
    shd.set(qn('w:color'), 'auto')
    shd.set(qn('w:fill'), 'BDD7EE')
    trPr.append(shd)

def add_simple_table(doc, headers, rows, col_widths_cm):
    table = doc.add_table(rows=1, cols=len(headers))
    table.style = 'Table Grid'
    shade_header_row(table)
    hdr = table.rows[0].cells
    for i, h in enumerate(headers):
        p = hdr[i].paragraphs[0]
        run = p.add_run(h)
        set_font(run, size=9, bold=True)
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    for row_data in rows:
        row = table.add_row()
        for i, val in enumerate(row_data):
            p = row.cells[i].paragraphs[0]
            run = p.add_run(val)
            set_font(run, size=9)
    for row in table.rows:
        for i, cell in enumerate(row.cells):
            if i < len(col_widths_cm):
                cell.width = Cm(col_widths_cm[i])
    doc.add_paragraph()


# ─── Konten Bab ───────────────────────────────────────────────────────────────

def bab1_memulai(doc):
    add_heading(doc, 'Bab 1 — Memulai', level=1)

    add_heading(doc, '1.1  Login ke Sistem', level=2)
    add_body(doc, 'Sistem Musik KITA diakses melalui browser di komputer studio (terhubung ke WiFi studio).')
    add_step(doc, 1, 'Buka browser (Chrome/Firefox) dan masukkan alamat IP studio di address bar (contoh: 192.168.1.10).')
    add_step(doc, 2, 'Tampil halaman login. Masukkan Email dan Password yang diberikan owner.')
    add_step(doc, 3, 'Klik tombol "Masuk". Anda akan langsung masuk ke halaman Dashboard.')
    add_warning(doc, 'Ganti password default segera setelah pertama kali login. Hubungi owner untuk reset jika lupa password.')

    add_heading(doc, '1.2  Mengenal Tampilan Utama', level=2)
    add_body(doc, 'Setelah login, layar terbagi menjadi tiga bagian:')
    add_step(doc, 1, 'Sidebar (kiri): menu navigasi ke semua modul — Murid, Absensi, Keuangan, Honor Guru, dll.')
    add_step(doc, 2, 'Topbar (atas): nama pengguna yang sedang login, tombol ganti tema (☀️/🌙), dan tombol Keluar.')
    add_step(doc, 3, 'Area Konten (tengah-kanan): menampilkan data dan form sesuai menu yang dipilih.')
    add_note(doc, 'Gunakan tombol ☀️/🌙 di pojok kanan atas untuk mengganti tampilan terang/gelap sesuai kenyamanan.')

    add_heading(doc, '1.3  Perbedaan Role Owner vs Admin', level=2)
    add_body(doc, 'Sistem memiliki tiga level akses. Sebagai Admin, Anda memiliki akses operasional harian.')
    headers = ['Fitur', 'Owner', 'Admin']
    rows = [
        ('Input absensi harian', '✓', '✓'),
        ('Catat pembayaran', '✓', '✓'),
        ('Daftar murid baru', '✓', '✓'),
        ('Generate SPP & denda', '✓', '✓'),
        ('Ubah harga paket', '✓', '✗ (tidak bisa)'),
        ('Void pembayaran', '✓', '✗ (tidak bisa)'),
        ('Tandai honor dibayar', '✓', '✗ (tidak bisa)'),
        ('Hapus master data', '✓', '✗ (tidak bisa)'),
        ('Lihat audit log', '✓', '✗ (tidak bisa)'),
    ]
    add_simple_table(doc, headers, rows, [6.0, 2.5, 2.5])


def bab2_harian(doc):
    add_heading(doc, 'Bab 2 — Tugas Harian', level=1)

    add_heading(doc, '2.1  Input Absensi Sesi Hari Ini', level=2)
    add_body(doc, 'Lakukan setelah setiap sesi selesai, atau di akhir hari.')
    add_step(doc, 1, 'Klik menu "Absensi" di sidebar.')
    add_step(doc, 2, 'Pastikan tanggal yang tampil adalah hari ini. Klik tanggal lain jika perlu.')
    add_step(doc, 3, 'Cari sesi yang ingin diisi (tampil berurutan per jam).')
    add_step(doc, 4, 'Klik tombol "Input" atau ikon status di kolom Status.')
    add_step(doc, 5, 'Pilih salah satu status: HADIR, HADIR_TERLAMBAT, IZIN_RESCHEDULE, HANGUS, DIGANTI.')
    add_step(doc, 6, 'Jika HADIR_TERLAMBAT: isi berapa menit terlambat.')
    add_step(doc, 7, 'Jika DIGANTI: pilih guru pengganti dari dropdown.')
    add_step(doc, 8, 'Klik "Simpan".')
    add_note(doc, 'Untuk IZIN_RESCHEDULE: setelah simpan, tombol "Buat Sesi Pengganti" akan aktif. Isi tanggal, jam, dan ruang pengganti lalu simpan.')

    add_heading(doc, '2.2  Catat Pembayaran Murid', level=2)
    add_step(doc, 1, 'Klik menu "Keuangan" → "Daftar Invoice".')
    add_step(doc, 2, 'Cari invoice murid yang akan bayar (gunakan kolom pencarian nama atau nomor invoice).')
    add_step(doc, 3, 'Klik nomor invoice untuk membuka detail.')
    add_step(doc, 4, 'Klik tombol "Catat Pembayaran".')
    add_step(doc, 5, 'Isi: Nominal, Metode (CASH / TRANSFER / QRIS / DEBIT), Tanggal.')
    add_step(doc, 6, 'Jika TRANSFER: upload foto bukti transfer (opsional tapi dianjurkan).')
    add_step(doc, 7, 'Klik "Simpan". Kuitansi KW/YYYY/MM/NNNN otomatis terbuat.')
    add_note(doc, 'Status invoice otomatis berubah: UNPAID → PARTIAL (bayar sebagian) → PAID (lunas). Kuitansi bisa dicetak dari tombol "Cetak" di detail invoice.')

    add_heading(doc, '2.3  Cek Status Tagihan Outstanding', level=2)
    add_step(doc, 1, 'Klik menu "Keuangan" → "Daftar Invoice".')
    add_step(doc, 2, 'Klik filter "Status" → pilih "UNPAID".')
    add_step(doc, 3, 'Lihat kolom "Jatuh Tempo" — invoice dengan tanggal merah sudah lewat jatuh tempo.')
    add_step(doc, 4, 'Hubungi murid/orang tua untuk mengingatkan pembayaran.')
    add_warning(doc, 'Murid dengan tunggakan >1 bulan akan muncul peringatan di Dashboard. Segera laporkan ke owner jika ada tunggakan lama.')


def bab3_awal_bulan(doc):
    add_heading(doc, 'Bab 3 — Tugas Awal Bulan', level=1)
    add_body(doc, 'Lakukan tugas-tugas ini di awal setiap bulan.')

    add_heading(doc, '3.1  Generate SPP (Lakukan Tanggal 1)', level=2)
    add_body(doc, 'SPP = Surat Pembayaran Privat — tagihan bulanan untuk semua murid aktif.')
    add_step(doc, 1, 'Klik menu "Keuangan" → "Daftar Invoice".')
    add_step(doc, 2, 'Klik tombol "Generate SPP".')
    add_step(doc, 3, 'Pilih Tahun dan Bulan yang akan di-generate.')
    add_step(doc, 4, 'Klik "Generate". Sistem akan membuat invoice untuk semua murid Aktif.')
    add_step(doc, 5, 'Lihat pesan hasil: berapa invoice baru dibuat, berapa skip (sudah ada).')
    add_note(doc, 'Generate SPP bersifat idempotent — aman dijalankan ulang. Invoice yang sudah ada tidak akan duplikat.')
    add_warning(doc, 'Murid Kids Class Bundle TIDAK mendapat invoice SPP bulanan — sudah tercover di cicilan 3 termin.')

    add_heading(doc, '3.2  Apply Denda Keterlambatan (Lakukan Tanggal 11+)', level=2)
    add_body(doc, 'Denda Rp 5.000/hari berlaku mulai tanggal 11 untuk invoice yang belum dibayar.')
    add_step(doc, 1, 'Klik menu "Keuangan" → "Daftar Invoice".')
    add_step(doc, 2, 'Klik tombol "Apply Denda".')
    add_step(doc, 3, 'Pilih Tahun dan Bulan.')
    add_step(doc, 4, 'Klik "Apply". Sistem menambah item DENDA ke setiap invoice yang belum lunas.')
    add_note(doc, 'Denda dihitung otomatis: Rp 5.000 × (hari ini − 10). Contoh: bayar tanggal 15 → denda Rp 25.000.')

    add_heading(doc, '3.3  Kalkulasi Honor Guru', level=2)
    add_body(doc, 'Honor dihitung berdasarkan sesi yang sudah diisi status absensinya.')
    add_step(doc, 1, 'Klik menu "Honor Guru".')
    add_step(doc, 2, 'Klik tombol "Kalkulasi Honor".')
    add_step(doc, 3, 'Pilih Tahun dan Bulan.')
    add_step(doc, 4, 'Klik "Hitung". Sistem membuat/memperbarui slip honor untuk semua guru.')
    add_note(doc, 'Kalkulasi dilakukan H-2 sebelum akhir bulan (contoh: tanggal 28 untuk bulan 30 hari). Lakukan di hari yang tepat agar semua sesi sudah ter-input.')

    add_heading(doc, '3.4  Cetak & Distribusi Slip Honor Guru', level=2)
    add_step(doc, 1, 'Klik menu "Honor Guru" → pilih guru → klik "Detail Slip".')
    add_step(doc, 2, 'Periksa rincian: honor pokok, honor transport (isi jika ada), honor lain-lain + keterangan.')
    add_step(doc, 3, 'Klik "Cetak Slip". Halaman A4 akan terbuka.')
    add_step(doc, 4, 'Tekan Ctrl+P → simpan sebagai PDF atau cetak langsung.')
    add_step(doc, 5, 'Serahkan slip ke guru atau kirim via WhatsApp.')
    add_warning(doc, 'Hanya Owner yang bisa menandai slip sebagai "Dibayar". Setelah ditandai dibayar, slip terkunci dan tidak bisa diedit.')


def bab4_insidental(doc):
    add_heading(doc, 'Bab 4 — Tugas Insidental', level=1)

    add_heading(doc, '4.1  Daftar Murid Baru (Status Calon)', level=2)
    add_step(doc, 1, 'Klik menu "Murid" → tombol "Tambah Murid".')
    add_step(doc, 2, 'Isi semua data yang wajib: Nama Lengkap, Gender, Tanggal Lahir.')
    add_step(doc, 3, 'Isi data orang tua/wali: Nama, No. HP, Hubungan.')
    add_step(doc, 4, 'Email murid bersifat opsional (boleh kosong).')
    add_step(doc, 5, 'Klik "Simpan". Murid terdaftar dengan status Calon dan kode otomatis (M-YYYY-NNNN).')

    add_heading(doc, '4.2  Jadwalkan Sesi Trial', level=2)
    add_step(doc, 1, 'Buka detail murid (status Calon).')
    add_step(doc, 2, 'Klik tombol "Jadwalkan Trial".')
    add_step(doc, 3, 'Isi: Tanggal Trial, Jam, Guru, Paket yang diminati.')
    add_step(doc, 4, 'Klik "Simpan". Status murid berubah ke Trial. Sesi trial terbuat.')
    add_note(doc, 'Semua sesi trial berdurasi 30 menit, apapun paket yang diminati.')

    add_heading(doc, '4.3  Konversi Trial → Aktif', level=2)
    add_step(doc, 1, 'Buka detail murid (status Trial).')
    add_step(doc, 2, 'Klik tombol "Konversi ke Aktif".')
    add_step(doc, 3, 'Pilih: Paket, Guru, Ruangan, Hari, Jam jadwal tetap.')
    add_step(doc, 4, 'Klik "Konfirmasi". Status murid berubah ke Aktif.')
    add_step(doc, 5, 'Sistem otomatis membuat Invoice Registrasi Rp 250.000 + Invoice SPP bulan berjalan.')
    add_step(doc, 6, 'Minta murid/orang tua segera melunasi kedua invoice tersebut.')

    add_heading(doc, '4.4  Skip Trial — Langsung Aktif', level=2)
    add_step(doc, 1, 'Buka detail murid (status Calon).')
    add_step(doc, 2, 'Klik tombol "Skip Trial & Aktifkan".')
    add_step(doc, 3, 'Pilih alasan: Walk-in (datang langsung), Migrasi, Reaktivasi, atau Lulus Kids Class.')
    add_step(doc, 4, 'Isi data kelas: Paket, Guru, Ruangan, Hari, Jam.')
    add_step(doc, 5, 'Klik "Konfirmasi". Murid langsung Aktif + invoice terbuat.')

    add_heading(doc, '4.5  Pengajuan Cuti Murid', level=2)
    add_step(doc, 1, 'Buka detail murid (status Aktif).')
    add_step(doc, 2, 'Klik tombol "Ajukan Cuti".')
    add_step(doc, 3, 'Isi tanggal mulai dan tanggal berakhir cuti (maks 1 bulan).')
    add_step(doc, 4, 'Klik "Simpan". Status murid berubah ke Cuti.')
    add_step(doc, 5, 'Tambahkan biaya cuti Rp 100.000 ke tagihan murid via "Tambah Item" di invoice.')
    add_warning(doc, 'Cuti hanya bisa diperpanjang 1 kali (total maksimal 2 bulan). Murid cuti tidak mendapat tagihan SPP tapi tetap dikenai biaya cuti.')

    add_heading(doc, '4.6  Reschedule Sesi (Izin Murid)', level=2)
    add_body(doc, 'Reschedule hanya diperbolehkan jika: (1) murid memberi info minimal 5 jam sebelum sesi, DAN (2) ini adalah izin pertama murid di bulan tersebut.')
    add_step(doc, 1, 'Input absensi sesi → pilih status IZIN_RESCHEDULE → simpan.')
    add_step(doc, 2, 'Tombol "Buat Sesi Pengganti" akan aktif.')
    add_step(doc, 3, 'Klik tombol tersebut. Modal kecil terbuka.')
    add_step(doc, 4, 'Isi: Tanggal Pengganti, Jam, Ruangan.')
    add_step(doc, 5, 'Klik "Simpan". Sistem cek konflik guru dan ruangan secara otomatis.')
    add_note(doc, 'Izin ke-2 dan seterusnya di bulan yang sama tidak bisa reschedule — gunakan status IZIN_VIDEO (sesi dianggap hadir, guru tetap dapat honor).')

    add_heading(doc, '4.7  Tambah Kelas Baru untuk Murid Existing (Multi-Kelas)', level=2)
    add_step(doc, 1, 'Buka detail murid (status Aktif).')
    add_step(doc, 2, 'Klik tab "Kelas".')
    add_step(doc, 3, 'Klik tombol "Tambah Kelas".')
    add_step(doc, 4, 'Pilih: Paket baru, Guru, Ruangan, Hari, Jam.')
    add_step(doc, 5, 'Klik "Simpan". Enrollment baru terbuat dengan status ACTIVE.')
    add_note(doc, 'Invoice SPP otomatis hanya untuk kelas utama (primary enrollment). Invoice kelas tambahan dibuat manual jika diperlukan.')

    add_heading(doc, '4.8  Hentikan Kelas / Murid Mundur', level=2)
    add_body(doc, 'Untuk hentikan salah satu kelas (enrollment non-primary):')
    add_step(doc, 1, 'Tab "Kelas" di detail murid → klik "Hentikan" pada enrollment yang dituju.')
    add_step(doc, 2, 'Isi alasan. Klik "Konfirmasi". Enrollment berubah COMPLETED.')
    add_body(doc, 'Untuk hentikan semua kelas (murid mundur):')
    add_step(doc, 3, 'Buka detail murid → klik tombol "Mundurkan Murid".')
    add_step(doc, 4, 'Isi alasan pengunduran diri. Klik "Konfirmasi".')
    add_step(doc, 5, 'Status murid berubah ke Mengundurkan Diri. Semua enrollment COMPLETED.')
    add_warning(doc, 'Jika murid mundur dan ingin kembali lagi di kemudian hari, mereka WAJIB membayar ulang biaya registrasi Rp 250.000.')

    add_heading(doc, '4.9  Generate Cicilan Kids Class Bundle', level=2)
    add_body(doc, 'Khusus untuk murid Kids Class yang memilih opsi bayar cicilan 3 termin.')
    add_step(doc, 1, 'Buka detail murid (Aktif, Kids Class Bundle).')
    add_step(doc, 2, 'Klik tombol "Generate Cicilan Bundle".')
    add_step(doc, 3, 'Isi Tanggal Mulai Program (format YYYY-MM-DD, contoh: 2026-06-01).')
    add_step(doc, 4, 'Klik "Konfirmasi". Tiga invoice cicilan terbuat sekaligus.')
    add_note(doc, 'Cicilan terbagi: Termin 1 (bulan ke-1), Termin 2 (bulan ke-2), Termin 3 (bulan ke-4). Total ketiga termin = Rp 2.180.000.')
    add_warning(doc, 'Generate cicilan hanya bisa dilakukan SEKALI per murid. Sistem akan menolak jika cicilan sudah pernah dibuat.')


def bab5_referensi(doc):
    add_heading(doc, 'Bab 5 — Referensi Cepat', level=1)

    add_heading(doc, '5.1  Status Murid dan Transisi yang Valid', level=2)
    headers = ['Status', 'Arti', 'Transisi yang Mungkin']
    rows = [
        ('Calon',               'Sudah daftar, belum trial',        '→ Trial (jadwal trial) | → Aktif (skip trial)'),
        ('Trial',               'Sedang/sudah trial, belum aktif',  '→ Aktif (konversi) | → Mengundurkan Diri'),
        ('Aktif',               'Murid berjalan, kena tagihan SPP', '→ Cuti | → Mengundurkan Diri | → Selesai'),
        ('Cuti',                'Sedang cuti berbayar, sesi pause', '→ Aktif (cuti berakhir) | → Mengundurkan Diri'),
        ('Selesai',             'Lulus Kids Class 6 bulan',         '→ Aktif (re-enroll privat, tanpa biaya reg)'),
        ('Mengundurkan Diri',   'Keluar dari studio',               '→ Aktif (re-aktivasi, bayar reg ulang Rp 250.000)'),
    ]
    add_simple_table(doc, headers, rows, [3.0, 4.0, 7.0])

    add_heading(doc, '5.2  Format Nomor Dokumen', level=2)
    headers = ['Jenis Dokumen', 'Format', 'Contoh']
    rows = [
        ('Invoice',         'INV/YYYY/MM/NNNN',  'INV/2026/05/0001'),
        ('Kuitansi',        'KW/YYYY/MM/NNNN',   'KW/2026/05/0001'),
        ('Slip Honor Guru', 'SLIP/YYYY/MM/NNNN', 'SLIP/2026/05/0001'),
        ('Kode Murid',      'M-YYYY-NNNN',       'M-2026-0001'),
    ]
    add_simple_table(doc, headers, rows, [4.5, 4.5, 4.5])

    add_heading(doc, '5.3  Kode Honor Guru', level=2)
    headers = ['Kode', 'Skenario', 'Nominal']
    rows = [
        ('H_REG',    'Sesi reguler (hadir/telat/no-show/hangus)',     'harga paket × 50% / 4'),
        ('H_TRIAL',  'Sesi trial — murid hadir',                      'Sama seperti H_REG'),
        ('TRIAL_NS', 'Sesi trial — murid no-show',                    'Rp 0'),
        ('H_VIDEO',  'Izin video pengganti (izin ke-2+)',             'Sama seperti H_REG'),
        ('H_LIBUR',  'Sesi libur nasional (guru tetap dibayar)',      'Sama seperti H_REG'),
        ('H_HANGUS', 'Murid tidak hadir tanpa konfirmasi',            'Sama seperti H_REG (guru tetap dibayar)'),
        ('H_PENG',   'Sesi diajar guru pengganti (ke pengganti)',     'Sama seperti H_REG'),
        ('H_KIDS',   'Sesi Kids Class (per murid dalam grup)',        'Rp 42.500 × jumlah murid'),
        ('H_UJIAN',  'Pengawas ujian grade',                         'Rp 250.000 flat'),
        ('H_IZIN',   'Sesi original IZIN_RESCHEDULE (guru tidak datang)', 'Rp 0 (dibayar via sesi pengganti)'),
    ]
    add_simple_table(doc, headers, rows, [2.5, 7.0, 4.0])

    add_heading(doc, '5.4  Cara Eskalasi Masalah ke Owner', level=2)
    add_body(doc, 'Jika menemukan masalah yang tidak bisa diselesaikan sendiri, hubungi owner via WhatsApp dengan format:')
    add_body(doc, '"[SISTEM] [Modul] – Masalah singkat – Langkah yang sudah dicoba"', indent=True)
    add_body(doc, 'Contoh:')
    add_body(doc, '"[SISTEM] M05 – Invoice SPP bulan Juni tidak muncul untuk murid Ahmad – sudah coba generate ulang, tetap tidak ada"', indent=True)
    add_body(doc, '')
    add_body(doc, 'Tangkap screenshot error jika ada (gunakan Windows+Shift+S atau PrtScn), kirim bersama pesan WhatsApp.')

    add_heading(doc, '5.5  Komponen Tagihan Manual (dari Katalog)', level=2)
    headers = ['Kode', 'Nama', 'Nominal']
    rows = [
        ('REG',     'Biaya Registrasi',       'Rp 250.000'),
        ('SPP',     'SPP Bulanan',             'Sesuai paket'),
        ('CUTI',    'Biaya Cuti',              'Rp 100.000/pengajuan'),
        ('UJI',     'Ujian + Mini Concert',    'Rp 395.000'),
        ('MC',      'Mini Concert saja',       'Rp 295.000'),
        ('KIDS_FP', 'Final Project Kids Class','Rp 140.000/murid'),
        ('DENDA',   'Denda Keterlambatan',     'Rp 5.000/hari (mulai tgl 11)'),
        ('DISKON',  'Diskon Manual',           'Nominal Rp atau % dari item'),
    ]
    add_simple_table(doc, headers, rows, [2.0, 5.0, 4.5])
    add_note(doc, 'Tambah item manual ke invoice via halaman detail invoice → tombol "Tambah Item". Pilih dari daftar katalog di atas. Untuk item DISKON, wajib isi alasan diskon.')


# ─── Main ──────────────────────────────────────────────────────────────────────

def build_document():
    doc = Document()

    # ── Margin ──
    for section in doc.sections:
        section.top_margin    = Cm(2.0)
        section.bottom_margin = Cm(2.0)
        section.left_margin   = Cm(2.5)
        section.right_margin  = Cm(2.0)

    # ── Cover ──
    doc.add_paragraph()
    title = doc.add_paragraph()
    title.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r = title.add_run('Panduan Penggunaan Sistem\nRole: Admin')
    set_font(r, size=20, bold=True, color=(0x1F, 0x49, 0x7D))

    doc.add_paragraph()
    sub = doc.add_paragraph()
    sub.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r2 = sub.add_run('Musik KITA')
    set_font(r2, size=14, bold=True)

    doc.add_paragraph()
    meta_items = [
        ('Versi',      'v1.0'),
        ('Tanggal',    date.today().strftime('%d %B %Y')),
        ('Berlaku untuk', 'Role: Admin'),
        ('Catatan',    'Kids Class Bundle & Monthly dikelola manual sementara'),
    ]
    for label, value in meta_items:
        p = doc.add_paragraph()
        r1 = p.add_run(f'{label:<18}: ')
        set_font(r1, size=11, bold=True)
        r2 = p.add_run(value)
        set_font(r2, size=11)

    doc.add_page_break()

    # ── Daftar Isi (manual) ──
    add_heading(doc, 'Daftar Isi', level=1)
    toc_items = [
        ('Bab 1 — Memulai',                          '1.1 Login | 1.2 Tampilan Utama | 1.3 Owner vs Admin'),
        ('Bab 2 — Tugas Harian',                     '2.1 Input Absensi | 2.2 Catat Pembayaran | 2.3 Cek Tagihan'),
        ('Bab 3 — Tugas Awal Bulan',                 '3.1 Generate SPP | 3.2 Apply Denda | 3.3 Kalkulasi Honor | 3.4 Cetak Slip'),
        ('Bab 4 — Tugas Insidental',                 '4.1–4.9: Daftar murid, trial, konversi, cuti, reschedule, multi-kelas, mundur, bundle'),
        ('Bab 5 — Referensi Cepat',                  'Status murid, format nomor dokumen, kode honor, eskalasi, katalog tagihan'),
    ]
    for chapter, detail in toc_items:
        p = doc.add_paragraph()
        r1 = p.add_run(chapter)
        set_font(r1, bold=True)
        r2 = p.add_run(f'\n    {detail}')
        set_font(r2, size=9.5, italic=True, color=(0x60, 0x60, 0x60))

    doc.add_page_break()

    # ── Bab-bab ──
    bab1_memulai(doc)
    doc.add_page_break()
    bab2_harian(doc)
    doc.add_page_break()
    bab3_awal_bulan(doc)
    doc.add_page_break()
    bab4_insidental(doc)
    doc.add_page_break()
    bab5_referensi(doc)

    return doc


if __name__ == '__main__':
    doc = build_document()
    out = os.path.abspath(OUTPUT_PATH)
    doc.save(out)
    print(f'✅ User Manual Admin berhasil dibuat: {out}')
    print(f'   5 bab: Memulai, Tugas Harian, Awal Bulan, Insidental, Referensi Cepat')
```

- [ ] **Step 2: Jalankan script untuk verifikasi**

```powershell
cd C:\laragon\www\musik-kita-ops
C:\laragon\bin\python\python-3.13\python.exe docs/generate-docs/generate_manual.py
```

Expected output:
```
✅ User Manual Admin berhasil dibuat: C:\laragon\www\musik-kita-ops\UserManual-Admin-Musik-KITA-v1.0.docx
   5 bab: Memulai, Tugas Harian, Awal Bulan, Insidental, Referensi Cepat
```

Buka file `UserManual-Admin-Musik-KITA-v1.0.docx` dan verifikasi:
- Cover page: judul, studio, versi, catatan
- Daftar Isi
- Bab 1–5 dengan sub-bab sesuai spec
- Langkah bernomor (List Number style)
- Catatan biru dan peringatan merah tampil

- [ ] **Step 3: Commit script dan output**

```bash
git add docs/generate-docs/generate_manual.py UserManual-Admin-Musik-KITA-v1.0.docx
git commit -m "Docs: Tambah generator User Manual Admin + output UserManual-Admin-Musik-KITA-v1.0.docx (5 bab)"
```

---

## Task 4: Final Verification dan Commit Penutup

**Files:**
- Verify: `UAT-Musik-KITA-v1.0.docx`
- Verify: `UserManual-Admin-Musik-KITA-v1.0.docx`

- [ ] **Step 1: Jalankan kedua script sekaligus untuk memastikan re-runnable**

```powershell
cd C:\laragon\www\musik-kita-ops
C:\laragon\bin\python\python-3.13\python.exe docs/generate-docs/generate_uat.py
C:\laragon\bin\python\python-3.13\python.exe docs/generate-docs/generate_manual.py
```

Expected: Kedua ✅ tampil, tidak ada error. File ter-overwrite tanpa masalah.

- [ ] **Step 2: Cek ukuran file output (sanity check)**

```powershell
Get-Item "C:\laragon\www\musik-kita-ops\UAT-Musik-KITA-v1.0.docx",
          "C:\laragon\www\musik-kita-ops\UserManual-Admin-Musik-KITA-v1.0.docx" |
    Select-Object Name, @{N='Size KB';E={[math]::Round($_.Length/1KB,1)}}
```

Expected: Kedua file ada dan ukuran > 20 KB (ada konten, bukan file kosong).

- [ ] **Step 3: Commit final**

```bash
git add UAT-Musik-KITA-v1.0.docx UserManual-Admin-Musik-KITA-v1.0.docx docs/generate-docs/
git commit -m "Docs: Regenerate UAT + User Manual — final verified re-runnable"
```

---

## Cara Re-Run (Jika Ada Revisi Konten)

Edit konten di dalam script (data skenario di `generate_uat.py` atau teks bab di `generate_manual.py`), lalu jalankan ulang:

```powershell
C:\laragon\bin\python\python-3.13\python.exe docs/generate-docs/generate_uat.py
C:\laragon\bin\python\python-3.13\python.exe docs/generate-docs/generate_manual.py
```

File output akan ter-overwrite otomatis.
