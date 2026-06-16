<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
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
            'scheduler_honor_calculate_enabled'           => 'true',
            'scheduler_honor_calculate_days_before_eom'   => '2',
            'scheduler_honor_calculate_time'              => '06:00',
            'scheduler_fines_apply_enabled'               => 'true',
            'scheduler_fines_apply_time'                  => '06:00',
            'scheduler_overdue_check_enabled'             => 'true',
            'scheduler_overdue_check_day'                 => '1',
            'scheduler_overdue_check_time'                => '06:05',
            'schedule_reminder_enabled'                   => 'false',
            'schedule_reminder_mode'                      => 'day_before',
            'schedule_reminder_send_time'                 => '18:00',
            'schedule_reminder_hours_before'              => '2',
        ];

        foreach ($defaults as $key => $value) {
            Setting::set($key, $value);
        }
    }

    public function test_owner_dapat_akses_halaman_pengaturan_scheduler(): void
    {
        $this->seedDefaultSchedulerSettings();

        $response = $this->actingAs($this->owner())->get(route('scheduler.edit'));
        $response->assertOk();
        $response->assertViewIs('scheduler.edit');
        $response->assertViewHas('preview');
    }

    public function test_admin_tidak_bisa_akses_halaman_pengaturan_scheduler(): void
    {
        $this->actingAs($this->admin())->get(route('scheduler.edit'))
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

        $response = $this->patchScheduler([
            'scheduler_sessions_generate_enabled'         => '1',
            'scheduler_sessions_generate_day'             => '20',
            'scheduler_sessions_generate_time'            => '07:30',

            'scheduler_invoices_generate_enabled'         => '0',
            'scheduler_invoices_generate_day'             => '1',
            'scheduler_invoices_generate_time'            => '06:00',

            'scheduler_honor_calculate_enabled'             => '1',
            'scheduler_honor_calculate_days_before_eom'   => '2',
            'scheduler_honor_calculate_time'              => '08:00',

            'scheduler_fines_apply_enabled'               => '1',
            'scheduler_fines_apply_time'                  => '06:00',

            'scheduler_overdue_check_enabled'             => '1',
            'scheduler_overdue_check_day'                 => '5',
            'scheduler_overdue_check_time'                => '06:05',

            'schedule_reminder_enabled'                   => '1',
            'schedule_reminder_mode'                      => 'same_day',
            'schedule_reminder_send_time'                 => '10:00',
            'schedule_reminder_hours_before'              => '3',
        ]);

        $response->assertRedirect(route('scheduler.edit'));
        $response->assertSessionHas('success');

        $this->assertEquals('true', Setting::get('scheduler_sessions_generate_enabled'));
        $this->assertEquals('20', Setting::get('scheduler_sessions_generate_day'));
        $this->assertEquals('07:30', Setting::get('scheduler_sessions_generate_time'));
        $this->assertEquals('false', Setting::get('scheduler_invoices_generate_enabled'));
        $this->assertEquals('5', Setting::get('scheduler_overdue_check_day'));
        $this->assertEquals('true', Setting::get('schedule_reminder_enabled'));
        $this->assertEquals('same_day', Setting::get('schedule_reminder_mode'));
        $this->assertEquals('10:00', Setting::get('schedule_reminder_send_time'));

        $this->assertDatabaseHas('audit_logs', [
            'action'       => AuditLog::ACTION_UPDATE,
            'entity_type'  => null,
            'entity_label' => 'Scheduler Settings',
        ]);
    }

    public function test_simpan_dengan_toggle_mati_tidak_wajib_day_time(): void
    {
        $this->seedDefaultSchedulerSettings();

        $this->patchScheduler([
            'scheduler_sessions_generate_enabled'         => '0',
            'scheduler_invoices_generate_enabled'         => '1',
            'scheduler_invoices_generate_day'             => '1',
            'scheduler_invoices_generate_time'            => '06:00',
            'scheduler_honor_calculate_enabled'           => '1',
            'scheduler_honor_calculate_days_before_eom'   => '2',
            'scheduler_honor_calculate_time'              => '06:00',
            'scheduler_fines_apply_enabled'               => '1',
            'scheduler_fines_apply_time'                  => '06:00',
            'scheduler_overdue_check_enabled'             => '1',
            'scheduler_overdue_check_day'                 => '1',
            'scheduler_overdue_check_time'                => '06:05',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEquals('false', Setting::get('scheduler_sessions_generate_enabled'));
        $this->assertEquals('25', Setting::get('scheduler_sessions_generate_day'));
        $this->assertEquals('06:00', Setting::get('scheduler_sessions_generate_time'));
    }

    public function test_honor_offset_dan_overdue_day_validasi(): void
    {
        $this->seedDefaultSchedulerSettings();

        $this->patchScheduler([
            'scheduler_sessions_generate_enabled'         => '1',
            'scheduler_sessions_generate_day'             => '25',
            'scheduler_sessions_generate_time'            => '06:00',
            'scheduler_invoices_generate_enabled'         => '1',
            'scheduler_invoices_generate_day'             => '1',
            'scheduler_invoices_generate_time'            => '06:00',
            'scheduler_honor_calculate_enabled'           => '1',
            'scheduler_honor_calculate_days_before_eom' => '0',
            'scheduler_honor_calculate_time'              => '06:00',
            'scheduler_fines_apply_enabled'               => '1',
            'scheduler_fines_apply_time'                  => '06:00',
            'scheduler_overdue_check_enabled'             => '1',
            'scheduler_overdue_check_day'                 => '32',
            'scheduler_overdue_check_time'                => '06:05',
            'schedule_reminder_enabled'                   => '0',
        ])->assertSessionHasErrors([
            'scheduler_honor_calculate_days_before_eom',
            'scheduler_overdue_check_day',
        ]);
    }

    public function test_simpan_konfigurasi_scheduler_invalid_ditolak(): void
    {
        $this->seedDefaultSchedulerSettings();

        $response = $this->patchScheduler([
            'scheduler_sessions_generate_enabled'         => '1',
            'scheduler_sessions_generate_day'             => '32',
            'scheduler_sessions_generate_time'            => '25:00',

            'scheduler_invoices_generate_enabled'         => '1',
            'scheduler_invoices_generate_day'             => '0',
            'scheduler_invoices_generate_time'            => '12:00',

            'scheduler_honor_calculate_enabled'           => '1',
            'scheduler_honor_calculate_days_before_eom'   => '2',
            'scheduler_honor_calculate_time'              => '06:00',
            'scheduler_fines_apply_enabled'               => '1',
            'scheduler_fines_apply_time'                  => '06:00',
            'scheduler_overdue_check_enabled'             => '1',
            'scheduler_overdue_check_day'                 => '1',
            'scheduler_overdue_check_time'                => '06:05',

            'schedule_reminder_enabled'                   => '1',
            'schedule_reminder_mode'                      => 'invalid_mode',
            'schedule_reminder_send_time'                 => '18:00',
            'schedule_reminder_hours_before'              => '25',
        ]);

        $response->assertSessionHasErrors([
            'scheduler_sessions_generate_day',
            'scheduler_sessions_generate_time',
            'scheduler_invoices_generate_day',
            'schedule_reminder_mode',
            'schedule_reminder_hours_before',
        ]);
    }
}
