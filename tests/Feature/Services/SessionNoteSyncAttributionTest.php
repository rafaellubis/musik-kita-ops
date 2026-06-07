<?php

namespace Tests\Feature\Services;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Instrument;
use App\Models\Package;
use App\Models\ProgressReport;
use App\Models\ReportTemplate;
use App\Models\SessionTeacherNote;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\SessionNoteSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionNoteSyncAttributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_includes_manual_session_by_attribution_not_session_date(): void
    {
        $instrument = Instrument::create(['code' => 'PIANO', 'name' => 'Piano', 'is_active' => true, 'sort_order' => 1]);
        $package = Package::create([
            'code' => 'PIANO_REG_30', 'instrument_id' => $instrument->id,
            'class_type' => 'REGULER', 'duration_min' => 30,
            'price_per_month' => 340000, 'is_active' => true, 'sort_order' => 1,
        ]);
        $student = Student::factory()->create(['status' => 'Aktif']);
        $teacher = Teacher::factory()->create();
        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'package_id' => $package->id,
            'teacher_id' => $teacher->id, 'status' => 'ACTIVE',
            'effective_date' => '2026-01-15', 'is_primary' => true,
        ]);
        $template = ReportTemplate::create([
            'instrument_id' => $instrument->id, 'name' => 'Piano Reg',
            'template_kind' => 'REGULER', 'is_active' => true, 'sort_order' => 1,
        ]);
        $report = ProgressReport::create([
            'enrollment_id' => $enrollment->id,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'report_template_id' => $template->id,
            'month' => 1, 'year' => 2026, 'status' => 'DRAFT',
        ]);

        $janSession = ClassSession::factory()->create([
            'enrollment_id'     => $enrollment->id,
            'student_id'        => $student->id,
            'teacher_id'        => $teacher->id,
            'session_date'      => '2026-01-16',
            'attribution_year'  => 2026,
            'attribution_month' => 1,
            'session_sequence'  => 1,
            'session_type'      => ClassSession::TYPE_REGULAR,
            'status'            => ClassSession::STATUS_HADIR,
        ]);
        SessionTeacherNote::create([
            'class_session_id' => $janSession->id,
            'teacher_id'       => $teacher->id,
            'material_learned' => 'Skala C',
            'session_rating'   => 4,
        ]);

        $rapelSession = ClassSession::factory()->create([
            'enrollment_id'     => $enrollment->id,
            'student_id'        => $student->id,
            'teacher_id'        => $teacher->id,
            'session_date'      => '2026-02-07',
            'attribution_year'  => 2026,
            'attribution_month' => 1,
            'session_sequence'  => 3,
            'session_type'      => ClassSession::TYPE_MANUAL,
            'status'            => ClassSession::STATUS_HADIR,
        ]);
        SessionTeacherNote::create([
            'class_session_id' => $rapelSession->id,
            'teacher_id'       => $teacher->id,
            'material_learned' => 'Arpeggio Jan',
            'session_rating'   => 5,
        ]);

        // Feb regular session — should NOT sync into Jan report
        $febSession = ClassSession::factory()->create([
            'enrollment_id'     => $enrollment->id,
            'student_id'        => $student->id,
            'teacher_id'        => $teacher->id,
            'session_date'      => '2026-02-06',
            'attribution_year'  => 2026,
            'attribution_month' => 2,
            'session_sequence'  => 1,
            'session_type'      => ClassSession::TYPE_REGULAR,
            'status'            => ClassSession::STATUS_HADIR,
        ]);
        SessionTeacherNote::create([
            'class_session_id' => $febSession->id,
            'teacher_id'       => $teacher->id,
            'material_learned' => 'Feb only',
            'session_rating'   => 3,
        ]);

        app(SessionNoteSyncService::class)->sync($report);

        $report->load('sessionNotes');
        $this->assertCount(2, $report->sessionNotes);
        $materials = $report->weeklyMaterials();
        $this->assertSame('Skala C', $materials[1]);
        $this->assertSame('Arpeggio Jan', $materials[3]);
        $this->assertNull($materials[2]);
    }
}
