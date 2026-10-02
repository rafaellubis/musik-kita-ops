<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventTeacherAssignment;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventParticipantService
{
    /** Tambahkan satu atau beberapa enrollment aktif milik murid ke event DRAFT. */
    public function addEnrollments(
        Event $event,
        Student $student,
        array $enrollmentIds,
        User $actor,
    ): Collection {
        return DB::transaction(function () use ($event, $student, $enrollmentIds, $actor) {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            abort_unless($event->isDraft(), 422, 'Peserta hanya dapat diubah saat event masih DRAFT.');

            $ids = collect($enrollmentIds)->map(fn ($id) => (int) $id)->unique()->values();
            if ($ids->isEmpty()) {
                $ids = Enrollment::query()
                    ->where('student_id', $student->id)
                    ->active()
                    ->pluck('id');

                if ($ids->count() !== 1) {
                    throw ValidationException::withMessages([
                        'enrollment_ids' => 'Pilih minimal satu enrollment aktif milik murid.',
                    ]);
                }
            }

            $enrollments = Enrollment::with(['package.instrument', 'teacher'])
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            if ($enrollments->count() !== $ids->count()
                || $enrollments->contains(fn (Enrollment $item) =>
                    $item->student_id !== $student->id || $item->status !== Enrollment::STATUS_ACTIVE
                )) {
                throw ValidationException::withMessages([
                    'enrollment_ids' => 'Enrollment harus aktif dan dimiliki oleh murid yang dipilih.',
                ]);
            }

            if ($enrollments->contains(fn (Enrollment $item) => ! $item->package?->instrument)) {
                throw ValidationException::withMessages([
                    'enrollment_ids' => 'Paket dan instrumen enrollment harus tersedia.',
                ]);
            }

            $existing = EventParticipant::query()
                ->where('event_id', $event->id)
                ->whereIn('enrollment_id', $ids)
                ->lockForUpdate()
                ->get()
                ->keyBy('enrollment_id');

            if ($existing->contains(fn (EventParticipant $item) => $item->status === EventParticipant::STATUS_ACTIVE)) {
                throw ValidationException::withMessages([
                    'enrollment_ids' => 'Enrollment yang dipilih sudah terdaftar pada event ini.',
                ]);
            }

            $participants = new Collection();
            foreach ($enrollments as $enrollment) {
                $assignment = EventTeacherAssignment::firstOrCreate([
                    'event_id' => $event->id,
                    'teacher_id' => $enrollment->teacher_id,
                ], [
                    'attendance_status' => EventTeacherAssignment::ATTENDANCE_PENDING,
                ]);
                $package = $enrollment->package;
                $participant = $existing->get($enrollment->id) ?? new EventParticipant([
                    'event_id' => $event->id,
                    'enrollment_id' => $enrollment->id,
                ]);
                $wasCancelled = $participant->exists;

                $participant->fill([
                    'student_id' => $student->id,
                    'status' => EventParticipant::STATUS_ACTIVE,
                    'cancelled_at' => null,
                    'cancelled_by' => null,
                    'cancellation_reason' => null,
                    'active_teacher_assignment_id' => $assignment->id,
                    'invoice_id' => null,
                    'invoice_item_id' => null,
                    'snapshot_package_id' => $package->id,
                    'snapshot_package_code' => $package->code,
                    'snapshot_instrument_name' => $package->instrument->name,
                    'snapshot_class_type' => $package->class_type,
                    'snapshot_grade' => $package->grade,
                    'snapshot_duration_min' => $package->duration_min,
                    'snapshot_package_price' => $package->price_per_month,
                    'snapshot_mc_fee' => 0,
                    'snapshot_honor_amount' => (int) round($package->price_per_month * 0.5 / 4),
                    'attendance_status' => EventParticipant::ATTENDANCE_PENDING,
                ]);
                $participant->save();

                $this->audit(
                    $wasCancelled ? AuditLog::ACTION_UPDATE : AuditLog::ACTION_CREATE,
                    $participant,
                    $actor,
                    $wasCancelled ? ['status' => EventParticipant::STATUS_CANCELLED] : null,
                    ['status' => EventParticipant::STATUS_ACTIVE, 'enrollment_id' => $enrollment->id],
                );
                $participants->push($participant);
            }

            return $participants;
        });
    }

    public function assignDraftTeacher(
        EventParticipant $participant,
        Teacher $teacher,
        User $actor,
    ): EventParticipant {
        return DB::transaction(function () use ($participant, $teacher, $actor) {
            $participant = EventParticipant::with('event')
                ->lockForUpdate()
                ->findOrFail($participant->id);
            abort_unless($participant->event->isDraft(), 422, 'Guru hanya dapat diubah saat event masih DRAFT.');
            abort_unless($teacher->is_active, 422, 'Guru pendamping harus masih aktif.');

            $oldTeacherId = $participant->activeTeacherAssignment?->teacher_id;
            $assignment = EventTeacherAssignment::firstOrCreate([
                'event_id' => $participant->event_id,
                'teacher_id' => $teacher->id,
            ], [
                'attendance_status' => EventTeacherAssignment::ATTENDANCE_PENDING,
            ]);
            $participant->update(['active_teacher_assignment_id' => $assignment->id]);

            $this->audit(
                AuditLog::ACTION_UPDATE,
                $participant,
                $actor,
                ['teacher_id' => $oldTeacherId],
                ['teacher_id' => $teacher->id],
            );

            return $participant->fresh('activeTeacherAssignment');
        });
    }

    public function cancelParticipant(
        EventParticipant $participant,
        User $actor,
        string $reason,
    ): EventParticipant {
        return DB::transaction(function () use ($participant, $actor, $reason) {
            $participant = EventParticipant::with('event')
                ->lockForUpdate()
                ->findOrFail($participant->id);
            abort_unless($participant->event->isDraft(), 422, 'Peserta hanya dapat dibatalkan saat event masih DRAFT.');
            abort_if($participant->status === EventParticipant::STATUS_CANCELLED, 422, 'Partisipasi sudah dibatalkan.');

            $participant->update([
                'status' => EventParticipant::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancellation_reason' => $reason,
            ]);

            $this->audit(
                AuditLog::ACTION_UPDATE,
                $participant,
                $actor,
                ['status' => EventParticipant::STATUS_ACTIVE],
                ['status' => EventParticipant::STATUS_CANCELLED, 'reason' => $reason],
            );

            return $participant;
        });
    }

    private function audit(
        string $action,
        EventParticipant $participant,
        User $actor,
        ?array $oldValues,
        array $newValues,
    ): void {
        AuditLog::create([
            'user_id' => $actor->id,
            'user_name' => $actor->name,
            'action' => $action,
            'entity_type' => class_basename($participant),
            'entity_id' => $participant->id,
            'entity_label' => "Event #{$participant->event_id} / Enrollment #{$participant->enrollment_id}",
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
        ]);
    }
}
