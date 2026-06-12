<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Student;
use App\Models\User;
use App\Services\DiscountService;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RemoveFineTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $admin;
    private User $auditor;
    private Invoice $invoice;
    private InvoiceItem $sppItem;
    private InvoiceItem $dendaItem;
    private InvoiceService $invoiceService;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);

        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->admin = User::factory()->create()->assignRole('Admin');
        $this->auditor = User::factory()->create()->assignRole('Auditor');

        $student = Student::factory()->create(['status' => 'Aktif']);

        $this->invoice = Invoice::create([
            'invoice_number' => 'INV/2026/05/0001',
            'student_id'     => $student->id,
            'year'           => 2026,
            'month'          => 5,
            'total_amount'   => 385000,
            'paid_amount'    => 0,
            'status'         => Invoice::STATUS_UNPAID,
            'due_date'       => '2026-05-10',
            'issued_at'      => '2026-05-01',
        ]);

        $this->sppItem = InvoiceItem::create([
            'invoice_id'  => $this->invoice->id,
            'item_code'   => 'SPP',
            'description' => 'SPP Reguler Piano',
            'amount'      => 370000,
        ]);

        $this->dendaItem = InvoiceItem::create([
            'invoice_id'  => $this->invoice->id,
            'item_code'   => 'DENDA',
            'description' => 'Denda keterlambatan (3 hari × Rp 5.000)',
            'amount'      => 15000,
            'metadata'    => ['days_late' => 3],
        ]);

        $this->invoiceService = new InvoiceService();
    }

    public function test_owner_hapus_denda_berhasil(): void
    {
        $this->actingAs($this->owner);

        $response = $this->post(route('invoice-items.remove-fine', $this->dendaItem->id), [
            'reason' => 'Konfirmasi telat bayar via WA',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('invoice_items', ['id' => $this->dendaItem->id]);

        $this->invoice->refresh();
        $this->assertEquals(370000, $this->invoice->total_amount);
        $this->assertNotNull($this->invoice->fine_waived_at);
        $this->assertEquals($this->owner->id, $this->invoice->fine_waived_by);
        $this->assertEquals('Konfirmasi telat bayar via WA', $this->invoice->fine_waive_reason);
    }

    public function test_admin_hapus_denda_berhasil(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('invoice-items.remove-fine', $this->dendaItem->id), [
            'reason' => 'Kesepakatan admin dengan orang tua',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->invoice->refresh();
        $this->assertNotNull($this->invoice->fine_waived_at);
        $this->assertEquals($this->admin->id, $this->invoice->fine_waived_by);
    }

    public function test_auditor_tidak_bisa_hapus_denda(): void
    {
        $this->actingAs($this->auditor);

        $response = $this->post(route('invoice-items.remove-fine', $this->dendaItem->id), [
            'reason' => 'Coba auditor',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('invoice_items', ['id' => $this->dendaItem->id]);
    }

    public function test_hapus_denda_gagal_jika_invoice_paid(): void
    {
        $this->invoice->update([
            'status'       => Invoice::STATUS_PAID,
            'paid_amount'  => 385000,
            'total_amount' => 385000,
        ]);

        $this->actingAs($this->owner);

        $response = $this->post(route('invoice-items.remove-fine', $this->dendaItem->id), [
            'reason' => 'Coba hapus saat lunas',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('invoice_items', ['id' => $this->dendaItem->id]);
    }

    public function test_hapus_denda_gagal_jika_bukan_item_denda(): void
    {
        $this->actingAs($this->owner);

        $this->expectException(InvalidArgumentException::class);

        $this->invoiceService->waiveFine(
            $this->sppItem,
            $this->owner,
            'Salah item',
        );
    }

    public function test_hapus_denda_gagal_jika_sudah_di_waive(): void
    {
        $this->invoice->update([
            'fine_waived_at'    => now(),
            'fine_waived_by'    => $this->owner->id,
            'fine_waive_reason' => 'Sudah dihapus sebelumnya',
        ]);

        $this->actingAs($this->owner);

        $response = $this->post(route('invoice-items.remove-fine', $this->dendaItem->id), [
            'reason' => 'Coba hapus lagi',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_hapus_denda_juga_hapus_diskon_child(): void
    {
        $discountService = new DiscountService($this->invoiceService);

        $discountService->applyDiscount(
            $this->dendaItem,
            InvoiceItem::DISCOUNT_TYPE_NOMINAL,
            5000,
            'Diskon sebagian denda',
            $this->owner,
        );

        $this->actingAs($this->owner);

        $this->post(route('invoice-items.remove-fine', $this->dendaItem->id), [
            'reason' => 'Hapus denda penuh',
        ])->assertSessionHas('success');

        $this->assertDatabaseMissing('invoice_items', [
            'invoice_id' => $this->invoice->id,
            'item_code'  => 'DENDA',
        ]);
        $this->assertDatabaseMissing('invoice_items', [
            'invoice_id' => $this->invoice->id,
            'item_code'  => 'DISKON',
        ]);

        $this->invoice->refresh();
        $this->assertEquals(370000, $this->invoice->total_amount);
    }

    public function test_cron_skip_invoice_yang_sudah_di_waive(): void
    {
        $student = Student::factory()->create(['status' => 'Aktif']);

        $waivedInvoice = Invoice::create([
            'invoice_number' => 'INV/2026/05/0002',
            'student_id'     => $student->id,
            'year'           => 2026,
            'month'          => 5,
            'total_amount'   => 370000,
            'paid_amount'    => 0,
            'status'         => Invoice::STATUS_UNPAID,
            'due_date'       => '2026-05-10',
            'issued_at'      => '2026-05-01',
            'fine_waived_at' => now(),
            'fine_waived_by' => $this->owner->id,
            'fine_waive_reason' => 'Denda dihapus manual',
        ]);

        InvoiceItem::create([
            'invoice_id'  => $waivedInvoice->id,
            'item_code'   => 'SPP',
            'description' => 'SPP',
            'amount'      => 370000,
        ]);

        $asOf = Carbon::parse('2026-05-15');

        $this->invoiceService->applyLateFinesForMonth(2026, 5, $asOf);

        $this->assertDatabaseMissing('invoice_items', [
            'invoice_id' => $waivedInvoice->id,
            'item_code'  => 'DENDA',
        ]);
    }

    public function test_cron_tetap_apply_denda_ke_invoice_non_waived(): void
    {
        $student = Student::factory()->create(['status' => 'Aktif']);

        $plainInvoice = Invoice::create([
            'invoice_number' => 'INV/2026/05/0003',
            'student_id'     => $student->id,
            'year'           => 2026,
            'month'          => 5,
            'total_amount'   => 370000,
            'paid_amount'    => 0,
            'status'         => Invoice::STATUS_UNPAID,
            'due_date'       => '2026-05-10',
            'issued_at'      => '2026-05-01',
        ]);

        InvoiceItem::create([
            'invoice_id'  => $plainInvoice->id,
            'item_code'   => 'SPP',
            'description' => 'SPP',
            'amount'      => 370000,
        ]);

        $asOf = Carbon::parse('2026-05-15');

        $this->invoiceService->applyLateFinesForMonth(2026, 5, $asOf);

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $plainInvoice->id,
            'item_code'  => 'DENDA',
            'amount'     => 25000,
        ]);

        $plainInvoice->refresh();
        $this->assertEquals(395000, $plainInvoice->total_amount);
    }

    public function test_waive_fine_mencatat_audit_log(): void
    {
        $this->actingAs($this->owner);

        $this->invoiceService->waiveFine(
            $this->dendaItem,
            $this->owner,
            'Audit trail test',
        );

        $this->assertDatabaseHas('audit_logs', [
            'action'      => AuditLog::ACTION_DELETE,
            'entity_type' => 'Invoice',
            'entity_id'   => $this->invoice->id,
        ]);
    }
}
