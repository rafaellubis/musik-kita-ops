<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\SchedulerPreviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulerPreviewServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_marks_disabled_job(): void
    {
        Setting::set('scheduler_invoices_generate_enabled', 'false');

        $preview = (new SchedulerPreviewService())->build();
        $spp = collect($preview)->firstWhere('key', 'invoices');

        $this->assertFalse($spp['enabled']);
        $this->assertSame('Nonaktif', $spp['status_label']);
    }

    public function test_preview_honor_shows_fixed_day_label(): void
    {
        Setting::set('scheduler_honor_calculate_day', '28');
        Setting::set('scheduler_honor_calculate_enabled', 'true');
        Setting::set('scheduler_honor_calculate_time', '08:00');

        $preview = (new SchedulerPreviewService())->build();
        $honor = collect($preview)->firstWhere('key', 'honor');

        $this->assertStringContainsString('tanggal 28', $honor['frequency_label']);
    }

    public function test_preview_reminder_shows_poll_minutes_label(): void
    {
        Setting::set('schedule_reminder_enabled', 'true');
        Setting::set('schedule_reminder_mode', 'hours_before');
        Setting::set('schedule_reminder_poll_minutes', '30');
        Setting::set('schedule_reminder_hours_before', '2');

        $preview = (new SchedulerPreviewService())->build();
        $reminder = collect($preview)->firstWhere('key', 'reminder');

        $this->assertStringContainsString('30 menit', $reminder['frequency_label']);
    }
}
