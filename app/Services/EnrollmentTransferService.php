<?php

namespace App\Services;

use App\Models\{AuditLog, ClassSession, Enrollment, EnrollmentTransfer, HonorSlip, ProgressReport, ProgressReportSessionNote, Room, Schedule, Student, Teacher, User};
use Carbon\{Carbon, CarbonInterface};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnrollmentTransferService
{
    public function assertNotScheduled(Enrollment $enrollment): void
    {
        $this->require(!$enrollment->transfers()->where('status', 'SCHEDULED')->exists(), 'enrollment_id',
            'Kelas memiliki pengalihan guru terjadwal. Selesaikan pengalihan sebelum mengubah kelas.');
    }

    public function preview(Enrollment $enrollment, array $data): array
    {
        $enrollment->load('student', 'package.instrument', 'schedules', 'transfers');
        $this->require($enrollment->status === 'ACTIVE' && $enrollment->student->status === 'Aktif', 'enrollment_id', 'Hanya kelas aktif milik murid Aktif yang dapat dialihkan.');
        $this->assertNotScheduled($enrollment);
        app(EnrollmentPromotionService::class)->assertNotScheduled($enrollment);
        $effective = Carbon::parse($data['effective_date'])->startOfDay();
        $this->require($effective->gte(today()) && (!$enrollment->end_date || $effective->lt($enrollment->end_date)), 'effective_date', 'Tanggal berlaku tidak boleh lampau atau melewati akhir kelas.');
        $this->require(!$enrollment->transfers()->whereDate('effective_date', '>=', $effective)->exists(), 'effective_date', 'Tanggal berlaku harus setelah pengalihan sebelumnya.');
        $schedules = $enrollment->schedules->where('is_active', true);
        $this->require($schedules->count() === 1, 'enrollment_id', 'Kelas harus memiliki tepat satu jadwal aktif sebelum dialihkan.');
        $teacher = Teacher::findOrFail($data['teacher_id']);
        $this->require($teacher->id !== $enrollment->teacher_id && $teacher->is_active
            && $teacher->instruments()->where('instruments.id', $enrollment->package->instrument_id)->exists(), 'teacher_id', 'Pilih guru aktif yang berbeda dan mengajar instrumen kelas ini.');
        $room = !empty($data['room_id']) ? Room::findOrFail($data['room_id']) : null;
        $this->require(!$room || ($room->is_active && $room->supportsInstrument($enrollment->package->instrument->name)), 'room_id', 'Ruangan tidak aktif atau tidak mendukung instrumen kelas.');
        $end = Carbon::createFromFormat('!H:i', $data['start_time'])->addMinutes($enrollment->package->duration_min);
        $this->require($end->format('H:i') > $data['start_time'], 'start_time', 'Sesi harus selesai pada hari yang sama.');
        $this->require(filled(trim($data['reason'] ?? '')), 'reason', 'Alasan pengalihan wajib diisi.');
        $before = $enrollment->teachingAssignmentOn($effective);
        $after = ['teacher_id' => $teacher->id, 'day_of_week' => (int) $data['day_of_week'],
            'start_time' => $data['start_time'].':00', 'end_time' => $end->format('H:i:s'), 'room_id' => $room?->id];
        $remove = collect();
        $preserve = collect();
        $sessions = $enrollment->classSessions()->with('originSession', 'teacherNote')->whereDate('session_date', '>=', $effective)->orderBy('id')->get();
        foreach ($sessions as $session) {
            $oldRight = sprintf('%04d-%02d', $session->attributionYear(), $session->attributionMonth()) < $effective->format('Y-m');
            $origin = $session->originSession;
            if ($oldRight || ($origin && $origin->session_date < $effective->toDateString())) {
                $preserve->push($session);
                continue;
            }
            $autoNote = ($session->status === 'LIBUR' && str_starts_with($session->notes ?? '', 'Auto-set LIBUR: '))
                || ($origin?->status === 'LIBUR' && $session->notes === 'Sesi pengganti dari tanggal libur');
            $safe = $session->schedule_id === $schedules->first()->id && $session->session_type === ClassSession::TYPE_REGULAR
                && in_array($session->status, ['SCHEDULED', 'LIBUR'], true) && (!$origin || $origin->status === 'LIBUR')
                && !$session->split_part && !$session->substitute_teacher_id && (!$session->notes || $autoNote)
                && !$session->teacherNote && !ProgressReportSessionNote::where('class_session_id', $session->id)->exists();
            // Sesi khusus dipertahankan; generator akan memeriksa bentroknya dengan slot baru.
            if (!$safe) {
                $preserve->push($session);
                continue;
            }
            $this->require(Carbon::parse($session->session_date.' '.$session->start_time)->gt(now()), 'sessions', "Sesi {$session->session_date} sudah berlangsung. Pilih tanggal berlaku berikutnya.");
            $paid = HonorSlip::where('teacher_id', $session->honored_teacher_id)->where('year', Carbon::parse($session->session_date)->year)
                ->where('month', Carbon::parse($session->session_date)->month)->where('status', 'PAID')->exists();
            $this->require(!$paid || !$session->honor_amount, 'sessions', "Honor sesi {$session->session_date} sudah dibayar. Selesaikan koreksi honor terlebih dahulu.");
            $remove->push($session);
        }
        foreach ($preserve as $session) {
            $this->require(!$session->origin_session_id || !$remove->contains('id', $session->origin_session_id),
                'sessions', 'Sesi pengganti libur memiliki catatan atau penyesuaian. Periksa pasangan sesi libur sebelum pengalihan.');
        }
        $months = $remove->map(fn ($s) => Carbon::parse($s->session_date)->startOfMonth()->toDateString())
            ->push($effective->copy()->startOfMonth()->toDateString())->unique()->sort()->values()->all();
        $this->assertSlotAvailable($enrollment, $after, $effective, $remove->pluck('id')->all());
        $payload = ['before' => $before, 'after' => $after, 'effective_date' => $effective->toDateString(),
            'affected_sessions' => $remove->map->getAttributes()->all(), 'preserved_sessions' => $preserve->map->getAttributes()->all(),
            'months' => $months, 'reason' => trim($data['reason']), 'teacher_name' => $teacher->name, 'room_name' => $room?->name ?? 'Tanpa ruangan'];
        $payload['fingerprint'] = hash('sha256', json_encode($payload));
        return $payload;
    }

    public function schedule(Enrollment $enrollment, array $data, User $actor): EnrollmentTransfer
    {
        abort_unless($actor->hasAnyRole(['Owner', 'Admin']), 403);
        return DB::transaction(function () use ($enrollment, $data, $actor) {
            Student::whereKey($enrollment->student_id)->lockForUpdate()->firstOrFail();
            Teacher::whereIn('id', [$enrollment->teacher_id, $data['teacher_id']])->orderBy('id')->lockForUpdate()->get();
            Room::whereKey($data['room_id'] ?? null)->lockForUpdate()->get();
            $enrollment = Enrollment::lockForUpdate()->findOrFail($enrollment->id);
            $enrollment->classSessions()->lockForUpdate()->get();
            HonorSlip::whereIn('teacher_id', [$enrollment->teacher_id, $data['teacher_id']])->orderBy('id')->lockForUpdate()->get();
            $preview = $this->preview($enrollment, $data);
            $this->require(!isset($data['fingerprint']) || hash_equals($preview['fingerprint'], $data['fingerprint']), 'fingerprint', 'Data berubah sejak pratinjau. Periksa pratinjau kembali.');
            $paidSnapshots = [];
            foreach (HonorSlip::whereIn('teacher_id', [$enrollment->teacher_id, $data['teacher_id']])->where('status', 'PAID')->get() as $slip) {
                $paidSnapshots[$slip->id] = ClassSession::honoredForTeacher($slip->teacher_id)->honoredStatus()
                    ->whereYear('session_date', $slip->year)->whereMonth('session_date', $slip->month)
                    ->where('honor_amount', '>', 0)->orderBy('id')->pluck('honor_amount', 'id')->all();
            }
            $transfer = EnrollmentTransfer::create(['enrollment_id' => $enrollment->id,
                'source_teacher_id' => $enrollment->teacher_id, 'target_teacher_id' => $data['teacher_id'],
                'effective_date' => $preview['effective_date'], 'before' => $preview['before'], 'after' => $preview['after'],
                'reason' => $preview['reason'], 'created_by' => $actor->id, 'status' => 'SCHEDULED']);
            $ids = collect($preview['affected_sessions'])->pluck('id');
            ClassSession::whereIn('id', $ids)->whereNotNull('origin_session_id')->delete();
            ClassSession::whereIn('id', $ids)->delete();
            $enrollment->unsetRelation('transfers');
            foreach ($preview['months'] as $month) {
                $date = Carbon::parse($month);
                $result = app(SessionGeneratorService::class)->generateForEnrollment($enrollment, $date->year, $date->month);
                $this->require($result['skipped_conflict'] === 0, 'sessions', 'Slot baru bentrok dengan sesi konkret. Tidak ada perubahan disimpan.');
            }
            foreach ($paidSnapshots as $slipId => $snapshot) {
                $slip = HonorSlip::findOrFail($slipId);
                $current = ClassSession::honoredForTeacher($slip->teacher_id)->honoredStatus()
                    ->whereYear('session_date', $slip->year)->whereMonth('session_date', $slip->month)
                    ->where('honor_amount', '>', 0)->orderBy('id')->pluck('honor_amount', 'id')->all();
                $this->require($snapshot === $current, 'sessions', 'Pengalihan mengubah rincian honor yang sudah dibayar, termasuk guru tujuan. Selesaikan koreksi honor terlebih dahulu.');
            }
            foreach (HonorSlip::whereIn('teacher_id', [$transfer->source_teacher_id, $transfer->target_teacher_id])->where('status', '!=', 'PAID')->get() as $slip) {
                if (in_array(sprintf('%04d-%02d-01', $slip->year, $slip->month), $preview['months'], true)) {
                    app(HonorCalculationService::class)->calculateForTeacher(Teacher::findOrFail($slip->teacher_id), $slip->year, $slip->month, $actor->id);
                }
            }
            AuditLog::record('CREATE', $transfer, 'Alih guru kelas terjadwal', newValues: $preview);
            $this->applyDue();
            return $transfer->fresh();
        });
    }

    public function applyDue(?CarbonInterface $date = null): int
    {
        $count = 0;
        foreach (EnrollmentTransfer::where('status', 'SCHEDULED')->whereDate('effective_date', '<=', $date ?? today())->orderBy('id')->get() as $candidate) {
            $count += DB::transaction(function () use ($candidate) {
                Student::whereKey($candidate->enrollment->student_id)->lockForUpdate()->firstOrFail();
                $transfer = EnrollmentTransfer::lockForUpdate()->findOrFail($candidate->id);
                if ($transfer->status !== 'SCHEDULED') { return 0; }
                $enrollment = Enrollment::lockForUpdate()->findOrFail($transfer->enrollment_id);
                Teacher::whereIn('id', [$transfer->source_teacher_id, $transfer->target_teacher_id])->orderBy('id')->lockForUpdate()->get();
                $teacher = Teacher::findOrFail($transfer->target_teacher_id);
                $room = !empty($transfer->after['room_id']) ? Room::lockForUpdate()->find($transfer->after['room_id']) : null;
                try {
                    $this->require(in_array($enrollment->status, ['ACTIVE', 'ON_LEAVE'], true) && $teacher->is_active
                        && $teacher->instruments()->where('instruments.id', $enrollment->package->instrument_id)->exists(),
                        'teacher_id', 'Guru tujuan tidak aktif atau kelas tidak lagi dapat dialihkan.');
                    $this->require(empty($transfer->after['room_id']) || ($room && $room->is_active && $room->supportsInstrument($enrollment->package->instrument->name)),
                        'room_id', 'Ruangan tujuan tidak lagi tersedia untuk instrumen kelas.');
                    $prepared = $enrollment->classSessions()->whereDate('session_date', '>=', $transfer->effective_date)
                        ->where('teacher_id', $transfer->target_teacher_id)->pluck('id')->all();
                    $this->assertSlotAvailable($enrollment, $transfer->after, $transfer->effective_date, $prepared);
                } catch (ValidationException $error) {
                    $transfer->update(['blocked_reason' => implode(' ', \Illuminate\Support\Arr::flatten($error->errors()))]);
                    return 0;
                }
                $enrollment->update(['teacher_id' => $transfer->target_teacher_id]);
                $assignment = $transfer->after;
                unset($assignment['teacher_id']);
                $enrollment->schedules()->where('is_active', true)->update($assignment);
                foreach ($enrollment->progressReports()->whereRaw('(year * 100 + month) >= ?', [(int) $transfer->effective_date->format('Ym')])->lockForUpdate()->get() as $report) {
                    $old = $report->teacher_id;
                    $report->update(['teacher_id' => $transfer->target_teacher_id]);
                    AuditLog::record('UPDATE', $report, 'Penanggung jawab laporan dialihkan', ['teacher_id' => $old], ['teacher_id' => $transfer->target_teacher_id]);
                }
                $transfer->update(['status' => 'APPLIED', 'applied_at' => now(), 'blocked_reason' => null]);
                AuditLog::record('UPDATE', $transfer, 'Alih guru kelas mulai berlaku', $transfer->before, $transfer->after);
                return 1;
            });
        }
        return $count;
    }

    /** Slot rutin diperiksa pada setiap batas periode, bukan hanya sesi yang sudah dibuat. */
    private function assertSlotAvailable(Enrollment $source, array $after, Carbon $effective, array $removed): void
    {
        $detector = app(ScheduleConflictDetector::class);
        $detector->assertAssignmentAvailable($source, $after, $effective);
        $dates = ClassSession::whereDate('session_date', '>=', $effective)->whereNotIn('id', $removed)
            ->select('session_date')->distinct()->pluck('session_date');
        foreach ($dates as $date) {
            if (Carbon::parse($date)->dayOfWeek !== $after['day_of_week']) { continue; }
            $conflict = $detector->findConcreteConflict($source, $after, $date, $removed);
            $this->require(!$conflict, 'start_time', "Slot baru bentrok dengan sesi {$date}.");
        }
    }

    private function require(bool $condition, string $field, string $message): void
    {
        if (!$condition) { throw ValidationException::withMessages([$field => $message]); }
    }
}
