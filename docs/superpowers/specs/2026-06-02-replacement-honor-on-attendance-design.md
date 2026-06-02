# Design: Honor Sesi Pengganti Kalender — Isi Saat Absensi

**Tanggal:** 2026-06-02  
**Status:** Approved (user request inline)

## Masalah

`SessionGeneratorService` mengisi `honor_code` + `honor_amount` (`H_REG` / `H_DUO`) saat membuat sesi pengganti dari `replacement_date` kalender akademik, meski status masih `SCHEDULED`. UI daftar sesi menampilkan honor seolah sudah final.

`RescheduleService::createReplacement()` (izin reschedule manual) **tidak** pre-fill honor — diisi oleh `AttendanceService` saat absensi.

## Keputusan

Sesi pengganti kalender akademik mengikuti pola reschedule manual:

| Tahap | honor_code | honor_amount |
|-------|------------|--------------|
| Generator (status SCHEDULED) | `null` | `null` |
| Setelah absensi (`AttendanceService`) | dihitung per status | dihitung per status |

**Tidak berubah:**

- Sesi `LIBUR` tanpa replacement → tetap `H_LIBUR` penuh saat generate (BR-4.10)
- Sesi `LIBUR` dengan replacement → tetap honor Rp 0 (honor via sesi pengganti setelah absen)
- Split reschedule (`H_SPLIT`) → tetap pre-fill di `RescheduleService` (skema berbeda)
- `HonorCalculationService` tetap exclude `SCHEDULED`

## Backfill

Migration one-shot: null-kan honor pada sesi pengganti kalender yang masih `SCHEDULED` dan belum di-absen (identifikasi: `origin_session_id` + notes generator).

## Testing

- Test baru: replacement dari libur → `honor_code`/`honor_amount` null
- Test existing libur honor logic tetap pass
