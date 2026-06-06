<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InvoiceVoidTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $admin;
    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->admin = User::factory()->create()->assignRole('Admin');

        $student = Student::factory()->create(['status' => 'Aktif']);

        $this->invoice = Invoice::create([
            'invoice_number' => 'INV/2026/06/0100',
            'student_id'     => $student->id,
            'year'           => 2026,
            'month'          => 6,
            'total_amount'   => 340000,
            'paid_amount'    => 0,
            'status'         => Invoice::STATUS_UNPAID,
            'due_date'       => now()->addDays(10)->toDateString(),
            'issued_at'      => now()->toDateString(),
        ]);

        InvoiceItem::create([
            'invoice_id'  => $this->invoice->id,
            'item_code'   => 'SPP',
            'description' => 'SPP Juni',
            'amount'      => 340000,
        ]);
    }

    public function test_owner_bisa_void_invoice_unpaid(): void
    {
        $response = $this->actingAs($this->owner)
            ->post(route('invoices.void', $this->invoice), [
                'reason' => 'Duplikat SPP generate Juni',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->invoice->refresh();
        $this->assertEquals(Invoice::STATUS_VOID, $this->invoice->status);
        $this->assertNotNull($this->invoice->voided_at);
        $this->assertEquals($this->owner->id, $this->invoice->voided_by);
        $this->assertEquals('Duplikat SPP generate Juni', $this->invoice->voided_reason);

        $this->assertDatabaseHas('audit_logs', [
            'action'       => AuditLog::ACTION_VOID,
            'entity_type'  => 'Invoice',
            'entity_id'    => $this->invoice->id,
        ]);
    }

    public function test_admin_bisa_void_invoice_unpaid(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('invoices.void', $this->invoice), [
                'reason' => 'Duplikat SPP generate Juni',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->invoice->refresh();
        $this->assertEquals(Invoice::STATUS_VOID, $this->invoice->status);
        $this->assertEquals($this->admin->id, $this->invoice->voided_by);
    }

    public function test_admin_tidak_bisa_void_invoice_dengan_pembayaran_aktif(): void
    {
        Payment::create([
            'receipt_number' => 'KW/2026/06/0002',
            'invoice_id'     => $this->invoice->id,
            'amount'         => 100000,
            'method'         => 'CASH',
            'payment_date'   => now()->toDateString(),
            'created_by'     => $this->admin->id,
        ]);
        $this->invoice->update([
            'paid_amount' => 100000,
            'status'      => Invoice::STATUS_PARTIAL,
        ]);

        $this->actingAs($this->admin)
            ->post(route('invoices.void', $this->invoice), [
                'reason' => 'Salah invoice',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->invoice->refresh();
        $this->assertEquals(Invoice::STATUS_PARTIAL, $this->invoice->status);
    }

    public function test_void_gagal_jika_ada_pembayaran_aktif(): void
    {
        Payment::create([
            'receipt_number' => 'KW/2026/06/0001',
            'invoice_id'     => $this->invoice->id,
            'amount'         => 340000,
            'method'         => 'CASH',
            'payment_date'   => now()->toDateString(),
            'created_by'     => $this->owner->id,
        ]);
        $this->invoice->update([
            'paid_amount' => 340000,
            'status'      => Invoice::STATUS_PAID,
        ]);

        $this->actingAs($this->owner)
            ->post(route('invoices.void', $this->invoice), [
                'reason' => 'Salah invoice',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->invoice->refresh();
        $this->assertEquals(Invoice::STATUS_PAID, $this->invoice->status);
    }

    public function test_void_gagal_tanpa_alasan(): void
    {
        $this->actingAs($this->owner)
            ->post(route('invoices.void', $this->invoice), [])
            ->assertSessionHasErrors('reason');

        $this->invoice->refresh();
        $this->assertEquals(Invoice::STATUS_UNPAID, $this->invoice->status);
    }

    public function test_void_gagal_jika_sudah_void(): void
    {
        $this->actingAs($this->owner)
            ->post(route('invoices.void', $this->invoice), [
                'reason' => 'Duplikat pertama',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($this->owner)
            ->post(route('invoices.void', $this->invoice), [
                'reason' => 'Duplikat kedua',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }
}
