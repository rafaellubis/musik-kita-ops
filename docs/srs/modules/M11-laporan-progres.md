# SRS M11 — Laporan Progres Murid

**Induk:** [SRS-musik-kita-ops-2026-05-31.md](../SRS-musik-kita-ops-2026-05-31.md)

## 1. Tujuan

Template laporan (Owner), guru isi laporan per murid/periode, staff review & cetak PDF.

## 2. Controller & Route

### Template (Owner)

| Route | Fungsi |
|-------|--------|
| `report-templates.index`, `show` | + Admin, Auditor (read) |
| `report-templates` CRUD | Owner write |
| `report-templates.sections.store/destroy` | Owner |
| `report-templates.items.store/destroy` | Owner |

**Controller:** `ReportTemplateController`

### Laporan (Staff read)

| Route | Role |
|-------|------|
| `progress-reports.index` | Owner\|Admin\|Auditor |
| `progress-reports.show` | + |
| `progress-reports.pdf` | + |

**Controller:** `ProgressReportController`

### Isi laporan (Guru)

| Route | Role |
|-------|------|
| `guru.laporan.index`, `store` | Guru |
| `guru.laporan.edit`, `update` | Guru |

**Controller:** `GuruController` (method laporan*)

## 3. Schema

| Tabel | Peran |
|-------|--------|
| `report_templates` | Master template |
| `report_template_sections` | Bagian form |
| `report_template_items` | Pertanyaan/item per section |
| `progress_reports` | Instance laporan per murid |
| `progress_report_sections` | Jawaban per section |
| `progress_report_items` | Nilai per item |
| `progress_report_session_notes` | Snapshot catatan per sesi (sync dari `session_teacher_notes`) |

### Catatan per sesi (guru → laporan bulanan)

| Tabel | Peran |
|-------|--------|
| `session_teacher_notes` | Source of truth: catatan terstruktur per `ClassSession` (Materi, Tugas/Latihan, Catatan) |

Guru isi via `PATCH /guru/sesi/{classSession}/catatan` setelah absensi HADIR. Saat buka/simpan laporan DRAFT, `SessionNoteSyncService` sync ke `progress_report_session_notes` (read-only di form). Snapshot frozen saat SUBMITTED.

Spec: `docs/superpowers/specs/2026-06-06-catatan-per-sesi-guru-design.md`

Migrasi: `2026_05_29_081049_*` series.

## 4. Business Rules

- Template hanya Owner yang ubah struktur (sections/items)
- Guru hanya edit laporan yang dibuat/ditugaskan kepadanya
- PDF untuk arsip / orang tua — route sebelum wildcard `{progressReport}`

## 5. File Scope

```
app/Http/Controllers/ReportTemplateController.php
app/Http/Controllers/ProgressReportController.php
app/Http/Controllers/GuruController.php (laporan)
app/Models/ReportTemplate*.php
app/Models/ProgressReport*.php
resources/views/report-templates/
resources/views/progress-reports/
resources/views/guru/laporan*
```

## 6. Acceptance Criteria

- [ ] Admin tidak POST report-templates
- [ ] PDF route tidak tertangkap show
- [ ] Tests: `ReportTemplateTest`, `ProgressReportGuruTest`, `ProgressReportAdminTest`
