# Fitur Diskon Per Item Invoice — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambah kemampuan memberi diskon per item invoice (nominal/persentase), tampil sebagai baris negatif terpisah di bawah item induknya, bisa diubah/dihapus selama invoice belum PAID.

**Architecture:** Diskon disimpan sebagai `InvoiceItem` baru dengan `item_code='DISKON'` dan `amount` negatif, terhubung ke item induk via kolom `parent_item_id`. `InvoiceService::recalcStatus()` tidak diubah — amount negatif otomatis mengurangi total saat `sum(amount)`. Maks 1 diskon per item dijaga di `DiscountService`, bukan di database constraint.

**Tech Stack:** Laravel 11, PHP 8.3, MySQL (prod) / SQLite in-memory (test), Blade + Alpine.js, Spatie Permission v6, Tailwind CSS 3.x

---

## File Map

| File | Status | Tanggung Jawab |
|------|--------|----------------|
| `database/migrations/2026_05_22_..._add_discount_fields_to_invoice_items_table.php` | Create | Tambah 4 kolom baru ke `invoice_items` |
| `app/Models/InvoiceItem.php` | Modify | Tambah fillable, konstanta, relasi `discountItem`/`parentItem`, helper `isDiscount()` |
| `app/Services/DiscountService.php` | Create | Logic `applyDiscount()` + `removeDiscount()` |
| `app/Http/Requests/StoreDiscountRequest.php` | Create | Validasi input form diskon |
| `app/Http/Controllers/DiscountController.php` | Create | `store()` + `destroy()` endpoint |
| `routes/web.php` | Modify | 2 route baru dalam middleware `role:Owner\|Admin` |
| `app/Http/Controllers/InvoiceController.php` | Modify | `show()` — filter parent items + eager load `discountItem`; `print()` — sama |
| `resources/views/invoices/show.blade.php` | Modify | Tabel item dengan sub-baris diskon + form Alpine.js inline |
| `resources/views/invoices/print.blade.php` | Modify | Sub-baris diskon di cetak |
| `tests/Feature/DiscountTest.php` | Create | Test service (unit-style) + HTTP endpoint |

---

## Task 1: Migration — Tambah Kolom Diskon ke `invoice_items`

**Files:**
- Create: `database/migrations/2026_05_22_000000_add_discount_fields_to_invoice_items_table.php`

- [ ] **Step 1: Buat file migration via Artisan**

```bash
php artisan make:migration add_discount_fields_to_invoice_items_table
```

- [ ] **Step 2: Isi konten migration**

Buka file migration yang baru dibuat (ada timestamp hari ini di namanya). Ganti seluruh isinya:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            // FK ke item induk — null berarti ini bukan item diskon
            $table->unsignedBigInteger('parent_item_id')
                  ->nullable()
                  ->after('invoice_id');
            $table->foreign('parent_item_id')
                  ->references('id')
                  ->on('invoice_items')
                  ->nullOnDelete();

            // Kolom diskon — hanya diisi oleh item dengan item_code='DISKON'
            $table->string('discount_type', 10)->nullable()->after('metadata');
            $table->integer('discount_value')->nullable()->after('discount_type');
            $table->string('discount_reason', 500)->nullable()->after('discount_value');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['parent_item_id']);
            $table->dropColumn(['parent_item_id', 'discount_type', 'discount_value', 'discount_reason']);
        });
    }
};
```

- [ ] **Step 3: Jalankan migration**

```bash
php artisan migrate
```

Expected output berisi: `Migrating: 2026_05_22_000000_add_discount_fields_to_invoice_items_table` → `Migrated`.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/
git commit -m "DB: Migration tambah kolom diskon ke invoice_items"
```

---

## Task 2: Update Model `InvoiceItem`

**Files:**
- Modify: `app/Models/InvoiceItem.php`

- [ ] **Step 1: Ganti seluruh isi `app/Models/InvoiceItem.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id', 'invoice_component_id', 'added_by',
        'parent_item_id',
        'item_code', 'description', 'amount', 'metadata',
        'discount_type', 'discount_value', 'discount_reason',
    ];

    protected $casts = [
        'amount'         => 'integer',
        'metadata'       => 'array',
        'discount_value' => 'integer',
    ];

    // Tipe diskon
    public const DISCOUNT_TYPE_NOMINAL = 'NOMINAL';
    public const DISCOUNT_TYPE_PERCENT = 'PERCENT';

    // ============= HELPERS =============

    /** Item manual: added_by tidak null. Item sistem: added_by null. */
    public function isManual(): bool
    {
        return $this->added_by !== null;
    }

    /** Cek apakah item ini adalah item diskon (item_code = DISKON). */
    public function isDiscount(): bool
    {
        return $this->item_code === 'DISKON';
    }

    // ============= RELATIONSHIPS =============

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(InvoiceComponent::class, 'invoice_component_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'added_by');
    }

    /** Item induk yang didiskon oleh item ini (hanya berlaku jika isDiscount()). */
    public function parentItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class, 'parent_item_id');
    }

    /** Item diskon yang terikat ke item ini (satu item maks 1 diskon). */
    public function discountItem(): HasOne
    {
        return $this->hasOne(InvoiceItem::class, 'parent_item_id');
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Models/InvoiceItem.php
git commit -m "M05: Update InvoiceItem — tambah relasi discountItem, helper isDiscount, konstanta tipe"
```

