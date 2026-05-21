# Desain Fitur Diskon — Musik KITA Ops
**Tanggal:** 2026-05-22
**Status:** Disetujui, siap implementasi

---

## Ringkasan

Menambahkan kemampuan memberikan diskon per item pada invoice. Diskon bersifat sekali pakai (tidak berulang otomatis), bisa nominal tetap atau persentase, wajib disertai alasan, dan ditampilkan sebagai baris tersendiri di bawah item yang didiskon.

---

## Kebutuhan (Requirements)

| # | Kebutuhan |
|---|-----------|
| D-1 | Diskon diberikan **per item** di invoice, bukan per total invoice |
| D-2 | Tipe diskon: **NOMINAL** (Rp) atau **PERCENT** (%) |
| D-3 | Alasan diskon **wajib** diisi (minimal 3 karakter) |
| D-4 | Diskon bisa ditambah/diubah/dihapus selama status invoice **UNPAID atau PARTIAL** |
| D-5 | Diskon tampil sebagai **baris tersendiri** (amount negatif) di bawah item induknya |
| D-6 | Maksimal **1 diskon per item** |
| D-7 | Role yang boleh memberi diskon: **Admin dan Owner** |
| D-8 | Setiap aksi diskon dicatat di **audit log** |

---

## Pendekatan: InvoiceItem Negatif dengan `parent_item_id`

Diskon disimpan sebagai `InvoiceItem` baru dengan:
- `item_code = 'DISKON'`
- `amount` bernilai **negatif** (mis. `-50000`)
- `parent_item_id` menunjuk ke item yang didiskon

Keunggulan: `recalcStatus()` di `InvoiceService` sudah menghitung `sum(amount)` dari seluruh items — diskon negatif langsung mengurangi total tanpa perubahan logika kalkulasi.

---

## Bagian 1: Database

### Migration — tambah kolom ke `invoice_items`

```php
// Migration baru: add_discount_fields_to_invoice_items_table
$table->unsignedBigInteger('parent_item_id')->nullable()->after('invoice_id');
$table->foreign('parent_item_id')
      ->references('id')->on('invoice_items')
      ->nullOnDelete();
$table->string('discount_type', 10)->nullable()->after('metadata');   // NOMINAL | PERCENT
$table->integer('discount_value')->nullable()->after('discount_type'); // 50000 atau 10 (untuk 10%)
$table->string('discount_reason', 500)->nullable()->after('discount_value');
```

**Catatan:** Constraint "max 1 diskon per item" tidak di-enforce di database (unique partial index kompleks karena NULL). Dijaga di `DiscountService`.

### Kolom baru di `invoice_items`

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `parent_item_id` | FK nullable | Menunjuk ke item induk yang didiskon |
| `discount_type` | string nullable | `NOMINAL` atau `PERCENT` |
| `discount_value` | integer nullable | Nilai diskon asli (Rp atau %) sebelum dihitung |
| `discount_reason` | string nullable | Alasan wajib diisi oleh Admin/Owner |

---

## Bagian 2: Model

### `InvoiceItem` — tambahan

```php
// Konstanta
public const DISCOUNT_TYPE_NOMINAL = 'NOMINAL';
public const DISCOUNT_TYPE_PERCENT = 'PERCENT';

// Helper
public function isDiscount(): bool
{
    return $this->item_code === 'DISKON';
}

// Relasi: item induk punya satu item diskon
public function discountItem(): HasOne
{
    return $this->hasOne(InvoiceItem::class, 'parent_item_id');
}

// Relasi: item diskon merujuk ke induknya
public function parentItem(): BelongsTo
{
    return $this->belongsTo(InvoiceItem::class, 'parent_item_id');
}
```

Tambahkan `discount_type`, `discount_value`, `discount_reason`, `parent_item_id` ke `$fillable`.

---

## Bagian 3: Service Layer

### `DiscountService` (class baru — `App\Services\DiscountService`)

**Method `applyDiscount()`**

```
Input:
  - InvoiceItem $item      → item yang akan didiskon (bukan item DISKON)
  - string $type           → NOMINAL | PERCENT
  - int $value             → nilai diskon (Rp atau %)
  - string $reason         → alasan wajib
  - User $by               → user yang memberi diskon

Validasi:
  1. Invoice status bukan PAID dan bukan VOID
  2. $item bukan item DISKON itu sendiri (parent_item_id IS NULL)
  3. Untuk NOMINAL: value > 0 dan value < $item->amount
  4. Untuk PERCENT: value antara 1–100

Kalkulasi amount negatif:
  - NOMINAL → -$value
  - PERCENT → -intdiv($item->amount * $value, 100)

Logika simpan:
  - Cek apakah sudah ada discountItem() → jika ada, UPDATE
  - Jika belum ada, CREATE baru
  - Panggil InvoiceService::recalcStatus($invoice)
  - Catat audit log

Output: InvoiceItem (item DISKON yang baru/diupdate)
```

**Method `removeDiscount()`**

```
Input:
  - InvoiceItem $discountItem  → item dengan item_code = 'DISKON'
  - User $by                   → user yang menghapus

Validasi:
  1. Invoice status bukan PAID dan bukan VOID
  2. $discountItem->item_code === 'DISKON'

Logika:
  - Hapus $discountItem
  - Panggil InvoiceService::recalcStatus($invoice)
  - Catat audit log
```

### `InvoiceService` — tidak ada perubahan

