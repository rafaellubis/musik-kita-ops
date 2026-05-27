# Spec: Pisah Metode Bayar di Laporan Keuangan

**Tanggal:** 2026-05-28
**Status:** Approved
**Scope:** ReportController.php + finance.blade.php

---

## Latar Belakang

Laporan keuangan (`/reports/finance`) saat ini menghitung `$revenueTransfer` sebagai
`$totalRevenue - $revenueCash`. Akibatnya QRIS dan DEBIT tercampur ke dalam label
"Transfer", padahal sistem sudah mendukung 4 metode pembayaran: CASH, TRANSFER, QRIS, DEBIT.

---

## Tujuan

Menampilkan breakdown 4 metode pembayaran secara terpisah di section
"Pendapatan per Jenis" pada halaman laporan keuangan bulanan.

---

## Perubahan

### 1. ReportController.php

**File:** `app/Http/Controllers/ReportController.php`
**Method:** `finance()`

**Hapus** variabel lama:
```php
$revenueCash     = Payment::...->where('method', 'CASH')->sum('amount');
$revenueTransfer = $totalRevenue - $revenueCash;
```

**Ganti** dengan satu query groupBy:
```php
$revenueByMethod = Payment::whereNull('voided_at')
    ->whereYear('payment_date', $year)
    ->whereMonth('payment_date', $month)
    ->selectRaw('method, SUM(amount) as total')
    ->groupBy('method')
    ->pluck('total', 'method');
```

`$revenueByMethod` adalah Collection keyed by method string.
Contoh hasil: `['CASH' => 2100000, 'QRIS' => 450000]` (method tanpa transaksi tidak muncul).

**Update** `compact()` di return: ganti `'revenueCash', 'revenueTransfer'`
dengan `'revenueByMethod'`.

---

### 2. finance.blade.php

**File:** `resources/views/reports/finance.blade.php`
**Section:** "Pendapatan per Jenis" (~baris 99–107)

**Hapus** 2 baris lama (Cash, Transfer).

**Ganti** dengan 4 baris statis berurutan:

| Label   | Nilai                                    |
|---------|------------------------------------------|
| Cash    | `$revenueByMethod['CASH'] ?? 0`          |
| Transfer| `$revenueByMethod['TRANSFER'] ?? 0`      |
| QRIS    | `$revenueByMethod['QRIS'] ?? 0`          |
| Debit   | `$revenueByMethod['DEBIT'] ?? 0`         |

Method dengan nilai 0 tetap ditampilkan agar format laporan konsisten setiap bulan.

---

## Yang Tidak Berubah

- `$totalRevenue` — tetap dari `Payment::sum('amount')`, tidak diubah
- `$revenueByType` (rincian per item_code SPP/DENDA/dll) — tidak diubah
- `$invoiceStats` — tidak diubah
- Semua logika honor, pengeluaran, P&L — tidak diubah
- Schema database — tidak ada migration
- Model Payment — tidak ada perubahan

---

## Edge Cases

- Bulan tanpa transaksi QRIS/DEBIT: `$revenueByMethod` tidak punya key itu,
  view handle dengan `?? 0` — tampil Rp 0.
- Semua method nol (bulan kosong): 4 baris tetap tampil dengan Rp 0 semua.
- Payment void: sudah difilter `whereNull('voided_at')` di query.

---

## Testing Manual

1. Buka `/reports/finance` di bulan yang ada transaksi QRIS atau DEBIT.
2. Pastikan 4 baris muncul (Cash, Transfer, QRIS, Debit).
3. Jumlah keempat baris = Total Pendapatan di P&L.
4. Buka bulan tanpa transaksi QRIS — pastikan QRIS tetap tampil dengan Rp 0.