---

## Task 3: `DiscountService` (TDD)

**Files:**
- Create: `tests/Feature/DiscountTest.php`
- Create: `app/Services/DiscountService.php`

- [ ] **Step 1: Tulis test yang gagal — buat `tests/Feature/DiscountTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Student;
use App\Models\User;
use App\Services\DiscountService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DiscountTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Invoice $invoice;
    private InvoiceItem $sppItem;
    private DiscountService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);

        $this->owner = User::factory()->create()->assignRole('Owner');
        $student = Student::factory()->create(['status' => 'Aktif']);

        $this->invoice = Invoice::create([
            'invoice_number' => 'INV/2026/05/0001',
            'student_id'     => $student->id,
            'year'           => 2026,
            'month'          => 5,
            'total_amount'   => 370000,
            'paid_amount'    => 0,
            'status'         => Invoice::STATUS_UNPAID,
            'due_date'       => now()->addDays(10)->toDateString(),
            'issued_at'      => now()->toDateString(),
        ]);

        $this->sppItem = InvoiceItem::create([
            'invoice_id'  => $this->invoice->id,
            'item_code'   => 'SPP',
            'description' => 'SPP Reguler Piano',
            'amount'      => 370000,
        ]);

        $this->service = new DiscountService(new InvoiceService());
    }

    public function test_apply_diskon_nominal_berhasil(): void
    {
        $this->actingAs($this->owner);

        $discountItem = $this->service->applyDiscount(
            $this->sppItem,
            InvoiceItem::DISCOUNT_TYPE_NOMINAL,
            50000,
            'Diskon ulang tahun',
            $this->owner,
        );

        $this->assertEquals('DISKON', $discountItem->item_code);
        $this->assertEquals(-50000, $discountItem->amount);
        $this->assertEquals($this->sppItem->id, $discountItem->parent_item_id);
        $this->assertEquals('NOMINAL', $discountItem->discount_type);
        $this->assertEquals(50000, $discountItem->discount_value);
        $this->assertEquals('Diskon ulang tahun', $discountItem->discount_reason);

        $this->invoice->refresh();
        $this->assertEquals(320000, $this->invoice->total_amount);
    }

    public function test_apply_diskon_persen_berhasil(): void
    {
        $this->actingAs($this->owner);

        $discountItem = $this->service->applyDiscount(
            $this->sppItem,
            InvoiceItem::DISCOUNT_TYPE_PERCENT,
            10,
            'Diskon 10%',
            $this->owner,
        );

        // intdiv(370000 * 10, 100) = 37000
        $this->assertEquals(-37000, $discountItem->amount);
        $this->invoice->refresh();
        $this->assertEquals(333000, $this->invoice->total_amount);
    }

    public function test_apply_diskon_update_jika_sudah_ada(): void
    {
        $this->actingAs($this->owner);

        $this->service->applyDiscount($this->sppItem, InvoiceItem::DISCOUNT_TYPE_NOMINAL, 50000, 'Pertama', $this->owner);
        $this->service->applyDiscount($this->sppItem, InvoiceItem::DISCOUNT_TYPE_NOMINAL, 30000, 'Update', $this->owner);

        // Hanya boleh ada 1 item DISKON untuk sppItem
        $count = InvoiceItem::where('parent_item_id', $this->sppItem->id)->count();
        $this->assertEquals(1, $count);

        $this->invoice->refresh();
        $this->assertEquals(340000, $this->invoice->total_amount);
    }

    public function test_apply_diskon_gagal_jika_invoice_paid(): void
    {
        $this->actingAs($this->owner);
        $this->invoice->update(['status' => Invoice::STATUS_PAID]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->applyDiscount($this->sppItem, InvoiceItem::DISCOUNT_TYPE_NOMINAL, 50000, 'Test', $this->owner);
    }

    public function test_apply_diskon_gagal_jika_item_adalah_diskon(): void
    {
        $this->actingAs($this->owner);

        $existingDiscount = InvoiceItem::create([
            'invoice_id'      => $this->invoice->id,
            'parent_item_id'  => $this->sppItem->id,
            'item_code'       => 'DISKON',
            'description'     => 'Diskon test',
            'amount'          => -50000,
            'discount_type'   => 'NOMINAL',
            'discount_value'  => 50000,
            'discount_reason' => 'Test',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->applyDiscount($existingDiscount, InvoiceItem::DISCOUNT_TYPE_NOMINAL, 10000, 'Test', $this->owner);
    }

    public function test_apply_diskon_nominal_gagal_jika_value_lebih_besar_dari_amount(): void
    {
        $this->actingAs($this->owner);

        $this->expectException(\InvalidArgumentException::class);
        // 400000 >= 370000 (amount item) → harus ditolak
        $this->service->applyDiscount($this->sppItem, InvoiceItem::DISCOUNT_TYPE_NOMINAL, 400000, 'Test', $this->owner);
    }

    public function test_apply_diskon_persen_gagal_jika_lebih_dari_100(): void
    {
        $this->actingAs($this->owner);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->applyDiscount($this->sppItem, InvoiceItem::DISCOUNT_TYPE_PERCENT, 101, 'Test', $this->owner);
    }

    public function test_remove_diskon_berhasil(): void
    {
        $this->actingAs($this->owner);

        $discountItem = InvoiceItem::create([
            'invoice_id'      => $this->invoice->id,
            'parent_item_id'  => $this->sppItem->id,
            'item_code'       => 'DISKON',
            'description'     => 'Diskon ulang tahun',
            'amount'          => -50000,
            'discount_type'   => 'NOMINAL',
            'discount_value'  => 50000,
            'discount_reason' => 'Diskon ulang tahun',
            'added_by'        => $this->owner->id,
        ]);
        // Sync total agar sesuai kondisi setelah diskon
        $this->invoice->update(['total_amount' => 320000]);

        $this->service->removeDiscount($discountItem, $this->owner);

        $this->assertDatabaseMissing('invoice_items', ['id' => $discountItem->id]);
        $this->invoice->refresh();
        $this->assertEquals(370000, $this->invoice->total_amount);
    }

    public function test_remove_diskon_gagal_jika_invoice_paid(): void
    {
        $this->actingAs($this->owner);
        $this->invoice->update(['status' => Invoice::STATUS_PAID]);

        $discountItem = InvoiceItem::create([
            'invoice_id'      => $this->invoice->id,
            'parent_item_id'  => $this->sppItem->id,
            'item_code'       => 'DISKON',
            'description'     => 'Test',
            'amount'          => -50000,
            'discount_type'   => 'NOMINAL',
            'discount_value'  => 50000,
            'discount_reason' => 'Test',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->removeDiscount($discountItem, $this->owner);
    }

    public function test_apply_diskon_catat_audit_log(): void
    {
        $this->actingAs($this->owner);

        $this->service->applyDiscount($this->sppItem, InvoiceItem::DISCOUNT_TYPE_NOMINAL, 50000, 'Audit test', $this->owner);

        $this->assertDatabaseHas('audit_logs', [
            'action'      => AuditLog::ACTION_UPDATE,
            'entity_type' => 'Invoice',
            'entity_id'   => $this->invoice->id,
        ]);
    }

    // ===== HTTP Endpoint Tests =====

    public function test_http_store_diskon_berhasil_sebagai_admin(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');

        $response = $this->actingAs($admin)->post(
            route('invoice-items.discount.store', $this->sppItem->id),
            [
                'discount_type'   => 'NOMINAL',
                'discount_value'  => 50000,
                'discount_reason' => 'Diskon promosi bulan ini',
            ]
        );

        $response->assertRedirect(route('invoices.show', $this->invoice->id));
        $this->assertDatabaseHas('invoice_items', [
            'parent_item_id' => $this->sppItem->id,
            'item_code'      => 'DISKON',
            'amount'         => -50000,
        ]);
    }

    public function test_http_store_diskon_ditolak_untuk_auditor(): void
    {
        $auditor = User::factory()->create()->assignRole('Auditor');

        $response = $this->actingAs($auditor)->post(
            route('invoice-items.discount.store', $this->sppItem->id),
            [
                'discount_type'   => 'NOMINAL',
                'discount_value'  => 50000,
                'discount_reason' => 'Test unauthorized',
            ]
        );

        $response->assertForbidden();
    }

    public function test_http_destroy_diskon_berhasil(): void
    {
        $discountItem = InvoiceItem::create([
            'invoice_id'      => $this->invoice->id,
            'parent_item_id'  => $this->sppItem->id,
            'item_code'       => 'DISKON',
            'description'     => 'Diskon test',
            'amount'          => -50000,
            'discount_type'   => 'NOMINAL',
            'discount_value'  => 50000,
            'discount_reason' => 'Diskon test',
            'added_by'        => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner)->delete(
            route('invoice-items.discount.destroy', $this->sppItem->id)
        );

        $response->assertRedirect(route('invoices.show', $this->invoice->id));
        $this->assertDatabaseMissing('invoice_items', ['id' => $discountItem->id]);
    }
}
```

