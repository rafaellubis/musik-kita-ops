<?php

namespace Tests\Unit;

use App\Models\Instrument;
use App\Models\Package;
use App\Models\ProgressReport;
use App\Models\ReportTemplate;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Enrollment;
use App\Services\ProgressReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgressReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private function seedReport(?string $reportNumber): void
    {
        $instrument = Instrument::create(['code' => 'PIANO', 'name' => 'Piano', 'is_active' => true, 'sort_order' => 1]);
        $package = Package::create([
            'code' => 'PIANO_HOBBY_30', 'instrument_id' => $instrument->id,
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
            'instrument_id' => $instrument->id, 'name' => 'Piano Hobby',
            'template_kind' => 'HOBBY', 'is_active' => true, 'sort_order' => 1,
        ]);

        ProgressReport::create([
            'report_number'      => $reportNumber,
            'enrollment_id'      => $enrollment->id,
            'student_id'         => $student->id,
            'teacher_id'         => $teacher->id,
            'report_template_id' => $template->id,
            'month'              => 6,
            'year'               => 2026,
            'status'             => 'DRAFT',
        ]);
    }

    public function test_generate_report_number_starts_at_one(): void
    {
        $service = new ProgressReportService();

        $this->assertSame('LMK/LPR/2026/06/0001', $service->generateReportNumber(2026, 6));
    }

    public function test_generate_report_number_increments_per_month(): void
    {
        $this->seedReport('LMK/LPR/2026/06/0001');

        $service = new ProgressReportService();

        $this->assertSame('LMK/LPR/2026/06/0002', $service->generateReportNumber(2026, 6));
        $this->assertSame('LMK/LPR/2026/07/0001', $service->generateReportNumber(2026, 7));
    }

    public function test_backfill_assigns_numbers_to_legacy_reports(): void
    {
        $instrument = Instrument::create(['code' => 'PIANO', 'name' => 'Piano', 'is_active' => true, 'sort_order' => 1]);
        $package = Package::create([
            'code' => 'PIANO_HOBBY_30', 'instrument_id' => $instrument->id,
            'class_type' => 'HOBBY', 'duration_min' => 30,
            'price_per_month' => 390000, 'is_active' => true, 'sort_order' => 1,
        ]);
        $teacher = Teacher::factory()->create();
        $template = ReportTemplate::create([
            'instrument_id' => $instrument->id, 'name' => 'Piano Hobby',
            'template_kind' => 'HOBBY', 'is_active' => true, 'sort_order' => 1,
        ]);

        $makeReport = function (Student $student) use ($package, $teacher, $template) {
            $enrollment = Enrollment::create([
                'student_id' => $student->id, 'package_id' => $package->id,
                'teacher_id' => $teacher->id, 'status' => 'ACTIVE',
                'effective_date' => now()->toDateString(), 'is_primary' => true,
            ]);

            return ProgressReport::create([
                'report_number'      => null,
                'enrollment_id'      => $enrollment->id,
                'student_id'         => $student->id,
                'teacher_id'         => $teacher->id,
                'report_template_id' => $template->id,
                'month'              => 6,
                'year'               => 2026,
                'status'             => 'DRAFT',
            ]);
        };

        $older = $makeReport(Student::factory()->create());
        $newer = $makeReport(Student::factory()->create());

        $service = new ProgressReportService();
        $count   = $service->backfillReportNumbers();

        $this->assertSame(2, $count);
        $this->assertSame('LMK/LPR/2026/06/0001', $older->fresh()->report_number);
        $this->assertSame('LMK/LPR/2026/06/0002', $newer->fresh()->report_number);
    }
}
