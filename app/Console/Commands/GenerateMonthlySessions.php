<?php

namespace App\Console\Commands;

use App\Services\SessionGeneratorService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Generate sesi mingguan ke tabel class_sessions.
 *
 * Pemakaian:
 *   php artisan sessions:generate-month                 # bulan depan
 *   php artisan sessions:generate-month --year=2026 --month=6
 *
 * Idempotent: aman dijalankan ulang.
 *
 * TODO: jadwalkan otomatis tanggal 25 setiap bulan via routes/console.php
 *       (CLAUDE.md M03 — generator cron tanggal 25 untuk bulan berikutnya).
 */
class GenerateMonthlySessions extends Command
{
    protected $signature = 'sessions:generate-month
                            {--year= : Tahun target (default: bulan depan)}
                            {--month= : Bulan target 1-12 (default: bulan depan)}';

    protected $description = 'Generate sesi mingguan tetap ke tabel class_sessions untuk bulan target.';

    public function handle(SessionGeneratorService $generator): int
    {
        // Default: bulan depan (sesuai cron tanggal 25)
        $target = Carbon::now()->addMonth();
        $year = (int) ($this->option('year') ?: $target->year);
        $month = (int) ($this->option('month') ?: $target->month);

        if ($month < 1 || $month > 12) {
            $this->error("Bulan tidak valid: {$month}. Harus 1-12.");
            return self::FAILURE;
        }

        $this->info("Generate sesi untuk: " . Carbon::create($year, $month, 1)->format('F Y'));

        $report = $generator->generateForMonth($year, $month);

        $this->table(
            ['Metrik', 'Jumlah'],
            [
                ['Schedule diproses', $report['schedules_processed']],
                ['Sesi baru dibuat', $report['created']],
                ['  - di antaranya LIBUR', $report['skipped_libur']],
                ['Sesi sudah ada (skip)', $report['skipped_exists']],
            ],
        );

        return self::SUCCESS;
    }
}
