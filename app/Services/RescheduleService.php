<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\PayrollConfig;
use App\Models\Room;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Menangani pembuatan sesi pengganti (reschedule) beserta validasi konflik.
 *
 * Dipanggil oleh AbsensiController saat status IZIN_RESCHEDULE.
 * Tidak menyentuh AttendanceService atau HonorCalculationService.
 */
class RescheduleService
{
    /**
     * Buat sesi pengganti untuk sesi yang di-reschedule.
     *
     * @param  ClassSession   $original            Sesi asli (status sudah IZIN_RESCHEDULE)
     * @param  string         $date                Format Y-m-d (tanggal pengganti)
     * @param  string         $startTime           Format H:i (jam mulai pengganti)
     * @param  int|null       $roomId              ID ruangan pengganti, null = tanpa ruangan
     * @param  Enrollment|null $overrideEnrollment Jika diisi, sesi dibuat untuk murid/enrollment ini
     *                                             (dipakai Open Slot Board — "isi slot" dengan murid lain)
     *                                             Guru tetap dari sesi asli.
     *
     * @throws InvalidArgumentException Jika ada konflik guru atau ruangan
     */
    public function createReplacement(
        ClassSession $original,
        string $date,
        string $startTime,
        ?int $roomId,
        ?Enrollment $overrideEnrollment = null,
    ): ClassSession {
        // Hitung jam selesai berdasarkan durasi paket enrollment
        // Jika ada override enrollment, gunakan paket dari enrollment override
        if ($overrideEnrollment !== null) {
            $overrideEnrollment->loadMissing('package');
            $durationMin = $overrideEnrollment->package->duration_min;
        } else {
            $original->loadMissing(['enrollment.package', 'teacher']);
            $durationMin = $original->enrollment->package->duration_min;
        }

        $endTime = Carbon::createFromFormat('H:i', $startTime)
            ->addMinutes($durationMin)
            ->format('H:i:s');

        $startTimeFull = $startTime . ':00';

        // Tentukan student_id dan enrollment_id yang dipakai di sesi pengganti
        $enrollmentId = $overrideEnrollment?->id ?? $original->enrollment_id;
        $studentId    = $overrideEnrollment?->student_id ?? $original->student_id;

        // Guru selalu dari sesi asli (guru tidak berubah meski murid di-override)
        $original->loadMissing('teacher');

        // Cek konflik guru — satu guru tidak boleh dua sesi overlap waktu
        $teacherConflict = ClassSession::where('teacher_id', $original->teacher_id)
            ->whereDate('session_date', $date)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTimeFull)
            ->whereNotIn('status', ClassSession::statusesExcludedFromScheduleConflict())
            ->where('id', '!=', $original->id)
            ->first();

        if ($teacherConflict) {
            $namaGuru   = $original->teacher->name;
            $jamMulai   = substr($teacherConflict->start_time, 0, 5);
            $jamSelesai = substr($teacherConflict->end_time, 0, 5);
            throw new InvalidArgumentException(
                "Guru {$namaGuru} sudah ada sesi lain pada {$date} {$jamMulai}–{$jamSelesai}"
            );
        }

        // Cek konflik ruangan — skip jika ruangan tidak dipilih
        if ($roomId !== null) {
            $roomConflict = ClassSession::where('room_id', $roomId)
                ->whereDate('session_date', $date)
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTimeFull)
                ->whereNotIn('status', ClassSession::statusesExcludedFromScheduleConflict())
                ->where('id', '!=', $original->id)
                ->first();

