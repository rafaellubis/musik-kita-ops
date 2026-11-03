<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Room;
use App\Models\Schedule;
use App\Services\ScheduleConflictDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ScheduleConflictTimeTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\DataProvider('timeBoundaries')]
    public function test_mixed_time_formats_keep_half_open_slot_boundaries(string $start, string $end, bool $storedSeconds, ?string $expected): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-03 12:00:00'));
        $room = Room::factory()->create(['capacity' => 1]);
        $enrollment = Enrollment::factory()->create(['status' => 'ACTIVE', 'effective_date' => '2026-01-01']);
        $suffix = $storedSeconds ? ':00' : '';
        $before = Schedule::factory()->for($enrollment)->create([
            'room_id' => $room->id, 'day_of_week' => 4, 'is_active' => true,
            'start_time' => '18:30'.$suffix, 'end_time' => '19:00'.$suffix,
        ]);
        $after = Schedule::factory()->for($enrollment)->create([
            'room_id' => $room->id, 'day_of_week' => 4, 'is_active' => true,
            'start_time' => '19:30'.$suffix, 'end_time' => '20:00'.$suffix,
        ]);
        $ids = $expected ? [($expected === 'before' ? $before : $after)->id] : [];
        $detector = app(ScheduleConflictDetector::class);

        $this->assertSame($ids, $detector->findTeacherConflicts($enrollment->teacher_id, 4, $start, $end)->pluck('id')->all());
        $this->assertSame($ids, $detector->findRoomConflicts($room->id, 4, $start, $end)->pluck('id')->all());
        $this->assertSame($ids, $detector->findStudentConflicts($enrollment->student_id, 4, $start, $end)->pluck('id')->all());
        $this->assertSame($expected !== null, $detector->isRoomFull($room->id, 4, $start, $end));
        if ($expected) {
            $this->assertSame([], $detector->findTeacherConflicts($enrollment->teacher_id, 4, $start, $end, $ids[0])->all());
        }

        // Pengalihan/kenaikan memakai pemeriksaan penugasan yang sama, bukan endpoint tambah jadwal.
        $candidate = Enrollment::factory()->create(['status' => 'ACTIVE', 'effective_date' => '2026-01-01']);
        try {
            $detector->assertAssignmentAvailable($candidate, [
                'teacher_id' => $enrollment->teacher_id, 'room_id' => $room->id,
                'day_of_week' => 4, 'start_time' => $start, 'end_time' => $end,
            ], today());
            $this->assertNull($expected, 'Slot yang benar-benar overlap harus ditolak.');
        } catch (ValidationException $exception) {
            $this->assertNotNull($expected, 'Slot berurutan tidak boleh dianggap bentrok.');
            $this->assertArrayHasKey('start_time', $exception->errors());
        }
    }

    public static function timeBoundaries(): array
    {
        return [
            'form minutes between stored seconds' => ['19:00', '19:30', true, null],
            'stored minutes between request seconds' => ['19:00:00', '19:30:00', false, null],
            'real 45 minute conflict' => ['19:00', '19:45', true, 'after'],
            'overlap earlier session' => ['18:45', '19:15', true, 'before'],
            'touch earlier start' => ['18:00', '18:30', true, null],
            'preserve nonzero seconds' => ['18:59:59', '19:00:00', true, 'before'],
            'same starting time' => ['19:30', '20:00', true, 'after'],
        ];
    }
}
