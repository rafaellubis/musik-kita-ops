<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;
use App\Models\Room;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\SessionGeneratorService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * List sesi & trigger generator manual (M03 UI).
 *
 * Default filter: bulan ini. Admin tinggal ubah year/month untuk
 * lihat bulan lain. Generator manual cuma di-expose ke Owner+Admin.
 */
class SessionController extends Controller
{
    /**
     * List sesi dengan filter. Read-only (Auditor juga boleh).
     */
    public function index(Request $request)
    {
        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);

        $query = ClassSession::query()
            ->with(['student', 'teacher', 'substituteTeacher', 'room'])
            ->inMonth($year, $month);

        if ($request->filled('teacher_id')) {
            $query->forTeacher((int) $request->teacher_id);
        }
        if ($request->filled('room_id')) {
            $query->where('room_id', $request->room_id);
        }
        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $sessions = $query
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->paginate(50)
            ->withQueryString();

        // Stats per status untuk bulan terpilih (tanpa filter lain)
        $stats = ClassSession::query()
            ->inMonth($year, $month)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $teachers = Teacher::where('is_active', true)->orderBy('name')->get();
        $rooms = Room::where('is_active', true)->orderBy('code')->get();

        // Untuk dropdown student di filter (cuma yang punya sesi di bulan ini)
        $studentIds = ClassSession::inMonth($year, $month)
            ->distinct()
            ->pluck('student_id');
        $students = Student::whereIn('id', $studentIds)
            ->orderBy('full_name')
            ->get(['id', 'student_code', 'full_name']);

        return view('sessions.index', compact(
            'sessions', 'stats', 'teachers', 'rooms', 'students',
            'year', 'month'
        ));
    }

    /**
     * Trigger generator manual untuk bulan tertentu. Owner+Admin only.
     */
    public function generate(Request $request, SessionGeneratorService $generator)
    {
        $data = $request->validate([
            'year'  => 'required|integer|min:2024|max:2030',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $report = $generator->generateForMonth($data['year'], $data['month']);

        $monthName = Carbon::create($data['year'], $data['month'], 1)->format('F Y');
        $msg = "Generator selesai untuk {$monthName}: " .
               "{$report['created']} sesi baru " .
               "({$report['skipped_libur']} LIBUR), " .
               "{$report['skipped_exists']} sudah ada (skip).";

        return redirect()->route('sessions.index', [
            'year' => $data['year'],
            'month' => $data['month'],
        ])->with('success', $msg);
    }

    /**
     * Update jam, guru, atau ruang satu sesi. Owner+Admin only.
     * Conflict detection: guru dan ruang tidak boleh double-booked di jam yang sama.
     */
    public function update(
        \App\Http\Requests\UpdateSessionRequest $request,
        ClassSession $classSession
    ): \Illuminate\Http\RedirectResponse {
        $data      = $request->validated();
        $startTime = $data['start_time'] . ':00';
        $endTime   = $data['end_time'] . ':00';

        // Deteksi konflik guru (abaikan CANCELLED dan sesi itu sendiri)
        $teacherConflict = ClassSession::where('session_date', $classSession->session_date)
            ->where('teacher_id', $data['teacher_id'])
            ->where('status', '!=', 'CANCELLED')
            ->where('id', '!=', $classSession->id)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->exists();

        if ($teacherConflict) {
            return back()
                ->withErrors(['teacher_id' => 'Guru sudah punya sesi lain di jam yang sama.'])
                ->withInput();
        }

        // Deteksi konflik ruang (hanya jika ruang dipilih)
        if (!empty($data['room_id'])) {
            $roomConflict = ClassSession::where('session_date', $classSession->session_date)
                ->where('room_id', $data['room_id'])
                ->where('status', '!=', 'CANCELLED')
                ->where('id', '!=', $classSession->id)
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime)
                ->exists();

            if ($roomConflict) {
                return back()
                    ->withErrors(['room_id' => 'Ruang sudah dipakai sesi lain di jam yang sama.'])
                    ->withInput();
            }
        }

        $classSession->update([
            'start_time' => $startTime,
            'end_time'   => $endTime,
            'teacher_id' => $data['teacher_id'],
            'room_id'    => $data['room_id'] ?? null,
        ]);

        return back()->with('success', 'Sesi berhasil diperbarui.');
    }
}
