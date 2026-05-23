<?php

namespace Tests\Feature\Admin;

use App\Models\Student;
use App\Models\User;
use App\Notifications\MuridOverdueNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Admin',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Owner',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);

        $this->admin = User::factory()->create()->assignRole('Admin');
    }

    /** Guest tidak bisa akses route notifikasi. */
    public function test_guest_tidak_bisa_mark_read(): void
    {
        $fakeId = \Illuminate\Support\Str::uuid()->toString();

        $this->postJson(route('notifications.read', $fakeId))
             ->assertUnauthorized();
    }

    /** Mark satu notifikasi sebagai dibaca. */
    public function test_mark_satu_notif_as_read(): void
    {
        $this->admin->notify(new MuridOverdueNotification(
            $this->makeStudent(),
            340000,
            'Mei 2026'
        ));

        $notif = $this->admin->unreadNotifications()->first();
        $this->assertNotNull($notif);

        $this->actingAs($this->admin)
             ->postJson(route('notifications.read', $notif->id))
             ->assertOk()
             ->assertJson(['success' => true]);

        $this->assertNotNull($notif->fresh()->read_at);
    }

    /** Mark semua notifikasi sebagai dibaca. */
    public function test_mark_semua_notif_as_read(): void
    {
        $student = $this->makeStudent();

        $this->admin->notify(new MuridOverdueNotification($student, 340000, 'Mei 2026'));
        $this->admin->notify(new MuridOverdueNotification($student, 450000, 'April 2026'));

        $this->assertEquals(2, $this->admin->unreadNotifications()->count());

        $this->actingAs($this->admin)
             ->postJson(route('notifications.read-all'))
             ->assertOk()
             ->assertJson(['success' => true]);

        $this->assertEquals(0, $this->admin->unreadNotifications()->count());
    }

    /** Admin tidak bisa mark notif milik user lain. */
    public function test_tidak_bisa_mark_notif_user_lain(): void
    {
        $owner = User::factory()->create()->assignRole('Owner');
        $owner->notify(new MuridOverdueNotification($this->makeStudent(), 340000, 'Mei 2026'));

        $notifMilikOwner = $owner->unreadNotifications()->first();

        $this->actingAs($this->admin)
             ->postJson(route('notifications.read', $notifMilikOwner->id))
             ->assertNotFound();
    }

    /** Helper buat student dummy tanpa simpan ke DB. */
    private function makeStudent(): Student
    {
        $s = new Student();
        $s->id           = 99;
        $s->full_name    = 'Test Murid';
        $s->student_code = 'M-2025-0099';
        return $s;
    }
}
