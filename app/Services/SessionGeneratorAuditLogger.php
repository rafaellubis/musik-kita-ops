<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Carbon\Carbon;

/**
 * Catat ringkasan satu run generator sesi ke audit log (M03/M09).
 * Satu entri per run — bukan per ClassSession.
 */
class SessionGeneratorAuditLogger
{
    /**
     * @param array{
     *     created: int,
     *     replacements_created?: int,
     *     skipped_exists: int,
     *     skipped_libur: int,
     *     skipped_conflict?: int,
     *     schedules_processed?: int,
     *     month: string,
     * } $report
     * @param 'manual'|'scheduler' $source
     */
    public static function record(array $report, int $year, int $month, ?User $user, string $source): void
    {
        $notesPrefix = $source === 'scheduler'
            ? 'Cron sessions:generate-month'
            : 'Manual generate sesi';

        $monthLabel = $report['month'] ?? Carbon::create($year, $month, 1)->format('F Y');

        AuditLog::create([
            'user_id'      => $user?->id,
            'user_name'    => $user?->name ?? 'System (Scheduler)',
            'action'       => AuditLog::ACTION_CREATE,
            'entity_type'  => null,
            'entity_id'    => null,
            'entity_label' => 'Generate Sesi Bulanan',
            'new_values'   => [
                'year'                 => $year,
                'month'                => $month,
                'month_label'          => $monthLabel,
                'source'               => $source,
                'created'              => $report['created'],
                'replacements_created' => $report['replacements_created'] ?? 0,
                'skipped_exists'       => $report['skipped_exists'],
                'skipped_libur'        => $report['skipped_libur'],
                'skipped_conflict'     => $report['skipped_conflict'] ?? 0,
                'schedules_processed'  => $report['schedules_processed'] ?? 0,
            ],
            'notes' => "{$notesPrefix} — {$monthLabel}",
        ]);
    }
}
