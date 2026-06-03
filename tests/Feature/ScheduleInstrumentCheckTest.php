<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Instrument;
use App\Models\Package;
use App\Models\Room;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScheduleInstrumentCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);
    }

    private function ownerUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Owner');
        return $user;
    }

    /**
     * Buat setup murid Piano dengan enrollment aktif.
     * Return [$student, $pianoRoom, $drumRoom]
     */
    private function setupPianoStudent(): array
    {
        $piano = Instrument::create(['name' => 'Piano', 'code' => 'PIANO', 'is_active' => true, 'sort_order' => 1]);
        Instrument::create(['name' => 'Drum', 'code' => 'DRUM', 'is_active' => true, 'sort_order' => 2]);

        $pianoRoom = Room::create([
            'code' => 'R2', 'name' => 'Studio 2', 'capacity' => 1,
            'supported_instruments' => ['Piano', 'Gitar'],
            'is_active' => true,
        ]);
        $drumRoom = Room::create([
            'code' => 'R8', 'name' => 'Studio 8', 'capacity' => 1,
            'supported_instruments' => ['Drum'],
            'is_active' => true,
        ]);

        $package = Package::create([
            'code' => 'REG-PIANO-BASIC', 'instrument_id' => $piano->id,
            'class_type' => 'REGULER', 'grade' => 'Basic',
            'duration_min' => 30, 'price_per_month' => 340000,
            'is_active' => true, 'sort_order' => 1,
        ]);
        $teacher = Teacher::create([
            'code' => 'TCH-001', 'name' => 'Adi',
            'phone' => '08123456789', 'is_active' => true,
        ]);
        $student = Student::create([
            'student_code' => 'M-2026-0001', 'full_name' => 'Budi Santoso',
            'gender' => 'L', 'status' => 'Aktif',
        ]);
        $enrollment = Enrollment::create([
            'student_id'     => $student->id, 'package_id' => $package->id,
            'teacher_id'     => $teacher->id,
            'effective_date' => now()->toDateString(), 'status' => 'ACTIVE',
            'is_primary'     => true,
        ]);

        return [$student, $pianoRoom, $drumRoom, $enrollment];
    }

    public function test_tidak_bisa_buat_jadwal_dengan_ruangan_yang_tidak_support_instrumen(): void
    {
        $owner = $this->ownerUser();
        [$student, $pianoRoom, $drumRoom, $enrollment] = $this->setupPianoStudent();

        // Coba assign R8 (Drum) untuk murid Piano — harus ditolak
        $response = $this->actingAs($owner)->post(route('schedules.store', $student), [
            'enrollment_id' => $enrollment->id,
            'day_of_week'   => 1,
            'start_time'    => '15:00',
            'end_time'      => '15:30',
            'room_id'       => $drumRoom->id,
        ]);

        $response->assertRedirect();
        $this->assertTrue($response->isRedirect());
        $this->assertNotNull(session('error'));
        $this->assertDatabaseMissing('schedules', ['room_id' => $drumRoom->id]);
    }

    public function test_bisa_buat_jadwal_dengan_ruangan_yang_support_instrumen(): void
    {
        $owner = $this->ownerUser();
        [$student, $pianoRoom, $drumRoom, $enrollment] = $this->setupPianoStudent();

        // Assign R2 (Piano, Gitar) untuk murid Piano — harus sukses
        $response = $this->actingAs($owner)->post(route('schedules.store', $student), [
            'enrollment_id' => $enrollment->id,
            'day_of_week'   => 1,
            'start_time'    => '15:00',
            'end_time'      => '15:30',
            'room_id'       => $pianoRoom->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('schedules', [
            'room_id'     => $pianoRoom->id,
            'day_of_week' => 1,
        ]);
    }

    public function test_bisa_buat_jadwal_tanpa_ruangan(): void
    {
        // room_id opsional — tanpa ruangan tetap valid
        $owner = $this->ownerUser();
        [$student, ,, $enrollment] = $this->setupPianoStudent();

        $response = $this->actingAs($owner)->post(route('schedules.store', $student), [
            'enrollment_id' => $enrollment->id,
            'day_of_week'   => 2,
            'start_time'    => '10:00',
            'end_time'      => '10:30',
            'room_id'       => '',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('schedules', ['room_id' => null, 'day_of_week' => 2]);
    }
}
