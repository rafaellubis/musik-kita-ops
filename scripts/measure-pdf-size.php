<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$r = App\Models\ProgressReport::where('status', 'SUBMITTED')->first();
if (! $r) {
    echo "no submitted report\n";
    exit(1);
}

$r->load(['student', 'teacher', 'enrollment.package.instrument', 'sessionNotes']);
$progressReport = $r;
$logoPath = app(App\Services\ProgressReportPdfAssetService::class)->optimizedLogoPath();

$pdf = Barryvdh\DomPDF\Facade\Pdf::loadView('progress-reports.pdf', compact('progressReport', 'logoPath'))
    ->setPaper('a4', 'portrait')
    ->setOption('enable_font_subsetting', true);

$bytes = strlen($pdf->output());

echo 'Optimized PDF: ' . round($bytes / 1024, 1) . " KB\n";
if ($logoPath) {
    echo 'Optimized logo: ' . round(filesize($logoPath) / 1024, 1) . " KB\n";
}
