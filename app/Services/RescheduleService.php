<?php

namespace App\Services;

use App\Models\ClassSession;
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
     * @param  ClassSession  $original   Sesi asli (status sudah IZIN_RESCHEDULE)
     * @param  string        $date       Format Y-m-d (tanggal pengganti)
     * @param  string        $startTime  Format H:i (jam mulai pengganti)
     * @param  int|null      $roomId     ID ruangan pengganti, null = tanpa ruangan
     *
     * @throws InvalidArgumentException Jika ada konflik guru atau ruangan
     */
    public function createReplacement(
        ClassSession $original,
        string $date,
        string $startTime,
        ?int $roomId
    ): ClassSession {
        // Hitung jam selesai berdasarkan durasi paket enrollment
        $original->loadMissing(['enrollment.package', 'teacher']);

        $durationMin = $original->enrollment->package->duration_min;
        $endTime = Carbon::createFromFormat('H:i', $startTime)
            ->addMinutes($durationMin)
            ->format('H:i:s');

        $startTimeFull = $startTime . ':00';

        // Cek konflik guru — satu guru tidak boleh dua sesi overlap waktu
        $teacherConflict = ClassSession::where('teacher_id', $original->teacher_id)
            ->whereDate('session_date', $date)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTimeFull)
            ->where('status', '!=', ClassSession::STATUS_CANCELLED)
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
                ->where('status', '!=', ClassSession::STATUS_CANCELLED)
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
            'enrollment_id'         => $original->enrollment_id,
            'student_id'            => $original->student_id,
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
        $original->update([
            'notes' => "Sesi pengganti: {$date} " . substr($startTime, 0, 5),
        ]);

        return $replacement;
    }
}
