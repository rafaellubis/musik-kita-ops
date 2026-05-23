<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAbsensiRequest;
use App\Models\ClassSession;
use App\Models\Room;
use App\Models\Teacher;
use App\Services\AttendanceService;
use App\Services\RescheduleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Absensi Harian (M04) — tampilan per-hari.
 *
 * Menampilkan SEMUA sesi pada satu tanggal sekaligus
 * agar Admin bisa input absensi dalam satu layar (M04 daily view).
 *
 * Dua endpoint:
 *   GET  /absensi              -> daftar sesi hari ini (filter by tanggal)
 *   PATCH /absensi/{session}   -> update satu sesi via AJAX inline
 */
class AbsensiController extends Controller
{
    public function __construct(
        private RescheduleService $rescheduleService,
        private AttendanceService $attendanceService,
    ) {}

    /**
     * Tampilkan halaman absensi harian.
     * Default: hari ini. Bisa difilter via query ?date=YYYY-MM-DD
     */
    public function index(Request $request): View
    {
        $tanggal = $request->date
            ? Carbon::parse($request->date)->toDateString()
            : today()->toDateString();

        $sessions = ClassSession::with(['student', 'teacher', 'substituteTeacher', 'room'])
            ->whereDate('session_date', $tanggal)
            ->orderBy('start_time')
            ->get();

        $teachers = Teacher::where('is_active', true)->orderBy('name')->get();
        $rooms    = Room::where('is_active', true)->orderBy('code')->get();

        return view('absensi.index', [
            'sessions'   => $sessions,
            'teachers'   => $teachers,
            'rooms'      => $rooms,
            'tanggal'    => $tanggal,
            'tanggalObj' => Carbon::parse($tanggal),
        ]);
    }

    /**
     * Update status absensi satu sesi (AJAX inline).
     *
     * Business rules:
     * - LIBUR tidak bisa diubah (BR-4.10)
     * - IZIN_RESCHEDULE: buat sesi pengganti via RescheduleService
     *   Jika ada konflik guru/ruangan → rollback via DB transaction, return 422
     */
    public function update(UpdateAbsensiRequest $request, ClassSession $classSession): JsonResponse
    {
        if ($classSession->status === ClassSession::STATUS_LIBUR) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi libur nasional tidak bisa diubah.',
            ], 403);
        }

        try {
            DB::transaction(function () use ($request, $classSession) {
                // AttendanceService menghitung honor_code + honor_amount berdasarkan status & paket
                $this->attendanceService->recordAttendance($classSession, [
                    'status'                => $request->status,
                    'late_minutes'          => $request->late_minutes,
                    'substitute_teacher_id' => $request->substitute_teacher_id,
                    'notes'                 => $request->notes,
                    '__session'             => $classSession,
                ]);

                if ($request->status === ClassSession::STATUS_IZIN_RESCHEDULE) {
                    $this->rescheduleService->createReplacement(
                        $classSession,
                        $request->replacement_date,
                        $request->replacement_time,
                        $request->replacement_room_id,
                    );
                }
            });
        } catch (\InvalidArgumentException $e) {
            // Konflik guru atau ruangan — transaction di-rollback otomatis
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $classSession->refresh()->load('substituteTeacher');

        return response()->json([
            'success'                 => true,
            'session_id'              => $classSession->id,
            'status'                  => $classSession->status,
            'late_minutes'            => $classSession->late_minutes,
            'substitute_teacher_name' => $classSession->substituteTeacher?->name,
            // notes berisi "Sesi pengganti: 2026-06-05 14:00" untuk IZIN_RESCHEDULE
            'replacement_label'       => $classSession->status === ClassSession::STATUS_IZIN_RESCHEDULE
                                            ? str_replace('Sesi pengganti: ', '', $classSession->notes ?? '')
                                            : null,
        ]);
    }
}
