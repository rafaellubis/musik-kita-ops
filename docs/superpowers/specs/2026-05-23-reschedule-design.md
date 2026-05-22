# Spesifikasi: Reschedule Lengkap (Fase 2)

**Tanggal:** 2026-05-23
**Status:** Disetujui — siap implementasi

---

## Latar Belakang

Di Fase 1, admin bisa tandai sesi sebagai `IZIN_RESCHEDULE` di halaman absensi, tapi sesi pengganti harus dibuat manual di luar sistem. Di Fase 2, admin langsung input tanggal + jam + ruangan pengganti dari form absensi yang sama — sistem yang buat `ClassSession` baru secara otomatis.

---

## Business Rules yang Diterapkan

```
BR-4.4 : Validasi ≥5 jam + izin pertama bulan = manual admin (tidak diotomasi).
          Admin yang memutuskan apakah layak reschedule atau HANGUS.
BR-4.5 : Sesi pengganti bisa di bulan berjalan atau bulan depan.
          Admin bebas pilih tanggal — tidak dibatasi bulan.
BR-4.6 : Izin ke-2+ = IZIN_VIDEO (bukan reschedule). Di luar scope fitur ini.
```

---

## Data Model

### Tidak ada kolom baru di database

`ClassSession` sudah punya semua field yang diperlukan:
- `schedule_id` nullable → null untuk sesi ad-hoc / pengganti
- `notes` → referensi ke sesi asli
- semua FK (student_id, enrollment_id, teacher_id, room_id)

### Sesi pengganti (ClassSession baru)

```
schedule_id       = null
student_id        = original.student_id
enrollment_id     = original.enrollment_id
teacher_id        = original.teacher_id
session_date      = tanggal input admin
start_time        = jam input admin
end_time          = start_time + package.duration_min
room_id           = ruangan input admin (nullable)
status            = SCHEDULED
honor_code        = null (dihitung saat absensi nanti)
honor_amount      = null
notes             = "Sesi pengganti dari {original.session_date}"
```

### Sesi asli

```
status  = IZIN_RESCHEDULE (tidak berubah)
honor   = 0 (sudah ditangani AttendanceService)
notes   = diupdate: "Sesi pengganti: {replacement_date} {replacement_time}"
```

---

## Alur Lengkap

```
1. Admin buka halaman Absensi
2. Admin pilih status IZIN_RESCHEDULE di dropdown sesi
   → Alpine.js tampilkan 3 field tambahan:
      - Tanggal Pengganti (date)
      - Jam Mulai (time)
      - Ruangan (select, nullable)
3. Admin isi form → Submit (PATCH /absensi/{classSession})
4. UpdateAbsensiRequest validasi semua field
5. AbsensiController update status sesi asli
6. AbsensiController panggil RescheduleService::createReplacement()
7. RescheduleService:
   a. Cek konflik guru → block jika overlap
   b. Cek konflik ruangan → block jika overlap (skip jika ruangan kosong)
   c. Buat ClassSession baru (status=SCHEDULED)
   d. Update notes sesi asli
8. Flash success: "Sesi pengganti berhasil dijadwalkan: {tanggal} {jam}"
```

---

## Cek Konflik (Hard Block)

Sesi dianggap overlap jika:
```
session_date = replacement_date
AND start_time < replacement_end_time
AND end_time > replacement_start_time
```

Cek dilakukan terpisah untuk:
- **Guru:** cari ClassSession lain dengan teacher_id yang sama, waktu overlap, status bukan CANCELLED
- **Ruangan:** cari ClassSession lain dengan room_id yang sama, waktu overlap, status bukan CANCELLED

Error message:
- Guru bentrok: `"Guru {nama} sudah ada sesi lain pada {tanggal} {jam}–{jam_selesai}"`
- Ruangan bentrok: `"Ruangan {kode} sudah dipakai pada {tanggal} {jam}–{jam_selesai}"`

---

## Perubahan File

### Baru
```
app/Services/RescheduleService.php
tests/Feature/Admin/RescheduleTest.php
```

### Dimodifikasi
```
app/Http/Requests/UpdateAbsensiRequest.php
  — tambah rules replacement_date/time/room_id (required_if IZIN_RESCHEDULE)

app/Http/Controllers/AbsensiController.php
  — inject RescheduleService, panggil createReplacement() saat IZIN_RESCHEDULE

resources/views/absensi/index.blade.php
  — Alpine.js: tambah 3 field conditional (date, time, room select)
  — Tambah dropdown ruangan aktif ke data controller
```

---

## RescheduleService Interface

```php
class RescheduleService
{
    /**
     * Buat sesi pengganti untuk sesi yang di-reschedule.
     * Lempar InvalidArgumentException jika ada konflik guru/ruangan.
     *
     * @throws InvalidArgumentException
     */
    public function createReplacement(
        ClassSession $original,
        string $date,         // format Y-m-d
        string $startTime,    // format H:i
        ?int $roomId
    ): ClassSession;
}
```

---

## Honor

- Sesi asli `IZIN_RESCHEDULE` → honor = 0 (sudah berjalan di AttendanceService)
- Sesi pengganti → honor dihitung normal saat admin input absensi (H_REG)
- Tidak ada perubahan di AttendanceService / HonorCalculationService

---

## Test Cases

```
RescheduleTest:
1. Happy path — buat sesi pengganti berhasil
2. Konflik guru → return 422 dengan pesan guru bentrok
3. Konflik ruangan → return 422 dengan pesan ruangan bentrok
4. Ruangan kosong (null) — tidak cek konflik ruangan, berhasil
5. Tanggal bulan depan (rapel) — berhasil
6. Replacement_date wajib saat IZIN_RESCHEDULE — validasi 422
7. Sesi pengganti: schedule_id = null, status = SCHEDULED
8. Notes sesi asli terupdate dengan referensi replacement
```

---

## Yang Tidak Berubah

- `AttendanceService` — tidak ada perubahan
- `HonorCalculationService` — tidak ada perubahan
- Alur absensi untuk status lain (HADIR, HANGUS, dll.) — tidak berubah
- Validasi IZIN_VIDEO (izin ke-2+) — masih manual, di luar scope
