<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Instrument;
use App\Models\Package;
use App\Models\ReportTemplate;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\ReportTemplateResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTemplateResolverTest extends TestCase
{
    use RefreshDatabase;

    private ReportTemplateResolverService $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(ReportTemplateResolverService::class);
    }

    public function test_resolve_hobby_package(): void
    {
        $instrument = Instrument::create(['code' => 'GIT', 'name' => 'Gitar', 'is_active' => true, 'sort_order' => 1]);
        $template = ReportTemplate::create([
            'instrument_id' => $instrument->id,
            'name'          => 'Gitar · Hobby',
            'template_kind' => ReportTemplate::KIND_HOBBY,
            'is_active'     => true,
            'sort_order'    => 1,
        ]);
        $package = Package::create([
            'code' => 'GITAR_HOBBY_45', 'instrument_id' => $instrument->id,
            'class_type' => 'HOBBY', 'duration_min' => 45,
            'price_per_month' => 450000, 'is_active' => true, 'sort_order' => 1,
        ]);
        $teacher = Teacher::factory()->create();
        $student = Student::factory()->create();
        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'package_id' => $package->id,
            'teacher_id' => $teacher->id, 'status' => 'ACTIVE',
            'effective_date' => now()->toDateString(), 'is_primary' => true,
        ]);

        $this->assertEquals($template->id, $this->resolver->resolveForEnrollment($enrollment)?->id);
    }

    public function test_resolve_kids_class_package(): void
    {
        $instrument = Instrument::create(['code' => 'KIDS', 'name' => 'Kids Class', 'is_active' => true, 'sort_order' => 1]);
        $template = ReportTemplate::create([
            'instrument_id' => $instrument->id,
            'name'          => 'Kids Class · Eksplorasi Bakat',
            'template_kind' => ReportTemplate::KIND_KIDS,
            'is_active'     => true,
            'sort_order'    => 1,
        ]);
        $package = Package::create([
            'code' => 'KIDS_GRUP_MONTHLY', 'instrument_id' => $instrument->id,
            'class_type' => 'KIDS_CLASS', 'duration_min' => 45,
            'price_per_month' => 340000, 'is_active' => true, 'sort_order' => 1,
        ]);
        $teacher = Teacher::factory()->create();
        $student = Student::factory()->create();
        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'package_id' => $package->id,
            'teacher_id' => $teacher->id, 'status' => 'ACTIVE',
            'effective_date' => now()->toDateString(), 'is_primary' => true,
        ]);

        $this->assertEquals($template->id, $this->resolver->resolveForEnrollment($enrollment)?->id);
    }
}
