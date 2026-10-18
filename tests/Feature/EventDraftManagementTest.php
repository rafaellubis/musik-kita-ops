<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventTeacherAssignment;
use App\Models\Instrument;
use App\Models\Package;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventDraftManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Owner', 'Admin', 'Auditor', 'Guru'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_owner_and_admin_can_create_mini_concert_draft(): void
    {
        foreach (['Owner', 'Admin'] as $index => $role) {
            $response = $this->actingAs($this->userWithRole($role))->post(route('events.store'), [
                'name' => "Mini Concert {$role}",
                'type' => Event::TYPE_MINI_CONCERT,
                'event_date' => "2026-12-1{$index}",
                'payment_due_date' => "2026-12-0".($index + 1),
                'notes' => 'Event akhir tahun.',
            ]);

            $response->assertSessionHasNoErrors();
            $response->assertRedirect();
            $event = Event::where('name', "Mini Concert {$role}")->firstOrFail();
            $response->assertRedirect(route('events.show', $event));
            $this->assertSame(Event::STATUS_DRAFT, $event->status);
            $this->assertSame($response->getSession()->get('success') !== null, true);
        }
    }

    public function test_event_validation_rejects_reserved_type_and_due_date_after_event(): void
    {
        $owner = $this->userWithRole('Owner');

        $this->actingAs($owner)->post(route('events.store'), [
            'name' => 'Ujian belum tersedia',
            'type' => Event::TYPE_UJIAN_GRADE,
            'event_date' => '2026-12-10',
            'payment_due_date' => '2026-12-11',
        ])->assertInvalid(['type', 'payment_due_date']);

        $this->assertDatabaseCount('events', 0);
    }

    public function test_auditor_and_guru_cannot_mutate_event(): void
    {
        $event = Event::factory()->create();
        $enrollment = Enrollment::factory()->create();
        $participant = EventParticipant::factory()->create(['event_id' => $event->id]);
        $teacher = Teacher::factory()->create(['is_active' => true]);

        foreach (['Auditor', 'Guru'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->post(route('events.store'), $this->eventPayload($role))
                ->assertForbidden();
            $this->actingAs($user)->patch(route('events.update', $event), $this->eventPayload($role))
                ->assertForbidden();
            $this->actingAs($user)->delete(route('events.destroy', $event))
                ->assertForbidden();
            $this->actingAs($user)->post(route('events.participants.store', $event), [
                'student_id' => $enrollment->student_id,
                'enrollment_ids' => [$enrollment->id],
            ])->assertForbidden();
            $this->actingAs($user)->delete(route('event-participants.destroy', $participant), [
                'cancellation_reason' => 'Tidak berwenang.',
            ])->assertForbidden();
            $this->actingAs($user)->patch(route('event-participants.update-teacher', $participant), [
                'teacher_id' => $teacher->id,
            ])->assertForbidden();
        }

        $this->assertDatabaseCount('events', 1);
        $this->assertSame(EventParticipant::STATUS_ACTIVE, $participant->fresh()->status);
        $this->assertNotSame($teacher->id, $participant->fresh()->activeTeacherAssignment?->teacher_id);
    }

    public function test_single_active_enrollment_is_selected_and_multiple_can_be_submitted(): void
    {
        $admin = $this->userWithRole('Admin');
        $event = Event::factory()->create();
        $singleStudent = Student::factory()->create();
        $single = Enrollment::factory()->create(['student_id' => $singleStudent->id]);

        $this->actingAs($admin)->post(route('events.participants.store', $event), [
            'student_id' => $singleStudent->id,
        ])->assertRedirect();

        $multiStudent = Student::factory()->create();
        $first = Enrollment::factory()->create(['student_id' => $multiStudent->id]);
        $second = Enrollment::factory()->create(['student_id' => $multiStudent->id]);

        $this->actingAs($admin)->post(route('events.participants.store', $event), [
            'student_id' => $multiStudent->id,
            'enrollment_ids' => [$first->id, $second->id],
        ])->assertRedirect();

        $this->assertEqualsCanonicalizing(
            [$single->id, $first->id, $second->id],
            $event->participants()->pluck('enrollment_id')->all(),
        );
        $this->assertSame(0, $event->participants()->whereNotNull('invoice_id')->count());
    }

    public function test_explicit_null_enrollment_ids_uses_the_single_active_enrollment(): void
    {
        $admin = $this->userWithRole('Admin');
        $event = Event::factory()->create();
        $enrollment = Enrollment::factory()->create();

        $this->actingAs($admin)->post(route('events.participants.store', $event), [
            'student_id' => $enrollment->student_id,
            'enrollment_ids' => null,
        ])->assertRedirect();

        $this->assertDatabaseHas('event_participants', [
            'event_id' => $event->id,
            'enrollment_id' => $enrollment->id,
            'status' => EventParticipant::STATUS_ACTIVE,
        ]);
    }

    public function test_inactive_or_foreign_student_enrollment_is_rejected(): void
    {
        $admin = $this->userWithRole('Admin');
        $event = Event::factory()->create();
        $student = Student::factory()->create();
        $inactive = Enrollment::factory()->create([
            'student_id' => $student->id,
            'status' => Enrollment::STATUS_INACTIVE,
        ]);
        $foreign = Enrollment::factory()->create();

        foreach ([$inactive, $foreign] as $enrollment) {
            $this->actingAs($admin)->post(route('events.participants.store', $event), [
                'student_id' => $student->id,
                'enrollment_ids' => [$enrollment->id],
            ])->assertInvalid(['enrollment_ids']);
        }

        $this->assertDatabaseCount('event_participants', 0);
    }

    public function test_same_enrollment_cannot_be_added_twice_and_deleted_draft_participant_can_be_added_again(): void
    {
        $admin = $this->userWithRole('Admin');
        $event = Event::factory()->create();
        $enrollment = Enrollment::factory()->create();
        $payload = [
            'student_id' => $enrollment->student_id,
            'enrollment_ids' => [$enrollment->id],
        ];

        $this->actingAs($admin)->post(route('events.participants.store', $event), $payload)
            ->assertRedirect();
        $participant = EventParticipant::firstOrFail();

        $this->actingAs($admin)->post(route('events.participants.store', $event), $payload)
            ->assertInvalid(['enrollment_ids']);

        $this->actingAs($admin)->delete(route('event-participants.destroy', $participant), [
            'cancellation_reason' => 'Murid membatalkan keikutsertaan.',
        ])->assertRedirect();
        $this->assertDatabaseMissing('event_participants', ['id' => $participant->id]);

        $this->actingAs($admin)->post(route('events.participants.store', $event), $payload)
            ->assertRedirect();

        $this->assertDatabaseCount('event_participants', 1);
        $replacement = EventParticipant::firstOrFail();
        $this->assertNotSame($participant->id, $replacement->id);
        $this->assertSame(EventParticipant::STATUS_ACTIVE, $replacement->status);
        $deletionAudit = AuditLog::query()
            ->where('user_id', $admin->id)
            ->where('action', AuditLog::ACTION_DELETE)
            ->where('entity_type', 'EventParticipant')
            ->where('entity_id', $participant->id)
            ->firstOrFail();
        $this->assertSame($enrollment->id, $deletionAudit->old_values['enrollment_id']);
    }

    public function test_event_mutation_and_audit_log_are_atomic(): void
    {
        $owner = $this->userWithRole('Owner');
        AuditLog::creating(function (): void {
            if (request()->header('X-Test-Failure') === 'audit') {
                throw new \RuntimeException('Simulasi audit gagal.');
            }
        });
        Event::deleting(function (): void {
            if (request()->header('X-Test-Failure') === 'delete') {
                throw new \RuntimeException('Simulasi delete gagal.');
            }
        });

        $this->assertRuntimeFailure(fn () => $this->actingAs($owner)
            ->withHeader('X-Test-Failure', 'audit')
            ->post(route('events.store'), $this->eventPayload('Atomic Store')));
        $this->assertDatabaseMissing('events', ['name' => 'Mini Concert Atomic Store']);

        $event = Event::factory()->create(['name' => 'Nama Awal']);
        $this->assertRuntimeFailure(fn () => $this->actingAs($owner)
            ->withHeader('X-Test-Failure', 'audit')
            ->patch(route('events.update', $event), $this->eventPayload('Atomic Update')));
        $this->assertSame('Nama Awal', $event->fresh()->name);

        $this->assertRuntimeFailure(fn () => $this->actingAs($owner)
            ->withHeader('X-Test-Failure', 'delete')
            ->delete(route('events.destroy', $event)));
        $this->assertModelExists($event);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditLog::ACTION_DELETE,
            'entity_type' => 'Event',
            'entity_id' => $event->id,
        ]);
    }

    public function test_update_and_delete_recheck_the_locked_event_status(): void
    {
        $owner = $this->userWithRole('Owner');
        Event::retrieved(function (Event $event): void {
            if (request()->header('X-Stale-Event') === (string) $event->id && $event->isDraft()) {
                DB::table('events')->where('id', $event->id)->update(['status' => Event::STATUS_PUBLISHED]);
            }
        });

        $updateEvent = Event::factory()->create(['name' => 'Jangan Diubah']);
        $this->actingAs($owner)
            ->withHeader('X-Stale-Event', (string) $updateEvent->id)
            ->patch(route('events.update', $updateEvent), $this->eventPayload('Stale Update'))
            ->assertUnprocessable();
        $updateEvent->refresh();
        $this->assertSame('Jangan Diubah', $updateEvent->name);
        $this->assertSame(Event::STATUS_PUBLISHED, $updateEvent->status);

        $deleteEvent = Event::factory()->create();
        $this->actingAs($owner)
            ->withHeader('X-Stale-Event', (string) $deleteEvent->id)
            ->delete(route('events.destroy', $deleteEvent))
            ->assertUnprocessable();
        $this->assertModelExists($deleteEvent);
        $this->assertSame(Event::STATUS_PUBLISHED, $deleteEvent->fresh()->status);
    }

    public function test_active_teacher_can_override_enrollment_teacher_while_draft(): void
    {
        $admin = $this->userWithRole('Admin');
        $event = Event::factory()->create();
        $enrollment = Enrollment::factory()->create();
        $replacement = Teacher::factory()->create(['is_active' => true]);

        $this->actingAs($admin)->post(route('events.participants.store', $event), [
            'student_id' => $enrollment->student_id,
            'enrollment_ids' => [$enrollment->id],
        ]);
        $participant = EventParticipant::firstOrFail();

        $this->assertSame($enrollment->teacher_id, $participant->activeTeacherAssignment->teacher_id);

        $this->actingAs($admin)->patch(route('event-participants.update-teacher', $participant), [
            'teacher_id' => $replacement->id,
        ])->assertRedirect();

        $this->assertSame($replacement->id, $participant->fresh()->activeTeacherAssignment->teacher_id);

        $inactiveTeacher = Teacher::factory()->create(['is_active' => false]);
        $this->actingAs($admin)->patch(route('event-participants.update-teacher', $participant), [
            'teacher_id' => $inactiveTeacher->id,
        ])->assertInvalid(['teacher_id']);

        $published = Event::factory()->published()->create();
        $publishedParticipant = EventParticipant::factory()->create(['event_id' => $published->id]);
        $this->actingAs($admin)->patch(route('event-participants.update-teacher', $publishedParticipant), [
            'teacher_id' => $replacement->id,
        ])->assertUnprocessable();
    }

    public function test_only_draft_event_can_be_deleted(): void
    {
        $owner = $this->userWithRole('Owner');
        $draft = Event::factory()->create();
        $published = Event::factory()->published()->create();

        $this->actingAs($owner)->delete(route('events.destroy', $draft))
            ->assertRedirect(route('events.index'));
        $this->assertModelMissing($draft);

        $this->actingAs($owner)->delete(route('events.destroy', $published))
            ->assertUnprocessable();
        $this->assertModelExists($published);
    }

    public function test_package_and_honor_are_snapshotted_when_participant_is_added(): void
    {
        $admin = $this->userWithRole('Admin');
        $event = Event::factory()->create();
        $instrument = Instrument::factory()->create(['name' => 'Piano']);
        $package = Package::factory()->create([
            'instrument_id' => $instrument->id,
            'code' => 'REG-PIANO-BASIC',
            'class_type' => 'REGULER',
            'grade' => 'BASIC',
            'duration_min' => 30,
            'price_per_month' => 340000,
        ]);
        $enrollment = Enrollment::factory()->create(['package_id' => $package->id]);

        $this->actingAs($admin)->post(route('events.participants.store', $event), [
            'student_id' => $enrollment->student_id,
            'enrollment_ids' => [$enrollment->id],
        ])->assertRedirect();

        $participant = EventParticipant::firstOrFail();
        $this->assertSame($package->id, $participant->snapshot_package_id);
        $this->assertSame('REG-PIANO-BASIC', $participant->snapshot_package_code);
        $this->assertSame('Piano', $participant->snapshot_instrument_name);
        $this->assertSame('REGULER', $participant->snapshot_class_type);
        $this->assertSame('BASIC', $participant->snapshot_grade);
        $this->assertSame(30, $participant->snapshot_duration_min);
        $this->assertSame(340000, $participant->snapshot_package_price);
        $this->assertSame(42500, $participant->snapshot_honor_amount);
        $this->assertSame(0, $participant->snapshot_mc_fee);
        $this->assertNull($participant->invoice_id);
        $this->assertNull($participant->invoice_item_id);
        $this->assertDatabaseHas('event_teacher_assignments', [
            'event_id' => $event->id,
            'teacher_id' => $enrollment->teacher_id,
            'attendance_status' => EventTeacherAssignment::ATTENDANCE_PENDING,
        ]);

        $package->update(['price_per_month' => 500000]);
        $this->assertSame(340000, $participant->fresh()->snapshot_package_price);
        $this->assertDatabaseCount('invoices', 0);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    private function eventPayload(string $suffix): array
    {
        return [
            'name' => "Mini Concert {$suffix}",
            'type' => Event::TYPE_MINI_CONCERT,
            'event_date' => '2026-12-20',
            'payment_due_date' => '2026-12-10',
        ];
    }

    private function assertRuntimeFailure(callable $request): void
    {
        $this->withoutExceptionHandling();

        try {
            $request();
            $this->fail('Request seharusnya gagal.');
        } catch (\RuntimeException $exception) {
            $this->assertStringStartsWith('Simulasi ', $exception->getMessage());
        } finally {
            $this->withExceptionHandling();
        }
    }
}
