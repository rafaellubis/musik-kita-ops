<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Bangun preview jadwal aktif untuk halaman pengaturan scheduler (Bahasa Indonesia).
 */
class SchedulerPreviewService
{
    public function build(?Carbon $now = null): array
    {
        $now = $now ?? now();

        return [
            $this->rowSessions($now),
            $this->rowInvoices($now),
            $this->rowHonor($now),
            $this->rowFines($now),
            $this->rowOverdue($now),
            $this->rowReminder($now),
        ];
    }

    private function rowSessions(Carbon $now): array
    {
        $enabled = SchedulerSettings::sessionsGenerateEnabled();
        $day = SchedulerSettings::sessionsGenerateDay();
        $time = SchedulerSettings::sessionsGenerateTime();

        return $this->makeRow(
            key: 'sessions',
            name: 'Generator Sesi Mingguan',
            enabled: $enabled,
            frequencyLabel: "Setiap bulan tanggal {$day}, jam {$time}",
            nextRun: $enabled ? $this->nextMonthlyOn($day, $time, $now) : null,
        );
    }

    private function rowInvoices(Carbon $now): array
    {
        $enabled = SchedulerSettings::invoicesGenerateEnabled();
        $day = SchedulerSettings::invoicesGenerateDay();
        $time = SchedulerSettings::invoicesGenerateTime();

        return $this->makeRow(
            key: 'invoices',
            name: 'Generator SPP Bulanan',
            enabled: $enabled,
            frequencyLabel: "Setiap bulan tanggal {$day}, jam {$time}",
            nextRun: $enabled ? $this->nextMonthlyOn($day, $time, $now) : null,
        );
    }

    private function rowHonor(Carbon $now): array
    {
        $enabled = SchedulerSettings::honorCalculateEnabled();
        $offset = SchedulerSettings::honorDaysBeforeEom();
        $time = SchedulerSettings::honorCalculateTime();

        return $this->makeRow(
            key: 'honor',
            name: 'Kalkulasi Honor Guru',
            enabled: $enabled,
            frequencyLabel: "{$offset} hari sebelum akhir bulan, jam {$time}",
            nextRun: $enabled ? $this->nextHonorRun($offset, $time, $now) : null,
        );
    }

    private function rowFines(Carbon $now): array
    {
        $enabled = SchedulerSettings::finesApplyEnabled();
        $time = SchedulerSettings::finesApplyTime();

        return $this->makeRow(
            key: 'fines',
            name: 'Apply Denda Harian',
            enabled: $enabled,
            frequencyLabel: "Setiap hari (mulai tgl 11), jam {$time}",
            nextRun: $enabled ? $this->nextFinesRun($time, $now) : null,
        );
    }

    private function rowOverdue(Carbon $now): array
    {
        $enabled = SchedulerSettings::overdueCheckEnabled();
        $day = SchedulerSettings::overdueCheckDay();
        $time = SchedulerSettings::overdueCheckTime();

        return $this->makeRow(
            key: 'overdue',
            name: 'Deteksi Murid Overdue',
            enabled: $enabled,
            frequencyLabel: "Setiap bulan tanggal {$day}, jam {$time}",
            nextRun: $enabled ? $this->nextMonthlyOn($day, $time, $now) : null,
        );
    }

    private function rowReminder(Carbon $now): array
    {
        $enabled = SchedulerSettings::reminderEnabled();
        $mode = SchedulerSettings::reminderMode();

        if ($mode === 'hours_before') {
            $hours = SchedulerSettings::reminderHoursBefore();

            return $this->makeRow(
                key: 'reminder',
                name: 'WhatsApp Reminder Jadwal',
                enabled: $enabled,
                frequencyLabel: "Setiap 15 menit (~{$hours} jam sebelum sesi)",
                nextRun: $enabled ? $this->nextQuarterHour($now) : null,
            );
        }

        $time = SchedulerSettings::reminderSendTime();
        $modeLabel = $mode === 'same_day' ? 'Hari-H' : 'H-1';

        return $this->makeRow(
            key: 'reminder',
            name: 'WhatsApp Reminder Jadwal',
            enabled: $enabled,
            frequencyLabel: "Harian ({$modeLabel}), jam {$time}",
            nextRun: $enabled ? $this->nextDailyAt($time, $now) : null,
        );
    }

