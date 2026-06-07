<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Room;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Pembuatan sesi manual oleh admin (enrollment mid-month / rapel).
 *
 * Berbeda dari RescheduleService: tidak butuh sesi asli, menambah slot baru.
 */
class ManualSessionService
{
    /**
     * @return array<int, ClassSession|null>  Keys 1–4
     */
    public function slotSummary(Enrollment $enrollment, int $year, int $month): array
    {
        $sessions = ClassSession::query()
            ->where('enrollment_id', $enrollment->id)
            ->forAttributionMonth($year, $month)
            ->whereNotNull('session_sequence')
            ->whereNotIn('status', [ClassSession::STATUS_CANCELLED])
            ->get()
            ->keyBy('session_sequence');

        $slots = [];
        for ($i = 1; $i <= 4; $i++) {
            $slots[$i] = $sessions->get($i);
        }

        return $slots;
    }

    public function suggestNextSequence(Enrollment $enrollment, int $year, int $month): ?int
    {
        $used = ClassSession::query()
            ->where('enrollment_id', $enrollment->id)
            ->forAttributionMonth($year, $month)
            ->whereNotNull('session_sequence')
            ->whereNotIn('status', [ClassSession::STATUS_CANCELLED])
            ->pluck('session_sequence')
            ->all();

        for ($i = 1; $i <= 4; $i++) {
            if (! in_array($i, $used, true)) {
                return $i;
            }
        }

        return null;
    }

    public function create(
        Enrollment $enrollment,
        string $sessionDate,
        string $startTime,
        ?int $roomId,
        int $attributionYear,
        int $attributionMonth,
        ?int $sessionSequence = null,
    ): ClassSession {
        $enrollment->loadMissing(['package', 'student']);

        if ($enrollment->status !== 'ACTIVE') {
            throw new InvalidArgumentException('Enrollment harus berstatus ACTIVE.');
        }

        if ($enrollment->student?->status !== 'Aktif') {
            throw new InvalidArgumentException('Murid harus berstatus Aktif.');
        }

        $sessionSequence ??= $this->suggestNextSequence($enrollment, $attributionYear, $attributionMonth);

        if ($sessionSequence === null) {
            throw new InvalidArgumentException('Semua slot sesi (1–4) sudah terisi untuk periode atribusi ini.');
        }

        if ($this->sequenceTaken($enrollment, $attributionYear, $attributionMonth, $sessionSequence)) {
            throw new InvalidArgumentException("Sequence {$sessionSequence} sudah terpakai untuk periode atribusi ini.");
        }

        $package = $enrollment->package;
        if (! $package) {
            throw new InvalidArgumentException('Paket enrollment tidak ditemukan.');
        }

        $startTimeFull = strlen($startTime) === 5 ? $startTime . ':00' : $startTime;
        $endTime = Carbon::createFromFormat('H:i:s', $startTimeFull)
            ->addMinutes($package->duration_min)
            ->format('H:i:s');

        $this->assertNoConflict(
            enrollment: $enrollment,
            date: $sessionDate,
            startTimeFull: $startTimeFull,
            endTime: $endTime,
            roomId: $roomId,
        );

        return ClassSession::create([
            'schedule_id'        => null,
            'enrollment_id'      => $enrollment->id,
            'student_id'         => $enrollment->student_id,
            'teacher_id'         => $enrollment->teacher_id,
            'session_date'       => $sessionDate,
            'start_time'         => $startTimeFull,
            'end_time'           => $endTime,
            'room_id'            => $roomId,
            'status'             => ClassSession::STATUS_SCHEDULED,
            'session_sequence'   => $sessionSequence,
            'attribution_year'   => $attributionYear,
            'attribution_month'  => $attributionMonth,
            'session_type'       => ClassSession::TYPE_MANUAL,
            'notes'              => 'Sesi manual admin',
        ]);
    }

    private function sequenceTaken(
        Enrollment $enrollment,
        int $attributionYear,
        int $attributionMonth,
        int $sessionSequence,
    ): bool {
        return ClassSession::query()
            ->where('enrollment_id', $enrollment->id)
            ->forAttributionMonth($attributionYear, $attributionMonth)
            ->where('session_sequence', $sessionSequence)
            ->whereNotIn('status', [ClassSession::STATUS_CANCELLED])
            ->exists();
    }

    private function assertNoConflict(
        Enrollment $enrollment,
        string $date,
        string $startTimeFull,
        string $endTime,
        ?int $roomId,
    ): void {
        $classType = $enrollment->package?->class_type;
        if (in_array($classType, ['KIDS_CLASS', 'KIDS_CLASS_BUNDLE'], true)) {
            return;
        }

        $isDuo = $classType === 'DUO';
        $teacherId = $enrollment->teacher_id;

        $teacherQuery = $this->overlappingQuery($date, $startTimeFull, $endTime)
            ->where('teacher_id', $teacherId);

        $teacherConflict = $isDuo
            ? $this->findDuoSlotConflict($teacherQuery)
            : ['kind' => 'blocked', 'session' => $teacherQuery->first()];

        if ($teacherConflict['session'] !== null) {
            if ($teacherConflict['kind'] === 'full') {
                throw new InvalidArgumentException('Slot DUO sudah penuh (maksimal 2 murid per slot).');
            }

            $jamMulai   = substr($teacherConflict['session']->start_time, 0, 5);
            $jamSelesai = substr($teacherConflict['session']->end_time, 0, 5);
            throw new InvalidArgumentException(
                "Guru sudah ada sesi lain pada {$date} {$jamMulai}–{$jamSelesai}"
            );
        }

        if ($roomId === null) {
            return;
        }

        $roomQuery = $this->overlappingQuery($date, $startTimeFull, $endTime)
            ->where('room_id', $roomId);

        $roomConflict = $isDuo
            ? $this->findDuoSlotConflict($roomQuery)
            : ['kind' => 'blocked', 'session' => $roomQuery->first()];

        if ($roomConflict['session'] === null) {
            return;
        }

        $room = Room::find($roomId);
        $jamMulai   = substr($roomConflict['session']->start_time, 0, 5);
        $jamSelesai = substr($roomConflict['session']->end_time, 0, 5);

        if ($roomConflict['kind'] === 'full') {
            throw new InvalidArgumentException('Ruangan tidak tersedia di slot ini untuk DUO.');
        }

        throw new InvalidArgumentException(
            "Ruangan {$room->code} sudah dipakai pada {$date} {$jamMulai}–{$jamSelesai}"
        );
    }

    private function overlappingQuery(string $date, string $startTimeFull, string $endTime)
    {
        return ClassSession::whereDate('session_date', $date)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTimeFull)
            ->whereNotIn('status', ClassSession::statusesExcludedFromScheduleConflict());
    }

    /**
     * @return array{kind: 'ok'|'blocked'|'full', session: ClassSession|null}
     */
    private function findDuoSlotConflict($query): array
    {
        $nonDuo = (clone $query)
            ->whereHas('enrollment.package', fn ($q) => $q->where('class_type', '!=', 'DUO'))
            ->first();

        if ($nonDuo !== null) {
            return ['kind' => 'blocked', 'session' => $nonDuo];
        }

        $duoCount = (clone $query)
            ->whereHas('enrollment.package', fn ($q) => $q->where('class_type', 'DUO'))
            ->count();

        if ($duoCount >= 2) {
            return [
                'kind'    => 'full',
                'session' => (clone $query)
                    ->whereHas('enrollment.package', fn ($q) => $q->where('class_type', 'DUO'))
                    ->first(),
            ];
        }

        return ['kind' => 'ok', 'session' => null];
    }
}
