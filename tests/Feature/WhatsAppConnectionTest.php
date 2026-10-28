<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\IntegrationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatsAppConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Owner', 'Admin', 'Auditor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        Http::preventStrayRequests();
        foreach (['integration_whatsapp_provider' => 'gowa', 'integration_gowa_base_url' => 'http://gowa.test',
            'integration_gowa_username' => 'studio', 'integration_gowa_device_id' => 'studio'] as $key => $value) {
            Setting::set($key, $value);
        }
        IntegrationSettings::set('integration_gowa_password', 'private-password');
    }

    private function user(string $role): User
    {
        return User::factory()->create(['is_active' => true])->assignRole($role);
    }

    public function test_only_owner_can_run_diagnostics(): void
    {
        Http::fake();
        $this->post('/scheduler/whatsapp/check')->assertRedirect('/login');
        foreach (['Admin', 'Auditor'] as $role) {
            $this->actingAs($this->user($role))->post('/scheduler/whatsapp/check')->assertForbidden();
            $this->post('/scheduler/whatsapp/test', ['phone' => '081234567890'])->assertForbidden();
        }
        Http::assertNothingSent();
    }

    public function test_owner_checks_device_without_sending_a_message(): void
    {
        Http::fake(['http://gowa.test/devices/studio/status' => Http::response([
            'code' => 'SUCCESS', 'results' => ['device_id' => 'studio', 'is_connected' => true, 'is_logged_in' => true],
        ])]);
        $this->actingAs($this->user('Owner'))->post('/scheduler/whatsapp/check')
            ->assertRedirect(route('scheduler.edit'))->assertSessionHas('whatsapp_result.state', 'connected');
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($r) => $r->method() === 'POST');
    }

    public function test_owner_test_uses_saved_configuration_and_records_safe_audit_only(): void
    {
        Http::fake(['http://gowa.test/send/message' => Http::response(['code' => 'SUCCESS', 'results' => ['message_id' => 'test-id']])]);
        $this->actingAs($this->user('Owner'))->post('/scheduler/whatsapp/test', [
            'phone' => '081234567890', 'provider' => 'fonnte', 'base_url' => 'http://evil.test',
        ])->assertRedirect(route('scheduler.edit'))->assertSessionHas('whatsapp_result.state', 'sent')
            ->assertSessionHas('whatsapp_result.message_ids', ['test-id']);
        Http::assertSent(fn ($r) => $r['phone'] === '6281234567890@s.whatsapp.net');
        Http::assertSentCount(1);
        $this->assertDatabaseCount('invoice_reminder_logs', 0);
        $this->assertDatabaseCount('schedule_reminder_logs', 0);
        $this->assertDatabaseCount('session_report_wa_logs', 0);
        $audit = AuditLog::where('entity_label', 'WhatsApp Test')->sole();
        $this->assertSame('gowa', $audit->new_values['provider']);
        $this->assertStringNotContainsString('private-password', json_encode($audit->new_values));
    }

    public function test_invalid_number_and_provider_failure_do_not_send_twice_or_leak_password(): void
    {
        Http::fake(['http://gowa.test/send/message' => Http::response(['code' => 'ERROR', 'message' => 'private-password'])]);
        $this->actingAs($this->user('Owner'))->post('/scheduler/whatsapp/test', ['phone' => '123'])->assertSessionHasErrors('phone');
        Http::assertNothingSent();
        $response = $this->post('/scheduler/whatsapp/test', ['phone' => '081234567890']);
        $response->assertSessionHas('whatsapp_result.state', 'failed');
        $this->assertStringNotContainsString('private-password', json_encode(session('whatsapp_result')));
        Http::assertSentCount(1);
    }

    public function test_non_string_phone_is_rejected_without_contacting_provider(): void
    {
        Http::fake();
        $this->actingAs($this->user('Owner'))->post('/scheduler/whatsapp/test', [
            'phone' => ['081234567890'],
        ])->assertSessionHasErrors('phone');
        Http::assertNothingSent();
    }
}
