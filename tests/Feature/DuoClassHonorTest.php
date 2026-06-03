<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Instrument;
use App\Models\Package;
use App\Models\PayrollConfig;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DuoClassHonorTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceService $service;
    private Package $duoPackage;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $this->service = app(AttendanceService::class);

        PayrollConfig::create([
            'scenario_code'    => 'H_DUO',
            'scenario_name'    => 'Sesi DUO Terlaksana',
            'formula_type'     => 'FIXED',
            'value_or_formula' => '40000',
            'description'      => 'Honor guru DUO per murid per sesi.',
            'is_active'        => true,
        ]);

        $instr = Instrument::factory()->create(['name' => 'Piano', 'code' => 'PIANO']);
        $this->duoPackage = Package::factory()->create([
            'class_type'      => 'DUO',
            'duration_min'    => 30,
            'price_per_month' => 320000,
            'instrument_id'   => $instr->id,
        ]);
    }

    private function makeDuoSession(): ClassSession
    {
        $teacher    = Teacher::factory()->create(['is_active' => true]);
        $student    = Student::factory()->create(['status' => 'Aktif']);
        $room       = Room::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'package_id' => $this->duoPackage->id,
            'teacher_id' => $teacher->id,
            'status'     => 'ACTIVE',
        ]);
        $schedule = Schedule::factory()->create([
            'enrollment_id' => $enrollment->id,
            'room_id'       => $room->id,
        ]);
        return ClassSession::factory()->create([
            'schedule_id'   => $schedule->id,
            'enrollment_id' => $enrollment->id,
            'student_id'    => $student->id,
            'teacher_id'    => $teacher->id,
            'room_id'       => $room->id,
            'status'        => 'SCHEDULED',
        ]);
    }

    /** @test */
    public function sesi_duo_hadir_menghasilkan_H_DUO_40000(): void
    {
        $session = $this->makeDuoSession();
        $result  = $this->service->recordAttendance($session, ['status' => 'HADIR']);

        $this->assertSame('H_DUO', $result->honor_code);
        $this->assertSame(40000, $result->honor_amount);
    }

    /** @test */
    public function sesi_duo_hangus_tetap_dapat_honor_penuh(): void
    {
        $session = $this->makeDuoSession();
        $result  = $this->service->recordAttendance($session, ['status' => 'HANGUS']);

        $this->assertSame('H_DUO', $result->honor_code);
        $this->assertSame(40000, $result->honor_amount);
    }

    /** @test */
    public function sesi_duo_izin_reschedule_honor_nol(): void
    {
        $session = $this->makeDuoSession();
        $result  = $this->service->recordAttendance($session, ['status' => 'IZIN_RESCHEDULE']);

        $this->assertNull($result->honor_code);
        $this->assertSame(0, $result->honor_amount);
    }

    /** @test */
    public function rate_H_DUO_terbaca_dari_payroll_config(): void
    {
        PayrollConfig::where('scenario_code', 'H_DUO')->update(['value_or_formula' => '50000']);

        $session = $this->makeDuoSession();
        $result  = $this->service->recordAttendance($session, ['status' => 'HADIR']);

        $this->assertSame('H_DUO', $result->honor_code);
        $this->assertSame(50000, $result->honor_amount);
    }

    /** @test */
    public function sesi_duo_trial_hangus_menghasilkan_TRIAL_NS_nol(): void
    {
        $teacher    = Teacher::factory()->create(['is_active' => true]);
        $student    = Student::factory()->create(['status' => 'Trial']);
        $room       = Room::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'package_id' => $this->duoPackage->id,
            'teacher_id' => $teacher->id,
            'status'     => 'TRIAL',
        ]);
        $schedule = Schedule::factory()->create([
            'enrollment_id' => $enrollment->id,
            'room_id'       => $room->id,
        ]);
        $session = ClassSession::factory()->create([
            'schedule_id'   => $schedule->id,
            'enrollment_id' => $enrollment->id,
            'student_id'    => $student->id,
            'teacher_id'    => $teacher->id,
            'room_id'       => $room->id,
            'status'        => 'SCHEDULED',
        ]);

        $result = $this->service->recordAttendance($session, ['status' => 'HANGUS']);

        $this->assertSame('TRIAL_NS', $result->honor_code);
        $this->assertSame(0, $result->honor_amount);
    }

    /**
     * @test
     * DIGANTI sekarang two-phase: honor pending (null) saat assign, H_PENG setelah dikonfirmasi.
     * Test ini verifikasi bahwa recordAttendance set honor_code = null (pending).
     */
    public function sesi_duo_diganti_menghasilkan_honor_pending_sampai_dikonfirmasi(): void
    {
        $teacher    = Teacher::factory()->create(['is_active' => true]);
        $substitute = Teacher::factory()->create(['is_active' => true]);
        $student    = Student::factory()->create(['status' => 'Aktif']);
        $room       = Room::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'package_id' => $this->duoPackage->id,
            'teacher_id' => $teacher->id,
            'status'     => 'ACTIVE',
        ]);
        $schedule = Schedule::factory()->create([
            'enrollment_id' => $enrollment->id,
            'room_id'       => $room->id,
        ]);
        $session = ClassSession::factory()->create([
            'schedule_id'   => $schedule->id,
            'enrollment_id' => $enrollment->id,
            'student_id'    => $student->id,
            'teacher_id'    => $teacher->id,
            'room_id'       => $room->id,
            'status'        => 'SCHEDULED',
        ]);

        // Phase 1: assign pengganti — honor pending (null) sampai dikonfirmasi
        $result = $this->service->recordAttendance($session, [
            'status'                => 'DIGANTI',
            'substitute_teacher_id' => $substitute->id,
        ]);

        $this->assertNull($result->honor_code, 'DIGANTI honor_code harus null (pending konfirmasi)');
        $this->assertSame(0, $result->honor_amount);

        // Phase 2: konfirmasi hadir via calculateSubstituteHonor — harus H_PENG dengan rate DUO
        $result->loadMissing(['enrollment.package']);
        $honor = $this->service->calculateSubstituteHonor($result);
        $this->assertSame('H_PENG', $honor['code']);
        $this->assertSame(40000, $honor['amount']);
    }
}
