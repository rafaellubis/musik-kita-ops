# Import Murid dari Excel — Design Spec

> **For agentic workers:** Gunakan `superpowers:writing-plans` untuk membuat implementation plan dari spec ini.

**Goal:** Fitur import massal data murid dari file Excel (.xlsx) ke database sistem Musik KITA, untuk keperluan migrasi data dari sistem lama (300+ murid).

**Architecture:** Upload file → dry run validasi server → preview hasil → konfirmasi → simpan. Satu halaman, state antar step disimpan di session Laravel.

**Tech Stack:** Laravel 11, PHP 8.3, `maatwebsite/excel` (Laravel Excel v3), Blade + Alpine.js + Tailwind CSS, MySQL.

---

## Fitur Utama

1. **Download template** — file `.xlsx` siap pakai dengan 2 sheet (Data + Referensi Kode)
2. **Upload & validasi (dry run)** — server parse dan validasi tanpa menyimpan ke DB
3. **Preview hasil** — tampilkan baris valid / overwrite / error dengan alasan
4. **Konfirmasi import** — simpan hanya baris valid dan overwrite, skip error
5. **Laporan hasil** — flash message ringkasan setelah selesai

---

## File yang Dibuat / Dimodifikasi

| File | Status |
|------|--------|
| `composer.json` | Modify — tambah `maatwebsite/excel` |
| `app/Http/Controllers/ImportController.php` | Create |
| `app/Services/StudentImportService.php` | Create |
| `app/Imports/StudentsImport.php` | Create |
| `app/Exports/StudentTemplateExport.php` | Create |
| `resources/views/imports/index.blade.php` | Create |
| `routes/web.php` | Modify — tambah 3 route import |

---

## Routes

```php
// Hanya Owner dan Admin
Route::middleware(['auth', 'role:Owner|Admin'])->group(function () {
    Route::get('/import',          [ImportController::class, 'index'])->name('import.index');
    Route::get('/import/template', [ImportController::class, 'downloadTemplate'])->name('import.template');
    Route::post('/import/validate',[ImportController::class, 'validate'])->name('import.validate');
    Route::post('/import/confirm', [ImportController::class, 'confirm'])->name('import.confirm');
});
```

---

## Template Excel — 2 Sheet

### Sheet 1: "Data Murid" (18 kolom)

| Kolom | Wajib | Format / Validasi |
|-------|-------|-------------------|
| full_name | ✅ | String, max 100 karakter |
| nickname | — | String, max 30 karakter |
| gender | ✅ | `L` atau `P` |
| birth_date | — | `YYYY-MM-DD` |
| phone | — | String, max 20 karakter |
| email | — | Format email |
| address | — | Text bebas |
| notes | — | Text bebas |
| parent_name | — | String, max 100 karakter |
| parent_phone | — | String, max 20 karakter |
| parent_email | — | Format email |
| parent_relationship | — | `Ayah` / `Ibu` / `Wali` |
| status | ✅ | `Calon` / `Trial` / `Aktif` / `Cuti` / `Selesai` / `Mengundurkan Diri` |
| package_code | — | Kode paket dari tabel `packages` |
| teacher_code | — | Kode guru dari tabel `teachers` |
| preferred_day | — | `Senin` / `Selasa` / `Rabu` / `Kamis` / `Jumat` / `Sabtu` / `Minggu` |
| preferred_time | — | `HH:MM` (contoh: `15:30`) |
| active_since | — | `YYYY-MM-DD` |

Row pertama adalah header. Baris data mulai dari row 2. Baris kosong dilewati.

### Sheet 2: "Referensi Kode" (read-only, auto-generate)

Berisi daftar kode yang valid saat template di-generate:
- Daftar semua `package_code` + nama paket + class_type
- Daftar semua `teacher_code` + nama guru
- Daftar nilai `status` yang valid
- Daftar nilai `preferred_day` yang valid
- Daftar nilai `parent_relationship` yang valid

---

## Validasi Per Baris (Dry Run)

### Kategori hasil per baris:

| Kategori | Warna | Kondisi |
|----------|-------|---------|
| ✅ Valid | Hijau | Semua field valid, murid belum ada di DB |
| ⚠️ Overwrite | Kuning | Semua field valid, murid sudah ada (match by `full_name` + `phone`) |
| ❌ Error | Merah | Ada field yang tidak valid — baris dilewati saat confirm |

### Rules validasi:

```php
// Wajib
'full_name'          => required, max:100
'gender'             => required, in:L,P
'status'             => required, in:Calon,Trial,Aktif,Cuti,Selesai,Mengundurkan Diri

// Opsional dengan format
'birth_date'         => nullable, date_format:Y-m-d
'email'              => nullable, email
'parent_email'       => nullable, email
'parent_relationship'=> nullable, in:Ayah,Ibu,Wali
'preferred_day'      => nullable, in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu
'preferred_time'     => nullable, date_format:H:i
'active_since'       => nullable, date_format:Y-m-d

// Foreign key (jika diisi, harus exist dan is_active = true)
'package_code'       => nullable, exists:packages,code + is_active = true
'teacher_code'       => nullable, exists:teachers,code + is_active = true
```

