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
 *   1. Konflik guru: 1 guru tidak boleh punya 2 schedule di hari + jam
 *      yang overlap. Selalu konflik (tidak ada exception).
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
        return Schedule::query()
            ->active()
            ->whereHas('enrollment', function ($q) use ($teacherId) {
                $q->active()->where('teacher_id', $teacherId);
            })
            ->where('day_of_week', $dayOfWeek)
            ->where(fn ($q) => $this->whereTimeOverlaps($q, $startTime, $endTime))
            ->when($excludeScheduleId, fn ($q) => $q->where('id', '!=', $excludeScheduleId))
            ->with('enrollment.student')
            ->get();
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
        return Schedule::query()
            ->active()
            ->where('room_id', $roomId)
            ->where('day_of_week', $dayOfWeek)
            ->where(fn ($q) => $this->whereTimeOverlaps($q, $startTime, $endTime))
            ->when($excludeScheduleId, fn ($q) => $q->where('id', '!=', $excludeScheduleId))
            ->with('enrollment.student')
            ->get();
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
