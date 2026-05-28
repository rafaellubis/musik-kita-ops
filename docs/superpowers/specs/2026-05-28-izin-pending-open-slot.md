# Spec: IZIN_PENDING — Reschedule Tanggal Menyusul & Open Slot Board

**Tanggal:** 2026-05-28  
**Status:** Approved  
**Modul terkait:** M03 Penjadwalan, M04 Absensi, M06 Honor Guru, Portal Guru

---

## Latar Belakang

Kondisi lapangan: murid izin reschedule tapi belum tahu kapan bisa datang pengganti. Sistem saat ini memaksa Admin mengisi tanggal pengganti di saat yang sama ketika mencatat izin — tidak mungkin dilakukan jika murid belum konfirmasi.

Kebutuhan yang muncul:
1. Admin bisa catat izin tanpa tanggal pengganti (status "pending")
2. Slot yang ditinggalkan murid otomatis terlihat di Open Slot Board — bisa diisi murid lain
3. Guru lihat daftar "Sesi Pending" di dashboard — tahu ada kewajiban sesi yang belum terjadwal
4. Guru bisa suggest tanggal ke Admin via portal

---

## Pendekatan: Status Baru IZIN_PENDING (Approach A)

Tidak ada tabel baru. Solusi minimal: satu status baru di enum `class_sessions.status`, satu halaman baru per sisi (Admin + Guru), edit minor di tiga file existing.

---

## Section 1 — Data Model

### Perubahan enum `class_sessions.status`

Tambah satu nilai baru:

```
IZIN_PENDING
```

Posisi di enum (setelah IZIN_RESCHEDULE):
```
SCHEDULED | HADIR | HADIR_TERLAMBAT | IZIN_RESCHEDULE | IZIN_PENDING |
IZIN_VIDEO | HANGUS | LIBUR | DIGANTI | CANCELLED
```

### Nilai kolom saat IZIN_PENDING dibuat

| Kolom | Nilai |
|---|---|
| `status` | `IZIN_PENDING` |
| `honor_code` | `'H_IZIN'` |
| `honor_amount` | `0` |
| `origin_session_id` | `NULL` (belum ada replacement) |
| `notes` | Catatan izin dari Admin (opsional) |

### State machine tambahan

```
SCHEDULED      → IZIN_PENDING        Admin: izin, tanggal menyusul
IZIN_PENDING   → IZIN_RESCHEDULE     Admin: input tanggal pengganti → replacement dibuat
```

Transisi `IZIN_PENDING → IZIN_RESCHEDULE` terjadi saat Admin membuat sesi pengganti untuk murid yang sama lewat Open Slot Board atau halaman absensi. `RescheduleService::createReplacement()` yang sudah ada dipakai — `origin_session_id` diisi saat itu.

### Perubahan HonorCalculationService

Tambah `STATUS_IZIN_PENDING` ke array `whereNotIn()` di method `calculateForTeacher()` dan `getSessionBreakdown()`:

```php
->whereNotIn('status', [
    ClassSession::STATUS_SCHEDULED,
    ClassSession::STATUS_IZIN_RESCHEDULE,
    ClassSession::STATUS_IZIN_PENDING,   // ← tambah ini
    ClassSession::STATUS_CANCELLED,
])
```

Tanpa ini, sesi IZIN_PENDING masuk query tapi dengan `honor_amount = 0` — tidak inflate honor, tapi muncul sebagai baris kosong membingungkan di breakdown slip.

---

## Section 2 — Komponen Baru & Perubahan

### Komponen baru

**`resources/views/guru/sesi-pending.blade.php`**  
Halaman list sesi IZIN_PENDING untuk guru yang login. Tiap card menampilkan:
- Nama murid, sesi ke-, paket, tanggal asli izin
- Badge jumlah hari sudah pending (merah jika > 14 hari)
- Accordion "Suggest Tanggal" — form tanggal + jam + catatan opsional
- Tombol "Kirim Saran ke Admin" → POST ke `guru.sesi-pending.suggest`

**`GuruController::sesiPending()`** — method baru  
Query: sesi milik guru ini dengan status `IZIN_PENDING`, diurutkan dari yang paling lama pending.

```php
$sesiPending = ClassSession::where('teacher_id', $teacher->id)
    ->where('status', ClassSession::STATUS_IZIN_PENDING)
    ->with(['student', 'enrollment.package'])
    ->orderBy('session_date')
    ->get();
```

**`GuruController::suggestDate()`** — method baru  
Menerima POST dari form suggest. Validasi: tanggal harus >= hari ini. Simpan ke `notes` sesi dengan prefix `[SARAN GURU: tgl jam - catatan]`. Tidak membuat sesi baru — hanya catatan untuk Admin.

**`resources/views/admin/open-slot-board.blade.php`** (atau di dalam absensi)  
Halaman Admin: semua sesi IZIN_PENDING yang belum punya replacement (tidak ada record dengan `origin_session_id` = id sesi ini). Kolom: tanggal, jam, guru, ruang, nama murid, sesi ke-, sudah berapa hari, aksi.

Dua aksi dari Open Slot Board:
1. **Isi dengan murid lain** — form pilih murid + enrollment → `RescheduleService::createReplacement()` dengan enrollment murid yang dipilih. Sesi Budi tetap IZIN_PENDING.
2. **Jadwalkan pengganti Budi** — input tanggal + ruang → `RescheduleService::createReplacement()` untuk enrollment Budi → status Budi berubah ke IZIN_RESCHEDULE.

