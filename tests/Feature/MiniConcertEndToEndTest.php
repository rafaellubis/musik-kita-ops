<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Event;
use App\Models\EventHonorEntry;
use App\Models\EventParticipant;
use App\Models\Invoice;
use App\Models\InvoiceComponent;
use App\Models\Package;
use App\Models\Payment;
use App\Models\SessionTeacherNote;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\EventAttendanceService;
use App\Services\EventFinanceService;
use App\Services\EventLifecycleService;
use App\Services\EventParticipantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MiniConcertEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 6, 20)->setTime(10, 0));

        foreach (['Owner', 'Admin', 'Auditor', 'Guru'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_complete_mini_concert_flow_groups_enrollments_pays_final_teachers_and_preserves_sessions(): void
    {
        $owner = User::factory()->create()->assignRole('Owner');
        $admin = User::factory()->create()->assignRole('Admin');
        [$firstTeacher, $firstGuru] = $this->teacherWithGuruAccount();
        [$absentTeacher] = $this->teacherWithGuruAccount();
        [$replacementTeacher, $replacementGuru] = $this->teacherWithGuruAccount();
        $student = Student::factory()->create(['status' => 'Aktif']);
        $firstEnrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'teacher_id' => $firstTeacher->id,
            'package_id' => Package::factory()->create(['price_per_month' => 340000]),
            'status' => Enrollment::STATUS_ACTIVE,
        ]);
        $secondEnrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'teacher_id' => $absentTeacher->id,
            'package_id' => Package::factory()->create(['price_per_month' => 400000]),
            'status' => Enrollment::STATUS_ACTIVE,
        ]);
        InvoiceComponent::create([
            'code' => 'MC',
            'name' => 'Mini Concert',
            'default_price' => 310000,
            'is_active' => true,
        ]);

        $session = ClassSession::factory()->create([
            'enrollment_id' => $firstEnrollment->id,
            'student_id' => $student->id,
            'teacher_id' => $firstTeacher->id,
            'session_date' => '2026-06-10',
            'status' => ClassSession::STATUS_HADIR,
            'notes' => 'Catatan sesi reguler tidak boleh berubah.',
            'honor_code' => 'H_REG',
            'honor_amount' => 42500,
        ]);
        $sessionNote = SessionTeacherNote::create([
            'class_session_id' => $session->id,
            'teacher_id' => $firstTeacher->id,
            'material_learned' => 'Materi reguler tetap.',
            'notes' => 'Catatan guru reguler tetap.',
        ]);
        $sessionCount = ClassSession::count();
        $sessionBefore = $session->fresh()->getAttributes();
        $sessionNoteBefore = $sessionNote->fresh()->getAttributes();

        $this->actingAs($admin)->post(route('events.store'), [
            'name' => 'Mini Concert Integrasi',
            'type' => Event::TYPE_MINI_CONCERT,
            'event_date' => '2026-06-15',
            'payment_due_date' => '2026-06-10',
            'notes' => 'Skenario end-to-end.',
        ])->assertRedirect();
        $event = Event::where('name', 'Mini Concert Integrasi')->sole();
        $this->assertTrue($event->isDraft());

        $this->post(route('events.participants.store', $event), [
            'student_id' => $student->id,
            'enrollment_ids' => [$firstEnrollment->id, $secondEnrollment->id],
        ])->assertRedirect();
        $this->assertEqualsCanonicalizing(
            [$firstEnrollment->id, $secondEnrollment->id],
            $event->participants()->pluck('enrollment_id')->all(),
        );

        $this->post(route('events.publish', $event))->assertRedirect();
        $this->post(route('events.publish', $event))->assertRedirect();
        $invoice = $event->invoices()->notVoid()->sole();
        $this->assertSame($student->id, $invoice->student_id);
        $this->assertNull($invoice->enrollment_id);
        $this->assertSame(620000, $invoice->total_amount);
        $this->assertSame([310000, 310000], $invoice->items()->orderBy('id')->pluck('amount')->all());
        $this->assertSame(2, $invoice->items()->where('item_code', 'MC')->count());

        $participants = $event->participants()->with('activeTeacherAssignment')->get()->keyBy('enrollment_id');
        $firstParticipant = $participants->get($firstEnrollment->id);
        $secondParticipant = $participants->get($secondEnrollment->id);
        $firstAssignment = $firstParticipant->activeTeacherAssignment;
        $absentAssignment = $secondParticipant->activeTeacherAssignment;

        $this->patch(route('event-teacher-assignments.attendance', $firstAssignment), [
            'attendance_status' => 'HADIR',
        ])->assertRedirect();
        $this->patch(route('event-teacher-assignments.attendance', $absentAssignment), [
            'attendance_status' => 'TIDAK_HADIR',
            'reason' => 'Sakit pada hari acara.',
        ])->assertRedirect();
        $this->patch(route('event-participants.reassign', $secondParticipant), [
            'teacher_id' => $replacementTeacher->id,
            'reason' => 'Pendamping pengganti per enrollment.',
        ])->assertRedirect();
        $secondParticipant->refresh()->load('activeTeacherAssignment');
        $this->patch(route('event-teacher-assignments.attendance', $secondParticipant->activeTeacherAssignment), [
            'attendance_status' => 'HADIR',
        ])->assertRedirect();

        $this->actingAs($firstGuru)->patch(route('guru.events.attendance.update', $firstParticipant), [
            'attendance_status' => 'HADIR',
            'event_notes' => 'Tampil sesuai jadwal.',
        ])->assertRedirect(route('guru.events.show', $event));
        $this->actingAs($replacementGuru)->patch(route('guru.events.attendance.update', $secondParticipant), [
            'attendance_status' => 'TIDAK_HADIR',
            'event_notes' => 'Murid berhalangan hadir.',
        ])->assertRedirect(route('guru.events.show', $event));

        $this->actingAs($admin)->post(route('events.complete', $event))->assertRedirect();
        $this->post(route('events.complete', $event))->assertRedirect();
        $entries = EventHonorEntry::where('event_id', $event->id)
            ->where('status', EventHonorEntry::STATUS_POSTED)
            ->orderBy('amount')
            ->get();
        $this->assertCount(2, $entries);
        $this->assertEqualsCanonicalizing(
            [$firstTeacher->id, $replacementTeacher->id],
            $entries->pluck('teacher_id')->all(),
        );
        $this->assertSame([42500, 50000], $entries->pluck('amount')->all());
        $this->assertFalse($entries->contains('teacher_id', $absentTeacher->id));

        $this->post(route('payments.store', $invoice), [
            'amount' => 620000,
            'method' => Payment::METHOD_CASH,
            'payment_date' => '2026-06-20',
        ])->assertRedirect(route('invoices.show', $invoice));
        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_PAID, $invoice->status);

        $summary = app(EventFinanceService::class)->summary($event);
        $this->assertSame(620000, $summary['collected']);
        $this->assertSame(92500, $summary['final_honor']);
        $this->assertSame(527500, $summary['cash_margin']);
        $finance = $this->actingAs($owner)
            ->get(route('reports.finance', ['year' => 2026, 'month' => 6]))
            ->assertOk()
            ->assertSee('Mini Concert');
        $miniConcertRevenue = $finance->viewData('revenueByType')->firstWhere('item_code', 'MC');
        $this->assertSame(620000, (int) $miniConcertRevenue->total);
        $this->assertSame(1, (int) $miniConcertRevenue->invoice_count);
        $this->assertSame(620000, (int) $finance->viewData('totalRevenue'));
        $this->assertSame(620000, (int) ($finance->viewData('revenueByMethod')['CASH'] ?? 0));

        $this->assertSame($sessionCount, ClassSession::count());
        $this->assertSame($sessionBefore, $session->fresh()->getAttributes());
        $this->assertSame($sessionNoteBefore, $sessionNote->fresh()->getAttributes());
    }

    public function test_required_event_mutations_are_audited_with_actor_and_meaningful_changes(): void
    {
        $owner = User::factory()->create()->assignRole('Owner');
        $admin = User::factory()->create()->assignRole('Admin');
        $teacher = Teacher::factory()->create();
        $replacement = Teacher::factory()->create();
        $enrollment = Enrollment::factory()->create(['teacher_id' => $teacher->id]);
        InvoiceComponent::create([
            'code' => 'MC', 'name' => 'Mini Concert', 'default_price' => 310000, 'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('events.store'), [
            'name' => 'Audit Mini Concert',
            'type' => Event::TYPE_MINI_CONCERT,
            'event_date' => '2026-06-15',
            'payment_due_date' => '2026-06-10',
            'notes' => 'Versi awal.',
        ])->assertRedirect();
        $event = Event::where('name', 'Audit Mini Concert')->sole();

        $this->patch(route('events.update', $event), [
            'name' => 'Audit Mini Concert Diperbarui',
            'type' => Event::TYPE_MINI_CONCERT,
            'event_date' => '2026-06-15',
            'payment_due_date' => '2026-06-10',
            'notes' => 'Versi diperbarui.',
        ])->assertRedirect();
        $this->post(route('events.participants.store', $event), [
            'student_id' => $enrollment->student_id,
            'enrollment_ids' => [$enrollment->id],
        ])->assertRedirect();
        $participant = $event->participants()->sole();
        $this->post(route('events.publish', $event))->assertRedirect();
        $this->patch(route('events.reschedule', $event), [
            'name' => 'Audit Mini Concert Dijadwalkan Ulang',
            'event_date' => '2026-06-16',
            'payment_due_date' => '2026-06-11',
            'notes' => 'Jadwal baru.',
        ])->assertRedirect();

        $participant->refresh()->load('activeTeacherAssignment');
        app(EventAttendanceService::class)->confirmTeacher(
            $participant->activeTeacherAssignment, 'HADIR', $admin, null,
        );
        app(EventAttendanceService::class)->recordParticipant($participant, 'HADIR', 'Catatan awal.', $admin);
        app(EventAttendanceService::class)->resetParticipant($participant, $admin, 'Koreksi absensi.');
        $participant = app(EventAttendanceService::class)->reassignParticipant(
            $participant, $replacement, $admin, 'Pergantian pendamping.',
        );
        app(EventAttendanceService::class)->confirmTeacher(
            $participant->activeTeacherAssignment, 'HADIR', $admin, null,
        );
        app(EventAttendanceService::class)->recordParticipant($participant, 'TIDAK_HADIR', 'Murid berhalangan.', $admin);
        app(EventLifecycleService::class)->complete($event, $admin);
        app(EventLifecycleService::class)->reopen($event, $owner, 'Perbaikan data event.');

        $eventAudits = AuditLog::where('entity_type', 'Event')->where('entity_id', $event->id)->get();
        $this->assertAudit($eventAudits->firstWhere('action', AuditLog::ACTION_CREATE), $admin, false, true);
        $this->assertAudit($eventAudits->first(fn (AuditLog $audit) =>
            ($audit->old_values['name'] ?? null) === 'Audit Mini Concert'
            && ($audit->new_values['name'] ?? null) === 'Audit Mini Concert Diperbarui'), $admin);
        $this->assertAudit($eventAudits->first(fn (AuditLog $audit) =>
            ($audit->old_values['status'] ?? null) === Event::STATUS_DRAFT
            && ($audit->new_values['status'] ?? null) === Event::STATUS_PUBLISHED), $admin);
        $this->assertAudit($eventAudits->first(fn (AuditLog $audit) =>
            ($audit->old_values['event_date'] ?? null) === '2026-06-15'
            && ($audit->new_values['event_date'] ?? null) === '2026-06-16'), $admin);
        $this->assertAudit($eventAudits->first(fn (AuditLog $audit) =>
            ($audit->old_values['status'] ?? null) === Event::STATUS_PUBLISHED
            && ($audit->new_values['status'] ?? null) === Event::STATUS_COMPLETED), $admin);
        $this->assertAudit($eventAudits->first(fn (AuditLog $audit) =>
            ($audit->old_values['status'] ?? null) === Event::STATUS_COMPLETED
            && ($audit->new_values['status'] ?? null) === Event::STATUS_PUBLISHED), $owner);

        $participantAudits = AuditLog::where('entity_type', 'EventParticipant')
            ->where('entity_id', $participant->id)->get();
        $this->assertAudit($participantAudits->firstWhere('action', AuditLog::ACTION_CREATE), $admin, false, true);
        $this->assertAudit($participantAudits->first(fn (AuditLog $audit) =>
            ($audit->old_values['attendance_status'] ?? null) === 'PENDING'
            && ($audit->new_values['attendance_status'] ?? null) === 'HADIR'), $admin);
        $this->assertAudit($participantAudits->first(fn (AuditLog $audit) =>
            ($audit->old_values['attendance_status'] ?? null) === 'HADIR'
            && ($audit->new_values['attendance_status'] ?? null) === 'PENDING'), $admin);
        $this->assertAudit($participantAudits->first(fn (AuditLog $audit) =>
            ($audit->old_values['active_teacher_assignment_id'] ?? null)
                !== ($audit->new_values['active_teacher_assignment_id'] ?? null)), $admin);

        foreach (AuditLog::where('entity_type', 'EventTeacherAssignment')->get() as $audit) {
            $this->assertAudit($audit, $admin);
        }
        $this->assertCount(2, AuditLog::where('entity_type', 'EventTeacherAssignment')->get());

        $honorAudits = AuditLog::where('entity_type', 'EventHonorEntry')->get();
        $this->assertAudit($honorAudits->firstWhere('action', AuditLog::ACTION_CREATE), $admin, false, true);
        $this->assertAudit($honorAudits->first(fn (AuditLog $audit) =>
            ($audit->old_values['status'] ?? null) === EventHonorEntry::STATUS_POSTED
            && ($audit->new_values['status'] ?? null) === EventHonorEntry::STATUS_REVERSED), $owner);

        $cancelEvent = Event::factory()->create([
            'event_date' => '2026-06-18',
            'payment_due_date' => '2026-06-12',
            'created_by' => $admin->id,
        ]);
        $cancelEnrollment = Enrollment::factory()->create(['teacher_id' => $teacher->id]);
        $cancelParticipant = app(EventParticipantService::class)
            ->addEnrollments($cancelEvent, $cancelEnrollment->student, [$cancelEnrollment->id], $admin)->sole();
        app(EventLifecycleService::class)->publish($cancelEvent, $admin);
        app(EventParticipantService::class)->cancelParticipant(
            $cancelParticipant, $admin, 'Peserta membatalkan keikutsertaan.',
        );
        app(EventLifecycleService::class)->cancel($cancelEvent, $admin, 'Event tidak dapat dilaksanakan.');

        $this->assertAudit(AuditLog::where('entity_type', 'EventParticipant')
            ->where('entity_id', $cancelParticipant->id)->where('action', AuditLog::ACTION_UPDATE)->latest('id')->first(), $admin);
        $this->assertAudit(AuditLog::where('entity_type', 'Invoice')
            ->where('action', AuditLog::ACTION_VOID)->latest('id')->first(), $admin);
        $this->assertAudit(AuditLog::where('entity_type', 'Event')->where('entity_id', $cancelEvent->id)
            ->get()->first(fn (AuditLog $audit) => ($audit->new_values['status'] ?? null) === Event::STATUS_CANCELLED), $admin);

        foreach (AuditLog::whereIn('entity_type', [
            'Event', 'EventParticipant', 'EventTeacherAssignment', 'Invoice', 'EventHonorEntry',
        ])->get() as $audit) {
            $this->assertNotNull($audit->user_id, "Audit #{$audit->id} tidak memiliki actor.");
            $this->assertNotEmpty($audit->user_name, "Audit #{$audit->id} tidak memiliki nama actor.");
            $this->assertTrue(
                filled($audit->old_values) || $audit->action === AuditLog::ACTION_CREATE,
                "Audit #{$audit->id} tidak memiliki nilai lama yang bermakna.",
            );
            $this->assertNotEmpty($audit->new_values, "Audit #{$audit->id} tidak memiliki nilai baru yang bermakna.");
        }
    }

    public function test_lifecycle_audit_label_is_generic_for_students_and_events(): void
    {
        $audit = new AuditLog(['action' => AuditLog::ACTION_LIFECYCLE]);

        $this->assertSame('Perubahan Status', $audit->action_label);
    }

    /** @return array{Teacher, User} */
    private function teacherWithGuruAccount(): array
    {
        $user = User::factory()->create()->assignRole('Guru');
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);

        return [$teacher, $user];
    }

    private function assertAudit(
        ?AuditLog $audit,
        User $actor,
        bool $expectsOldValues = true,
        bool $expectsNewValues = true,
    ): void {
        $this->assertNotNull($audit, 'Audit wajib tidak ditemukan.');
        $this->assertSame($actor->id, $audit->user_id);
        $this->assertSame($actor->name, $audit->user_name);
        if ($expectsOldValues) {
            $this->assertNotEmpty($audit->old_values);
        }
        if ($expectsNewValues) {
            $this->assertNotEmpty($audit->new_values);
        }
    }
}