- [ ] **Step 2: Jalankan test — pastikan FAIL karena `DiscountService` belum ada**

```bash
php artisan test tests/Feature/DiscountTest.php
```

Expected: Error `Class "App\Services\DiscountService" not found` atau semua test merah.

- [ ] **Step 3: Buat `app/Services/DiscountService.php`**

```php
<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Logika bisnis diskon per item invoice (M05).
 *
 * Diskon disimpan sebagai InvoiceItem baru dengan item_code='DISKON',
 * amount negatif, dan parent_item_id menunjuk ke item yang didiskon.
 * Maks 1 diskon per item dijaga di service ini, bukan di database constraint.
 */
class DiscountService
{
    public function __construct(private InvoiceService $invoiceService) {}

    /**
     * Beri atau update diskon pada sebuah item invoice.
     * Idempotent: jika diskon sudah ada → diupdate. Jika belum → dibuat baru.
     *
     * @throws \InvalidArgumentException jika validasi bisnis gagal
     */
    public function applyDiscount(
        InvoiceItem $item,
        string $type,
        int $value,
        string $reason,
        User $by,
    ): InvoiceItem {
        $invoice = $item->invoice;

        if (in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_VOID])) {
            throw new \InvalidArgumentException('Diskon tidak bisa ditambahkan pada invoice yang sudah PAID atau VOID.');
        }

        if ($item->isDiscount()) {
            throw new \InvalidArgumentException('Tidak bisa memberi diskon pada item diskon.');
        }

        if ($type === InvoiceItem::DISCOUNT_TYPE_NOMINAL) {
            if ($value <= 0 || $value >= $item->amount) {
                throw new \InvalidArgumentException('Nilai diskon nominal harus lebih dari 0 dan kurang dari harga item.');
            }
        } elseif ($type === InvoiceItem::DISCOUNT_TYPE_PERCENT) {
            if ($value < 1 || $value > 100) {
                throw new \InvalidArgumentException('Persentase diskon harus antara 1 dan 100.');
            }
        } else {
            throw new \InvalidArgumentException('Tipe diskon tidak valid. Gunakan NOMINAL atau PERCENT.');
        }

        $calculatedAmount = $type === InvoiceItem::DISCOUNT_TYPE_NOMINAL
            ? -$value
            : -intdiv($item->amount * $value, 100);

        return DB::transaction(function () use ($item, $type, $value, $reason, $by, $calculatedAmount) {
            $existing = $item->discountItem()->first();

            $data = [
                'invoice_id'      => $item->invoice_id,
                'parent_item_id'  => $item->id,
                'item_code'       => 'DISKON',
                'description'     => $reason,
                'amount'          => $calculatedAmount,
                'discount_type'   => $type,
                'discount_value'  => $value,
                'discount_reason' => $reason,
                'added_by'        => $by->id,
            ];

            $oldValues = null;

            if ($existing) {
                $oldValues = [
                    'discount_type'   => $existing->discount_type,
                    'discount_value'  => $existing->discount_value,
                    'discount_reason' => $existing->discount_reason,
                    'amount'          => $existing->amount,
                ];
                $existing->update($data);
                $discountItem = $existing->fresh();
            } else {
                $discountItem = InvoiceItem::create($data);
            }

            // Recalc total dari sum items — amount negatif diskon otomatis mengurangi
            $this->invoiceService->recalcStatus($item->invoice()->first());

            AuditLog::record(
                action: AuditLog::ACTION_UPDATE,
                entity: $item->invoice,
                entityLabel: "Invoice {$item->invoice->invoice_number}",
                oldValues: $oldValues,
                newValues: [
                    'item_id'           => $item->id,
                    'item_code'         => $item->item_code,
                    'discount_type'     => $type,
                    'discount_value'    => $value,
                    'discount_reason'   => $reason,
                    'calculated_amount' => $calculatedAmount,
                ],
                notes: $existing ? 'Edit diskon item invoice' : 'Beri diskon item invoice',
            );

            return $discountItem;
        });
    }

    /**
     * Hapus diskon dari item invoice.
     *
     * @throws \InvalidArgumentException jika validasi bisnis gagal
     */
    public function removeDiscount(InvoiceItem $discountItem, User $by): void
    {
        if (!$discountItem->isDiscount()) {
            throw new \InvalidArgumentException('Item yang dihapus bukan item diskon.');
        }

        $invoice = $discountItem->invoice;

        if (in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_VOID])) {
            throw new \InvalidArgumentException('Diskon tidak bisa dihapus dari invoice yang sudah PAID atau VOID.');
        }

        DB::transaction(function () use ($discountItem) {
            $snapshot = [
                'item_id'         => $discountItem->parent_item_id,
                'discount_type'   => $discountItem->discount_type,
                'discount_value'  => $discountItem->discount_value,
                'discount_reason' => $discountItem->discount_reason,
                'amount'          => $discountItem->amount,
            ];

            $invoice = $discountItem->invoice;
            $discountItem->delete();
            $this->invoiceService->recalcStatus($invoice);

            AuditLog::record(
                action: AuditLog::ACTION_UPDATE,
                entity: $invoice,
                entityLabel: "Invoice {$invoice->invoice_number}",
                oldValues: $snapshot,
                newValues: ['removed_discount' => $snapshot],
                notes: 'Hapus diskon item invoice',
            );
        });
    }
}
```

