<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\HonorSlip;
use App\Models\ProgressReport;
use App\Models\ProgressReportItem;
use App\Models\ProgressReportSection;
use App\Models\SessionTeacherNote;
use App\Services\AttendanceService;
use App\Services\ReportTemplateResolverService;
use App\Services\SessionNoteSyncService;
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
 * - confirmSubstitute: hanya guru pengganti (substitute_teacher_id) yang boleh
 *   konfirmasi hadir/batal pada sesi DIGANTI (two-phase, sama seperti Admin)
 */
class GuruController extends Controller
{
    /**
     * AttendanceService diinjeksi via constructor agar honor_code dan
     * honor_amount ikut terset saat guru submit absensi (Issue 1 fix).
     */
    public function __construct(
        private readonly AttendanceService $attendanceService,
        private readonly ReportTemplateResolverService $reportTemplateResolver,
        private readonly SessionNoteSyncService $sessionNoteSyncService,
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
            ->with(['student', 'room', 'enrollment.package', 'teacher', 'substituteTeacher', 'teacherNote'])
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

        // Jumlah sesi IZIN_PENDING milik guru ini — untuk banner + counter di dashboard
        $jumlahPending = ClassSession::where('teacher_id', $teacher->id)
            ->where('status', ClassSession::STATUS_IZIN_PENDING)
            ->count();

        return view('guru.dashboard', compact(
            'teacher', 'sesiHariIni', 'totalSesiBulan', 'slipBulanIni', 'jumlahPending'
        ));
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
            ->with(['student', 'room', 'enrollment.package', 'teacher', 'substituteTeacher', 'teacherNote'])
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
     * Daftar sesi IZIN_PENDING milik guru yang login.
     * Diurutkan dari yang paling lama pending (terlama di atas).
     */
    public function sesiPending()
    {
        $teacher = auth()->user()->teacher;
        abort_if(!$teacher, 403, 'Akun ini tidak terhubung ke data guru.');

        $sesiPending = ClassSession::where('teacher_id', $teacher->id)
            ->where('status', ClassSession::STATUS_IZIN_PENDING)
            ->with(['student', 'enrollment.package'])
            ->orderBy('session_date')
            ->get();

        return view('guru.sesi-pending', compact('teacher', 'sesiPending'));
    }

    /**
     * Guru submit saran tanggal pengganti untuk sesi IZIN_PENDING miliknya.
     * Saran disimpan ke kolom notes dengan prefix [SARAN GURU: tgl jam — catatan].
     * Admin yang akan konfirmasi dan buat sesi penggantinya.
     */
    public function suggestDate(Request $request, ClassSession $session)
    {
        $teacher = auth()->user()->teacher;
        abort_if(!$teacher, 403);
        abort_if($session->teacher_id !== $teacher->id, 403, 'Bukan sesi Anda.');
        abort_if($session->status !== ClassSession::STATUS_IZIN_PENDING, 422, 'Sesi bukan IZIN_PENDING.');

        $request->validate([
            'tanggal' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:today'],
            'jam'     => ['required', 'date_format:H:i'],
            'catatan' => ['nullable', 'string', 'max:200'],
        ]);

        $saran = "[SARAN GURU: {$request->tanggal} {$request->jam}" .
                 ($request->catatan ? " — {$request->catatan}" : '') . ']';
        $notes = $session->notes ? $session->notes . "\n" . $saran : $saran;

        $session->update(['notes' => $notes]);

        return response()->json(['success' => true, 'message' => 'Saran terkirim ke Admin.']);
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

    /**
     * Daftar laporan progres milik guru yang login.
     */
    public function laporan()
    {
        $teacher = auth()->user()->teacher;
        abort_if(!$teacher, 403, 'Akun ini tidak terhubung ke data guru.');

        $enrollments = Enrollment::where('teacher_id', $teacher->id)
            ->whereIn('status', ['ACTIVE', 'ON_LEAVE'])
            ->with(['student', 'package.instrument'])
            ->get();

        $laporan = ProgressReport::where('teacher_id', $teacher->id)
            ->with(['student', 'enrollment.package'])
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();

        $enrollmentTemplateMap = [];
        foreach ($enrollments as $enrollment) {
            $enrollmentTemplateMap[$enrollment->id] = $this->reportTemplateResolver
                ->previewForEnrollment($enrollment);
        }

        return view('guru.laporan', compact('teacher', 'laporan', 'enrollments', 'enrollmentTemplateMap'));
    }

    /**
     * Simpan laporan baru (DRAFT).
     */
    public function laporanStore(Request $request)
    {
        $teacher = auth()->user()->teacher;
        abort_if(!$teacher, 403);

        $validated = $request->validate([
            'enrollment_id' => 'required|exists:enrollments,id',
            'month'         => 'required|integer|min:1|max:12',
            'year'          => 'required|integer|min:2024|max:2030',
        ], [
            'enrollment_id.required' => 'Kelas wajib dipilih.',
            'month.required'         => 'Bulan wajib diisi.',
            'year.required'          => 'Tahun wajib diisi.',
        ]);

        $enrollment = Enrollment::with('package')->findOrFail($validated['enrollment_id']);
        abort_if($enrollment->teacher_id !== $teacher->id, 403, 'Bukan enrollment Anda.');

        $template = $this->reportTemplateResolver->resolveForEnrollment($enrollment);
        if (! $template) {
            return back()->with(
                'error',
                'Template laporan untuk paket ' . $enrollment->package->code . ' belum tersedia. Hubungi Owner.'
            );
        }

        $sudahAda = ProgressReport::where('enrollment_id', $enrollment->id)
            ->where('month', $validated['month'])
            ->where('year', $validated['year'])
            ->exists();

        if ($sudahAda) {
            return back()->with('error', 'Laporan untuk kelas dan bulan ini sudah ada.');
        }

        $template->load('sections.items');

        $report = ProgressReport::create([
            'enrollment_id'      => $enrollment->id,
            'student_id'         => $enrollment->student_id,
            'teacher_id'         => $teacher->id,
            'report_template_id' => $template->id,
            'month'              => $validated['month'],
            'year'               => $validated['year'],
            'status'             => ProgressReport::STATUS_DRAFT,
        ]);

        foreach ($template->sections as $section) {
            ProgressReportSection::create([
                'progress_report_id'         => $report->id,
                'report_template_section_id' => $section->id,
                'summary'                    => null,
            ]);
            foreach ($section->items as $item) {
                ProgressReportItem::create([
                    'progress_report_id'      => $report->id,
                    'report_template_item_id' => $item->id,
                    'is_checked'              => false,
                ]);
            }
        }

        return redirect()->route('guru.laporan.edit', $report)
            ->with('success', 'Laporan baru dibuat. Silakan isi dan submit.');
    }

    /**
     * Form edit laporan (hanya DRAFT).
     */
    public function laporanEdit(ProgressReport $progressReport)
    {
        $teacher = auth()->user()->teacher;
        abort_if(!$teacher, 403);
        abort_if($progressReport->teacher_id !== $teacher->id, 403, 'Bukan laporan Anda.');
        abort_if($progressReport->status === ProgressReport::STATUS_SUBMITTED, 403, 'Laporan sudah disubmit.');

        $this->sessionNoteSyncService->sync($progressReport);

        $progressReport->load([
            'template.sections.items',
            'sections.templateSection',
            'items.templateItem',
            'sessionNotes',
            'student',
            'enrollment.package',
        ]);

        return view('guru.laporan-form', compact('progressReport'));
    }

    /**
     * Update isi laporan. Jika request.submit = '1', status jadi SUBMITTED.
     */
    public function laporanUpdate(Request $request, ProgressReport $progressReport)
    {
        $teacher = auth()->user()->teacher;
        abort_if(!$teacher, 403);
        abort_if($progressReport->teacher_id !== $teacher->id, 403, 'Bukan laporan Anda.');
        abort_if($progressReport->status === ProgressReport::STATUS_SUBMITTED, 403, 'Laporan sudah disubmit.');

        $this->sessionNoteSyncService->sync($progressReport);

        $validated = $request->validate([
            'highlight'            => 'nullable|string|max:3000',
            'summary_notes'        => 'nullable|string|max:2000',
            'target_notes'         => 'nullable|string|max:2000',
            'repertoire'           => 'nullable|array',
            'repertoire.*'         => 'string|max:200',
            'section_summary'      => 'nullable|array',
            'section_summary.*'    => 'nullable|string|max:500',
            'checked_items'        => 'nullable|array',
            'checked_items.*'      => 'integer|exists:report_template_items,id',
        ]);

        $progressReport->update([
            'highlight'     => $validated['highlight'] ?? null,
            'summary_notes' => $validated['summary_notes'] ?? null,
            'target_notes'  => $validated['target_notes'] ?? null,
            'repertoire'    => array_filter($validated['repertoire'] ?? []),
        ]);

        if (!empty($validated['section_summary'])) {
            foreach ($validated['section_summary'] as $sectionId => $summary) {
                ProgressReportSection::where('progress_report_id', $progressReport->id)
                    ->where('report_template_section_id', $sectionId)
                    ->update(['summary' => $summary ?: null]);
            }
        }

        $checkedIds = $validated['checked_items'] ?? [];
        ProgressReportItem::where('progress_report_id', $progressReport->id)->update(['is_checked' => false]);
        if (!empty($checkedIds)) {
            ProgressReportItem::where('progress_report_id', $progressReport->id)
                ->whereIn('report_template_item_id', $checkedIds)
                ->update(['is_checked' => true]);
        }

        if ($request->input('submit') === '1') {
            $progressReport->update([
                'status'       => ProgressReport::STATUS_SUBMITTED,
                'submitted_at' => now(),
            ]);
            return redirect()->route('guru.laporan.index')
                ->with('success', 'Laporan berhasil disubmit.');
        }

        return back()->with('success', 'Draft laporan tersimpan.');
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

    /**
     * Guru pengganti konfirmasi kehadiran pada sesi DIGANTI (fase 2 two-phase).
     * Hanya substitute_teacher_id yang cocok dengan guru login.
     */
    public function confirmSubstitute(Request $request, ClassSession $classSession)
    {
        $teacher = auth()->user()->teacher;
        abort_if(!$teacher, 403);

        abort_if(
            (int) $classSession->substitute_teacher_id !== (int) $teacher->id,
            403,
            'Hanya guru pengganti yang ditugaskan yang boleh konfirmasi.'
        );

        $request->validate([
            'action' => ['required', Rule::in(['hadir', 'batal'])],
        ], [
            'action.required' => 'Aksi konfirmasi wajib diisi.',
            'action.in'       => 'Aksi tidak valid.',
        ]);

        if ($classSession->status !== ClassSession::STATUS_DIGANTI) {
            return back()->with('error', 'Sesi bukan berstatus DIGANTI.');
        }

        if ($classSession->honor_code !== null) {
            return back()->with('error', 'Sesi sudah dikonfirmasi sebelumnya.');
        }

        if ($request->action === 'hadir') {
            $classSession->loadMissing(['enrollment.package']);
            $honor = $this->attendanceService->calculateSubstituteHonor($classSession);

            $classSession->update([
                'honor_code'   => $honor['code'],
                'honor_amount' => $honor['amount'],
            ]);

            $classSession->student?->update([
                'last_session_at' => Carbon::parse($classSession->session_date)
                    ->setTimeFromTimeString($classSession->start_time),
            ]);

            return back()->with('success', 'Kehadiran sebagai pengganti dikonfirmasi. Honor akan masuk slip bulan ini.');
        }

        // batal: kembalikan ke SCHEDULED, restore jam/ruang dari jadwal mingguan
        $schedule = $classSession->schedule;

        $classSession->update([
            'status'                => ClassSession::STATUS_SCHEDULED,
            'substitute_teacher_id' => null,
            'honor_code'            => null,
            'honor_amount'          => null,
            'start_time'            => $schedule?->start_time ?? $classSession->start_time,
            'end_time'              => $schedule?->end_time   ?? $classSession->end_time,
            'room_id'               => $schedule?->room_id    ?? $classSession->room_id,
        ]);

        return back()->with('success', 'Penugasan pengganti dibatalkan. Admin perlu atur ulang jika masih diperlukan.');
    }

    /**
     * Guru simpan catatan terstruktur per sesi (materi, tugas, catatan).
     * Boleh untuk sesi HADIR/HADIR_TERLAMBAT di bulan yang laporannya belum SUBMITTED.
     */
    public function updateSessionNotes(Request $request, ClassSession $classSession)
    {
        $teacher = auth()->user()->teacher;
        abort_if(!$teacher, 403);

        $isMainTeacher = (int) $classSession->teacher_id === (int) $teacher->id;
        $isSubstitute = (int) $classSession->substitute_teacher_id === (int) $teacher->id;

        abort_if(!$isMainTeacher && !$isSubstitute, 403, 'Bukan sesi Anda.');

        abort_if(
            !in_array($classSession->status, [ClassSession::STATUS_HADIR, ClassSession::STATUS_HADIR_TERLAMBAT], true),
            403,
            'Catatan hanya bisa diisi untuk sesi yang sudah hadir.'
        );

        if ($isSubstitute && !$isMainTeacher) {
            abort_if(
                $classSession->honor_code === null,
                403,
                'Guru pengganti belum dikonfirmasi.'
            );
        }

        $sessionDate = Carbon::parse($classSession->session_date);
        $reportSubmitted = ProgressReport::where('enrollment_id', $classSession->enrollment_id)
            ->where('month', $sessionDate->month)
            ->where('year', $sessionDate->year)
            ->where('status', ProgressReport::STATUS_SUBMITTED)
            ->exists();

        abort_if(
            $reportSubmitted,
            403,
            'Laporan bulan ini sudah disubmit, catatan tidak bisa diubah.'
        );

        $validated = $request->validate([
            'material_learned' => 'nullable|string|max:2000',
            'homework_notes'   => 'nullable|string|max:2000',
            'notes'            => 'nullable|string|max:2000',
        ]);

        $hasContent = collect($validated)
            ->contains(fn (?string $value) => filled(trim((string) $value)));

        if (!$hasContent) {
            return back()
                ->withErrors(['notes' => 'Isi minimal satu kolom catatan.'])
                ->withInput();
        }

        SessionTeacherNote::updateOrCreate(
            ['class_session_id' => $classSession->id],
            [
                'teacher_id'       => $teacher->id,
                'material_learned' => $validated['material_learned'] ?? null,
                'homework_notes'   => $validated['homework_notes'] ?? null,
                'notes'            => $validated['notes'] ?? null,
            ]
        );

        return back()->with('success', 'Catatan sesi tersimpan.');
    }
}
