<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Room;
use App\Models\Schedule;
use Illuminate\Support\Collection;

/**
 * Cek konflik jadwal mingguan tetap (M03).
 *
 * Aturan:
 *   1. Konflik guru: 1 guru tidak boleh punya 2 schedule privat (REGULER/HOBBY)
 *      di hari + jam yang overlap. Pengecualian:
 *      - KIDS_CLASS / KIDS_CLASS_BUNDLE boleh overlap antar-Kids (kelas grup)
 *      - DUO punya aturan khusus (maks 2 pasangan DUO) — di caller
 *
 *   2. Konflik ruang: 1 ruang tidak boleh punya N schedule overlap kalau
 *      N >= room.capacity. Untuk ruang biasa (capacity=1), 2 schedule
 *      overlap = konflik. Untuk ruang Kids Class (capacity=4), boleh
 *      sampai 4 enrollment overlap di slot yang sama.
 *
 * Cara pakai:
 *   $detector = app(ScheduleConflictDetector::class);
 *   $teacherClashes = $detector->findTeacherConflicts(
 *       teacherId: 5,
 *       dayOfWeek: 1,           // Senin
 *       startTime: '15:00',
 *       endTime: '15:30',
 *       excludeScheduleId: null,
 *   );
 *   if ($teacherClashes->isNotEmpty()) { ... }
 */
class ScheduleConflictDetector
{
    public function findStudentConflicts(int $studentId, int $dayOfWeek, string $startTime, string $endTime, ?int $excludeScheduleId = null): Collection
    {
        $startTime = $this->normalizeTime($startTime);
        $endTime = $this->normalizeTime($endTime);
        return $this->assignmentsFrom(today())->filter(fn ($s) =>
            $s->enrollment->student_id === $studentId && $s->day_of_week === $dayOfWeek
            && $s->start_time < $endTime && $s->end_time > $startTime
            && (!$excludeScheduleId || $s->id !== $excludeScheduleId))->unique('id')->values();
    }

    /** Konkrit dan rutin memakai aturan berbagi slot yang sama. */
    public function findConcreteConflict(Enrollment $enrollment, array $assignment, string $date, array $excluded = []): ?\App\Models\ClassSession
    {
        $sessions = \App\Models\ClassSession::whereDate('session_date', $date)->whereNotIn('id', $excluded)
            ->whereNotIn('status', \App\Models\ClassSession::statusesExcludedFromScheduleConflict())
            ->where('start_time', '<', $assignment['end_time'])->where('end_time', '>', $assignment['start_time'])
            ->with('enrollment.package')->get();
        $student = $sessions->firstWhere('student_id', $enrollment->student_id);
        if ($student) { return $student; }
        $teachers = $sessions->filter(fn ($s) => in_array($assignment['teacher_id'], [$s->teacher_id, $s->substitute_teacher_id], true));
        $rooms = !empty($assignment['room_id']) ? $sessions->where('room_id', $assignment['room_id']) : collect();
        $type = $enrollment->package->class_type;
        if ($this->isKidsClassType($type)) {
            $blocked = $teachers->first(fn ($s) => !$this->isKidsClassType($s->enrollment?->package?->class_type));
        } elseif ($type === 'DUO') {
            $blocked = $teachers->first(fn ($s) => $s->enrollment?->package?->class_type !== 'DUO')
                ?? ($teachers->count() >= 2 ? $teachers->first() : null);
        } else {
            $blocked = $teachers->first();
        }
        if ($blocked) { return $blocked; }
        if ($type === 'DUO') {
            return $rooms->first(fn ($s) => $s->enrollment?->package?->class_type !== 'DUO')
                ?? ($rooms->count() >= 2 ? $rooms->first() : null);
        }
        $capacity = !empty($assignment['room_id']) ? Room::findOrFail($assignment['room_id'])->capacity : 1;
        return $rooms->count() >= $capacity ? $rooms->first() : null;
    }
    /** Variasi penugasan yang masih memesan slot pada periode yang diperiksa. */
    public function assignmentsFrom(\Carbon\CarbonInterface $from): Collection
    {
        $rows = collect();
        $schedules = Schedule::active()->whereHas('enrollment', fn ($q) =>
            $q->forPeriod($from, \Carbon\Carbon::parse('9999-12-31')))
            ->with('enrollment.transfers', 'enrollment.schedules', 'enrollment.student', 'enrollment.package')->get();
        foreach ($schedules as $schedule) {
            $enrollment = $schedule->enrollment;
            $start = $enrollment->effective_date && $enrollment->effective_date->gt($from) ? $enrollment->effective_date : $from;
            $boundaries = collect([$start->toDateString()])
                ->merge($enrollment->transfers->filter(fn ($t) => $t->effective_date->gt($start))->map(fn ($t) => $t->effective_date->toDateString()))
                ->unique()->sort()->values();
            foreach ($boundaries as $index => $boundary) {
                if ($enrollment->end_date && $boundary >= $enrollment->end_date->toDateString()) { continue; }
                $variant = clone $schedule;
                $assignment = $enrollment->transfers->isEmpty() ? [
                    'teacher_id' => $enrollment->teacher_id, 'day_of_week' => $schedule->day_of_week,
                    'start_time' => $schedule->start_time, 'end_time' => $schedule->end_time, 'room_id' => $schedule->room_id,
                ] : $enrollment->teachingAssignmentOn(\Carbon\Carbon::parse($boundary));
                $assignment['start_time'] = $this->normalizeTime($assignment['start_time']);
                $assignment['end_time'] = $this->normalizeTime($assignment['end_time']);
                $variant->forceFill($assignment);
                $variant->setAttribute('period_from', $boundary);
                $variant->setAttribute('period_until', $boundaries[$index + 1] ?? $enrollment->end_date?->toDateString() ?? '9999-12-31');
                $rows->push($variant);
            }
        }
        return $rows;
    }

