<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\HonorSlip;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Kalkulasi honor guru per bulan (M06).
 *
 * Logika utama:
 *   - Kumpulkan honor_amount dari class_sessions yang honor-nya ke guru ini
 *     (guru asli ATAU guru pengganti — sesuai honored_teacher_id di ClassSession)
 *   - Terapkan cutoff H-2 sebelum akhir bulan (BRD M06 revisi v1.1)
 *   - Buat / update HonorSlip per guru; skip jika sudah PAID
 *
 * Honor per sesi sudah dihitung oleh AttendanceService saat input absensi.
 * Service ini hanya melakukan aggregate — tidak menghitung ulang formula.
 */
class HonorCalculationService
{
    /**
     * Hitung dan buat/update slip untuk SEMUA guru aktif di bulan tertentu.
     *
     * @return array{created:int, updated:int, skipped:int}
     */
    public function generateAllSlips(int $year, int $month, int $createdBy): array
    {
        $report = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        $teachers = Teacher::where('is_active', true)->orderBy('name')->get();

        foreach ($teachers as $teacher) {
            $result = $this->calculateForTeacher($teacher, $year, $month, $createdBy);
            $report[$result]++;
        }

        return $report;
    }

    /**
     * Hitung dan buat/update slip untuk SATU guru.
     *
     * @return 'created'|'updated'|'skipped'
     */
    public function calculateForTeacher(
        Teacher $teacher,
        int $year,
        int $month,
        int $createdBy
    ): string {
        // Jika sudah PAID, jangan diubah
        $existingSlip = HonorSlip::where('teacher_id', $teacher->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        if ($existingSlip && $existingSlip->status === HonorSlip::STATUS_PAID) {
            return 'skipped';
        }

        // H-2 sebelum akhir bulan (BRD revisi v1.1)
        $cutoffDate = Carbon::create($year, $month, 1)
            ->endOfMonth()
            ->subDays(2)
            ->toDateString();

        // Ambil semua sesi yang honor-nya ke guru ini di bulan & tahun ini
        // Guru berhak honor jika:
        //   - dia guru asli DAN tidak ada pengganti (substitute_teacher_id IS NULL)
        //   - ATAU dia adalah guru pengganti (substitute_teacher_id = teacher_id)
        $sessions = ClassSession::query()
            ->where(function ($q) use ($teacher) {
                $q->where(function ($qq) use ($teacher) {
                    $qq->where('teacher_id', $teacher->id)
                       ->whereNull('substitute_teacher_id');
                })->orWhere('substitute_teacher_id', $teacher->id);
            })
            ->whereYear('session_date', $year)
            ->whereMonth('session_date', $month)
            ->whereDate('session_date', '<=', $cutoffDate)
            ->whereNotIn('status', [
                ClassSession::STATUS_SCHEDULED,
                ClassSession::STATUS_IZIN_RESCHEDULE,
                ClassSession::STATUS_IZIN_PENDING,
                ClassSession::STATUS_CANCELLED,
            ])
            ->get(['id', 'honor_code', 'honor_amount', 'session_date', 'status',
                   'teacher_id', 'substitute_teacher_id', 'student_id', 'enrollment_id']);

        $baseHonor = $sessions->sum('honor_amount');

        return DB::transaction(function () use (
            $existingSlip, $teacher, $year, $month, $baseHonor, $createdBy
        ) {
            if ($existingSlip) {
                // Pertahankan komponen manual (transport + other) yang sudah diisi Owner
                $existingSlip->base_honor = $baseHonor;
                $existingSlip->status     = HonorSlip::STATUS_CALCULATED;
                $existingSlip->recalcTotal();
                $existingSlip->save();

                return 'updated';
            }

            // Buat slip baru
            $slip = new HonorSlip([
                'slip_number'     => $this->generateSlipNumber($year, $month),
                'teacher_id'      => $teacher->id,
                'year'            => $year,
                'month'           => $month,
                'base_honor'      => $baseHonor,
                'transport_honor' => 0,
                'other_honor'     => 0,
                'other_honor_note'=> null,
                'status'          => HonorSlip::STATUS_CALCULATED,
                'created_by'      => $createdBy,
            ]);
            $slip->recalcTotal();
            $slip->save();

            return 'created';
        });
    }

    /**
     * Rincian sesi per honor_code untuk ditampilkan di halaman show slip.
     * Hanya sesi dalam rentang cutoff yang diikutkan.
     *
     * @return Collection — groupBy honor_code, tiap item: [{code, count, total}]
     */
    public function getSessionBreakdown(HonorSlip $slip): Collection
    {
        $cutoffDate = Carbon::create($slip->year, $slip->month, 1)
            ->endOfMonth()
            ->subDays(2)
            ->toDateString();

        $sessions = ClassSession::query()
            ->with('student:id,full_name,student_code', 'enrollment.package.instrument')
            ->where(function ($q) use ($slip) {
                $q->where(function ($qq) use ($slip) {
                    $qq->where('teacher_id', $slip->teacher_id)
                       ->whereNull('substitute_teacher_id');
                })->orWhere('substitute_teacher_id', $slip->teacher_id);
            })
            ->whereYear('session_date', $slip->year)
            ->whereMonth('session_date', $slip->month)
            ->whereDate('session_date', '<=', $cutoffDate)
            ->whereNotIn('status', [
                ClassSession::STATUS_SCHEDULED,
                ClassSession::STATUS_IZIN_RESCHEDULE,
                ClassSession::STATUS_IZIN_PENDING,
                ClassSession::STATUS_CANCELLED,
            ])
            ->orderBy('session_date')
            ->get();

        return $sessions;
    }

    /**
     * Ringkasan honor per murid untuk slip cetak (M06 print view).
     *
     * Mengolah hasil getSessionBreakdown() menjadi 1 baris per murid.
     * Urutan: privat (urut nama A-Z) dulu, lalu Kids Class (urut nama A-Z).
     *
     * @return Collection — tiap item: [student_id, student_name, instrument,
     *                                   session_count, total_amount, is_kids]
     */
    public function getStudentBreakdown(HonorSlip $slip): Collection
    {
        $sessions = $this->getSessionBreakdown($slip);

        // Group by student_id, hitung session_count dan total_amount per murid
        $grouped = $sessions->groupBy('student_id')->map(function ($rows) {
            $first  = $rows->first();
            $isKids = $first->honor_code === 'H_KIDS';

            // Nama instrumen: dari enrollment.package.instrument, fallback 'Kids Class'
            $instrument = $isKids
                ? 'Kids Class'
                : optional(optional(optional($first->enrollment)->package)->instrument)->name ?? '—';

            return [
                'student_id'    => $first->student_id,
                'student_name'  => optional($first->student)->full_name ?? '—',
                'instrument'    => $instrument,
                'session_count' => $rows->count(),
                'total_amount'  => $rows->sum('honor_amount'),
                'is_kids'       => $isKids,
            ];
        })->values();

        // Privat dulu (urut nama), lalu Kids Class (urut nama)
        $privat = $grouped->where('is_kids', false)->sortBy('student_name')->values();
        $kids   = $grouped->where('is_kids', true)->sortBy('student_name')->values();

        return $privat->concat($kids);
    }

    /**
     * Tandai slip sebagai PAID. Hanya dipanggil oleh Owner.
     */
    public function markPaid(HonorSlip $slip, int $userId): HonorSlip
    {
        if ($slip->status === HonorSlip::STATUS_PAID) {
            return $slip;
        }

        $slip->update([
            'status'  => HonorSlip::STATUS_PAID,
            'paid_at' => now(),
            'paid_by' => $userId,
        ]);

        return $slip->fresh();
    }

    /**
     * Generate nomor slip format SLIP/YYYY/MM/NNNN (reset per bulan).
     */
    public function generateSlipNumber(int $year, int $month): string
    {
        $monthStr = str_pad((string) $month, 2, '0', STR_PAD_LEFT);

        $latest = HonorSlip::where('slip_number', 'like', "SLIP/{$year}/{$monthStr}/%")
            ->orderBy('slip_number', 'desc')
            ->value('slip_number');

        $nextSeq = 1;
        if ($latest) {
            $parts   = explode('/', $latest);
            $nextSeq = ((int) end($parts)) + 1;
        }

        return sprintf('SLIP/%d/%s/%04d', $year, $monthStr, $nextSeq);
    }
}
