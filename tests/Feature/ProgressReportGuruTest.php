<?php
namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Instrument;
use App\Models\Package;
use App\Models\ProgressReport;
use App\Models\ProgressReportSessionNote;
use App\Models\ReportTemplate;
use App\Models\ReportTemplateSection;
use App\Models\ReportTemplateItem;
use App\Models\SessionTeacherNote;
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
        $this->withoutVite();
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
            'instrument_id'  => $instrument->id,
            'name'           => 'Vocal · Hobby',
            'template_kind'  => ReportTemplate::KIND_HOBBY,
            'is_active'      => true,
            'sort_order'     => 1,
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

    public function test_guru_bisa_buat_laporan_baru_auto_template(): void
    {
        $this->actingAs($this->guruUser)
            ->post('/guru/laporan', [
                'enrollment_id' => $this->enrollment->id,
                'month'         => 5,
                'year'          => 2026,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('progress_reports', [
            'enrollment_id'      => $this->enrollment->id,
            'report_template_id' => $this->template->id,
            'month'              => 5,
            'year'               => 2026,
            'status'             => 'DRAFT',
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
                'enrollment_id' => $this->enrollment->id,
                'month'         => 5,
                'year'          => 2026,
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

    public function test_duo_enrollment_memakai_template_reguler(): void
    {
        $instrument = Instrument::create(['code' => 'PIA', 'name' => 'Piano', 'is_active' => true, 'sort_order' => 2]);
        $duoPackage = Package::create([
            'code' => 'DUO_PIANO_30', 'instrument_id' => $instrument->id,
            'class_type' => 'DUO', 'duration_min' => 30,
            'price_per_month' => 320000, 'is_active' => true, 'sort_order' => 2,
        ]);
        $regulerTemplate = ReportTemplate::create([
            'instrument_id' => $instrument->id,
            'name'          => 'Piano · Reguler',
            'template_kind' => ReportTemplate::KIND_REGULER,
            'is_active'     => true,
            'sort_order'    => 2,
        ]);
        $student = Student::factory()->create(['status' => 'Aktif']);
        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'package_id' => $duoPackage->id,
            'teacher_id' => $this->teacher->id, 'status' => 'ACTIVE',
            'effective_date' => now()->toDateString(), 'is_primary' => true,
        ]);

        $this->actingAs($this->guruUser)
            ->post('/guru/laporan', [
                'enrollment_id' => $enrollment->id,
                'month'         => 6,
                'year'          => 2026,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('progress_reports', [
            'enrollment_id'      => $enrollment->id,
            'report_template_id' => $regulerTemplate->id,
        ]);
    }

    public function test_laporan_edit_syncs_session_notes_from_hadir_sessions(): void
    {
        $session1 = ClassSession::factory()->create([
            'enrollment_id'    => $this->enrollment->id,
            'student_id'       => $this->enrollment->student_id,
            'teacher_id'       => $this->teacher->id,
            'session_date'     => '2026-05-05',
            'start_time'       => '10:00:00',
            'end_time'         => '10:30:00',
            'status'           => ClassSession::STATUS_HADIR,
            'session_sequence' => 1,
        ]);

        $session2 = ClassSession::factory()->create([
            'enrollment_id'    => $this->enrollment->id,
            'student_id'       => $this->enrollment->student_id,
            'teacher_id'       => $this->teacher->id,
            'session_date'     => '2026-05-12',
            'start_time'       => '10:00:00',
            'end_time'         => '10:30:00',
            'status'           => ClassSession::STATUS_HADIR,
            'session_sequence' => 2,
        ]);

        SessionTeacherNote::create([
            'class_session_id' => $session1->id,
            'teacher_id'       => $this->teacher->id,
            'material_learned' => 'Scales',
            'homework_notes'   => 'Practice daily',
            'notes'            => 'Good progress',
            'session_rating'   => 4,
        ]);

        $report = ProgressReport::create([
            'enrollment_id'      => $this->enrollment->id,
            'student_id'         => $this->enrollment->student_id,
            'teacher_id'         => $this->teacher->id,
            'report_template_id' => $this->template->id,
            'month'              => 5,
            'year'               => 2026,
            'status'             => 'DRAFT',
        ]);

        $this->actingAs($this->guruUser)
            ->get("/guru/laporan/{$report->id}/edit")
            ->assertOk();

        $this->assertEquals(2, ProgressReportSessionNote::where('progress_report_id', $report->id)->count());

        $this->assertDatabaseHas('progress_report_session_notes', [
            'progress_report_id' => $report->id,
            'class_session_id'   => $session1->id,
            'material_learned'   => 'Scales',
            'homework_notes'     => 'Practice daily',
            'notes'              => 'Good progress',
            'session_rating'     => 4,
        ]);

        $this->assertDatabaseHas('progress_report_session_notes', [
            'progress_report_id' => $report->id,
            'class_session_id'   => $session2->id,
        ]);
    }

    public function test_guru_gagal_buat_laporan_jika_template_tidak_ada(): void
    {
        $instrument = Instrument::create(['code' => 'SAX', 'name' => 'Saxophone', 'is_active' => true, 'sort_order' => 3]);
        $package = Package::create([
            'code' => 'SAX_HOBBY_30', 'instrument_id' => $instrument->id,
            'class_type' => 'HOBBY', 'duration_min' => 30,
            'price_per_month' => 420000, 'is_active' => true, 'sort_order' => 3,
        ]);
        $student = Student::factory()->create(['status' => 'Aktif']);
        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'package_id' => $package->id,
            'teacher_id' => $this->teacher->id, 'status' => 'ACTIVE',
            'effective_date' => now()->toDateString(), 'is_primary' => false,
        ]);

        $this->actingAs($this->guruUser)
            ->post('/guru/laporan', [
                'enrollment_id' => $enrollment->id,
                'month'         => 7,
                'year'          => 2026,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('progress_reports', [
            'enrollment_id' => $enrollment->id,
            'month'         => 7,
            'year'          => 2026,
        ]);
    }
}
