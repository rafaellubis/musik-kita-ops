<?php

namespace App\Services;

use App\Models\Setting;
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

    public static function honorCalculateDay(): int
    {
        return (int) self::get('scheduler_honor_calculate_day', 28);
    }

    public static function honorCalculateTime(): string
    {
        return (string) self::get('scheduler_honor_calculate_time', '06:00');
    }

    public static function finesApplyEnabled(): bool
    {
        return filter_var(self::get('scheduler_fines_apply_enabled', 'true'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function finesApplyTime(): string
    {
        return (string) self::get('scheduler_fines_apply_time', '06:00');
    }

    public static function finesStartDay(): int
    {
        return (int) self::get('scheduler_fines_start_day', 11);
    }

    public static function sppDueDay(): int
    {
        return (int) self::get('spp_due_day', 10);
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

    public static function reminderPollMinutes(): int
    {
        $minutes = (int) self::get('schedule_reminder_poll_minutes', 15);

        return max(5, min(60, $minutes));
    }

    public static function invoiceReminderEnabled(): bool
    {
        return filter_var(self::get('scheduler_invoice_reminder_enabled', 'false'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function invoiceReminderDaysBeforeDue(): int
    {
        return (int) self::get('scheduler_invoice_reminder_days_before_due', 3);
    }

    public static function invoiceReminderTime(): string
    {
        return (string) self::get('scheduler_invoice_reminder_time', '10:00');
    }

    public static function invoiceReminderTemplateId(): ?int
    {
        $value = self::get('scheduler_invoice_reminder_template_id', '');

        if ($value === '' || $value === null) {
            return null;
        }

        return (int) $value;
    }

    public static function cutiReturnEnabled(): bool
    {
        return filter_var(self::get('scheduler_cuti_return_enabled', 'true'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function cutiReturnTime(): string
    {
        return (string) self::get('scheduler_cuti_return_time', '23:59');
    }

    public static function staffPayrollEnabled(): bool
    {
        return filter_var(self::get('scheduler_staff_payroll_enabled', 'true'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function staffPayrollDay(): int
    {
        return (int) self::get('scheduler_staff_payroll_day', 28);
    }

    public static function staffPayrollTime(): string
    {
        return (string) self::get('scheduler_staff_payroll_time', '07:00');
    }
}
