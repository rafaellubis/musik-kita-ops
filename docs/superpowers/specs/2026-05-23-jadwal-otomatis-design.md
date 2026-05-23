# Spec: Jadwal Otomatis dengan Kalender Akademik
**Tanggal:** 2026-05-23
**Modul:** M03 — Penjadwalan
**Status:** Approved, siap implementasi

---

## 1. Latar Belakang

`SessionGeneratorService` saat ini menggunakan logika sederhana: selalu skip minggu ke-5,
dan menandai semua hari libur sebagai sesi LIBUR tanpa mempertimbangkan tanggal pengganti.

Fitur ini menambahkan:
1. Dukungan **kalender akademik tahunan** (owner input di awal tahun)
2. **Tanggal pengganti** per hari libur (auto-suggest minggu ke-5, owner bisa override)
3. **Honor rules** yang benar untuk sesi LIBUR dan IZIN_RESCHEDULE
4. **Tracking guru pendamping** Konser KITA di M08

---

## 2. Database Changes

### 2.1 Tabel `holidays` — tambah 2 kolom

```sql
replacement_date  DATE NULL
-- NULL  = tidak ada penggantian sesi
-- Diisi = tanggal sesi pengganti (wajib dalam bulan yang sama)
-- UNIQUE constraint nullable: tidak boleh dua holiday berbagi tanggal pengganti yang sama
-- DISABLED di UI jika type = 'Internal'

is_honor_paid  BOOLEAN NOT NULL DEFAULT TRUE
-- TRUE  = guru tetap dibayar honor saat libur (default, untuk Nasional/Cuti Bersama)
-- FALSE = honor Rp 0 saat libur (untuk Internal/Konser KITA — guru dapat honor via slip manual)
```

**Model `Holiday`:**
- Tambah `replacement_date` dan `is_honor_paid` ke `$fillable`
- Tambah cast: `'replacement_date' => 'date'`

### 2.2 Tabel `event_participants` — tambah 1 kolom

```sql
accompanying_teacher_id  BIGINT NULL FK → teachers.id ON DELETE SET NULL
-- NULL  = tidak ada guru pendamping / guru tidak bisa hadir
-- Diisi = guru yang mendampingi murid ini di event (Konser KITA)
```

**Model `EventParticipant`:**
- Tambah `accompanying_teacher_id` ke `$fillable`
- Tambah relationship: `accompanyingTeacher()` → `BelongsTo(Teacher)`

---

## 3. Session Generation Rules

### 3.1 Rules Lengkap

| Rule | Kondisi | Hasil Sesi |
|------|---------|------------|
| R3 | 5 occurrence, 0 libur | 4 SCHEDULED (week 5 skip) |
| R4 | 5 occurrence, 1 libur **dengan** `replacement_date` | 3 SCHEDULED + 1 LIBUR + 1 SCHEDULED(pengganti) |
| R4b | 5 occurrence, 1 libur **tanpa** `replacement_date` | 3 SCHEDULED + 1 LIBUR (week 5 tetap skip) |
| R5 | 4 occurrence, 0 libur | 4 SCHEDULED |
| R6 | 4 occurrence, 1 libur **dengan** `replacement_date` | 3 SCHEDULED + 1 LIBUR + 1 SCHEDULED(pengganti) |
| R6b | 4 occurrence, 1 libur **tanpa** `replacement_date` | 3 SCHEDULED + 1 LIBUR |

**Min 3 sesi, max 4 sesi efektif per bulan per enrollment.**

### 3.2 Algoritma Baru — `generateForSchedule()`

