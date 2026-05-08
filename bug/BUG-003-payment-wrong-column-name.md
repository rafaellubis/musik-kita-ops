# BUG-003: Nama Kolom Salah di Query Payment (payment_method vs method)

**Status:** FIXED
**Modul:** M07 — Pengeluaran & Kas
**File:** `app/Http/Controllers/ExpenseController.php`
**Ditemukan:** 2026-05-08

---

## Deskripsi

Halaman `/expenses` (index) crash dengan error SQL column not found ketika
mencoba memuat data petty cash.

## Error

```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'payment_method'
in 'where clause'
SQL: select sum(`amount`) as aggregate from `payments`
     where `payment_method` = CASH and `voided_at` is null
```

## Root Cause

Kolom metode pembayaran di tabel `payments` bernama **`method`**, bukan
`payment_method`. Tapi di `ExpenseController::index()`, query petty cash
menggunakan nama kolom yang salah:

```php
// SALAH
Payment::where('payment_method', 'CASH')
```

Nama kolom `method` sudah konsisten di model Payment:
```php
// app/Models/Payment.php
public const METHOD_CASH = 'CASH';
// fillable menggunakan 'method'
```

## Fix

Ganti semua `'payment_method'` menjadi `'method'` di query Payment dalam
`ExpenseController::index()`:

```php
// BENAR
$kasmasukHariIni = Payment::where('method', 'CASH')
$kasmasukBulan   = Payment::where('method', 'CASH')
```

## Pelajaran

Sebelum query kolom pada model lain, cek `$fillable` atau migration-nya
terlebih dulu. Nama kolom di tabel `payments` sengaja dibuat pendek (`method`)
bukan verbose (`payment_method`) agar konsisten dengan enum yang juga pendek.
