# BUG-002 — `orderByRelation()` Bukan Method Eloquent yang Valid

**Status:** FIXED
**Tanggal ditemukan:** 2026-05-08
**Ditemukan saat:** Testing M06 — membuka halaman `/honors`
**Error:** `BadMethodCallException: Call to undefined method ...Builder::orderByRelation()`
**Diperbaiki di commit:** `fa1bf23`

---

## Deskripsi Bug

Di `HonorController::index()`, query untuk mengurutkan slip honor berdasarkan nama guru menggunakan:

```php
// KODE SALAH
$query = HonorSlip::query()
    ->with('teacher')
    ->forMonth($year, $month)
    ->orderBy('status')
    ->orderByRelation('teacher', 'name');  // ← method ini tidak ada
```

Method `orderByRelation()` **tidak ada** di Eloquent Laravel.
Halaman `/honors` langsung 500 Internal Server Error setiap kali dibuka.

---

## Dampak

Halaman index slip honor tidak bisa dibuka sama sekali (`500 Internal Server Error`).
Semua halaman yang bergantung pada `/honors` ikut tidak bisa diakses.

---

## Root Cause

Kesalahan penulisan — mencoba mempersingkat sintaks ordering by relationship column
dengan method yang tidak ada. Mungkin terinspirasi dari syntax ORM lain seperti
Eloquent Extensions atau syntax yang pernah ada di versi lama.

---

## Fix

Ganti dengan `join()` eksplisit ke tabel `teachers`, lalu `orderBy` kolom langsung:

```php
// KODE BENAR — di HonorController::index()
$query = HonorSlip::query()
    ->join('teachers', 'teacher_honor_slips.teacher_id', '=', 'teachers.id')
    ->select('teacher_honor_slips.*')   // pastikan kolom slip yang di-select, bukan semua
    ->with('teacher')
    ->forMonth($year, $month)
    ->orderBy('status')
    ->orderBy('teachers.name');
```

Catatan: `->select('teacher_honor_slips.*')` wajib ditambahkan setelah `join()`.
Tanpanya, kolom `id` dari tabel `teachers` akan override kolom `id` dari `teacher_honor_slips`
dan menyebabkan data guru dimuat dengan ID yang salah.

---

## Cara Hindari di Masa Depan

Untuk ordering berdasarkan kolom di related table, pilih salah satu:

```php
// Opsi 1: join() + orderBy() — paling umum, cocok untuk pagination
HonorSlip::join('teachers', 'teacher_honor_slips.teacher_id', '=', 'teachers.id')
    ->select('teacher_honor_slips.*')
    ->orderBy('teachers.name');

// Opsi 2: withAggregate() (Laravel 8.42+) — tanpa join, untuk kolom non-aggregate
HonorSlip::withAggregate('teacher', 'name')
    ->orderBy('teacher_name');

// Opsi 3: sortBy() pada Collection — hanya cocok kalau TIDAK pakai paginate()
HonorSlip::with('teacher')->get()->sortBy('teacher.name');
```

File terdampak: `app/Http/Controllers/HonorController.php`