### Perubahan file existing

**`AbsensiController::update()`**  
Tambah `IZIN_PENDING` sebagai pilihan status yang valid saat Admin klik dropdown absensi. Di samping `IZIN_RESCHEDULE` yang sudah ada, muncul opsi "Izin — Tanggal Menyusul (Pending)".

Saat dipilih: set `status = IZIN_PENDING`, `honor_code = 'H_IZIN'`, `honor_amount = 0`. Tidak panggil `RescheduleService` — tidak ada replacement dibuat saat ini.

**`resources/views/guru/dashboard.blade.php`**  
Tambah dua elemen (kondisional, hanya muncul jika `$jumlahPending > 0`):
1. Banner alert di bawah header: "X Sesi Pending — tap untuk detail" → link ke halaman sesi-pending
2. Kartu ketiga di grid ringkasan: angka pending (merah) + label "Sesi Pending"

**`HonorCalculationService`**  
Edit `whereNotIn()` seperti dijelaskan di Section 1.

**`ClassSession` model**  
Tambah konstanta:
```php
const STATUS_IZIN_PENDING = 'IZIN_PENDING';
```

---

## Section 3 — Open Slot Board (Admin)

### Definisi "slot terbuka"

Sesi dianggap terbuka jika:
```sql
status = 'IZIN_PENDING'
AND NOT EXISTS (
    SELECT 1 FROM class_sessions cs2
    WHERE cs2.origin_session_id = class_sessions.id
)
```
Artinya: sesi sudah izin pending, dan belum ada sesi replacement yang dibuat untuk murid ini.

### Mengisi slot dengan murid lain

Saat Admin assign Cici ke slot Budi (Mei 3, 09:00, Studio 8, Thomas):
- Panggil `RescheduleService::createReplacement()` dengan `student_id` dan `enrollment_id` milik Cici
- Sesi baru untuk Cici: `session_date = 3 Mei`, `teacher_id = Thomas`, `room_id = Studio 8`, `honor_code = null` (diisi saat absensi), `origin_session_id = id sesi reschedule Cici yang original`
- Sesi Budi **tetap IZIN_PENDING** — belum selesai

Conflict detection tetap berjalan seperti biasa (guru + ruang tidak boleh overlap).

### Honor flow lengkap

| Sesi | honor_code | honor_amount | Masuk slip |
|---|---|---|---|
| Budi IZIN_PENDING (Mei 3) | H_IZIN | Rp 0 | Tidak (excluded) |
| Cici mengisi slot Budi (Mei 3) | H_REG | Normal dari paket Cici | Ya, slip Thomas Mei |
| Pengganti Budi (mis. Jun 7) | H_REG | Normal dari paket Budi | Ya, slip Thomas Jun |

Tidak ada dobel bayar. Studio tidak rugi karena murid tetap bayar SPP penuh (BR-3.6).

---

## Routing

```php
// Portal Guru (tambahan)
Route::get('/guru/sesi-pending', [GuruController::class, 'sesiPending'])->name('guru.sesi-pending.index');
Route::post('/guru/sesi-pending/{session}/suggest', [GuruController::class, 'suggestDate'])->name('guru.sesi-pending.suggest');

// Admin (tambahan)
Route::get('/absensi/open-slots', [AbsensiController::class, 'openSlotBoard'])->name('absensi.open-slots');
Route::post('/absensi/open-slots/{session}/assign', [AbsensiController::class, 'assignOpenSlot'])->name('absensi.open-slots.assign');
Route::post('/absensi/open-slots/{session}/schedule', [AbsensiController::class, 'scheduleReplacement'])->name('absensi.open-slots.schedule');
```

Semua route Admin di middleware `role:Owner|Admin`. Route Guru di middleware `role:Guru`.

---

## Migration

```php
// Ubah enum status di class_sessions
Schema::table('class_sessions', function (Blueprint $table) {
    $table->enum('status', [
        'SCHEDULED', 'HADIR', 'HADIR_TERLAMBAT',
        'IZIN_RESCHEDULE', 'IZIN_PENDING',
        'IZIN_VIDEO', 'HANGUS', 'LIBUR', 'DIGANTI', 'CANCELLED',
    ])->change();
});
```

---

## Yang Tidak Berubah

- `RescheduleService` — dipakai apa adanya, tidak ada modifikasi
- Tabel `class_sessions` — tidak ada kolom baru
- Flow IZIN_RESCHEDULE yang sudah ada — tetap berjalan normal
- Portal guru: halaman jadwal, honor, profil — tidak disentuh
- Honor slip PDF — tidak berubah

---

## Edge Cases

| Kasus | Penanganan |
|---|---|
| Admin salah pilih IZIN_PENDING, harusnya IZIN_RESCHEDULE | Admin bisa ubah status langsung dari halaman Open Slot Board — input tanggal → jadi IZIN_RESCHEDULE |
| Guru di-nonaktifkan saat masih ada sesi IZIN_PENDING miliknya | Sesi tetap tercatat, Open Slot Board masih menampilkan — Admin tetap bisa jadwalkan pengganti |
| Murid mundur saat masih IZIN_PENDING | Status sesi biarkan saja (IZIN_PENDING historis), tidak perlu diubah |
| Slip honor sudah CALCULATED saat replacement baru dibuat di bulan yang sama | Replacement masuk slip bulan berikutnya (sesuai `session_date` replacement) — tidak ada masalah |
