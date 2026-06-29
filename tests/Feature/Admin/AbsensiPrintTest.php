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
        Role::create(['name' => 'Guru']);
    }

    public function test_guest_cannot_access_print_page(): void
    {
        $response = $this->get(route('absensi.print'));
        $response->assertRedirect(route('login'));
    }

    public function test_unauthorized_roles_cannot_access_print_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');

        $response = $this->actingAs($user)->get(route('absensi.print'));
        $response->assertStatus(403);
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

    public function test_print_page_filters_by_teacher_id(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        $thomas = \App\Models\Teacher::factory()->create(['name' => 'THOMAS', 'is_active' => true]);
        $adi = \App\Models\Teacher::factory()->create(['name' => 'ADI', 'is_active' => true]);

        $student = \App\Models\Student::factory()->create();
        $room = \App\Models\Room::factory()->create(['code' => 'R1']);
        $package = \App\Models\Package::factory()->create();

        $enrollmentThomas = \App\Models\Enrollment::factory()->create([
            'student_id' => $student->id,
            'package_id' => $package->id,
            'teacher_id' => $thomas->id,
            'status' => 'ACTIVE',
        ]);
        $enrollmentAdi = \App\Models\Enrollment::factory()->create([
            'student_id' => $student->id,
            'package_id' => $package->id,
            'teacher_id' => $adi->id,
            'status' => 'ACTIVE',
        ]);

        // Sesi 1: Utama Thomas, no substitute (Thomas mengajar)
        \App\Models\ClassSession::factory()->create([
            'enrollment_id' => $enrollmentThomas->id,
            'student_id' => $student->id,
            'teacher_id' => $thomas->id,
            'room_id' => $room->id,
            'session_date' => '2026-06-29',
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'status' => \App\Models\ClassSession::STATUS_SCHEDULED,
            'notes' => 'Sesi Thomas Asli',
        ]);

        // Sesi 2: Utama Adi, no substitute (Adi mengajar)
        \App\Models\ClassSession::factory()->create([
            'enrollment_id' => $enrollmentAdi->id,
            'student_id' => $student->id,
            'teacher_id' => $adi->id,
            'room_id' => $room->id,
            'session_date' => '2026-06-29',
            'start_time' => '11:00:00',
            'end_time' => '11:30:00',
            'status' => \App\Models\ClassSession::STATUS_SCHEDULED,
            'notes' => 'Sesi Adi Asli',
        ]);

        // Sesi 3: Utama Adi, substitute Thomas (Thomas mengajar)
        \App\Models\ClassSession::factory()->create([
            'enrollment_id' => $enrollmentAdi->id,
            'student_id' => $student->id,
            'teacher_id' => $adi->id,
            'substitute_teacher_id' => $thomas->id,
            'room_id' => $room->id,
            'session_date' => '2026-06-29',
            'start_time' => '12:00:00',
            'end_time' => '12:30:00',
            'status' => \App\Models\ClassSession::STATUS_SCHEDULED,
            'notes' => 'Sesi Adi diganti Thomas',
        ]);

        // Sesi 4: Utama Thomas, substitute Adi (Adi mengajar)
        \App\Models\ClassSession::factory()->create([
            'enrollment_id' => $enrollmentThomas->id,
            'student_id' => $student->id,
            'teacher_id' => $thomas->id,
            'substitute_teacher_id' => $adi->id,
            'room_id' => $room->id,
            'session_date' => '2026-06-29',
            'start_time' => '13:00:00',
            'end_time' => '13:30:00',
            'status' => \App\Models\ClassSession::STATUS_SCHEDULED,
            'notes' => 'Sesi Thomas diganti Adi',
        ]);

        // Test filter untuk Thomas (Harus ada Sesi 1 & 3, tidak ada Sesi 2 & 4)
        $responseThomas = $this->actingAs($user)->get(route('absensi.print', [
            'date' => '2026-06-29',
            'teacher_id' => $thomas->id,
        ]));
        $responseThomas->assertStatus(200);
        $responseThomas->assertSee('Sesi Thomas Asli');
        $responseThomas->assertSee('Sesi Adi diganti Thomas');
        $responseThomas->assertDontSee('Sesi Adi Asli');
        $responseThomas->assertDontSee('Sesi Thomas diganti Adi');

        // Test filter untuk Adi (Harus ada Sesi 2 & 4, tidak ada Sesi 1 & 3)
        $responseAdi = $this->actingAs($user)->get(route('absensi.print', [
            'date' => '2026-06-29',
            'teacher_id' => $adi->id,
        ]));
        $responseAdi->assertStatus(200);
        $responseAdi->assertSee('Sesi Adi Asli');
        $responseAdi->assertSee('Sesi Thomas diganti Adi');
        $responseAdi->assertDontSee('Sesi Thomas Asli');
        $responseAdi->assertDontSee('Sesi Adi diganti Thomas');
    }

    public function test_print_page_displays_selected_teacher_name_in_header(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        $thomas = \App\Models\Teacher::factory()->create(['name' => 'THOMAS_TEST', 'is_active' => true]);

        $response = $this->actingAs($user)->get(route('absensi.print', [
            'date' => '2026-06-29',
            'teacher_id' => $thomas->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Daftar Absensi Harian (Guru: THOMAS_TEST)');
    }

    public function test_print_page_renders_teacher_dropdown(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        \App\Models\Teacher::factory()->create(['name' => 'THOMAS_DROPDOWN', 'is_active' => true]);
        \App\Models\Teacher::factory()->create(['name' => 'ADI_DROPDOWN', 'is_active' => true]);

        $response = $this->actingAs($user)->get(route('absensi.print', [
            'date' => '2026-06-29',
        ]));

        $response->assertStatus(200);
        $response->assertSee('id="print-filter-guru"', false);
        $response->assertSee('THOMAS_DROPDOWN');
        $response->assertSee('ADI_DROPDOWN');
    }

    public function test_index_page_renders_print_button_and_teacher_dropdown(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        $response = $this->actingAs($user)->get(route('absensi.index'));

        $response->assertStatus(200);
        $response->assertSee('id="btn-cetak-absensi"', false);
        $response->assertSee('id="filter-guru"', false);
    }
}


