<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventTeacherAssignment;
use App\Models\EventTeacherReassignment;
use App\Models\HonorSlip;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EventAttendanceService
{
    public function reassignParticipant(EventParticipant $participant, Teacher $replacement, User $actor, string $reason): EventParticipant
    {
        abort_unless($actor->hasAnyRole(['Owner', 'Admin']), 403);
        Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'max:1000']])->validate();

        return DB::transaction(function () use ($participant, $replacement, $actor, $reason) {
            $event = $this->lockEvent($participant->event_id);
            $participant = $event->participants()->lockForUpdate()->findOrFail($participant->id);
            abort_unless($participant->status === EventParticipant::STATUS_ACTIVE, 422, 'Peserta sudah dibatalkan.');
            abort_unless($participant->attendance_status === EventParticipant::ATTENDANCE_PENDING,
                422, 'Reset kehadiran murid dengan alasan sebelum mengganti pendamping.');

            // Kunci kedua assignment menurut ID, termasuk jika pengganti sudah mendampingi peserta lain.
            $assignments = $event->teacherAssignments()
                ->where(fn ($query) => $query->where('id', $participant->active_teacher_assignment_id)
                    ->orWhere('teacher_id', $replacement->id))
                ->orderBy('id')->lockForUpdate()->get();
            $previous = $assignments->firstWhere('id', $participant->active_teacher_assignment_id);
            abort_unless($previous, 422, 'Peserta harus memiliki assignment aktif pada event ini.');
            abort_if($previous->teacher_id === $replacement->id, 422, 'Pilih guru pengganti yang berbeda.');
            $teachers = Teacher::whereIn('id', [$previous->teacher_id, $replacement->id])
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $replacement = $teachers->get($replacement->id);
            abort_unless($replacement, 404);
            abort_unless($replacement->is_active, 422, 'Guru pengganti harus masih aktif.');

            $slips = HonorSlip::whereIn('teacher_id', [$previous->teacher_id, $replacement->id])
                ->forMonth($event->event_date->year, $event->event_date->month)
                ->orderBy('id')->lockForUpdate()->get();
            abort_if($slips->contains('status', HonorSlip::STATUS_PAID),
                422, 'Slip honor guru pada bulan event sudah dibayar.');

            $assignment = $assignments->firstWhere('teacher_id', $replacement->id)
                ?? $event->teacherAssignments()->create([
                    'teacher_id' => $replacement->id,
                    'attendance_status' => EventTeacherAssignment::ATTENDANCE_PENDING,
                ]);
            $old = $participant->toArray();
            $participant->update(['active_teacher_assignment_id' => $assignment->id]);
            EventTeacherReassignment::create([
                'event_id' => $event->id, 'event_participant_id' => $participant->id,
                'from_teacher_id' => $previous->teacher_id, 'to_teacher_id' => $replacement->id,
                'reason' => $reason, 'changed_by' => $actor->id, 'changed_at' => now(),
            ]);
            $this->audit($participant, $actor, $old, $reason);

            return $participant->load('activeTeacherAssignment');
        });
    }

    public function recordParticipant(EventParticipant $participant, string $status, ?string $notes, User $actor): EventParticipant
    {
        abort_unless($actor->hasAnyRole(['Owner', 'Admin', 'Guru']), 403);
        Validator::make(['attendance_status' => $status, 'event_notes' => $notes], [
            'attendance_status' => ['required', 'in:HADIR,TIDAK_HADIR'],
            'event_notes' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        return DB::transaction(function () use ($participant, $status, $notes, $actor) {
            $event = $this->lockEvent($participant->event_id);
            $participant = $this->lockParticipant($event, $participant->id);
            abort_unless($actor->hasAnyRole(['Owner', 'Admin'])
                || $actor->teacher()->whereKey($participant->activeTeacherAssignment->teacher_id)->exists(), 403);
            abort_unless($participant->activeTeacherAssignment->attendance_status === EventTeacherAssignment::ATTENDANCE_HADIR,
                422, 'Kehadiran murid hanya dapat diisi setelah guru pendamping HADIR.');
            $old = $participant->toArray();
            $participant->update([
                'attendance_status' => $status, 'event_notes' => $notes,
                'attendance_updated_at' => now(), 'attendance_updated_by' => $actor->id,
            ]);
            $this->audit($participant, $actor, $old);

            return $participant;
        });
    }

    public function resetParticipant(EventParticipant $participant, User $actor, string $reason): EventParticipant
    {
        abort_unless($actor->hasAnyRole(['Owner', 'Admin']), 403);
        Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'max:1000']])->validate();

        return DB::transaction(function () use ($participant, $actor, $reason) {
            $event = $this->lockEvent($participant->event_id);
            $participant = $this->lockParticipant($event, $participant->id);
            $old = $participant->toArray();
            $participant->update([
                'attendance_status' => EventParticipant::ATTENDANCE_PENDING, 'event_notes' => null,
                'attendance_updated_at' => now(), 'attendance_updated_by' => $actor->id,
            ]);
            $this->audit($participant, $actor, $old, $reason);

            return $participant;
        });
    }

    public function confirmTeacher(EventTeacherAssignment $assignment, string $status, User $actor, ?string $reason): EventTeacherAssignment
    {
        abort_unless($actor->hasAnyRole(['Owner', 'Admin']), 403);
        Validator::make(['attendance_status' => $status, 'reason' => $reason], [
            'attendance_status' => ['required', 'in:HADIR,TIDAK_HADIR'],
            'reason' => ['required_if:attendance_status,TIDAK_HADIR', 'nullable', 'string', 'max:1000'],
        ])->validate();

        return DB::transaction(function () use ($assignment, $status, $actor, $reason) {
            $event = $this->lockEvent($assignment->event_id);
            $assignment = $event->teacherAssignments()->lockForUpdate()->findOrFail($assignment->id);
            abort_unless($assignment->participants()->where('event_id', $event->id)
                ->where('status', EventParticipant::STATUS_ACTIVE)->exists(),
                422, 'Assignment harus memiliki peserta aktif pada event ini.');
            $old = $assignment->toArray();
            $assignment->update([
                'attendance_status' => $status,
                'absence_reason' => $status === EventTeacherAssignment::ATTENDANCE_TIDAK_HADIR ? $reason : null,
                'confirmed_at' => now(),
                'confirmed_by' => $actor->id,
            ]);
            $this->audit($assignment, $actor, $old);

            return $assignment;
        });
    }

    /** Event selalu dikunci dahulu agar mutasi peserta dan lifecycle tidak saling mendahului. */
    private function lockEvent(int $eventId): Event
    {
        $event = Event::query()->lockForUpdate()->findOrFail($eventId);
        abort_unless($event->isPublished(), 422, 'Absensi hanya dapat diubah saat event PUBLISHED.');
        abort_unless($event->event_date && $event->event_date->lte(today()),
            422, 'Absensi baru dapat diisi mulai tanggal event.');

        return $event;
    }

    private function lockParticipant(Event $event, int $participantId): EventParticipant
    {
        $participant = $event->participants()->lockForUpdate()->findOrFail($participantId);
        abort_unless($participant->status === EventParticipant::STATUS_ACTIVE, 422, 'Peserta sudah dibatalkan.');
        $assignment = $event->teacherAssignments()->whereKey($participant->active_teacher_assignment_id)
            ->lockForUpdate()->first();
        abort_unless($assignment, 422, 'Peserta harus memiliki assignment aktif pada event ini.');
        $participant->setRelation('activeTeacherAssignment', $assignment);

        return $participant;
    }

    private function audit(EventTeacherAssignment|EventParticipant $record, User $actor, array $old, ?string $reason = null): void
    {
        AuditLog::create([
            'user_id' => $actor->id, 'user_name' => $actor->name,
            'action' => AuditLog::ACTION_UPDATE,
            'entity_type' => class_basename($record), 'entity_id' => $record->id,
            'entity_label' => "Event #{$record->event_id}",
            'old_values' => $old,
            'new_values' => $record->toArray() + ['reason' => $reason],
            'ip_address' => request()->ip(),
        ]);
    }
}
