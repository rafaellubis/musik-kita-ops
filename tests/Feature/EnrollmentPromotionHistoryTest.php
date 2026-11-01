<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\ProgressReport;
use App\Models\ReportTemplate;
use App\Models\SessionTeacherNote;
use App\Models\User;
use App\Services\EnrollmentPromotionService;
use App\Services\SessionNoteSyncService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesPromotionClass;
use Tests\TestCase;

class EnrollmentPromotionHistoryTest extends TestCase
{
    use RefreshDatabase, CreatesPromotionClass;

    public function test_completed_level_and_old_teacher_keep_their_reports_and_session_notes(): void
    {
        extract($this->promotionClass());
        $oldTeacher = $source->teacher;
        Role::findOrCreate('Guru', 'web');
        $guru = User::factory()->create()->assignRole('Guru');
        $oldTeacher->update(['user_id' => $guru->id]);
        $session = ClassSession::factory()->create(['student_id' => $student->id, 'enrollment_id' => $source->id, 'teacher_id' => $oldTeacher->id, 'session_date' => '2026-10-05', 'status' => 'HADIR', 'honor_amount' => 42500, 'honor_code' => 'H_REG']);
        SessionTeacherNote::create(['class_session_id' => $session->id, 'teacher_id' => $oldTeacher->id, 'material_learned' => 'Tangga nada Oktober', 'homework_notes' => 'Latihan dua oktaf', 'notes' => 'Teknik membaik']);
        $template = ReportTemplate::create(['instrument_id' => $package->instrument_id, 'name' => 'Template Piano', 'is_active' => true, 'sort_order' => 1]);
        $report = ProgressReport::create(['student_id' => $student->id, 'enrollment_id' => $source->id, 'teacher_id' => $oldTeacher->id, 'report_template_id' => $template->id, 'year' => 2026, 'month' => 10, 'status' => 'SUBMITTED', 'submitted_at' => now()]);
        app(SessionNoteSyncService::class)->sync($report);
        app(EnrollmentPromotionService::class)->schedule($source, $data, $actor);
        $this->travelTo(Carbon::parse('2026-11-01 08:00:00'));
        app(EnrollmentPromotionService::class)->applyDue();
        $this->assertSame($package->id, $report->fresh()->enrollment->package_id);
        $this->assertSame($oldTeacher->id, $report->fresh()->teacher_id);
        $this->assertSame('Tangga nada Oktober', $report->sessionNotes()->first()->material_learned);
        $this->assertSame(42500, $session->fresh()->honor_amount);
        $this->actingAs($actor)->get('/progress-reports/'.$report->id)->assertOk()->assertSee('Tangga nada Oktober');
        $this->actingAs($guru)->get('/guru/laporan')->assertOk()->assertViewHas('enrollments', fn ($classes) => $classes->contains('id', $source->id));
    }
}
