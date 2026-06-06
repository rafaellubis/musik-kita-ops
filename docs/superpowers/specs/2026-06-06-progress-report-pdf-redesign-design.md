# Design Spec: Progress Report PDF Redesign

**Tanggal:** 2026-06-06  
**Fitur:** Redesign laporan progres bulanan (PDF, form guru, halaman admin)  
**Status:** Draft

---

## 1. Latar Belakang

Sistem lama menggunakan template checklist untuk laporan progres. Desain baru menggantinya dengan:
- Rating terstruktur per aspek musikal (input manual guru)
- Catatan naratif perkembangan
- Progress bar kontinu 0–100%
- Kesimpulan progres berupa pilihan enum
- Ringkasan per sesi (Minggu 1–4) otomatis dari `progress_report_session_notes`

Kolom lama (`highlight`, `summary_notes`, `target_notes`, `repertoire`, checklist) **tetap ada di DB** untuk laporan historis, tidak diupdate atau ditampilkan di alur baru.

---

## 2. Mockup → DB Field Mapping

### 2.1 Header Laporan

| Elemen Mockup | Sumber Data | Tabel / Kolom |
|---|---|---|
| Nama | Nama murid | `students.full_name` |
| Instrumen | Jenis instrumen | `enrollment → package → instrument → name` |
| Guru Pengajar | Nama guru | `users.name` (guru yang mengajar) |
| Bulan | Bulan sesi laporan | `progress_reports.month` + `progress_reports.year` (dirender via `$progressReport->namaBulan()`) |
| Rating Anak hari Ini | Rata-rata `session_rating` sesi bulan ini | Dihitung dari `progress_report_session_notes.session_rating` WHERE bulan = `report_month` |

**Catatan:** "Rating Anak hari Ini" adalah nilai **otomatis** (bukan input guru). Jika tidak ada `session_rating` sama sekali di sesi bulan itu, tampilkan `"—"`.

### 2.2 Kehadiran dan Materi (Minggu 1–4)

| Elemen Mockup | Sumber Data | Tabel / Kolom |
|---|---|---|
| Minggu 1 | Materi sesi ke-1 bulan ini | `progress_report_session_notes.material_learned` WHERE `session_sequence = 1` |
| Minggu 2 | Materi sesi ke-2 bulan ini | `progress_report_session_notes.material_learned` WHERE `session_sequence = 2` |
| Minggu 3 | Materi sesi ke-3 bulan ini | `progress_report_session_notes.material_learned` WHERE `session_sequence = 3` |
| Minggu 4 | Materi sesi ke-4 bulan ini | `progress_report_session_notes.material_learned` WHERE `session_sequence = 4` |

**Catatan:** `session_sequence` di-assign per sesi dalam bulan yang sama (`1`, `2`, `3`, `4`). Jika sesi tidak ada atau `material_learned` kosong/null, box tampil `"—"`.

### 2.3 Rating Bulanan (Input Manual Guru)

| Elemen Mockup | DB Column | Tipe | Range |
|---|---|---|---|
| Teknik Bermain ⭐ | `progress_reports.rating_teknik` | `unsignedTinyInteger nullable` | 1–5 |
| Materi ⭐ | `progress_reports.rating_materi` | `unsignedTinyInteger nullable` | 1–5 |
| Reading ⭐ | `progress_reports.rating_reading` | `unsignedTinyInteger nullable` | 1–5 |
| Repertoar ⭐ | `progress_reports.rating_repertoar` | `unsignedTinyInteger nullable` | 1–5 |

Rating ini **diisi manual oleh guru** melalui form laporan. Bukan dihitung otomatis.

### 2.4 Catatan Guru

| Elemen Mockup | DB Column | Tipe |
|---|---|---|
| Catatan Perkembangan Musikal | `progress_reports.catatan_perkembangan_musikal` | `text nullable` |
| Catatan Karakter | `progress_reports.catatan_karakter` | `text nullable` |

**Catatan:** Mockup asli menampilkan dua box "Catatan Guru Terhadap Karakter" — ini adalah typo/duplikat. Hanya **satu field** `catatan_karakter`.

