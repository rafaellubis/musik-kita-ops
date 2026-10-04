<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventTeacherAssignment;
use App\Models\HonorSlip;
use App\Models\Teacher;
use App\Models\User;
use App\Services\EventAttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventTeacherAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;
    private Event $event;
    private EventTeacherAssignment $assignment;
    private EventParticipant $participant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 12, 20)->startOfDay());
        foreach (['Owner', 'Admin', 'Auditor', 'Guru'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $this->actor = User::factory()->create()->assignRole('Admin');
        $this->actingAs($this->actor);
        $this->event = Event::factory()->create(['status' => 'PUBLISHED', 'event_date' => '2026-12-20']);
        $this->assignment = EventTeacherAssignment::factory()->create(['event_id' => $this->event->id]);
        $this->participant = EventParticipant::factory()->create([
            'event_id' => $this->event->id, 'active_teacher_assignment_id' => $this->assignment->id,
        ]);
    }

    public function test_attendance_cannot_be_confirmed_before_event_date(): void
    {
        $this->travelTo(now()->subSecond());
        $this->confirm('HADIR')->assertUnprocessable();
        $this->assertSame('PENDING', $this->assignment->fresh()->attendance_status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    #[DataProvider('staffRoles')]
    public function test_owner_or_admin_can_confirm_teacher_hadir_or_tidak_hadir(string $role): void
    {
        $actor = User::factory()->create()->assignRole($role);
        $this->actingAs($actor);
        $this->confirm('TIDAK_HADIR', 'Sakit')->assertRedirect();
        $this->assignment->refresh();
        $this->assertSame('TIDAK_HADIR', $this->assignment->attendance_status);
        $this->assertSame('Sakit', $this->assignment->absence_reason);
        $this->assertSame($actor->id, $this->assignment->confirmed_by);
        $this->assertNotNull($this->assignment->confirmed_at);
        $this->confirm('HADIR')->assertRedirect();
        $this->assertSame('HADIR', $this->assignment->fresh()->attendance_status);
        $this->assertNull($this->assignment->fresh()->absence_reason);
        $audit = AuditLog::latest('id')->first();
        $this->assertSame('EventTeacherAssignment', $audit->entity_type);
        $this->assertSame('TIDAK_HADIR', $audit->old_values['attendance_status']);
        $this->assertSame('HADIR', $audit->new_values['attendance_status']);
        $this->assertSame($actor->id, $audit->user_id);
    }

    public static function staffRoles(): array
    {
        return [['Owner'], ['Admin']];
    }

    public function test_teacher_confirmation_requires_valid_status_absence_reason_and_active_participants(): void
    {
        $this->confirm('TIDAK_HADIR', '   ')->assertUnprocessable();
        $this->confirm('PENDING')->assertUnprocessable();
        $this->confirm('INVALID')->assertUnprocessable();
        $this->confirm('TIDAK_HADIR', str_repeat('x', 1001))->assertUnprocessable();
        $this->participant->update(['status' => 'CANCELLED']);
        $this->confirm('HADIR')->assertUnprocessable();
        $this->assertSame('PENDING', $this->assignment->fresh()->attendance_status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    #[DataProvider('closedStatuses')]
    public function test_teacher_confirmation_requires_published_event(string $status): void
    {
        $this->event->update(['status' => $status]);
        $this->confirm('HADIR')->assertUnprocessable();
        $this->assertSame('PENDING', $this->assignment->fresh()->attendance_status);
    }

    public static function closedStatuses(): array
    {
        return [['DRAFT'], ['COMPLETED'], ['CANCELLED']];
    }

    public function test_teacher_confirmation_route_rejects_non_staff(): void
    {
        foreach (['Auditor', 'Guru'] as $role) {
            $this->actingAs(User::factory()->create()->assignRole($role));
            $this->confirm('HADIR')->assertForbidden();
        }
        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function confirm(string $status, ?string $reason = null)
    {
        return $this->patchJson('/event-teacher-assignments/'.$this->assignment->id.'/attendance', [
            'attendance_status' => $status, 'reason' => $reason,
        ]);
    }

    public function test_tidak_hadir_requires_reason_and_locks_participant_attendance(): void
    {
        $this->confirm('TIDAK_HADIR')->assertUnprocessable();
        $this->record('HADIR')->assertUnprocessable();
        $this->confirm('TIDAK_HADIR', 'Sakit')->assertRedirect();
        $this->record('HADIR')->assertUnprocessable();
        $this->assertSame('PENDING', $this->participant->fresh()->attendance_status);
    }

    #[DataProvider('staffRoles')]
    public function test_owner_or_admin_can_record_student_attendance_for_present_teacher(string $role): void
    {
        $actor = User::factory()->create()->assignRole($role);
        $this->actingAs($actor);
        $this->confirm('HADIR')->assertRedirect();
        $this->record('HADIR', 'Tampil lancar')->assertRedirect();
        $this->assertSame('HADIR', $this->participant->fresh()->attendance_status);
        $this->record('TIDAK_HADIR', 'Koreksi absensi')->assertRedirect();
        $participant = $this->participant->fresh();
        $this->assertSame('TIDAK_HADIR', $participant->attendance_status);
        $this->assertSame('Koreksi absensi', $participant->event_notes);
        $this->assertSame($actor->id, $participant->attendance_updated_by);
        $this->assertNotNull($participant->attendance_updated_at);
        $audit = AuditLog::latest('id')->first();
        $this->assertSame('EventParticipant', $audit->entity_type);
        $this->assertSame('HADIR', $audit->old_values['attendance_status']);
        $this->assertSame('TIDAK_HADIR', $audit->new_values['attendance_status']);
        $this->assertDatabaseCount('sessions', 0);
        $this->assertDatabaseCount('event_honor_entries', 0);
    }

    #[DataProvider('staffRoles')]
    public function test_reset_requires_reason_and_audits_previous_attendance_even_for_absent_teacher(string $role): void
    {
        $this->actor->syncRoles($role);
        $this->assignment->update(['attendance_status' => 'HADIR']);
        $this->record('HADIR', 'Catatan lama')->assertRedirect();
        $this->assignment->update(['attendance_status' => 'TIDAK_HADIR']);
        $this->resetAttendance('  ')->assertUnprocessable();
        $this->assertSame('HADIR', $this->participant->fresh()->attendance_status);
        $this->resetAttendance('Salah pilih murid')->assertRedirect();
        $participant = $this->participant->fresh();
        $this->assertSame('PENDING', $participant->attendance_status);
        $this->assertNull($participant->event_notes);
        $this->assertSame($this->actor->id, $participant->attendance_updated_by);
        $this->assertNotNull($participant->attendance_updated_at);
        $audit = AuditLog::latest('id')->first();
        $this->assertSame('HADIR', $audit->old_values['attendance_status']);
        $this->assertSame('Catatan lama', $audit->old_values['event_notes']);
        $this->assertSame('PENDING', $audit->new_values['attendance_status']);
        $this->assertSame('Salah pilih murid', $audit->new_values['reason']);
    }

    #[DataProvider('invalidParticipantContexts')]
    public function test_record_and_reset_revalidate_event_and_active_assignment(string $case): void
    {
        $this->assignment->update(['attendance_status' => 'HADIR']);
        match ($case) {
            'future' => $this->event->update(['event_date' => '2026-12-21']),
            'draft', 'completed', 'cancelled_event' => $this->event->update(['status' => match ($case) {
                'draft' => 'DRAFT', 'completed' => 'COMPLETED', 'cancelled_event' => 'CANCELLED',
            }]),
            'cancelled_participant' => $this->participant->update(['status' => 'CANCELLED']),
            'missing_assignment' => $this->participant->update(['active_teacher_assignment_id' => null]),
            'foreign_assignment' => $this->participant->update([
                'active_teacher_assignment_id' => EventTeacherAssignment::factory()->hadir()->create()->id,
            ]),
        };
        $this->record('HADIR')->assertUnprocessable();
        $this->resetAttendance('Koreksi')->assertUnprocessable();
        $this->assertSame('PENDING', $this->participant->fresh()->attendance_status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public static function invalidParticipantContexts(): array
    {
        return [['future'], ['draft'], ['completed'], ['cancelled_event'], ['cancelled_participant'], ['missing_assignment'], ['foreign_assignment']];
    }

    public function test_participant_routes_validate_payloads_and_staff_roles(): void
    {
        $this->assignment->update(['attendance_status' => 'HADIR']);
        $this->record('PENDING')->assertUnprocessable();
        $this->record('INVALID')->assertUnprocessable();
        $this->record('HADIR', str_repeat('x', 1001))->assertUnprocessable();
        $this->resetAttendance(str_repeat('x', 1001))->assertUnprocessable();
        foreach (['Auditor', 'Guru'] as $role) {
            $this->actingAs(User::factory()->create()->assignRole($role));
            $this->record('HADIR')->assertForbidden();
            $this->resetAttendance('Koreksi')->assertForbidden();
        }
        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function record(string $status, ?string $notes = null)
    {
        return $this->patchJson('/event-participants/'.$this->participant->id.'/attendance', [
            'attendance_status' => $status, 'event_notes' => $notes,
        ]);
    }

    private function resetAttendance(string $reason)
    {
        return $this->postJson('/event-participants/'.$this->participant->id.'/attendance/reset', ['reason' => $reason]);
    }

    #[DataProvider('staffRoles')]
    public function test_reassignment_is_per_participant_and_creates_history(string $role): void
    {
        $this->actor->syncRoles($role);
        $sibling = EventParticipant::factory()->create([
            'event_id' => $this->event->id, 'active_teacher_assignment_id' => $this->assignment->id,
        ]);
        $this->confirm('TIDAK_HADIR', 'Sakit')->assertRedirect();
        $replacement = Teacher::factory()->create();
        $this->reassign($replacement)->assertRedirect();
        $participant = $this->participant->fresh();
        $assignment = $participant->activeTeacherAssignment;
        $this->assertSame($replacement->id, $assignment->teacher_id);
        $this->assertSame($this->event->id, $assignment->event_id);
        $this->assertSame('PENDING', $assignment->attendance_status);
        $this->assertNull($assignment->confirmed_at);
        $this->assertSame($this->assignment->id, $sibling->fresh()->active_teacher_assignment_id);
        $this->assertSame('TIDAK_HADIR', $this->assignment->fresh()->attendance_status);
        $history = $participant->teacherReassignments()->sole();
        $this->assertSame($this->event->id, $history->event_id);
        $this->assertSame($this->assignment->teacher_id, $history->from_teacher_id);
        $this->assertSame($replacement->id, $history->to_teacher_id);
        $this->assertSame('Guru pengganti', $history->reason);
        $this->assertSame($this->actor->id, $history->changed_by);
        $this->assertNotNull($history->changed_at);
        $audit = AuditLog::latest('id')->first();
        $this->assertSame($this->assignment->id, $audit->old_values['active_teacher_assignment_id']);
        $this->assertSame($assignment->id, $audit->new_values['active_teacher_assignment_id']);
        $this->assertSame('Guru pengganti', $audit->new_values['reason']);
        $this->record('HADIR')->assertUnprocessable();
        $this->patchJson('/event-teacher-assignments/'.$assignment->id.'/attendance', ['attendance_status' => 'HADIR'])->assertRedirect();
        $this->record('HADIR')->assertRedirect();
        $this->assertDatabaseCount('sessions', 0);
        $this->assertDatabaseCount('event_honor_entries', 0);
    }

    #[DataProvider('existingAssignmentStatuses')]
    public function test_reassignment_reuses_existing_assignment_without_resetting_other_participants(string $status): void
    {
        $existing = EventTeacherAssignment::factory()->create([
            'event_id' => $this->event->id, 'attendance_status' => $status, 'confirmed_by' => $this->actor->id,
        ]);
        $sibling = EventParticipant::factory()->create([
            'event_id' => $this->event->id, 'active_teacher_assignment_id' => $existing->id, 'attendance_status' => 'HADIR',
        ]);
        $this->reassign($existing->teacher)->assertRedirect();
        $this->assertSame($existing->id, $this->participant->fresh()->active_teacher_assignment_id);
        $this->assertSame($status, $existing->fresh()->attendance_status);
        $this->assertSame('HADIR', $sibling->fresh()->attendance_status);
        $this->assertDatabaseCount('event_teacher_assignments', 2);
    }

    public static function existingAssignmentStatuses(): array
    {
        return [['PENDING'], ['HADIR'], ['TIDAK_HADIR']];
    }

    #[DataProvider('paidTeacherSides')]
    public function test_replacement_with_paid_slip_is_rejected(string $side): void
    {
        $replacement = Teacher::factory()->create();
        HonorSlip::factory()->create([
            'teacher_id' => $side === 'replacement' ? $replacement->id : $this->assignment->teacher_id,
            'year' => 2026, 'month' => 12, 'status' => 'PAID',
        ]);
        $this->reassign($replacement)->assertUnprocessable();
        $this->assertSame($this->assignment->id, $this->participant->fresh()->active_teacher_assignment_id);
        $this->assertDatabaseCount('event_teacher_assignments', 1);
        $this->assertDatabaseCount('event_teacher_reassignments', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public static function paidTeacherSides(): array
    {
        return [['replacement'], ['outgoing']];
    }

    public function test_paid_slip_in_other_period_does_not_block_reassignment(): void
    {
        $replacement = Teacher::factory()->create();
        HonorSlip::factory()->create(['teacher_id' => $replacement->id, 'year' => 2026, 'month' => 11, 'status' => 'PAID']);
        HonorSlip::factory()->create(['teacher_id' => $replacement->id, 'year' => 2025, 'month' => 12, 'status' => 'PAID']);
        HonorSlip::factory()->create(['teacher_id' => $replacement->id, 'year' => 2026, 'month' => 12, 'status' => 'CALCULATED']);
        $this->reassign($replacement)->assertRedirect();
    }

    #[DataProvider('recordedStatuses')]
    public function test_existing_student_attendance_must_be_reset_with_reason_before_reassignment(string $status): void
    {
        $this->participant->update(['attendance_status' => $status, 'event_notes' => 'Catatan lama']);
        $replacement = Teacher::factory()->create();
        $this->reassign($replacement)->assertUnprocessable();
        $this->resetAttendance('Koreksi pendamping')->assertRedirect();
        $this->reassign($replacement)->assertRedirect();
        $this->assertSame('PENDING', $this->participant->fresh()->attendance_status);
        $this->assertSame('Koreksi pendamping', AuditLog::orderBy('id')->first()->new_values['reason']);
    }

    public static function recordedStatuses(): array
    {
        return [['HADIR'], ['TIDAK_HADIR']];
    }

    public function test_reassignment_validates_reason_teacher_and_staff_role(): void
    {
        $replacement = Teacher::factory()->create();
        $this->reassign($replacement, '  ')->assertUnprocessable();
        $this->reassign($replacement, str_repeat('x', 1001))->assertUnprocessable();
        $this->reassign($this->assignment->teacher)->assertUnprocessable();
        $this->reassign(Teacher::factory()->create(['is_active' => false]))->assertUnprocessable();
        $this->patchJson('/event-participants/'.$this->participant->id.'/reassign', ['teacher_id' => 999999, 'reason' => 'Ganti'])->assertUnprocessable();
        foreach (['Auditor', 'Guru'] as $role) {
            $this->actingAs(User::factory()->create()->assignRole($role));
            $this->reassign($replacement)->assertForbidden();
        }
        $this->assertDatabaseCount('event_teacher_reassignments', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    #[DataProvider('invalidParticipantContexts')]
    public function test_reassignment_revalidates_event_and_active_assignment(string $case): void
    {
        match ($case) {
            'future' => $this->event->update(['event_date' => '2026-12-21']),
            'draft', 'completed', 'cancelled_event' => $this->event->update(['status' => match ($case) {
                'draft' => 'DRAFT', 'completed' => 'COMPLETED', 'cancelled_event' => 'CANCELLED',
            }]),
            'cancelled_participant' => $this->participant->update(['status' => 'CANCELLED']),
            'missing_assignment' => $this->participant->update(['active_teacher_assignment_id' => null]),
            'foreign_assignment' => $this->participant->update([
                'active_teacher_assignment_id' => EventTeacherAssignment::factory()->create()->id,
            ]),
        };
        $this->reassign(Teacher::factory()->create())->assertUnprocessable();
        $this->assertDatabaseCount('event_teacher_reassignments', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function reassign(Teacher $teacher, string $reason = 'Guru pengganti')
    {
        return $this->patchJson('/event-participants/'.$this->participant->id.'/reassign', [
            'teacher_id' => $teacher->id, 'reason' => $reason,
        ]);
    }

    public function test_linked_guru_can_record_only_their_active_assignment_after_teacher_is_present(): void
    {
        $guru = User::factory()->create()->assignRole('Guru');
        $this->assignment->teacher->update(['user_id' => $guru->id]);
        $this->assignment->update(['attendance_status' => 'HADIR']);
        try {
            $participant = app(EventAttendanceService::class)->recordParticipant($this->participant, 'HADIR', 'Penampilan baik', $guru);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->fail('Guru pemilik assignment HADIR harus boleh mencatat absensi; HTTP '.$exception->getStatusCode());
        }
        $this->assertSame('HADIR', $participant->attendance_status);
        $this->assertSame($guru->id, $participant->attendance_updated_by);
        $this->assertSame($guru->id, AuditLog::latest('id')->first()->user_id);

        $this->assignment->update(['attendance_status' => 'TIDAK_HADIR']);
        $this->assertServiceRejected(fn () => app(EventAttendanceService::class)->recordParticipant($this->participant, 'HADIR', null, $guru), 422);
        $this->assignment->update(['attendance_status' => 'PENDING']);
        $this->assertServiceRejected(fn () => app(EventAttendanceService::class)->recordParticipant($this->participant, 'HADIR', null, $guru), 422);

        // Model peserta lama tidak boleh memberi akses setelah assignment berpindah.
        $this->resetAttendance('Ganti guru')->assertRedirect();
        $this->reassign(Teacher::factory()->create())->assertRedirect();
        $this->participant->fresh()->activeTeacherAssignment->update(['attendance_status' => 'HADIR']);
        $this->assertServiceRejected(fn () => app(EventAttendanceService::class)->recordParticipant($this->participant, 'HADIR', null, $guru), 403);
        $this->assertSame('PENDING', $this->participant->fresh()->attendance_status);
    }

    private function assertServiceRejected(callable $operation, int $status): void
    {
        try {
            $operation();
            $this->fail('Mutasi seharusnya ditolak.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame($status, $exception->getStatusCode());
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertSame($status, $exception->status);
        }
    }

    public function test_service_enforces_staff_only_actions_and_guru_assignment_ownership(): void
    {
        $service = app(EventAttendanceService::class);
        $replacement = Teacher::factory()->create();
        $this->assignment->update(['attendance_status' => 'HADIR']);
        foreach (['Auditor', 'Guru'] as $role) {
            $actor = User::factory()->create()->assignRole($role);
            $this->assertServiceRejected(fn () => $service->confirmTeacher($this->assignment, 'HADIR', $actor, null), 403);
            $this->assertServiceRejected(fn () => $service->reassignParticipant($this->participant, $replacement, $actor, 'Ganti'), 403);
            $this->assertServiceRejected(fn () => $service->resetParticipant($this->participant, $actor, 'Reset'), 403);
            $this->assertServiceRejected(fn () => $service->recordParticipant($this->participant, 'HADIR', null, $actor), 403);
        }
        $unrelated = User::factory()->create()->assignRole('Guru');
        $replacement->update(['user_id' => $unrelated->id]);
        $this->assertServiceRejected(fn () => $service->recordParticipant($this->participant, 'HADIR', null, $unrelated), 403);
        $linkedNonGuru = User::factory()->create()->assignRole('Auditor');
        $this->assignment->teacher->update(['user_id' => $linkedNonGuru->id]);
        $this->assertServiceRejected(fn () => $service->recordParticipant($this->participant, 'HADIR', null, $linkedNonGuru), 403);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('event_teacher_reassignments', 0);
    }

    public function test_direct_service_calls_validate_payloads_and_stale_models(): void
    {
        $service = app(EventAttendanceService::class);
        $replacement = Teacher::factory()->create();
        $this->assertServiceRejected(fn () => $service->confirmTeacher($this->assignment, 'INVALID', $this->actor, null), 422);
        $this->assertServiceRejected(fn () => $service->confirmTeacher($this->assignment, 'TIDAK_HADIR', $this->actor, '   '), 422);
        $this->assertServiceRejected(fn () => $service->recordParticipant($this->participant, 'PENDING', null, $this->actor), 422);
        $this->assertServiceRejected(fn () => $service->recordParticipant($this->participant, 'HADIR', str_repeat('x', 1001), $this->actor), 422);
        $this->assertServiceRejected(fn () => $service->resetParticipant($this->participant, $this->actor, '  '), 422);
        $this->assertServiceRejected(fn () => $service->reassignParticipant($this->participant, $replacement, $this->actor, '  '), 422);

        Teacher::whereKey($replacement->id)->update(['is_active' => false]);
        $this->assertServiceRejected(fn () => $service->reassignParticipant($this->participant, $replacement, $this->actor, 'Ganti'), 422);
        EventTeacherAssignment::whereKey($this->assignment->id)->update(['attendance_status' => 'TIDAK_HADIR']);
        $this->assertServiceRejected(fn () => $service->recordParticipant($this->participant, 'HADIR', null, $this->actor), 422);
        EventParticipant::whereKey($this->participant->id)->update(['status' => 'CANCELLED']);
        $this->assertServiceRejected(fn () => $service->resetParticipant($this->participant, $this->actor, 'Reset'), 422);
        Event::whereKey($this->event->id)->update(['status' => 'COMPLETED']);
        $this->assertServiceRejected(fn () => $service->confirmTeacher($this->assignment, 'HADIR', $this->actor, null), 422);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('event_teacher_reassignments', 0);
    }

    #[DataProvider('attendanceOperations')]
    public function test_attendance_mutation_and_history_roll_back_when_audit_fails(string $operation): void
    {
        $this->assignment->update(['attendance_status' => 'HADIR']);
        if ($operation === 'reset') {
            $this->participant->update(['attendance_status' => 'HADIR', 'event_notes' => 'Catatan awal']);
        }
        $originalParticipant = $this->participant->fresh()->getAttributes();
        $originalAssignment = $this->assignment->fresh()->getAttributes();
        $replacement = Teacher::factory()->create();
        AuditLog::creating(function (): void {
            throw new \RuntimeException('Simulasi audit gagal.');
        });
        $service = app(EventAttendanceService::class);
        try {
            match ($operation) {
                'confirm' => $service->confirmTeacher($this->assignment, 'TIDAK_HADIR', $this->actor, 'Sakit'),
                'record' => $service->recordParticipant($this->participant, 'HADIR', 'Tampil', $this->actor),
                'reset' => $service->resetParticipant($this->participant, $this->actor, 'Koreksi'),
                'reassign' => $service->reassignParticipant($this->participant, $replacement, $this->actor, 'Ganti'),
            };
            $this->fail('Mutasi seharusnya gagal jika audit tidak tersimpan.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulasi audit gagal.', $exception->getMessage());
        }
        $this->assertSame($originalParticipant, $this->participant->fresh()->getAttributes());
        $this->assertSame($originalAssignment, $this->assignment->fresh()->getAttributes());
        $this->assertDatabaseCount('event_teacher_assignments', 1);
        $this->assertDatabaseCount('event_teacher_reassignments', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('sessions', 0);
    }

    public static function attendanceOperations(): array
    {
        return [['confirm'], ['record'], ['reset'], ['reassign']];
    }
}
