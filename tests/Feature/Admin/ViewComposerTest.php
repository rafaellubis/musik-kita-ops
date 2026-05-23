<?php

namespace Tests\Feature\Admin;

use App\Models\Student;
use App\Models\User;
use App\Notifications\MuridOverdueNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Test untuk View Composer di AppServiceProvider.
 * Memastikan variabel overdueNotifs dan overdueNotifCount
 * tersedia di layouts.app saat user login.
 */
class ViewComposerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    public function test_topbar_menerima_overdueNotifs_saat_ada_notifikasi(): void
    {
        // Buat notifikasi overdue untuk admin
        $student = Student::factory()->create(['status' => 'Aktif']);
        $notif = new MuridOverdueNotification($student, 340000, 'April 2026');
        $this->admin->notify($notif);

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('overdueNotifs');
        $response->assertViewHas('overdueNotifCount', 1);
    }

    public function test_topbar_overdueNotifCount_nol_jika_tidak_ada_notifikasi(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('overdueNotifCount', 0);
    }

    public function test_notif_yang_sudah_dibaca_tidak_dihitung(): void
    {
        $student = Student::factory()->create(['status' => 'Aktif']);
        $notif = new MuridOverdueNotification($student, 340000, 'April 2026');
        $this->admin->notify($notif);
        // Tandai semua notifikasi sebagai sudah dibaca
        $this->admin->notifications()->update(['read_at' => now()]);

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('overdueNotifCount', 0);
    }
}