### 2.5 Kesimpulan Progress

| Elemen Mockup | DB Column | Tipe | Nilai Enum |
|---|---|---|---|
| Perlu Pendampingan Lebih | `progress_reports.kesimpulan_progress` | `enum nullable` | `PERLU_PENDAMPINGAN` |
| Cukup | `progress_reports.kesimpulan_progress` | `enum nullable` | `CUKUP` |
| Baik | `progress_reports.kesimpulan_progress` | `enum nullable` | `BAIK` |
| Sangat Baik | `progress_reports.kesimpulan_progress` | `enum nullable` | `SANGAT_BAIK` |

Ditampilkan sebagai 4 box di form/PDF; box yang dipilih disorot/aktif.

### 2.6 Footer: Instrumen & Progress Bar

| Elemen Mockup | Sumber Data | DB Column / Config |
|---|---|---|
| Emoji instrumen | Map statis | `config/instruments.php` → `emoji` |
| Jenis instrumen | Instrumen murid | `instruments.name` |
| Level | Level paket murid | `Package::getLevelLabel()` (diturunkan dari `packages.class_type`, `packages.grade`, `isKidsClass()`, `isDuo()`) |
| Progress bar (████░░) | Input manual guru 0–100% | `progress_reports.progress_percent` (`unsignedTinyInteger nullable`) |
| Label % | Nilai `progress_percent` | Tampil di kanan bar: `"40%"` |

Progress bar adalah **kontinu** (bukan segmented). Label persentase tampil di kanan bar.

---

## 3. Data Flow

### 3.1 Session Notes Sync → Weekly Materials

```
[Guru mengisi catatan per sesi di form jadwal/absensi]
        ↓
progress_report_session_notes
  - progress_report_id  (FK ke progress_reports)
  - session_sequence    (1, 2, 3, 4 — urutan sesi dalam bulan ini)
  - material_learned    (text)
  - session_rating      (tinyInteger nullable, 1–5)
        ↓
[Form laporan guru membaca session notes]
  - weeklyMaterials[1..4] = material_learned by session_sequence
  - averageSessionRating  = AVG(session_rating) WHERE session_rating IS NOT NULL
        ↓
[Ditampilkan di form guru & PDF]
  - Minggu 1–4 box = weeklyMaterials[1..4]
  - Rating Anak hari Ini = averageSessionRating (dibulatkan 1 desimal, atau "—")
```

### 3.2 Form Guru → DB → PDF

```
[Guru membuka form laporan bulan ini]
  ↓ Load: header data, session notes (otomatis)
  ↓ Input: rating_teknik, rating_materi, rating_reading, rating_repertoar
            catatan_perkembangan_musikal, catatan_karakter
            kesimpulan_progress, progress_percent
  ↓
[Submit sebagai Draft]   → semua field nullable, status = 'draft'
[Submit sebagai Final]   → validasi required fields, status = 'submitted'
  ↓
[Admin membuka halaman show]
  → Tampil semua data laporan (read-only)
  → Tombol "Cetak PDF" → generate PDF dari data yang sama
```

### 3.3 Average Session Rating Calculation

```php
// Pseudo-code
$notes = $progressReport->sessionNotes; // progress_report_session_notes
$ratings = $notes->pluck('session_rating')->filter(); // hapus null
$averageRating = $ratings->isNotEmpty()
    ? round($ratings->avg(), 1)
    : null; // null → tampil "—"
```

---

## 4. Edge Cases

