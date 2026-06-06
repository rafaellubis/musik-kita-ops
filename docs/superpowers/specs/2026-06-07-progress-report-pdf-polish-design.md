# Design Spec: Progress Report PDF Polish + Nomor Laporan

**Tanggal:** 2026-06-07  
**Status:** Approved (mockup v2.2)  
**Mockup referensi:** `docs/mockups/2026-06-07-progress-report-pdf-polish.html`

---

## 1. Ringkasan

Perbaikan visual PDF laporan progress guru agar lebih profesional (corporate neutral), ber-branding Musik KITA, dengan nomor laporan unik otomatis. Scope utama: **PDF output** + **generator nomor** + **tampilan nomor di admin index/show**. Form guru dan admin show tetap memakai tema web existing (tidak diubah warna).

---

## 2. Nomor Laporan Otomatis

### 2.1 Format

| Komponen | Nilai |
|---|---|
| Prefix | `LMK/LPR` |
| Pola lengkap | `LMK/LPR/YYYY/MM/NNNN` |
| Contoh | `LMK/LPR/2026/06/0001` |
| Reset sequence | Per bulan (sama seperti INV, KW, SLIP) |

### 2.2 Kapan digenerate

- Saat guru **membuat laporan baru** via `POST /guru/laporan` (`GuruController::laporanStore`)
- Disimpan ke kolom `progress_reports.report_number` (string 30, unique, nullable)
- Laporan lama (sebelum fitur ini) boleh `NULL` — PDF tidak menampilkan baris nomor jika kosong

### 2.3 Implementasi

- Service: `App\Services\ProgressReportService::generateReportNumber(int $year, int $month)`
- Query nomor terakhir: `LIKE 'LMK/LPR/{year}/{MM}/%'`, increment NNNN

---

## 3. Palet Warna PDF (Corporate Neutral)

Tidak mengikuti template gold/cream sistem web.

| Token | Hex | Pemakaian |
|---|---|---|
| Navy (aksen) | `#1E3A5F` | Header strip, section title, progress fill, sel kesimpulan aktif |
| Teks utama | `#1F2937` | Isi laporan |
| Teks sekunder | `#6B7280` | Label meta, nomor laporan, footnote |
| Border | `#D1D5DB` | Kotak minggu, catatan, meta box |
| Background kotak | `#F9FAFB` | Minggu 1–4, catatan naratif |
| Progress track | `#E5E7EB` | Background bar |
| Bintang rating | `#B45309` | ★/☆ (kontras baik di cetak) |

---

## 4. Layout PDF

### 4.1 Urutan section (atas → bawah)

1. **Nomor laporan** — kanan atas, monospace, di atas branded header
2. **Branded header** — strip navy: logo kiri, teks kanan
3. **Meta box** — Nama, Instrumen, Guru, Bulan, Rating Anak
4. Kehadiran & Materi (Minggu 1–4)
5. Perkembangan bulanan (4 rating bintang)
6. Catatan perkembangan musikal
7. Catatan karakter
8. **Laporan Progress** (grid 4 kesimpulan)
9. Footer: emoji + instrumen/level + bar progress
10. TTD + footnote

### 4.2 Branded header

| Elemen | Konten |
|---|---|
| Logo | `public/images/logo-musikkita-dark-mode.PNG` via `public_path()`; fallback teks "MUSIK KITA" |
| Judul | `MONTHLY PROGRESS REPORT` (uppercase, putih) |
| Subtitle | **Les Musik KITA** (bold, putih) — bukan "Studio Musik KITA", tanpa "Laporan Bulanan" |
| Periode | `Periode: {namaBulan()}` |

### 4.3 Field Instrumen + Level

Helper: `Package::getReportInstrumentLabel()`

| Tipe paket | Output contoh |
|---|---|
| REGULER Basic | `Piano · Basic` |
| REGULER L1–L4 | `Piano · Level 1` … `Level 4` (bukan `Level L1`) |
| DUO | `Piano · Basic` |
| HOBBY / Kids Class | `Vocal` saja (tanpa level) |

### 4.4 Progress bar

- Label kecil: **PROGRESS** (uppercase navy)
- Lebar bar tetap **220px** (bukan full lebar kertas)
- Tinggi 10px, fill navy, track abu
- Label persen **`{n}%`** di kanan bar (inline)

### 4.5 TTD

```
Jakarta, {tanggal submit}
Guru Pengajar

[ruang tanda tangan 50px]

{Nama Guru}
Pengajar {Instrumen}
```

Semua elemen TTD **center-aligned** dalam kolom 200px, posisi kanan halaman (sejajar vertikal dengan nama guru).

### 4.6 Label teks

| Lokasi | Teks |
|---|---|
| `<title>` | `Progress Report — {nama murid}` |
| Section kesimpulan | **Laporan Progress** (bukan "Kesimpulan Progress") |
| TTD label | **Guru Pengajar** (bukan "Hormat kami,") |

---

## 5. DomPDF Constraints

- Layout table-based (no flexbox)
- Logo: path lokal `public_path()`, cek `file_exists()`
- Warna bar: `bgcolor` attribute pada `<td>`
- Font: DejaVu Sans (+ DejaVu Sans Mono untuk nomor laporan)

---

## 6. Admin UI (minimal)

| Halaman | Perubahan |
|---|---|
| `progress-reports/index` | Kolom **No. Laporan** (`report_number`, monospace) |
| `progress-reports/show` | Tampilkan nomor di subtitle header; instrumen pakai `getReportInstrumentLabel()`; section **Laporan Progress** |

Form guru (`laporan-form`) **out of scope** untuk polish visual ini.

---

## 7. Testing

| Test | Cakupan |
|---|---|
| `ProgressReportServiceTest` | Generate `LMK/LPR/...`, increment per bulan |
| `PackageReportLabelTest` | HOBBY, DUO, REGULER L2 label |
| `ProgressReportGuruTest` | Nomor saat create, increment, PDF download OK |

---

## 8. Out of Scope

- Redesign form guru / admin show theme colors
- Email/WhatsApp kirim PDF otomatis

## 9. Backfill Nomor Laporan Lama

- Method: `ProgressReportService::backfillReportNumbers()`
- Target: semua row `report_number IS NULL`
- Urutan: `year ASC`, `month ASC`, `id ASC` (kronologis create)
- Migration: `2026_06_07_130000_backfill_progress_report_numbers.php` (one-time saat deploy)
