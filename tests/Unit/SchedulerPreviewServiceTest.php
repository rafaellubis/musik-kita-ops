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

    public function test_preview_honor_shows_eom_offset_label(): void
    {
        Setting::set('scheduler_honor_calculate_days_before_eom', '3');
        Setting::set('scheduler_honor_calculate_enabled', 'true');
        Setting::set('scheduler_honor_calculate_time', '08:00');

        $preview = (new SchedulerPreviewService())->build();
        $honor = collect($preview)->firstWhere('key', 'honor');

        $this->assertStringContainsString('3 hari sebelum akhir bulan', $honor['frequency_label']);
    }
}