- [ ] **Step 4: Jalankan test service — pastikan PASS (HTTP test masih fail, itu normal)**

```bash
php artisan test tests/Feature/DiscountTest.php --filter="test_apply|test_remove"
```

Expected: 8 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/DiscountTest.php app/Services/DiscountService.php
git commit -m "M05: Tambah DiscountService + tests TDD (apply + remove diskon)"
```

---

## Task 4: `StoreDiscountRequest`

**Files:**
- Create: `app/Http/Requests/StoreDiscountRequest.php`

- [ ] **Step 1: Buat Form Request via Artisan**

```bash
php artisan make:request StoreDiscountRequest
```

- [ ] **Step 2: Ganti isi `app/Http/Requests/StoreDiscountRequest.php`**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi role sudah dijaga oleh middleware route (role:Owner|Admin)
        return true;
    }

    public function rules(): array
    {
        return [
            'discount_type'   => ['required', 'in:NOMINAL,PERCENT'],
            'discount_value'  => ['required', 'integer', 'min:1'],
            'discount_reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'discount_type.required'   => 'Tipe diskon wajib dipilih.',
            'discount_type.in'         => 'Tipe diskon harus NOMINAL atau PERCENT.',
            'discount_value.required'  => 'Nilai diskon wajib diisi.',
            'discount_value.integer'   => 'Nilai diskon harus berupa angka bulat.',
            'discount_value.min'       => 'Nilai diskon minimal 1.',
            'discount_reason.required' => 'Alasan diskon wajib diisi.',
            'discount_reason.min'      => 'Alasan diskon minimal 3 karakter.',
            'discount_reason.max'      => 'Alasan diskon maksimal 500 karakter.',
        ];
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Requests/StoreDiscountRequest.php
git commit -m "M05: Tambah StoreDiscountRequest validasi form diskon"
```

