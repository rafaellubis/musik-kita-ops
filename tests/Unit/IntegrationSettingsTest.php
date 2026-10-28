<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\IntegrationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class IntegrationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_encrypt_decrypt_round_trip_for_fonnte_token(): void
    {
        IntegrationSettings::set('integration_fonnte_token', 'secret-token-123');

        $this->assertSame('secret-token-123', IntegrationSettings::fonnteToken());
    }

    public function test_falls_back_to_config_when_db_empty(): void
    {
        foreach ([
            'integration_fonnte_token',
            'integration_fonnte_base_url',
            'integration_wablas_token',
            'integration_wablas_secret_key',
            'integration_wablas_base_url',
        ] as $key) {
            Setting::set($key, '');
        }

        Config::set('services.fonnte.token', 'env-fonnte-token');
        Config::set('services.fonnte.base_url', 'https://env.fonnte.com');
        Config::set('services.wablas.token', 'env-wablas-token');
        Config::set('services.wablas.secret_key', 'env-wablas-secret');
        Config::set('services.wablas.base_url', 'https://env.wablas.com');

        $this->assertSame('env-fonnte-token', IntegrationSettings::fonnteToken());
        $this->assertSame('https://env.fonnte.com', IntegrationSettings::fonnteBaseUrl());
        $this->assertSame('env-wablas-token', IntegrationSettings::wablasToken());
        $this->assertSame('env-wablas-secret', IntegrationSettings::wablasSecretKey());
        $this->assertSame('https://env.wablas.com', IntegrationSettings::wablasBaseUrl());
    }

    public function test_db_value_takes_precedence_over_config(): void
    {
        Config::set('services.fonnte.token', 'env-token');
        IntegrationSettings::set('integration_fonnte_token', 'db-token');

        $this->assertSame('db-token', IntegrationSettings::fonnteToken());
    }

    public function test_short_secret_is_not_fully_exposed_in_ui_or_audit(): void
    {
        $this->assertStringNotContainsString('abcd', IntegrationSettings::maskSecret('abcd'));
        IntegrationSettings::set('integration_gowa_password', 'abcd');
        $this->assertStringNotContainsString('abcd', IntegrationSettings::maskValueForAudit(
            'integration_gowa_password', Setting::get('integration_gowa_password')
        ));
    }

    public function test_is_masked_placeholder_detects_preview_values(): void
    {
        $this->assertTrue(IntegrationSettings::isMaskedPlaceholder('********c123'));
        $this->assertTrue(IntegrationSettings::isMaskedPlaceholder('••••••••c123'));
        $this->assertFalse(IntegrationSettings::isMaskedPlaceholder('real-new-token'));
        $this->assertFalse(IntegrationSettings::isMaskedPlaceholder(''));
    }

    public function test_from_env_detection_when_db_empty(): void
    {
        Config::set('services.fonnte.token', 'env-fonnte');
        Config::set('services.wablas.token', 'env-wablas');
        Config::set('services.wablas.secret_key', 'env-secret');

        $this->assertFalse(IntegrationSettings::isFonnteTokenStoredInDb());
        $this->assertFalse(IntegrationSettings::isWablasTokenStoredInDb());
        $this->assertFalse(IntegrationSettings::isWablasSecretStoredInDb());
    }

    public function test_from_db_detection_when_stored(): void
    {
        IntegrationSettings::set('integration_fonnte_token', 'db-fonnte');
        IntegrationSettings::set('integration_wablas_token', 'db-wablas');
        IntegrationSettings::set('integration_wablas_secret_key', 'db-secret');

        $this->assertTrue(IntegrationSettings::isFonnteTokenStoredInDb());
        $this->assertTrue(IntegrationSettings::isWablasTokenStoredInDb());
        $this->assertTrue(IntegrationSettings::isWablasSecretStoredInDb());
    }

    public function test_stored_value_is_encrypted_in_database(): void
    {
        IntegrationSettings::set('integration_wablas_secret_key', 'super-secret');

        $raw = Setting::get('integration_wablas_secret_key');
        $this->assertNotSame('super-secret', $raw);
        $this->assertSame('super-secret', Crypt::decryptString($raw));
    }
}
