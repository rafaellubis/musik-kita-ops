<?php

namespace App\Console\Commands;

use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Generate invoice SPP bulanan untuk semua murid AKTIF (M05, BR-5.1).
 *
 * Pemakaian:
 *   php artisan invoices:generate-spp                     # bulan ini
 *   php artisan invoices:generate-spp --year=2026 --month=6
 *
 * Idempotent: invoice SPP yang sudah ada untuk (student, year, month)
 * tidak duplikat. Aman dijalankan ulang oleh cron tanggal 1.
 *
 * TODO: jadwalkan tanggal 1 setiap bulan via routes/console.php.
 */
class GenerateMonthlySpp extends Command
{
    protected $signature = 'invoices:generate-spp
                            {--year= : Tahun target (default: bulan ini)}
                            {--month= : Bulan target 1-12 (default: bulan ini)}';

    protected $description = 'Generate invoice SPP bulanan untuk semua murid AKTIF.';

    public function handle(InvoiceService $service): int
    {
        $now = Carbon::now();
        $year = (int) ($this->option('year') ?: $now->year);
        $month = (int) ($this->option('month') ?: $now->month);

        if ($month < 1 || $month > 12) {
            $this->error("Bulan tidak valid: {$month}.");
            return self::FAILURE;
        }

        $this->info("Generate SPP untuk: " . Carbon::create($year, $month, 1)->format('F Y'));

        $report = $service->generateMonthlySPP($year, $month);

        $this->table(['Metrik', 'Jumlah'], [
            ['Invoice SPP baru', $report['created']],
            ['Sudah ada (skip)', $report['skipped']],
        ]);

        return self::SUCCESS;
    }
}
