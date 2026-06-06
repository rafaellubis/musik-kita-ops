<?php

namespace App\Services;

use App\Models\ProgressReport;

/**
 * Logika bisnis laporan progress guru (M08).
 */
class ProgressReportService
{
    /**
     * Generate nomor laporan unik: LMK/LPR/YYYY/MM/NNNN (reset per bulan).
     * Pola sama dengan invoice (INV), kuitansi (KW), slip honor (SLIP).
     */
    public function generateReportNumber(int $year, int $month): string
    {
        $monthStr = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
        $prefix   = 'LMK/LPR';

        $latest = ProgressReport::where('report_number', 'like', "{$prefix}/{$year}/{$monthStr}/%")
            ->orderBy('report_number', 'desc')
            ->value('report_number');

        $nextSeq = 1;
        if ($latest) {
            $parts   = explode('/', $latest);
            $nextSeq = ((int) end($parts)) + 1;
        }

        return sprintf('%s/%d/%s/%04d', $prefix, $year, $monthStr, $nextSeq);
    }

    /**
     * Isi report_number untuk laporan lama yang masih NULL.
     * Urutan: year → month → id (kronologis create).
     *
     * @return int Jumlah laporan yang di-backfill
     */
    public function backfillReportNumbers(): int
    {
        $count = 0;

        ProgressReport::query()
            ->whereNull('report_number')
            ->orderBy('year')
            ->orderBy('month')
            ->orderBy('id')
            ->each(function (ProgressReport $report) use (&$count) {
                $report->report_number = $this->generateReportNumber($report->year, $report->month);
                $report->saveQuietly();
                $count++;
            });

        return $count;
    }
}
