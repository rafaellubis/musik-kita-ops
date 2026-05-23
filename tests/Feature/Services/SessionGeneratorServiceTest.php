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

class SessionGeneratorServiceTest extends TestCase
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

    /**
     * Buat schedule aktif dengan enrollment + murid Aktif.
     * dayOfWeek menggunakan konstanta Carbon (0=Minggu, 1=Senin, ..., 6=Sabtu).
     */
    private function createSchedule(int $dayOfWeek, ?Package $package = null): Schedule
    {
        $pkg     = $package ?? $this->package;
        $student = Student::factory()->create(['status' => 'Aktif']);

        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'package_id' => $pkg->id,
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

    // R3: 5 occurrence, 0 libur → 4 SCHEDULED (week 5 skip)
    public function test_r3_five_occurrences_no_holiday_creates_four_scheduled(): void
    {
        // Januari 2026 punya 5 Kamis: 1, 8, 15, 22, 29
        $schedule = $this->createSchedule(Carbon::THURSDAY);

        $this->service->generateForMonth(2026, 1);

        $sessions = ClassSession::where('schedule_id', $schedule->id)->get();
        $this->assertCount(4, $sessions);
        $this->assertTrue($sessions->every(fn ($s) => $s->status === 'SCHEDULED'));
        // Kamis ke-5 (29 Jan) tidak dibuat
        $this->assertFalse(
            $sessions->contains('session_date', '2026-01-29'),
            'Week 5 seharusnya di-skip'
        );
    }

    // R4: 5 occurrence, 1 libur dengan replacement → 4 efektif
    public function test_r4_five_occurrences_holiday_with_replacement_creates_four_effective(): void
    {
        // Maret 2026 punya 5 Senin: 2, 9, 16, 23, 30
        // Libur 23 Mar → replacement 30 Mar (Senin ke-5)
        $schedule = $this->createSchedule(Carbon::MONDAY);

        Holiday::create([
            'date'             => '2026-03-23',
            'name'             => 'Idul Fitri',
            'type'             => 'Nasional',
            'is_active'        => true,
            'is_honor_paid'    => true,
            'replacement_date' => '2026-03-30',
        ]);

        $this->service->generateForMonth(2026, 3);

        $sessions = ClassSession::where('schedule_id', $schedule->id)
            ->orderBy('session_date')->get();

        $this->assertCount(5, $sessions); // 3 SCHEDULED + 1 LIBUR + 1 replacement
        $this->assertEquals('LIBUR', $sessions->firstWhere('session_date', '2026-03-23')?->status);
        $this->assertEquals('SCHEDULED', $sessions->firstWhere('session_date', '2026-03-30')?->status);
        $this->assertEquals(4, $sessions->where('status', 'SCHEDULED')->count());
    }

    // R4b: 5 occurrence, 1 libur tanpa replacement → 3 efektif, week 5 skip
    public function test_r4b_five_occurrences_holiday_without_replacement_skips_week5(): void
    {
        // Januari 2026 punya 5 Jumat: 2, 9, 16, 23, 30
        // Isra Mikraj 16 Jan (Jumat ke-3) → tanpa replacement
        $schedule = $this->createSchedule(Carbon::FRIDAY);

        Holiday::create([
            'date'          => '2026-01-16',
            'name'          => 'Isra Mikraj',
            'type'          => 'Nasional',
            'is_active'     => true,
            'is_honor_paid' => true,
        ]);

        $this->service->generateForMonth(2026, 1);

        $sessions = ClassSession::where('schedule_id', $schedule->id)->get();
        $this->assertCount(4, $sessions); // 3 SCHEDULED + 1 LIBUR
        $this->assertEquals(3, $sessions->where('status', 'SCHEDULED')->count());
        $this->assertFalse(
            $sessions->contains('session_date', '2026-01-30'),
            'Week 5 tetap di-skip meski ada libur tanpa replacement'
        );
    }

    // R5: 4 occurrence, 0 libur → 4 SCHEDULED
    public function test_r5_four_occurrences_no_holiday_creates_four_scheduled(): void
    {
        // Februari 2026 punya 4 Selasa: 3, 10, 17, 24
        $schedule = $this->createSchedule(Carbon::TUESDAY);

        $this->service->generateForMonth(2026, 2);

        $sessions = ClassSession::where('schedule_id', $schedule->id)->get();
        $this->assertCount(4, $sessions);
        $this->assertTrue($sessions->every(fn ($s) => $s->status === 'SCHEDULED'));
    }

    // R6b: 4 occurrence, 1 libur tanpa replacement → 3 efektif
    public function test_r6b_four_occurrences_holiday_without_replacement_creates_three(): void
    {
        // Februari 2026 punya 4 Selasa: 3, 10, 17, 24
        // Imlek 17 Feb (Selasa ke-3) → tanpa replacement
        $schedule = $this->createSchedule(Carbon::TUESDAY);

        Holiday::create([
            'date'          => '2026-02-17',
            'name'          => 'Imlek',
            'type'          => 'Nasional',
            'is_active'     => true,
            'is_honor_paid' => true,
        ]);

        $this->service->generateForMonth(2026, 2);

        $sessions = ClassSession::where('schedule_id', $schedule->id)->get();
        $this->assertCount(4, $sessions); // 3 SCHEDULED + 1 LIBUR
        $this->assertEquals(3, $sessions->where('status', 'SCHEDULED')->count());
    }

    // Honor: LIBUR nasional tanpa replacement → H_LIBUR + honor penuh
    public function test_libur_without_replacement_sets_full_honor(): void
    {
        $schedule = $this->createSchedule(Carbon::TUESDAY);

        Holiday::create([
            'date'          => '2026-02-17',
            'name'          => 'Imlek',
            'type'          => 'Nasional',
            'is_active'     => true,
            'is_honor_paid' => true,
        ]);

        $this->service->generateForMonth(2026, 2);

        $libur = ClassSession::where('schedule_id', $schedule->id)
            ->where('status', 'LIBUR')->first();

        $this->assertNotNull($libur);
        $this->assertEquals('H_LIBUR', $libur->honor_code);
        $this->assertEquals(42500, $libur->honor_amount); // 340000 * 0.5 / 4
    }

    // Honor: LIBUR dengan replacement_date → honor Rp 0
    public function test_libur_with_replacement_sets_zero_honor(): void
    {
        $schedule = $this->createSchedule(Carbon::MONDAY);

        Holiday::create([
            'date'             => '2026-03-23',
            'name'             => 'Idul Fitri',
            'type'             => 'Nasional',
            'is_active'        => true,
            'is_honor_paid'    => true,
            'replacement_date' => '2026-03-30',
        ]);

        $this->service->generateForMonth(2026, 3);

        $libur = ClassSession::where('schedule_id', $schedule->id)
            ->where('status', 'LIBUR')->first();

        $this->assertNull($libur->honor_code);
        $this->assertEquals(0, $libur->honor_amount);
    }

    // Honor: Internal holiday (is_honor_paid=false) → honor Rp 0
    public function test_internal_holiday_sets_zero_honor(): void
    {
        // April 2026 punya 4 Sabtu: 4, 11, 18, 25
        $schedule = $this->createSchedule(Carbon::SATURDAY);

        Holiday::create([
            'date'          => '2026-04-18',
            'name'          => 'Konser KITA',
            'type'          => 'Internal',
            'is_active'     => true,
            'is_honor_paid' => false,
        ]);

        $this->service->generateForMonth(2026, 4);

        $libur = ClassSession::where('schedule_id', $schedule->id)
            ->where('status', 'LIBUR')->first();

        $this->assertNull($libur->honor_code);
        $this->assertEquals(0, $libur->honor_amount);
    }

    // Idempotency: aman dijalankan ulang
    public function test_generator_is_idempotent(): void
    {
        $schedule = $this->createSchedule(Carbon::TUESDAY);

        $this->service->generateForMonth(2026, 2);
        $countFirst = ClassSession::where('schedule_id', $schedule->id)->count();

        $this->service->generateForMonth(2026, 2);
        $countSecond = ClassSession::where('schedule_id', $schedule->id)->count();

        $this->assertEquals($countFirst, $countSecond);
    }
}
