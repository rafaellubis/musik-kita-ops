# Desain: Diskon Item Invoice — Extension ke DENDA + Cap PERCENT 90%

**Tanggal**: 2026-05-22
**Status**: Approved
**Scope**: Extension fitur diskon yang sudah ada — bukan fitur baru

---

## Konteks

Infrastruktur diskon per item invoice sudah fully implemented:
- `DiscountService` + `DiscountController` + routes sudah ada
- Diskon disimpan sebagai child `InvoiceItem` dengan `item_code='DISKON'` dan `amount` negatif
- UI form sudah ada di `invoices/show.blade.php`

Yang belum ada:
1. Item DENDA dikecualikan dari diskon (2 kondisi `!== 'DENDA'` di view)
2. PERCENT bisa mencapai 100% — memungkinkan Admin bypass "Hapus Denda" (Owner-only)

---

## Keputusan Desain

### Siapa yang boleh memberi diskon DENDA?
**Owner dan Admin** — konsisten dengan diskon item lain.

### Batasan nilai diskon
| Tipe    | Rule                          | Alasan                                                      |
|---------|-------------------------------|-------------------------------------------------------------|
| NOMINAL | `0 < value < amount`          | Tidak berubah — tidak boleh menghapus item sepenuhnya       |
| PERCENT | `1 ≤ value ≤ 90`              | Cap 90% berlaku semua item; mencegah full waiver via PERCENT |

PERCENT 100% sengaja diblokir karena secara efektif sama dengan "Hapus Denda" yang merupakan aksi Owner-only.

### Interaksi dengan cron denda
Diskon diberikan tepat sebelum murid bayar — invoice langsung PAID setelahnya. Cron `applyLateFinesForMonth` tidak perlu dimodifikasi.

---

## Perubahan yang Diperlukan

### 1. `app/Services/DiscountService.php`

Ubah guard validasi PERCENT:

```php
// Sebelum:
if ($value < 1 || $value > 100) {
    throw new \InvalidArgumentException('Persentase diskon harus antara 1 dan 100.');
}

// Sesudah:
if ($value < 1 || $value > 90) {
    throw new \InvalidArgumentException('Persentase diskon maksimal 90% dari harga item.');
}
```

### 2. `resources/views/invoices/show.blade.php`

**Perubahan A** — hapus exclusion DENDA di 2 tempat identik:

```php
// Sebelum:
@if($canDiscount && $item->item_code !== 'DENDA')

// Sesudah:
@if($canDiscount)
```

**Perubahan B** — update Alpine.js max PERCENT:

```js
// Sebelum:
:max="type === 'PERCENT' ? 100 : itemAmount - 1"

// Sesudah:
:max="type === 'PERCENT' ? 90 : itemAmount - 1"
```

### 3. `tests/Feature/DiscountTest.php`

**Update 1 test lama:**

```php
// test_apply_diskon_persen_gagal_jika_lebih_dari_100
// → rename ke test_apply_diskon_persen_gagal_jika_lebih_dari_90
// → ganti angka 101 → 91
```

**Tambah 5 test baru:**

| Test | Skenario |
|------|----------|
| `test_apply_diskon_persen_gagal_jika_lebih_dari_90` | PERCENT 91 → exception |
| `test_apply_diskon_persen_tepat_90_berhasil` | PERCENT 90 → diterima, amount = -intdiv(370000×90,100) |
| `test_apply_diskon_nominal_pada_denda_berhasil` | DENDA item, nominal Rp 5.000 → berhasil |
| `test_apply_diskon_persen_pada_denda_berhasil` | DENDA item, PERCENT 50 → berhasil |
| `test_apply_diskon_nominal_gagal_jika_sama_dengan_amount_denda` | NOMINAL = amount DENDA → exception |

Fixture DENDA dibuat **inline di tiap test DENDA** (bukan di `setUp()`), agar tidak mengubah `total_amount` invoice dan merusak assertion test-test lama:

```php
// Pola yang dipakai di tiap test DENDA:
$dendaItem = InvoiceItem::create([
    'invoice_id'  => $this->invoice->id,
    'item_code'   => 'DENDA',
    'description' => 'Denda keterlambatan (3 hari × Rp 5.000)',
    'amount'      => 15000,
]);
$this->invoice->update(['total_amount' => 370000 + 15000]); // sync total
```

---

## Yang TIDAK Berubah

- `StoreDiscountRequest` — validasi `min:1` tetap; batas atas dijaga di `DiscountService` (konsisten dengan pola yang sudah ada)
- Skema database — tidak ada migrasi baru
- Routes — tidak ada route baru
- `DiscountController` — tidak ada perubahan
- "Hapus Denda" (Owner-only) — tetap ada sebagai jalur hapus total

---

## Ringkasan File yang Diubah

```
app/Services/DiscountService.php          ← 1 baris validasi PERCENT
resources/views/invoices/show.blade.php   ← hapus 2 kondisi + update Alpine max
tests/Feature/DiscountTest.php            ← rename 1 test + tambah 5 test baru
```
