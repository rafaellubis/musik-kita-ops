<?php

namespace Tests\Feature;

use App\Models\ClassSession;
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

class SessionTeacherNoteTest extends TestCase
{
    use RefreshDatabase;

    private User $guruUser;
    private Teacher $teacher;
    private Enrollment $enrollment;

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
    }

    private function createHadirSession(array $overrides = []): ClassSession
    {
        return ClassSession::factory()->create(array_merge([
            'enrollment_id' => $this->enrollment->id,
            'student_id'    => $this->enrollment->student_id,
            'teacher_id'    => $this->teacher->id,
            'session_date'  => '2026-05-10',
            'start_time'    => '10:00:00',
            'end_time'      => '10:30:00',
            'status'        => ClassSession::STATUS_HADIR,
        ], $overrides));
    }

    public function test_guru_utama_can_save_notes_on_hadir_session(): void
    {
        $session = $this->createHadirSession();

        $this->actingAs($this->guruUser)
            ->patch(route('guru.sesi.catatan.update', $session), [
                'material_learned' => 'Scales mayor',
                'homework_notes'   => 'Latihan 15 menit',
                'notes'            => 'Murid antusias',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Catatan sesi tersimpan.');

        $this->assertDatabaseHas('session_teacher_notes', [
            'class_session_id' => $session->id,
            'teacher_id'       => $this->teacher->id,
            'material_learned' => 'Scales mayor',
            'homework_notes'   => 'Latihan 15 menit',
            'notes'            => 'Murid antusias',
        ]);
    }

    public function test_substitute_can_save_notes_after_confirmed(): void
    {
        $guruAsli = Teacher::factory()->create();
        $session = $this->createHadirSession([
            'teacher_id'            => $guruAsli->id,
            'substitute_teacher_id' => $this->teacher->id,
            'honor_code'            => 'H_PENG',
            'honor_amount'          => 50000,
        ]);

        $this->actingAs($this->guruUser)
            ->patch(route('guru.sesi.catatan.update', $session), [
                'material_learned' => 'Arpeggio',
                'homework_notes'   => null,
                'notes'            => null,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Catatan sesi tersimpan.');

        $this->assertDatabaseHas('session_teacher_notes', [
            'class_session_id' => $session->id,
            'teacher_id'       => $this->teacher->id,
            'material_learned' => 'Arpeggio',
        ]);
    }

    public function test_other_guru_gets_403(): void
    {
        $guruLain = User::factory()->create(['email_verified_at' => now()]);
        $guruLain->assignRole('Guru');
        Teacher::factory()->create(['user_id' => $guruLain->id]);

        $session = $this->createHadirSession();

        $this->actingAs($guruLain)
            ->patch(route('guru.sesi.catatan.update', $session), [
                'material_learned' => 'Tidak boleh',
            ])
            ->assertForbidden();
    }

    public function test_cannot_save_on_scheduled_session(): void
    {
        $session = $this->createHadirSession(['status' => ClassSession::STATUS_SCHEDULED]);

        $this->actingAs($this->guruUser)
            ->patch(route('guru.sesi.catatan.update', $session), [
                'material_learned' => 'Tidak boleh',
            ])
            ->assertForbidden();
    }

    public function test_validation_requires_at_least_one_field(): void
    {
        $session = $this->createHadirSession();

        $this->actingAs($this->guruUser)
            ->patch(route('guru.sesi.catatan.update', $session), [
                'material_learned' => '   ',
                'homework_notes'   => '',
                'notes'            => null,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('notes');

        $this->assertDatabaseMissing('session_teacher_notes', [
            'class_session_id' => $session->id,
        ]);
    }

    public function test_cannot_edit_when_monthly_report_submitted(): void
    {
        $session = $this->createHadirSession();

        $template = ReportTemplate::create([
            'instrument_id' => $this->enrollment->package->instrument_id,
            'name'          => 'Vocal · Hobby',
            'template_kind' => ReportTemplate::KIND_HOBBY,
            'is_active'     => true,
            'sort_order'    => 1,
        ]);

        ProgressReport::create([
            'enrollment_id'      => $this->enrollment->id,
            'student_id'         => $this->enrollment->student_id,
            'teacher_id'         => $this->teacher->id,
            'report_template_id' => $template->id,
            'month'              => 5,
            'year'               => 2026,
            'status'             => ProgressReport::STATUS_SUBMITTED,
            'submitted_at'       => now(),
        ]);

        $this->actingAs($this->guruUser)
            ->patch(route('guru.sesi.catatan.update', $session), [
                'material_learned' => 'Tidak boleh',
            ])
            ->assertForbidden();
    }
}
