<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\InvoiceReminderLog;
use App\Models\User;
use App\Models\WhatsappMessageTemplate;
use App\Services\InvoiceReminderPanelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsInvoiceReminderPanel;
use Tests\TestCase;

class InvoiceReminderPanelSendTest extends TestCase
{
    use RefreshDatabase, BuildsInvoiceReminderPanel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPanel();
    }

    private function payload(): array
    {
        return ['preview_token' => $this->panelPreview()['preview_token'], 'message_body' => "  Halo 🎵\n\n<strong>Tagihan</strong>\n  ", 'confirm_repeat' => false];
    }

    public function test_final_message_is_preserved_and_replaying_does_not_send_twice(): void
    {
        Http::fake(['https://api.fonnte.com/send' => Http::response(['status' => true, 'id' => ['panel-1']])]);
        $payload = $this->payload();
        WhatsappMessageTemplate::query()->update(['body' => 'Template baru']);
        $url = route('invoices.reminder.send', $this->source);
        $first = $this->postJson($url, $payload)->assertOk()->assertJsonPath('state', 'sent');
        $this->postJson($url, $payload)->assertOk()->assertJsonPath('log_id', $first->json('log_id'));
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['message'] === $payload['message_body']);
        $log = InvoiceReminderLog::sole();
        $this->assertSame($payload['message_body'], $log->message_body);
        $this->assertSame([$this->source->id], $log->invoice_ids);
        $this->assertSame($this->owner->id, $log->sent_by);
        $this->assertSame(['panel-1'], $log->provider_message_ids);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertSame('Template baru', WhatsappMessageTemplate::first()->body);
        $this->postJson($url, [...$payload, 'message_body' => 'Pesan berbeda'])->assertConflict()->assertJsonPath('code', 'request_conflict');
    }

    public function test_changed_invoice_item_with_same_total_stops_delivery(): void
    {
        $payload = $this->payload();
        $this->source->items()->first()->update(['description' => 'Rincian diganti']);
        $this->postJson(route('invoices.reminder.send', $this->source), $payload)->assertConflict()->assertJsonPath('code', 'preview_changed');
        Http::assertNothingSent();
    }

    public function test_changed_balance_status_phone_and_due_date_stop_delivery(): void
    {
        foreach ([['paid_amount' => 1000], ['status' => 'PAID'], ['status' => 'VOID'], ['due_date' => '2026-11-11']] as $changes) {
            $payload = $this->payload();
            $this->source->update($changes);
            $this->postJson(route('invoices.reminder.send', $this->source), $payload)->assertConflict();
            $this->source->update(['paid_amount' => 0, 'status' => 'UNPAID', 'due_date' => '2026-11-10']);
            $this->travel(61)->seconds();
        }
        $payload = $this->payload();
        $this->student->update(['parent_phone' => '081111222333']);
        $this->postJson(route('invoices.reminder.send', $this->source), $payload)->assertConflict();
        Http::assertNothingSent();
    }

    public function test_untrusted_or_expired_context_and_message_boundaries_are_rejected(): void
    {
        $payload = $this->payload();
        $url = route('invoices.reminder.send', $this->source);
        foreach ([' ', str_repeat('x', 5001)] as $message) {
            $this->postJson($url, [...$payload, 'message_body' => $message])->assertUnprocessable();
        }
        $this->postJson($url, [...$payload, 'phone' => '081111111111'])->assertUnprocessable();
        $this->travel(61)->seconds();
        $admin = User::factory()->create()->assignRole('Admin');
        $this->actingAs($admin)->postJson($url, $payload)->assertForbidden();
        $this->actingAs($this->owner)->postJson(route('invoices.reminder.send', $this->sibling), $payload)->assertConflict();
        $this->travel(31)->minutes();
        $this->postJson($url, $payload)->assertConflict()->assertJsonPath('code', 'preview_expired');
        Http::assertNothingSent();
    }

    public function test_repeat_success_requires_confirmation_but_failed_attempt_does_not(): void
    {
        Http::fake(['https://api.fonnte.com/send' => Http::response(['status' => true, 'id' => ['repeat-1']])]);
        $this->panelLog([$this->source->id]);
        $this->panelLog([$this->source->id], 'FAILED');
        $payload = $this->payload();
        $url = route('invoices.reminder.send', $this->source);
        $this->postJson($url, $payload)->assertConflict()->assertJsonPath('code', 'repeat_confirmation_required');
        Http::assertNothingSent();
        $this->postJson($url, [...$payload, 'confirm_repeat' => true])->assertOk()->assertJsonPath('state', 'sent');
    }

    public function test_in_flight_request_cannot_reclaim_the_same_token(): void
    {
        $payload = $this->payload();
        Http::fake(['https://api.fonnte.com/send' => function () use ($payload) {
            try {
                app(InvoiceReminderPanelService::class)->send($this->source, $this->owner, $payload['preview_token'], $payload['message_body']);
                $this->fail('Permintaan aktif tidak boleh mengirim ulang.');
            } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
                $this->assertSame('request_in_progress', $e->getResponse()->getData(true)['code']);
            }
            return Http::response(['status' => true, 'id' => ['once']]);
        }]);
        $this->postJson(route('invoices.reminder.send', $this->source), $payload)->assertOk();
        Http::assertSentCount(1);
        $this->assertDatabaseCount('invoice_reminder_logs', 1);
    }

    public function test_connection_failure_is_unknown_and_is_not_automatically_retried(): void
    {
        $payload = $this->payload();
        Http::fake(['https://api.fonnte.com/send' => fn () => throw new ConnectionException('secret-provider-error')]);
        $url = route('invoices.reminder.send', $this->source);
        $this->postJson($url, $payload)->assertOk()->assertJsonPath('state', 'unknown')->assertDontSee('secret-provider-error');
        $this->postJson($url, $payload)->assertOk()->assertJsonPath('state', 'unknown');
        $this->assertDatabaseCount('invoice_reminder_logs', 1);
    }

    public function test_provider_rejection_is_failed_without_exposing_raw_response(): void
    {
        $payload = $this->payload();
        Http::fake(['https://api.fonnte.com/send' => Http::response(['status' => false, 'reason' => 'secret-body'], 400)]);
        $this->postJson(route('invoices.reminder.send', $this->source), $payload)->assertOk()->assertJsonPath('state', 'failed')->assertDontSee('secret-body');
        $this->assertSame('FAILED', InvoiceReminderLog::sole()->status);
    }

    public function test_cache_loss_fails_closed(): void
    {
        $payload = $this->payload();
        Cache::flush();
        $this->postJson(route('invoices.reminder.send', $this->source), $payload)->assertConflict();
        Http::assertNothingSent();
    }

    public function test_log_failure_after_dispatch_keeps_action_claimed(): void
    {
        $payload = $this->payload();
        Http::fake(['https://api.fonnte.com/send' => Http::response(['status' => true, 'id' => ['logged-1']])]);
        InvoiceReminderLog::creating(fn () => throw new \RuntimeException('secret-database-error'));
        try {
            $url = route('invoices.reminder.send', $this->source);
            $this->postJson($url, $payload)->assertOk()->assertJsonPath('state', 'unknown')->assertDontSee('secret-database-error');
            $this->postJson($url, $payload)->assertOk()->assertJsonPath('state', 'unknown');
            Http::assertSentCount(1);
        } finally {
            InvoiceReminderLog::flushEventListeners();
        }
    }
    public function test_non_string_message_is_a_validation_error_not_a_server_error(): void
    {
        $payload = $this->payload();
        $this->postJson(route('invoices.reminder.send', $this->source), [...$payload, 'message_body' => ['invalid']])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_cache_claim_write_failure_never_dispatches(): void
    {
        $payload = $this->payload();
        $cache = \Mockery::mock(Cache::getFacadeRoot());
        $cache->shouldReceive('put')->andReturn(false);
        Cache::swap($cache);
        $this->postJson(route('invoices.reminder.send', $this->source), $payload)->assertStatus(503);
        Http::assertNothingSent();
    }

}
