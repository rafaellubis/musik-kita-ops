<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\SchedulerSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulerSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_honor_calculate_day_returns_setting(): void
    {
        Setting::set('scheduler_honor_calculate_day', '28');

        $this->assertSame(28, SchedulerSettings::honorCalculateDay());
    }

    public function test_fines_start_day_returns_setting(): void
    {
        Setting::set('scheduler_fines_start_day', '12');

        $this->assertSame(12, SchedulerSettings::finesStartDay());
    }

    public function test_spp_due_day_returns_setting(): void
    {
        Setting::set('spp_due_day', '15');

        $this->assertSame(15, SchedulerSettings::sppDueDay());
    }

    public function test_reminder_poll_minutes_clamped_between_5_and_60(): void
    {
        Setting::set('schedule_reminder_poll_minutes', '3');
        $this->assertSame(5, SchedulerSettings::reminderPollMinutes());

        Setting::set('schedule_reminder_poll_minutes', '90');
        $this->assertSame(60, SchedulerSettings::reminderPollMinutes());

        Setting::set('schedule_reminder_poll_minutes', '30');
        $this->assertSame(30, SchedulerSettings::reminderPollMinutes());
    }

    public function test_invoice_reminder_days_before_due_returns_setting(): void
    {
        Setting::set('scheduler_invoice_reminder_days_before_due', '5');

        $this->assertSame(5, SchedulerSettings::invoiceReminderDaysBeforeDue());
    }

    public function test_new_job_getters_return_defaults(): void
    {
        $this->assertFalse(SchedulerSettings::invoiceReminderEnabled());
        $this->assertTrue(SchedulerSettings::cutiReturnEnabled());
        $this->assertSame('23:59', SchedulerSettings::cutiReturnTime());
        $this->assertTrue(SchedulerSettings::staffPayrollEnabled());
        $this->assertSame(28, SchedulerSettings::staffPayrollDay());
        $this->assertSame('07:00', SchedulerSettings::staffPayrollTime());
    }

    public function test_overdue_day_returns_setting(): void
    {
        Setting::set('scheduler_overdue_check_day', '5');
        $this->assertSame(5, SchedulerSettings::overdueCheckDay());
    }
}
