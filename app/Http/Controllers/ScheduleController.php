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
     * enrollment_id wajib dikirim dari form agar multi-enrollment tertangani dengan benar.
     */
    public function store(Request $request, Student $student)
    {
        $data = $request->validate([
            'enrollment_id' => 'required|integer|exists:enrollments,id',
            'day_of_week'   => 'required|integer|min:0|max:6',
            'start_time'    => 'required|date_format:H:i',
            'end_time'      => 'required|date_format:H:i|after:start_time',
            'room_id'       => 'nullable|exists:rooms,id',
            'notes'         => 'nullable|string|max:500',
        ], [
            'enrollment_id.required' => 'Enrollment wajib dipilih.',
            'day_of_week.required'   => 'Hari wajib dipilih.',
            'start_time.required'    => 'Jam mulai wajib diisi.',
            'end_time.after'         => 'Jam selesai harus setelah jam mulai.',
        ]);

        // Cari enrollment dari request, pastikan milik student ini
        $enrollment = $student->enrollments()->active()->find($data['enrollment_id']);
        if (!$enrollment) {
            abort(403, 'Enrollment ini tidak ditemukan atau bukan milik murid ini.');
        }

        $package = $enrollment->package;
        $isDuo   = $package?->isDuo() ?? false;

        // Validasi konflik guru — DUO boleh berbagi slot jika partner juga DUO (maks 2)
        $teacherClashes = $this->conflictDetector->findTeacherConflicts(
            teacherId: $enrollment->teacher_id,
            dayOfWeek: $data['day_of_week'],
            startTime: $data['start_time'],
            endTime:   $data['end_time'],
        );

        if ($isDuo) {
            $nonDuoClashes = $teacherClashes->filter(
                fn ($s) => $s->enrollment?->package?->class_type !== 'DUO'
            );
            $duoClashes = $teacherClashes->filter(
                fn ($s) => $s->enrollment?->package?->class_type === 'DUO'
            );

            if ($nonDuoClashes->isNotEmpty()) {
                $names = $nonDuoClashes->map(fn ($s) => $s->enrollment->student->full_name ?? '?')
                                       ->implode(', ');
                return back()->withInput()->with('error',
                    "Bentrok jadwal guru di slot tsb. Sudah dipakai untuk: {$names}.");
            }
            if ($duoClashes->count() >= 2) {
                return back()->withInput()->with('error',
                    'Slot DUO sudah penuh (maksimal 2 murid per slot).');
            }
        } else {
            $blockingClashes = $this->conflictDetector->findBlockingTeacherConflicts(
                teacherId: $enrollment->teacher_id,
                dayOfWeek: $data['day_of_week'],
                startTime: $data['start_time'],
                endTime:   $data['end_time'],
                newClassType: $package?->class_type ?? '',
            );

            if ($blockingClashes->isNotEmpty()) {
                $names = $blockingClashes->map(fn ($s) => $s->enrollment->student->full_name ?? '?')
                                         ->implode(', ');
                return back()->withInput()->with('error',
                    "Bentrok jadwal guru di slot tsb. Sudah dipakai untuk: {$names}.");
            }
        }

        // Validasi kapasitas ruangan — DUO boleh berbagi ruang dengan partner DUO (maks 2)
        if (!empty($data['room_id'])) {
            if ($isDuo) {
                $roomConflicts = $this->conflictDetector->findRoomConflicts(
                    roomId:    (int) $data['room_id'],
                    dayOfWeek: $data['day_of_week'],
                    startTime: $data['start_time'],
                    endTime:   $data['end_time'],
                );
                $nonDuoRoom = $roomConflicts->filter(
                    fn ($s) => $s->enrollment?->package?->class_type !== 'DUO'
                );
                $duoRoom = $roomConflicts->filter(
                    fn ($s) => $s->enrollment?->package?->class_type === 'DUO'
                );

                if ($nonDuoRoom->isNotEmpty() || $duoRoom->count() >= 2) {
                    return back()->withInput()->with('error',
                        'Ruangan tidak tersedia di slot ini untuk DUO.');
                }
            } else {
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

    public function update(Request $request, Student $student, Schedule $schedule)
    {
        abort_unless(
            $schedule->enrollment->student_id === $student->id,
            403,
            'Jadwal tidak ditemukan untuk murid ini.'
        );

        $data = $request->validate([
            'day_of_week' => 'required|integer|min:0|max:6',
            'start_time'  => 'required|date_format:H:i',
            'end_time'    => 'required|date_format:H:i|after:start_time',
            'room_id'     => 'nullable|exists:rooms,id',
            'notes'       => 'nullable|string|max:500',
        ]);

        $teacherId = $schedule->enrollment->teacher_id;
        $package   = $schedule->enrollment?->package;
        $isDuo     = $package?->isDuo() ?? false;

        // Validasi konflik guru — DUO boleh berbagi slot jika partner juga DUO (maks 2)
        // Kecualikan schedule ini sendiri (saat update)
        $teacherClashes = $this->conflictDetector->findTeacherConflicts(
            teacherId: $teacherId,
            dayOfWeek: $data['day_of_week'],
            startTime: $data['start_time'],
            endTime:   $data['end_time'],
            excludeScheduleId: $schedule->id,
        );

        if ($isDuo) {
            $nonDuoClashes = $teacherClashes->filter(
                fn ($s) => $s->enrollment?->package?->class_type !== 'DUO'
            );
            $duoClashes = $teacherClashes->filter(
                fn ($s) => $s->enrollment?->package?->class_type === 'DUO'
            );

            if ($nonDuoClashes->isNotEmpty()) {
                $names = $nonDuoClashes->map(fn ($s) => $s->enrollment->student->full_name ?? '?')
                                       ->implode(', ');
                return back()->withInput()->with('error',
                    "Bentrok jadwal guru di slot tsb. Sudah dipakai untuk: {$names}.");
            }
            if ($duoClashes->count() >= 2) {
                return back()->withInput()->with('error',
                    'Slot DUO sudah penuh (maksimal 2 murid per slot).');
            }
        } else {
            $blockingClashes = $this->conflictDetector->findBlockingTeacherConflicts(
                teacherId: $teacherId,
                dayOfWeek: $data['day_of_week'],
                startTime: $data['start_time'],
                endTime:   $data['end_time'],
                newClassType: $package?->class_type ?? '',
                excludeScheduleId: $schedule->id,
            );

            if ($blockingClashes->isNotEmpty()) {
                $names = $blockingClashes->map(fn ($s) => $s->enrollment->student->full_name ?? '?')
                                         ->implode(', ');
                return back()->withInput()->with('error',
                    "Bentrok jadwal guru di slot tsb. Sudah dipakai untuk: {$names}.");
            }
        }

        if (!empty($data['room_id'])) {
            if ($isDuo) {
                $roomConflicts = $this->conflictDetector->findRoomConflicts(
                    roomId:    (int) $data['room_id'],
                    dayOfWeek: $data['day_of_week'],
                    startTime: $data['start_time'],
                    endTime:   $data['end_time'],
                    excludeScheduleId: $schedule->id,
                );
                $nonDuoRoom = $roomConflicts->filter(
                    fn ($s) => $s->enrollment?->package?->class_type !== 'DUO'
                );
                $duoRoom = $roomConflicts->filter(
                    fn ($s) => $s->enrollment?->package?->class_type === 'DUO'
                );

                if ($nonDuoRoom->isNotEmpty() || $duoRoom->count() >= 2) {
                    return back()->withInput()->with('error',
                        'Ruangan tidak tersedia di slot ini untuk DUO.');
                }
            } else {
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

    public function destroy(Student $student, Schedule $schedule)
    {
        abort_unless(
            $schedule->enrollment->student_id === $student->id,
            403,
            'Jadwal tidak ditemukan untuk murid ini.'
        );

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
    public function toggleActive(Student $student, Schedule $schedule)
    {
        abort_unless(
            $schedule->enrollment->student_id === $student->id,
            403,
            'Jadwal tidak ditemukan untuk murid ini.'
        );

        $schedule->update(['is_active' => !$schedule->is_active]);

        $msg = $schedule->is_active
            ? 'Jadwal diaktifkan kembali.'
            : 'Jadwal dinonaktifkan. Sesi yang sudah ada tetap, tapi sesi baru tidak akan di-generate.';

        return back()->with('success', $msg);
    }
}
