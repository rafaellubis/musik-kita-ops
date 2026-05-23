<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Holiday;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Generator sesi mingguan ke tabel class_sessions (M03).
 *
 * Cara pakai:
 *   $report = app(SessionGeneratorService::class)->generateForMonth(2026, 6);
 *
 * Aturan yang diimplementasikan (BRD v1.3):
 *   R3: 5 occurrence, 0 libur → 4 SCHEDULED (week 5 skip)
 *   R4: 5 occurrence, 1 libur DGN replacement → 3 SCHEDULED + 1 LIBUR + 1 replacement
 *   R4b: 5 occurrence, 1 libur TANPA replacement → 3 SCHEDULED + 1 LIBUR (week 5 tetap skip)
 *   R5: 4 occurrence, 0 libur → 4 SCHEDULED
 *   R6: 4 occurrence, 1 libur DGN replacement → 3 SCHEDULED + 1 LIBUR + 1 replacement
 *   R6b: 4 occurrence, 1 libur TANPA replacement → 3 SCHEDULED + 1 LIBUR
 *
 *   Honor LIBUR:
 *   - is_honor_paid=false (Konser KITA) → honor Rp 0
 *   - Ada replacement_date → honor Rp 0 (dibayar via sesi pengganti)
 *   - Libur nasional tanpa pengganti → H_LIBUR penuh (BR-4.10)
 *
 * Idempotent: aman dipanggil ulang. Tidak ada retroactive update jika holiday diubah
 * setelah sesi digenerate — admin handle manual via Reschedule.
 */
class SessionGeneratorService
{
    private const MAX_SESSIONS_PER_MONTH = 4;

    /**
     * Generate semua sesi yang seharusnya ada di bulan target.
     *
     * @return array{
     *     created: int,
     *     replacements_created: int,
     *     skipped_exists: int,
     *     skipped_libur: int,
     *     skipped_conflict: int,
     *     schedules_processed: int,
     *     month: string,
     * }
     */
    public function generateForMonth(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $report = [
            'created'              => 0,
            'replacements_created' => 0,
            'skipped_exists'       => 0,
            'skipped_libur'        => 0,
            'skipped_conflict'     => 0,
            'schedules_processed'  => 0,
            'month'                => $start->format('F Y'),
        ];

        // Pre-load holidays bulan ini sebagai [Y-m-d => Holiday]
        // Include 'Internal' agar Konser KITA terdeteksi
        $holidayMap = $this->loadHolidayDates($start, $end);

        // Ambil schedule aktif dengan enrollment ACTIVE dan murid berstatus Aktif.
        // Double-filter karena enrollment bisa tertinggal ACTIVE saat murid sudah mundur.
        // Eager-load enrollment.package untuk perhitungan honor
        $schedules = Schedule::query()
            ->active()
            ->whereHas('enrollment', fn ($q) => $q->active())
            ->whereHas('enrollment.student', fn ($q) => $q->where('status', 'Aktif'))
            ->with(['enrollment', 'enrollment.package'])
            ->get();

        foreach ($schedules as $schedule) {
            $report['schedules_processed']++;
            $this->generateForSchedule($schedule, $start, $end, $holidayMap, $report);
        }

        return $report;
    }