| Skenario | Behavior yang Diharapkan | Implementasi |
|---|---|---|
| **Bulan < 4 sesi** (misal hanya 2 sesi) | Minggu 3 dan Minggu 4 box tampil `"—"` | Query `session_sequence` 1–4; jika tidak ada record, return null/empty string |
| **Sesi ada, `material_learned` kosong/null** | Box tampil `"—"` | Cek `empty($material_learned)` atau `is_null` |
| **Tidak ada `session_rating` sama sekali** | Header "Rating Anak hari Ini" tampil `"—"` | `$ratings->isNotEmpty()` check sebelum AVG |
| **Semua `session_rating` null (ada sesi tapi belum diisi)** | Header tampil `"—"` | Filter null sebelum AVG; koleksi kosong → `"—"` |
| **Laporan lama (punya checklist data)** | Checklist tetap di DB, **tidak ditampilkan** di UI baru | Form/PDF baru hanya render field baru; kolom lama diabaikan |
| **`progress_percent` = 0** | Progress bar tampil kosong, label `"0%"` | 0 adalah nilai valid; null = field belum diisi (tampil kosong di draft) |
| **`kesimpulan_progress` = null (draft)** | Tidak ada box yang disorot | Semua box tampil unselected/abu-abu |
| **Rating 1–5, nilai di luar range** | Validasi DB-level (tinyInteger) + validation rule `between:1,5` | Migration: `unsignedTinyInteger`; validation: `nullable|integer|between:1,5` |
| **`session_sequence` > 4** | Minggu 5+ diabaikan di tampilan (hanya render 1–4) | Loop/slice hanya untuk sequence 1, 2, 3, 4 |
| **Murid tanpa emoji instrumen di config** | Fallback: tidak ada emoji, instrumen name saja | `config('instruments.emoji', '')` dengan default empty string |

---

## 5. Validation Matrix

### 5.1 Field-level Validation

| Field | Draft | Submit (Final) | Rule |
|---|---|---|---|
| `rating_teknik` | nullable | required | `integer\|between:1,5` |
| `rating_materi` | nullable | required | `integer\|between:1,5` |
| `rating_reading` | nullable | required | `integer\|between:1,5` |
| `rating_repertoar` | nullable | required | `integer\|between:1,5` |
| `catatan_perkembangan_musikal` | nullable | nullable | `string\|max:3000` |
| `catatan_karakter` | nullable | nullable | `string\|max:3000` |
| `kesimpulan_progress` | nullable | required | `in:PERLU_PENDAMPINGAN,CUKUP,BAIK,SANGAT_BAIK` |
| `progress_percent` | nullable | required | `integer\|between:0,100` |

### 5.2 Submit Logic

```php
// Request parameter: submit=1 → final, submit=0 / tidak ada → draft
$isDraft = !$request->boolean('submit');

$rules = [
    'catatan_perkembangan_musikal' => ['nullable', 'string', 'max:2000'],
    'catatan_karakter'             => ['nullable', 'string', 'max:2000'],
];

if (!$isDraft) {
    $rules['rating_teknik']     = ['required', 'integer', 'between:1,5'];
    $rules['rating_materi']     = ['required', 'integer', 'between:1,5'];
    $rules['rating_reading']    = ['required', 'integer', 'between:1,5'];
    $rules['rating_repertoar']  = ['required', 'integer', 'between:1,5'];
    $rules['kesimpulan_progress'] = ['required', 'in:PERLU_PENDAMPINGAN,CUKUP,BAIK,SANGAT_BAIK'];
    $rules['progress_percent']  = ['required', 'integer', 'between:0,100'];
}
```

### 5.3 Status Transisi

| Kondisi | Status `progress_reports.status` |
|---|---|
| Simpan draft | `DRAFT` |
| Submit final | `SUBMITTED` |
| Admin approve (jika ada flow) | `approved` (out of scope untuk task ini) |

---

## 6. DB Schema: Field Baru di `progress_reports`

```sql
-- Migration: add_new_fields_to_progress_reports
ALTER TABLE progress_reports
    ADD COLUMN rating_teknik             TINYINT UNSIGNED NULL,
    ADD COLUMN rating_materi             TINYINT UNSIGNED NULL,
    ADD COLUMN rating_reading            TINYINT UNSIGNED NULL,
    ADD COLUMN rating_repertoar          TINYINT UNSIGNED NULL,
    ADD COLUMN catatan_perkembangan_musikal TEXT NULL,
    ADD COLUMN catatan_karakter          TEXT NULL,
    ADD COLUMN kesimpulan_progress       ENUM('PERLU_PENDAMPINGAN','CUKUP','BAIK','SANGAT_BAIK') NULL,
    ADD COLUMN progress_percent          TINYINT UNSIGNED NULL;
```