    public function assertAssignmentAvailable(Enrollment $source, array $assignment, \Carbon\CarbonInterface $from): void
    {
        $assignment['start_time'] = $this->normalizeTime($assignment['start_time']);
        $assignment['end_time'] = $this->normalizeTime($assignment['end_time']);
        $rows = $this->assignmentsFrom($from)->filter(fn ($s) =>
            $s->enrollment_id !== $source->id && $s->day_of_week === $assignment['day_of_week']
            && $s->start_time < $assignment['end_time'] && $s->end_time > $assignment['start_time']);
        // Kapasitas dihitung per batas periode supaya dua reservasi berurutan tidak dijumlahkan.
        $boundaries = $rows->pluck('period_from')->push($from->toDateString())->unique();
        foreach ($boundaries as $boundary) {
            if ($source->end_date && $boundary >= $source->end_date->toDateString()) { continue; }
            $active = $rows->filter(fn ($s) => $s->period_from <= $boundary && $s->period_until > $boundary);
            $teachers = $active->where('teacher_id', $assignment['teacher_id']);
            $type = $source->package->class_type;
            $teacherBlocked = $this->isKidsClassType($type)
                ? $teachers->contains(fn ($s) => !$this->isKidsClassType($s->enrollment->package->class_type))
                : ($type === 'DUO'
                    ? $teachers->contains(fn ($s) => $s->enrollment->package->class_type !== 'DUO') || $teachers->count() >= 2
                    : $teachers->isNotEmpty());
            $rooms = $assignment['room_id'] ? $active->where('room_id', $assignment['room_id']) : collect();
            $roomBlocked = $type === 'DUO'
                ? $rooms->contains(fn ($s) => $s->enrollment->package->class_type !== 'DUO') || $rooms->count() >= 2
                : ($assignment['room_id'] && $rooms->count() >= Room::findOrFail($assignment['room_id'])->capacity);
            if ($teacherBlocked || $roomBlocked || $active->contains('enrollment.student_id', $source->student_id)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['start_time' => 'Guru, ruang, atau murid memiliki jadwal yang bentrok pada periode pengalihan.']);
            }
        }
    }
    /**
     * Cari schedule lain yang akan bentrok kalau guru X mengajar di slot tsb.
     *
     * @param  int|null  $excludeScheduleId  Untuk update existing schedule,
     *                                       jangan cek terhadap dirinya sendiri.
     * @return Collection<int, Schedule>
     */
    public function findTeacherConflicts(
        int $teacherId,
        int $dayOfWeek,
        string $startTime,
        string $endTime,
        ?int $excludeScheduleId = null,
    ): Collection {
        $startTime = $this->normalizeTime($startTime);
        $endTime = $this->normalizeTime($endTime);
        return $this->assignmentsFrom(today())->filter(fn ($s) =>
            (int) $s->teacher_id === $teacherId && $s->day_of_week === $dayOfWeek
            && $s->start_time < $endTime && $s->end_time > $startTime
            && (!$excludeScheduleId || $s->id !== $excludeScheduleId))->unique('id')->values();
    }

    /**
     * Konflik guru yang benar-benar memblokir penambahan jadwal baru (Opsi 2).
     *
     * - Paket Kids baru: hanya diblokir jika slot sudah dipakai kelas non-Kids
     * - Paket privat (REGULER/HOBBY/dll): semua overlap memblokir
     * - DUO: caller pakai findTeacherConflicts + filter DUO sendiri
     */
    public function findBlockingTeacherConflicts(
        int $teacherId,
        int $dayOfWeek,
        string $startTime,
        string $endTime,
        string $newClassType,
        ?int $excludeScheduleId = null,
    ): Collection {
        $conflicts = $this->findTeacherConflicts(
            $teacherId,
            $dayOfWeek,
            $startTime,
            $endTime,
            $excludeScheduleId,
        );

        if ($this->isKidsClassType($newClassType)) {
            return $conflicts
                ->filter(fn ($schedule) => ! $this->isKidsClassType(
                    $schedule->enrollment?->package?->class_type
                ))
                ->values();
        }

        return $conflicts;
    }

    /** Apakah class_type termasuk kelas grup Kids? */
    public function isKidsClassType(?string $classType): bool
    {
        return in_array($classType, ['KIDS_CLASS', 'KIDS_CLASS_BUNDLE'], true);
    }

    /**
     * Cari schedule lain di ruang tsb yang overlap. Caller cek sendiri
     * apakah jumlah konflik >= room.capacity untuk decide error vs warning.
     *
     * @return Collection<int, Schedule>
     */
    public function findRoomConflicts(
        int $roomId,
        int $dayOfWeek,
        string $startTime,
        string $endTime,
        ?int $excludeScheduleId = null,
    ): Collection {
        $startTime = $this->normalizeTime($startTime);
        $endTime = $this->normalizeTime($endTime);
        return $this->assignmentsFrom(today())->filter(fn ($s) =>
            (int) $s->room_id === $roomId && $s->day_of_week === $dayOfWeek
            && $s->start_time < $endTime && $s->end_time > $startTime
            && (!$excludeScheduleId || $s->id !== $excludeScheduleId))->unique('id')->values();
    }

    /**
     * Convenience: cek apakah ruang sudah penuh kalau ditambah satu schedule lagi.
     */
    public function isRoomFull(
        int $roomId,
        int $dayOfWeek,
        string $startTime,
        string $endTime,
        ?int $excludeScheduleId = null,
    ): bool {
        $conflicts = $this->findRoomConflicts(
            $roomId, $dayOfWeek, $startTime, $endTime, $excludeScheduleId
        );

        $room = Room::find($roomId);
        if (!$room) {
            return false;
        }

        // Schedule baru ini menambah 1 occupant. Penuh kalau total >= capacity.
        return ($conflicts->count() + 1) > $room->capacity;
    }

    /** Samakan jam form dan database; detik yang sudah ada tetap dipertahankan. */
    private function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }

    /**
     * Helper: WHERE clause untuk overlap waktu.
     * Dua interval [A1,A2) overlap dengan [B1,B2) jika A1 < B2 AND A2 > B1.
     */
    private function whereTimeOverlaps($query, string $start, string $end): void
    {
        $query->where('start_time', '<', $end)
              ->where('end_time', '>', $start);
    }
}
