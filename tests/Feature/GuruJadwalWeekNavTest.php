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

    public function test_default_menampilkan_sesi_minggu_ini_saja(): void
    {
        $thisWeekMonday = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $nextWeekMonday = $thisWeekMonday->copy()->addWeek();

        $this->createSessionForTeacher($thisWeekMonday, 'Murid Minggu Ini');
        $this->createSessionForTeacher($nextWeekMonday, 'Murid Minggu Depan');

        $response = $this->actingAs($this->guruUser)->get(route('guru.jadwal'));

        $response->assertOk()
            ->assertSee('Murid Minggu Ini', false)
            ->assertDontSee('Murid Minggu Depan', false)
            ->assertViewHas('weekStart', fn ($weekStart) => $weekStart->isSameWeek(Carbon::now()));
    }

    public function test_week_param_menampilkan_sesi_minggu_terpilih(): void
    {
        $lastWeekMonday = Carbon::now()->startOfWeek(Carbon::MONDAY)->subWeek();
        $thisWeekMonday = Carbon::now()->startOfWeek(Carbon::MONDAY);

        $this->createSessionForTeacher($lastWeekMonday, 'Murid Minggu Lalu');
        $this->createSessionForTeacher($thisWeekMonday, 'Murid Minggu Ini');

        $response = $this->actingAs($this->guruUser)
            ->get(route('guru.jadwal', ['week' => $lastWeekMonday->format('Y-m-d')]));

        $response->assertOk()
            ->assertSee('Murid Minggu Lalu', false)
            ->assertDontSee('Murid Minggu Ini', false)
            ->assertViewHas('weekStart', fn ($weekStart) => $weekStart->format('Y-m-d') === $lastWeekMonday->format('Y-m-d'));
    }

    public function test_week_param_invalid_fallback_ke_minggu_ini(): void
    {
        $response = $this->actingAs($this->guruUser)
            ->get(route('guru.jadwal', ['week' => 'not-a-date']));

        $response->assertOk()
            ->assertViewHas('weekStart', fn ($weekStart) => $weekStart->isSameWeek(Carbon::now()));
    }

    public function test_navigasi_minggu_lalu_dan_minggu_depan_ada_di_halaman(): void
    {
        $response = $this->actingAs($this->guruUser)->get(route('guru.jadwal'));

        $response->assertOk()
            ->assertSee('Minggu Lalu', false)
            ->assertSee('Minggu Depan', false)
            ->assertSee(route('guru.jadwal', [
                'week' => Carbon::now()->startOfWeek(Carbon::MONDAY)->subWeek()->format('Y-m-d'),
            ]), false)
            ->assertSee(route('guru.jadwal', [
                'week' => Carbon::now()->startOfWeek(Carbon::MONDAY)->addWeek()->format('Y-m-d'),
            ]), false);
    }

    public function test_tombol_minggu_ini_tampil_saat_bukan_minggu_berjalan(): void
    {
        $lastWeekMonday = Carbon::now()->startOfWeek(Carbon::MONDAY)->subWeek();

        $response = $this->actingAs($this->guruUser)
            ->get(route('guru.jadwal', ['week' => $lastWeekMonday->format('Y-m-d')]));

        $response->assertOk()
            ->assertSee('Minggu Ini', false)
            ->assertViewHas('isCurrentWeek', false);
    }

    public function test_tombol_minggu_ini_disembunyikan_saat_sudah_minggu_berjalan(): void
    {
        $response = $this->actingAs($this->guruUser)->get(route('guru.jadwal'));

        $response->assertOk()
            ->assertDontSee('Minggu Ini', false)
            ->assertViewHas('isCurrentWeek', true);
    }
}
