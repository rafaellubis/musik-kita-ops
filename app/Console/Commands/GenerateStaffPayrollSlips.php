<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\StaffPayrollService;
use Illuminate\Console\Command;

/**
 * Generate slip gaji staff non-guru bulan berjalan (M12).
 * Dijadwalkan monthlyOn via routes/console.php.
 */
class GenerateStaffPayrollSlips extends Command
{
    protected $signature   = 'staff-payroll:generate-slips';
    protected $description = 'Generate slip gaji staff non-guru untuk bulan berjalan';

    public function handle(StaffPayrollService $service): int
    {
        $owner = User::role('Owner')->orderBy('id')->first();
        $userId = $owner?->id ?? 1;

        $year  = now()->year;
        $month = now()->month;

        $report = $service->generateAllSlips($year, $month, $userId);

        $this->table(['Metrik', 'Jumlah'], [
            ['Slip baru', $report['created']],
            ['Diperbarui', $report['updated']],
            ['Dilewati (PAID)', $report['skipped']],
        ]);

        if ($report['created'] > 0 || $report['updated'] > 0) {
            AuditLog::create([
                'user_id'      => $owner?->id,
                'user_name'    => $owner?->name ?? 'System (Scheduler)',
                'action'       => AuditLog::ACTION_CREATE,
                'entity_type'  => null,
                'entity_id'    => null,
                'entity_label' => 'Scheduler Generate Slip Gaji Staff',
                'new_values'   => [
                    'year'    => $year,
                    'month'   => $month,
                    'created' => $report['created'],
                    'updated' => $report['updated'],
                    'skipped' => $report['skipped'],
                ],
                'notes' => "Cron staff-payroll:generate-slips — {$year}/{$month}",
            ]);
        }

        $this->info('Selesai generate slip gaji staff.');

        return self::SUCCESS;
    }
}
