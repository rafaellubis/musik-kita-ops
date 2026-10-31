<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\EnrollmentPromotion;
use App\Models\ClassSession;
use App\Models\HonorSlip;
use App\Models\Package;
use App\Models\ProgressReportSessionNote;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Teacher;
use App\Models\User;
use App\Models\Student;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnrollmentPromotionService
{
    public function applyDue(?CarbonInterface $today = null): int
    {
        $date = ($today ?? today())->toDateString();
        $count = 0;
        foreach (EnrollmentPromotion::where('status', 'SCHEDULED')->whereDate('effective_date', '<=', $date)->orderBy('id')->get() as $candidate) {
            $count += DB::transaction(function () use ($candidate) {
                // Murid selalu dikunci lebih dulu, sama seperti transaksi pengajuan.
                $student = Student::lockForUpdate()->findOrFail($candidate->student_id);
                $promotion = EnrollmentPromotion::lockForUpdate()->findOrFail($candidate->id);
                if ($promotion->status !== 'SCHEDULED') {
                    return 0;
                }
                $source = Enrollment::lockForUpdate()->findOrFail($promotion->source_enrollment_id);
                $successor = Enrollment::lockForUpdate()->findOrFail($promotion->successor_enrollment_id);
                $primary = $student->primary_enrollment_id === $source->id;
                $source->update(['status' => 'COMPLETED', 'is_primary' => false]);
                $source->schedules()->update(['is_active' => false]);
                $successor->update(['status' => 'ACTIVE', 'is_primary' => $primary]);
                if ($primary) {
                    $student->update(['primary_enrollment_id' => $successor->id]);
                }
                $promotion->update(['status' => 'APPLIED', 'applied_at' => now()]);
                AuditLog::record('UPDATE', $promotion, 'Kenaikan level mulai berlaku', newValues: [
                    'source_enrollment_id' => $source->id, 'successor_enrollment_id' => $successor->id,
                ]);
                return 1;
            });
        }
        return $count;
    }

    public function preview(Enrollment $source, array $data): array
    {
        $source->load('student', 'package.instrument', 'teacher', 'schedules.room');
        $effective = today()->startOfMonth()->addMonth();
        $this->require($data['effective_date'] === $effective->toDateString(), 'effective_date', 'Tanggal berlaku sudah berubah. Buka ulang pratinjau kenaikan.');
        $this->require($source->status === 'ACTIVE' && $source->student->status === 'Aktif', 'enrollment_id', 'Hanya kelas aktif milik murid Aktif yang dapat naik level.');
        $this->assertNotScheduled($source);
        $this->require(!$source->end_date, 'enrollment_id', 'Kelas sudah memiliki tanggal akhir.');
        $target = Package::with('instrument')->findOrFail($data['package_id']);
        $grades = ['BASIC', 'L1', 'L2', 'L3', 'L4'];
        $index = array_search($source->package->grade, $grades, true);
        $this->require($source->package->class_type === 'REGULER' && $index !== false
            && isset($grades[$index + 1]) && $target->grade === $grades[$index + 1]
            && $target->class_type === 'REGULER' && $target->instrument_id === $source->package->instrument_id
            && $target->is_active, 'package_id', 'Pilih paket aktif satu tingkat berikutnya pada instrumen yang sama.');
        $this->require($target->price_per_month >= $source->package->price_per_month, 'package_id', 'Harga level tujuan lebih rendah. Periksa master paket terlebih dahulu.');
        $teacher = Teacher::findOrFail($data['teacher_id']);
        $room = Room::findOrFail($data['room_id']);
        $this->require($teacher->is_active && $teacher->instruments()->where('instruments.id', $target->instrument_id)->exists(), 'teacher_id', 'Guru tidak aktif atau tidak mengajar instrumen kelas ini.');
        $this->require($room->is_active && $room->supportsInstrument($target->instrument->name), 'room_id', 'Ruangan tidak aktif atau tidak mendukung instrumen kelas ini.');
        $end = Carbon::createFromFormat('!H:i', $data['start_time'])->addMinutes($target->duration_min);
        $this->require($end->format('H:i') > $data['start_time'], 'start_time', 'Sesi harus selesai pada hari yang sama.');
        $this->require(!$source->leaves()->whereDate('cuti_until', '>=', $effective)->exists(), 'enrollment_id', 'Periksa cuti kelas yang melintasi tanggal kenaikan terlebih dahulu.');

        $sessions = $source->classSessions()->with('originSession', 'teacherNote')->whereDate('session_date', '>=', $effective)->get();
        $remove = collect();
        $preserve = collect();
        foreach ($sessions as $session) {
            $origin = $session->originSession;
            // Hak sesi periode lama tidak ikut diganti, termasuk rapel tanpa origin.
            $oldRight = sprintf('%04d-%02d', $session->attribution_year, $session->attribution_month) < $effective->format('Y-m');
            if ($oldRight || ($origin && $origin->session_date < $effective->toDateString())) {
                $preserve->push($session);
                continue;
            }
            $holidayChild = $origin && $origin->status === 'LIBUR';
            $autoNote = ($session->status === 'LIBUR' && str_starts_with($session->notes ?? '', 'Auto-set LIBUR: '))
                || ($holidayChild && $session->notes === 'Sesi pengganti dari tanggal libur');
            $safe = $session->session_type === ClassSession::TYPE_REGULAR
                && in_array($session->status, ['SCHEDULED', 'LIBUR'], true)
                && (!$origin || $holidayChild) && !$session->split_part && !$session->substitute_teacher_id
                && (!$session->notes || $autoNote) && !$session->teacherNote
                && !ProgressReportSessionNote::where('class_session_id', $session->id)->exists();
            $paid = HonorSlip::where('teacher_id', $session->honored_teacher_id)
                ->where('year', Carbon::parse($session->session_date)->year)
                ->where('month', Carbon::parse($session->session_date)->month)->where('status', 'PAID')->exists();
            $this->require($safe && !$paid, 'sessions', "Sesi {$session->session_date} memiliki catatan, absensi, sesi manual, atau honor dibayar. Periksa sebelum kenaikan.");
            $remove->push($session);
        }
        $months = $remove->map(fn ($s) => Carbon::parse($s->session_date)->startOfMonth()->toDateString())
            ->push($effective->toDateString())->unique()->sort()->values();
        $this->assertSlotAvailable($source, $data, $end->format('H:i:s'), $effective, $remove->pluck('id')->all());
        $oldPrice = (int) $source->package->price_per_month;
        return [
            'effective_date' => $effective->toDateString(), 'target_package' => $target->only('id', 'code', 'grade'),
            'old_price' => $oldPrice, 'new_price' => (int) $target->price_per_month,
            'old_honor' => (int) round($oldPrice / 8), 'new_honor' => (int) round($target->price_per_month / 8),
            'end_time' => $end->format('H:i:s'), 'months' => $months->all(),
            'sessions_to_delete' => $remove->map->getAttributes()->values()->all(),
            'preserved_sessions' => $preserve->map->getAttributes()->values()->all(),
            'invoice_changes' => app(InvoiceService::class)->previewPromotion($source, $target, $effective),
            'schedule' => ['teacher' => $teacher->name, 'room' => $room->name, 'day_of_week' => (int) $data['day_of_week'], 'start_time' => $data['start_time']],
        ];
    }

    public function schedule(Enrollment $source, array $data, User $actor): EnrollmentPromotion
    {
        abort_unless($actor->hasAnyRole(['Owner', 'Admin']), 403);
        return DB::transaction(function () use ($source, $data, $actor) {
            Student::whereKey($source->student_id)->lockForUpdate()->firstOrFail();
            // Kunci sumber daya yang sama untuk dua pengajuan pada slot yang sama.
            Teacher::whereIn('id', [$source->teacher_id, $data['teacher_id']])->orderBy('id')->lockForUpdate()->get();
            Room::whereKey($data['room_id'])->lockForUpdate()->firstOrFail();
            $source = Enrollment::lockForUpdate()->findOrFail($source->id);
            $source->classSessions()->lockForUpdate()->get();
            \App\Models\Invoice::where('enrollment_id', $source->id)->lockForUpdate()->get();
            $preview = $this->preview($source, $data);
            $successor = Enrollment::create([
                'student_id' => $source->student_id, 'package_id' => $data['package_id'], 'teacher_id' => $data['teacher_id'],
                'effective_date' => $preview['effective_date'], 'status' => 'INACTIVE', 'is_primary' => false,
            ]);
            $newSchedule = $successor->schedules()->create([
                'room_id' => $data['room_id'], 'day_of_week' => $data['day_of_week'],
                'start_time' => $data['start_time'].':00', 'end_time' => $preview['end_time'], 'is_active' => true,
            ]);
            $source->update(['end_date' => $preview['effective_date']]);
            $promotion = EnrollmentPromotion::create([
                'student_id' => $source->student_id, 'source_enrollment_id' => $source->id, 'successor_enrollment_id' => $successor->id,
                'effective_date' => $preview['effective_date'], 'evaluation_note' => $data['evaluation_note'],
                'created_by' => $actor->id, 'status' => 'SCHEDULED', 'invoice_adjustments' => [],
                'snapshot' => $preview + [
                    'source_package' => $source->package->only('id', 'code', 'grade', 'price_per_month'),
                    'source_teacher' => $source->teacher->only('id', 'name'),
                    'source_schedules' => $source->schedules->toArray(), 'new_schedule' => $newSchedule->toArray(),
                ],
            ]);
            // Hapus anak sebelum origin agar foreign key tidak menghilangkan kaitan audit.
            $ids = collect($preview['sessions_to_delete'])->pluck('id');
            ClassSession::whereIn('id', $ids)->whereNotNull('origin_session_id')->delete();
            ClassSession::whereIn('id', $ids)->delete();
            foreach ($preview['months'] as $month) {
                $date = Carbon::parse($month);
                $report = app(SessionGeneratorService::class)->generateForEnrollment($successor, $date->year, $date->month);
                $this->require($report['skipped_conflict'] === 0, 'sessions', 'Jadwal baru bentrok dengan sesi konkret. Tidak ada perubahan yang disimpan.');
            }
            $created = $successor->classSessions()->get()->map->getAttributes()->all();
            $promotion->update(['snapshot' => $promotion->snapshot + ['sessions_created' => $created]]);
            $promotion->update(['invoice_adjustments' => app(InvoiceService::class)->adjustForPromotion($promotion)]);
            AuditLog::record('CREATE', $promotion, 'Kenaikan level terjadwal', newValues: $promotion->toArray());
            return $promotion->fresh('successor');
        });
    }

    private function assertSlotAvailable(Enrollment $source, array $data, string $end, Carbon $effective, array $removed): void
    {
        app(ScheduleConflictDetector::class)->assertAssignmentAvailable($source, [
            'teacher_id' => (int) $data['teacher_id'], 'day_of_week' => (int) $data['day_of_week'],
            'start_time' => $data['start_time'].':00', 'end_time' => $end, 'room_id' => (int) $data['room_id'],
        ], $effective);
        $schedules = Schedule::active()->where('enrollment_id', '!=', $source->id)
            ->where('day_of_week', $data['day_of_week'])->whereTime('start_time', '<', $end)
            ->whereTime('end_time', '>', $data['start_time'].':00')
            ->whereHas('enrollment', fn ($q) => $q->forPeriod($effective, Carbon::parse('9999-12-31')))
            ->with('enrollment')->get();
        foreach ($schedules as $schedule) {
            $this->require($schedule->enrollment->teacher_id !== (int) $data['teacher_id']
                && $schedule->room_id !== (int) $data['room_id'] && $schedule->enrollment->student_id !== $source->student_id,
                'start_time', 'Guru, ruang, atau murid sudah memiliki jadwal pada periode dan jam tersebut.');
        }
        // Periksa juga rapel, sesi manual, dan guru pengganti yang tidak mengikuti jadwal mingguan.
        $concrete = ClassSession::whereDate('session_date', '>=', $effective)->whereNotIn('id', $removed)
            ->whereNotIn('status', ClassSession::statusesExcludedFromScheduleConflict())
            ->whereTime('start_time', '<', $end)->whereTime('end_time', '>', $data['start_time'].':00')
            ->where(fn ($q) => $q->where('teacher_id', $data['teacher_id'])->orWhere('substitute_teacher_id', $data['teacher_id'])
                ->orWhere('room_id', $data['room_id'])->orWhere('student_id', $source->student_id))->get();
        foreach ($concrete as $session) {
            $this->require(Carbon::parse($session->session_date)->dayOfWeek !== (int) $data['day_of_week'], 'start_time', "Jadwal bentrok dengan sesi {$session->session_date}.");
        }
    }

    private function require(bool $condition, string $field, string $message): void
    {
        if (!$condition) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    public function assertRescheduleAllowed(ClassSession $original, Enrollment $enrollment): void
    {
        $pending = $enrollment->promotedFrom;
        $sourcePromotion = $enrollment->promotion;
        $period = sprintf('%04d-%02d', $original->attributionYear(), $original->attributionMonth());
        $this->require($pending?->status !== 'SCHEDULED'
            && (!$sourcePromotion || $period < $sourcePromotion->effective_date->format('Y-m')),
            'enrollment_id', 'Reschedule hanya dapat memakai hak periode kelas yang berlaku; kenaikan level terjadwal tidak boleh diubah.');
    }

    public function assertNotScheduled(Enrollment $enrollment): void
    {
        app(EnrollmentTransferService::class)->assertNotScheduled($enrollment);
        if (EnrollmentPromotion::where('status', 'SCHEDULED')->where(fn ($q) => $q
            ->where('source_enrollment_id', $enrollment->id)->orWhere('successor_enrollment_id', $enrollment->id))->exists()) {
            throw ValidationException::withMessages(['enrollment_id' => 'Kelas memiliki kenaikan level terjadwal. Perubahan kelas belum dapat dilakukan.']);
        }
    }
}
