<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Instrument;
use App\Models\Package;
use App\Models\Room;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class KalenderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Owner',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);
    }

    private function ownerUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Owner');
        return $user;
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');
        return $user;
    }

    private function auditorUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Auditor');
        return $user;
    }

    public function test_owner_dapat_akses_kalender(): void
    {
        $response = $this->actingAs($this->ownerUser())->get(route('kalender.index'));
        $response->assertStatus(200);
    }

    public function test_admin_dapat_akses_kalender(): void
    {
        $response = $this->actingAs($this->adminUser())->get(route('kalender.index'));
        $response->assertStatus(200);
    }

    public function test_auditor_dapat_akses_kalender(): void
    {
        $response = $this->actingAs($this->auditorUser())->get(route('kalender.index'));
        $response->assertStatus(200);
    }

    public function test_tamu_tidak_dapat_akses_kalender(): void
    {
        $response = $this->get(route('kalender.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_default_week_adalah_senin_minggu_ini(): void
    {
        $response = $this->actingAs($this->ownerUser())->get(route('kalender.index'));
        $response->assertStatus(200);
        $response->assertViewHas('weekStart', function ($weekStart) {
            return $weekStart->isMonday() && $weekStart->isSameWeek(now());
        });
    }

    public function test_week_param_menentukan_minggu_yang_ditampilkan(): void
    {
        $response = $this->actingAs($this->ownerUser())
            ->get(route('kalender.index') . '?week=2026-05-18');
        $response->assertStatus(200);
        $response->assertViewHas('weekStart', function ($weekStart) {
            return $weekStart->format('Y-m-d') === '2026-05-18';
        });
    }

    public function test_grid_berisi_sesi_minggu_ini(): void
    {
        $teacher    = Teacher::factory()->create(['name' => 'ADI', 'is_active' => true]);
        $room       = Room::factory()->create(['code' => 'R2', 'is_active' => true]);
        $instrument = Instrument::create(['name' => 'Piano', 'code' => 'PIANO', 'is_active' => true, 'sort_order' => 1]);
        $package    = Package::factory()->create(['instrument_id' => $instrument->id]);
        $student    = Student::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'package_id' => $package->id,
            'status'     => 'ACTIVE',
        ]);

        $senin = Carbon::now()->startOfWeek(Carbon::MONDAY);
        ClassSession::factory()->create([
            'enrollment_id' => $enrollment->id,
            'student_id'    => $student->id,
            'teacher_id'    => $teacher->id,
            'room_id'       => $room->id,
            'session_date'  => $senin,
            'start_time'    => '09:00:00',
            'end_time'      => '09:30:00',
            'status'        => 'SCHEDULED',
        ]);

        $response = $this->actingAs($this->ownerUser())->get(route('kalender.index'));
        $response->assertStatus(200);
        $response->assertViewHas('grid', function ($grid) use ($senin) {
            $dow = $senin->dayOfWeek; // 1 = Monday
            return isset($grid[$dow]['09:00:00']) && count($grid[$dow]['09:00:00']) === 1;
        });
    }

    public function test_filter_teacher_id_hanya_tampilkan_sesi_guru_itu(): void
    {
        $teacher1   = Teacher::factory()->create(['name' => 'ADI',    'is_active' => true]);
        $teacher2   = Teacher::factory()->create(['name' => 'THOMAS', 'is_active' => true]);
        $room       = Room::factory()->create(['code' => 'R2', 'is_active' => true]);
        $instrument = Instrument::create(['name' => 'Piano', 'code' => 'PIANO2', 'is_active' => true, 'sort_order' => 2]);
        $package    = Package::factory()->create(['instrument_id' => $instrument->id]);
        $student    = Student::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'teacher_id' => $teacher1->id,
            'package_id' => $package->id,
            'status'     => 'ACTIVE',
        ]);

        $senin = Carbon::now()->startOfWeek(Carbon::MONDAY);

        ClassSession::factory()->create([
            'enrollment_id' => $enrollment->id,
            'student_id'    => $student->id,
            'teacher_id'    => $teacher1->id,
            'room_id'       => $room->id,
            'session_date'  => $senin,
            'start_time'    => '09:00:00',
            'end_time'      => '09:30:00',
            'status'        => 'SCHEDULED',
        ]);
        ClassSession::factory()->create([
            'enrollment_id' => $enrollment->id,
            'student_id'    => $student->id,
            'teacher_id'    => $teacher2->id,
            'room_id'       => $room->id,
            'session_date'  => $senin,
            'start_time'    => '10:00:00',
            'end_time'      => '10:30:00',
            'status'        => 'SCHEDULED',
        ]);

        $response = $this->actingAs($this->ownerUser())
            ->get(route('kalender.index') . '?teacher_id=' . $teacher1->id);

        $response->assertViewHas('grid', function ($grid) {
            $totalSesi = 0;
            foreach ($grid as $daySlots) {
                foreach ($daySlots as $sessions) {
                    $totalSesi += count($sessions);
                }
            }
            return $totalSesi === 1;
        });
    }

    /**
     * Cell kalender menampilkan nickname murid agar multi-kelas mudah dikenali.
     */
    public function test_cell_menampilkan_nickname_murid(): void
    {
        $teacher    = Teacher::factory()->create(['name' => 'ADI', 'is_active' => true]);
        $room       = Room::factory()->create(['code' => 'R2', 'is_active' => true]);
        $instrument = Instrument::create(['name' => 'Piano', 'code' => 'PIANO3', 'is_active' => true, 'sort_order' => 3]);
        $package    = Package::factory()->create(['instrument_id' => $instrument->id]);
        $student    = Student::factory()->create([
            'full_name' => 'Budi Santoso',
            'nickname'  => 'Bud',
        ]);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'package_id' => $package->id,
            'status'     => 'ACTIVE',
        ]);

        $senin = Carbon::now()->startOfWeek(Carbon::MONDAY);
        ClassSession::factory()->create([
            'enrollment_id' => $enrollment->id,
            'student_id'    => $student->id,
            'teacher_id'    => $teacher->id,
            'room_id'       => $room->id,
            'session_date'  => $senin,
            'start_time'    => '09:00:00',
            'end_time'      => '09:30:00',
            'status'        => 'SCHEDULED',
        ]);

        $response = $this->actingAs($this->ownerUser())->get(route('kalender.index'));
        $response->assertStatus(200);
        $response->assertSee('Bud', false);
        $response->assertSee('Piano ·', false);
        $response->assertSee('text-mk-accent', false);
        $response->assertSee('ADI', false);
    }

    /**
     * Jika nickname kosong, cell pakai kata pertama full_name.
     */
    public function test_cell_fallback_kata_pertama_jika_nickname_kosong(): void
    {
        $teacher    = Teacher::factory()->create(['name' => 'NAEL', 'is_active' => true]);
        $room       = Room::factory()->create(['code' => 'R4', 'is_active' => true]);
        $instrument = Instrument::create(['name' => 'Gitar', 'code' => 'GITAR1', 'is_active' => true, 'sort_order' => 4]);
        $package    = Package::factory()->create(['instrument_id' => $instrument->id]);
        $student    = Student::factory()->create([
            'full_name' => 'Sari Wijaya',
            'nickname'  => null,
        ]);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'package_id' => $package->id,
            'status'     => 'ACTIVE',
        ]);

        $senin = Carbon::now()->startOfWeek(Carbon::MONDAY);
        ClassSession::factory()->create([
            'enrollment_id' => $enrollment->id,
            'student_id'    => $student->id,
            'teacher_id'    => $teacher->id,
            'room_id'       => $room->id,
            'session_date'  => $senin,
            'start_time'    => '10:00:00',
            'end_time'      => '10:30:00',
            'status'        => 'SCHEDULED',
        ]);

        $response = $this->actingAs($this->ownerUser())->get(route('kalender.index'));
        $response->assertStatus(200);
        $response->assertSee('Sari', false);
        $response->assertSee('Gitar ·', false);
        $response->assertSee('NAEL', false);
    }
}
