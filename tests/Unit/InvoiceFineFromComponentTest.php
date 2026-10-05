<?php

namespace Tests\Unit;

use App\Models\InvoiceComponent;
use App\Models\Setting;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Student;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceFineFromComponentTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\InvoiceComponentSeeder::class);
        $this->service = app(InvoiceService::class);
    }

    public function test_fine_per_day_reads_denda_component(): void
    {
        InvoiceComponent::where('code', 'DENDA')->update(['default_price' => 7500]);

        $this->assertSame(7500, $this->service->finePerDay());
    }

    public function test_fine_per_day_falls_back_when_component_missing(): void
    {
        InvoiceComponent::where('code', 'DENDA')->delete();

        $this->assertSame(InvoiceService::FINE_PER_DAY, $this->service->finePerDay());
    }

    public function test_due_day_reads_spp_due_day_setting(): void
    {
        Setting::set('spp_due_day', '12');

        $this->assertSame(12, $this->service->dueDay());
    }

    public function test_apply_late_fines_uses_component_price_in_description(): void
    {
        InvoiceComponent::where('code', 'DENDA')->update(['default_price' => 6000]);
        Setting::set('spp_due_day', '10');

        $student = \App\Models\Student::factory()->create(['status' => 'Aktif']);
        $invoice = \App\Models\Invoice::factory()->create([
            'student_id'   => $student->id,
            'year'         => 2026,
            'month'        => 6,
            'status'       => 'UNPAID',
            'due_date'     => '2026-06-10',
            'total_amount' => 340000,
            'paid_amount'  => 0,
        ]);
        \App\Models\InvoiceItem::create([
            'invoice_id'  => $invoice->id,
            'item_code'   => 'SPP',
            'amount'      => 340000,
            'description' => 'SPP Juni 2026',
        ]);

        $asOf = Carbon::create(2026, 6, 12);
        $report = $this->service->applyLateFinesForMonth(2026, 6, $asOf);

        $this->assertSame(1, $report['updated']);
        $fine = $invoice->fresh()->items()->where('item_code', 'DENDA')->first();
        $this->assertNotNull($fine);
        $this->assertSame(12000, $fine->amount);
        $this->assertStringContainsString('Rp 6.000', $fine->description);
    }

    public function test_event_invoice_does_not_receive_late_fine(): void
    {
        Setting::set('spp_due_day', '10');
        $student = Student::factory()->create(['status' => 'Aktif']);
        $event = Event::factory()->create();

        $eventInvoice = Invoice::factory()->create([
            'student_id' => $student->id,
            'event_id' => $event->id,
            'year' => 2026,
            'month' => 6,
            'status' => Invoice::STATUS_UNPAID,
            'due_date' => '2026-06-10',
            'total_amount' => 295000,
            'paid_amount' => 0,
        ]);
        InvoiceItem::create([
            'invoice_id' => $eventInvoice->id,
            'item_code' => 'MC',
            'amount' => 295000,
            'description' => 'Mini Concert',
        ]);

        $registrationInvoice = Invoice::factory()->create([
            'student_id' => $student->id,
            'year' => 2026,
            'month' => 6,
            'status' => Invoice::STATUS_UNPAID,
            'due_date' => '2026-06-10',
            'total_amount' => 250000,
            'paid_amount' => 0,
        ]);
        InvoiceItem::create([
            'invoice_id' => $registrationInvoice->id,
            'item_code' => 'REG',
            'amount' => 250000,
            'description' => 'Registrasi',
        ]);

        $sppInvoice = Invoice::factory()->create([
            'student_id' => $student->id,
            'year' => 2026,
            'month' => 6,
            'status' => Invoice::STATUS_UNPAID,
            'due_date' => '2026-06-10',
            'total_amount' => 340000,
            'paid_amount' => 0,
        ]);
        InvoiceItem::create([
            'invoice_id' => $sppInvoice->id,
            'item_code' => 'SPP',
            'amount' => 340000,
            'description' => 'SPP Juni 2026',
        ]);

        $report = $this->service->applyLateFinesForMonth(2026, 6, Carbon::create(2026, 6, 12));

        $this->assertSame(['updated' => 1, 'processed' => 1], $report);
        $this->assertFalse($eventInvoice->items()->where('item_code', 'DENDA')->exists());
        $this->assertFalse($registrationInvoice->items()->where('item_code', 'DENDA')->exists());
        $this->assertTrue($sppInvoice->items()->where('item_code', 'DENDA')->exists());
    }
}