---

## Task 5: `DiscountController` + Routes

**Files:**
- Create: `app/Http/Controllers/DiscountController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Buat `app/Http/Controllers/DiscountController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDiscountRequest;
use App\Models\InvoiceItem;
use App\Services\DiscountService;
use Illuminate\Http\RedirectResponse;

/**
 * Endpoint beri/hapus diskon per item invoice (M05).
 *
 * {invoiceItem} selalu mengacu ke item INDUK yang akan/sudah mendapat diskon.
 * Middleware role:Owner|Admin sudah dipasang di routes/web.php.
 */
class DiscountController extends Controller
{
    public function __construct(private DiscountService $discountService) {}

    /**
     * Simpan atau update diskon untuk item invoice.
     * Idempotent: jika diskon sudah ada, nilainya diupdate.
     */
    public function store(InvoiceItem $invoiceItem, StoreDiscountRequest $request): RedirectResponse
    {
        try {
            $this->discountService->applyDiscount(
                item: $invoiceItem,
                type: $request->discount_type,
                value: (int) $request->discount_value,
                reason: $request->discount_reason,
                by: auth()->user(),
            );

            return redirect()
                ->route('invoices.show', $invoiceItem->invoice_id)
                ->with('success', 'Diskon berhasil diterapkan.');
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('invoices.show', $invoiceItem->invoice_id)
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Hapus diskon dari item invoice.
     * {invoiceItem} adalah item INDUK — controller ambil discountItem-nya.
     */
    public function destroy(InvoiceItem $invoiceItem): RedirectResponse
    {
        $discountItem = $invoiceItem->discountItem()->first();

        if (!$discountItem) {
            return redirect()
                ->route('invoices.show', $invoiceItem->invoice_id)
                ->with('error', 'Diskon tidak ditemukan untuk item ini.');
        }

        try {
            $this->discountService->removeDiscount(
                discountItem: $discountItem,
                by: auth()->user(),
            );

            return redirect()
                ->route('invoices.show', $invoiceItem->invoice_id)
                ->with('success', 'Diskon berhasil dihapus.');
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('invoices.show', $invoiceItem->invoice_id)
                ->with('error', $e->getMessage());
        }
    }
}
```

- [ ] **Step 2: Tambah route di `routes/web.php`**

Buka `routes/web.php`. Cari blok `route:Owner|Admin` yang berisi `invoice-items.store` dan `invoice-items.destroy` (sekitar baris 255). Tambahkan 2 route baru di dalam blok yang sama:

```php
// Diskon per item invoice — beri/update dan hapus
Route::post('invoice-items/{invoiceItem}/discount',
    [\App\Http\Controllers\DiscountController::class, 'store']
)->name('invoice-items.discount.store');

Route::delete('invoice-items/{invoiceItem}/discount',
    [\App\Http\Controllers\DiscountController::class, 'destroy']
)->name('invoice-items.discount.destroy');
```

- [ ] **Step 3: Jalankan seluruh test DiscountTest — semua harus PASS**

```bash
php artisan test tests/Feature/DiscountTest.php
```

Expected: 12 tests, 12 PASS.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/DiscountController.php routes/web.php
git commit -m "M05: Tambah DiscountController + 2 route diskon (store/destroy)"
```

---

## Task 6: Update `InvoiceController` — Eager Load `discountItem`

**Files:**
- Modify: `app/Http/Controllers/InvoiceController.php`

- [ ] **Step 1: Update method `show()` — filter parent items dan eager load discountItem**

Buka `app/Http/Controllers/InvoiceController.php`. Cari method `show()` (sekitar baris 72). Ganti blok `$invoice->load([...])` hingga akhir method dengan:

```php
public function show(Invoice $invoice)
{
    $invoice->load([
        'student',
        // Hanya item induk (bukan item DISKON) + eager load diskon tiap item
        'items' => fn ($q) => $q->whereNull('parent_item_id')
                                ->with(['discountItem', 'addedBy']),
        'payments' => fn ($q) => $q->latest('payment_date'),
        'payments.createdBy',
        'payments.voidedBy',
    ]);

    $catalogItems = \App\Models\InvoiceComponent::where('is_active', true)
        ->orderBy('sort_order')
        ->orderBy('code')
        ->get(['id', 'code', 'name', 'default_price']);

    return view('invoices.show', compact('invoice', 'catalogItems'));
}
```

- [ ] **Step 2: Update method `print()` — filter parent items dan eager load discountItem**

Cari method `print()` (sekitar baris 194). Ganti blok `$invoice->load([...])` dengan:

```php
public function print(Invoice $invoice)
{
    $invoice->load([
        'student.package.instrument',
        'items' => fn ($q) => $q->whereNull('parent_item_id')->with('discountItem'),
        'validPayments',
    ]);

    return view('invoices.print', compact('invoice'));
}
```

- [ ] **Step 3: Jalankan test — pastikan tidak ada regresi**

```bash
php artisan test tests/Feature/DiscountTest.php
```

Expected: 12 PASS.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/InvoiceController.php
git commit -m "M05: Update InvoiceController — eager load discountItem di show dan print"
```

---

## Task 7: Update `invoices/show.blade.php` — UI Diskon

**Files:**
- Modify: `resources/views/invoices/show.blade.php`

- [ ] **Step 1: Tambah `$canDiscount` di blok `@php`**

Buka `resources/views/invoices/show.blade.php`. Cari baris `$canEditItems = in_array($invoice->status, ['UNPAID', 'PARTIAL']);` (sekitar baris 28). Tambahkan tepat setelahnya:

```php
// Diskon: boleh selama UNPAID atau PARTIAL (sama dengan canEditItems)
$canDiscount = $canEditItems;
```

- [ ] **Step 2: Ganti seluruh blok `<tbody>` di tabel item**

Cari `<tbody>` di bagian tabel item (sekitar baris 311). Ganti seluruh blok `<tbody>` (dari `<tbody>` s.d. `</tbody>` tepat sebelum baris total `<tr class="font-bold border-t-2">`) dengan:

```blade
<tbody>
    @foreach($invoice->items as $item)
        {{-- ===== Baris item induk ===== --}}
        <tr class="border-b" x-data="{
            showDiscount: false,
            type: '{{ $item->discountItem?->discount_type ?? 'NOMINAL' }}',
            value: '{{ $item->discountItem?->discount_value ?? '' }}',
            itemAmount: {{ $item->amount }},
            get preview() {
                const v = parseInt(this.value) || 0;
                if (v <= 0) return 0;
                if (this.type === 'PERCENT') return Math.floor(this.itemAmount * v / 100);
                return v;
            },
            get previewFormatted() {
                return this.preview.toLocaleString('id-ID');
            }
        }">
            <td class="py-2 font-mono text-xs">{{ $item->item_code }}</td>
            <td class="py-2">
                {{ $item->description }}
                @if($item->isManual() && $item->addedBy)
                    <div class="text-xs text-gray-400">+ oleh {{ $item->addedBy->name }}</div>
                @endif
            </td>
            <td class="py-2 text-right">Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
            <td class="py-2 text-center">
                @if($item->isManual())
                    <span class="px-2 py-0.5 rounded text-xs bg-indigo-100 text-indigo-700">Manual</span>
                @else
                    <span class="px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-500">Sistem</span>
                @endif
            </td>
            @hasanyrole('Owner|Admin')
                @if($canEditItems)
                    <td class="py-2 text-right space-x-2 whitespace-nowrap">
                        @if($item->isManual())
                            <form method="POST"
                                  action="{{ route('invoice-items.destroy', $item->id) }}"
                                  class="inline"
                                  onsubmit="return confirm('Hapus item {{ $item->item_code }} dari invoice ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-red-600 hover:underline">Hapus</button>
                            </form>
                        @else
                            <span class="text-xs text-gray-300">—</span>
                        @endif
                        @if($canDiscount && $item->item_code !== 'DENDA')
                            <button type="button"
                                    @click="showDiscount = !showDiscount"
                                    class="text-xs text-amber-700 hover:underline">
                                {{ $item->discountItem ? 'Edit Diskon' : 'Beri Diskon' }}
                            </button>
                        @endif
                    </td>
                @endif
            @endhasanyrole
        </tr>

