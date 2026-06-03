# Design Spec: Perbaikan Sistem Schedule & Absensi DIGANTI
**Tanggal:** 2026-06-03
**Status:** Approved — siap implementasi

---

## Latar Belakang

Ditemukan 7 bug/inkonsistensi di dua area:
- **Cluster A:** Schedule CRUD (jadwal mingguan) tidak kompatibel dengan fitur multi-enrollment
- **Cluster B:** Alur DIGANTI di absensi tidak lengkap — tidak ada konfirmasi kehadiran pengganti dan tidak bisa ganti jam/ruang

---

## Cluster A — Schedule CRUD (Jadwal Mingguan)

### A1 + A2: Multi-enrollment di Tab Jadwal

**Masalah:**
- `$activeEnrollment = $student->enrollments->firstWhere('status', 'ACTIVE')` — hanya ambil enrollment pertama di koleksi
- `ScheduleController::store()` pakai `enrollments()->active()->latest()->first()` — ambil enrollment terbaru
- Jika murid punya Piano (lama) + Gitar (baru): UI tampilkan jadwal Piano, submit form masuk ke Gitar. Data silang.
- Tab Jadwal hanya loop satu enrollment — murid multi-kelas tidak bisa manage jadwal semua kelas

**Desain:**
- Tab Jadwal tampilkan semua enrollment ACTIVE dalam grup terpisah, diurutkan `is_primary desc`
- Setiap grup punya header: ikon instrumen, nama paket, guru, badge "(Utama)" jika `is_primary`
- Setiap grup punya tombol "+ Tambah Jadwal" sendiri
- Form tambah jadwal include `<input type="hidden" name="enrollment_id" value="{{ $enrollment->id }}">`
- `ScheduleController::store()` pakai `enrollment_id` dari request, validasi `exists:enrollments,id`
- Tambah guard: enrollment harus ACTIVE dan milik student yang bersangkutan

**Struktur view baru di tab Jadwal:**
```
@foreach($activeEnrollments as $enrollment)
  // Header grup: instrumen · paket · guru
  // Tabel jadwal $enrollment->schedules
  // Form tambah jadwal dengan hidden enrollment_id
@endforeach
```

### A3: Fix `$bookedSchedules` — konflik palsu di edit modal

**Masalah:**
Query tidak select `id`, sehingga client-side exclusion `s.id !== editSchedule.id` selalu false (undefined !== number). Jadwal yang sedang diedit dihitung sebagai "menempati ruangan" — ruangan selalu tampak penuh.

**Fix:**
```php
// StudentController::show()
$bookedSchedules = Schedule::active()
    ->whereNotNull('room_id')
    ->get(['id', 'room_id', 'day_of_week', 'start_time', 'end_time']); // tambah 'id'
```

### A4: Fix filter instrumen ruangan per-enrollment

**Masalah:**
`$studentInstrument` diambil dari enrollment pertama, selalu sama untuk semua form di halaman. Murid Piano + Gitar: form Gitar salah filter (hanya tampilkan ruangan Piano).

**Fix:**
Untuk setiap grup enrollment, kirim instrumen spesifik enrollment tersebut ke Alpine.js:
```blade
x-data="{
    instrument: {{ Js::from($enrollment->package?->instrument?->name) }},
    ...
}"
```

### A5: Ownership validation di `ScheduleController::update()`

**Masalah:** Route `PATCH /schedules/{schedule}` tidak memverifikasi bahwa schedule milik student yang bersangkutan.

**Fix:** Tambah guard di `update()`:
```php
abort_unless(
    $schedule->enrollment->student_id === $student->id,
    403,
    'Jadwal tidak ditemukan untuk murid ini.'
);
```

Route diubah dari `PATCH /schedules/{schedule}` menjadi `PATCH /students/{student}/schedules/{schedule}` agar `$student` tersedia via route model binding. Route `destroy` dan `toggle-active` ikut diubah ke nested pattern yang sama.

---

## Cluster B — Absensi: DIGANTI Two-Phase

### Konsep

DIGANTI dipecah menjadi 2 fase. Tidak ada status baru di DB — dibedakan oleh ada/tidaknya `honor_code`:

| Fase | Status | `honor_code` | Arti |
|------|--------|--------------|------|
| Assignment | `DIGANTI` | `null` | Pengganti ditugaskan, belum konfirmasi hadir |
| Konfirmasi Hadir | `DIGANTI` | `H_PENG` | Pengganti terkonfirmasi hadir, honor final |
| Batalkan Penugasan | *(kembali `SCHEDULED`)* | — | Reset, admin input ulang |

### B1: Expand Modal DIGANTI

Modal DIGANTI sekarang hanya punya dropdown guru. Tambahkan:
- **Jam Mulai Pengganti** (input time, opsional, default: jam asli sesi)
- **Ruangan Pengganti** (select room, opsional, default: ruangan asli sesi)

Field jam dan ruang hanya berpengaruh jika diisi (nullable). Jika dikosongkan, sesi tetap di jam/ruang asli.

