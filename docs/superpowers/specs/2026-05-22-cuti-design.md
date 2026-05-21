# Design: Fitur Cuti Murid (M05)
**Tanggal:** 2026-05-22
**Status:** Approved — siap implementasi

---

## Ringkasan

Fitur cuti memungkinkan admin mencatat pengajuan cuti murid (status Aktif → Cuti), menerbitkan invoice biaya cuti Rp 100.000, dan mengembalikan murid ke Aktif setelah periode cuti selesai. 80% fitur sudah ada di codebase — implementasi ini menutup gap yang tersisa.

---

## Business Rules

- Biaya cuti: **Rp 100.000/pengajuan**, invoice terpisah (bukan masuk invoice SPP)
- Status langsung berubah ke **Cuti** saat admin input pengajuan (tidak menunggu lunas)
- Cuti **tidak bisa dibatalkan atau diakhiri lebih awal** — murid harus selesaikan periode penuh
- Maks cuti: **1 bulan + perpanjang 1x** = total maks 2 bulan dari `cuti_from` awal
- Perpanjang cuti: call `ajukanCuti()` saat status sudah Cuti, invoice baru Rp 100.000 lagi
- Guard: cuti diblok jika ada SPP **UNPAID/PARTIAL** (sudah ada di service)
- Sesi selama cuti:
  - **Opsi A** (future months): session generator skip murid berstatus Cuti — sudah benar
  - **Opsi B** (sessions already generated): cancel semua sesi `SCHEDULED` dalam range `cuti_from` s/d `cuti_until`
- Honor guru selama cuti: Rp 0 (PayrollConfig H_CUTI = 0 — sudah ada)

---

## Kondisi Awal (Sudah Ada)

| Komponen | Status |
|---|---|
| `StudentLifecycleService::ajukanCuti()` | ✅ Ada — perlu tambahan session cancel + simpan kolom |
| `StudentLifecycleService::aktifkanDariCuti()` | ✅ Ada — perlu tambahan enforce cuti_until + clear kolom |
| `StudentController::startCuti()` + `returnFromCuti()` | ✅ Lengkap |
| Routes `students.start-cuti` + `students.return-from-cuti` | ✅ Terdaftar |
| UI tombol + form di `students/show.blade.php` | ✅ Ada — perlu disable gate + tampilan cuti_until |
| Invoice FEE_CUTI + createOneOff | ✅ Sudah benar |
| Session generator skip Cuti students | ✅ Sudah benar |
| Dashboard counter `$muridCuti` | ✅ Sudah benar |
| PayrollConfig H_CUTI = 0 | ✅ Sudah benar |

---

## Gap yang Diimplementasi

### 1. Migration — tambah kolom `cuti_from` + `cuti_until`

Tambah dua kolom nullable date ke tabel `students`:

```php
$table->date('cuti_from')->nullable()->after('active_since');
$table->date('cuti_until')->nullable()->after('cuti_from');
```

Diisi saat `ajukanCuti()`, di-clear saat `aktifkanDariCuti()`.

---

### 2. `StudentLifecycleService::ajukanCuti()` — 3 tambahan

**a) Simpan cuti_from + cuti_until ke student record**

Untuk pengajuan baru: simpan keduanya. Untuk perpanjang: hanya update `cuti_until` — `cuti_from` tetap dari pengajuan awal.

```php
// Capture cuti_until lama sebelum di-update (untuk range cancel sesi perpanjang)
$oldCutiUntil = $student->cuti_until;

$updateData = ['status' => 'Cuti', 'cuti_until' => $data['cuti_until']];
if (!$isExtension) {
    $updateData['cuti_from'] = $data['cuti_from'];
}
$student->update($updateData);
```

**b) Cancel sesi SCHEDULED dalam range cuti**

Untuk pengajuan baru: cancel dari `cuti_from` s/d `cuti_until`.
Untuk perpanjang: cancel dari `$oldCutiUntil` s/d `cuti_until` baru (range tambahan saja).

```php
$cancelFrom = $isExtension ? $oldCutiUntil : $data['cuti_from'];

ClassSession::whereIn('enrollment_id', $student->enrollments()->pluck('id'))
    ->where('status', ClassSession::STATUS_SCHEDULED)
    ->whereBetween('session_date', [$cancelFrom, $data['cuti_until']])
    ->update([
        'status' => ClassSession::STATUS_CANCELLED,
        'notes'  => 'Sesi dibatalkan otomatis — murid cuti periode ' .
                    $data['cuti_from'] . ' s/d ' . $data['cuti_until'],
    ]);
```

**c) Validasi maks 2 bulan total (termasuk perpanjang)**

Ambil `cuti_from` awal dari kolom student (untuk perpanjang, `cuti_from` tidak diubah — hanya `cuti_until` yang di-extend). Validasi: selisih `$student->cuti_from` ke `$data['cuti_until']` ≤ 62 hari.

```php
if ($isExtension) {
    $originalFrom = \Carbon\Carbon::parse($student->cuti_from);
    $newUntil     = \Carbon\Carbon::parse($data['cuti_until']);
    if ($originalFrom->diffInDays($newUntil) > 62) {
        throw new InvalidArgumentException(
            'Total cuti melebihi batas maksimal 2 bulan.'
        );
    }
}
```

---