        {{-- ===== Form beri/edit diskon (inline expand, Alpine.js) ===== --}}
        @hasanyrole('Owner|Admin')
            @if($canDiscount && $item->item_code !== 'DENDA')
                <tr x-show="showDiscount" x-cloak>
                    <td colspan="{{ $canEditItems ? 5 : 4 }}"
                        class="py-3 px-4 bg-amber-50 border-b border-amber-200">
                        <form method="POST"
                              action="{{ route('invoice-items.discount.store', $item->id) }}">
                            @csrf
                            <p class="text-xs font-semibold text-amber-800 mb-2">
                                Beri Diskon untuk: <span class="font-normal">{{ $item->description }}</span>
                            </p>
                            <div class="flex gap-3 items-end flex-wrap">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Tipe</label>
                                    <select name="discount_type" x-model="type"
                                            class="block border-gray-300 rounded text-sm">
                                        <option value="NOMINAL">Nominal (Rp)</option>
                                        <option value="PERCENT">Persentase (%)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">
                                        Nilai <span class="text-red-500">*</span>
                                    </label>
                                    <input type="number" name="discount_value"
                                           x-model="value"
                                           min="1"
                                           :max="type === 'PERCENT' ? 100 : itemAmount - 1"
                                           required
                                           class="block w-28 border-gray-300 rounded text-sm"
                                           placeholder="0">
                                    <p class="text-xs text-amber-700 mt-1">
                                        Preview: –Rp <span x-text="previewFormatted">0</span>
                                    </p>
                                </div>
                                <div class="flex-1" style="min-width:180px">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">
                                        Alasan <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="discount_reason"
                                           required minlength="3" maxlength="500"
                                           value="{{ $item->discountItem?->discount_reason }}"
                                           class="block w-full border-gray-300 rounded text-sm"
                                           placeholder="Mis: Diskon ulang tahun, promosi bulan ini...">
                                </div>
                                <div class="flex gap-2">
                                    <button type="submit"
                                            class="px-3 py-1.5 rounded text-sm font-medium"
                                            style="background:rgba(212,168,83,0.9);color:#1A1000">
                                        Simpan Diskon
                                    </button>
                                    <button type="button"
                                            @click="showDiscount = false"
                                            class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-800">
                                        Batal
                                    </button>
                                </div>
                            </div>
                        </form>
                    </td>
                </tr>
            @endif
        @endhasanyrole

