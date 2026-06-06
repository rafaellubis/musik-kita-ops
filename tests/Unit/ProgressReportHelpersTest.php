<?php
namespace Tests\Unit;

use App\Models\ProgressReport;
use App\Models\ProgressReportSessionNote;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Instrument;
use App\Models\ReportTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgressReportHelpersTest extends TestCase
{
    use RefreshDatabase;

    private function makeReport(): ProgressReport
    {
        $instrument = Instrument::create(['code' => 'VOCAL', 'name' => 'Vocal', 'is_active' => true, 'sort_order' => 1]);
        $package = Package::create([
            'code' => 'VOCAL_HOBBY_30', 'instrument_id' => $instrument->id,
            'class_type' => 'HOBBY', 'duration_min' => 30,
            'price_per_month' => 390000, 'is_active' => true, 'sort_order' => 1,
        ]);
        $student = Student::factory()->create();
        $teacher = Teacher::factory()->create();
        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'package_id' => $package->id,
            'teacher_id' => $teacher->id, 'status' => 'ACTIVE',
            'effective_date' => now()->toDateString(), 'is_primary' => true,
        ]);
        $template = ReportTemplate::create([
            'instrument_id' => $instrument->id, 'name' => 'Vocal Hobby',
            'template_kind' => 'HOBBY', 'is_active' => true, 'sort_order' => 1,
        ]);

        return ProgressReport::create([
            'enrollment_id' => $enrollment->id,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'report_template_id' => $template->id,
            'month' => 6, 'year' => 2026, 'status' => 'DRAFT',
        ]);
    }

    public function test_average_session_rating_returns_null_when_no_ratings(): void
    {
        $report = $this->makeReport();
        $this->assertNull($report->averageSessionRating());
    }

    public function test_average_session_rating_averages_non_null_ratings(): void
    {
        $report = $this->makeReport();
        ProgressReportSessionNote::create([
            'progress_report_id' => $report->id, 'session_date' => '2026-06-03',
            'session_sequence' => 1, 'session_rating' => 3, 'sort_order' => 0, 'notes' => '',
        ]);
        ProgressReportSessionNote::create([
            'progress_report_id' => $report->id, 'session_date' => '2026-06-10',
            'session_sequence' => 2, 'session_rating' => 5, 'sort_order' => 1, 'notes' => '',
        ]);
        $report->load('sessionNotes');

        $this->assertSame(4.0, $report->averageSessionRating());
    }

    public function test_weekly_materials_maps_by_session_sequence(): void
    {
        $report = $this->makeReport();
        ProgressReportSessionNote::create([
            'progress_report_id' => $report->id, 'session_date' => '2026-06-03',
            'session_sequence' => 1, 'material_learned' => 'Skala C', 'sort_order' => 0, 'notes' => '',
        ]);
        ProgressReportSessionNote::create([
            'progress_report_id' => $report->id, 'session_date' => '2026-06-17',
            'session_sequence' => 3, 'material_learned' => 'Arpeggio', 'sort_order' => 1, 'notes' => '',
        ]);
        $report->load('sessionNotes');

        $this->assertSame([
            1 => 'Skala C',
            2 => null,
            3 => 'Arpeggio',
            4 => null,
        ], $report->weeklyMaterials());
    }

    public function test_render_stars(): void
    {
        $this->assertSame('—', ProgressReport::renderStars(null));
        $this->assertSame('★★★☆☆', ProgressReport::renderStars(3));
    }
}