`recalcStatus()` sudah benar. Amount negatif dari item DISKON otomatis mengurangi total saat `sum(amount)`. Tidak ada yang perlu diubah.

---

## Bagian 4: Controller & Routes

### `DiscountController` (class baru)

```
POST   /invoice-items/{item}/discount  → store()    (simpan/update diskon)
DELETE /invoice-items/{item}/discount  → destroy()  (hapus diskon)
```

Middleware route group: `auth`, `role:Owner|Admin`.

**`store()`:**
1. Load `$item` dengan `$item->invoice` (eager load invoice)
2. Validasi request: `discount_type`, `discount_value`, `discount_reason`
3. Panggil `DiscountService::applyDiscount()`
4. Redirect kembali ke `invoices.show` dengan flash success

**`destroy()`:**
1. Load item DISKON dengan invoice-nya
2. Panggil `DiscountService::removeDiscount()`
3. Redirect kembali dengan flash success

### Form Request: `StoreDiscountRequest`

```php
'discount_type'   => ['required', 'in:NOMINAL,PERCENT'],
'discount_value'  => ['required', 'integer', 'min:1'],
'discount_reason' => ['required', 'string', 'min:3', 'max:500'],
```

Validasi bisnis (value vs amount, percent range) dilakukan di `DiscountService`.

---

## Bagian 5: UI — `invoices/show.blade.php`

### Tabel item — render dengan grouping

Items di-filter sebelum render: **item DISKON tidak masuk loop utama** — mereka ditampilkan sebagai sub-baris di bawah item induknya.

```php
// Di controller InvoiceController@show
$items = $invoice->items()
    ->whereNull('parent_item_id')  // hanya item induk
    ->with('discountItem')         // eager load diskon
    ->get();
```

Contoh tampilan tabel:

```
Kode    Deskripsi                              Jumlah      Tipe    Aksi
SPP     SPP Reguler Piano – Juni 2026          Rp 370.000  Sistem  [Beri Diskon]
  ↳     Diskon ulang tahun – 10%              –Rp 37.000  Diskon  [Hapus]
REG     Registrasi murid baru                  Rp 250.000  Sistem  [Beri Diskon]
DENDA   Denda keterlambatan (3 hari × 5.000)   Rp  15.000  Sistem  —
──────────────────────────────────────────────────────────────────
Total                                           Rp 598.000
```

- Item DISKON: teks jumlah merah, badge amber/gold, tidak punya tombol "Beri Diskon"
- Item DENDA: tidak dapat diskon (dikecualikan di controller/UI)
- Item DISKON itu sendiri: tidak dapat diskon

### Form Beri/Edit Diskon — inline expand (Alpine.js)

Klik "Beri Diskon" pada satu item → panel expand inline di bawah baris tersebut:

```
┌─ Beri Diskon untuk: SPP Reguler Piano ─────────────────────────┐
│  Tipe:  ○ Nominal (Rp)   ○ Persentase (%)                      │
│  Nilai: [________] → Preview: –Rp 37.000  (dari Rp 370.000)    │
│  Alasan: [_________________________________] (wajib, min 3 kar)│
│  [Simpan Diskon]  [Batal]                                      │
└────────────────────────────────────────────────────────────────┘
```

Preview kalkulasi dihitung real-time oleh Alpine.js (tanpa request server). Setiap item punya state Alpine sendiri (`x-data` per baris).

Jika item sudah punya diskon → tombol berubah jadi "Edit Diskon", form pre-fill dengan nilai saat ini.

### Aturan tampil tombol "Beri/Edit Diskon"

| Kondisi | Tampil? |
|---------|---------|
| Invoice UNPAID atau PARTIAL | Ya |
| Invoice PAID atau VOID | Tidak |
| Item adalah DISKON atau DENDA | Tidak |
| Role Owner atau Admin | Ya |

---

## Bagian 6: Print View (`invoices/print.blade.php`)

Item DISKON tampil sebagai baris tersendiri dengan tanda `–` di depan jumlah, di bawah item induknya. Gunakan grouping yang sama seperti show view. Tidak ada perubahan struktur cetak lainnya.

---

## Bagian 7: Audit Log

Setiap aksi dicatat:

| Aksi | `action` | `entity_type` | `new_values` |
|------|----------|---------------|--------------|
| Beri diskon | `UPDATE` | `Invoice` | `{item_id, discount_type, discount_value, discount_reason, calculated_amount}` |
| Edit diskon | `UPDATE` | `Invoice` | `{item_id, old: {...}, new: {...}}` |
| Hapus diskon | `UPDATE` | `Invoice` | `{item_id, removed_discount: {...}}` |

---

## Yang Tidak Termasuk Scope Ini

- Diskon otomatis/berulang per murid (bisa jadi fitur Fase 2)
- Diskon untuk item DENDA (dikecualikan by design — denda ada flow sendiri via "Hapus Denda")
- Approval workflow (diskon langsung berlaku setelah Admin/Owner simpan)
- Batasan maksimum total diskon per invoice

---

## Urutan Implementasi

1. Migration `add_discount_fields_to_invoice_items_table`
2. Update `InvoiceItem` model (fillable, konstanta, relasi, helper)
3. Buat `DiscountService`
4. Buat `StoreDiscountRequest`
5. Buat `DiscountController`
6. Daftarkan route di `web.php`
7. Update `InvoiceController@show` (pass items terkelompok ke view)
8. Update `invoices/show.blade.php` (tabel + form diskon Alpine)
9. Update `invoices/print.blade.php` (grouping item DISKON)
