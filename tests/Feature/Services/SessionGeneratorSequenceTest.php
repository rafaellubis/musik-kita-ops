<?php

namespace Tests\Feature\Services;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Holiday;
use App\Models\Package;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\SessionGeneratorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionGeneratorSequenceTest extends TestCase
{
    use RefreshDatabase;

    private SessionGeneratorService $service;
    private Teacher $teacher;
    private Package $package;
    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SessionGeneratorService::class);
        $this->teacher = Teacher::factory()->create(['is_active' => true]);
        $this->package = Package::factory()->create([
            'class_type'      => 'REGULER',
            'price_per_month' => 340000,
            'is_active'       => true,
        ]);
        $this->room = Room::factory()->create(['is_active' => true]);
    }

    private function createSchedule(int $dayOfWeek): Schedule
    {
        $student    = Student::factory()->create(['status' => 'Aktif']);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'package_id' => $this->package->id,
            'teacher_id' => $this->teacher->id,
            'status'     => 'ACTIVE',
        ]);
        $student->update(['primary_enrollment_id' => $enrollment->id]);

        return Schedule::factory()->create([
            'enrollment_id' => $enrollment->id,
            'day_of_week'   => $dayOfWeek,
            'start_time'    => '14:00:00',
            'end_time'      => '14:30:00',
            'room_id'       => $this->room->id,
            'is_active'     => true,
        ]);
    }

    /** Bulan tanpa libur → sesi ke-1,2,3,4 bernomor urut */
    public function test_sequence_tanpa_libur_adalah_1_sampai_4(): void
    {
        // Januari 2026: 5 Kamis (1,8,15,22,29) → 4 sesi (week 5 skip)
        $schedule = $this->createSchedule(Carbon::THURSDAY);

        $this->service->generateForMonth(2026, 1);

        $sessions = ClassSession::where('schedule_id', $schedule->id)
            ->orderBy('session_date')
            ->get();

        $this->assertCount(4, $sessions);
        $this->assertSame(1, $sessions[0]->session_sequence); // 1 Jan
        $this->assertSame(2, $sessions[1]->session_sequence); // 8 Jan
        $this->assertSame(3, $sessions[2]->session_sequence); // 15 Jan
        $this->assertSame(4, $sessions[3]->session_sequence); // 22 Jan
    }

    /** LIBUR tanpa replacement → sequence tetap bernomor */
    public function test_libur_tanpa_replacement_dapat_sequence(): void
    {
        // Mei 2026: Senin ke-1=4, ke-2=11, ke-3=18, ke-4=25
        $schedule = $this->createSchedule(Carbon::MONDAY);

        Holiday::create([
            'date'             => '2026-05-11',
            'name'             => 'Libur Test',
            'type'             => 'Nasional',
            'replacement_date' => null,
            'is_honor_paid'    => true,
            'is_active'        => true,
        ]);

        $this->service->generateForMonth(2026, 5);

        $libur = ClassSession::where('schedule_id', $schedule->id)
            ->where('session_date', '2026-05-11')
            ->first();

        $this->assertNotNull($libur);
        $this->assertSame('LIBUR', $libur->status);
        $this->assertSame(2, $libur->session_sequence); // Senin ke-2 = slot 2
    }

    /** LIBUR dengan replacement → LIBUR null, pengganti dapat slot LIBUR */
    public function test_libur_dengan_replacement_sequence_dan_origin(): void
    {
        // Mei 2026: Senin ke-2=11 libur, pengganti di 28 Mei
        $schedule = $this->createSchedule(Carbon::MONDAY);

        Holiday::create([
            'date'             => '2026-05-11',
            'name'             => 'Libur Nasional',
            'type'             => 'Nasional',
            'replacement_date' => '2026-05-28',
            'is_honor_paid'    => true,
            'is_active'        => true,
        ]);

        $this->service->generateForMonth(2026, 5);

        $allSessions = ClassSession::where(function ($q) use ($schedule) {
            $q->where('schedule_id', $schedule->id)
              ->orWhere('enrollment_id', $schedule->enrollment_id);
        })->orderBy('session_date')->get();

        // Senin 4 Mei → sequence 1
        $s1 = $allSessions->firstWhere('session_date', '2026-05-04');
        $this->assertSame(1, $s1->session_sequence);
        $this->assertNull($s1->origin_session_id);

        // Senin 11 Mei → LIBUR, sequence null
        $libur = $allSessions->firstWhere('session_date', '2026-05-11');
        $this->assertSame('LIBUR', $libur->status);
        $this->assertNull($libur->session_sequence);
        $this->assertNull($libur->origin_session_id);

        // Senin 18 Mei → sequence 3 (bukan 2!)
        $s3 = $allSessions->firstWhere('session_date', '2026-05-18');
        $this->assertSame(3, $s3->session_sequence);

        // Senin 25 Mei → sequence 4
        $s4 = $allSessions->firstWhere('session_date', '2026-05-25');
        $this->assertSame(4, $s4->session_sequence);

        // Kamis 28 Mei → pengganti, sequence 2, origin = LIBUR 11 Mei
        $rep = $allSessions->firstWhere('session_date', '2026-05-28');
        $this->assertNotNull($rep);
        $this->assertSame(2, $rep->session_sequence);
        $this->assertSame($libur->id, $rep->origin_session_id);
    }

    /** Idempotency: run kedua tidak ubah sequence yang sudah ada */
    public function test_idempotent_tidak_mengubah_sequence_yang_ada(): void
    {
        $schedule = $this->createSchedule(Carbon::THURSDAY);

        $this->service->generateForMonth(2026, 1);
        $this->service->generateForMonth(2026, 1); // run kedua

        $sequences = ClassSession::where('schedule_id', $schedule->id)
            ->orderBy('session_date')
            ->pluck('session_sequence')
            ->toArray();

        $this->assertSame([1, 2, 3, 4], $sequences);
    }
}