        {{-- ===== Sub-baris diskon (jika ada) ===== --}}
        @if($item->discountItem)
            <tr class="border-b">
                <td class="py-1.5 pl-6 font-mono text-xs">
                    <span class="px-1.5 py-0.5 rounded text-xs bg-amber-100 text-amber-800">DISKON</span>
                </td>
                <td class="py-1.5 text-xs text-gray-600">
                    ↳ {{ $item->discountItem->discount_reason }}
                    @if($item->discountItem->discount_type === 'PERCENT')
                        <span class="text-gray-400">({{ $item->discountItem->discount_value }}%)</span>
                    @endif
                </td>
                <td class="py-1.5 text-right text-red-600 text-xs font-medium">
                    –Rp {{ number_format(abs($item->discountItem->amount), 0, ',', '.') }}
                </td>
                <td class="py-1.5 text-center">
                    <span class="px-1.5 py-0.5 rounded text-xs bg-amber-100 text-amber-700">Diskon</span>
                </td>
                @hasanyrole('Owner|Admin')
                    @if($canDiscount)
                        <td class="py-1.5 text-right">
                            <form method="POST"
                                  action="{{ route('invoice-items.discount.destroy', $item->id) }}"
                                  class="inline"
                                  onsubmit="return confirm('Hapus diskon dari item {{ $item->item_code }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-red-600 hover:underline">
                                    Hapus Diskon
                                </button>
                            </form>
                        </td>
                    @endif
                @endhasanyrole
            </tr>
        @endif
    @endforeach