### Deteksi duplikat (overwrite):

Match dilakukan berdasarkan **`full_name` + `phone`** (keduanya sama = murid sama). Jika ditemukan, data lama di-overwrite dengan data dari Excel.

---

## Flow UI (Satu Halaman)

```
┌─────────────────────────────────────────────┐
│  STEP 1: Upload                              │
│  ┌─────────────────────────────────────┐    │
│  │  [Download Template .xlsx]           │    │
│  │                                      │    │
│  │  Drop file di sini atau klik upload  │    │
│  │  (hanya .xlsx, max 5MB)              │    │
│  └─────────────────────────────────────┘    │
│  [Tombol: Validasi File]                     │
└─────────────────────────────────────────────┘

         ↓ POST /import/validate (form submit)

┌─────────────────────────────────────────────┐
│  STEP 2: Preview Hasil Validasi              │
│  Ringkasan: 280 valid · 15 overwrite · 8 error│
│                                              │
│  ┌───┬──────────────┬───────┬───────┬──────┐│
│  │ # │ Nama         │Status │Paket  │Ket.  ││
│  │ 1 │ Budi S.      │Aktif  │✅     │      ││
│  │ 2 │ Sari W. (⚠️) │Aktif  │✅     │overwrite││
│  │ 3 │ Rudi P. (❌) │Aktif  │❌     │kode paket tidak ditemukan││
│  └───┴──────────────┴───────┴───────┴──────┘│
│  (pagination per 50 baris)                   │
│                                              │
│  [Batal]        [Konfirmasi Import (295 data)]│
└─────────────────────────────────────────────┘

         ↓ POST /import/confirm

Flash: "295 murid berhasil diimport. 8 baris dilewati."
Redirect → /students
```

### State management:

Hasil dry run (array baris valid + error) disimpan di `session('import_preview')`. Saat confirm, server membaca dari session — tidak perlu upload file kedua kali. Session di-clear setelah confirm atau batal.

---

## StudentImportService — Method Utama

```php
class StudentImportService
{
    // Parse file Excel, validasi tiap baris, return hasil dry run
    public function validate(UploadedFile $file): array
    // Return: ['valid' => [...], 'overwrite' => [...], 'errors' => [...]]

    // Simpan baris valid + overwrite ke database
    public function confirm(array $validRows, array $overwriteRows): array
    // Return: ['imported' => int, 'skipped' => int]

    // Validasi satu baris Excel
    private function validateRow(int $rowNum, array $row): array|string
    // Return: array data jika valid, string pesan error jika tidak

    // Deteksi apakah murid sudah ada di DB
    private function findExisting(string $fullName, ?string $phone): ?Student
}
```

---

## ImportController — Endpoint

```php
class ImportController extends Controller
{
    // GET /import — tampilkan form upload + link download template
    public function index(): View

    // GET /import/template — generate dan download file .xlsx template
    public function downloadTemplate(): BinaryFileResponse

    // POST /import/validate — dry run, simpan hasil ke session, redirect ke index
    public function validate(Request $request): RedirectResponse

    // POST /import/confirm — simpan dari session ke DB, redirect ke students
    public function confirm(Request $request): RedirectResponse
}
```

---

## StudentTemplateExport — Generate Template

Menggunakan `maatwebsite/excel`. Menghasilkan file `.xlsx` dengan:
- Sheet 1 "Data Murid": header 18 kolom + 1 baris contoh (auto-fill dengan data dummy)
- Sheet 2 "Referensi Kode": di-query live dari DB saat download

```php
class StudentTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new DataMuridSheet(),       // header + 1 baris contoh
            new ReferensiKodeSheet(),   // query DB live
        ];
    }
}
```

---

## Error Handling

| Kondisi | Handling |
|---------|---------|
| File bukan `.xlsx` | Validasi Laravel request, error flash |
| File > 5MB | Validasi Laravel request, error flash |
| Semua baris error | Tampilkan preview, tombol konfirmasi disabled |
| Session import_preview expired | Redirect ke form upload dengan pesan "Sesi validasi kadaluarsa, upload ulang." |
| DB error saat confirm | Rollback transaction, error flash, log error |

---

## Audit Log

Setiap import yang berhasil dicatat di `audit_logs`:

```php
AuditLog::record(
    action: AuditLog::ACTION_CREATE,
    entity: null,
    entityLabel: 'Import Murid Excel',
    newValues: ['imported' => 295, 'skipped' => 8],
    notes: 'Import massal dari file Excel oleh ' . auth()->user()->email,
);
```

---

## Tidak Diimport (Out of Scope)

- Enrollment — dibuat manual setelah import
- Jadwal mingguan — dibuat manual setelah import
- Data historis sesi, pembayaran, honor
- `student_code` — di-generate otomatis sistem (format M-YYYY-NNNN)
- `assigned_room_id` — ditentukan saat buat jadwal
- `trial_date` — tidak relevan untuk import massal
- `last_session_at` — auto-tracked sistem

---

*Spec dibuat: 2026-05-16*
