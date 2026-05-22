# Diskon DENDA + Cap PERCENT 90% — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Izinkan diskon pada item DENDA di invoice, dan batasi diskon bertipe PERCENT maksimal 90% untuk semua item.

**Architecture:** Perubahan minimal pada infrastruktur yang sudah berjalan — hapus 2 kondisi exclusion DENDA di view, update 1 guard validasi PERCENT di DiscountService. Tidak ada migrasi, route baru, atau controller baru.

**Tech Stack:** Laravel 11, PHP 8.3, Alpine.js, Blade, PHPUnit

---

## File Map

| File | Aksi | Yang berubah |
|------|------|--------------|
| `app/Services/DiscountService.php` | Modify | Guard PERCENT: `> 100` → `> 90`, pesan error diupdate |
| `resources/views/invoices/show.blade.php` | Modify | Hapus 2 kondisi `!== 'DENDA'`, Alpine max 100 → 90 |
| `tests/Feature/DiscountTest.php` | Modify | Hapus 1 test lama yang redundan, tambah 5 test baru |

---

### Task 1: Cap PERCENT 90% di DiscountService (TDD)

**Files:**
- Modify: `tests/Feature/DiscountTest.php`
- Modify: `app/Services/DiscountService.php`

- [ ] **Step 1: Tambah failing test untuk PERCENT > 90**

Buka `tests/Feature/DiscountTest.php`. Tambahkan method baru setelah `test_apply_diskon_persen_gagal_jika_lebih_dari_100`:

```php
public function test_apply_diskon_persen_gagal_jika_lebih_dari_90(): void
{
    $this->actingAs($this->owner);
    $this->expectException(\InvalidArgumentException::class);
    $this->service->applyDiscount(
        $this->sppItem,
        InvoiceItem::DISCOUNT_TYPE_PERCENT,
        91,
        'Test boundary',
        $this->owner,
    );
}
```

- [ ] **Step 2: Jalankan test baru, pastikan FAIL**

```bash
php artisan test --filter=test_apply_diskon_persen_gagal_jika_lebih_dari_90
```

Expected: **FAIL** — "Failed asserting that exception of type InvalidArgumentException is thrown."
(Nilai 91 masih diterima oleh validasi saat ini yang batas atasnya 100)

- [ ] **Step 3: Update DiscountService — ubah cap PERCENT dari 100 ke 90**

Buka `app/Services/DiscountService.php`, cari blok `DISCOUNT_TYPE_PERCENT` di method `applyDiscount` (~baris 52). Ubah:

```php
// Sebelum:
} elseif ($type === InvoiceItem::DISCOUNT_TYPE_PERCENT) {
    if ($value < 1 || $value > 100) {
        throw new \InvalidArgumentException('Persentase diskon harus antara 1 dan 100.');
    }
}

// Sesudah:
} elseif ($type === InvoiceItem::DISCOUNT_TYPE_PERCENT) {
    if ($value < 1 || $value > 90) {
        throw new \InvalidArgumentException('Persentase diskon maksimal 90% dari harga item.');
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan PASS**

```bash
php artisan test --filter=test_apply_diskon_persen_gagal_jika_lebih_dari_90
```

Expected: **PASS**

- [ ] **Step 5: Hapus test lama yang redundan + tambah boundary test**

Di `tests/Feature/DiscountTest.php`, lakukan 2 perubahan:

**A) Hapus method `test_apply_diskon_persen_gagal_jika_lebih_dari_100` seluruhnya** — sudah digantikan oleh `test_apply_diskon_persen_gagal_jika_lebih_dari_90` di atas.

**B) Tambahkan boundary test** tepat setelah test yang baru ditambahkan:

```php
public function test_apply_diskon_persen_tepat_90_berhasil(): void
{
    $this->actingAs($this->owner);

    $discountItem = $this->service->applyDiscount(
        $this->sppItem,
        InvoiceItem::DISCOUNT_TYPE_PERCENT,
        90,
        'Diskon 90%',
        $this->owner,
    );

    // intdiv(370000 * 90, 100) = 333000
    $this->assertEquals(-333000, $discountItem->amount);
    $this->invoice->refresh();
    $this->assertEquals(37000, $this->invoice->total_amount);
}
```

- [ ] **Step 6: Jalankan seluruh DiscountTest**

```bash
php artisan test tests/Feature/DiscountTest.php
```

Expected: semua PASS (total test net +1: 1 dihapus, 2 ditambahkan)

- [ ] **Step 7: Commit**

```bash
git add app/Services/DiscountService.php tests/Feature/DiscountTest.php
git commit -m "M05: Cap diskon PERCENT maksimal 90% — semua item invoice"
```

---

### Task 2: Test untuk diskon item DENDA

**Files:**
- Modify: `tests/Feature/DiscountTest.php`

> `DiscountService` tidak pernah memblokir item DENDA secara eksplisit, jadi test-test ini **langsung PASS tanpa perubahan service**. Tujuannya mendokumentasikan perilaku dan mengunci agar tidak regresi.

- [ ] **Step 1: Tambah 3 test DENDA di akhir class DiscountTest**

```php
public function test_apply_diskon_nominal_pada_denda_berhasil(): void
{
    $this->actingAs($this->owner);

    // Dibuat inline — tidak di setUp() agar tidak merusak total invoice test lain
    $dendaItem = InvoiceItem::create([
        'invoice_id'  => $this->invoice->id,
        'item_code'   => 'DENDA',
        'description' => 'Denda keterlambatan (3 hari × Rp 5.000)',
        'amount'      => 15000,
    ]);
    $this->invoice->update(['total_amount' => 370000 + 15000]);

    $discountItem = $this->service->applyDiscount(
        $dendaItem,
        InvoiceItem::DISCOUNT_TYPE_NOMINAL,
        5000,
        'Diskon denda — konfirmasi terlambat masuk sistem',
        $this->owner,
    );

    $this->assertEquals('DISKON', $discountItem->item_code);
    $this->assertEquals(-5000, $discountItem->amount);
    $this->assertEquals($dendaItem->id, $discountItem->parent_item_id);

    $this->invoice->refresh();
    // total = sppItem(370000) + dendaItem(15000) + discountItem(-5000) = 380000
    $this->assertEquals(380000, $this->invoice->total_amount);
}