    {{-- Baris total — pakai total_amount dari DB (sudah termasuk diskon) --}}
    <tr class="font-bold border-t-2">
        <td colspan="2" class="py-2 text-right text-gray-700">Total</td>
        <td class="py-2 text-right">
            Rp {{ number_format($invoice->total_amount, 0, ',', '.') }}
        </td>
        <td colspan="{{ $canEditItems ? 2 : 1 }}"></td>
    </tr>
</tbody>
```

- [ ] **Step 3: Build asset (jika ada class Tailwind baru)**

```bash
npm run build
```

- [ ] **Step 4: Verifikasi manual di browser**

Buka invoice UNPAID. Pastikan:
- Tombol "Beri Diskon" muncul untuk SPP/REG
- Tombol "Beri Diskon" tidak muncul untuk DENDA
- Form expand muncul, preview hitung real-time (NOMINAL dan PERCENT)
- Submit → sub-baris DISKON muncul, total berkurang
- Tombol "Edit Diskon" pre-fill nilai lama
- Tombol "Hapus Diskon" menghapus sub-baris, total kembali naik
- Invoice PAID: tidak ada tombol diskon

- [ ] **Step 5: Commit**

```bash
git add resources/views/invoices/show.blade.php
git commit -m "M05: UI diskon per item — form Alpine inline + sub-baris diskon di tabel"
```

---

## Task 8: Update `invoices/print.blade.php` — Diskon di Cetak

**Files:**
- Modify: `resources/views/invoices/print.blade.php`

- [ ] **Step 1: Update loop items di print view**

Buka `resources/views/invoices/print.blade.php`. Cari `@foreach($invoice->items as $item)` (sekitar baris 301). Ganti blok `@foreach` tersebut (termasuk `@endforeach`) dengan:

```blade
@foreach($invoice->items as $item)
    <tr>
        <td class="code">{{ $item->item_code }}</td>
        <td>{{ $item->description }}</td>
        <td class="right">Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
    </tr>
    @if($item->discountItem)
        <tr style="color:#b45309;font-size:9.5pt;">
            <td class="code" style="padding-left:12pt;">DISKON</td>
            <td>↳ {{ $item->discountItem->discount_reason }}
                @if($item->discountItem->discount_type === 'PERCENT')
                    ({{ $item->discountItem->discount_value }}%)
                @endif
            </td>
            <td class="right">–Rp {{ number_format(abs($item->discountItem->amount), 0, ',', '.') }}</td>
        </tr>
    @endif
@endforeach
```

- [ ] **Step 2: Verifikasi manual cetak**

Buka invoice yang ada diskonnya → klik "Cetak Invoice" → pastikan:
- Sub-baris DISKON muncul di bawah item yang didiskon
- Warna amber/orange (#b45309) membedakannya dari item biasa
- Tanda `–` di depan jumlah diskon
- Total di bagian bawah sudah benar (sudah termasuk diskon)

- [ ] **Step 3: Commit**

```bash
git add resources/views/invoices/print.blade.php
git commit -m "M05: Update print view invoice — sub-baris diskon di cetak"
```

---

## Task 9: Final Check — Semua Test + Verifikasi End-to-End

- [ ] **Step 1: Jalankan seluruh test suite**

```bash
php artisan test
```

Expected: Semua test PASS, tidak ada regresi.

- [ ] **Step 2: Checklist verifikasi manual**

- [ ] Invoice UNPAID: tombol "Beri Diskon" muncul untuk SPP dan REG
- [ ] Invoice UNPAID: tombol "Beri Diskon" TIDAK muncul untuk DENDA
- [ ] Form diskon: preview real-time Alpine bekerja untuk NOMINAL dan PERCENT
- [ ] Submit NOMINAL Rp 50.000 → total berkurang Rp 50.000, flash "Diskon berhasil diterapkan"
- [ ] Submit PERCENT 10% dari SPP 370.000 → total berkurang Rp 37.000
- [ ] Klik "Edit Diskon" → form pre-fill nilai lama, submit update nilai baru
- [ ] Klik "Hapus Diskon" → sub-baris hilang, total kembali ke aslinya
- [ ] Invoice PARTIAL: tombol diskon masih muncul
- [ ] Invoice PAID: tombol diskon tidak muncul
- [ ] Auditor: tidak ada tombol diskon (role check berfungsi)
- [ ] Cetak invoice: sub-baris DISKON muncul dengan warna amber
- [ ] Audit log: aksi beri/edit/hapus diskon tercatat di tabel audit_logs

- [ ] **Step 3: Commit akhir (jika ada perubahan kecil dari verifikasi)**

```bash
git add -p
git commit -m "M05: Fitur diskon per item invoice — selesai"
```