**Perubahan `AttendanceService::recordAttendance()` untuk status DIGANTI:**
- Simpan `substitute_teacher_id`
- Update `start_time`/`end_time` jika `substitute_start_time` + `substitute_end_time` diisi
- Update `room_id` jika `substitute_room_id` diisi
- Set `honor_code = null`, `honor_amount = null` (honor pending konfirmasi)

**Perubahan `UpdateAbsensiRequest`:** tambah validasi opsional:
```php
'substitute_start_time' => 'nullable|date_format:H:i',
'substitute_end_time'   => 'nullable|date_format:H:i|after:substitute_start_time',
'substitute_room_id'    => 'nullable|exists:rooms,id',
```

### B2: Konfirmasi Kehadiran Pengganti

Setelah DIGANTI di-set (`honor_code = null`), baris absensi tampilkan:
- Badge: `↔ NamaGuru (⏳ belum dikonfirmasi)`
- Dua tombol:
  - **`✓ Konfirmasi Hadir`** — hitung honor H_PENG ke pengganti, badge berubah `↔ NamaGuru ✓`
  - **`✗ Batalkan Penugasan`** — reset `substitute_teacher_id = null`, `status = SCHEDULED`, baris normal

**Endpoint baru:**
```
POST /absensi/{classSession}/confirm-substitute
Body: { action: 'hadir' | 'batal' }
```

**Method baru `AbsensiController::confirmSubstitute()`:**
- `action = hadir`: hitung honor dari paket enrollment → set `honor_code = H_PENG`, `honor_amount = X` (formula: `price_per_month * 0.5 / 4`)
- `action = batal`: set `substitute_teacher_id = null`, `status = SCHEDULED`, `honor_code = null`, `honor_amount = null`. Restore `start_time`/`end_time`/`room_id` dari `$session->schedule` (jadwal mingguan asal) — menggunakan relasi `ClassSession::schedule()`

**Deteksi "sudah dikonfirmasi" di `_row.blade.php`:**
```php
$isDigantiPending = $session->status === 'DIGANTI' && $session->honor_code === null;
$isDigantiConfirmed = $session->status === 'DIGANTI' && $session->honor_code === 'H_PENG';
```

### B3: Klarifikasi Session Edit vs Absensi DIGANTI

Keduanya tetap ada dengan peran berbeda. Tambah helper text di UI:

| Fitur | Lokasi | Kapan Dipakai |
|-------|--------|---------------|
| Session Edit | `/sessions` | Koreksi sebelum sesi: perubahan guru/jam/ruang permanen di data sesi |
| DIGANTI | `/absensi` | Hari H: guru asli berhalangan, diganti sementara |

---

## Tidak Ada Perubahan Schema Database

Kolom yang ada sudah cukup:
- `substitute_teacher_id` — untuk menyimpan guru pengganti
- `honor_code` — null = pending, H_PENG = confirmed
- `honor_amount` — null = pending, angka = confirmed
- `start_time`, `end_time`, `room_id` — bisa di-update jika pengganti masuk di jam/ruang berbeda

---

## File yang Akan Diubah

### Cluster A
| File | Perubahan |
|------|-----------|
| `app/Http/Controllers/ScheduleController.php` | `store()`: pakai `enrollment_id` dari request; `update()`: tambah ownership guard |
| `app/Http/Controllers/StudentController.php` | `show()`: tambah `id` ke `$bookedSchedules` query; hapus `$activeEnrollment` / `$studentInstrument` (diganti per-enrollment) |
| `resources/views/students/show.blade.php` | Tab Jadwal: loop `$activeEnrollments`, form per-enrollment dengan `enrollment_id` + instrumen masing-masing |
| `routes/web.php` | Ubah route `schedules.update`, `schedules.destroy`, `schedules.toggle-active` ke nested `students/{student}/schedules/{schedule}` |

### Cluster B
| File | Perubahan |
|------|-----------|
| `app/Http/Controllers/AbsensiController.php` | Tambah method `confirmSubstitute()` |
| `app/Http/Requests/UpdateAbsensiRequest.php` | Tambah field opsional jam/ruang pengganti |
| `app/Services/AttendanceService.php` | DIGANTI: honor pending, update jam/ruang opsional |
| `resources/views/absensi/_row.blade.php` | Modal DIGANTI expanded; conditional tombol konfirmasi |
| `routes/web.php` | Tambah route `POST /absensi/{classSession}/confirm-substitute` |

---

## Urutan Implementasi yang Disarankan

1. **A3** (fix bookedSchedules id) — quickest win, 1 baris
2. **A1+A2** (multi-enrollment tab jadwal + store fix) — core fix
3. **A4** (instrumen per-enrollment) — follow-up A1
4. **A5** (ownership validation + route fix) — hardening
5. **B1** (expand modal DIGANTI + backend) — UX improvement
6. **B2** (konfirmasi kehadiran pengganti) — closes the loop
7. **B3** (helper text klarifikasi) — polish