```
FASE 1: Kumpulkan semua tanggal day_of_week di bulan ini → dates[] (4 atau 5 item)

FASE 2: Proses minggu 1–4 (dates[0..3])
  untuk setiap date:

    Guard enrollment boundary:
    → jika date < enrollment.effective_date → skip
    → jika date >= enrollment.end_date → skip

    jika date ada di holidayMap:
      holiday = holidayMap[date]

      // Set honor langsung di sini (tidak menunggu absensi form)
      jika holiday.replacement_date IS NOT NULL:
        honorCode   = null     // Rp 0 — akan dibayar via sesi pengganti
        honorAmount = 0
      elseif holiday.is_honor_paid = false:
        honorCode   = null     // Rp 0 — Konser KITA, honor via slip manual
        honorAmount = 0
      else:
        honorCode   = 'H_LIBUR'  // BR-4.10 — libur nasional, bayar penuh
        honorAmount = hitungHonorBase(enrollment.package)

      buat LIBUR session(date, honorCode, honorAmount)

      jika holiday.replacement_date IS NOT NULL:
        replacementQueue.add(holiday.replacement_date)

    else:
      buat SCHEDULED session(date)

FASE 3: Proses replacement sessions (DI LUAR counter 4-sesi)
  untuk setiap replacementDate di replacementQueue:

    // Guard 1: idempotency
    jika session sudah ada (schedule_id, replacementDate) → skip

    // Guard 2: enrollment boundary
    jika replacementDate < enrollment.effective_date → skip + log
    jika replacementDate >= enrollment.end_date → skip + log

    // Guard 3: replacement_date bukan hari libur lain
    jika replacementDate ada di holidayMap → skip + log warning

    // Guard 4: conflict detection guru dan ruang (BR-3.11, BR-3.12)
    jika ScheduleConflictDetector.hasConflict(schedule, replacementDate):
      skip + log warning "Konflik jadwal di tanggal pengganti {replacementDate} untuk {student}"
      continue

    buat SCHEDULED session(replacementDate, honorCode=H_REG, honorAmount=hitungHonorBase(...))

FASE 4: Week 5 — dipakai natural jika replacementDate jatuh di sana, tidak ada logika khusus

FASE 5: Warning jika sesi terjadwal < 3
  jika sessionsScheduled < 3:
    Log::warning("Peringatan: {student} hanya {n} sesi di {bulan} — cek hari libur")
```

### 3.3 Perubahan `loadHolidayDates()`

```php
// SEBELUM — hanya Nasional dan Cuti Bersama
->whereIn('type', ['Nasional', 'Cuti Bersama'])

// SESUDAH — include Internal agar Konser KITA terdeteksi
->whereIn('type', ['Nasional', 'Cuti Bersama', 'Internal'])
->where('is_active', true)
```

### 3.4 Idempotency

Sederhana: cek `(schedule_id, session_date)` sebelum buat session. **Tidak ada retroactive update**
jika holiday ditambah/diubah setelah sessions sudah digenerate. Jika terjadi, admin handle manual
via fitur Reschedule.

---

## 4. Honor Rules Lengkap

| Status Session | Kondisi | Honor Code | Amount |
|----------------|---------|------------|--------|
| HADIR / HADIR_TERLAMBAT | — | H_REG | Penuh |
| LIBUR | Nasional/Cuti Bersama, `is_honor_paid=true`, tanpa `replacement_date` | H_LIBUR | Penuh (BR-4.10) |
| LIBUR | Ada `replacement_date` | `null` | Rp 0 |
| LIBUR | `is_honor_paid=false` (Konser KITA) | `null` | Rp 0 |
| IZIN_RESCHEDULE | Sesi original (guru tidak datang) | H_IZIN *(baru)* | Rp 0 |
| SCHEDULED | Sesi pengganti / replacement | H_REG | Penuh |
| HANGUS | Murid no-show tanpa izin valid | H_HANGUS | Penuh |
| IZIN_VIDEO | Izin ke-2+ | H_VIDEO | Penuh |
| DIGANTI | Guru pengganti | H_PENG | Penuh (ke guru pengganti) |
| CANCELLED | — | `null` | Rp 0 |

**Honor code baru: `H_IZIN`** (amount = Rp 0)
- Untuk sesi original IZIN_RESCHEDULE — guru tidak datang ke studio
- Sudah di-exclude dari agregasi `HonorCalculationService` (konsisten dengan pattern existing)
- Intentional: honor guru hanya dari sesi pengganti (H_REG)

**Generator set honor langsung** saat buat LIBUR session.
LIBUR sessions tidak diproses absensi form, jadi `honor_code` dan `honor_amount` harus di-set
saat session dibuat — bukan menunggu input manual.

**Known gap — backfill:** LIBUR sessions yang sudah ada di database sebelum fitur ini
punya `honor_code = NULL`. Akan di-backfill via migration script saat deploy.

---

## 5. UI — Holiday CRUD (M01)

### 5.1 Form Tambah/Edit Holiday

