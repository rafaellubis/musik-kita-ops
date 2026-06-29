<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AbsensiPrintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Buat role Spatie yang dibutuhkan
        Role::create(['name' => 'Owner']);
        Role::create(['name' => 'Admin']);
        Role::create(['name' => 'Auditor']);
    }

    public function test_guest_cannot_access_print_page(): void
    {
        $response = $this->get(route('absensi.print'));
        $response->assertRedirect(route('login'));
    }

    public function test_authorized_roles_can_access_print_page(): void
    {
        $roles = ['Owner', 'Admin', 'Auditor'];

        foreach ($roles as $roleName) {
            $user = User::factory()->create();
            $user->assignRole($roleName);

            $response = $this->actingAs($user)->get(route('absensi.print'));
            $response->assertStatus(200);
        }
    }

    public function test_print_page_renders_sessions_correctly(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        // Buat mock data sesi
        $teacher = \App\Models\Teacher::factory()->create(['name' => 'THOMAS']);
        $student1 = \App\Models\Student::factory()->create(['full_name' => 'Budi Santoso']);
        $student2 = \App\Models\Student::factory()->create(['full_name' => 'Susi Susanti']);
        $room = \App\Models\Room::factory()->create(['code' => 'R1', 'name' => 'STUDIO 1']);
        
        $package = \App\Models\Package::factory()->create();
        $enrollment1 = \App\Models\Enrollment::factory()->create([
            'student_id' => $student1->id,
            'package_id' => $package->id,
            'teacher_id' => $teacher->id,
            'status' => 'ACTIVE',
        ]);
        $enrollment2 = \App\Models\Enrollment::factory()->create([
            'student_id' => $student2->id,
            'package_id' => $package->id,
            'teacher_id' => $teacher->id,
            'status' => 'ACTIVE',
        ]);

        // Sesi 1: SCHEDULED
        \App\Models\ClassSession::factory()->create([
            'enrollment_id' => $enrollment1->id,
            'student_id' => $student1->id,
            'teacher_id' => $teacher->id,
            'room_id' => $room->id,
            'session_date' => '2026-06-29',
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'status' => \App\Models\ClassSession::STATUS_SCHEDULED,
        ]);

        // Sesi 2: LIBUR
        \App\Models\ClassSession::factory()->create([
            'enrollment_id' => $enrollment2->id,
            'student_id' => $student2->id,
            'teacher_id' => $teacher->id,
            'room_id' => $room->id,
            'session_date' => '2026-06-29',
            'start_time' => '11:00:00',
            'end_time' => '11:30:00',
            'status' => \App\Models\ClassSession::STATUS_LIBUR,
        ]);

        $response = $this->actingAs($user)->get(route('absensi.print', ['date' => '2026-06-29']));
        
        $response->assertStatus(200);
        $response->assertSee('Budi Santoso');
        $response->assertSee('Susi Susanti');
        // Sesi SCHEDULED harus me-render garis bawah kosong
        $response->assertSee('......');
        // Sesi LIBUR harus me-render tulisan LIBUR
        $response->assertSee('LIBUR');
        // Memastikan ada teks footer penanggung jawab
        $response->assertSee('Dibuat oleh,');
        $response->assertSee('Admin Musik KITA');
    }
}