    /**
     * Generate sesi untuk satu schedule pada bulan tertentu.
     * Modifikasi $report by-reference.
     */
    private function generateForSchedule(
        Schedule $schedule,
        Carbon $monthStart,
        Carbon $monthEnd,
        array $holidayMap,
        array &$report,
    ): void {
        $enrollment = $schedule->enrollment;
        if (!$enrollment) {
            return;
        }

        // FASE 1: Kumpulkan semua tanggal yang cocok dengan day_of_week di bulan ini
        $allDates = collect();
        $cursor   = $monthStart->copy();
        while ($cursor->lte($monthEnd)) {
            if ($cursor->dayOfWeek === $schedule->day_of_week) {
                $allDates->push($cursor->copy());
            }
            $cursor->addDay();
        }

        $replacementQueue = []; // Carbon[] — tanggal pengganti
        $scheduledCount   = 0;  // hitung sesi efektif (SCHEDULED, bukan LIBUR)

        // FASE 2: Proses minggu ke-1 sampai ke-4 saja (BR-3.5)
        $weekDates = $allDates->take(self::MAX_SESSIONS_PER_MONTH);

        foreach ($weekDates as $date) {
            // Guard: enrollment boundary
            if ($enrollment->effective_date && $date->lt($enrollment->effective_date)) {
                continue;
            }
            if ($enrollment->end_date && $date->gte($enrollment->end_date)) {
                continue;
            }

            $dateStr = $date->toDateString();

            // Idempotency: skip jika sesi sudah ada
            if (ClassSession::where('schedule_id', $schedule->id)
                ->whereDate('session_date', $dateStr)->exists()) {
                $report['skipped_exists']++;
                continue;
            }

            if (isset($holidayMap[$dateStr])) {
                // Tanggal ini adalah hari libur
                $holiday = $holidayMap[$dateStr];

                [$honorCode, $honorAmount] = $this->resolveLiburHonor($holiday, $enrollment);

                ClassSession::create([
                    'schedule_id'   => $schedule->id,
                    'enrollment_id' => $enrollment->id,
                    'student_id'    => $enrollment->student_id,
                    'teacher_id'    => $enrollment->teacher_id,
                    'session_date'  => $dateStr,
                    'start_time'    => $schedule->start_time,
                    'end_time'      => $schedule->end_time,
                    'room_id'       => $schedule->room_id,
                    'status'        => 'LIBUR',
                    'honor_code'    => $honorCode,
                    'honor_amount'  => $honorAmount,
                    'notes'         => 'Auto-set LIBUR: ' . $holiday->name,
                ]);

                $report['created']++;
                $report['skipped_libur']++;

                // Jika ada tanggal pengganti, antri untuk FASE 3
                if ($holiday->replacement_date) {
                    $replacementQueue[] = Carbon::parse($holiday->replacement_date);
                }
            } else {
                // Sesi normal
                ClassSession::create([
                    'schedule_id'   => $schedule->id,
                    'enrollment_id' => $enrollment->id,
                    'student_id'    => $enrollment->student_id,
                    'teacher_id'    => $enrollment->teacher_id,
                    'session_date'  => $dateStr,
                    'start_time'    => $schedule->start_time,
                    'end_time'      => $schedule->end_time,
                    'room_id'       => $schedule->room_id,
                    'status'        => 'SCHEDULED',
                ]);

                $report['created']++;
                $scheduledCount++;
            }
        }

        // FASE 3: Buat replacement sessions
        // Replacement dibuat DI LUAR counter 4-sesi — tidak memblok week 5
        foreach ($replacementQueue as $repDate) {
            $repStr = $repDate->toDateString();

            // Guard 1: idempotency
            if (ClassSession::where('schedule_id', $schedule->id)
                ->whereDate('session_date', $repStr)->exists()) {
                $report['skipped_exists']++;
                continue;
            }

            // Guard 2: enrollment boundary
            if ($enrollment->effective_date && $repDate->lt($enrollment->effective_date)) {
                Log::info("[SessionGenerator] Skip replacement {$repStr}: sebelum effective_date enrollment #{$enrollment->id}");
                continue;
            }
            if ($enrollment->end_date && $repDate->gte($enrollment->end_date)) {
                Log::info("[SessionGenerator] Skip replacement {$repStr}: setelah end_date enrollment #{$enrollment->id}");
                continue;
            }

            // Guard 3: replacement_date bukan hari libur lain
            if (isset($holidayMap[$repStr])) {
                Log::warning("[SessionGenerator] Skip replacement {$repStr}: tanggal tersebut juga hari libur ({$holidayMap[$repStr]->name})");
                continue;
            }

            // Guard 4: conflict detection guru dan ruang
            if ($this->hasConflictOnDate($schedule, $repDate)) {
                Log::warning("[SessionGenerator] Skip replacement {$repStr}: konflik jadwal guru/ruang untuk schedule #{$schedule->id}");
                $report['skipped_conflict']++;
                continue;
            }

            ClassSession::create([
                'schedule_id'   => $schedule->id,
                'enrollment_id' => $enrollment->id,
                'student_id'    => $enrollment->student_id,
                'teacher_id'    => $enrollment->teacher_id,
                'session_date'  => $repStr,
                'start_time'    => $schedule->start_time,
                'end_time'      => $schedule->end_time,
                'room_id'       => $schedule->room_id,
                'status'        => 'SCHEDULED',
                'honor_code'    => 'H_REG',
                'honor_amount'  => $this->calculateBaseHonor($enrollment),
                'notes'         => 'Sesi pengganti dari tanggal libur',
            ]);

            $report['created']++;
            $report['replacements_created']++;
            $scheduledCount++;
        }

        // FASE 5: Warning jika sesi efektif kurang dari minimum (BR-3.3)
        if ($scheduledCount > 0 && $scheduledCount < 3) {
            Log::warning(
                "[SessionGenerator] Peringatan: murid #{$enrollment->student_id} " .
                "hanya {$scheduledCount} sesi di bulan ini — cek hari libur"
            );
        }
    }