public function test_apply_diskon_persen_pada_denda_berhasil(): void
{
    $this->actingAs($this->owner);

    $dendaItem = InvoiceItem::create([
        'invoice_id'  => $this->invoice->id,
        'item_code'   => 'DENDA',
        'description' => 'Denda keterlambatan (3 hari × Rp 5.000)',
        'amount'      => 15000,
    ]);
    $this->invoice->update(['total_amount' => 370000 + 15000]);

    $discountItem = $this->service->applyDiscount(
        $dendaItem,
        InvoiceItem::DISCOUNT_TYPE_PERCENT,
        50,
        'Diskon denda 50%',
        $this->owner,
    );

    // intdiv(15000 * 50, 100) = 7500
    $this->assertEquals(-7500, $discountItem->amount);

    $this->invoice->refresh();
    // total = 370000 + 15000 - 7500 = 377500
    $this->assertEquals(377500, $this->invoice->total_amount);
}

public function test_apply_diskon_nominal_gagal_jika_sama_dengan_amount_denda(): void
{
    $this->actingAs($this->owner);

    $dendaItem = InvoiceItem::create([
        'invoice_id'  => $this->invoice->id,
        'item_code'   => 'DENDA',
        'description' => 'Denda keterlambatan (3 hari × Rp 5.000)',
        'amount'      => 15000,
    ]);
    $this->invoice->update(['total_amount' => 370000 + 15000]);

    // Diskon nominal = amount denda (100%) → harus ditolak
    $this->expectException(\InvalidArgumentException::class);
    $this->service->applyDiscount(
        $dendaItem,
        InvoiceItem::DISCOUNT_TYPE_NOMINAL,
        15000,
        'Test full waiver via nominal',
        $this->owner,
    );
}
```

- [ ] **Step 2: Jalankan 3 test baru**

```bash
php artisan test --filter="test_apply_diskon_nominal_pada_denda_berhasil|test_apply_diskon_persen_pada_denda_berhasil|test_apply_diskon_nominal_gagal_jika_sama_dengan_amount_denda"
```

Expected: **semua 3 PASS** — service tidak memblokir DENDA.

- [ ] **Step 3: Jalankan seluruh DiscountTest**

```bash
php artisan test tests/Feature/DiscountTest.php
```

Expected: semua PASS, tidak ada regresi.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/DiscountTest.php
git commit -m "M05: Tambah test diskon item DENDA — nominal, persen, dan full-waiver guard"
```

---

### Task 3: Update view — aktifkan diskon DENDA + update Alpine max

**Files:**
- Modify: `resources/views/invoices/show.blade.php`

- [ ] **Step 1: Hapus 2 kondisi exclusion DENDA**

Di `resources/views/invoices/show.blade.php`, ada 2 baris identik. Keduanya harus diubah:

**Kemunculan pertama** (~baris 361) — kondisi tombol "Beri Diskon":
```php
// Sebelum:
@if($canDiscount && $item->item_code !== 'DENDA')

// Sesudah:
@if($canDiscount)
```

**Kemunculan kedua** (~baris 375) — kondisi form diskon inline:
```php
// Sebelum:
@if($canDiscount && $item->item_code !== 'DENDA')

// Sesudah:
@if($canDiscount)
```

- [ ] **Step 2: Update Alpine.js max PERCENT dari 100 ke 90**

Di file yang sama, cari baris Alpine `:max` (~baris 401):
```js
// Sebelum:
:max="type === 'PERCENT' ? 100 : itemAmount - 1"

// Sesudah:
:max="type === 'PERCENT' ? 90 : itemAmount - 1"
```

- [ ] **Step 3: Jalankan full test suite untuk konfirmasi tidak ada regresi**

```bash
php artisan test
```

Expected: seluruh test suite PASS. Catat jumlah total test yang lulus.

- [ ] **Step 4: Commit**

```bash
git add resources/views/invoices/show.blade.php
git commit -m "M05: Aktifkan diskon item DENDA di view + cap Alpine PERCENT max 90%"
```