            if ($roomConflict) {
                $room       = Room::find($roomId);
                $jamMulai   = substr($roomConflict->start_time, 0, 5);
                $jamSelesai = substr($roomConflict->end_time, 0, 5);
                throw new InvalidArgumentException(
                    "Ruangan {$room->code} sudah dipakai pada {$date} {$jamMulai}–{$jamSelesai}"
                );
            }
        }

        // Buat sesi pengganti (ad-hoc — schedule_id null)
        $replacement = ClassSession::create([
            'schedule_id'           => null,
            'enrollment_id'         => $enrollmentId,
            'student_id'            => $studentId,
            'teacher_id'            => $original->teacher_id,
            'substitute_teacher_id' => null,
            'session_date'          => $date,
            'start_time'            => $startTimeFull,
            'end_time'              => $endTime,
            'room_id'               => $roomId,
            'status'                => ClassSession::STATUS_SCHEDULED,
            'honor_code'            => null,
            'honor_amount'          => null,
            'notes'                 => "Sesi pengganti dari " . \Carbon\Carbon::parse($original->session_date)->format('d/m/Y'),
            'session_sequence'      => $original->session_sequence,  // mewarisi dari sesi asli
            'origin_session_id'     => $original->id,                // referensi ke sesi asli
        ]);

        // Update notes sesi asli dengan referensi tanggal pengganti
        // (hanya untuk pengganti murid asli — override tidak mengubah notes sesi asli)
        if ($overrideEnrollment === null) {
            $original->update([
                'notes' => "Sesi pengganti: {$date} " . substr($startTime, 0, 5),
            ]);
        }

        return $replacement;
    }

    /**
     * Buat satu bagian dari split reschedule (½ durasi paket).
     *
     * Dipanggil oleh AbsensiController::storeSplitPart().
     * Original harus sudah IZIN_RESCHEDULE sebelum method ini dipanggil.
     *
     * @param  ClassSession  $original  Sesi asli (status IZIN_RESCHEDULE)
     * @param  string        $date      Format Y-m-d
     * @param  string        $startTime Format H:i
     * @param  int|null      $roomId
     * @param  int           $part      1 atau 2
     *
     * @throws InvalidArgumentException Jika ada konflik guru atau ruangan
     */
    public function createSplitPart(
        ClassSession $original,
        string $date,
        string $startTime,
        ?int $roomId,
        int $part
    ): ClassSession {
        $original->loadMissing(['enrollment.package', 'teacher']);

        // Durasi split = setengah durasi paket (30 menit → 15 menit)
        $durationMin   = (int) ceil($original->enrollment->package->duration_min / 2);
        $endTime       = Carbon::createFromFormat('H:i', $startTime)->addMinutes($durationMin)->format('H:i:s');
        $startTimeFull = $startTime . ':00';

        // Cek konflik guru — satu guru tidak boleh dua sesi overlap waktu
        $teacherConflict = ClassSession::where('teacher_id', $original->teacher_id)
            ->whereDate('session_date', $date)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTimeFull)
            ->whereNotIn('status', ClassSession::statusesExcludedFromScheduleConflict())
            ->where('id', '!=', $original->id)
            ->first();

        if ($teacherConflict) {
            $namaGuru   = $original->teacher->name;
            $jamMulai   = substr($teacherConflict->start_time, 0, 5);
            $jamSelesai = substr($teacherConflict->end_time, 0, 5);
            throw new InvalidArgumentException(
                "Guru {$namaGuru} sudah ada sesi lain pada {$date} {$jamMulai}–{$jamSelesai}"
            );
        }

        // Cek konflik ruangan — skip jika tidak dipilih
        if ($roomId !== null) {
            $roomConflict = ClassSession::where('room_id', $roomId)
                ->whereDate('session_date', $date)
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTimeFull)
                ->whereNotIn('status', ClassSession::statusesExcludedFromScheduleConflict())
                ->where('id', '!=', $original->id)
                ->first();

            if ($roomConflict) {
                $room       = Room::find($roomId);
                $jamMulai   = substr($roomConflict->start_time, 0, 5);
                $jamSelesai = substr($roomConflict->end_time, 0, 5);
                throw new InvalidArgumentException(
                    "Ruangan {$room->code} sudah dipakai pada {$date} {$jamMulai}–{$jamSelesai}"
                );
            }
        }

        // Honor = setengah honor normal satu sesi (DUO pakai rate dari PayrollConfig)
        $package = $original->enrollment->package;
        if ($package?->isDuo()) {
            $duoRate     = (int) (PayrollConfig::where('scenario_code', 'H_DUO')->value('value_or_formula') ?? 40000);
            $honorAmount = (int) round($duoRate / 2);
        } else {
            $honorAmount = (int) round($package->price_per_month * 0.5 / 4 / 2);
        }

        return ClassSession::create([
            'schedule_id'           => null,
            'enrollment_id'         => $original->enrollment_id,
            'student_id'            => $original->student_id,
            'teacher_id'            => $original->teacher_id,
            'substitute_teacher_id' => null,
            'session_date'          => $date,
            'start_time'            => $startTimeFull,
            'end_time'              => $endTime,
            'room_id'               => $roomId,
            'status'                => ClassSession::STATUS_SCHEDULED,
            'honor_code'            => 'H_SPLIT',
            'honor_amount'          => $honorAmount,
            'notes'                 => "Split bagian {$part}/2 dari sesi " . Carbon::parse($original->session_date)->format('d/m/Y'),
            'session_sequence'      => $original->session_sequence,
            'origin_session_id'     => $original->id,
            'split_part'            => $part,
        ]);
    }
}
