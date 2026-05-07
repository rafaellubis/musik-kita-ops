<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;
use App\Models\Teacher;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Input absensi per sesi (M04).
 *
 * Dua endpoint:
 *   GET  sessions/{session}/attendance  -> form (read-only data sesi + form input)
 *   POST sessions/{session}/attendance  -> submit
 *
 * Form ini ALWAYS overwrite absensi sebelumnya — admin bisa koreksi typo
 * dengan submit ulang.
 */
class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $service,
    ) {}

    /**
     * Tampilkan form input absensi.
     */
    public function edit(ClassSession $session)
    {
        $session->load([
            'student',
            'teacher',
            'substituteTeacher',
            'enrollment.package.instrument',
            'room',
        ]);

        // Daftar guru pengganti yang mengajar instrumen sama (matriks).
        // Exclude guru asli supaya tidak bisa pilih dirinya sendiri.
        $instrumentId = $session->enrollment?->package?->instrument_id;
        $substituteCandidates = Teacher::where('is_active', true)
            ->where('id', '!=', $session->teacher_id)
            ->when($instrumentId, function ($q) use ($instrumentId) {
                $q->whereHas('instruments', fn ($qi) => $qi->where('instruments.id', $instrumentId));
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return view('attendance.edit', compact('session', 'substituteCandidates'));
    }

    /**
     * Submit absensi.
     */
    public function update(Request $request, ClassSession $session)
    {
        $data = $request->validate([
            'status'                => 'required|in:HADIR,HADIR_TERLAMBAT,IZIN_RESCHEDULE,IZIN_VIDEO,HANGUS,LIBUR,DIGANTI',
            'late_minutes'          => 'nullable|integer|min:1|max:60|required_if:status,HADIR_TERLAMBAT',
            'substitute_teacher_id' => 'nullable|exists:teachers,id|required_if:status,DIGANTI',
            'notes'                 => 'nullable|string|max:500',
        ], [
            'status.required'                  => 'Status absensi wajib dipilih.',
            'status.in'                        => 'Status absensi tidak valid.',
            'late_minutes.required_if'         => 'Status terlambat wajib disertai jumlah menit.',
            'late_minutes.min'                 => 'Menit terlambat minimal 1.',
            'late_minutes.max'                 => 'Menit terlambat maksimal 60.',
            'substitute_teacher_id.required_if'=> 'Status DIGANTI wajib pilih guru pengganti.',
            'substitute_teacher_id.exists'     => 'Guru pengganti tidak valid.',
        ]);

        // Tambahan: guru pengganti tidak boleh sama dengan guru asli.
        if ($data['status'] === 'DIGANTI'
            && (int) $data['substitute_teacher_id'] === (int) $session->teacher_id) {
            return back()->withInput()
                ->with('error', 'Guru pengganti tidak boleh sama dengan guru asli.');
        }

        try {
            $this->service->recordAttendance($session, $data);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        // Redirect kembali ke list sesi yang sebulan dengan sesi tsb,
        // pertahankan filter via query string.
        return redirect()->route('sessions.index', [
            'year'  => $session->session_date->year,
            'month' => $session->session_date->month,
        ])->with('success', sprintf(
            'Absensi sesi %s (%s) tersimpan: %s.',
            $session->session_date->format('d M Y'),
            $session->student->full_name ?? '?',
            $data['status']
        ));
    }
}
