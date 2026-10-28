<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsappMessageTemplate;
use App\Services\IntegrationSettings;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SchedulerSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Owner',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Guru',    'guard_name' => 'web']);
        config(['services.fonnte.token' => 'test-fonnte-token']);

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    private function owner(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Owner');

        return $user;
    }

    private function admin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Admin');

        return $user;
    }

    /** Seed semua key scheduler dengan nilai default. */
    private function seedDefaultSchedulerSettings(): void
    {
        $defaults = [
            'scheduler_sessions_generate_enabled'         => 'true',
            'scheduler_sessions_generate_day'             => '25',
            'scheduler_sessions_generate_time'            => '06:00',
            'scheduler_invoices_generate_enabled'         => 'true',
            'scheduler_invoices_generate_day'             => '1',
            'scheduler_invoices_generate_time'            => '06:00',
            'spp_due_day'                                 => '10',
            'scheduler_honor_calculate_enabled'           => 'true',
            'scheduler_honor_calculate_day'               => '28',
            'scheduler_honor_calculate_time'              => '06:00',
            'scheduler_fines_apply_enabled'               => 'true',
            'scheduler_fines_apply_time'                  => '06:00',
            'scheduler_fines_start_day'                   => '11',
            'scheduler_overdue_check_enabled'             => 'true',
            'scheduler_overdue_check_day'                 => '1',
            'scheduler_overdue_check_time'                => '06:05',
            'scheduler_invoice_reminder_enabled'          => 'false',
            'scheduler_invoice_reminder_days_before_due'  => '3',
            'scheduler_invoice_reminder_time'             => '10:00',
            'scheduler_invoice_reminder_template_id'      => '',
            'scheduler_cuti_return_enabled'               => 'true',
            'scheduler_cuti_return_time'                  => '23:59',
            'scheduler_staff_payroll_enabled'             => 'true',
            'scheduler_staff_payroll_day'                 => '28',
            'scheduler_staff_payroll_time'                => '07:00',
            'schedule_reminder_enabled'                   => 'false',
            'schedule_reminder_mode'                      => 'day_before',
            'schedule_reminder_send_time'                 => '18:00',
            'schedule_reminder_hours_before'              => '2',
            'schedule_reminder_poll_minutes'              => '15',
            'integration_fonnte_base_url'                 => 'https://api.fonnte.com',
            'integration_wablas_base_url'                 => 'https://solo.wablas.com',
        ];

        foreach ($defaults as $key => $value) {
            Setting::set($key, $value);
        }
    }

    /** Payload minimal valid untuk PATCH scheduler. */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'scheduler_sessions_generate_enabled'         => '1',
            'scheduler_sessions_generate_day'             => '25',
            'scheduler_sessions_generate_time'            => '06:00',
            'scheduler_invoices_generate_enabled'         => '1',
            'scheduler_invoices_generate_day'             => '1',
            'scheduler_invoices_generate_time'            => '06:00',
            'spp_due_day'                                 => '10',
            'scheduler_honor_calculate_enabled'           => '1',
            'scheduler_honor_calculate_day'               => '28',
            'scheduler_honor_calculate_time'              => '06:00',
            'scheduler_fines_apply_enabled'               => '1',
            'scheduler_fines_apply_time'                  => '06:00',
            'scheduler_fines_start_day'                   => '11',
            'scheduler_overdue_check_enabled'             => '1',
            'scheduler_overdue_check_day'                 => '1',
            'scheduler_overdue_check_time'                => '06:05',
            'scheduler_invoice_reminder_enabled'          => '0',
            'scheduler_cuti_return_enabled'               => '1',
            'scheduler_cuti_return_time'                  => '23:59',
            'scheduler_staff_payroll_enabled'             => '1',
            'scheduler_staff_payroll_day'                 => '28',
            'scheduler_staff_payroll_time'                => '07:00',
            'schedule_reminder_enabled'                   => '0',
            'integration_fonnte_base_url'                 => 'https://api.fonnte.com',
            'integration_wablas_base_url'                 => 'https://solo.wablas.com',
        ], $overrides);
    }

    public function test_owner_dapat_akses_halaman_pengaturan_scheduler(): void
    {
        $this->seedDefaultSchedulerSettings();

        $response = $this->actingAs($this->owner())->get(route('scheduler.edit'));
        $response->assertOk();
        $response->assertViewIs('scheduler.edit');
        $response->assertViewHas('preview');
        $response->assertViewHas('whatsappTemplates');
    }

    public function test_admin_tidak_bisa_akses_halaman_pengaturan_scheduler(): void
    {
        $this->actingAs($this->admin())->get(route('scheduler.edit'))
            ->assertForbidden();
    }

    public function test_admin_tidak_bisa_simpan_pengaturan_scheduler(): void
    {
        $this->seedDefaultSchedulerSettings();

        $this->actingAs($this->admin())
            ->patch(route('scheduler.update'), $this->validPayload())
            ->assertForbidden();
    }

    public function test_non_logged_in_user_diarahkan_ke_login(): void
    {
        $this->get(route('scheduler.edit'))
            ->assertRedirect(route('login'));
    }

    /** PATCH scheduler sebagai Owner (CSRF disabled di setUp). */
    private function patchScheduler(array $data)
    {
        return $this->actingAs($this->owner())->patch(route('scheduler.update'), $data);
    }

    public function test_owner_bisa_simpan_konfigurasi_scheduler_valid(): void
    {
        $this->seedDefaultSchedulerSettings();

        $response = $this->patchScheduler($this->validPayload([
            'scheduler_sessions_generate_day'             => '20',
            'scheduler_sessions_generate_time'            => '07:30',
            'scheduler_invoices_generate_enabled'         => '0',
            'scheduler_overdue_check_day'                 => '5',
            'scheduler_honor_calculate_day'               => '27',
            'scheduler_fines_start_day'                   => '12',
            'spp_due_day'                                 => '10',
            'schedule_reminder_enabled'                   => '1',
            'schedule_reminder_mode'                      => 'same_day',
            'schedule_reminder_send_time'                 => '10:00',
            'schedule_reminder_hours_before'              => '3',
            'schedule_reminder_poll_minutes'              => '20',
        ]));

        $response->assertRedirect(route('scheduler.edit'));
        $response->assertSessionHas('success');

        $this->assertEquals('true', Setting::get('scheduler_sessions_generate_enabled'));
        $this->assertEquals('20', Setting::get('scheduler_sessions_generate_day'));
        $this->assertEquals('07:30', Setting::get('scheduler_sessions_generate_time'));
        $this->assertEquals('false', Setting::get('scheduler_invoices_generate_enabled'));
        $this->assertEquals('5', Setting::get('scheduler_overdue_check_day'));
        $this->assertEquals('27', Setting::get('scheduler_honor_calculate_day'));
        $this->assertEquals('12', Setting::get('scheduler_fines_start_day'));
        $this->assertEquals('10', Setting::get('spp_due_day'));
        $this->assertEquals('true', Setting::get('schedule_reminder_enabled'));
        $this->assertEquals('same_day', Setting::get('schedule_reminder_mode'));
        $this->assertEquals('10:00', Setting::get('schedule_reminder_send_time'));
        $this->assertEquals('20', Setting::get('schedule_reminder_poll_minutes'));

        $this->assertDatabaseHas('audit_logs', [
            'action'       => AuditLog::ACTION_UPDATE,
            'entity_type'  => null,
            'entity_label' => 'Scheduler Settings',
        ]);
    }

    public function test_simpan_kredensial_wa_terenkripsi_dan_audit_masked(): void
    {
        $this->seedDefaultSchedulerSettings();

        $this->patchScheduler($this->validPayload([
            'integration_fonnte_token' => 'my-fonnte-secret-token',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('my-fonnte-secret-token', IntegrationSettings::fonnteToken());

        $log = AuditLog::query()->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('********oken', $log->new_values['integration_fonnte_token'] ?? null);
    }

    public function test_kosongkan_field_kredensial_tidak_overwrite(): void
    {
        $this->seedDefaultSchedulerSettings();
        IntegrationSettings::set('integration_fonnte_token', 'existing-token');

        $this->patchScheduler($this->validPayload([
            'integration_fonnte_token' => '',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('existing-token', IntegrationSettings::fonnteToken());
    }

    public function test_simpan_dengan_toggle_mati_tidak_wajib_day_time(): void
    {
        $this->seedDefaultSchedulerSettings();

        $this->patchScheduler($this->validPayload([
            'scheduler_sessions_generate_enabled' => '0',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEquals('false', Setting::get('scheduler_sessions_generate_enabled'));
        $this->assertEquals('25', Setting::get('scheduler_sessions_generate_day'));
        $this->assertEquals('06:00', Setting::get('scheduler_sessions_generate_time'));
    }

    public function test_honor_day_dan_overdue_day_validasi(): void
    {
        $this->seedDefaultSchedulerSettings();

        $this->patchScheduler($this->validPayload([
            'scheduler_honor_calculate_day' => '0',
            'scheduler_overdue_check_day'   => '32',
        ]))->assertSessionHasErrors([
            'scheduler_honor_calculate_day',
            'scheduler_overdue_check_day',
        ]);
    }

    public function test_invoice_reminder_days_before_due_harus_kurang_dari_tempo(): void
    {
        $this->seedDefaultSchedulerSettings();

        $template = WhatsappMessageTemplate::create([
            'code'       => WhatsappMessageTemplate::CODE_INVOICE_REMINDER,
            'name'       => 'Reminder Tagihan',
            'body'       => 'Halo {nama_ortu}',
            'is_active'  => true,
            'sort_order' => 1,
        ]);

        $this->patchScheduler($this->validPayload([
            'spp_due_day'                                => '5',
            'scheduler_invoice_reminder_enabled'         => '1',
            'scheduler_invoice_reminder_days_before_due' => '5',
            'scheduler_invoice_reminder_time'            => '10:00',
            'scheduler_invoice_reminder_template_id'     => (string) $template->id,
        ]))->assertSessionHasErrors(['scheduler_invoice_reminder_days_before_due']);
    }

    public function test_simpan_konfigurasi_scheduler_invalid_ditolak(): void
    {
        $this->seedDefaultSchedulerSettings();

        $response = $this->patchScheduler($this->validPayload([
            'scheduler_sessions_generate_day'  => '32',
            'scheduler_sessions_generate_time' => '25:00',
            'scheduler_invoices_generate_day'  => '0',
            'schedule_reminder_enabled'        => '1',
            'schedule_reminder_mode'           => 'invalid_mode',
            'schedule_reminder_send_time'      => '18:00',
            'schedule_reminder_hours_before'   => '25',
            'integration_fonnte_base_url'    => 'not-a-url',
        ]));

        $response->assertSessionHasErrors([
            'scheduler_sessions_generate_day',
            'scheduler_sessions_generate_time',
            'scheduler_invoices_generate_day',
            'schedule_reminder_mode',
            'schedule_reminder_hours_before',
            'integration_fonnte_base_url',
        ]);
    }
    public function test_gowa_password_changes_even_when_mask_is_identical_and_survives_blank_input(): void
    {
        IntegrationSettings::set('integration_gowa_password', 'first-secret-1234');
        $payload = $this->validPayload([
            'integration_whatsapp_provider' => 'gowa',
            'integration_gowa_base_url' => 'http://gowa.test:3000/gowa/',
            'integration_gowa_username' => 'studio',
            'integration_gowa_password' => 'second-secret-1234',
            'integration_gowa_device_id' => 'studio/a',
        ]);
        $this->patchScheduler($payload)->assertSessionHasNoErrors();
        $raw = Setting::get('integration_gowa_password');
        $this->assertNotSame('second-secret-1234', $raw);
        $this->assertSame('second-secret-1234', \Illuminate\Support\Facades\Crypt::decryptString($raw));
        $this->assertSame('gowa', Setting::get('integration_whatsapp_provider'));

        $payload['integration_gowa_password'] = '';
        unset($payload['integration_fonnte_base_url']);
        $this->patchScheduler($payload)->assertSessionHasNoErrors();
        $this->assertSame($raw, Setting::get('integration_gowa_password'));
        $this->assertSame('https://api.fonnte.com', IntegrationSettings::fonnteBaseUrl());
        $log = AuditLog::query()->where('entity_label', 'Scheduler Settings')->first();
        $this->assertStringNotContainsString('second-secret', json_encode($log->new_values));
    }

    public function test_selected_provider_requires_effective_credentials_and_rejects_unsafe_url(): void
    {
        $this->patchScheduler($this->validPayload([
            'integration_whatsapp_provider' => 'gowa',
            'integration_gowa_base_url' => 'http://user:password@gowa.test/gowa?token=secret#fragment',
            'integration_gowa_username' => 'studio',
            'integration_gowa_device_id' => 'studio',
        ]))->assertSessionHasErrors(['integration_gowa_base_url', 'integration_gowa_password']);
        $this->assertNull(Setting::get('integration_whatsapp_provider'));
    }

    public function test_invalid_configuration_never_flashes_secrets(): void
    {
        $this->patchScheduler($this->validPayload([
            'integration_whatsapp_provider' => 'invalid',
            'integration_gowa_password' => 'do-not-flash-this',
            'integration_fonnte_token' => 'do-not-flash-token',
        ]))->assertSessionHasErrors('integration_whatsapp_provider');
        $this->assertArrayNotHasKey('integration_gowa_password', session()->getOldInput());
        $this->assertArrayNotHasKey('integration_fonnte_token', session()->getOldInput());
    }

    public function test_configuration_failure_rolls_back_provider_and_audit(): void
    {
        Setting::set('integration_whatsapp_provider', 'fonnte');
        $dispatcher = clone Setting::getEventDispatcher();
        Setting::saving(function (Setting $setting) {
            if ($setting->key === 'integration_gowa_username') {
                throw new \RuntimeException('simulated write failure');
            }
        });
        try {
            $this->patchScheduler($this->validPayload([
                'integration_whatsapp_provider' => 'gowa',
                'integration_gowa_base_url' => 'http://gowa.test:3000',
                'integration_gowa_username' => 'studio',
                'integration_gowa_password' => 'secret',
                'integration_gowa_device_id' => 'studio',
            ]))->assertStatus(500);
            $this->assertSame('fonnte', Setting::get('integration_whatsapp_provider'));
            $this->assertNull(Setting::get('integration_gowa_base_url'));
            $this->assertDatabaseMissing('audit_logs', ['entity_label' => 'Scheduler Settings']);
        } finally {
            Setting::setEventDispatcher($dispatcher);
        }
    }
}