```
[Tanggal Libur *] [Nama Libur *          ] [Tipe ▼ *]
                                           (Nasional | Cuti Bersama | Internal)

[Tanggal Pengganti]
 - Jika type = Internal: field ini DISABLED
   Tooltip: "Event studio ditangani manual via fitur Reschedule"
 - Jika type = Nasional/Cuti Bersama:
   Auto-suggestion via Alpine.js: hitung minggu ke-5 hari yang sama di bulan tsb
   → Tampil sebagai hint: "Saran: Senin, 30 Maret 2026"
   → Jika tidak ada minggu ke-5: "Tidak ada minggu ke-5 di bulan ini"
   → Owner bisa: pakai saran | isi manual | kosongkan

[is_honor_paid checkbox] "Guru mendapat honor saat libur ini" (default: centang)
 - Jika type = Internal: otomatis uncheck + disabled
   Tooltip: "Honor event diinput manual di slip honor guru"
```

### 5.2 Validasi `replacement_date`
- Wajib dalam **bulan yang sama** dengan `date` libur
- Jika beda bulan → error: *"Penggantian lintas bulan tidak ditangani otomatis.
  Kosongkan dan gunakan fitur Reschedule untuk sesi pengganti."*
- Tidak boleh sama dengan `date` libur lain yang sudah ada (unique DB constraint)
- Tidak boleh sama dengan tanggal libur itu sendiri
- Tidak boleh di-set jika `type = Internal`

### 5.3 Contoh Data Kalender Akademik 2026

| Tanggal | Nama | Tipe | replacement_date | is_honor_paid |
|---------|------|------|-----------------|---------------|
| 16 Jan | Isra Mikraj | Nasional | NULL | true |
| 01 Jan | Tahun Baru | Nasional | 29 Jan | true |
| 23 Mar | Idul Fitri (hari 4) | Nasional | 30 Mar | true |
| 24 Mar | Idul Fitri (hari 5) | Nasional | 31 Mar | true |
| 18 Apr | Konser KITA | Internal | NULL (disabled) | false |
| 01 Mei | Hari Buruh | Nasional | 29 Mei | true |
| 01 Jun | Hari Lahir Pancasila | Nasional | 29 Jun | true |
| 17 Agt | Kemerdekaan RI | Nasional | 31 Agt | true |

---

## 6. Skenario Khusus

### 6.1 Konser KITA

**Alur sistem:**
1. Owner input Konser KITA sebagai holiday `type = Internal`, `replacement_date = NULL` (disabled),
   `is_honor_paid = false`
2. Generator jalan tanggal 25: buat LIBUR session semua murid yang punya jadwal di hari itu,
   `honor_code = null`, `honor_amount = 0`
3. Di M08, admin:
   - Register murid peserta konser di `event_participants`
   - Assign `accompanying_teacher_id` per murid (guru yang mendampingi)
4. Setelah konser:
   - Admin buat replacement session manual via Reschedule **hanya untuk murid non-peserta**
   - Admin input `event_honor` + `transport_honor` di slip honor bulanan guru yang mendampingi

**Catatan honor guru:**
- Guru mendampingi murid di konser → honor via `teacher_honor_slips.event_honor` + `transport_honor` (input manual)
- Guru tidak mendampingi → tidak ada honor untuk hari itu (LIBUR session honor = Rp 0)
- Sesi pengganti murid non-peserta → guru mengajar → H_REG (honor normal)

### 6.2 Ujian KITA

Selalu dijadwalkan hari Minggu. Tidak ada sesi reguler hari Minggu.
**Tidak berdampak pada session generator sama sekali.** Sepenuhnya dihandle M08.

### 6.3 IZIN_RESCHEDULE (Reschedule Manual oleh Murid)

- Sesi original: `honor_code = H_IZIN`, `honor_amount = 0` (guru tidak datang, murid kasih izin valid)
- Sesi pengganti (dibuat admin via RescheduleService): `honor_code = H_REG`, honor penuh
- Total honor guru: **1 slot** (hanya dari sesi pengganti)

---

## 7. M08 — Tambahan Form Event (Konser KITA)

### 7.1 UI Perubahan di Halaman Event Show

Pada tabel peserta (`event_participants`), tambah kolom **"Guru Pendamping"**:

```
| Murid  | Tipe Partisipasi | Biaya   | Guru Pendamping     | Aksi  |
|--------|-----------------|---------|---------------------|-------|
| Budi   | TAMPIL_SAJA     | 295.000 | [ADI           ▼]   | Hapus |
| Sari   | UJIAN_TAMPIL    | 395.000 | [— Pilih Guru —▼]   | Hapus |
| Deni   | TAMPIL_SAJA     | 295.000 | [YUAN          ▼]   | Hapus |
```

