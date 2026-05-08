<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\HonorCalculationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Artisan command untuk kalkulasi honor guru bulanan (M06).
 *
 * Penggunaan:
 *   php artisan honor:calculate              → bulan ini
 *   php artisan honor:calculate 2026 5       → Mei 2026
 *
 * Di-schedule otomatis H-2 sebelum akhir bulan via console.php.
 * Bisa juga dijalankan manual dari UI oleh Owner.
 */
class CalculateHonor extends Command
{
    protected $signature = 'honor:calculate
                            {year? : Tahun (default: tahun ini)}
                            {month? : Bulan 1-12 (default: bulan ini)}';

    protected $description = 'Kalkulasi honor guru bulanan dari data absensi (M06)';

    public function handle(HonorCalculationService $service): int
    {
        $year  = (int) ($this->argument('year')  ?? now()->year);
        $month = (int) ($this->argument('month') ?? now()->month);

        if ($month < 1 || $month > 12) {
            $this->error('Bulan harus antara 1-12.');
            return self::FAILURE;
        }

        $monthName = Carbon::create($year, $month, 1)->format('F Y');
        $this->info("Kalkulasi honor {$monthName}...");

        // Pakai user system (id=1 = owner pertama) sebagai created_by
        // untuk cron otomatis. Kalau user belum ada, pakai 0.
        $createdBy = User::role('Owner')->value('id') ?? 1;

        $report = $service->generateAllSlips($year, $month, $createdBy);

        $this->table(
            ['Hasil', 'Jumlah'],
            [
                ['Slip baru dibuat',    $report['created']],
                ['Slip di-update',      $report['updated']],
                ['Skip (sudah PAID)',   $report['skipped']],
            ]
        );

        $total = $report['created'] + $report['updated'];
        $this->info("Selesai. {$total} slip diproses untuk {$monthName}.");

        return self::SUCCESS;
    }
}
