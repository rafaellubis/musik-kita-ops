# Design: Auto-Pilih Template Laporan Progres dari Enrollment

**Tanggal:** 2026-05-31  
**Modul:** M11 Laporan Progres  
**Status:** Disetujui — diimplementasikan 2026-05-31

---

## 1. Masalah

Guru saat buat laporan harus pilih **Kelas** dan **Template** secara manual. Template yang benar sudah ditentukan oleh `enrollment.package` (instrumen + tipe paket), sehingga dropdown template rawan salah pilih (mis. Piano Hobby untuk murid Reguler).

## 2. Tujuan

- Template laporan **otomatis** dipilih dari enrollment yang dipilih guru.
- Resolusi template di **server** (tidak percaya input client sembarangan).
- UI guru lebih sederhana: pilih murid + periode saja.
- Mapping selaras ke seeder existing (`ReportTemplateSeeder`).

## 3. Aturan Mapping Paket → Template

| `package.class_type` | Template yang dipakai |
|---|---|
| `REGULER` | `{Instrumen} · Reguler` |
| `DUO` | `{Instrumen} · Reguler` *(DUO = Reguler Basic, seksi Berduo di form/PDF)* |
| `HOBBY` | `{Instrumen} · Hobby` |
| `KIDS_CLASS` / `KIDS_CLASS_BUNDLE` | `Kids Class · Eksplorasi Bakat` |
| Lain / tidak ada template | Error Bahasa Indonesia ke guru |

**Catatan:** Grade Reguler (Basic–L4) **tidak** membedakan template — guru centang item sesuai level (sudah didesain di seeder).

**Saxophone:** hanya paket `HOBBY` (`SAX_HOBBY_30/45`) — template `Saxophone · Hobby`. Tidak ada Reguler sax; `DUO_SAX` (nonaktif) nanti ikut Reguler jika paket Reguler sax ditambahkan.

**Lookup key:** `instrument_id` + `template_kind` (kolom baru, lihat §4).

## 4. Arsitektur (Rekomendasi)

### Opsi A — Match by name string saja
- Tanpa migrasi.
- Rapuh jika Owner rename template.

### Opsi B — Kolom `template_kind` di `report_templates` ✅ **Dipilih**
- Enum: `REGULER` | `HOBBY` | `KIDS`
- DUO tidak punya kind sendiri → resolver map ke `REGULER`
- Unique per `(instrument_id, template_kind)` untuk template aktif
- Seeder di-update set `template_kind`

### Opsi C — FK `package_id` per template
- Terlalu granular (30+ paket Reguler) — ditolak.

### Service

`App\Services\ReportTemplateResolverService`

```php
public function resolveForEnrollment(Enrollment $enrollment): ?ReportTemplate
public function templateKindForPackage(Package $package): ?string // REGULER|HOBBY|KIDS
```

Dipanggil dari `GuruController::laporanStore()`.

## 5. Perubahan UI (Portal Guru)

**Form buat laporan** (`guru/laporan.blade.php`):

- Hapus dropdown `report_template_id`.
- Tambah preview read-only (Alpine): saat pilih enrollment, tampilkan nama template otomatis.
- Data mapping: controller kirim `$enrollmentTemplateMap` JSON `{ enrollmentId: { name, ok, error? } }`.

**Form isi laporan** (`guru/laporan-form.blade.php`):

- Sembunyikan seksi checklist **Belajar Berduo** jika enrollment **bukan** DUO (konsisten dengan PDF).

## 6. Perubahan Backend

| File | Perubahan |
|---|---|
| Migration | `add_template_kind_to_report_templates_table` |
| `ReportTemplate` model | fillable + constant kinds |
| `ReportTemplateSeeder` | set `template_kind` per template |
| `ReportTemplateResolverService` | logic mapping |
| `GuruController::laporan` | build map untuk Alpine |
| `GuruController::laporanStore` | resolve template server-side; `report_template_id` dihapus dari validasi input |
| `ProgressReportGuruTest` | update + test auto-resolve, DUO→Reguler, missing template |

## 7. Error Handling

Jika template tidak ditemukan (mis. Saxophone belum ada template):

```
Template laporan untuk paket {code} belum tersedia. Hubungi Owner.
```

Redirect back dengan flash `error`.

## 8. Acceptance Criteria

- [x] Guru buat laporan **tanpa** pilih template manual
- [x] `DUO_PIANO_30` → template `Piano · Reguler`
- [x] `PIANO_HOBBY_45` → template `Piano · Hobby`
- [x] `KIDS_GRUP_MONTHLY` → template `Kids Class · Eksplorasi Bakat`
- [x] Seksi Berduo hanya tampil di form edit untuk enrollment DUO
- [x] Test feature pass
- [x] Seeder idempotent dengan `template_kind`

## 9. Out of Scope

- Auto-reminder laporan belum diisi akhir bulan
- Pre-fill catatan sesi dari absensi (M04)
- Validasi wajib isi highlight sebelum submit

---

## 10. Implementasi

Setelah spec disetujui → `writing-plans` skill → `executing-plans` / implement langsung.
