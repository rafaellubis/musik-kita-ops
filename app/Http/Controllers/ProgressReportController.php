<?php
namespace App\Http\Controllers;

use App\Models\ProgressReport;
use App\Models\Teacher;
use App\Services\ProgressReportPdfService;
use Illuminate\Http\Request;

/**
 * ProgressReportController — Admin/Owner/Auditor lihat laporan progres guru.
 * Guru submit via /guru/laporan (GuruController).
 * Admin/Owner lihat daftar, detail, preview PDF, dan download PDF via controller ini.
 */
class ProgressReportController extends Controller
{
    public function __construct(
        private readonly ProgressReportPdfService $pdfService,
    ) {}
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
            'sessionNotes',
        ]);

        return view('progress-reports.show', compact('progressReport'));
    }

    /**
     * Halaman preview PDF — iframe inline + tombol download.
     */
    public function pdfView(ProgressReport $progressReport)
    {
        $progressReport = $this->pdfService->loadReport($progressReport);

        return view('progress-reports.pdf-viewer', $this->viewerData($progressReport));
    }

    /**
     * Stream PDF inline untuk iframe preview.
     */
    public function pdfFile(ProgressReport $progressReport)
    {
        $progressReport = $this->pdfService->loadReport($progressReport);

        return $this->pdfService->makePdf($progressReport)
            ->stream($this->pdfService->filename($progressReport));
    }

    /**
     * Download PDF laporan progres — generate via DomPDF.
     */
    public function pdfDownload(ProgressReport $progressReport)
    {
        $progressReport = $this->pdfService->loadReport($progressReport);

        return $this->pdfService->makePdf($progressReport)
            ->download($this->pdfService->filename($progressReport));
    }

    private function viewerData(ProgressReport $progressReport): array
    {
        return [
            'progressReport' => $progressReport,
            'layout' => 'admin',
            'backUrl' => route('progress-reports.show', $progressReport),
            'downloadUrl' => route('progress-reports.pdf.download', $progressReport),
            'fileUrl' => route('progress-reports.pdf.file', $progressReport),
        ];
    }
}
