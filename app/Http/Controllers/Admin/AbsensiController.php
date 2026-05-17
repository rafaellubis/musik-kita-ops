<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAbsensiRequest;
use App\Models\ClassSession;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Absensi Harian (M04) — tampilan per-hari.
 *
 * Berbeda dari AttendanceController (form edit per sesi individual),
 * controller ini menampilkan SEMUA sesi pada satu tanggal sekaligus
 * agar Admin bisa input absensi dalam satu layar (M04 daily view).
 *
 * Dua endpoint:
 *   GET  /admin/absensi              -> daftar sesi hari ini (filter by tanggal)
 *   PATCH /admin/absensi/{session}   -> update satu sesi via AJAX inline
 */
class AbsensiController extends Controller
{
    /**
     * Tampilkan halaman absensi harian.
     * Default: hari ini. Bisa difilter via query ?date=YYYY-MM-DD
     */
    public function index(Request $request): View
    {
        // Parse tanggal dari query parameter, default hari ini
        $tanggal = $request->date
            ? Carbon::parse($request->date)->toDateString()
            : today()->toDateString();

        // Query sesi pada tanggal terpilih, eager-load relasi untuk performa
        $sessions = ClassSession::with(['student', 'teacher', 'substituteTeacher', 'room'])
            ->whereDate('session_date', $tanggal)
            ->orderBy('start_time')
            ->get();

        // Query guru aktif untuk dropdown pengganti (M04 Task 3 nanti)
        $teachers = Teacher::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.absensi.index', [
            'sessions'   => $sessions,
            'teachers'   => $teachers,
            'tanggal'    => $tanggal,
            'tanggalObj' => Carbon::parse($tanggal),
        ]);
    }

    /**
     * Update status absensi satu sesi (AJAX inline).
     * Implementasi penuh di Task 4.
     */
    public function update(UpdateAbsensiRequest $request, ClassSession $classSession): JsonResponse
    {
        // Stub — implementasi di Task 4
        return response()->json(['success' => true]);
    }
}
