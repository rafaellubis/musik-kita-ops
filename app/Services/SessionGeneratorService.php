<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Holiday;
use App\Models\PayrollConfig;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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
 *   - Ada replacement_date → honor Rp 0 di sesi LIBUR; honor sesi pengganti
 *     diisi saat absensi (AttendanceService), sama seperti RescheduleService
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
        app(EnrollmentPromotionService::class)->applyDue();
        app(EnrollmentTransferService::class)->applyDue();
        return $this->generateMonth($year, $month);
    }

    public function generateForEnrollment(Enrollment $enrollment, int $year, int $month): array
    {
        return $this->generateMonth($year, $month, $enrollment);
    }

    private function generateMonth(int $year, int $month, ?Enrollment $only = null): array
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

        // Seluruh proses generate dibungkus DB::transaction() agar atomic.
        // Jika koneksi terputus di tengah proses, SEMUA insert di-rollback —
        // tidak ada data sesi partial yang tersimpan di database.
        // Admin cukup jalankan ulang generator setelah koneksi pulih.
        DB::transaction(function () use ($start, $end, $only, &$report) {
            // Pre-load holidays bulan ini sebagai [Y-m-d => Holiday]
            // Include 'Internal' agar Konser KITA terdeteksi
            $holidayMap = $this->loadHolidayDates($start, $end);

            // Status hari ini tidak menentukan sesi bulan target; tanggal cuti per kelas yang menentukan.
            $schedules = Schedule::query()
                ->active()
                ->whereHas('enrollment', fn ($q) => $q->forPeriod($start, $end->copy()->addDay()->startOfDay()))
                ->when($only, fn ($q) => $q->where('enrollment_id', $only->id))
                ->whereHas('enrollment.student', fn ($q) => $q->whereIn('status', ['Aktif', 'Cuti']))
                ->with(['enrollment', 'enrollment.package', 'enrollment.student', 'enrollment.teacher', 'enrollment.leaves'])
                ->get();

            foreach ($schedules as $schedule) {
                $report['schedules_processed']++;
                $this->generateForSchedule($schedule, $start, $end, $holidayMap, $report);
            }
        });

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
        $baseSchedule = $schedule;
        if (!$enrollment) {
            return;
        }

        // Tanggal mengikuti penugasan pada hari tersebut, termasuk pengalihan tengah bulan.
        $allDates = collect();
        $cursor = $monthStart->copy();
        while ($cursor->lte($monthEnd)) {
            $assignment = $enrollment->transfers->isEmpty()
                ? ['day_of_week' => $schedule->day_of_week]
                : $enrollment->teachingAssignmentOn($cursor);
            if ($cursor->dayOfWeek === $assignment['day_of_week']) {
                $allDates->push($cursor->copy());
            }
            $cursor->addDay();
        }
        $replacementQueue = [];
        $scheduledCount = 0;
        $slotCounter = 0;
        $weekDates = $allDates->take(self::MAX_SESSIONS_PER_MONTH);

        foreach ($weekDates as $date) {
            $schedule = $this->assignedSchedule($baseSchedule, $date);
            $enrollment = $schedule->enrollment;
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

            // Jangan menggeser nomor slot atau membuat sesi LIBUR dalam periode cuti.
            if ($enrollment->isOnLeaveOn($dateStr)) {
                continue;
            }

            // Hak sesi khusus yang dipertahankan tidak dibuat ulang di tanggal rutin baru.
            if ($enrollment->transfers->isNotEmpty() && ClassSession::where('enrollment_id', $enrollment->id)
                ->forAttributionMonth($monthStart->year, $monthStart->month)
                ->where('session_sequence', $slotCounter)->where('session_date', '!=', $dateStr)->exists()) {
                $report['skipped_exists']++;
                continue;
            }

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
                    ...$this->attributionFields($date),
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
                    ...$this->attributionFields($date),
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
            $schedule = $this->assignedSchedule($baseSchedule, $repDate);
            $enrollment = $schedule->enrollment;

            // Tanggal pengganti dapat berada di bulan lain dan tetap mengikuti riwayat cuti kelas.
            if ($enrollment->isOnLeaveOn($repStr)) {
                continue;
            }

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

            // Honor sesi pengganti diisi saat absensi (AttendanceService) —
            // konsisten dengan RescheduleService::createReplacement()
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
                'honor_code'       => null,
                'honor_amount'     => null,
                'notes'            => 'Sesi pengganti dari tanggal libur',
                'session_sequence' => $repItem['reserved_slot'],    // mewarisi slot LIBUR
                'origin_session_id'=> $repItem['libur_session_id'], // referensi ke sesi LIBUR
                // Atribusi mengikuti bulan yang di-generate (slot mingguan), bukan bulan tanggal pengganti.
                ...$this->attributionFields($monthStart),
            ]);

            $report['created']++;
            $report['replacements_created']++;
            $scheduledCount++;
        }

        // FASE 5: Warning jika sesi efektif kurang dari minimum (BR-3.3)
        if ($scheduledCount > 0 && $scheduledCount < 3) {
            Log::warning(
                "[SessionGenerator] Peringatan: murid #{$enrollment->student_id} " .
                "hanya {$scheduledCount} sesi di bulan ini — cek periode cuti dan hari libur"
            );
        }
    }

    private function assignedSchedule(Schedule $schedule, Carbon $date): Schedule
    {
        if ($schedule->enrollment->transfers->isEmpty()) {
            return $schedule;
        }
        $assigned = clone $schedule;
        $enrollment = clone $schedule->enrollment;
        $assignment = $enrollment->teachingAssignmentOn($date);
        $enrollment->teacher_id = $assignment['teacher_id'];
        unset($assignment['teacher_id']);
        $assigned->forceFill($assignment);
        $assigned->setRelation('enrollment', $enrollment);
        return $assigned;
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

        // Ada tanggal pengganti → honor sesi LIBUR Rp 0; honor dibayar via sesi pengganti saat absensi
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
        if ($schedule->enrollment->transfers->isNotEmpty()) {
            return app(ScheduleConflictDetector::class)->findConcreteConflict($schedule->enrollment, [
                'teacher_id' => $schedule->enrollment->teacher_id, 'room_id' => $schedule->room_id,
                'start_time' => $schedule->start_time, 'end_time' => $schedule->end_time,
            ], $date->toDateString());
        }
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
                ->where(fn ($q) => $q->whereNull('schedule_id')->orWhere('schedule_id', '!=', $schedule->id))
                ->whereNotIn('status', ClassSession::statusesExcludedFromScheduleConflict())
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
                    ->where(fn ($q) => $q->whereNull('schedule_id')->orWhere('schedule_id', '!=', $schedule->id))
                    ->whereNotIn('status', ClassSession::statusesExcludedFromScheduleConflict())
                    ->whereHas('enrollment.package', fn ($q) => $q->where('class_type', '!=', 'DUO'))
                    ->first();

                if ($roomConflict) {
                    return $roomConflict;
                }
            }

            // Cek slot DUO tidak melebihi 2 murid (BR-3.11)
            $duoCount = ClassSession::where('teacher_id', $teacherId)
                ->whereDate('session_date', $date)
                ->where('start_time', '<', $schedule->end_time)
                ->where('end_time', '>', $schedule->start_time)
                ->where(fn ($q) => $q->whereNull('schedule_id')->orWhere('schedule_id', '!=', $schedule->id))
                ->whereNotIn('status', ClassSession::statusesExcludedFromScheduleConflict())
                ->whereHas('enrollment.package', fn ($q) => $q->where('class_type', 'DUO'))
                ->count();

            if ($duoCount >= 2) {
                // Kembalikan salah satu sesi DUO yang sudah ada sebagai referensi konflik
                return ClassSession::where('teacher_id', $teacherId)
                    ->whereDate('session_date', $date)
                    ->where('start_time', '<', $schedule->end_time)
                    ->where('end_time', '>', $schedule->start_time)
                    ->where(fn ($q) => $q->whereNull('schedule_id')->orWhere('schedule_id', '!=', $schedule->id))
                    ->whereNotIn('status', ClassSession::statusesExcludedFromScheduleConflict())
                    ->whereHas('enrollment.package', fn ($q) => $q->where('class_type', 'DUO'))
                    ->with('student')
                    ->first();
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
            ->where(fn ($q) => $q->whereNull('schedule_id')->orWhere('schedule_id', '!=', $schedule->id))
            ->whereNotIn('status', ClassSession::statusesExcludedFromScheduleConflict())
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
                ->where(fn ($q) => $q->whereNull('schedule_id')->orWhere('schedule_id', '!=', $schedule->id))
                ->whereNotIn('status', ClassSession::statusesExcludedFromScheduleConflict())
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

    /** @return array{attribution_year: int, attribution_month: int, session_type: string} */
    private function attributionFields(Carbon $date): array
    {
        return [
            'attribution_year'  => $date->year,
            'attribution_month' => $date->month,
            'session_type'      => ClassSession::TYPE_REGULAR,
        ];
    }
}
