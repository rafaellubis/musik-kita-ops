<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\IntegrationSettings;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppTransportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function configureGowa(): void
    {
        foreach ([
            'integration_whatsapp_provider' => 'gowa',
            'integration_gowa_base_url' => 'http://gowa.test:3000/gowa/',
            'integration_gowa_username' => 'studio',
            'integration_gowa_password' => 'private-password',
            'integration_gowa_device_id' => 'studio/a',
        ] as $key => $value) {
            IntegrationSettings::set($key, $value);
        }
    }

    public function test_gowa_uses_authenticated_device_scoped_text_contract(): void
    {
        $this->configureGowa();
        Http::fake(['http://gowa.test:3000/gowa/send/message' => Http::response([
            'code' => 'SUCCESS', 'message' => 'Success',
            'results' => ['message_id' => 'gowa-123', 'status' => 'Message sent'],
        ])]);
        $result = app(WhatsAppService::class)->sendText('081234567890', 'Tagihan');
        $this->assertTrue($result['ok']);
        $this->assertSame('gowa', $result['provider']);
        $this->assertSame(['gowa-123'], $result['message_ids']);
        Http::assertSent(fn ($request) => $request->url() === 'http://gowa.test:3000/gowa/send/message'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('studio:private-password'))
            && $request->hasHeader('X-Device-Id', 'studio/a')
            && $request['phone'] === '6281234567890@s.whatsapp.net'
            && $request['message'] === 'Tagihan' && !isset($request['scheduled_at']));
    }

    public function test_batch_is_frozen_but_next_batch_reads_new_configuration(): void
    {
        config(['services.fonnte.token' => 'old-token']);
        $gateway = app(WhatsAppService::class);
        $batch = $gateway->forBatch();
        $this->configureGowa();
        Http::fake([
            'https://api.fonnte.com/send' => Http::response(['status' => true, 'id' => ['old-id']]),
            'http://gowa.test:3000/gowa/send/message' => Http::response(['code' => 'SUCCESS', 'results' => ['message_id' => 'new-id']]),
        ]);
        $this->assertSame('fonnte', $batch->sendText('081234567890', 'lama')['provider']);
        $this->assertSame('gowa', $gateway->forBatch()->sendText('081234567890', 'baru')['provider']);
        Http::assertSent(fn ($r) => $r->url() === 'https://api.fonnte.com/send' && $r['target'] === '081234567890' && $r['countryCode'] === '62');
    }

    public function test_gowa_errors_do_not_leak_secrets_or_fall_back(): void
    {
        $this->configureGowa();
        foreach ([
            Http::response(['code' => 'ERROR', 'message' => 'private-password'], 200),
            Http::response('invalid-json', 200),
            Http::response(['code' => 'SUCCESS', 'results' => []], 200),
            Http::response(['message' => 'private-password'], 401),
            Http::response([], 403), Http::response([], 500),
            Http::response('', 302, ['Location' => 'https://other.test/steal']),
        ] as $response) {
            Http::swap(new \Illuminate\Http\Client\Factory);
            Http::preventStrayRequests();
            Http::fake(['http://gowa.test:3000/*' => $response]);
            $result = app(WhatsAppService::class)->sendText('081234567890', 'uji');
            $this->assertFalse($result['ok']);
            $this->assertStringNotContainsString('private-password', $result['error']);
            Http::assertSentCount(1);
        }
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('private-password'));
        $result = app(WhatsAppService::class)->sendText('081234567890', 'uji');
        $this->assertFalse($result['ok']);
        $this->assertStringNotContainsString('private-password', $result['error']);
    }

    public function test_connection_requires_both_connected_and_logged_in_and_encodes_device_path(): void
    {
        $this->configureGowa();
        foreach ([[true, true, 'connected'], [true, false, 'disconnected'], [false, true, 'disconnected']] as [$connected, $loggedIn, $expected]) {
            Http::swap(new \Illuminate\Http\Client\Factory);
            Http::preventStrayRequests();
            Http::fake(['http://gowa.test:3000/gowa/devices/studio%2Fa/status' => Http::response([
                'code' => 'SUCCESS', 'results' => ['device_id' => 'studio/a', 'is_connected' => $connected, 'is_logged_in' => $loggedIn],
            ])]);
            $this->assertSame($expected, app(WhatsAppService::class)->checkConnection()['state']);
        }
    }

    public function test_unknown_provider_does_not_silently_use_fonnte(): void
    {
        config(['services.fonnte.token' => 'available']);
        Setting::set('integration_whatsapp_provider', 'unknown');
        Http::fake();
        $this->assertFalse(app(WhatsAppService::class)->sendText('081234567890', 'uji')['ok']);
        Http::assertNothingSent();
    }
}
