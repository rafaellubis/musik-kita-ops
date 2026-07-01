<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Instrument;
use App\Models\Package;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuruSessionHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $guruUser;
    private Teacher $teacher;
    private ClassSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Guru', 'guard_name' => 'web']);

        $this->guruUser = User::factory()->create();
        $this->guruUser->assignRole('Guru');
        $this->teacher = Teacher::factory()->create(['user_id' => $this->guruUser->id]);

        $instr = Instrument::factory()->create(['name' => 'Piano', 'code' => 'PIANO']);
        $package = Package::factory()->create([
            'class_type' => 'REGULER',
            'instrument_id' => $instr->id,
        ]);
        $student = Student::factory()->create();
        $enroll = Enrollment::factory()->create([
            'student_id' => $student->id,
            'teacher_id' => $this->teacher->id,
            'package_id' => $package->id,
            'status' => 'ACTIVE',
        ]);

        $this->session = ClassSession::factory()->create([
            'enrollment_id' => $enroll->id,
            'student_id' => $student->id,
            'teacher_id' => $this->teacher->id,
            'session_date' => today()->toDateString(),
            'status' => 'HADIR',
        ]);
    }

    public function test_guest_cannot_access_history_endpoint(): void
    {
        $response = $this->get(route('guru.sesi.history', $this->session));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_guru_can_access_own_session_history(): void
    {
        $response = $this->actingAs($this->guruUser)
            ->get(route('guru.sesi.history', $this->session));

        $response->assertStatus(200);
    }

    public function test_guru_cannot_access_other_teacher_session_history(): void
    {
        $otherTeacherUser = User::factory()->create();
        $otherTeacherUser->assignRole('Guru');
        $otherTeacher = Teacher::factory()->create(['user_id' => $otherTeacherUser->id]);

        $otherSession = ClassSession::factory()->create([
            'teacher_id' => $otherTeacher->id,
            'session_date' => today()->toDateString(),
        ]);

        $response = $this->actingAs($this->guruUser)
            ->get(route('guru.sesi.history', $otherSession));

        $response->assertStatus(403);
    }

    public function test_renders_history_data_correctly(): void
    {
        // Buat sesi historis di bulan berjalan
        $pastSessionDate = sprintf(
            '%04d-%02d-%02d',
            $this->session->attribution_year,
            $this->session->attribution_month,
            $this->session->session_date === sprintf('%04d-%02d-01', $this->session->attribution_year, $this->session->attribution_month) ? 2 : 1
        );

        $pastSession = ClassSession::factory()->create([
            'enrollment_id' => $this->session->enrollment_id,
            'student_id' => $this->session->student_id,
            'teacher_id' => $this->teacher->id,
            'session_date' => $pastSessionDate,
            'attribution_month' => $this->session->attribution_month,
            'attribution_year' => $this->session->attribution_year,
            'status' => 'HADIR',
        ]);

        // Buat catatan sesi untuk sesi historis tersebut
        \App\Models\SessionTeacherNote::create([
            'class_session_id' => $pastSession->id,
            'teacher_id' => $this->teacher->id,
            'material_learned' => 'Tangga nada C mayor',
            'homework_notes' => 'Latihan ketukan metronom 80',
            'notes' => 'Ahmad sangat fokus hari ini',
            'session_rating' => 5,
        ]);

        $response = $this->actingAs($this->guruUser)
            ->get(route('guru.sesi.history', $this->session));

        $response->assertStatus(200);
        $response->assertSee('Tangga nada C mayor');
        $response->assertSee('Latihan ketukan metronom 80');
        $response->assertSee('Ahmad sangat fokus hari ini');
        $response->assertSee('Rating: 5/5');
    }

    public function test_renders_empty_state_when_no_history(): void
    {
        $response = $this->actingAs($this->guruUser)
            ->get(route('guru.sesi.history', $this->session));

        $response->assertStatus(200);
        $response->assertSee('Belum ada riwayat sesi');
    }

    public function test_substitute_guru_can_access_session_history(): void
    {
        $otherTeacherUser = User::factory()->create();
        $otherTeacherUser->assignRole('Guru');
        $otherTeacher = Teacher::factory()->create(['user_id' => $otherTeacherUser->id]);

        $substituteSession = ClassSession::factory()->create([
            'teacher_id' => $otherTeacher->id,
            'substitute_teacher_id' => $this->teacher->id,
            'session_date' => today()->toDateString(),
        ]);

        $response = $this->actingAs($this->guruUser)
            ->get(route('guru.sesi.history', $substituteSession));

        $response->assertStatus(200);
    }

    public function test_verifies_history_query_filtering(): void
    {
        $year = $this->session->attribution_year;
        $month = $this->session->attribution_month;

        // 1. Sesi yang valid (HADIR, enrollment sama, bulan/tahun sama)
        $validSession1 = ClassSession::factory()->create([
            'enrollment_id' => $this->session->enrollment_id,
            'student_id' => $this->session->student_id,
            'teacher_id' => $this->teacher->id,
            'session_date' => sprintf('%04d-%02d-%02d', $year, $month, $this->session->session_date === sprintf('%04d-%02d-01', $year, $month) ? 2 : 1),
            'attribution_month' => $month,
            'attribution_year' => $year,
            'status' => 'HADIR',
        ]);

        // 2. Sesi yang valid (HADIR_TERLAMBAT, enrollment sama, bulan/tahun sama)
        $validSession2 = ClassSession::factory()->create([
            'enrollment_id' => $this->session->enrollment_id,
            'student_id' => $this->session->student_id,
            'teacher_id' => $this->teacher->id,
            'session_date' => sprintf('%04d-%02d-%02d', $year, $month, $this->session->session_date === sprintf('%04d-%02d-02', $year, $month) ? 3 : 2),
            'attribution_month' => $month,
            'attribution_year' => $year,
            'status' => 'HADIR_TERLAMBAT',
        ]);

        // 3. Sesi dengan status tidak valid (IZIN_RESCHEDULE)
        $invalidStatusSession = ClassSession::factory()->create([
            'enrollment_id' => $this->session->enrollment_id,
            'student_id' => $this->session->student_id,
            'teacher_id' => $this->teacher->id,
            'session_date' => sprintf('%04d-%02d-%02d', $year, $month, $this->session->session_date === sprintf('%04d-%02d-03', $year, $month) ? 4 : 3),
            'attribution_month' => $month,
            'attribution_year' => $year,
            'status' => 'IZIN_RESCHEDULE',
        ]);

        // 4. Sesi dengan enrollment berbeda
        $otherEnroll = Enrollment::factory()->create([
            'student_id' => $this->session->student_id,
            'teacher_id' => $this->teacher->id,
            'package_id' => $this->session->enrollment->package_id,
            'status' => 'ACTIVE',
        ]);
        $otherEnrollSession = ClassSession::factory()->create([
            'enrollment_id' => $otherEnroll->id,
            'student_id' => $this->session->student_id,
            'teacher_id' => $this->teacher->id,
            'session_date' => sprintf('%04d-%02d-%02d', $year, $month, $this->session->session_date === sprintf('%04d-%02d-04', $year, $month) ? 5 : 4),
            'attribution_month' => $month,
            'attribution_year' => $year,
            'status' => 'HADIR',
        ]);

        // 5. Sesi dengan bulan berbeda
        $diffMonth = $month == 12 ? 1 : $month + 1;
        $diffYear = $month == 12 ? $year + 1 : $year;
        $diffMonthSession = ClassSession::factory()->create([
            'enrollment_id' => $this->session->enrollment_id,
            'student_id' => $this->session->student_id,
            'teacher_id' => $this->teacher->id,
            'session_date' => sprintf('%04d-%02d-01', $diffYear, $diffMonth),
            'attribution_month' => $diffMonth,
            'attribution_year' => $diffYear,
            'status' => 'HADIR',
        ]);

        $response = $this->actingAs($this->guruUser)
            ->get(route('guru.sesi.history', $this->session));

        $response->assertStatus(200);

        // Ambil data history dari view
        $history = $response->viewData('history');

        // Pastikan isi history hanya berisi $validSession1 dan $validSession2
        $this->assertCount(2, $history);
        $historyIds = $history->pluck('id')->toArray();
        $this->assertContains($validSession1->id, $historyIds);
        $this->assertContains($validSession2->id, $historyIds);

        // Pastikan sesi-sesi tidak valid tidak masuk
        $this->assertNotContains($invalidStatusSession->id, $historyIds);
        $this->assertNotContains($otherEnrollSession->id, $historyIds);
        $this->assertNotContains($diffMonthSession->id, $historyIds);

        // Pastikan sesi aktif itu sendiri ($this->session) tidak masuk
        $this->assertNotContains($this->session->id, $historyIds);
    }

    public function test_can_filter_history_by_month_and_year_parameters(): void
    {
        $year = $this->session->attribution_year;
        $month = $this->session->attribution_month;

        // Tentukan bulan & tahun target filter (1 bulan sebelumnya)
        $targetMonth = $month == 1 ? 12 : $month - 1;
        $targetYear = $month == 1 ? $year - 1 : $year;

        // Buat sesi historis di bulan target (Mei)
        $pastSession = ClassSession::factory()->create([
            'enrollment_id' => $this->session->enrollment_id,
            'student_id' => $this->session->student_id,
            'teacher_id' => $this->teacher->id,
            'session_date' => sprintf('%04d-%02d-15', $targetYear, $targetMonth),
            'attribution_month' => $targetMonth,
            'attribution_year' => $targetYear,
            'status' => 'HADIR',
        ]);

        // Panggil endpoint dengan parameter target
        $response = $this->actingAs($this->guruUser)
            ->get(route('guru.sesi.history', [
                'classSession' => $this->session->id,
                'month' => $targetMonth,
                'year' => $targetYear
            ]));

        $response->assertStatus(200);
        $history = $response->viewData('history');

        // Pastikan hanya $pastSession yang dikembalikan
        $this->assertCount(1, $history);
        $this->assertEquals($pastSession->id, $history->first()->id);
    }
}

