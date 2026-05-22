<?php

namespace App\Http\Controllers;

use App\Models\HonorSlip;
use App\Models\Teacher;
use App\Services\HonorCalculationService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Manajemen slip honor guru (M06).
 *
 * Route yang dilayani:
 *   GET  /honors              → index   (list slip per bulan, semua guru)
 *   GET  /honors/{slip}       → show    (detail + rincian sesi)
 *   GET  /honors/{slip}/edit  → edit    (form transport + lain-lain) [Owner]
 *   PATCH /honors/{slip}      → update  (simpan komponen manual) [Owner]
 *   POST /honors/calculate    → calculate (trigger kalkulasi bulanan) [Owner]
 *   POST /honors/{slip}/mark-paid → markPaid (tandai dibayar) [Owner]
 *   GET  /honors/{slip}/print → print   (cetak slip A4) [semua role]
 */
class HonorController extends Controller
{
    public function __construct(
        private readonly HonorCalculationService $service
    ) {}

    /**
     * Daftar slip honor per bulan. Filter by tahun/bulan dan status.
     */
    public function index(Request $request)
    {
        $year  = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);

        $query = HonorSlip::query()
            ->join('teachers', 'teacher_honor_slips.teacher_id', '=', 'teachers.id')
            ->select('teacher_honor_slips.*')
            ->with('teacher')
            ->forMonth($year, $month)
            ->orderBy('status')          // CALCULATED/DRAFT dulu, PAID di bawah
            ->orderBy('teachers.name');  // dalam satu status, urut nama guru

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $slips = $query->paginate(50)->withQueryString();

        // Statistik ringkas untuk header
        $stats = HonorSlip::forMonth($year, $month)
            ->selectRaw('status, COUNT(*) as cnt, SUM(total_honor) as sum_total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        // Guru yang belum ada slip di bulan ini — untuk reminder kalkulasi
        $slipTeacherIds = HonorSlip::forMonth($year, $month)->pluck('teacher_id');
        $missingCount   = Teacher::where('is_active', true)
            ->whereNotIn('id', $slipTeacherIds)
            ->count();

        $monthName = Carbon::create($year, $month, 1)->format('F Y');

        return view('honors.index', compact(
            'slips', 'stats', 'missingCount',
            'year', 'month', 'monthName'
        ));
    }

    /**
     * Detail slip: ringkasan komponen + rincian sesi per honor_code.
     */
    public function show(HonorSlip $honor)
    {
        $honor->load('teacher', 'paidBy', 'createdBy');

        $sessions = $this->service->getSessionBreakdown($honor);

        // Ringkasan per honor_code untuk tabel breakdown
        $breakdown = $sessions->groupBy('honor_code')
            ->map(fn ($group) => [
                'code'  => $group->first()->honor_code ?? '—',
                'count' => $group->count(),
                'total' => $group->sum('honor_amount'),
                'items' => $group,
            ]);

        $monthName = Carbon::create($honor->year, $honor->month, 1)->format('F Y');

        return view('honors.show', compact('honor', 'sessions', 'breakdown', 'monthName'));
    }

    /**
     * Form edit komponen manual: transport + lain-lain.
     * Hanya Owner, slip belum PAID.
     */
    public function edit(HonorSlip $honor)
    {
        if ($honor->isLocked()) {
            return redirect()->route('honors.show', $honor)
                ->with('error', 'Slip sudah berstatus PAID dan tidak bisa diubah.');
        }

        $monthName = Carbon::create($honor->year, $honor->month, 1)->format('F Y');

        return view('honors.edit', compact('honor', 'monthName'));
    }

