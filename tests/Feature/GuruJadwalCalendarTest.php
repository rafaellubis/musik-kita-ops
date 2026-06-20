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

class GuruJadwalCalendarTest extends TestCase
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

    private function createSessionForTeacher(Carbon $date, string $studentName, array $overrides = []): ClassSession
    {
        $student = Student::factory()->create(['full_name' => $studentName]);
        $enroll  = Enrollment::factory()->create([
            'student_id' => $student->id,
            'teacher_id' => $this->teacher->id,
            'package_id' => $this->package->id,
            'status'     => 'ACTIVE',
        ]);

        return ClassSession::factory()->create(array_merge([
            'enrollment_id' => $enroll->id,
            'student_id'    => $student->id,
            'teacher_id'    => $this->teacher->id,
            'session_date'  => $date->toDateString(),
            'status'        => 'SCHEDULED',
        ], $overrides));
    }

    public function test_jadwal_page_contains_alpine_calendar_bootstrap(): void
    {
        $response = $this->actingAs($this->guruUser)->get(route('guru.jadwal'));

        $response->assertOk()
            ->assertSee('guruJadwalCalendar', false)
            ->assertViewHas('calendarBootstrap', fn ($data) =>
                isset($data['selectedDate'], $data['dates'], $data['sessionCounts'], $data['routes'])
                && count($data['dates']) === (45 * 2 + 1)
            );
    }

    public function test_jadwal_sessions_endpoint_returns_json_with_html(): void
    {
        $today = Carbon::today();
        $this->createSessionForTeacher($today, 'Murid AJAX');

        $response = $this->actingAs($this->guruUser)
            ->getJson(route('guru.jadwal.sessions', ['date' => $today->format('Y-m-d')]));

        $response->assertOk()
            ->assertJsonStructure(['date', 'is_today', 'session_count', 'has_live', 'html'])
            ->assertJsonPath('session_count', 1)
            ->assertJsonPath('is_today', true);

        $this->assertStringContainsString('Murid AJAX', $response->json('html'));
    }

    public function test_jadwal_dates_endpoint_returns_date_array_with_has_sessions(): void
    {
        $today = Carbon::today();
        $this->createSessionForTeacher($today, 'Murid Dot');

        $response = $this->actingAs($this->guruUser)
            ->getJson(route('guru.jadwal.dates', [
                'from' => $today->copy()->subDays(2)->format('Y-m-d'),
                'to'   => $today->copy()->addDays(2)->format('Y-m-d'),
            ]));

        $response->assertOk()
            ->assertJsonStructure(['dates' => [['date', 'day_name', 'day_number', 'is_today', 'has_sessions']]]);

        $todayEntry = collect($response->json('dates'))->firstWhere('date', $today->format('Y-m-d'));
        $this->assertNotNull($todayEntry);
        $this->assertTrue($todayEntry['has_sessions']);
    }

    public function test_jadwal_dates_rejects_range_over_90_days(): void
    {
        $from = Carbon::today();
        $to   = $from->copy()->addDays(91);

        $response = $this->actingAs($this->guruUser)
            ->getJson(route('guru.jadwal.dates', [
                'from' => $from->format('Y-m-d'),
                'to'   => $to->format('Y-m-d'),
            ]));

        $response->assertStatus(422);
    }

    public function test_jadwal_sessions_requires_guru_auth(): void
    {
        $this->getJson(route('guru.jadwal.sessions', ['date' => today()->format('Y-m-d')]))
            ->assertUnauthorized();
    }

    public function test_jadwal_dates_requires_guru_auth(): void
    {
        $today = Carbon::today();

        $this->getJson(route('guru.jadwal.dates', [
            'from' => $today->format('Y-m-d'),
            'to'   => $today->format('Y-m-d'),
        ]))->assertUnauthorized();
    }

    public function test_substitute_session_counted_in_dates_endpoint(): void
    {
        $otherTeacher = Teacher::factory()->create();
        $today        = Carbon::today();

        ClassSession::factory()->create([
            'teacher_id'             => $otherTeacher->id,
            'substitute_teacher_id'  => $this->teacher->id,
            'session_date'           => $today->toDateString(),
            'status'                 => 'SCHEDULED',
            'student_id'             => Student::factory()->create()->id,
            'enrollment_id'          => Enrollment::factory()->create([
                'teacher_id' => $otherTeacher->id,
                'package_id' => $this->package->id,
            ])->id,
        ]);

        $response = $this->actingAs($this->guruUser)
            ->getJson(route('guru.jadwal.dates', [
                'from' => $today->format('Y-m-d'),
                'to'   => $today->format('Y-m-d'),
            ]));

        $entry = collect($response->json('dates'))->firstWhere('date', $today->format('Y-m-d'));
        $this->assertTrue($entry['has_sessions']);
    }

    public function test_deep_link_date_param_still_works_ssr(): void
    {
        $target = Carbon::today()->subDays(3);
        $this->createSessionForTeacher($target, 'Murid Deep Link');

        $response = $this->actingAs($this->guruUser)
            ->get(route('guru.jadwal', ['date' => $target->format('Y-m-d')]));

        $response->assertOk()
            ->assertSee('Murid Deep Link', false)
            ->assertViewHas('tanggal', $target->toDateString());
    }
}
