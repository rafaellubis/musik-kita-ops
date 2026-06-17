<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\InvoiceReminderService;
use Illuminate\Console\Command;

/**
 * Kirim reminder tagihan WA terjadwal H-N sebelum tempo (teks saja).
 * Dijadwalkan harian via routes/console.php.
 */
class SendInvoiceRemindersScheduled extends Command
{
    protected $signature   = 'invoice-reminders:send-scheduled {--dry-run : Simulasi tanpa kirim WA}';
    protected $description = 'Kirim reminder tagihan WA otomatis H-N sebelum tempo (teks saja)';

    public function handle(InvoiceReminderService $service): int
    {
        try {
            $summary = $service->sendScheduledReminders(now(), (bool) $this->option('dry-run'));
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($summary['skipped_disabled']) {
            $this->info('Scheduler invoice reminder nonaktif di pengaturan.');

            return self::SUCCESS;
        }

        if ($summary['skipped_time']) {
            $this->info('Bukan jam pengiriman yang dikonfigurasi — dilewati.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Dry-run: {$summary['eligible']} murid eligible, {$summary['sent']} akan dikirim.");
        } else {
            $this->table(['Metrik', 'Jumlah'], [
                ['Eligible', $summary['eligible']],
                ['Terkirim', $summary['sent']],
                ['Gagal', $summary['failed']],
                ['Lewati (sudah diingatkan bulan ini)', $summary['skipped_already_reminded']],
                ['Lewati (nomor tidak valid)', $summary['skipped']],
            ]);

            if ($summary['sent'] > 0 || $summary['failed'] > 0) {
                AuditLog::create([
                    'user_id'      => null,
                    'user_name'    => 'System (Scheduler)',
                    'action'       => AuditLog::ACTION_CREATE,
                    'entity_type'  => null,
                    'entity_id'    => null,
                    'entity_label' => 'Scheduler Reminder WA Tagihan',
                    'new_values'   => [
                        'eligible'                 => $summary['eligible'],
                        'sent'                     => $summary['sent'],
                        'failed'                   => $summary['failed'],
                        'skipped_already_reminded' => $summary['skipped_already_reminded'],
                        'skipped'                  => $summary['skipped'],
                        'log_ids'                  => $summary['log_ids'],
                    ],
                    'notes' => 'Cron invoice-reminders:send-scheduled',
                ]);
            }
        }

        return self::SUCCESS;
    }
}
