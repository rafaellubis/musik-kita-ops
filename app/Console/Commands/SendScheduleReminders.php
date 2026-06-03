<?php

namespace App\Console\Commands;

use App\Services\ScheduleReminderService;
use Illuminate\Console\Command;

/**
 * Kirim pengingat jadwal kelas ke ortu via Fonnte (cron otomatis).
 */
class SendScheduleReminders extends Command
{
    protected $signature = 'schedule-reminders:send
                            {--dry-run : Preview tanpa kirim WA dan tanpa cek waktu send}';

    protected $description = 'Kirim pengingat jadwal kelas via WhatsApp (Fonnte) ke parent_phone';

    public function handle(ScheduleReminderService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $now = now();

        if ($dryRun) {
            $this->warn('Mode dry-run: tidak mengirim pesan WA.');
        }

        $summary = $service->sendDueReminders($now, $dryRun);

        if ($summary['skipped'] && $summary['reason']) {
            $this->info($summary['reason']);

            return self::SUCCESS;
        }

        if ($summary['reason'] && $summary['sent'] === 0 && $summary['failed'] === 0) {
            $this->info($summary['reason']);
        }

        $this->info("Terkirim: {$summary['sent']}, gagal: {$summary['failed']}, dilewati: {$summary['skipped_students']}.");

        foreach ($summary['errors'] as $code => $error) {
            $this->error("  {$code}: {$error}");
        }

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
