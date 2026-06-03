<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Instrument;
use App\Models\Package;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuruUpdateAbsensiTest extends TestCase
{
    use RefreshDatabase;

    private User $guruUser;
    private Teacher $teacher;
    private ClassSession $sesiHariIni;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Guru', 'guard_name' => 'web']);

        $this->guruUser = User::factory()->create(['email_verified_at' => now()]);
        $this->guruUser->assignRole('Guru');
        $this->teacher = Teacher::factory()->create(['user_id' => $this->guruUser->id]);

        $instr   = Instrument::factory()->create(['name' => 'Piano', 'code' => 'PIANO']);
        $package = Package::factory()->create([
            'class_type'    => 'REGULER',
            'instrument_id' => $instr->id,
            'duration_min'  => 30,
        ]);
        $student  = Student::factory()->create();
        $room     = Room::factory()->create();
        $enroll   = Enrollment::factory()->create([
            'student_id' => $student->id,
            'teacher_id' => $this->teacher->id,
            'package_id' => $package->id,
            'status'     => 'ACTIVE',
        ]);
        $schedule = Schedule::factory()->create([
            'enrollment_id' => $enroll->id,
            'day_of_week'   => now()->dayOfWeek,
            'room_id'       => $room->id,
        ]);

        $this->sesiHariIni = ClassSession::factory()->create([
            'schedule_id'   => $schedule->id,
            'enrollment_id' => $enroll->id,
            'student_id'    => $student->id,
            'teacher_id'    => $this->teacher->id,
            'session_date'  => today()->toDateString(),
            'status'        => 'SCHEDULED',
            // honor_code dan honor_amount null — belum diisi sebelum absensi
        ]);
    }

    /**
     * Guru pengganti (substitute_teacher_id) boleh konfirmasi hadir pada sesi DIGANTI.
     */
    public function test_guru_pengganti_bisa_konfirmasi_hadir_diganti(): void
    {
        $guruAsli = Teacher::factory()->create();
        $instr    = Instrument::factory()->create(['name' => 'Vocal', 'code' => 'VOC']);
        $package  = Package::factory()->create([
            'class_type'    => 'REGULER',
            'instrument_id' => $instr->id,
            'duration_min'  => 30,
            'price_per_month' => 400000,
        ]);
        $student = Student::factory()->create();
        $enroll  = Enrollment::factory()->create([
            'student_id' => $student->id,
            'teacher_id' => $guruAsli->id,
            'package_id' => $package->id,
            'status'     => 'ACTIVE',
        ]);
        $sesi = ClassSession::factory()->create([
            'enrollment_id'         => $enroll->id,
            'student_id'            => $student->id,
            'teacher_id'            => $guruAsli->id,
            'substitute_teacher_id' => $this->teacher->id,
            'session_date'          => today()->toDateString(),
            'status'                => 'DIGANTI',
            'honor_code'            => null,
            'honor_amount'          => 0,
        ]);

        $this->actingAs($this->guruUser)
            ->post(route('guru.absensi.confirm-substitute', $sesi), ['action' => 'hadir'])
            ->assertRedirect();

        $sesi->refresh();
        $this->assertSame('H_PENG', $sesi->honor_code);
        $this->assertGreaterThan(0, $sesi->honor_amount);
        $this->assertSame('DIGANTI', $sesi->status);
    }

    /** Guru asli tidak boleh konfirmasi atas nama pengganti */
    public function test_guru_asli_tidak_bisa_konfirmasi_diganti_milik_pengganti(): void
    {
        $pengganti = Teacher::factory()->create();
        $instr     = Instrument::factory()->create(['name' => 'Drum', 'code' => 'DRM']);
        $package   = Package::factory()->create([
            'class_type'    => 'REGULER',
            'instrument_id' => $instr->id,
            'duration_min'  => 30,
        ]);
        $student = Student::factory()->create();
        $enroll  = Enrollment::factory()->create([
            'student_id' => $student->id,
            'teacher_id' => $this->teacher->id,
            'package_id' => $package->id,
            'status'     => 'ACTIVE',
        ]);
        $sesi = ClassSession::factory()->create([
            'enrollment_id'         => $enroll->id,
            'student_id'            => $student->id,
            'teacher_id'            => $this->teacher->id,
            'substitute_teacher_id' => $pengganti->id,
            'session_date'          => today()->toDateString(),
            'status'                => 'DIGANTI',
            'honor_code'            => null,
        ]);

        $this->actingAs($this->guruUser)
            ->post(route('guru.absensi.confirm-substitute', $sesi), ['action' => 'hadir'])
            ->assertStatus(403);
    }

    public function test_guru_bisa_set_hadir(): void
    {
        $this->actingAs($this->guruUser)
            ->patch(route('guru.absensi.update', $this->sesiHariIni), ['status' => 'HADIR'])
            ->assertRedirect();

        $this->assertEquals('HADIR', $this->sesiHariIni->fresh()->status);
    }

    public function test_guru_bisa_set_hadir_terlambat_dengan_menit(): void
    {
        $this->actingAs($this->guruUser)
            ->patch(route('guru.absensi.update', $this->sesiHariIni), [
                'status'       => 'HADIR_TERLAMBAT',
                'late_minutes' => 10,
            ])
            ->assertRedirect();

        $sesi = $this->sesiHariIni->fresh();
        $this->assertEquals('HADIR_TERLAMBAT', $sesi->status);
        $this->assertEquals(10, $sesi->late_minutes);
    }

    public function test_guru_pengganti_bisa_input_absensi(): void
    {
        $guruLain = Teacher::factory()->create();
        $sesiPengganti = ClassSession::factory()->create([
            'teacher_id'            => $guruLain->id,
            'substitute_teacher_id' => $this->teacher->id,
            'student_id'            => $this->sesiHariIni->student_id,
            'session_date'          => today()->toDateString(),
            'status'                => 'SCHEDULED',
        ]);

        $this->actingAs($this->guruUser)
            ->patch(route('guru.absensi.update', $sesiPengganti), ['status' => 'HADIR'])
            ->assertRedirect();

        $this->assertEquals('HADIR', $sesiPengganti->fresh()->status);
    }

    public function test_guru_tidak_bisa_update_sesi_guru_lain(): void
    {
        $guruLain = Teacher::factory()->create();
        $sesiLain = ClassSession::factory()->create([
            'teacher_id'   => $guruLain->id,
            'session_date' => today()->toDateString(),
            'status'       => 'SCHEDULED',
        ]);

        $this->actingAs($this->guruUser)
            ->patch(route('guru.absensi.update', $sesiLain), ['status' => 'HADIR'])
            ->assertForbidden();
    }

    public function test_guru_tidak_bisa_update_sesi_kemarin(): void
    {
        $sesiKemarin = ClassSession::factory()->create([
            'teacher_id'   => $this->teacher->id,
            'session_date' => today()->subDay()->toDateString(),
            'status'       => 'SCHEDULED',
        ]);

        $this->actingAs($this->guruUser)
            ->patch(route('guru.absensi.update', $sesiKemarin), ['status' => 'HADIR'])
            ->assertForbidden();
    }

    public function test_guru_tidak_bisa_set_status_izin(): void
    {
        // Accept: application/json agar Laravel kembalikan 422 bukan redirect 302
        // saat validasi gagal (Rule::in gagal untuk status yang tidak diizinkan)
        $this->actingAs($this->guruUser)
            ->withHeaders(['Accept' => 'application/json'])
            ->patch(route('guru.absensi.update', $this->sesiHariIni), ['status' => 'IZIN_RESCHEDULE'])
            ->assertUnprocessable();
    }

    public function test_late_minutes_di_reset_jika_status_hadir(): void
    {
        $this->actingAs($this->guruUser)
            ->patch(route('guru.absensi.update', $this->sesiHariIni), [
                'status'       => 'HADIR',
                'late_minutes' => 5,
            ])
            ->assertRedirect();

        $this->assertNull($this->sesiHariIni->fresh()->late_minutes);
    }
}
