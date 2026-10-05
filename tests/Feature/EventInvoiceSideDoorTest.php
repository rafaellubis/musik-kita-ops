<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Invoice;
use App\Models\InvoiceComponent;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventInvoiceSideDoorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Invoice $eventInvoice;
    private InvoiceItem $eventItem;
    private InvoiceComponent $component;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Owner', 'Admin', 'Auditor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->admin = User::factory()->create()->assignRole('Admin');
        $event = Event::factory()->published()->create();
        $this->eventInvoice = Invoice::factory()->create([
            'event_id' => $event->id,
            'status' => Invoice::STATUS_UNPAID,
            'total_amount' => 295000,
            'paid_amount' => 0,
        ]);
        $this->component = InvoiceComponent::create([
            'code' => 'MC',
            'name' => 'Mini Concert',
            'default_price' => 295000,
            'is_active' => true,
        ]);
        $this->eventItem = InvoiceItem::create([
            'invoice_id' => $this->eventInvoice->id,
            'invoice_component_id' => $this->component->id,
            'item_code' => 'MC',
            'description' => 'Mini Concert',
            'amount' => 295000,
        ]);
    }

    public function test_generic_manual_item_endpoints_reject_event_invoices(): void
    {
        $this->actingAs($this->admin)
            ->post(route('invoice-items.store', $this->eventInvoice), $this->manualItemData())
            ->assertUnprocessable();

        $manualItem = InvoiceItem::create([
            'invoice_id' => $this->eventInvoice->id,
            'invoice_component_id' => $this->component->id,
            'added_by' => $this->admin->id,
            'item_code' => 'MC',
            'description' => 'Item manual terlanjur ada',
            'amount' => 10000,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('invoice-items.destroy', $manualItem))
            ->assertUnprocessable();

        $this->assertDatabaseHas('invoice_items', ['id' => $manualItem->id]);
        $this->assertSame(2, $this->eventInvoice->items()->count());
    }

    public function test_generic_fine_removal_endpoint_rejects_event_invoices(): void
    {
        $fine = InvoiceItem::create([
            'invoice_id' => $this->eventInvoice->id,
            'item_code' => 'DENDA',
            'description' => 'Denda korup pada invoice event',
            'amount' => 5000,
        ]);

        $this->actingAs($this->admin)
            ->post(route('invoice-items.remove-fine', $fine), ['reason' => 'Hapus dari M05'])
            ->assertUnprocessable();

        $this->assertDatabaseHas('invoice_items', ['id' => $fine->id]);
    }

    public function test_generic_discount_endpoints_reject_event_invoices(): void
    {
        $this->actingAs($this->admin)
            ->post(route('invoice-items.discount.store', $this->eventItem), [
                'discount_type' => InvoiceItem::DISCOUNT_TYPE_NOMINAL,
                'discount_value' => 10000,
                'discount_reason' => 'Diskon event generik',
            ])
            ->assertUnprocessable();

        $discount = InvoiceItem::create([
            'invoice_id' => $this->eventInvoice->id,
            'parent_item_id' => $this->eventItem->id,
            'added_by' => $this->admin->id,
            'item_code' => 'DISKON',
            'description' => 'Diskon historis',
            'amount' => -10000,
            'discount_type' => InvoiceItem::DISCOUNT_TYPE_NOMINAL,
            'discount_value' => 10000,
            'discount_reason' => 'Diskon historis',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('invoice-items.discount.destroy', $this->eventItem))
            ->assertUnprocessable();

        $this->assertDatabaseHas('invoice_items', ['id' => $discount->id]);
    }

    public function test_generic_void_endpoint_rejects_event_invoices(): void
    {
        $this->actingAs($this->admin)
            ->post(route('invoices.void', $this->eventInvoice), ['reason' => 'Void dari M05'])
            ->assertUnprocessable();

        $this->assertDatabaseHas('invoices', [
            'id' => $this->eventInvoice->id,
            'status' => Invoice::STATUS_UNPAID,
            'voided_at' => null,
        ]);
    }

    public function test_ordinary_invoice_keeps_generic_manual_item_behavior(): void
    {
        $invoice = Invoice::factory()->create([
            'event_id' => null,
            'status' => Invoice::STATUS_UNPAID,
            'total_amount' => 0,
            'paid_amount' => 0,
        ]);

        $this->actingAs($this->admin)
            ->post(route('invoice-items.store', $invoice), $this->manualItemData())
            ->assertRedirect();

        $manualItem = $invoice->items()->where('added_by', $this->admin->id)->sole();
        $this->assertSame(25000, $invoice->fresh()->total_amount);

        $this->actingAs($this->admin)
            ->delete(route('invoice-items.destroy', $manualItem))
            ->assertRedirect();

        $this->assertDatabaseMissing('invoice_items', ['id' => $manualItem->id]);
    }

    public function test_auditor_remains_forbidden_from_generic_event_invoice_mutations(): void
    {
        $auditor = User::factory()->create()->assignRole('Auditor');

        $this->actingAs($auditor)
            ->post(route('invoices.void', $this->eventInvoice), ['reason' => 'Tidak berhak'])
            ->assertForbidden();
        $this->actingAs($auditor)
            ->post(route('invoice-items.store', $this->eventInvoice), $this->manualItemData())
            ->assertForbidden();
        $this->actingAs($auditor)
            ->post(route('invoice-items.discount.store', $this->eventItem), [
                'discount_type' => InvoiceItem::DISCOUNT_TYPE_NOMINAL,
                'discount_value' => 10000,
                'discount_reason' => 'Tidak berhak',
            ])
            ->assertForbidden();
    }

    private function manualItemData(): array
    {
        return [
            'invoice_component_id' => $this->component->id,
            'description' => 'Item manual',
            'amount' => 25000,
        ];
    }
}
