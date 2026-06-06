<?php
namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Instrument;
use App\Models\Package;
use App\Models\ProgressReport;
use App\Models\ReportTemplate;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProgressReportAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $admin;
    private ProgressReport $report;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        foreach (['Owner', 'Admin', 'Auditor', 'Guru'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
        $this->owner = User::factory()->create(['email_verified_at' => now()]);
        $this->owner->assignRole('Owner');
        $this->admin = User::factory()->create(['email_verified_at' => now()]);
        $this->admin->assignRole('Admin');

        $instrument = Instrument::create(['code' => 'VOC', 'name' => 'Vocal', 'is_active' => true, 'sort_order' => 1]);
        $package = Package::create([
            'code' => 'VOC-HOB-30', 'instrument_id' => $instrument->id,
            'class_type' => 'HOBBY', 'duration_min' => 30,
            'price_per_month' => 390000, 'is_active' => true, 'sort_order' => 1,
        ]);
        $teacher = Teacher::factory()->create();
        $student = Student::factory()->create(['status' => 'Aktif']);
        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'package_id' => $package->id,
            'teacher_id' => $teacher->id, 'status' => 'ACTIVE',
            'effective_date' => now()->toDateString(), 'is_primary' => true,
        ]);
        $template = ReportTemplate::create([
            'instrument_id' => $instrument->id, 'name' => 'Template Vocal',
            'is_active' => true, 'sort_order' => 1,
        ]);
        $this->report = ProgressReport::create([
            'enrollment_id'      => $enrollment->id,
            'student_id'         => $student->id,
            'teacher_id'         => $teacher->id,
            'report_template_id' => $template->id,
            'month' => 5, 'year' => 2026, 'status' => 'SUBMITTED',
            'submitted_at'       => now(),
        ]);
    }

    public function test_admin_bisa_lihat_daftar_laporan(): void
    {
        $this->actingAs($this->admin)->get('/progress-reports')->assertOk();
    }

    public function test_owner_bisa_lihat_daftar_laporan(): void
    {
        $this->actingAs($this->owner)->get('/progress-reports')->assertOk();
    }

    public function test_guru_tidak_bisa_akses_progress_reports_admin(): void
    {
        $guruUser = User::factory()->create(['email_verified_at' => now()]);
        $guruUser->assignRole('Guru');
        Teacher::factory()->create(['user_id' => $guruUser->id]);

        $this->actingAs($guruUser)->get('/progress-reports')->assertForbidden();
    }

    public function test_admin_bisa_view_pdf(): void
    {
        $this->actingAs($this->admin)
            ->get("/progress-reports/{$this->report->id}/pdf")
            ->assertOk()
            ->assertSee('Preview PDF')
            ->assertSee('Download PDF');
    }

    public function test_admin_bisa_download_pdf(): void
    {
        $this->actingAs($this->admin)
            ->get("/progress-reports/{$this->report->id}/pdf/download")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_admin_bisa_view_pdf_draft(): void
    {
        $this->report->update(['status' => 'DRAFT', 'submitted_at' => null]);

        $this->actingAs($this->admin)
            ->get("/progress-reports/{$this->report->id}/pdf")
            ->assertOk()
            ->assertSee('Preview PDF')
            ->assertSee('Draft');
    }
}
