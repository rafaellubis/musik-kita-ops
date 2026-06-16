<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
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

    // ===== Uji Hak Akses (Authorization) =====

    public function test_owner_dapat_akses_halaman_pengaturan_scheduler(): void
    {
        $response = $this->actingAs($this->owner())->get(route('scheduler.edit'));
        $response->assertOk();
        $response->assertViewIs('scheduler.edit');
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

    // ===== Uji Penyimpanan Setelan Valid =====

    public function test_owner_bisa_simpan_konfigurasi_scheduler_valid(): void
    {
        // Pastikan tabel settings ada data default
        Setting::set('scheduler_sessions_generate_enabled', 'true');
        Setting::set('scheduler_sessions_generate_day', '25');
        Setting::set('scheduler_sessions_generate_time', '06:00');
        Setting::set('scheduler_invoices_generate_enabled', 'true');
        Setting::set('scheduler_invoices_generate_day', '1');
        Setting::set('scheduler_invoices_generate_time', '06:00');
        Setting::set('scheduler_honor_calculate_enabled', 'true');
        Setting::set('scheduler_honor_calculate_time', '06:00');
        Setting::set('scheduler_fines_apply_enabled', 'true');
        Setting::set('scheduler_fines_apply_time', '06:00');
        Setting::set('scheduler_overdue_check_enabled', 'true');
        Setting::set('scheduler_overdue_check_time', '06:05');
        Setting::set('schedule_reminder_enabled', 'false');
        Setting::set('schedule_reminder_mode', 'day_before');
        Setting::set('schedule_reminder_send_time', '18:00');
        Setting::set('schedule_reminder_hours_before', '2');

        $response = $this->actingAs($this->owner())->patch(route('scheduler.update'), [
            'scheduler_sessions_generate_enabled' => '1',
            'scheduler_sessions_generate_day'     => '20', // ubah tanggal
            'scheduler_sessions_generate_time'    => '07:30', // ubah jam

            'scheduler_invoices_generate_enabled' => '0', // nonaktifkan generator spp
            'scheduler_invoices_generate_day'     => '1',
            'scheduler_invoices_generate_time'    => '06:00',

            'scheduler_honor_calculate_enabled' => '1',
            'scheduler_honor_calculate_time'    => '08:00', // ubah jam

            'scheduler_fines_apply_enabled' => '1',
            'scheduler_fines_apply_time'    => '06:00',

            'scheduler_overdue_check_enabled' => '1',
            'scheduler_overdue_check_time'    => '06:05',

            'schedule_reminder_enabled'       => '1', // aktifkan
            'schedule_reminder_mode'          => 'same_day', // ubah mode
            'schedule_reminder_send_time'     => '10:00', // ubah jam kirim
            'schedule_reminder_hours_before'  => '3',
        ]);

        $response->assertRedirect(route('scheduler.edit'));
        $response->assertSessionHas('success');

        // Pastikan di database terupdate
        $this->assertEquals('true', Setting::get('scheduler_sessions_generate_enabled'));
        $this->assertEquals('20', Setting::get('scheduler_sessions_generate_day'));
        $this->assertEquals('07:30', Setting::get('scheduler_sessions_generate_time'));
        
        $this->assertEquals('false', Setting::get('scheduler_invoices_generate_enabled'));

        $this->assertEquals('true', Setting::get('schedule_reminder_enabled'));
        $this->assertEquals('same_day', Setting::get('schedule_reminder_mode'));
        $this->assertEquals('10:00', Setting::get('schedule_reminder_send_time'));

        // Cek Audit Log terbuat
        $this->assertDatabaseHas('audit_logs', [
            'action'      => AuditLog::ACTION_UPDATE,
            'entity_type' => null,
            'entity_label' => 'Scheduler Settings',
        ]);
    }

    // ===== Uji Validasi Input Ditolak (Validation) =====

    public function test_simpan_konfigurasi_scheduler_invalid_ditolak(): void
    {
        $response = $this->actingAs($this->owner())->patch(route('scheduler.update'), [
            'scheduler_sessions_generate_day'     => '32', // salah (max 31)
            'scheduler_sessions_generate_time'    => '25:00', // salah (format jam)

            'scheduler_invoices_generate_day'     => '0', // salah (min 1)
            'scheduler_invoices_generate_time'    => '12:00',

            'scheduler_honor_calculate_time'    => '06:00',
            'scheduler_fines_apply_time'        => '06:00',
            'scheduler_overdue_check_time'      => '06:05',

            'schedule_reminder_enabled'       => '1',
            'schedule_reminder_mode'          => 'invalid_mode', // salah (in:day_before...)
            'schedule_reminder_send_time'     => '18:00',
            'schedule_reminder_hours_before'  => '25', // salah (max 24)
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