- Dropdown berisi semua guru aktif + opsi kosong "— Tidak ada pendamping —"
- Bisa diubah selama event masih DRAFT
- Informasi ini dipakai admin sebagai referensi saat input honor slip

### 7.2 Endpoint Baru

```
PATCH /event-participants/{participant}/teacher
→ Update accompanying_teacher_id
→ Permission: Owner | Admin
→ Hanya jika event status = DRAFT
```

---

## 8. Komponen yang Berubah

| File / Komponen | Jenis Perubahan |
|----------------|----------------|
| `database/migrations/xxxx_add_replacement_date_is_honor_paid_to_holidays.php` | Baru: `replacement_date` + `is_honor_paid` + unique constraint |
| `database/migrations/xxxx_add_accompanying_teacher_to_event_participants.php` | Baru: `accompanying_teacher_id` FK |
| `database/migrations/xxxx_backfill_libur_honor_sessions.php` | Backfill `honor_code` + `honor_amount` untuk LIBUR sessions existing |
| `app/Models/Holiday.php` | Tambah `$fillable`, `$casts` |
| `app/Models/EventParticipant.php` | Tambah `accompanying_teacher_id`, relationship `accompanyingTeacher()` |
| `app/Services/SessionGeneratorService.php` | Rewrite `generateForSchedule()` + update `loadHolidayDates()` |
| `app/Services/HonorCalculationService.php` | Update logic honor LIBUR: cek `is_honor_paid` + `replacement_date` |
| `app/Http/Controllers/HolidayController.php` | Handle `replacement_date` + `is_honor_paid` di store/update |
| `app/Http/Controllers/EventController.php` | Tambah method `updateParticipantTeacher()` |
| `app/Http/Requests/StoreHolidayRequest.php` | Validasi `replacement_date` (same month, unique, Internal=null) |
| `app/Http/Requests/UpdateHolidayRequest.php` | Sama dengan Store |
| `resources/views/holidays/create.blade.php` | Field `replacement_date` + `is_honor_paid` + Alpine.js auto-suggest |
| `resources/views/holidays/edit.blade.php` | Sama dengan create |
| `resources/views/events/show.blade.php` | Kolom guru pendamping + dropdown |
| `CLAUDE.md` | Tambah `H_IZIN` ke daftar honor codes |

---

## 9. Known Gaps & Out of Scope

**Known Gap:**
- LIBUR sessions yang sudah ada sebelum fitur ini punya `honor_code = NULL`.
  Di-backfill via migration script: semua LIBUR sessions existing di-set `honor_code = H_LIBUR`
  + `honor_amount` dihitung dari package price × 50% / 4.
  Alasan: saat backfill dijalankan, kolom `replacement_date` baru ditambah dan semua holidays
  belum punya nilai (NULL), sehingga seluruh LIBUR existing dianggap "libur tanpa pengganti"
  → honor penuh sesuai BR-4.10. Jika owner kemudian menambah `replacement_date` ke holiday lama,
  sessions yang sudah di-backfill tidak diupdate (idempotency — admin handle manual jika perlu).

**Out of Scope (keputusan sadar):**
- Cross-module link `holidays.event_id → events.id`: tidak diimplementasi.
  Konser KITA selalu `type = Internal`, `replacement_date = NULL`. Replacement non-peserta
  ditangani manual. Kompleksitas tidak sebanding (2x/tahun, ~10-20 murid).
- Retroactive update saat holiday ditambah/diubah setelah sessions digenerate:
  tidak diimplementasi. Idempotency sederhana — admin handle manual via Reschedule.
- Konser lintas bulan otomatis: tidak diimplementasi, admin gunakan Reschedule.

---

## 10. Urutan Implementasi yang Disarankan

1. Migrations (3 migrasi: holidays columns, event_participants column, backfill)
2. Model updates (Holiday, EventParticipant)
3. `SessionGeneratorService` rewrite + unit tests
4. `HonorCalculationService` update
5. Form Request validasi Holiday
6. HolidayController update
7. Holiday views (create/edit) + Alpine.js auto-suggest
8. EventController tambah `updateParticipantTeacher()`
9. Event show view + dropdown guru pendamping
10. Update CLAUDE.md honor codes
