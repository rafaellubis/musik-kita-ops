<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Holiday;
use App\Models\PayrollConfig;
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
            'created'                  => 0,
            'replacements_created'     => 0,
            'skipped_exists'           => 0,
            'skipped_libur'            => 0,
            'skipped_conflict'         => 0,
            'skipped_conflict_details' => [], // ["Budi Santoso (THOMAS) — Senin 2026-06-02"]
            'schedules_processed'      => 0,
            'month'                    => $start->format('F Y'),
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
            ->with(['enrollment', 'enrollment.package', 'enrollment.student', 'enrollment.teacher'])
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

        $replacementQueue = []; // [['date'=>Carbon,'reserved_slot'=>int,'libur_session_id'=>int]]
        $scheduledCount   = 0;
        $slotCounter      = 0;  // nomor slot mingguan (1–4, sesuai urutan occurrence)

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

            // Slot ke-N untuk murid ini di bulan ini — increment sebelum idempotency check
            // agar run kedua tetap menghitung slot yang sama (sequence sudah tersimpan di run pertama)
            $slotCounter++;
            $dateStr = $date->toDateString();

            // Idempotency: skip jika sesi sudah ada (sequence sudah ter-set saat pertama kali dibuat)
            if (ClassSession::where('schedule_id', $schedule->id)
                ->whereDate('session_date', $dateStr)->exists()) {
                $report['skipped_exists']++;
                continue;
            }

            if (isset($holidayMap[$dateStr])) {
                // Tanggal ini adalah hari libur
                $holiday = $holidayMap[$dateStr];

                // Guard: skip jika guru sudah punya sesi LIBUR lain di jam yang sama
                // (mencegah double-count honor saat dua schedule punya guru+jam sama)
                $liburConflict = $this->findConflictOnDate($schedule, $date);
                if ($liburConflict) {
                    $detail = $this->buildConflictDetail($enrollment, $dateStr, $liburConflict, 'LIBUR');
                    Log::warning("[SessionGenerator] Skip {$detail}");
                    $report['skipped_conflict']++;
                    $report['skipped_conflict_details'][] = $detail;
                    continue;
                }

                [$honorCode, $honorAmount] = $this->resolveLiburHonor($holiday, $enrollment);

                // LIBUR dengan replacement → sequence null (slot diserahkan ke sesi pengganti)
                // LIBUR tanpa replacement → sequence = slotCounter (honor dibayar penuh, BR-4.10)
                $liburSequence = $holiday->replacement_date ? null : $slotCounter;

                $liburSession = ClassSession::create([
                    'schedule_id'      => $schedule->id,
                    'enrollment_id'    => $enrollment->id,
                    'student_id'       => $enrollment->student_id,
                    'teacher_id'       => $enrollment->teacher_id,
                    'session_date'     => $dateStr,
                    'start_time'       => $schedule->start_time,
                    'end_time'         => $schedule->end_time,
                    'room_id'          => $schedule->room_id,
                    'status'           => 'LIBUR',
                    'honor_code'       => $honorCode,
                    'honor_amount'     => $honorAmount,
                    'notes'            => 'Auto-set LIBUR: ' . $holiday->name,
                    'session_sequence' => $liburSequence,
                ]);

                $report['created']++;
                $report['skipped_libur']++;

                // Jika ada tanggal pengganti, antri dengan reserved_slot dan libur_session_id
                if ($holiday->replacement_date) {
                    $replacementQueue[] = [
                        'date'             => Carbon::parse($holiday->replacement_date),
                        'reserved_slot'    => $slotCounter,
                        'libur_session_id' => $liburSession->id,
                    ];
                }
            } else {
                // Guard FASE 2: skip jika guru atau ruang sudah punya sesi di jam yang sama
                $regularConflict = $this->findConflictOnDate($schedule, $date);
                if ($regularConflict) {
                    $detail = $this->buildConflictDetail($enrollment, $dateStr, $regularConflict);
                    Log::warning("[SessionGenerator] Skip {$detail}");
                    $report['skipped_conflict_details'][] = $detail;
                    $report['skipped_conflict']++;
                    continue;
                }

                // Sesi normal
                ClassSession::create([
                    'schedule_id'      => $schedule->id,
                    'enrollment_id'    => $enrollment->id,
                    'student_id'       => $enrollment->student_id,
                    'teacher_id'       => $enrollment->teacher_id,
                    'session_date'     => $dateStr,
                    'start_time'       => $schedule->start_time,
                    'end_time'         => $schedule->end_time,
                    'room_id'          => $schedule->room_id,
                    'status'           => 'SCHEDULED',
                    'session_sequence' => $slotCounter,
                ]);

                $report['created']++;
                $scheduledCount++;
            }
        }

        // FASE 3: Buat replacement sessions
        // Replacement dibuat DI LUAR counter 4-sesi — tidak memblok week 5
        foreach ($replacementQueue as $repItem) {
            $repDate = $repItem['date'];
            $repStr  = $repDate->toDateString();

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
            $repConflict = $this->findConflictOnDate($schedule, $repDate);
            if ($repConflict) {
                $detail = $this->buildConflictDetail($enrollment, $repStr, $repConflict, 'Pengganti');
                Log::warning("[SessionGenerator] Skip {$detail}");
                $report['skipped_conflict']++;
                $report['skipped_conflict_details'][] = $detail;
                continue;
            }

            // Tentukan honor sesi pengganti: DUO pakai H_DUO dari config, lainnya H_REG
            if ($enrollment->package?->isDuo()) {
                $repHonorCode   = 'H_DUO';
                $repHonorAmount = (int) (PayrollConfig::where('scenario_code', 'H_DUO')->value('value_or_formula') ?? 40000);
            } else {
                $repHonorCode   = 'H_REG';
                $repHonorAmount = $this->calculateBaseHonor($enrollment);
            }

            ClassSession::create([
                'schedule_id'      => $schedule->id,
                'enrollment_id'    => $enrollment->id,
                'student_id'       => $enrollment->student_id,
                'teacher_id'       => $enrollment->teacher_id,
                'session_date'     => $repStr,
                'start_time'       => $schedule->start_time,
                'end_time'         => $schedule->end_time,
                'room_id'          => $schedule->room_id,
                'status'           => 'SCHEDULED',
                'honor_code'       => $repHonorCode,
                'honor_amount'     => $repHonorAmount,
                'notes'            => 'Sesi pengganti dari tanggal libur',
                'session_sequence' => $repItem['reserved_slot'],    // mewarisi slot LIBUR
                'origin_session_id'=> $repItem['libur_session_id'], // referensi ke sesi LIBUR
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
        // Kids Class: flat Rp 42.500 per murid (konsisten dengan AttendanceService::H_KIDS)
        if ($enrollment->package?->isKidsClass()) {
            return ['H_KIDS', 42500];
        }

        // DUO: honor libur per murid diambil dari PayrollConfig H_DUO
        if ($enrollment->package?->isDuo()) {
            $honorPerMurid = (int) (PayrollConfig::where('scenario_code', 'H_DUO')
                ->value('value_or_formula') ?? 40000);
            return ['H_DUO', $honorPerMurid];
        }

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
     * Cari sesi yang konflik dengan schedule ini pada tanggal tertentu.
     * Return ClassSession pertama yang bentrok (dengan relasi student eager-loaded),
     * atau null jika tidak ada konflik.
     *
     * Cek guru dulu, lalu ruang. Berbeda dari ScheduleConflictDetector —
     * ini cek ClassSession konkret, bukan jadwal mingguan.
     */
    private function findConflictOnDate(Schedule $schedule, Carbon $date): ?ClassSession
    {
        // Kids Class adalah kelas grup — satu guru dan satu ruang dipakai beberapa murid
        // sekaligus. Tidak ada "konflik" untuk sesi Kids Class.
        $classType = $schedule->enrollment->package?->class_type;
        if (in_array($classType, ['KIDS_CLASS', 'KIDS_CLASS_BUNDLE'])) {
            return null;
        }

        // DUO: boleh berbagi slot dengan tepat satu DUO lain (pasangan).
        // Konflik tetap berlaku jika slot dipakai kelas non-DUO (REGULER, HOBBY, dll).
        if ($classType === 'DUO') {
            $teacherId = $schedule->enrollment->teacher_id;

            // Cek guru: konflik hanya jika sesi lain di slot ini BUKAN DUO
            $teacherConflict = ClassSession::where('teacher_id', $teacherId)
                ->whereDate('session_date', $date)
                ->where('start_time', '<', $schedule->end_time)
                ->where('end_time', '>', $schedule->start_time)
                ->where('schedule_id', '!=', $schedule->id)
                ->whereNotIn('status', ['CANCELLED'])
                ->whereHas('enrollment.package', fn ($q) => $q->where('class_type', '!=', 'DUO'))
                ->first();

            if ($teacherConflict) {
                return $teacherConflict;
            }

            // Cek ruang: konflik hanya jika sesi lain di slot ini BUKAN DUO
            if ($schedule->room_id) {
                $roomConflict = ClassSession::where('room_id', $schedule->room_id)
                    ->whereDate('session_date', $date)
                    ->where('start_time', '<', $schedule->end_time)
                    ->where('end_time', '>', $schedule->start_time)
                    ->where('schedule_id', '!=', $schedule->id)
                    ->whereNotIn('status', ['CANCELLED'])
                    ->whereHas('enrollment.package', fn ($q) => $q->where('class_type', '!=', 'DUO'))
                    ->first();

                if ($roomConflict) {
                    return $roomConflict;
                }
            }

            return null;
        }

        $teacherId = $schedule->enrollment->teacher_id;

        // Overlap interval: A_start < B_end AND A_end > B_start
        $teacherConflict = ClassSession::with('student')
            ->where('teacher_id', $teacherId)
            ->whereDate('session_date', $date)
            ->where('start_time', '<', $schedule->end_time)
            ->where('end_time', '>', $schedule->start_time)
            ->where('schedule_id', '!=', $schedule->id)
            ->whereNotIn('status', ['CANCELLED'])
            ->first();

        if ($teacherConflict) {
            return $teacherConflict;
        }

        if ($schedule->room_id) {
            $roomConflict = ClassSession::with('student')
                ->where('room_id', $schedule->room_id)
                ->whereDate('session_date', $date)
                ->where('start_time', '<', $schedule->end_time)
                ->where('end_time', '>', $schedule->start_time)
                ->where('schedule_id', '!=', $schedule->id)
                ->whereNotIn('status', ['CANCELLED'])
                ->first();

            if ($roomConflict) {
                return $roomConflict;
            }
        }

        return null;
    }

    /**
     * Bangun string detail konflik untuk ditampilkan di UI dan log.
     * Contoh: "Budi Santoso (THOMAS, 15:00–15:30) — bentrok dengan: Rina Wijaya [SCHEDULED]"
     */
    private function buildConflictDetail(
        Enrollment $enrollment,
        string $dateStr,
        ClassSession $conflicting,
        string $prefix = ''
    ): string {
        $studentName    = $enrollment->student->full_name  ?? "student #{$enrollment->student_id}";
        $teacherName    = $enrollment->teacher->name       ?? "guru #{$enrollment->teacher_id}";
        $startTime      = substr($enrollment->schedules->first()?->start_time ?? '', 0, 5);
        $endTime        = substr($enrollment->schedules->first()?->end_time   ?? '', 0, 5);
        $withStudent    = $conflicting->student->full_name ?? "student #{$conflicting->student_id}";
        $conflictStart  = substr($conflicting->start_time, 0, 5);
        $conflictEnd    = substr($conflicting->end_time,   0, 5);

        $jam = $startTime && $endTime ? " {$startTime}–{$endTime}" : '';
        $label = $prefix ? "{$prefix} " : '';

        return "{$label}{$dateStr} | {$studentName} (guru: {$teacherName}{$jam}) — " .
               "bentrok dengan: {$withStudent} [{$conflictStart}–{$conflictEnd}, {$conflicting->status}]";
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
