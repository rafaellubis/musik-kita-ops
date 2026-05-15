<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Holiday;
use App\Models\Schedule;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Generator sesi mingguan ke tabel class_sessions (M03).
 *
 * Cara pakai:
 *   $service = app(SessionGeneratorService::class);
 *   $report  = $service->generateForMonth(2026, 6);
 *   echo "Sesi baru: {$report['created']}, sudah ada: {$report['skipped_exists']}";
 *
 * Aturan business yang diimplementasikan:
 *   - Loop semua schedule aktif yang punya enrollment ACTIVE
 *   - Untuk tiap schedule, cari tanggal di bulan target yang day_of_week-nya cocok
 *   - Ambil 4 occurrence pertama (BR-3.5: minggu ke-5 tidak dilaksanakan)
 *   - Kalau tanggal kena hari libur nasional/cuti bersama, generate dengan
 *     status LIBUR (BR-4.10: honor guru tetap dibayar)
 *   - Idempotent: kalau row sudah ada untuk (schedule_id, session_date),
 *     skip — tidak duplikat
 *
 * Catatan: BR-3.5 mengatakan minggu ke-5 dilewat. Implementasi kita
 * "ambil 4 occurrence pertama" otomatis men-skip occurrence ke-5 atau
 * lebih kalau bulan kebetulan punya 5 hari kerja yang sama.
 */
class SessionGeneratorService
{
    /**
     * Maksimal sesi per murid per bulan (BR-3.3).
     */
    private const MAX_SESSIONS_PER_MONTH = 4;

    /**
     * Generate semua sesi yang seharusnya ada di bulan target.
     * Idempotent — aman dipanggil ulang.
     *
     * @return array{
     *     created: int,
     *     skipped_exists: int,
     *     skipped_libur: int,
     *     schedules_processed: int,
     *     month: string,
     * }
     */
    public function generateForMonth(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $report = [
            'created' => 0,
            'skipped_exists' => 0,
            'skipped_libur' => 0,
            'schedules_processed' => 0,
            'month' => $start->format('F Y'),
        ];

        // Pre-load libur nasional untuk bulan ini → array tanggal Y-m-d
        $holidayDates = $this->loadHolidayDates($start, $end);

        // Ambil schedule aktif dengan enrollment ACTIVE dan murid berstatus Aktif.
        // Double-filter karena enrollment bisa tertinggal ACTIVE saat murid sudah mundur.
        $schedules = Schedule::query()
            ->active()
            ->whereHas('enrollment', fn ($q) => $q->active())
            ->whereHas('enrollment.student', fn ($q) => $q->where('status', 'Aktif'))
            ->with('enrollment')
            ->get();

        foreach ($schedules as $schedule) {
            $report['schedules_processed']++;
            $this->generateForSchedule($schedule, $start, $end, $holidayDates, $report);
        }

        return $report;
    }

    /**
     * Generate sesi untuk satu schedule pada periode tertentu.
     * Modifikasi $report by-reference.
     */
    private function generateForSchedule(
        Schedule $schedule,
        Carbon $monthStart,
        Carbon $monthEnd,
        array $holidayDates,
        array &$report,
    ): void {
        $enrollment = $schedule->enrollment;
        if (!$enrollment) {
            return;
        }

        // Iterasi tiap hari dalam bulan, cocokkan day_of_week
        $period = CarbonPeriod::create($monthStart, $monthEnd);
        $sessionsCreated = 0;

        foreach ($period as $date) {
            // BR-3.5: maksimal 4 sesi per (schedule, bulan)
            if ($sessionsCreated >= self::MAX_SESSIONS_PER_MONTH) {
                break;
            }

            // Cocokkan hari (Carbon::dayOfWeek 0=Minggu)
            if ($date->dayOfWeek !== $schedule->day_of_week) {
                continue;
            }

            // Hindari generate sesi sebelum enrollment efektif
            if ($enrollment->effective_date && $date->lt($enrollment->effective_date)) {
                continue;
            }

            // Kalau enrollment sudah berakhir, stop
            if ($enrollment->end_date && $date->gt($enrollment->end_date)) {
                continue;
            }

            $dateStr = $date->toDateString();
            $isLibur = isset($holidayDates[$dateStr]);

            // Idempotent: cek apakah sesi sudah ada
            $existing = ClassSession::where('schedule_id', $schedule->id)
                ->whereDate('session_date', $dateStr)
                ->first();

            if ($existing) {
                $report['skipped_exists']++;
                $sessionsCreated++; // tetap hitung kuota
                continue;
            }

            // Bikin sesi baru
            ClassSession::create([
                'schedule_id'     => $schedule->id,
                'enrollment_id'   => $enrollment->id,
                'student_id'      => $enrollment->student_id,
                'teacher_id'      => $enrollment->teacher_id,
                'session_date'    => $dateStr,
                'start_time'      => $schedule->start_time,
                'end_time'        => $schedule->end_time,
                'room_id'         => $schedule->room_id,
                'status'          => $isLibur
                    ? ClassSession::STATUS_LIBUR
                    : ClassSession::STATUS_SCHEDULED,
                'notes'           => $isLibur
                    ? 'Auto-set LIBUR: ' . $holidayDates[$dateStr]
                    : null,
            ]);

            $report['created']++;
            if ($isLibur) {
                $report['skipped_libur']++;
            }
            $sessionsCreated++;
        }
    }

    /**
     * Pre-load tanggal libur dalam range. Return [Y-m-d => nama hari libur].
     */
    private function loadHolidayDates(Carbon $start, Carbon $end): array
    {
        return Holiday::query()
            ->where('is_active', true)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('type', ['Nasional', 'Cuti Bersama']) // 'Internal' tidak dianggap libur ops
            ->get()
            ->mapWithKeys(fn ($h) => [$h->date->toDateString() => $h->name])
            ->all();
    }
}
