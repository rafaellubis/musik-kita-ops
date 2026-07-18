<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\SessionReportWaLog;
use App\Models\SessionTeacherNote;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuruControllerAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $guruUser;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Guru', 'Owner', 'Admin', 'Auditor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->guruUser = User::factory()->create(['email_verified_at' => now()]);
        $this->guruUser->assignRole('Guru');
        Teacher::factory()->create(['user_id' => $this->guruUser->id]);
    }

    public function test_guru_akses_dashboard(): void
    {
        $this->actingAs($this->guruUser)->get('/guru/dashboard')->assertOk();
    }

    public function test_guru_akses_jadwal(): void
    {
        $this->actingAs($this->guruUser)->get('/guru/jadwal')->assertOk();
    }

    public function test_dashboard_hanya_memuat_log_wa_terbaru_dan_terbaru_sukses(): void
    {
        Carbon::setTestNow('2026-07-18 12:00:00');
        config(['session_report_wa.enabled' => true]);

        $session = ClassSession::factory()->create([
            'teacher_id' => $this->guruUser->teacher->id,
            'session_date' => '2026-07-18',
        ]);
        SessionTeacherNote::create([
            'class_session_id' => $session->id,
            'teacher_id' => $this->guruUser->teacher->id,
            'material_learned' => 'Tangga nada',
            'homework_notes' => 'Latihan',
            'updated_at' => now()->subMinute(),
        ]);

        $logs = collect([
            [SessionReportWaLog::STATUS_SUCCESS, now()->subMinutes(2)],
            [SessionReportWaLog::STATUS_SUCCESS, now()->subMinute()],
            [SessionReportWaLog::STATUS_FAILED, now()->subMinute()],
        ])->map(fn (array $data) => SessionReportWaLog::create([
            'class_session_id' => $session->id,
            'student_id' => $session->student_id,
            'phone' => '628123456789',
            'message_body' => 'Laporan',
            'provider' => 'fonnte',
            'status' => $data[0],
            'sent_at' => $data[1],
        ]));

        $response = $this->actingAs($this->guruUser)->get('/guru/dashboard')->assertOk();
        $loadedSession = $response->viewData('sesiHariIni')->firstWhere('id', $session->id);

        $this->assertSame($logs[2]->id, $loadedSession->latestSessionReportWaLog->id);
        $this->assertSame(SessionReportWaLog::STATUS_FAILED, $loadedSession->latestSessionReportWaLog->status);
        $this->assertSame($logs[1]->id, $loadedSession->latestSuccessfulSessionReportWaLog->id);
        $this->assertSame(SessionReportWaLog::STATUS_SUCCESS, $loadedSession->latestSuccessfulSessionReportWaLog->status);
        $this->assertFalse($loadedSession->relationLoaded('sessionReportWaLogs'));
    }

    public function test_guru_akses_honor(): void
    {
        $this->actingAs($this->guruUser)->get('/guru/honor')->assertOk();
    }

    public function test_owner_tidak_bisa_akses_guru_routes(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $owner->assignRole('Owner');

        $this->actingAs($owner)->get('/guru/dashboard')->assertForbidden();
    }

    public function test_guru_tidak_bisa_akses_dashboard_admin(): void
    {
        $this->actingAs($this->guruUser)->get('/dashboard')->assertForbidden();
    }

    public function test_guru_tidak_bisa_akses_teachers_index(): void
    {
        $this->actingAs($this->guruUser)->get('/teachers')->assertForbidden();
    }

    public function test_unauthenticated_redirect_ke_login(): void
    {
        $this->get('/guru/dashboard')->assertRedirect('/login');
    }
}
