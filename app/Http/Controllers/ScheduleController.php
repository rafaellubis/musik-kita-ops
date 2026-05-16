<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Services\ScheduleConflictDetector;
use Illuminate\Http\Request;

/**
 * Schedule mingguan tetap (M03).
 *
 * Pendekatan UI: schedule selalu dikelola dari halaman students/show.
 * 1 student punya 1 enrollment ACTIVE punya 1+ schedule. Saat create,
 * controller cari enrollment ACTIVE murid lalu attach schedule.
 */
class ScheduleController extends Controller
{
    public function __construct(
        private readonly ScheduleConflictDetector $conflictDetector,
    ) {}

    /**
     * Bikin schedule baru untuk enrollment ACTIVE murid.
     */
    public function store(Request $request, Student $student)
    {
        $data = $request->validate([
            'day_of_week' => 'required|integer|min:0|max:6',
            'start_time'  => 'required|date_format:H:i',
            'end_time'    => 'required|date_format:H:i|after:start_time',
            'room_id'     => 'nullable|exists:rooms,id',
            'notes'       => 'nullable|string|max:500',
        ], [
            'day_of_week.required' => 'Hari wajib dipilih.',
            'start_time.required'  => 'Jam mulai wajib diisi.',
            'end_time.after'       => 'Jam selesai harus setelah jam mulai.',
        ]);

        // Cari enrollment ACTIVE murid
        $enrollment = $student->enrollments()->active()->latest()->first();
        if (!$enrollment) {
            return back()->with('error',
                'Murid belum punya enrollment aktif. Jadikan murid Aktif dulu lewat lifecycle action.');
        }

        // Validasi konflik guru
        $teacherClashes = $this->conflictDetector->findTeacherConflicts(
            teacherId: $enrollment->teacher_id,
            dayOfWeek: $data['day_of_week'],
            startTime: $data['start_time'],
            endTime:   $data['end_time'],
        );
        if ($teacherClashes->isNotEmpty()) {
            $names = $teacherClashes->map(fn ($s) => $s->enrollment->student->full_name ?? '?')
                                    ->implode(', ');
            return back()->withInput()->with('error',
                "Bentrok jadwal guru di slot tsb. Sudah dipakai untuk: {$names}.");
        }

        // Validasi kapasitas ruangan (kalau room dipilih)
        if (!empty($data['room_id'])) {
            $isFull = $this->conflictDetector->isRoomFull(
                roomId:    (int) $data['room_id'],
                dayOfWeek: $data['day_of_week'],
                startTime: $data['start_time'],
                endTime:   $data['end_time'],
            );
            if ($isFull) {
                return back()->withInput()->with('error',
                    'Kapasitas ruangan sudah penuh di slot ini.');
            }
        }

        // Validasi: ruangan harus support instrumen murid
        if (!empty($data['room_id'])) {
            $room = Room::findOrFail($data['room_id']);
            $instrumentName = $enrollment->package?->instrument?->name;

            if ($instrumentName && !$room->supportsInstrument($instrumentName)) {
                return back()->withInput()->with('error',
                    "Ruangan [{$room->code}] {$room->name} tidak mendukung instrumen {$instrumentName}. " .
                    "Pilih ruangan lain atau kosongkan field ruangan."
                );
            }
        }

        Schedule::create([
            'enrollment_id' => $enrollment->id,
            'day_of_week'   => $data['day_of_week'],
            'start_time'    => $data['start_time'],
            'end_time'      => $data['end_time'],
            'room_id'       => $data['room_id'] ?? null,
            'notes'         => $data['notes'] ?? null,
            'is_active'     => true,
        ]);

        return back()->with('success', 'Jadwal mingguan berhasil ditambahkan.');
    }

    public function update(Request $request, Schedule $schedule)
    {
        $data = $request->validate([
            'day_of_week' => 'required|integer|min:0|max:6',
            'start_time'  => 'required|date_format:H:i',
            'end_time'    => 'required|date_format:H:i|after:start_time',
            'room_id'     => 'nullable|exists:rooms,id',
            'notes'       => 'nullable|string|max:500',
        ]);

        $teacherId = $schedule->enrollment->teacher_id;

        // Validasi konflik guru, kecualikan schedule ini sendiri
        $teacherClashes = $this->conflictDetector->findTeacherConflicts(
            teacherId: $teacherId,
            dayOfWeek: $data['day_of_week'],
            startTime: $data['start_time'],
            endTime:   $data['end_time'],
            excludeScheduleId: $schedule->id,
        );
        if ($teacherClashes->isNotEmpty()) {
            $names = $teacherClashes->map(fn ($s) => $s->enrollment->student->full_name ?? '?')
                                    ->implode(', ');
            return back()->withInput()->with('error',
                "Bentrok jadwal guru di slot tsb. Sudah dipakai untuk: {$names}.");
        }

        if (!empty($data['room_id'])) {
            $isFull = $this->conflictDetector->isRoomFull(
                roomId:    (int) $data['room_id'],
                dayOfWeek: $data['day_of_week'],
                startTime: $data['start_time'],
                endTime:   $data['end_time'],
                excludeScheduleId: $schedule->id,
            );
            if ($isFull) {
                return back()->withInput()->with('error',
                    'Kapasitas ruangan sudah penuh di slot ini.');
            }
        }

        // Validasi: ruangan harus support instrumen murid (saat update)
        if (!empty($data['room_id'])) {
            $room = Room::findOrFail($data['room_id']);
            $instrumentName = $schedule->enrollment?->package?->instrument?->name;

            if ($instrumentName && !$room->supportsInstrument($instrumentName)) {
                return back()->withInput()->with('error',
                    "Ruangan [{$room->code}] {$room->name} tidak mendukung instrumen {$instrumentName}. " .
                    "Pilih ruangan lain atau kosongkan field ruangan."
                );
            }
        }

        $schedule->update($data);

        return back()->with('success', 'Jadwal mingguan berhasil diperbarui.');
    }

    public function destroy(Schedule $schedule)
    {
        // CATATAN: hanya boleh hapus kalau belum ada sesi ter-generate.
        // Kalau sudah ada, lebih aman set is_active=false (toggle).
        if ($schedule->classSessions()->exists()) {
            return back()->with('error',
                'Jadwal sudah punya sesi. Pakai toggle nonaktif daripada hapus.');
        }

        $schedule->delete();
        return back()->with('success', 'Jadwal mingguan dihapus.');
    }

    /**
     * Toggle is_active. Sesi yang sudah ter-generate TIDAK dihapus —
     * generator hanya skip pembuatan sesi BARU saat is_active=false.
     */
    public function toggleActive(Schedule $schedule)
    {
        $schedule->update(['is_active' => !$schedule->is_active]);

        $msg = $schedule->is_active
            ? 'Jadwal diaktifkan kembali.'
            : 'Jadwal dinonaktifkan. Sesi yang sudah ada tetap, tapi sesi baru tidak akan di-generate.';

        return back()->with('success', $msg);
    }
}
