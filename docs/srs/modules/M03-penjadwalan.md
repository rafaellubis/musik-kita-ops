# SRS M03 — Penjadwalan

**Induk:** [SRS-musik-kita-ops-2026-05-31.md](../SRS-musik-kita-ops-2026-05-31.md)

## 1. Tujuan

Jadwal mingguan per enrollment, generate sesi bulanan, edit/hapus sesi, kalender visual, libur & sesi pengganti.

## 2. Controller & Route

| Aksi | Route | Role |
|------|-------|------|
| Store schedule | `schedules.store` | Owner\|Admin |
| Update/delete/toggle schedule | `schedules.update`, `destroy`, `toggle-active` | Owner\|Admin |
| Generate sesi | `sessions.generate` | Owner\|Admin |
| Update sesi | `sessions.update` | Owner\|Admin |
| Delete sesi | `sessions.destroy` | Owner\|Admin |
| List sesi | `sessions.index` | + Auditor |
| Kalender | `kalender.index` | + Auditor |

**Services:** `SessionGeneratorService`, `ScheduleConflictDetector`

**Cron:** `sessions:generate-month` — tgl 25, 06:00

**Manual:** `php artisan sessions:generate {year} {month}` (cek signature di command)

## 3. Schema

### schedules

`enrollment_id`, `day_of_week` (0=Minggu..6=Sabtu), `start_time`, `end_time`, `room_id`, `is_active`

### class_sessions

`schedule_id`, `enrollment_id`, `student_id`, `teacher_id`, `session_date`, `status`, `substitute_teacher_id`, `late_minutes`, `notes`, `honor_code`, `honor_amount`, `session_sequence`, `split_part` (reschedule split)

## 4. Business Rules

| BR | Aturan |
|----|--------|
| Generate | Dari jadwal mingguan aktif; skip enrollment ON_LEAVE/TRIAL |
| Min/max sesi | 3–4 sesi per murid per bulan (counter) |
| Minggu ke-5 | Tidak reguler kecuali replacement |
| Libur tanpa replacement | Status LIBUR saja |
| Libur + replacement_date | LIBUR + sesi pengganti di tanggal itu |
| Bayar penuh | Meski hanya 3 sesi di bulan |
| Pindah jadwal mingguan | Boleh dalam bulan yang sama |
| Konflik | 1 guru / 1 ruang tidak double-book (mingguan & sesi konkret) |
| Internal holiday | Honor Rp 0, tanpa replacement |

Generator set `honor_code` + `honor_amount` untuk sesi **LIBUR** sesuai `is_honor_paid`. Sesi pengganti kalender (`replacement_date`) honor **null** sampai absensi — sama seperti `RescheduleService`.

## 5. File Scope

```
app/Http/Controllers/ScheduleController.php
app/Http/Controllers/SessionController.php
app/Http/Controllers/KalenderController.php
app/Http/Controllers/HolidayController.php
app/Services/SessionGeneratorService.php
app/Services/ScheduleConflictDetector.php
app/Console/Commands/GenerateMonthlySessions.php
resources/views/sessions/
resources/views/kalender/
```

## 6. Acceptance Criteria

- [ ] Generate idempotent (tidak duplikat sesi sama)
- [ ] Enrollment ON_LEAVE tidak dapat sesi baru
- [ ] Edit sesi cek konflik guru/ruang
- [ ] `session_date` diparse dengan Carbon, bukan property date cast
- [ ] Tests: `SessionGeneratorServiceTest`, `SessionGeneratorConflictTest`, `KalenderControllerTest`
