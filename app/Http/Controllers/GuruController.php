<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;
use App\Models\HonorSlip;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * GuruController — portal self-service untuk guru yang login.
 *
 * Guru hanya bisa melihat sesi dan slip honor milik dirinya sendiri.
 * Satu-satunya aksi tulis yang diperbolehkan: update status absensi
 * sesi hari ini (HADIR / HADIR_TERLAMBAT).
 *
 * CATATAN KEAMANAN:
 * - Semua method wajib verifikasi $teacher via auth()->user()->teacher
 * - abort_if(!$teacher) mencegah user tanpa data guru mengakses portal
 * - updateAbsensi membatasi status ke HADIR/HADIR_TERLAMBAT saja
 *   (Admin tetap bisa set status lain via AbsensiController)
 */
class GuruController extends Controller
{
    /**
     * AttendanceService diinjeksi via constructor agar honor_code dan
     * honor_amount ikut terset saat guru submit absensi (Issue 1 fix).
     */
    public function __construct(
        private readonly AttendanceService $attendanceService,
    ) {}

    /**
     * Dashboard guru: sesi hari ini + ringkasan bulan berjalan.
     */
    public function dashboard()
    {
        $teacher = auth()->user()->teacher;
        abort_if(!$teacher, 403, 'Akun ini tidak terhubung ke data guru.');

        $today = today()->toDateString();

        // Sesi hari ini — sebagai guru asli atau guru pengganti
        $sesiHariIni = ClassSession::where(function ($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id)
                  ->orWhere('substitute_teacher_id', $teacher->id);
            })
            ->where('session_date', $today)
            ->whereNotIn('status', ['CANCELLED'])
            ->with(['student', 'room', 'enrollment.package'])
            ->orderBy('start_time')
            ->get();

        $startBulan = now()->startOfMonth()->toDateString();
        $endBulan   = now()->endOfMonth()->toDateString();

