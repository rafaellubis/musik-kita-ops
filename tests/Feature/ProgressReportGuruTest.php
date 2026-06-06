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
            'report_number'      => 'LMK/LPR/2026/05/0001',
            'month'              => 5,
            'year'               => 2026,
            'status'             => 'DRAFT',
        ]);
    }

    public function test_nomor_laporan_increment_per_bulan(): void
    {
        $this->actingAs($this->guruUser)
            ->post('/guru/laporan', [
                'enrollment_id' => $this->enrollment->id,
                'month'         => 5,
                'year'          => 2026,
            ])
            ->assertRedirect();

        $instrument2 = Instrument::create(['code' => 'GTR', 'name' => 'Gitar', 'is_active' => true, 'sort_order' => 2]);
        $package2 = Package::create([
            'code' => 'GTR-HOB-30', 'instrument_id' => $instrument2->id,
            'class_type' => 'HOBBY', 'duration_min' => 30,
            'price_per_month' => 390000, 'is_active' => true, 'sort_order' => 2,
        ]);
        $student2 = Student::factory()->create(['status' => 'Aktif']);
        $enrollment2 = Enrollment::create([
            'student_id' => $student2->id, 'package_id' => $package2->id,
            'teacher_id' => $this->teacher->id, 'status' => 'ACTIVE',
            'effective_date' => now()->toDateString(), 'is_primary' => true,
        ]);
        ReportTemplate::create([
            'instrument_id' => $instrument2->id, 'name' => 'Gitar · Hobby',
            'template_kind' => ReportTemplate::KIND_HOBBY, 'is_active' => true, 'sort_order' => 2,
        ]);

        $this->actingAs($this->guruUser)
            ->post('/guru/laporan', [
                'enrollment_id' => $enrollment2->id,
                'month'         => 5,
                'year'          => 2026,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('progress_reports', [
            'enrollment_id' => $enrollment2->id,
            'report_number' => 'LMK/LPR/2026/05/0002',
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

        $this->actingAs($this->guruUser)
            ->put("/guru/laporan/{$report->id}", [
                'rating_teknik'                => 4,
                'rating_materi'                => 4,
                'rating_reading'               => 3,
                'rating_repertoar'             => 4,
                'catatan_perkembangan_musikal'  => 'Bagus bulan ini.',
                'catatan_karakter'             => 'Disiplin latihan.',
                'kesimpulan_progress'          => 'BAIK',
                'progress_percent'             => 40,
                'submit'                       => '1',
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

    public function test_diganti_without_note_not_synced_to_report(): void
    {
        ClassSession::factory()->create([
            'enrollment_id'    => $this->enrollment->id,
            'student_id'       => $this->enrollment->student_id,
            'teacher_id'       => $this->teacher->id,
            'session_date'     => '2026-05-15',
            'start_time'       => '10:00:00',
            'end_time'         => '10:30:00',
            'status'           => ClassSession::STATUS_DIGANTI,
            'substitute_teacher_id' => Teacher::factory()->create()->id,
            'honor_code'       => 'H_PENG',
            'honor_amount'     => 50000,
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

        $this->assertSame(0, ProgressReportSessionNote::where('progress_report_id', $report->id)->count());
    }

    public function test_diganti_with_substitute_note_synced_with_substitute_name(): void
    {
        $substitute = Teacher::factory()->create(['name' => 'Guru Pengganti A']);

        $session = ClassSession::factory()->create([
            'enrollment_id'         => $this->enrollment->id,
            'student_id'            => $this->enrollment->student_id,
            'teacher_id'            => $this->teacher->id,
            'substitute_teacher_id' => $substitute->id,
            'session_date'          => '2026-05-20',
            'start_time'            => '10:00:00',
            'end_time'              => '10:30:00',
            'status'                => ClassSession::STATUS_DIGANTI,
            'honor_code'            => 'H_PENG',
            'honor_amount'          => 50000,
        ]);

        SessionTeacherNote::create([
            'class_session_id' => $session->id,
            'teacher_id'       => $substitute->id,
            'material_learned' => 'Arpeggio minor',
            'homework_notes'   => 'Latihan 10 menit',
            'notes'            => 'Baik',
            'session_rating'   => 5,
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
            ->assertOk()
            ->assertSee('Guru Pengganti')
            ->assertSee('Guru Pengganti A')
            ->assertSee('Arpeggio minor');

        $this->assertDatabaseHas('progress_report_session_notes', [
            'progress_report_id'      => $report->id,
            'class_session_id'        => $session->id,
            'material_learned'        => 'Arpeggio minor',
            'substitute_teacher_name' => 'Guru Pengganti A',
        ]);
    }

    public function test_guru_bisa_simpan_draft_dengan_field_baru(): void
    {
        $report = ProgressReport::create([
            'enrollment_id' => $this->enrollment->id,
            'student_id' => $this->enrollment->student_id,
            'teacher_id' => $this->teacher->id,
            'report_template_id' => $this->template->id,
            'month' => 5, 'year' => 2026, 'status' => 'DRAFT',
        ]);

        $this->actingAs($this->guruUser)
            ->put("/guru/laporan/{$report->id}", [
                'rating_teknik' => 4,
                'rating_materi' => 3,
                'rating_reading' => 5,
                'rating_repertoar' => 4,
                'catatan_teknik' => 'Postur jari sudah konsisten.',
                'catatan_materi' => 'Scale mayor dikuasai.',
                'catatan_perkembangan_musikal' => 'Teknik jari membaik.',
                'catatan_karakter' => 'Rajin dan fokus.',
                'kesimpulan_progress' => 'BAIK',
                'progress_percent' => 40,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('progress_reports', [
            'id' => $report->id,
            'rating_teknik' => 4,
            'catatan_teknik' => 'Postur jari sudah konsisten.',
            'catatan_materi' => 'Scale mayor dikuasai.',
            'kesimpulan_progress' => 'BAIK',
            'progress_percent' => 40,
        ]);
    }

    public function test_submit_gagal_jika_field_wajib_kosong(): void
    {
        $report = ProgressReport::create([
            'enrollment_id' => $this->enrollment->id,
            'student_id' => $this->enrollment->student_id,
            'teacher_id' => $this->teacher->id,
            'report_template_id' => $this->template->id,
            'month' => 5, 'year' => 2026, 'status' => 'DRAFT',
        ]);

        $this->actingAs($this->guruUser)
            ->put("/guru/laporan/{$report->id}", ['submit' => '1'])
            ->assertSessionHasErrors([
                'rating_teknik', 'rating_materi', 'rating_reading', 'rating_repertoar',
                'kesimpulan_progress', 'progress_percent',
            ]);
    }

    public function test_guru_bisa_view_pdf_draft(): void
    {
        $report = ProgressReport::create([
            'report_number' => 'LMK/LPR/2026/05/0001',
            'enrollment_id' => $this->enrollment->id,
            'student_id' => $this->enrollment->student_id,
            'teacher_id' => $this->teacher->id,
            'report_template_id' => $this->template->id,
            'month' => 5, 'year' => 2026, 'status' => 'DRAFT',
        ]);

        $this->actingAs($this->guruUser)
            ->get("/guru/laporan/{$report->id}/pdf")
            ->assertOk()
            ->assertSee('Preview PDF')
            ->assertSee('Draft');
    }

    public function test_guru_tidak_bisa_view_pdf_laporan_guru_lain(): void
    {
        $otherTeacher = Teacher::factory()->create();
        $report = ProgressReport::create([
            'report_number' => 'LMK/LPR/2026/05/0002',
            'enrollment_id' => $this->enrollment->id,
            'student_id' => $this->enrollment->student_id,
            'teacher_id' => $otherTeacher->id,
            'report_template_id' => $this->template->id,
            'month' => 6, 'year' => 2026, 'status' => 'DRAFT',
        ]);

        $this->actingAs($this->guruUser)
            ->get("/guru/laporan/{$report->id}/pdf")
            ->assertForbidden();
    }

    public function test_admin_bisa_download_pdf_laporan_submitted(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('Admin');

        $report = ProgressReport::create([
            'report_number' => 'LMK/LPR/2026/05/0001',
            'enrollment_id' => $this->enrollment->id,
            'student_id' => $this->enrollment->student_id,
            'teacher_id' => $this->teacher->id,
            'report_template_id' => $this->template->id,
            'month' => 5, 'year' => 2026, 'status' => 'SUBMITTED',
            'submitted_at' => now(),
            'rating_teknik' => 4, 'rating_materi' => 4,
            'rating_reading' => 3, 'rating_repertoar' => 4,
            'kesimpulan_progress' => 'BAIK',
            'progress_percent' => 40,
            'catatan_perkembangan_musikal' => 'Progres bagus.',
            'catatan_karakter' => 'Rajin.',
        ]);

        $response = $this->actingAs($admin)
            ->get("/progress-reports/{$report->id}/pdf/download");

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertLessThan(
            200 * 1024,
            strlen($response->getContent()),
            'PDF laporan progress seharusnya di bawah 200 KB setelah optimasi.'
        );
    }
}
