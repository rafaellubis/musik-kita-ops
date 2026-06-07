# Design: Sesi Manual Enrollment (Attribution Month/Year)

**Tanggal:** 2026-06-07  
**Modul:** M03 Penjadwalan + M08 Laporan Progres  
**Status:** Disetujui

---

## 1. Masalah

Murid yang daftar pertengahan bulan hanya mendapat 2 sesi dari generator (karena `effective_date` + max 4 slot). Praktik lapangan: admin menambah 1–2 sesi secara manual — di bulan yang sama atau dirapel ke bulan depan. Sistem tidak punya fitur buat sesi baru (reschedule hanya 1:1 swap izin).

## 2. Tujuan

- Admin bisa **tambah sesi manual** per enrollment ACTIVE
- Sesi rapel cross-month tetap **atribusi ke bulan pertama** (progress report Jan tetap 4 kotak)
- Panel info slot 1–4 per bulan atribusi (read-only, tanpa engine defisit otomatis)
- Laporan Sesi WA tidak berubah (pakai `session_date` aktual)

## 3. Keputusan Desain

| Aspek | Keputusan |
|-------|-----------|
| Pembuatan | Full manual admin |
| Atribusi rapel | `attribution_month/year` terpisah dari `session_date` |
| `session_type` | `REGULAR` (generator) / `MANUAL` (admin) |
| Sequence | 1–4 per periode atribusi; auto-suggest slot kosong |
| Konflik | Guru + ruang (sama seperti Reschedule; skip Kids Class) |
| Progress report sync | Filter `attribution_*`, bukan `session_date` |
| Guard catatan guru | Cek laporan SUBMITTED by `attribution_*` |
| Tagihan / honor WA | Tidak berubah |

## 4. Data Model

```sql
ALTER TABLE class_sessions ADD:
  attribution_month  TINYINT UNSIGNED NULL
  attribution_year   SMALLINT UNSIGNED NULL
  session_type       ENUM('REGULAR','MANUAL') NOT NULL DEFAULT 'REGULAR'
```

Backfill existing rows: `attribution_*` = dari `session_date`, `session_type` = `REGULAR`.

## 5. API

`POST /students/{student}/enrollments/{enrollment}/manual-sessions`

Body: `session_date`, `start_time`, `room_id` (nullable), `attribution_year`, `attribution_month`, `session_sequence` (optional)

## 6. Edge Cases

| Skenario | Behavior |
|----------|----------|
| Sequence duplikat di periode atribusi | 422 validation error |
| Semua slot 1–4 terisi | Tombol disabled / error |
| Kids Class | Skip conflict check |
| Sesi manual Feb, attr Jan | Sync ke laporan Jan; WA tanggal 7 Feb |
| Laporan Jan SUBMITTED | Catatan sesi manual attr=Jan diblokir |

## 7. Out of Scope

- Auto-defisit engine / banner alarm
- Proration tagihan
- Perubahan RescheduleService
- Perubahan SessionReportWaService
