<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Instrument;
use App\Models\Package;
use App\Models\PayrollConfig;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\SessionGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DuoClassConflictTest extends TestCase
{
    use RefreshDatabase;

    private SessionGeneratorService $generator;
    private Package $duoPackage;
    private Package $regularPackage;
    private Teacher $teacher;
    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $this->generator = app(SessionGeneratorService::class);

        PayrollConfig::create([
            'scenario_code'    => 'H_DUO',
            'scenario_name'    => 'Sesi DUO',
            'formula_type'     => 'FIXED',
            'value_or_formula' => '40000',
            'description'      => '',
            'is_active'        => true,
        ]);

        $instr = Instrument::factory()->create(['name' => 'Piano', 'code' => 'PIANO']);
        $this->duoPackage = Package::factory()->create([
            'class_type'      => 'DUO',
            'duration_min'    => 30,
            'price_per_month' => 320000,
            'instrument_id'   => $instr->id,
        ]);
        $this->regularPackage = Package::factory()->create([
            'class_type'      => 'REGULER',
            'duration_min'    => 30,
            'price_per_month' => 370000,
            'instrument_id'   => $instr->id,
        ]);
        $this->teacher = Teacher::factory()->create(['is_active' => true]);
        $this->room    = Room::factory()->create(['capacity' => 1]);
    }

    private function makeEnrollmentWithSchedule(
        string $dayName,
        string $start,
        string $end,
        string $classType = 'DUO'
    ): Enrollment {
        $pkg = $classType === 'DUO' ? $this->duoPackage : $this->regularPackage;

        $student    = Student::factory()->create(['status' => 'Aktif']);
        $enrollment = Enrollment::factory()->create([
            'student_id'     => $student->id,
            'package_id'     => $pkg->id,
            'teacher_id'     => $this->teacher->id,
            'status'         => 'ACTIVE',
            'effective_date' => '2026-06-01',
        ]);

        $dayMap = ['Senin'=>1,'Selasa'=>2,'Rabu'=>3,'Kamis'=>4,'Jumat'=>5,'Sabtu'=>6,'Minggu'=>0];
        Schedule::factory()->create([
            'enrollment_id' => $enrollment->id,
            'day_of_week'   => $dayMap[$dayName],
            'start_time'    => $start,
            'end_time'      => $end,
            'room_id'       => $this->room->id,
            'is_active'     => true,
        ]);

        return $enrollment;
    }

    /** @test */
    public function dua_duo_di_slot_sama_keduanya_dapat_sesi(): void
    {
        // Senin 10:00-10:30 — dua DUO, guru sama, ruang sama
        $this->makeEnrollmentWithSchedule('Senin', '10:00', '10:30', 'DUO');
        $this->makeEnrollmentWithSchedule('Senin', '10:00', '10:30', 'DUO');

        $result = $this->generator->generateForMonth(2026, 6);

        // Tidak boleh ada sesi yang di-skip karena konflik
        $this->assertSame(0, $result['skipped_conflict']);
        // Kedua enrollment harus punya sesi (Juni 2026 punya 4 Senin)
        $this->assertGreaterThanOrEqual(8, $result['created']);
    }

    /** @test */
    public function duo_tidak_bisa_pakai_slot_yang_sudah_ada_reguler(): void
    {
        // Reguler dulu di Senin 10:00
        $this->makeEnrollmentWithSchedule('Senin', '10:00', '10:30', 'REGULER');
        // DUO coba slot yang sama
        $this->makeEnrollmentWithSchedule('Senin', '10:00', '10:30', 'DUO');

        $result = $this->generator->generateForMonth(2026, 6);

        // DUO harus kena skip karena slot sudah dipakai non-DUO
        $this->assertGreaterThan(0, $result['skipped_conflict']);
    }
}
