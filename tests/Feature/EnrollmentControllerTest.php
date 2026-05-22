<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Room;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnrollmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $owner;
    private Student $student;
    private Package $package;
    private Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

        $this->owner   = User::factory()->create()->assignRole('Owner');
        $this->admin   = User::factory()->create()->assignRole('Admin');
        $this->student = Student::factory()->create(['status' => 'Aktif']);
        $this->package = Package::factory()->create(['class_type' => 'REGULER']);
        $this->teacher = Teacher::factory()->create();
    }

    // ===== STORE =====

    public function test_admin_dapat_tambah_kelas_baru(): void
    {
        $room = Room::factory()->create();

        // Buat enrollment utama dulu
        $e1 = Enrollment::factory()->for($this->student)->create([
            'is_primary' => true, 'status' => 'ACTIVE',
        ]);
        $this->student->update(['primary_enrollment_id' => $e1->id]);

        $response = $this->actingAs($this->admin)->post(
            route('students.enrollments.store', $this->student),
            [
                'package_id'     => $this->package->id,
                'teacher_id'     => $this->teacher->id,
                'room_id'        => $room->id,
                'day_of_week'    => 1,
                'start_time'     => '16:00',
                'effective_date' => now()->addDay()->format('Y-m-d'),
                'jadikan_utama'  => false,
            ]
        );

        $response->assertRedirect();
        $this->assertEquals(2, $this->student->enrollments()->active()->count());
        // Enrollment lama tetap utama
        $this->student->refresh();
        $this->assertEquals($e1->id, $this->student->primary_enrollment_id);
    }

    public function test_tambah_kelas_dengan_jadikan_utama(): void
    {
        $room = Room::factory()->create();
        $e1 = Enrollment::factory()->for($this->student)->create([
            'is_primary' => true, 'status' => 'ACTIVE',
        ]);
        $this->student->update(['primary_enrollment_id' => $e1->id]);

        $this->actingAs($this->admin)->post(
            route('students.enrollments.store', $this->student),
            [
                'package_id'     => $this->package->id,
                'teacher_id'     => $this->teacher->id,
                'room_id'        => $room->id,
                'day_of_week'    => 3,
                'start_time'     => '14:00',
                'effective_date' => now()->addDay()->format('Y-m-d'),
                'jadikan_utama'  => true,
            ]
        );

        $this->student->refresh();
        $e1->refresh();
        $this->assertFalse((bool) $e1->is_primary);
        $this->assertNotEquals($e1->id, $this->student->primary_enrollment_id);
    }

    // ===== SET PRIMARY =====

    public function test_admin_dapat_set_enrollment_sebagai_utama(): void
    {
        $e1 = Enrollment::factory()->for($this->student)->create(['is_primary' => true,  'status' => 'ACTIVE']);
        $e2 = Enrollment::factory()->for($this->student)->create(['is_primary' => false, 'status' => 'ACTIVE']);
        $this->student->update(['primary_enrollment_id' => $e1->id]);

        $this->actingAs($this->admin)
            ->patch(route('students.enrollments.set-primary', [$this->student, $e2]));

        $this->student->refresh();
        $e1->refresh();
        $e2->refresh();
        $this->assertEquals($e2->id, $this->student->primary_enrollment_id);
        $this->assertFalse((bool) $e1->is_primary);
        $this->assertTrue((bool) $e2->is_primary);
    }

    // ===== DESTROY =====

    public function test_hentikan_kelas_non_utama(): void
    {
        $e1 = Enrollment::factory()->for($this->student)->create(['is_primary' => true,  'status' => 'ACTIVE']);
        $e2 = Enrollment::factory()->for($this->student)->create(['is_primary' => false, 'status' => 'ACTIVE']);
        $this->student->update(['primary_enrollment_id' => $e1->id]);

        $this->actingAs($this->admin)
            ->delete(route('students.enrollments.destroy', [$this->student, $e2]));

        $e2->refresh();
        $this->assertEquals('INACTIVE', $e2->status);
        $this->student->refresh();
        $this->assertEquals($e1->id, $this->student->primary_enrollment_id);
    }

    public function test_hentikan_kelas_utama_minta_konfirmasi_jika_ada_kelas_lain(): void
    {
        $e1 = Enrollment::factory()->for($this->student)->create(['is_primary' => true,  'status' => 'ACTIVE']);
        $e2 = Enrollment::factory()->for($this->student)->create(['is_primary' => false, 'status' => 'ACTIVE']);
        $this->student->update(['primary_enrollment_id' => $e1->id]);

        $response = $this->actingAs($this->admin)
            ->delete(route('students.enrollments.destroy', [$this->student, $e1]));

        $response->assertRedirect();
        $response->assertSessionHas('confirm_primary_swap');
        $e1->refresh();
        $this->assertEquals('ACTIVE', $e1->status);
    }

    public function test_hentikan_kelas_utama_dengan_konfirmasi_swap(): void
    {
        $e1 = Enrollment::factory()->for($this->student)->create(['is_primary' => true,  'status' => 'ACTIVE']);
        $e2 = Enrollment::factory()->for($this->student)->create(['is_primary' => false, 'status' => 'ACTIVE']);
        $this->student->update(['primary_enrollment_id' => $e1->id]);

        $this->actingAs($this->admin)->delete(
            route('students.enrollments.destroy', [$this->student, $e1]),
            ['new_primary_enrollment_id' => $e2->id]
        );

        $e1->refresh();
        $e2->refresh();
        $this->student->refresh();
        $this->assertEquals('INACTIVE', $e1->status);
        $this->assertEquals($e2->id, $this->student->primary_enrollment_id);
        // Pastikan enrollment pengganti benar-benar ditandai sebagai utama
        $this->assertTrue((bool) $e2->is_primary);
    }
}
