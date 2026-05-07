<?php

namespace App\Console\Commands;

use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Apply denda harian Rp 5.000/hari untuk invoice telat (M05, BR-5.3).
 *
 * Pemakaian:
 *   php artisan invoices:apply-fines                              # bulan ini
 *   php artisan invoices:apply-fines --year=2026 --month=5
 *   php artisan invoices:apply-fines --as-of=2026-05-15           # untuk testing
 *
 * Idempotent: kalau item DENDA sudah ada, di-update sesuai jumlah hari
 * telat hari ini. Aman dijalankan harian via cron.
 *
 * TODO: jadwalkan harian via routes/console.php (atau scheduler kernel).
 */
class ApplyLateFines extends Command
{
    protected $signature = 'invoices:apply-fines
                            {--year= : Tahun invoice yang akan dikenai denda (default: bulan ini)}
                            {--month= : Bulan invoice (default: bulan ini)}
                            {--as-of= : Tanggal acuan untuk hitung hari telat (default: hari ini), format YYYY-MM-DD}';

    protected $description = 'Apply denda harian Rp 5.000/hari ke invoice telat (mulai tanggal 11).';

    public function handle(InvoiceService $service): int
    {
        $now = Carbon::now();
        $year = (int) ($this->option('year') ?: $now->year);
        $month = (int) ($this->option('month') ?: $now->month);

        $asOf = $this->option('as-of')
            ? Carbon::parse($this->option('as-of'))
            : $now;

        $this->info(sprintf(
            'Apply denda: invoice %s, dihitung sampai %s.',
            Carbon::create($year, $month, 1)->format('F Y'),
            $asOf->format('d M Y')
        ));

        $report = $service->applyLateFinesForMonth($year, $month, $asOf);

        $this->table(['Metrik', 'Jumlah'], [
            ['Invoice unpaid diproses', $report['processed']],
            ['Item denda dibuat/update', $report['updated']],
        ]);

        return self::SUCCESS;
    }
}
