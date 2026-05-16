# Import Excel — Extend dengan Enrollment, Jadwal & Status History

> **For agentic workers:** Gunakan `superpowers:writing-plans` untuk membuat implementation plan dari spec ini.

**Goal:** Memperluas fitur import Excel murid agar saat konfirmasi import, sistem otomatis membuat enrollment, schedule, dan student_status_histories untuk murid berstatus Aktif — sehingga migrasi 300 murid dari sistem lama selesai dalam satu langkah tanpa input manual per murid.

**Konteks:** Import murid sudah berjalan (dua fase: validate → confirm). Template sudah punya kolom `preferred_day` dan `preferred_time` yang terisi dari sistem lama. Yang belum ada: pembuatan enrollment, schedule, dan audit trail lifecycle saat confirm.

---

## Scope Perubahan

| Komponen | Jenis Perubahan |
|---|---|
| Template Excel (`ImportController@downloadTemplate`) | Tambah kolom `kode_ruangan` |
| `StudentImportService@validateRow` | Tambah validasi `kode_ruangan` |
| `StudentImportService@confirm` (atau `upsertStudent`) | Buat enrollment + schedule + status_history |
| Preview view (`import/preview.blade.php`) | Tampilkan kolom Jadwal & Ruangan + ringkasan |
| `StudentImportServiceTest` | Tambah test cases |
| `ImportControllerTest` | Tambah feature test |

---

## Section 1 — Template & Validasi

### 1a. Tambah Kolom `kode_ruangan`

Tambah satu kolom di akhir template Excel:

```
full_name, nickname, gender, birth_date, phone, email,
address, notes, parent_name, parent_phone, parent_email,
parent_relationship, status, package_code, teacher_code,
preferred_day, preferred_time, active_since, kode_ruangan  ← BARU
```

Contoh baris di sheet "Data Murid":
```
Budi Santoso, Budi, L, 2010-05-15, 08111111111, ..., Aktif, REG-PIANO-BASIC, ADI, Senin, 15:30, 2026-01-15, R2
```

Kolom `kode_ruangan` **opsional** — boleh dikosongkan.

### 1b. Validasi `kode_ruangan` di Fase Validate

Di `validateRow()`:

```php
// Validasi kode_ruangan jika diisi
if (!empty($row['kode_ruangan'])) {
    $room = Room::where('code', strtoupper(trim($row['kode_ruangan'])))->where('is_active', true)->first();
    if (!$room) {
        return "Kode ruangan '{$row['kode_ruangan']}' tidak ditemukan.";
    }
    // Warning (tidak block) jika instrumen tidak cocok
    $instrumentName = $package?->instrument?->name;
    if ($instrumentName && !$room->supportsInstrument($instrumentName)) {
        $warnings[] = "Ruangan {$room->code} tidak support instrumen {$instrumentName}.";
    }
}
```

**Error** (baris ditolak): `kode_ruangan` diisi tapi tidak ditemukan di tabel `rooms`.

**Warning** (baris tetap diimport): `kode_ruangan` valid tapi instrumen tidak cocok dengan `supported_instruments` ruangan. Admin perlu cek setelah import.

---

## Section 2 — Enrollment, Schedule & Status History

Dijalankan di fase **confirm** (setelah admin klik Konfirmasi Import), untuk setiap baris valid.

### Kondisi Trigger

Enrollment + Schedule dibuat **hanya jika semua terpenuhi**:
- `status` = `Aktif`
- `package_code` tidak kosong (package ditemukan)
- `teacher_code` tidak kosong (teacher ditemukan)
- `preferred_day` tidak kosong
- `preferred_time` tidak kosong

Jika salah satu kosong → student tetap dibuat, enrollment & schedule di-skip.

### 2a. Buat Enrollment

```php
$enrollment = Enrollment::create([
    'student_id'     => $student->id,
    'package_id'     => $package->id,
    'teacher_id'     => $teacher->id,
    'effective_date' => $row['active_since'] ?? today()->toDateString(),
    'status'         => 'ACTIVE',
]);
```

### 2b. Buat Schedule

`end_time` dihitung otomatis dari `preferred_time + package.duration_min`:

```php
$startTime = Carbon::createFromFormat('H:i', $row['preferred_time']);
$endTime   = $startTime->copy()->addMinutes($package->duration_min);

$room = !empty($row['kode_ruangan'])
    ? Room::where('code', strtoupper($row['kode_ruangan']))->where('is_active', true)->first()
    : null;

Schedule::create([
    'enrollment_id' => $enrollment->id,
    'day_of_week'   => $this->parseDayOfWeek($row['preferred_day']), // Senin→1, Selasa→2, dst.
    'start_time'    => $startTime->format('H:i'),
    'end_time'      => $endTime->format('H:i'),
    'room_id'       => $room?->id,
    'is_active'     => true,
]);
```

Konversi `preferred_day` → `day_of_week` (Carbon convention, Minggu=0):

```php
private function parseDayOfWeek(string $day): int
{
    return match(strtolower(trim($day))) {
        'minggu'  => 0,
        'senin'   => 1,
        'selasa'  => 2,
        'rabu'    => 3,
        'kamis'   => 4,
        'jumat'   => 5,
        'sabtu'   => 6,
        default   => throw new \InvalidArgumentException("Hari tidak valid: {$day}"),
    };
}
```

### 2c. Buat student_status_histories

