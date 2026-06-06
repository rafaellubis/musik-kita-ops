<?php

namespace App\Services;

use App\Models\ProgressReport;
use Barryvdh\DomPDF\Facade\Pdf;

class ProgressReportPdfService
{
    public function __construct(
        private readonly ProgressReportPdfAssetService $assetService,
    ) {}

    public function loadReport(ProgressReport $progressReport): ProgressReport
    {
        $progressReport->load([
            'student',
            'teacher',
            'enrollment.package.instrument',
            'sessionNotes',
        ]);

        return $progressReport;
    }

    public function makePdf(ProgressReport $progressReport)
    {
        $logoPath = $this->assetService->optimizedLogoPath();

        return Pdf::loadView('progress-reports.pdf', compact('progressReport', 'logoPath'))
            ->setPaper('a4', 'portrait')
            ->setOption('enable_font_subsetting', true);
    }

    public function filename(ProgressReport $progressReport): string
    {
        return 'Laporan-' .
            str_replace(' ', '-', $progressReport->student->full_name) . '-' .
            $progressReport->namaBulan() . '.pdf';
    }
}
