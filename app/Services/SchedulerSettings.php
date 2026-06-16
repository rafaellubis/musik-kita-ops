<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Pembaca tunggal konfigurasi scheduler dari tabel settings.
 */
class SchedulerSettings
{
    private static function get(string $key, mixed $default): mixed
    {
        try {
            if (! Schema::hasTable('settings')) {
                return $default;
            }

            return Setting::get($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function sessionsGenerateEnabled(): bool
    {
        return filter_var(self::get('scheduler_sessions_generate_enabled', 'true'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function sessionsGenerateDay(): int
    {
        return (int) self::get('scheduler_sessions_generate_day', 25);
    }

    public static function sessionsGenerateTime(): string
    {
        return (string) self::get('scheduler_sessions_generate_time', '06:00');
    }

    public static function invoicesGenerateEnabled(): bool
    {
        return filter_var(self::get('scheduler_invoices_generate_enabled', 'true'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function invoicesGenerateDay(): int
    {
        return (int) self::get('scheduler_invoices_generate_day', 1);
    }

    public static function invoicesGenerateTime(): string
    {
        return (string) self::get('scheduler_invoices_generate_time', '06:00');
    }

    public static function honorCalculateEnabled(): bool
    {
        return filter_var(self::get('scheduler_honor_calculate_enabled', 'true'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function honorCalculateTime(): string
    {
        return (string) self::get('scheduler_honor_calculate_time', '06:00');
    }

    public static function honorDaysBeforeEom(): int
    {
        return (int) self::get('scheduler_honor_calculate_days_before_eom', 2);
    }

    public static function honorTargetDay(Carbon $date): int
    {
        return $date->copy()->endOfMonth()->subDays(self::honorDaysBeforeEom())->day;
    }

    public static function isHonorRunDay(Carbon $now): bool
    {
        return $now->day === self::honorTargetDay($now);
    }

    public static function finesApplyEnabled(): bool
    {
        return filter_var(self::get('scheduler_fines_apply_enabled', 'true'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function finesApplyTime(): string
    {
        return (string) self::get('scheduler_fines_apply_time', '06:00');
    }

    public static function overdueCheckEnabled(): bool
    {
        return filter_var(self::get('scheduler_overdue_check_enabled', 'true'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function overdueCheckDay(): int
    {
        return (int) self::get('scheduler_overdue_check_day', 1);
    }

    public static function overdueCheckTime(): string
    {
        return (string) self::get('scheduler_overdue_check_time', '06:05');
    }

    public static function reminderEnabled(): bool
    {
        return filter_var(self::get('schedule_reminder_enabled', 'false'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function reminderMode(): string
    {
        return (string) self::get('schedule_reminder_mode', 'day_before');
    }

    public static function reminderSendTime(): string
    {
        return (string) self::get('schedule_reminder_send_time', '18:00');
    }

    public static function reminderHoursBefore(): int
    {
        return (int) self::get('schedule_reminder_hours_before', 2);
    }
}