**Kolom lama yang TIDAK diubah / tetap di DB:**
- `highlight` (text)
- `summary_notes` (text)
- `target_notes` (text)
- `repertoire` (text)
- Kolom-kolom checklist (boolean flags)

---

## 7. PDF Layout Spec

### 7.1 Struktur Halaman

```
┌─────────────────────────────────────────────┐
│  LOGO   |  LAPORAN PERKEMBANGAN BULANAN     │
│         |  MUSIK KITA                       │
├─────────────────────────────────────────────┤
│ Nama           : [nama murid]               │
│ Instrumen      : [instrumen]                │
│ Guru Pengajar  : [nama guru]                │
│ Bulan          : [bulan tahun]              │
│ Rating Hari Ini: ⭐⭐⭐⭐ (4.2) / —          │
├─────────────────────────────────────────────┤
│ KEHADIRAN DAN MATERI YANG DIPELAJARI        │
│ Minggu 1: [material_learned / —]            │
│ Minggu 2: [material_learned / —]            │
│ Minggu 3: [material_learned / —]            │
│ Minggu 4: [material_learned / —]            │
├─────────────────────────────────────────────┤
│ PERKEMBANGAN [NAMA] BULAN [BULAN]           │
│ Teknik Bermain : ⭐⭐⭐⭐⭐                   │
│ Materi         : ⭐⭐⭐                      │
│ Reading        : ⭐⭐⭐⭐                     │
│ Repertoar      : ⭐⭐⭐⭐⭐                   │
├─────────────────────────────────────────────┤
│ CATATAN PERKEMBANGAN MUSIKAL                │
│ [catatan_perkembangan_musikal]              │
│ CATATAN KARAKTER                            │
│ [catatan_karakter]                          │
├─────────────────────────────────────────────┤
│ KESIMPULAN PROGRESS                         │
│ [Perlu Pendampingan] [Cukup] [Baik✓] [Sangat Baik] │
├─────────────────────────────────────────────┤
│ 🎹 Piano  Level 2                           │
│ [████████░░]  80%                           │
├─────────────────────────────────────────────┤
│ TTD Guru          │  TTD Orang Tua          │
└─────────────────────────────────────────────┘
```

### 7.2 Progress Bar Rendering

- Bar kontinu (bukan 10 kotak terpisah)
- Lebar 100% dari container, filled sesuai `progress_percent`
- Label `"X%"` tampil di kanan bar
- Contoh: `progress_percent = 40` → bar 40% filled, label `"40%"`

---

## 8. Out of Scope

Item-item berikut **tidak** termasuk dalam task redesign ini:

| Item | Alasan |
|---|---|
| Migrasi data checklist lama ke field baru | Kolom lama dipertahankan untuk historis, tidak di-convert |
| Approval flow admin (approve/reject laporan) | Belum didesain |
| Notifikasi WhatsApp/email saat laporan dikirim | Fitur terpisah |
| Multi-page PDF (lebih dari 1 halaman) | Desain saat ini satu halaman |
| Edit laporan yang sudah `submitted` oleh guru | Perlu kebijakan bisnis; belum ditentukan |
| Session ke-5+ dalam satu bulan | Hanya Minggu 1–4 yang ditampilkan |
| Cetak batch multiple laporan | Feature request terpisah |
| `rating_teknik` dll untuk instrumen non-konvensional | Semua instrumen pakai rating yang sama |

---

## 9. Referensi

- Mockup asal: `memory/mockup.md`
- Plan dokumen: `docs/superpowers/plans/2026-06-06-progress-report-pdf-redesign.md`
- Tabel terkait: `progress_reports`, `progress_report_session_notes`, `students`, `enrollments`, `users`
- Config instrumen: `config/instruments.php` (field `emoji`)
