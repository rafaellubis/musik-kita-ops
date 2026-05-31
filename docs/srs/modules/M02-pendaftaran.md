# SRS M02 — Pendaftaran & Murid

**Induk:** [SRS-musik-kita-ops-2026-05-31.md](../SRS-musik-kita-ops-2026-05-31.md)

## 1. Tujuan

CRUD murid, state machine status, trial, skip trial, cuti, multi-kelas (enrollment), import Excel.

## 2. Controller & Route

| Aksi | Route name | Role |
|------|------------|------|
| CRUD murid | `students.*` (except index/show di write group) | Owner\|Admin write |
| Index/show murid | `students.index`, `students.show` | + Auditor |
| API guru by instrumen | `api.teachers-by-instrument` | + Auditor |
| start-trial | `students.start-trial` | Owner\|Admin |
| convert-active | `students.convert-active` | Owner\|Admin |
| skip-trial | `students.skip-trial` | Owner\|Admin |
| start-cuti | `students.start-cuti` | Owner\|Admin |
| withdraw | `students.withdraw` | Owner\|Admin |
| complete | `students.complete` | Owner\|Admin |
| return-from-cuti | `students.return-from-cuti` | Owner\|Admin |
| reactivate | `students.reactivate` | Owner\|Admin |
| Enrollment store | `students.enrollments.store` | Owner\|Admin |
| Set primary | `students.enrollments.set-primary` | Owner\|Admin |
| Stop enrollment | `students.enrollments.destroy` | Owner\|Admin |
| Import | `import.*` | **Owner** |

**Service utama:** `StudentLifecycleService`, `StudentImportService`

## 3. Schema

### students

`student_code` (M-YYYY-NNNN), `full_name`, `nickname` (unique), `gender` L|P, `birth_date`, kontak, `parent_*`, `status` (Title Case), `primary_enrollment_id`, `cuti_from`, `cuti_until`, `trial_date`, `active_since`, `last_session_at`

**Kolom dihapus (jangan dipakai):** `package_id`, `assigned_teacher_id`, `assigned_room_id`, `preferred_day`, `preferred_time`

### enrollments

`student_id`, `package_id`, `teacher_id`, `is_primary`, `effective_date`, `end_date`, `notes`, `status`

- `TRIAL` saat trial — tidak masuk generate SPP
- `ON_LEAVE` saat cuti — sesi tidak di-generate
- `is_primary` = tampilan UI; **bukan** filter SPP

### student_status_histories

Audit trail transisi; metadata `skipped_trial` untuk skip trial.

## 4. Business Rules

| BR | Aturan |
|----|--------|
| Trial | Gratis 1 sesi, **30 menit** semua tipe paket |
| Trial honor | HADIR = penuh; NO-SHOW = Rp 0 (`TRIAL_NS`) |
| Aktif valid | Registrasi + SPP bulan 1 lunas |
| Skip trial | Wajib `reason`: walk_in, migrasi, reaktivasi, lulus_kids |
| Cuti | Rp 100.000; enrollment → ON_LEAVE; max perpanjang 1x (2 bln total) |
| Multi-kelas | Banyak enrollment ACTIVE; satu `primary_enrollment_id` |
| Semua enrollment selesai | Murid auto mundur (lifecycle service) |
| Kids Class usia | 4 tahun s/d < 5 tahun |
| Kids kuota | Min 3 anak; calon menunggu jika < 3 |

## 5. Transisi Status (ringkas)

```
Calon → Trial | Aktif (skip)
Trial → Aktif | Mengundurkan Diri
Aktif → Cuti | Mundur | Selesai
Cuti → Aktif | perpanjang | Mundur
Selesai → Aktif (re-enroll tanpa registrasi ulang)
Mundur → Aktif (wajib registrasi Rp 250.000)
```

## 6. File Scope

```
app/Http/Controllers/StudentController.php
app/Http/Controllers/EnrollmentController.php
app/Http/Controllers/ImportController.php
app/Services/StudentLifecycleService.php
app/Services/StudentImportService.php
app/Http/Requests/*Student*
resources/views/students/
```

## 7. Acceptance Criteria

- [ ] Akses paket/guru via `primaryEnrollment` atau `enrollments`, bukan kolom students lama
- [ ] Skip trial wajib reason + history metadata
- [ ] Tambah kelas kedua membuat enrollment ACTIVE terpisah
- [ ] Import hanya Owner; tidak duplikat `student_code`
- [ ] Tests: `TrialEnrollmentTest`, `EnrollmentControllerTest`, `StudentCutiTest`, `ImportControllerTest`
