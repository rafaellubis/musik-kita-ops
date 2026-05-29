<?php
namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Instrument;
use App\Models\Package;
use App\Models\ProgressReport;
use App\Models\ReportTemplate;
use App\Models\ReportTemplateSection;
use App\Models\ReportTemplateItem;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProgressReportGuruTest extends TestCase
{
    use RefreshDatabase;

    private User $guruUser;
    private Teacher $teacher;
    private Enrollment $enrollment;
    private ReportTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Owner', 'Admin', 'Auditor', 'Guru'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }

        $this->guruUser = User::factory()->create(['email_verified_at' => now()]);
        $this->guruUser->assignRole('Guru');
        $this->teacher = Teacher::factory()->create(['user_id' => $this->guruUser->id]);

        $instrument = Instrument::create(['code' => 'VOC', 'name' => 'Vocal', 'is_active' => true, 'sort_order' => 1]);
        $package = Package::create([
            'code' => 'VOC-HOB-30', 'instrument_id' => $instrument->id,
            'class_type' => 'HOBBY', 'duration_min' => 30,
            'price_per_month' => 390000, 'is_active' => true, 'sort_order' => 1,
        ]);
        $student = Student::factory()->create(['status' => 'Aktif']);
        $this->enrollment = Enrollment::create([
            'student_id' => $student->id, 'package_id' => $package->id,
            'teacher_id' => $this->teacher->id, 'status' => 'ACTIVE',
            'effective_date' => now()->toDateString(), 'is_primary' => true,
        ]);

        $this->template = ReportTemplate::create([
            'instrument_id' => $instrument->id,
            'name'          => 'Template Vocal',
            'is_active'     => true,
            'sort_order'    => 1,
        ]);
        $section = ReportTemplateSection::create([
            'report_template_id' => $this->template->id,
            'title' => 'Kemampuan Bernyanyi', 'sort_order' => 1,
        ]);
        ReportTemplateItem::create([
            'report_template_section_id' => $section->id,
            'label' => 'Teknik Pernafasan', 'sort_order' => 1,
        ]);
    }

    public function test_guru_bisa_lihat_halaman_laporan(): void
    {
        $this->actingAs($this->guruUser)->get('/guru/laporan')->assertOk();
    }

    public function test_guru_bisa_buat_laporan_baru(): void
    {
        $this->actingAs($this->guruUser)
            ->post('/guru/laporan', [
                'enrollment_id'      => $this->enrollment->id,
                'report_template_id' => $this->template->id,
                'month'              => 5,
                'year'               => 2026,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('progress_reports', [
            'enrollment_id' => $this->enrollment->id,
            'month'         => 5,
            'year'          => 2026,
            'status'        => 'DRAFT',
        ]);
    }

    public function test_guru_tidak_bisa_buat_laporan_duplikat(): void
    {
        ProgressReport::create([
            'enrollment_id'      => $this->enrollment->id,
            'student_id'         => $this->enrollment->student_id,
            'teacher_id'         => $this->teacher->id,
            'report_template_id' => $this->template->id,
            'month' => 5, 'year' => 2026, 'status' => 'DRAFT',
        ]);

        $this->actingAs($this->guruUser)
            ->post('/guru/laporan', [
                'enrollment_id'      => $this->enrollment->id,
                'report_template_id' => $this->template->id,
                'month'              => 5,
                'year'               => 2026,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_guru_bisa_submit_laporan(): void
    {
        $report = ProgressReport::create([
            'enrollment_id'      => $this->enrollment->id,
            'student_id'         => $this->enrollment->student_id,
            'teacher_id'         => $this->teacher->id,
            'report_template_id' => $this->template->id,
            'month' => 5, 'year' => 2026, 'status' => 'DRAFT',
        ]);

        $item = $this->template->sections->first()->items->first();

        $this->actingAs($this->guruUser)
            ->put("/guru/laporan/{$report->id}", [
                'highlight'        => 'Murid berkembang pesat.',
                'summary_notes'    => 'Terus latihan.',
                'target_notes'     => 'Kuasai falsetto.',
                'repertoire'       => ['Reflection'],
                'section_summary'  => [],
                'checked_items'    => [$item->id],
                'submit'           => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('progress_reports', [
            'id'     => $report->id,
            'status' => 'SUBMITTED',
        ]);
    }

    public function test_guru_tidak_bisa_edit_laporan_guru_lain(): void
    {
        $guruLain = User::factory()->create(['email_verified_at' => now()]);
        $guruLain->assignRole('Guru');
        Teacher::factory()->create(['user_id' => $guruLain->id]);

        $report = ProgressReport::create([
            'enrollment_id'      => $this->enrollment->id,
            'student_id'         => $this->enrollment->student_id,
            'teacher_id'         => $this->teacher->id,
            'report_template_id' => $this->template->id,
            'month' => 5, 'year' => 2026, 'status' => 'DRAFT',
        ]);

        $this->actingAs($guruLain)->get("/guru/laporan/{$report->id}/edit")->assertForbidden();
    }
}
