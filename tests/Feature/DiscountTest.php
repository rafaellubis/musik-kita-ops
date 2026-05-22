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

    public function test_apply_diskon_persen_gagal_jika_nol(): void
    {
        $this->actingAs($this->owner);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->applyDiscount(
            $this->sppItem,
            InvoiceItem::DISCOUNT_TYPE_PERCENT,
            0,
            'Test lower boundary',
            $this->owner,
        );
    }

    public function test_apply_diskon_persen_minimum_1_berhasil(): void
    {
        $this->actingAs($this->owner);

        $discountItem = $this->service->applyDiscount(
            $this->sppItem,
            InvoiceItem::DISCOUNT_TYPE_PERCENT,
            1,
            'Diskon 1%',
            $this->owner,
        );

        // intdiv(370000 * 1, 100) = 3700
        $this->assertEquals(-3700, $discountItem->amount);
        $this->invoice->refresh();
        $this->assertEquals(366300, $this->invoice->total_amount);
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

    // ===== Test Diskon pada Item DENDA =====
    // DiscountService tidak memblokir item DENDA secara eksplisit.
    // Test-test ini mendokumentasikan perilaku dan mengunci agar tidak regresi.

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
        $this->assertEquals($dendaItem->id, $discountItem->parent_item_id);
        $this->assertEquals('PERCENT', $discountItem->discount_type);

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
}
