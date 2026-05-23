<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\SessionGeneratorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionGeneratorConflictTest extends TestCase
{
    use RefreshDatabase;

    public function test_fase2_skip_regular_session_jika_guru_sudah_terpakai(): void
    {
        $teacher  = Teacher::factory()->create(['is_active' => true]);
        $room1    = Room::factory()->create(['capacity' => 1, 'is_active' => true]);
        $room2    = Room::factory()->create(['capacity' => 1, 'is_active' => true]);
        $package  = Package::factory()->create(['duration_min' => 30, 'class_type' => 'REGULER', 'is_active' => true]);

        // Murid 1 — sudah punya sesi SCHEDULED di Senin 15:00 bulan target
        $student1    = Student::factory()->create(['status' => 'Aktif']);
        $enrollment1 = Enrollment::factory()->create([
            'student_id' => $student1->id,
            'teacher_id' => $teacher->id,
            'package_id' => $package->id,
            'status'     => 'ACTIVE',
        ]);
        $schedule1 = Schedule::factory()->create([
            'enrollment_id' => $enrollment1->id,
            'day_of_week'   => 1, // Senin
            'start_time'    => '15:00',
            'end_time'      => '15:30',
            'room_id'       => $room1->id,
            'is_active'     => true,
        ]);

        // Buat sesi konkret murid 1 di Senin pertama bulan depan
        $targetMonth = Carbon::now()->addMonth()->startOfMonth();
        $senin       = $targetMonth->copy()->next('Monday');
        ClassSession::factory()->create([
            'schedule_id'   => $schedule1->id,
            'enrollment_id' => $enrollment1->id,
            'student_id'    => $student1->id,
            'teacher_id'    => $teacher->id,
            'session_date'  => $senin->toDateString(),
            'start_time'    => '15:00',
            'end_time'      => '15:30',
            'room_id'       => $room1->id,
            'status'        => 'SCHEDULED',
        ]);

        // Murid 2 — guru sama, jam sama, ruang beda — belum punya sesi
        $student2    = Student::factory()->create(['status' => 'Aktif']);
        $enrollment2 = Enrollment::factory()->create([
            'student_id' => $student2->id,
            'teacher_id' => $teacher->id,
            'package_id' => $package->id,
            'status'     => 'ACTIVE',
        ]);
        $schedule2 = Schedule::factory()->create([
            'enrollment_id' => $enrollment2->id,
            'day_of_week'   => 1,
            'start_time'    => '15:00',
            'end_time'      => '15:30',
            'room_id'       => $room2->id,
            'is_active'     => true,
        ]);

        // Jalankan generator untuk bulan target
        $report = app(SessionGeneratorService::class)->generateForMonth(
            $targetMonth->year,
            $targetMonth->month
        );

        // Sesi murid 2 di Senin tsb tidak boleh terbuat — konflik guru
        $konflikTerbuat = ClassSession::where('schedule_id', $schedule2->id)
            ->whereDate('session_date', $senin)
            ->exists();

        $this->assertFalse($konflikTerbuat, 'Sesi konflik seharusnya tidak dibuat');
        $this->assertGreaterThan(0, $report['skipped_conflict'], 'skipped_conflict harus bertambah');
    }
}