    private function makeRow(
        string $key,
        string $name,
        bool $enabled,
        string $frequencyLabel,
        ?Carbon $nextRun,
    ): array {
        return [
            'key'              => $key,
            'name'             => $name,
            'enabled'          => $enabled,
            'status_label'     => $enabled ? 'Aktif' : 'Nonaktif',
            'frequency_label'  => $frequencyLabel,
            'next_run'         => $nextRun,
            'next_run_label'   => $nextRun ? $nextRun->format('d M Y, H:i') : '—',
        ];
    }

    private function parseTime(string $time): array
    {
        [$hour, $minute] = array_pad(explode(':', $time), 2, '0');

        return [(int) $hour, (int) $minute];
    }

    private function nextMonthlyOn(int $day, string $time, Carbon $from): Carbon
    {
        [$hour, $minute] = $this->parseTime($time);

        $candidate = $this->carbonOnDay($from->year, $from->month, $day, $hour, $minute);

        if ($candidate->greaterThanOrEqualTo($from)) {
            return $candidate;
        }

        $next = $from->copy()->addMonth();

        return $this->carbonOnDay($next->year, $next->month, $day, $hour, $minute);
    }

    private function carbonOnDay(int $year, int $month, int $day, int $hour, int $minute): Carbon
    {
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $safeDay = min($day, $daysInMonth);

        return Carbon::create($year, $month, $safeDay, $hour, $minute, 0);
    }

    private function nextHonorRun(int $offset, string $time, Carbon $from): Carbon
    {
        [$hour, $minute] = $this->parseTime($time);

        $cursor = $from->copy()->startOfMonth();
        $limit = 14;

        while ($limit-- > 0) {
            $targetDay = $cursor->copy()->endOfMonth()->subDays($offset)->day;
            $candidate = $this->carbonOnDay($cursor->year, $cursor->month, $targetDay, $hour, $minute);

            if ($candidate->greaterThanOrEqualTo($from)) {
                return $candidate;
            }

            $cursor->addMonth()->startOfMonth();
        }

        return $from->copy()->addMonth();
    }

    private function nextFinesRun(string $time, Carbon $from): Carbon
    {
        [$hour, $minute] = $this->parseTime($time);

        if ($from->day >= 11) {
            $today = $from->copy()->setTime($hour, $minute, 0);
            if ($today->greaterThanOrEqualTo($from)) {
                return $today;
            }

            return $from->copy()->addDay()->setTime($hour, $minute, 0);
        }

        $candidate = $this->carbonOnDay($from->year, $from->month, 11, $hour, $minute);
        if ($candidate->greaterThanOrEqualTo($from)) {
            return $candidate;
        }

        $next = $from->copy()->addMonth();

        return $this->carbonOnDay($next->year, $next->month, 11, $hour, $minute);
    }

    private function nextDailyAt(string $time, Carbon $from): Carbon
    {
        [$hour, $minute] = $this->parseTime($time);
        $candidate = $from->copy()->setTime($hour, $minute, 0);

        if ($candidate->greaterThanOrEqualTo($from)) {
            return $candidate;
        }

        return $candidate->addDay();
    }

    private function nextQuarterHour(Carbon $from): Carbon
    {
        $minute = (int) (ceil($from->minute / 15) * 15);
        $candidate = $from->copy()->second(0);

        if ($minute >= 60) {
            return $candidate->addHour()->minute(0);
        }

        $candidate->minute($minute);

        if ($candidate->lessThanOrEqualTo($from)) {
            $candidate->addMinutes(15);
        }

        return $candidate;
    }
}
