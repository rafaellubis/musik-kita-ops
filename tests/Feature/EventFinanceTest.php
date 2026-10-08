<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventHonorEntry;
use App\Models\EventParticipant;
use App\Models\HonorSlip;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\User;
use App\Services\EventFinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventFinanceTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Owner', 'Admin', 'Auditor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->owner = User::factory()->create()->assignRole('Owner');
    }

    public function test_event_revenue_is_zero_before_payment(): void
    {
        $event = Event::factory()->published()->create();
        EventParticipant::factory()->create([
            'event_id' => $event->id,
            'snapshot_mc_fee' => 295000,
        ]);
        $this->eventInvoice($event, [295000]);

        $summary = $this->summary($event);

        $this->assertSame(295000, $summary['billed']);
        $this->assertSame(0, $summary['collected']);
        $this->assertSame(295000, $summary['outstanding']);
        $this->assertSame(0, $summary['cash_margin']);

        $response = $this->actingAs($this->owner)
            ->get(route('reports.finance', ['year' => 2026, 'month' => 6]))
            ->assertOk();
        $this->assertSame(0, (int) $response->viewData('totalRevenue'));
    }

    public function test_event_revenue_uses_valid_payment_date_and_method(): void
    {
        $event = Event::factory()->published()->create();
        $invoice = $this->eventInvoice($event, [300000]);
        Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'amount' => 325000,
            'method' => Payment::METHOD_QRIS,
            'payment_date' => '2026-06-30',
            'voided_at' => null,
        ]);

        $may = $this->actingAs($this->owner)
            ->get(route('reports.finance', ['year' => 2026, 'month' => 5]))
            ->assertOk();
        $this->assertSame(0, (int) $may->viewData('totalRevenue'));

        $june = $this->get(route('reports.finance', ['year' => 2026, 'month' => 6]))
            ->assertOk()
            ->assertSee('Mini Concert');
        $this->assertSame(325000, (int) $june->viewData('totalRevenue'));
        $this->assertSame(325000, (int) ($june->viewData('revenueByMethod')['QRIS'] ?? 0));
        $this->assertSame(325000, (int) $june->viewData('revenueByType')->firstWhere('item_code', 'MC')->total);
    }

    public function test_void_payment_removes_event_revenue(): void
    {
        $event = Event::factory()->published()->create();
        $invoice = $this->eventInvoice($event, [295000]);
        Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'amount' => 295000,
            'payment_date' => '2026-06-15',
            'voided_at' => '2026-06-16 09:00:00',
        ]);

        $summary = $this->summary($event);

        $this->assertSame(0, $summary['collected']);
        $this->assertSame(295000, $summary['outstanding']);

        $response = $this->actingAs($this->owner)
            ->get(route('reports.finance', ['year' => 2026, 'month' => 6]))
            ->assertOk();
        $this->assertSame(0, (int) $response->viewData('totalRevenue'));
    }

    public function test_multi_item_event_invoice_is_counted_once_in_cash_total(): void
    {
        $event = Event::factory()->published()->create();
        $invoice = $this->eventInvoice($event, [150000, 150000]);
        Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'amount' => 325000,
            'method' => Payment::METHOD_CASH,
            'payment_date' => '2026-06-15',
        ]);

        $web = $this->actingAs($this->owner)
            ->get(route('reports.finance', ['year' => 2026, 'month' => 6]))
            ->assertOk()
            ->assertSee('Mini Concert');
        $miniConcert = $web->viewData('revenueByType')->firstWhere('item_code', 'MC');
        $this->assertSame(325000, (int) $miniConcert->total);
        $this->assertSame(1, (int) $miniConcert->invoice_count);
        $this->assertSame(325000, (int) $web->viewData('totalRevenue'));

        $this->get(route('reports.finance.print', ['year' => 2026, 'month' => 6]))
            ->assertOk()
            ->assertSee('Mini Concert')
            ->assertSee('Rp 325.000');
    }

    public function test_paid_event_honor_uses_paid_at_month_for_profit_and_loss(): void
    {
        HonorSlip::factory()->create([
            'year' => 2026,
            'month' => 6,
            'base_honor' => 0,
            'event_honor' => 42500,
            'total_honor' => 42500,
            'status' => HonorSlip::STATUS_PAID,
            'paid_at' => '2026-07-02 10:00:00',
            'paid_by' => $this->owner->id,
        ]);

        $june = $this->actingAs($this->owner)
            ->get(route('reports.finance', ['year' => 2026, 'month' => 6]))
            ->assertOk();
        $this->assertSame(42500, (int) $june->viewData('totalHonor'));
        $this->assertSame(0, (int) $june->viewData('honorPaid'));
        $this->assertSame(42500, (int) $june->viewData('honorPaidForPeriod'));
        $this->assertSame(0, (int) $june->viewData('labaBersih'));

        $july = $this->get(route('reports.finance', ['year' => 2026, 'month' => 7]))
            ->assertOk();
        $this->assertSame(0, (int) $july->viewData('totalHonor'));
        $this->assertSame(42500, (int) $july->viewData('honorPaid'));
        $this->assertSame(0, (int) $july->viewData('honorPaidForPeriod'));
        $this->assertSame(-42500, (int) $july->viewData('labaBersih'));
    }

    public function test_summary_returns_billed_collected_outstanding_honor_and_cash_margin(): void
    {
        $event = Event::factory()->completed()->create();
        $first = EventParticipant::factory()->create([
            'event_id' => $event->id,
            'snapshot_mc_fee' => 295000,
            'snapshot_honor_amount' => 42500,
        ]);
        $second = EventParticipant::factory()->create([
            'event_id' => $event->id,
            'snapshot_mc_fee' => 295000,
            'snapshot_honor_amount' => 50000,
        ]);
        EventParticipant::factory()->cancelled()->create([
            'event_id' => $event->id,
            'snapshot_mc_fee' => 295000,
            'snapshot_honor_amount' => 60000,
        ]);

        $invoice = $this->eventInvoice($event, [295000, 295000], Invoice::STATUS_PARTIAL);
        Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'amount' => 300000,
            'payment_date' => '2026-06-15',
        ]);
        $voidInvoice = $this->eventInvoice($event, [295000], Invoice::STATUS_VOID);
        Payment::factory()->create([
            'invoice_id' => $voidInvoice->id,
            'amount' => 295000,
            'payment_date' => '2026-06-15',
        ]);

        EventHonorEntry::factory()->posted()->create([
            'event_id' => $event->id,
            'event_participant_id' => $first->id,
            'amount' => 42500,
        ]);
        EventHonorEntry::factory()->reversed()->create([
            'event_id' => $event->id,
            'event_participant_id' => $second->id,
            'amount' => 50000,
        ]);

        $this->assertSame([
            'target' => 590000,
            'billed' => 590000,
            'collected' => 300000,
            'outstanding' => 290000,
            'estimated_honor' => 92500,
            'final_honor' => 42500,
            'cash_margin' => 257500,
            'invoice_counts' => [
                Invoice::STATUS_UNPAID => 0,
                Invoice::STATUS_PARTIAL => 1,
                Invoice::STATUS_PAID => 0,
            ],
        ], $this->summary($event));
    }

    public function test_event_show_receives_summary_and_renders_invoice_history_links(): void
    {
        $event = Event::factory()->published()->create();
        $invoice = $this->eventInvoice($event, [295000]);

        $response = $this->actingAs($this->owner)
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertViewHas('financeSummary')
            ->assertSee(route('invoices.show', $invoice), false)
            ->assertSee($invoice->invoice_number);

        $this->assertSame(295000, $response->viewData('financeSummary')['billed']);
    }

    private function summary(Event $event): array
    {
        $this->assertTrue(class_exists(EventFinanceService::class), 'EventFinanceService belum tersedia.');

        return app(EventFinanceService::class)->summary($event);
    }

    /** @param array<int,int> $amounts */
    private function eventInvoice(Event $event, array $amounts, string $status = Invoice::STATUS_UNPAID): Invoice
    {
        $invoice = Invoice::factory()->create([
            'event_id' => $event->id,
            'total_amount' => array_sum($amounts),
            'paid_amount' => 0,
            'status' => $status,
            'year' => 2026,
            'month' => 6,
            'due_date' => '2026-06-10',
            'issued_at' => '2026-06-01',
        ]);

        foreach ($amounts as $index => $amount) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'item_code' => 'MC',
                'description' => 'Mini Concert peserta '.($index + 1),
                'amount' => $amount,
            ]);
        }

        return $invoice;
    }
}