### 3. `StudentLifecycleService::aktifkanDariCuti()` — 2 tambahan

**a) Enforce: tidak bisa akhiri sebelum cuti_until**
```php
if ($student->cuti_until && now()->lt($student->cuti_until)) {
    throw new InvalidArgumentException(
        'Cuti belum selesai. Cuti berlaku hingga ' .
        \Carbon\Carbon::parse($student->cuti_until)->format('d M Y') . '.'
    );
}
```

**b) Clear kolom cuti setelah selesai**
```php
$student->update([
    'status'     => 'Aktif',
    'cuti_from'  => null,
    'cuti_until' => null,
]);
```

---

### 4. UI `students/show.blade.php` — 2 perubahan

**a) Tampilkan periode cuti di badge status**

Saat `$student->status === 'Cuti'` dan `$student->cuti_until` tidak null, tampilkan sub-teks di bawah badge:
```
Cuti s/d: 31 Jul 2026
```

**b) Disable tombol "Akhiri Cuti → Aktif" sebelum cuti_until**

```blade
@php $cutiSelesai = !$student->cuti_until || now()->gte($student->cuti_until); @endphp

@if($cutiSelesai)
    {{-- tombol aktif --}}
    <form method="POST" action="{{ route('students.return-from-cuti', $student->id) }}">...
@else
    {{-- tombol disabled dengan tooltip tanggal --}}
    <button disabled title="Cuti berlaku hingga {{ \Carbon\Carbon::parse($student->cuti_until)->format('d M Y') }}">
        ✅ Akhiri Cuti → Aktif
    </button>
@endif
```

---

### 5. `StudentSeeder` — update sample Cuti

Tambah `cuti_from` dan `cuti_until` ke student seeder yang berstatus Cuti:
```php
'cuti_from'  => now()->startOfMonth()->toDateString(),
'cuti_until' => now()->endOfMonth()->toDateString(),
```

---

### 6. `ImportController::dataMuridRows()` — tambah kolom

Tambah `cuti_until` ke header dan baris contoh (kosong untuk status non-Cuti):
```php
// Header:
['full_name', ..., 'active_since', 'kode_ruangan', 'cuti_until']

// Contoh baris: tambah '' di akhir
['Budi Santoso', ..., '2026-01-15', 'R2', '']
```

---

### 7. `ImportController::referensiKodeRows()` — tambah catatan

Setelah bagian NILAI STATUS, tambah:
```
=== CATATAN KOLOM CUTI_UNTIL ===
Wajib diisi jika status = Cuti (format: YYYY-MM-DD, contoh: 2026-07-31)
Kosongkan jika status bukan Cuti
```

---

### 8. `StudentImportService` — parse + validasi cuti_until

- Baca kolom `cuti_until` dari row Excel
- Validasi: jika `status === 'Cuti'` dan `cuti_until` kosong → error baris
- Validasi: format tanggal valid
- Simpan ke student record saat confirm

---

## Alur Lengkap (Happy Path)

```
Admin buka halaman detail murid (status: Aktif)
  → Klik "Ajukan Cuti"
  → Isi cuti_from, cuti_until, alasan → Submit
  → Service: validasi SPP lunas + maks 2 bulan
  → DB: status = 'Cuti', cuti_from/cuti_until tersimpan
  → DB: sesi SCHEDULED dalam range → CANCELLED
  → DB: invoice baru CUTI Rp 100.000 terbit
  → Redirect ke show murid, flash success

[Periode cuti berlangsung — sesi tidak di-generate]

Admin buka halaman detail murid setelah cuti_until terlewati
  → Tombol "Akhiri Cuti → Aktif" aktif (sebelumnya disabled)
  → Klik → Submit
  → Service: cek today >= cuti_until ✓
  → DB: status = 'Aktif', cuti_from = null, cuti_until = null
  → Redirect ke show murid, flash success
```

## Alur Perpanjang Cuti

```
Admin buka halaman detail murid (status: Cuti)
  → Klik "Perpanjang Cuti"
  → Isi cuti_until baru + alasan → Submit
  → Service: validasi total ≤ 2 bulan dari cuti_from awal
  → DB: cuti_until di-update
  → DB: sesi SCHEDULED tambahan (antara cuti_until lama dan baru) → CANCELLED
  → DB: invoice baru CUTI Rp 100.000 terbit (perpanjangan)
```

---

## File yang Disentuh

```
database/migrations/XXXX_add_cuti_columns_to_students_table.php  [BARU]
app/Services/StudentLifecycleService.php                          [EDIT]
resources/views/students/show.blade.php                           [EDIT]
database/seeders/StudentSeeder.php                                [EDIT]
app/Http/Controllers/ImportController.php                         [EDIT]
app/Services/StudentImportService.php                             [EDIT]
```

---

## File yang TIDAK Disentuh

```
StudentController.php          — controller sudah lengkap
routes/web.php                 — routes sudah terdaftar
app/Services/InvoiceService.php — invoice logic sudah benar
app/Services/SessionGeneratorService.php — sudah skip Cuti
app/Exports/StudentTemplateExport.php    — dead code, tidak dipakai
app/Exports/Sheets/DataMuridSheet.php    — dead code, tidak dipakai
app/Exports/Sheets/ReferensiKodeSheet.php — dead code, tidak dipakai
```