        // Total sesi terlaksana bulan ini (tidak hitung CANCELLED, LIBUR, atau belum terjadi)
        $totalSesiBulan = ClassSession::where(function ($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id)
                  ->orWhere('substitute_teacher_id', $teacher->id);
            })
            ->whereBetween('session_date', [$startBulan, $endBulan])
            ->whereNotIn('status', ['CANCELLED', 'LIBUR', 'SCHEDULED'])
            ->count();

        // Slip honor bulan ini (jika sudah dikalkulasi atau dibayar)
        $slipBulanIni = HonorSlip::where('teacher_id', $teacher->id)
            ->whereIn('status', ['CALCULATED', 'PAID'])
            ->where('month', now()->month)
            ->where('year', now()->year)
            ->first();

        return view('guru.dashboard', compact('teacher', 'sesiHariIni', 'totalSesiBulan', 'slipBulanIni'));
    }

    /**
     * Jadwal guru: sesi minggu ini + minggu depan.
     */
    public function jadwal()
    {
        $teacher = auth()->user()->teacher;
        abort_if(!$teacher, 403);

        // Tampilkan minggu berjalan (Senin s/d Minggu) + minggu depan
        $mulai = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $akhir = now()->addWeek()->endOfWeek(Carbon::SUNDAY)->toDateString();

        $sesi = ClassSession::where(function ($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id)
                  ->orWhere('substitute_teacher_id', $teacher->id);
            })
            ->whereBetween('session_date', [$mulai, $akhir])
            ->with(['student', 'room', 'enrollment.package'])
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();

        $today = today()->toDateString();

        return view('guru.jadwal', compact('teacher', 'sesi', 'today', 'mulai', 'akhir'));
    }

    /**
     * Daftar slip honor guru — hanya status CALCULATED atau PAID yang tampil.
     * Slip DRAFT tidak ditampilkan ke guru (belum final).
     */
    public function honor()
    {
        $teacher = auth()->user()->teacher;
        abort_if(!$teacher, 403);

        $slips = HonorSlip::where('teacher_id', $teacher->id)
            ->whereIn('status', ['CALCULATED', 'PAID'])
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();

        return view('guru.honor', compact('teacher', 'slips'));
    }

    /**
     * Detail satu slip honor beserta rincian sesi per murid.
     * Guru hanya bisa lihat slip miliknya sendiri dan yang sudah final.
     */
    public function honorShow(HonorSlip $honorSlip)
    {
        $teacher = auth()->user()->teacher;
        abort_if(!$teacher, 403);
        abort_if($honorSlip->teacher_id !== $teacher->id, 403, 'Bukan slip honor Anda.');
        abort_if(!in_array($honorSlip->status, ['CALCULATED', 'PAID']), 403, 'Slip honor belum tersedia.');

        // Sesi di bulan slip yang punya honor_code (sudah dikalkulasi)
        $sesi = ClassSession::where(function ($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id)
                  ->orWhere('substitute_teacher_id', $teacher->id);
            })
            ->whereYear('session_date', $honorSlip->year)
            ->whereMonth('session_date', $honorSlip->month)
            ->whereNotNull('honor_code')
            ->with(['student', 'room'])
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();

        return view('guru.honor-show', compact('teacher', 'honorSlip', 'sesi'));
    }

    /**
     * Guru update status absensi sesi miliknya — hanya HADIR atau HADIR_TERLAMBAT.
     *
     * Batasan:
     * - Hanya sesi yang teacher_id atau substitute_teacher_id = guru ini
     * - Hanya sesi hari ini (tidak boleh update sesi lampau/mendatang)
     * - Status dibatasi ke HADIR/HADIR_TERLAMBAT (Admin set status lain via AbsensiController)
     */
    public function profil()
    {
        $teacher = auth()->user()->teacher;
        abort_if(!$teacher, 403);

        return view('guru.profil');
    }

    public function updateAbsensi(Request $request, ClassSession $classSession)
    {
        $teacher = auth()->user()->teacher;
        abort_if(!$teacher, 403);

        // Pastikan sesi ini memang milik guru yang sedang login
        abort_if(
            $classSession->teacher_id !== $teacher->id
            && $classSession->substitute_teacher_id !== $teacher->id,
            403,
            'Bukan sesi Anda.'
        );

        // Hanya sesi hari ini — session_date tidak di-cast, bandingkan sebagai string
        abort_if(
            $classSession->session_date !== today()->toDateString(),
            403,
            'Hanya sesi hari ini yang bisa diupdate.'
        );

        // Issue 3: sesi libur tidak bisa diubah oleh guru
        abort_if(
            $classSession->status === 'LIBUR',
            403,
            'Sesi libur tidak bisa diupdate.'
        );

        // Issue 2: absensi yang sudah tercatat tidak bisa disubmit ulang — minta koreksi via Admin
        abort_if(
            in_array($classSession->status, ['HADIR', 'HADIR_TERLAMBAT'], true),
            403,
            'Absensi sudah dicatat. Hubungi admin untuk koreksi.'
        );

        $validated = $request->validate([
            'status'       => ['required', Rule::in(['HADIR', 'HADIR_TERLAMBAT'])],
            // Issue 5: late_minutes wajib diisi jika status HADIR_TERLAMBAT
            'late_minutes' => [
                'nullable',
                'integer',
                'min:1',
                'max:60',
                Rule::requiredIf(fn () => $request->input('status') === 'HADIR_TERLAMBAT'),
            ],
        ], [
            'status.required'          => 'Status wajib diisi.',
            'status.in'                => 'Status hanya boleh HADIR atau HADIR TERLAMBAT.',
            'late_minutes.integer'     => 'Menit keterlambatan harus berupa angka.',
            'late_minutes.required'    => 'Menit keterlambatan wajib diisi jika terlambat.',
        ]);

        // Issue 1: gunakan AttendanceService agar honor_code dan honor_amount ikut terset
        $this->attendanceService->recordAttendance($classSession, [
            'status'       => $validated['status'],
            // late_minutes hanya relevan jika terlambat; kosongkan jika status HADIR biasa
            'late_minutes' => $validated['status'] === 'HADIR_TERLAMBAT'
                ? ($validated['late_minutes'] ?? null)
                : null,
        ]);

        return back()->with('success', 'Absensi berhasil disimpan.');
    }
}
