<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\InvoiceReminderPanelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsInvoiceReminderPanel;
use Tests\TestCase;

class InvoiceReminderPanelHistoryTest extends TestCase
{
    use RefreshDatabase, BuildsInvoiceReminderPanel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPanel();
    }

    public function test_history_only_contains_source_logs_and_keeps_historical_content(): void
    {
        $text = "  Pesan lama\n<script>jangan dijalankan</script>  ";
        $first = $this->panelLog([$this->source->id], 'SUCCESS', $text);
        $shared = $this->panelLog([$this->source->id, $this->sibling->id], 'PARTIAL');
        $this->panelLog([$this->sibling->id], 'FAILED');
        $this->student->update(['parent_phone' => '081111222333']);
        $data = $this->actingAs($this->owner)->getJson(route('invoices.reminder.history', $this->source))->assertOk()->json('data');
        $this->assertSame([$shared->id, $first->id], array_column($data, 'id'));
        $this->assertSame($text, $data[1]['message_body']);
        $this->assertSame('6281234567890', $data[1]['phone']);
        $this->assertSame('PARTIAL', $data[0]['status']);
        $this->assertSame([$this->source->id, $this->sibling->id], array_column($data[0]['invoices'], 'id'));
    }

    public function test_history_paginates_without_losing_or_repeating_logs(): void
    {
        $ids = [];
        for ($i = 0; $i < 21; $i++) $ids[] = $this->panelLog([$this->source->id])->id;
        $url = route('invoices.reminder.history', $this->source);
        $page1 = $this->actingAs($this->owner)->getJson($url)->assertOk()->assertJsonPath('total', 21)->json('data');
        $page2 = $this->getJson($url.'?page=2')->assertOk()->json('data');
        $this->assertSame(array_reverse($ids), [...array_column($page1, 'id'), ...array_column($page2, 'id')]);
        $this->assertCount(20, $page1);
        $this->getJson($url.'?page=0')->assertUnprocessable();
    }

    public function test_prepare_resend_excludes_paid_siblings_and_rejects_unrelated_log(): void
    {
        $shared = $this->panelLog([$this->source->id, $this->sibling->id, 999999]);
        $this->sibling->update(['status' => 'PAID', 'paid_amount' => 390000]);
        $data = $this->panelPreview(['resend_log_id' => $shared->id]);
        $this->assertSame([$this->source->id], array_column($data['selected_invoices'], 'id'));
        $this->assertSame([$this->sibling->id, 999999], array_column($data['excluded_invoices'], 'id'));
        $otherLog = $this->panelLog([$this->sibling->id]);
        $this->actingAs($this->owner)->postJson(route('invoices.reminder.preview', $this->source), ['resend_log_id' => $otherLog->id])->assertUnprocessable();
        $this->source->update(['status' => 'VOID']);
        $this->getJson(route('invoices.reminder.history', $this->source))->assertOk()->assertJsonPath('data.0.can_prepare_resend', false);
    }

    public function test_metadata_handles_partial_paid_and_missing_logs_without_per_invoice_queries(): void
    {
        $this->panelLog([$this->source->id]);
        $last = $this->panelLog([$this->source->id], 'PARTIAL');
        $this->source->update(['status' => 'PAID', 'paid_amount' => 340000]);
        $service = app(InvoiceReminderPanelService::class);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $one = $service->metadataFor([$this->source->id]);
        $firstQueries = DB::getQueryLog();
        DB::flushQueryLog();
        $many = $service->metadataFor([$this->source->id, $this->sibling->id, ...range(100, 149)]);
        $manyQueries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertSame('PARTIAL', $one[$this->source->id]['status']);
        $this->assertNull($many[$this->sibling->id]['status']);
        $this->assertSame(count($firstQueries), count($manyQueries));
        $this->assertLessThanOrEqual(2, count($manyQueries));
        foreach ($manyQueries as $query) {
            $this->assertStringNotContainsString('message_body', $query['query']);
            $this->assertStringNotContainsString('phone', $query['query']);
        }
    }

    public function test_auditor_and_guru_cannot_read_private_message_history_or_send(): void
    {
        foreach (['Auditor', 'Guru'] as $role) {
            $user = User::factory()->create()->assignRole($role);
            $this->actingAs($user)->getJson(route('invoices.reminder.history', $this->source))->assertForbidden();
            $this->postJson(route('invoices.reminder.send', $this->source), [])->assertForbidden();
        }
    }
}