    /**
     * Tentukan honor_code dan honor_amount untuk sesi LIBUR.
     *
     * @return array{0: string|null, 1: int}  [honor_code, honor_amount]
     */
    private function resolveLiburHonor(Holiday $holiday, Enrollment $enrollment): array
    {
        // Konser KITA atau event studio yang tidak membayar honor via session
        if (!$holiday->is_honor_paid) {
            return [null, 0];
        }

        // Ada tanggal pengganti → honor akan dibayar via sesi pengganti (H_REG)
        if ($holiday->replacement_date) {
            return [null, 0];
        }

        // Libur nasional/cuti bersama tanpa pengganti → honor penuh (BR-4.10)
        return ['H_LIBUR', $this->calculateBaseHonor($enrollment)];
    }

    /**
     * Hitung honor dasar per sesi berdasarkan paket enrollment.
     * Formula: price_per_month × 50% / 4
     *
     * Kids Class dikecualikan — honor-nya dihitung per jumlah murid aktif (H_KIDS),
     * bukan per session.
     */
    private function calculateBaseHonor(Enrollment $enrollment): int
    {
        $package = $enrollment->package;
        if (!$package) {
            return 0;
        }

        if ($package->isKidsClass()) {
            return 0;
        }

        return (int) round($package->price_per_month * 0.5 / 4);
    }

    /**
     * Cek konflik guru atau ruang pada tanggal spesifik.
     * Berbeda dari ScheduleConflictDetector — ini cek ClassSession konkret di tanggal tertentu.
     */
    private function hasConflictOnDate(Schedule $schedule, Carbon $date): bool
    {
        $teacherId = $schedule->enrollment->teacher_id;

        $teacherBusy = ClassSession::where('teacher_id', $teacherId)
            ->whereDate('session_date', $date)
            ->where('start_time', $schedule->start_time)
            ->where('schedule_id', '!=', $schedule->id)
            ->whereNotIn('status', ['CANCELLED'])
            ->exists();

        if ($teacherBusy) {
            return true;
        }

        if ($schedule->room_id) {
            $roomBusy = ClassSession::where('room_id', $schedule->room_id)
                ->whereDate('session_date', $date)
                ->where('start_time', $schedule->start_time)
                ->where('schedule_id', '!=', $schedule->id)
                ->whereNotIn('status', ['CANCELLED'])
                ->exists();

            if ($roomBusy) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pre-load holidays dalam range sebagai [Y-m-d => Holiday].
     *
     * Include tipe 'Internal' agar Konser KITA terdeteksi.
     *
     * @return array<string, Holiday>
     */
    private function loadHolidayDates(Carbon $start, Carbon $end): array
    {
        return Holiday::query()
            ->where('is_active', true)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('type', ['Nasional', 'Cuti Bersama', 'Internal'])
            ->get()
            ->mapWithKeys(fn ($h) => [$h->date->toDateString() => $h])
            ->all();
    }
}