Untuk setiap murid berstatus `Aktif` yang diimport (dengan atau tanpa jadwal):

```php
StudentStatusHistory::create([
    'student_id'    => $student->id,
    'from_status'   => null,
    'to_status'     => 'Aktif',
    'reason'        => 'migrasi',
    'skipped_trial' => true,
    'metadata'      => ['skipped_trial' => true],
    'changed_by'    => auth()->id(),
]);
```

`reason = 'migrasi'` di-hardcode — tidak perlu input admin karena konteks import selalu migrasi dari sistem lama.

---

## Section 3 — Preview

### 3a. Tabel Preview — Kolom Baru

Tambah kolom **Jadwal** dan **Ruangan** di tabel preview:

| Murid | Status | Paket | Guru | Jadwal | Ruangan | Keterangan |
|---|---|---|---|---|---|---|
| Budi Santoso | Aktif | ✓ | ✓ | Senin 15:00–15:30 | R2 | ✓ Murid + Jadwal |
| Ani Rahayu | Aktif | ✓ | ✓ | Selasa 10:00–10:30 | ⚠️ R8 | Warning: instrumen |
| Cici | Calon | ✓ | ✓ | — | — | ✓ Murid saja |
| Dodi | Aktif | ✓ | ✓ | — | — | ✓ Murid saja (no preferred_day) |

**Legend:**
- `✓ Murid + Jadwal` — enrollment + schedule akan dibuat
- `✓ Murid saja` — hanya student, tidak ada jadwal (preferred_day/time kosong atau status bukan Aktif)
- `⚠️ Warning` — ruangan valid tapi instrumen tidak cocok, import tetap jalan

### 3b. Ringkasan di Atas Tabel

```
✓ 285 murid akan diimport dengan jadwal
   12 murid tanpa jadwal (preferred_day kosong)
    3 murid dengan warning ruangan — cek setelah import
```

---

## Section 4 — Testing

### Unit Test (`StudentImportServiceTest`)

Tambah test cases berikut:

| Test | Kondisi | Ekspektasi |
|---|---|---|
| `test_import_aktif_buat_enrollment_schedule_dan_status_history` | status=Aktif, semua field lengkap | Enrollment + Schedule + StatusHistory terbuat |
| `test_import_aktif_tanpa_preferred_day_skip_schedule` | status=Aktif, preferred_day kosong | Hanya Student, tidak ada Schedule |
| `test_import_calon_tidak_buat_enrollment` | status=Calon | Hanya Student, tidak ada Enrollment/Schedule/StatusHistory |
| `test_kode_ruangan_tidak_valid_ditolak` | kode_ruangan='X99' tidak ada di DB | Baris error, tidak diimport |
| `test_kode_ruangan_instrumen_tidak_cocok_warning` | kode_ruangan valid tapi instrumen beda | Warning, room_id tetap diisi, import jalan |
| `test_end_time_dihitung_dari_duration_min` | preferred_time=15:00, duration_min=30 | end_time=15:30 |
| `test_status_history_reason_migrasi` | status=Aktif diimport | reason='migrasi', skipped_trial=true |

### Feature Test (`ImportControllerTest`)

| Test | Kondisi | Ekspektasi |
|---|---|---|
| `test_confirm_buat_schedules_di_db` | POST confirm dengan data valid | schedules record ada di DB |
| `test_confirm_buat_status_history_migrasi` | POST confirm murid Aktif | student_status_histories ada dengan reason='migrasi' |

---

## Tidak Diubah

- Alur dua fase (validate → confirm) tidak berubah
- Deteksi duplikat (full_name + phone) tidak berubah
- Murid non-Aktif tetap diimport sebagai student saja
- `SessionGeneratorService` tidak perlu diubah — jadwal yang sudah ada langsung siap di-generate sesinya

---

## Alur Lengkap Setelah Implementasi

```
Admin upload Excel (dengan kolom kode_ruangan)
    ↓
Fase 1 — Validate:
    ✓ Validasi field murid (existing)
    ✓ Validasi kode_ruangan (exists di DB)
    ⚠️ Warning jika instrumen tidak cocok
    ↓
Preview tabel: tampilkan Jadwal + Ruangan + ringkasan
    ↓
Admin klik Konfirmasi
    ↓
Fase 2 — Confirm (per murid):
    1. Buat/update Student
    2. Jika Aktif → buat StudentStatusHistory (reason=migrasi)
    3. Jika Aktif + preferred_day + preferred_time → buat Enrollment
    4. → buat Schedule (end_time otomatis, room_id dari kode_ruangan)
    ↓
Redirect ke halaman sukses
    ↓
Admin tinggal jalankan session generator untuk bulan berjalan
```

---

## Edge Cases

| Kondisi | Handling |
|---|---|
| Murid Aktif tanpa preferred_day | Student + StatusHistory dibuat, Schedule di-skip |
| Murid Aktif tanpa kode_ruangan | Schedule dibuat dengan room_id=null |
| kode_ruangan tidak ditemukan di DB | Error — baris ditolak |
| kode_ruangan instrumen tidak cocok | Warning — import tetap jalan, room_id tetap diisi |
| active_since kosong | effective_date enrollment = tanggal hari ini |

---

*Spec dibuat: 2026-05-16*
*Berkaitan dengan: M02 Pendaftaran & Trial, M03 Penjadwalan*
