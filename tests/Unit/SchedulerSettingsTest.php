<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\SchedulerSettings;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulerSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_honor_target_day_uses_eom_offset(): void
    {
        Setting::set('scheduler_honor_calculate_days_before_eom', '2');

        $jan29 = Carbon::create(2026, 1, 29, 6, 0, 0);
        $this->assertTrue(SchedulerSettings::isHonorRunDay($jan29));

        $jan28 = Carbon::create(2026, 1, 28, 6, 0, 0);
        $this->assertFalse(SchedulerSettings::isHonorRunDay($jan28));
    }

    public function test_overdue_day_returns_setting(): void
    {
        Setting::set('scheduler_overdue_check_day', '5');
        $this->assertSame(5, SchedulerSettings::overdueCheckDay());
    }
}