    /**
     * Simpan komponen manual (transport + event_honor + other_honor + catatan).
     * Recalc total_honor otomatis setelah semua komponen tersimpan.
     */
    public function update(Request $request, HonorSlip $honor)
    {
        if ($honor->isLocked()) {
            return redirect()->route('honors.show', $honor)
                ->with('error', 'Slip sudah berstatus PAID dan tidak bisa diubah.');
        }

        $data = $request->validate([
            'transport_honor'  => 'required|integer|min:0|max:99999999',
            'event_honor'      => 'required|integer|min:0|max:99999999',
            'event_honor_note' => 'nullable|string|max:255',
            'other_honor'      => 'required|integer|min:0|max:99999999',
            'other_honor_note' => 'nullable|string|max:255',
        ], [
            'transport_honor.required' => 'Honor transport wajib diisi (isi 0 jika tidak ada).',
            'transport_honor.min'      => 'Honor transport tidak boleh negatif.',
            'event_honor.required'     => 'Honor event wajib diisi (isi 0 jika tidak ada event).',
            'event_honor.min'          => 'Honor event tidak boleh negatif.',
            'other_honor.required'     => 'Honor lain-lain wajib diisi (isi 0 jika tidak ada).',
            'other_honor.min'          => 'Honor lain-lain tidak boleh negatif.',
        ]);

        // Keterangan wajib jika event_honor > 0
        if ((int) $data['event_honor'] > 0 && empty(trim($data['event_honor_note'] ?? ''))) {
            return back()
                ->withErrors(['event_honor_note' => 'Keterangan event wajib diisi jika ada honor event.'])
                ->withInput();
        }

        // Keterangan wajib jika other_honor > 0
        if ((int) $data['other_honor'] > 0 && empty(trim($data['other_honor_note'] ?? ''))) {
            return back()
                ->withErrors(['other_honor_note' => 'Keterangan lain-lain wajib diisi jika ada honor lain-lain.'])
                ->withInput();
        }

        $honor->transport_honor  = $data['transport_honor'];
        $honor->event_honor      = $data['event_honor'];
        $honor->event_honor_note = $data['event_honor_note'] ?? null;
        $honor->other_honor      = $data['other_honor'];
        $honor->other_honor_note = $data['other_honor_note'] ?? null;
        $honor->recalcTotal();
        $honor->save();

        return redirect()->route('honors.show', $honor)
            ->with('success', 'Komponen honor berhasil disimpan.');
    }

    /**
     * Trigger kalkulasi honor untuk semua guru aktif di bulan tertentu.
     * Idempotent — slip yang sudah PAID tidak diubah.
     */
    public function calculate(Request $request)
    {
        $data = $request->validate([
            'year'  => 'required|integer|min:2024|max:2030',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $year  = $data['year'];
        $month = $data['month'];

        $report = $this->service->generateAllSlips(
            $year, $month,
            auth()->id()
        );

        $monthName = Carbon::create($year, $month, 1)->format('F Y');

        return redirect()->route('honors.index', ['year' => $year, 'month' => $month])
            ->with('success', sprintf(
                'Kalkulasi honor %s selesai: %d slip baru, %d di-update, %d skip (sudah PAID).',
                $monthName,
                $report['created'],
                $report['updated'],
                $report['skipped'],
            ));
    }

    /**
     * Tandai slip sebagai PAID. Hanya Owner.
     * Setelah PAID, slip tidak bisa diedit lagi.
     */
    public function markPaid(HonorSlip $honor)
    {
        if ($honor->status === HonorSlip::STATUS_DRAFT) {
            return redirect()->route('honors.show', $honor)
                ->with('error', 'Slip masih DRAFT — hitung dulu sebelum ditandai dibayar.');
        }

        $this->service->markPaid($honor, auth()->id());

        return redirect()->route('honors.show', $honor)
            ->with('success', 'Slip honor ' . $honor->slip_number . ' ditandai DIBAYAR.');
    }

    /**
     * Halaman A4 untuk dicetak (Ctrl+P → PDF).
     */
    public function print(HonorSlip $honor)
    {
        $honor->load('teacher.instruments', 'paidBy');

        $studentBreakdown = $this->service->getStudentBreakdown($honor);
        $hasKids          = $studentBreakdown->where('is_kids', true)->isNotEmpty();

        $monthName = Carbon::create($honor->year, $honor->month, 1)->format('F Y');

        return view('honors.print', compact(
            'honor', 'studentBreakdown', 'hasKids', 'monthName'
        ));
    }
}
