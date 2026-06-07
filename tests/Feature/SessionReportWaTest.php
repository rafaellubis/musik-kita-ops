<?php

namespace Tests\Feature;

use App\Jobs\SendSessionReportWaJob;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Instrument;
use App\Models\Package;
use App\Models\SessionReportWaLog;
use App\Models\SessionTeacherNote;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Models\WhatsappMessageTemplate;
use App\Services\SessionReportWaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SessionReportWaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        foreach (['Owner', 'Admin', 'Auditor', 'Guru'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
    }

    public function test_session_report_wa_log_status_constants(): void
    {
        $this->assertSame('SUCCESS', SessionReportWaLog::STATUS_SUCCESS);
        $this->assertSame('FAILED', SessionReportWaLog::STATUS_FAILED);
        $this->assertSame('SKIPPED', SessionReportWaLog::STATUS_SKIPPED);
    }

    public function test_default_session_report_template_exists_after_seed(): void
    {
        $this->seed(\Database\Seeders\WhatsappMessageTemplateSeeder::class);

        $template = WhatsappMessageTemplate::defaultSessionReport();

        $this->assertNotNull($template);
        $this->assertSame(WhatsappMessageTemplate::CODE_SESSION_REPORT, $template->code);
        $this->assertTrue($template->is_active);
    }

    public function test_default_session_report_student_template_exists_after_seed(): void
    {
        $this->seed(\Database\Seeders\WhatsappMessageTemplateSeeder::class);

        $template = WhatsappMessageTemplate::defaultSessionReportStudent();

        $this->assertNotNull($template);
        $this->assertSame(WhatsappMessageTemplate::CODE_SESSION_REPORT_STUDENT, $template->code);
        $this->assertTrue($template->is_active);
        $this->assertStringContainsString('{nama_murid}', $template->body);
        $this->assertStringNotContainsString('{nama_ortu}', $template->body);
        $this->assertNotEmpty($template->encouragement_lines['rating_5']);
        $this->assertNotEmpty($template->encouragement_lines['rating_2']);
        $this->assertNotEmpty($template->encouragement_lines['rating_1']);
    }

    private function seedSessionReportTemplate(): void
    {
        WhatsappMessageTemplate::create([
            'code'       => WhatsappMessageTemplate::CODE_SESSION_REPORT,
            'name'       => 'Laporan Sesi',
            'body'       => 'Halo {nama_ortu}, {nama_murid} {tanggal_sesi} {instrumen} {nama_guru} M:{materi} T:{tugas} {blok_catatan} {pesan_semangat} {studio_wa}',
            'is_active'  => true,
            'sort_order' => 3,
        ]);

        WhatsappMessageTemplate::create([
            'code'       => WhatsappMessageTemplate::CODE_SESSION_REPORT_STUDENT,
            'name'       => 'Laporan Sesi ke Murid',
            'body'       => 'Halo {nama_murid}! Kamu les {instrumen}. {pesan_semangat}',
            'is_active'  => true,
            'sort_order' => 4,
        ]);
    }

    /** @return array{student: Student, session: ClassSession, teacher: Teacher} */
    private function buildSessionWithNote(
        ?string $parentPhone = '0816920592',
        ?string $studentPhone = null,
    ): array {
        $this->seedSessionReportTemplate();

        $instrument = Instrument::factory()->create(['name' => 'Piano']);
        $package = Package::factory()->create(['instrument_id' => $instrument->id]);
        $teacher = Teacher::factory()->create(['name' => 'Pak Budi']);
        $student = Student::factory()->create([
            'full_name'    => 'Ani Kecil',
            'parent_name'  => 'Bu Siti',
            'parent_phone' => $parentPhone,
            'phone'        => $studentPhone,
            'status'       => 'Aktif',
        ]);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'package_id' => $package->id,
            'teacher_id' => $teacher->id,
            'status'     => Enrollment::STATUS_ACTIVE,
        ]);
        $session = ClassSession::factory()->create([
            'student_id'    => $student->id,
            'enrollment_id' => $enrollment->id,
            'teacher_id'    => $teacher->id,
            'session_date'  => '2026-06-05',
            'status'        => ClassSession::STATUS_HADIR,
        ]);
        SessionTeacherNote::create([
            'class_session_id' => $session->id,
            'teacher_id'       => $teacher->id,
            'material_learned' => 'Scales mayor',
            'homework_notes'   => 'Latihan 15 menit',
            'notes'            => 'Antusias',
            'session_rating'   => 5,
        ]);

        return [
            'student' => $student,
            'session' => $session,
            'teacher' => $teacher,
        ];
    }

    public function test_compose_message_includes_session_fields(): void
    {
        ['session' => $session] = $this->buildSessionWithNote();

        $session->load(['student', 'teacher', 'enrollment.package.instrument', 'teacherNote']);
        $message = app(SessionReportWaService::class)->composeMessage($session);

        $this->assertStringContainsString('Bu Siti', $message);
        $this->assertStringContainsString('Ani Kecil', $message);
        $this->assertStringContainsString('Scales mayor', $message);
        $this->assertStringContainsString('antusias dan fokus', $message);
    }

    public function test_compose_message_uses_student_template_when_sent_to_student(): void
    {
        ['session' => $session] = $this->buildSessionWithNote(null, '081234567890');

        $session->load(['student', 'teacher', 'enrollment.package.instrument', 'teacherNote']);
        $message = app(SessionReportWaService::class)->composeMessage($session, false, 'student');

        $this->assertStringStartsWith('Halo Ani Kecil!', $message);
        $this->assertStringContainsString('Kamu les Piano', $message);
        $this->assertStringContainsString('Kamu tampil sangat antusias', $message);
        $this->assertStringNotContainsString('Bu Siti', $message);
        $this->assertStringNotContainsString('Bapak/Ibu', $message);
    }

    public function test_compose_message_uses_parent_template_by_default(): void
    {
        ['session' => $session] = $this->buildSessionWithNote();

        $session->load(['student', 'teacher', 'enrollment.package.instrument', 'teacherNote']);
        $message = app(SessionReportWaService::class)->composeMessage($session, false, 'parent');

        $this->assertStringContainsString('Bu Siti', $message);
        $this->assertStringContainsString('antusias dan fokus', $message);
    }

    public function test_compose_message_uses_custom_encouragement_from_template(): void
    {
        ['session' => $session] = $this->buildSessionWithNote(null, '081234567890');

        WhatsappMessageTemplate::where('code', WhatsappMessageTemplate::CODE_SESSION_REPORT_STUDENT)
            ->update(['encouragement_lines' => [
                'rating_5' => 'Custom pesan semangat rating 5',
                'rating_4' => 'Custom rating 4',
                'rating_3' => 'Custom rating 3',
                'rating_2' => 'Custom rating 2',
                'rating_1' => 'Custom rating 1',
                'default'  => 'Custom default',
            ]]);

        $session->load(['student', 'teacher', 'enrollment.package.instrument', 'teacherNote']);
        $message = app(SessionReportWaService::class)->composeMessage($session, false, 'student');

        $this->assertStringContainsString('Custom pesan semangat rating 5', $message);
    }

    public function test_compose_message_uses_rating_2_encouragement(): void
    {
        ['session' => $session] = $this->buildSessionWithNote();

        SessionTeacherNote::first()->update(['session_rating' => 2]);

        WhatsappMessageTemplate::where('code', WhatsappMessageTemplate::CODE_SESSION_REPORT)
            ->update(['encouragement_lines' => array_merge(
                WhatsappMessageTemplate::defaultEncouragementLines(WhatsappMessageTemplate::CODE_SESSION_REPORT),
                ['rating_2' => 'Pesan khusus rating dua ortu'],
            )]);

        $session->load(['student', 'teacher', 'enrollment.package.instrument', 'teacherNote']);
        $message = app(SessionReportWaService::class)->composeMessage($session, false, 'parent');

        $this->assertStringContainsString('Pesan khusus rating dua ortu', $message);
    }

    public function test_compose_message_uses_rating_1_encouragement(): void
    {
        ['session' => $session] = $this->buildSessionWithNote(null, '081234567890');

        SessionTeacherNote::first()->update(['session_rating' => 1]);

        WhatsappMessageTemplate::where('code', WhatsappMessageTemplate::CODE_SESSION_REPORT_STUDENT)
            ->update(['encouragement_lines' => array_merge(
                WhatsappMessageTemplate::defaultEncouragementLines(WhatsappMessageTemplate::CODE_SESSION_REPORT_STUDENT),
                ['rating_1' => 'Pesan khusus rating satu murid'],
            )]);

        $session->load(['student', 'teacher', 'enrollment.package.instrument', 'teacherNote']);
        $message = app(SessionReportWaService::class)->composeMessage($session, false, 'student');

        $this->assertStringContainsString('Pesan khusus rating satu murid', $message);
    }

    public function test_owner_can_update_encouragement_lines_on_session_report_template(): void
    {
        $this->seed(\Database\Seeders\WhatsappMessageTemplateSeeder::class);

        $owner = User::factory()->create();
        $owner->assignRole('Owner');

        $template = WhatsappMessageTemplate::defaultSessionReport();
        $newLines = [
            'rating_5' => 'Ortu rating 5 baru',
            'rating_4' => 'Ortu rating 4 baru',
            'rating_3' => 'Ortu rating 3 baru',
            'rating_2' => 'Ortu rating 2 baru',
            'rating_1' => 'Ortu rating 1 baru',
            'default'  => 'Ortu default baru',
        ];

        $this->actingAs($owner)
            ->put(route('whatsapp-templates.update', $template), [
                'code'                => $template->code,
                'name'                => $template->name,
                'body'                => $template->body,
                'sort_order'          => $template->sort_order,
                'is_active'           => 1,
                'encouragement_lines' => $newLines,
            ])
            ->assertRedirect(route('whatsapp-templates.index'));

        $template->refresh();
        $this->assertSame('Ortu rating 2 baru', $template->encouragement_lines['rating_2']);
        $this->assertSame('Ortu rating 1 baru', $template->encouragement_lines['rating_1']);
    }

    public function test_resolve_recipient_type_prefers_parent_over_student(): void
    {
        $student = Student::factory()->create([
            'parent_phone' => '0816920592',
            'phone'        => '081234567890',
        ]);

        $service = app(SessionReportWaService::class);

        $this->assertSame('parent', $service->resolveRecipientType($student));
    }

    public function test_resolve_recipient_type_falls_back_to_student(): void
    {
        $student = Student::factory()->create([
            'parent_phone' => null,
            'phone'        => '081234567890',
        ]);

        $this->assertSame('student', app(SessionReportWaService::class)->resolveRecipientType($student));
    }

    public function test_falls_back_to_student_template_in_message_body(): void
    {
        config([
            'session_report_wa.enabled'    => true,
            'services.fonnte.token'        => 'test-fonnte-token',
            'services.fonnte.base_url'     => 'https://api.fonnte.com',
            'services.fonnte.country_code' => '62',
        ]);

        Http::fake([
            'https://api.fonnte.com/send' => Http::response([
                'status' => true,
                'id'     => ['999'],
            ], 200),
        ]);

        ['session' => $session] = $this->buildSessionWithNote(null, '081234567890');
        $note = SessionTeacherNote::first();

        $session->load(['student', 'teacher', 'enrollment.package.instrument', 'teacherNote']);
        app(SessionReportWaService::class)->sendForSession($session, $note->updated_at->copy());

        $log = SessionReportWaLog::first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Kamu les Piano', $log->message_body);
        $this->assertStringNotContainsString('Bu Siti', $log->message_body);
    }

    public function test_sends_to_parent_phone(): void
    {
        config([
            'session_report_wa.enabled'          => true,
            'services.fonnte.token'              => 'test-fonnte-token',
            'services.fonnte.base_url'           => 'https://api.fonnte.com',
            'services.fonnte.country_code'       => '62',
        ]);

        Http::fake([
            'https://api.fonnte.com/send' => Http::response([
                'status' => true,
                'id'     => ['999'],
                'detail' => 'success',
            ], 200),
        ]);

        ['session' => $session] = $this->buildSessionWithNote();
        $note = SessionTeacherNote::first();
        $snapshot = $note->updated_at->copy();

        $session->load(['student', 'teacher', 'enrollment.package.instrument', 'teacherNote']);
        app(SessionReportWaService::class)->sendForSession($session, $snapshot);

        Http::assertSent(fn ($req) => $req['target'] === '0816920592');
        $this->assertDatabaseHas('session_report_wa_logs', [
            'class_session_id' => $session->id,
            'status'           => SessionReportWaLog::STATUS_SUCCESS,
        ]);
    }

    public function test_falls_back_to_student_phone_when_parent_null(): void
    {
        config([
            'session_report_wa.enabled'    => true,
            'services.fonnte.token'        => 'test-fonnte-token',
            'services.fonnte.base_url'     => 'https://api.fonnte.com',
            'services.fonnte.country_code' => '62',
        ]);

        Http::fake([
            'https://api.fonnte.com/send' => Http::response([
                'status' => true,
                'id'     => ['999'],
            ], 200),
        ]);

        ['session' => $session] = $this->buildSessionWithNote(null, '081234567890');
        $note = SessionTeacherNote::first();

        $session->load(['student', 'teacher', 'enrollment.package.instrument', 'teacherNote']);
        app(SessionReportWaService::class)->sendForSession($session, $note->updated_at->copy());

        Http::assertSent(fn ($req) => $req['target'] === '081234567890');
    }

    public function test_skips_when_note_updated_after_snapshot(): void
    {
        config(['session_report_wa.enabled' => true]);

        ['session' => $session] = $this->buildSessionWithNote();
        $note = SessionTeacherNote::first();
        $oldSnapshot = $note->updated_at->copy();

        $this->travel(5)->seconds();
        $note->update(['material_learned' => 'Arpeggio']);

        $session->load(['student', 'teacher', 'enrollment.package.instrument', 'teacherNote']);
        $result = app(SessionReportWaService::class)->sendForSession($session, $oldSnapshot);

        $this->assertNull($result);
        $this->assertDatabaseCount('session_report_wa_logs', 0);
    }

    public function test_saving_session_notes_dispatches_wa_job(): void
    {
        Queue::fake();
        config(['session_report_wa.enabled' => true, 'session_report_wa.debounce_minutes' => 10]);

        $guruUser = User::factory()->create(['email_verified_at' => now()]);
        $guruUser->assignRole('Guru');
        $teacher = Teacher::factory()->create(['user_id' => $guruUser->id, 'name' => 'Pak Budi']);

        $instrument = Instrument::create(['code' => 'VOC', 'name' => 'Vocal', 'is_active' => true, 'sort_order' => 1]);
        $package = Package::create([
            'code' => 'VOC-HOB-30', 'instrument_id' => $instrument->id,
            'class_type' => 'HOBBY', 'duration_min' => 30,
            'price_per_month' => 390000, 'is_active' => true, 'sort_order' => 1,
        ]);
        $student = Student::factory()->create(['status' => 'Aktif']);
        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'package_id' => $package->id,
            'teacher_id' => $teacher->id, 'status' => 'ACTIVE',
            'effective_date' => now()->toDateString(), 'is_primary' => true,
        ]);
        $session = ClassSession::factory()->create([
            'enrollment_id' => $enrollment->id,
            'student_id'    => $student->id,
            'teacher_id'    => $teacher->id,
            'session_date'  => '2026-05-10',
            'status'        => ClassSession::STATUS_HADIR,
        ]);

        $this->actingAs($guruUser)
            ->patch(route('guru.sesi.catatan.update', $session), [
                'material_learned' => 'Scales',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Catatan sesi tersimpan. Laporan sesi akan otomatis dikirim ke WhatsApp orang tua.');

        Queue::assertPushed(SendSessionReportWaJob::class, function ($job) use ($session) {
            return $job->classSessionId === $session->id;
        });
    }

    public function test_admin_can_view_session_report_wa_logs(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        ['session' => $session, 'student' => $student] = $this->buildSessionWithNote();

        SessionReportWaLog::create([
            'class_session_id'     => $session->id,
            'student_id'           => $student->id,
            'phone'                => '62816920592',
            'message_body'         => 'Test pesan laporan sesi',
            'status'               => SessionReportWaLog::STATUS_SUCCESS,
            'is_update'            => false,
            'sent_at'              => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('session-report-wa-logs.index'))
            ->assertOk()
            ->assertSee('Ani Kecil')
            ->assertSee('SUCCESS');
    }
}
