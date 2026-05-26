# Design Spec: Honor Guru Pendamping Konser KITA

**Tanggal:** 2026-05-26
**Status:** Approved — siap implementasi
**Modul:** M06 Honor Guru / M08 Event

---

## Latar Belakang

Sistem sudah bisa tracking siapa guru yang mendampingi murid di Konser KITA
(`event_participants.accompanying_teacher_id`), tapi honor untuk guru pendamping
belum pernah dihitung otomatis — seluruhnya input manual oleh Owner.

Dua business rule yang belum terimplementasi:

1. Guru pendamping mendapat honor flat per event (kode `H_PENDAMPING`)
2. Sesi reguler guru di hari konser yang muridnya ikut konser — honor = Rp 0
   (sudah ditangani mekanisme Holiday Internal, bukan tugas service ini)

---

## Business Rules

| Rule | Keterangan |
|------|-----------|
| Honor pendamping | Flat per event, kode `H_PENDAMPING` di `payroll_configs` |
| Nominal default | Rp 250.000 (sama dengan H_UJIAN saat ini, tapi entry terpisah — bisa berbeda) |
| Satu guru, banyak murid | Tetap hanya Rp 250.000 sekali per event, bukan per murid |
| Sesi reguler hari konser | Honor = 0 untuk semua murid — sudah ditangani Holiday Internal (`is_honor_paid=false`) |
| Murid tidak ikut konser | Sesi mereka juga LIBUR karena holiday Internal, tidak disentuh service ini |
| Konfigurasi | Owner ubah nominal via halaman Payroll Config (tidak perlu sentuh kode) |

---

## Arsitektur

### Service Baru: `App\Services\EventHonorService`

Satu method publik:

```php
public function processEventCompletion(Event $event): array
```

**Return:**
```php
[
    'slips_updated'   => int,   // jumlah slip honor yang diperbarui
    'slips_skipped'   => int,   // jumlah slip dilewati (sudah PAID)
    'holiday_warning' => bool,  // true jika tidak ada Holiday Internal di event_date
]
```

### Trigger

`EventController::complete()` — inject `EventHonorService` via constructor, panggil
setelah `$event->update(['status' => Event::STATUS_COMPLETED])`:

```php
$result = $this->eventHonorService->processEventCompletion($event);
```

---

## Alur Detail `processEventCompletion`

### Step 1 — Warning Check

Cek apakah ada `Holiday` bertipe `Internal` untuk `event_date`:

```php
$hasHoliday = Holiday::where('date', $event->event_date)
    ->where('type', 'Internal')
    ->exists();
```

Jika `false` → set `holiday_warning = true` di return array. Tidak memblokir proses.

### Step 2 — Baca Nominal Honor dari PayrollConfig

```php
$config = PayrollConfig::where('scenario_code', 'H_PENDAMPING')
    ->where('is_active', true)
    ->first();

$honorAmount = (int) ($config?->value_or_formula ?? 250000);
```

Fallback ke 250.000 jika entry belum ada di database.

### Step 3 — Inject Honor ke Slip Guru Pendamping

`EventHonorService` menerima `int $createdBy` (auth user id) sebagai parameter kedua.

```php
public function processEventCompletion(Event $event, int $createdBy): array
```

```php
$teacherIds = $event->participants()
    ->whereNotNull('accompanying_teacher_id')
    ->pluck('accompanying_teacher_id')
    ->unique();

foreach ($teacherIds as $teacherId) {
    $month = $event->event_date->month;
    $year  = $event->event_date->year;

    $slip = HonorSlip::where('teacher_id', $teacherId)
        ->where('month', $month)
        ->where('year', $year)
        ->first();

    if (!$slip) {
        // Buat slip baru — slip_number harus di-generate (format SLIP/YYYY/MM/NNNN)
        // Ikuti pola yang sama dengan HonorCalculationService::generateSlipNumber()
        $slip = new HonorSlip([
            'slip_number'      => $this->generateSlipNumber($year, $month),
            'teacher_id'       => $teacherId,
            'month'            => $month,
            'year'             => $year,
            'base_honor'       => 0,
            'event_honor'      => 0,
            'transport_honor'  => 0,
            'other_honor'      => 0,
            'status'           => HonorSlip::STATUS_DRAFT,
            'created_by'       => $createdBy,
        ]);
    }

    if ($slip->isLocked()) {
        $result['slips_skipped']++;
        continue;
    }

    $slip->event_honor += $honorAmount;
    $slip->event_honor_note = trim(
        ($slip->event_honor_note ? $slip->event_honor_note . ' | ' : '')
        . "Pendamping {$event->name}"
    );
    $slip->recalcTotal();
    $slip->save();

    $result['slips_updated']++;
}
```

`generateSlipNumber()` adalah private method di `HonorCalculationService` — saat implementasi,
ekstrak ke private method yang sama di `EventHonorService` (duplikasi kecil yang disengaja
agar dua service tidak saling bergantung).

---

## Flash Message di UI

| Kondisi | Pesan |
|---------|-------|
| Normal | "Event selesai. {N} slip honor guru pendamping diperbarui." |
| Ada slip PAID | "Event selesai. {N} slip dilewati karena sudah PAID — perlu update manual." |
| Tidak ada Holiday | "Peringatan: Tidak ada Hari Libur Internal untuk tanggal ini. Pastikan sesi kelas sudah diatur via menu Hari Libur." |
| Tidak ada guru pendamping | "Event selesai. Tidak ada guru pendamping yang terdaftar." |

Semua kondisi bisa muncul bersamaan (sukses + warning holiday).

---

## Konfigurasi PayrollConfig

Tambahkan entry baru via seeder:

```php
['H_PENDAMPING', 'Honor Guru Pendamping Konser KITA', 'FIXED', '250000',
 'Honor flat per event untuk guru yang mendampingi murid di Konser KITA. Bisa berbeda dengan H_UJIAN.'],
```

Owner ubah nilai via halaman `/payroll-configs` (sudah ada, Owner-only).

---

## Yang Tidak Berubah

- **Zero sesi hari konser** — sudah ditangani Holiday Internal (`is_honor_paid=false`).
  `EventHonorService` tidak menyentuh `ClassSession`.
- **Event ↔ Holiday tidak di-link otomatis** — Admin tetap buat keduanya manual.
  Service hanya memberi warning jika holiday tidak ditemukan.
- **Schema database** — tidak ada migrasi baru. Semua field yang dipakai sudah ada.

---

## File yang Terdampak

| File | Aksi |
|------|------|
| `app/Services/EventHonorService.php` | **BARU** |
| `app/Http/Controllers/EventController.php` | Inject + panggil service di `complete()` |
| `database/seeders/PayrollConfigSeeder.php` | Tambah entry `H_PENDAMPING` |
| `resources/views/events/show.blade.php` | Tampilkan flash message hasil proses |

---

## Edge Cases

| Kasus | Penanganan |
|-------|-----------|
| Guru pendamping tidak ada (`accompanying_teacher_id` semua null) | Return `slips_updated=0`, pesan informatif |
| Slip honor sudah PAID | Skip, catat ke `slips_skipped` |
| Slip honor belum ada untuk bulan itu | `firstOrCreate` dengan status DRAFT |
| Guru mendampingi di 2 event bulan yang sama | `+=` di `event_honor` — akumulatif, note di-append dengan `|` |
| `H_PENDAMPING` belum ada di payroll_configs | Fallback ke 250.000, proses tetap jalan |
| `processEventCompletion` dipanggil ulang (idempotency) | Event sudah COMPLETED — `EventController::complete()` guard sudah cek `isCompleted()` sebelum memanggil service |
