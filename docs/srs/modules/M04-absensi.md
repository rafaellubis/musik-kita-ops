# SRS M04 — Absensi

**Induk:** [SRS-musik-kita-ops-2026-05-31.md](../SRS-musik-kita-ops-2026-05-31.md)

## 1. Tujuan

Input status kehadiran per sesi, hitung honor, reschedule (termasuk split), open slot untuk `IZIN_PENDING`, input guru terbatas.

## 2. Controller & Route

| Aksi | Route | Role |
|------|-------|------|
| Index absensi | `absensi.index` | Owner\|Admin\|Auditor |
| Update status | `absensi.update` | Owner\|Admin |
| Split reschedule part 1/2 | `absensi.split` | Owner\|Admin |
| Open slot board | `absensi.open-slots` | Owner\|Admin |
| Assign open slot | `absensi.open-slots.assign` | Owner\|Admin |
| Schedule replacement | `absensi.open-slots.schedule` | Owner\|Admin |
| Guru update absensi | `guru.absensi.update` | Guru |
| Guru sesi pending | `guru.sesi-pending.*` | Guru |

**Services:** `AttendanceService`, `RescheduleService`

## 3. Status Sesi (10 nilai)

| Status | Keterangan singkat |
|--------|-------------------|
| SCHEDULED | Belum diabsen |
| HADIR | Hadir |
| HADIR_TERLAMBAT | Hadir terlambat |
| IZIN_RESCHEDULE | Izin + ada/jadwal pengganti |
| IZIN_PENDING | Izin, menunggu slot/tanggal pengganti |
| IZIN_VIDEO | Video pengganti |
| HANGUS | Hangus / no-show |
| LIBUR | Libur (nasional/internal) |
| DIGANTI | Sesi pengganti |
| CANCELLED | Dibatalkan |

## 4. Honor Codes (set oleh AttendanceService / generator)

`H_REG`, `H_TRIAL`, `TRIAL_NS`, `H_VIDEO`, `H_LIBUR`, `H_HANGUS`, `H_PENG`, `H_KIDS`, `H_UJIAN`, `H_IZIN`, `H_SPLIT`

| Kode | Skenario |
|------|----------|
| H_REG | Hadir/telat |
| H_TRIAL | Trial hadir |
| TRIAL_NS | Trial no-show → Rp 0 |
| H_PENG | Honor ke `substitute_teacher_id` |
| H_IZIN | Sesi original izin reschedule → Rp 0 (honor di sesi pengganti) |
| H_SPLIT | Split reschedule → setengah formula |

## 5. Business Rules Absensi

| BR | Aturan |
|----|--------|
| Izin reschedule | Info ≥5 jam + izin **pertama** di bulan |
| Izin ke-2+ | Video pengganti |
| Info <5 jam / tanpa info | HANGUS |
| Guru pengganti | Honor ke pengganti |
| Libur nasional | Honor penuh (kecuali Internal `is_honor_paid=false`) |
| Reschedule | `RescheduleService` — cek konflik guru + ruang |
| Open slot | Admin assign / jadwalkan dari board `IZIN_PENDING` |

## 6. File Scope

```
app/Http/Controllers/AbsensiController.php
app/Http/Controllers/GuruController.php (absensi + sesi-pending)
app/Services/AttendanceService.php
app/Services/RescheduleService.php
resources/views/absensi/
resources/views/guru/
```

## 7. Acceptance Criteria

- [ ] Update absensi recalc `honor_code` / `honor_amount`
- [ ] Split hanya part 1 atau 2 (`where('part', '[12]')`)
- [ ] Route `open-slots` terdaftar sebelum `absensi/{classSession}`
- [ ] Guru hanya bisa ubah sesi miliknya
- [ ] Tests: `AbsensiControllerTest`, `RescheduleTest`, `SplitRescheduleTest`, `IzinPendingTest`, `GuruUpdateAbsensiTest`
