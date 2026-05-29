<?php
namespace App\Http\Controllers;

use App\Models\ProgressReport;
use App\Models\Teacher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * ProgressReportController — Admin/Owner/Auditor lihat laporan yang disubmit guru.
 * Guru submit via /guru/laporan (GuruController).
 * Admin/Owner lihat daftar, detail, dan download PDF via controller ini.
 */
class ProgressReportController extends Controller
{
    /**
     * Daftar semua laporan progres — bisa filter by guru, status, bulan, tahun.
     */
    public function index(Request $request)
    {
        $query = ProgressReport::with(['student', 'teacher', 'enrollment.package'])
            ->orderByDesc('year')
            ->orderByDesc('month');

        // Filter by guru
        if ($teacherId = $request->get('teacher_id')) {
            $query->where('teacher_id', $teacherId);
        }
        // Filter by status (DRAFT | SUBMITTED)
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        // Filter by bulan
        if ($month = $request->get('month')) {
            $query->where('month', $month);
        }
        // Filter by tahun
        if ($year = $request->get('year')) {
            $query->where('year', $year);
        }

        $laporan  = $query->paginate(30)->withQueryString();
        $teachers = Teacher::where('is_active', true)->orderBy('name')->get();

        return view('progress-reports.index', compact('laporan', 'teachers'));
    }

    /**
     * Detail satu laporan progres — tampilkan checklist per seksi,
     * repertoar, catatan sesi, catatan akhir, dan target bulan depan.
     */
    public function show(ProgressReport $progressReport)
    {
        $progressReport->load([
            'student',
            'teacher',
            'enrollment.package.instrument',
            'template.sections.items',
            'sections.templateSection',
            'items.templateItem',
            'sessionNotes',
        ]);

        return view('progress-reports.show', compact('progressReport'));
    }

    /**
     * Download PDF laporan progres — generate via DomPDF.
     * Hanya tersedia untuk laporan berstatus SUBMITTED.
     */
    public function pdf(ProgressReport $progressReport)
    {
        $progressReport->load([
            'student',
            'teacher',
            'enrollment.package.instrument',
            'template.sections.items',
            'sections.templateSection',
            'items.templateItem',
            'sessionNotes',
        ]);

        $pdf = Pdf::loadView('progress-reports.pdf', compact('progressReport'))
            ->setPaper('a4', 'portrait');

        // Nama file: Laporan-NamaMurid-BulanTahun.pdf
        $filename = 'Laporan-' .
            str_replace(' ', '-', $progressReport->student->full_name) . '-' .
            $progressReport->namaBulan() . '.pdf';

        return $pdf->download($filename);
    }
}
