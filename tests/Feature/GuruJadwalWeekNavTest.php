<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Instrument;
use App\Models\Package;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuruJadwalWeekNavTest extends TestCase
{
    use RefreshDatabase;

    private User $guruUser;
    private Teacher $teacher;
    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Guru', 'guard_name' => 'web']);

        $this->guruUser = User::factory()->create(['email_verified_at' => now()]);
        $this->guruUser->assignRole('Guru');
        $this->teacher = Teacher::factory()->create(['user_id' => $this->guruUser->id]);

        $instr = Instrument::factory()->create(['name' => 'Piano', 'code' => 'PIANO']);
        $this->package = Package::factory()->create([
            'class_type'    => 'REGULER',
            'instrument_id' => $instr->id,
            'duration_min'  => 30,
        ]);
    }

    private function createSessionForTeacher(Carbon $date, string $studentName): ClassSession
    {
        $student = Student::factory()->create(['full_name' => $studentName]);
        $enroll  = Enrollment::factory()->create([
            'student_id' => $student->id,
            'teacher_id' => $this->teacher->id,
            'package_id' => $this->package->id,
            'status'     => 'ACTIVE',
        ]);

        return ClassSession::factory()->create([
            'enrollment_id' => $enroll->id,
            'student_id'    => $student->id,
            'teacher_id'    => $this->teacher->id,
            'session_date'  => $date->toDateString(),
            'status'        => 'SCHEDULED',
        ]);
    }

    public function test_default_menampilkan_sesi_hari_ini_saja(): void
    {
        $today    = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        $this->createSessionForTeacher($today, 'Murid Hari Ini');
        $this->createSessionForTeacher($tomorrow, 'Murid Besok');

        $response = $this->actingAs($this->guruUser)->get(route('guru.jadwal'));

        $response->assertOk()
            ->assertSee('Murid Hari Ini', false)
            ->assertDontSee('Murid Besok', false)
            ->assertViewHas('tanggal', $today->toDateString());
    }

    public function test_date_param_menampilkan_sesi_tanggal_terpilih(): void
    {
        $yesterday = Carbon::yesterday();
        $today     = Carbon::today();

        $this->createSessionForTeacher($yesterday, 'Murid Kemarin');
        $this->createSessionForTeacher($today, 'Murid Hari Ini');

        $response = $this->actingAs($this->guruUser)
            ->get(route('guru.jadwal', ['date' => $yesterday->format('Y-m-d')]));

        $response->assertOk()
            ->assertSee('Murid Kemarin', false)
            ->assertDontSee('Murid Hari Ini', false)
            ->assertViewHas('tanggal', $yesterday->toDateString());
    }

    public function test_date_param_invalid_fallback_ke_hari_ini(): void
    {
        $response = $this->actingAs($this->guruUser)
            ->get(route('guru.jadwal', ['date' => 'not-a-date']));

        $response->assertOk()
            ->assertViewHas('tanggal', Carbon::today()->toDateString());
    }

    public function test_kalender_strip_bootstrap_91_hari(): void
    {
        $response = $this->actingAs($this->guruUser)->get(route('guru.jadwal'));

        $response->assertOk()
            ->assertSee('guruJadwalCalendar', false)
            ->assertViewHas('calendarBootstrap', fn ($data) =>
                count($data['dates']) === (45 * 2 + 1)
            );
    }

    public function test_session_counts_ada_di_bootstrap(): void
    {
        $today = Carbon::today();
        $this->createSessionForTeacher($today, 'Murid A');
        $this->createSessionForTeacher($today, 'Murid B');

        $response = $this->actingAs($this->guruUser)->get(route('guru.jadwal'));

        $response->assertOk()
            ->assertViewHas('calendarBootstrap', fn ($data) =>
                ($data['sessionCounts'][$today->toDateString()] ?? 0) === 2
            );
    }

    public function test_tidak_ada_panah_navigasi_minggu(): void
    {
        $response = $this->actingAs($this->guruUser)->get(route('guru.jadwal'));

        $response->assertOk()
            ->assertDontSee('Minggu sebelumnya', false)
            ->assertDontSee('Minggu berikutnya', false);
    }
}
