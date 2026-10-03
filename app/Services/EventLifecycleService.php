<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventTeacherAssignment;
use App\Models\HonorSlip;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EventLifecycleService
{
    public function __construct(private EventInvoiceService $invoiceService) {}

    public function publish(Event $event, User $actor): Event
    {
        return DB::transaction(function () use ($event, $actor) {
            // Event menjadi pengunci bersama agar dua publikasi tidak membuat invoice ganda.
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            abort_unless($event->isDraft() || $event->isPublished(), 422, 'Hanya event DRAFT yang dapat dipublikasikan.');
            abort_unless($event->type === Event::TYPE_MINI_CONCERT, 422, 'Publikasi hanya tersedia untuk Mini Concert.');
            abort_unless($event->event_date && $event->payment_due_date
                && $event->payment_due_date->lte($event->event_date), 422, 'Batas pembayaran wajib diisi dan tidak boleh melewati tanggal event.');

            $participants = $event->participants()->orderBy('id')->lockForUpdate()->get();
            $active = $participants->where('status', EventParticipant::STATUS_ACTIVE);
            abort_if($active->isEmpty(), 422, 'Tambahkan minimal satu peserta aktif sebelum publikasi.');
            $assignments = EventTeacherAssignment::whereIn('id', $active->pluck('active_teacher_assignment_id'))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $teachers = Teacher::whereIn('id', $assignments->pluck('teacher_id'))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $enrollments = Enrollment::whereIn('id', $active->pluck('enrollment_id'))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            foreach ($active as $participant) {
                $assignment = $assignments->get($participant->active_teacher_assignment_id);
                $enrollment = $enrollments->get($participant->enrollment_id);
                abort_unless($assignment && $assignment->event_id === $event->id
                    && $teachers->has($assignment->teacher_id), 422, 'Setiap peserta harus memiliki assignment guru pada event ini.');
                abort_unless($enrollment && $enrollment->student_id === $participant->student_id,
                    422, 'Enrollment peserta tidak sesuai dengan murid.');
                abort_unless($participant->snapshot_package_id
                    && filled($participant->snapshot_package_code)
                    && filled($participant->snapshot_instrument_name)
                    && in_array($participant->snapshot_class_type, ['REGULER', 'HOBBY', 'KIDS_CLASS', 'KIDS_CLASS_BUNDLE'], true)
                    && ($participant->snapshot_class_type !== 'REGULER' || filled($participant->snapshot_grade))
                    && $participant->snapshot_duration_min > 0
                    && $participant->snapshot_package_price > 0
                    && $participant->snapshot_honor_amount === (int) round($participant->snapshot_package_price * 0.5 / 4),
                    422, 'Snapshot paket dan honor peserta belum valid.');

                // Retry memeriksa hasil publikasi; perubahan master setelah publikasi tidak menulis ulang snapshot.
                if ($event->isDraft()) {
                    abort_unless($enrollment->status === Enrollment::STATUS_ACTIVE, 422, 'Seluruh enrollment peserta harus masih aktif.');
                    abort_unless($teachers->get($assignment->teacher_id)->is_active, 422, 'Guru pendamping harus masih aktif.');
                }
            }

            if ($event->isDraft()) {
                $slips = HonorSlip::whereIn('teacher_id', $assignments->pluck('teacher_id'))
                    ->forMonth($event->event_date->year, $event->event_date->month)
                    ->orderBy('id')->lockForUpdate()->get();
                abort_if($slips->contains('status', HonorSlip::STATUS_PAID), 422, 'Slip honor guru pendamping pada bulan event sudah dibayar.');
            } else {
                abort_unless($event->published_at && $event->published_by, 422, 'Data publikasi event tidak lengkap.');
            }

            $this->invoiceService->publishInvoices($event, $actor);
            if ($event->isPublished()) {
                return $event;
            }

            $event->update([
                'status' => Event::STATUS_PUBLISHED,
                'published_at' => now(),
                'published_by' => $actor->id,
            ]);
            AuditLog::create([
                'user_id' => $actor->id, 'user_name' => $actor->name,
                'action' => AuditLog::ACTION_UPDATE,
                'entity_type' => 'Event', 'entity_id' => $event->id, 'entity_label' => $event->name,
                'old_values' => ['status' => Event::STATUS_DRAFT],
                'new_values' => ['status' => Event::STATUS_PUBLISHED, 'published_by' => $actor->id],
                'ip_address' => request()->ip(),
            ]);

            return $event;
        });
    }
}
