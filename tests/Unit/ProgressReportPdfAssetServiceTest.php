<?php

namespace Tests\Unit;

use App\Services\ProgressReportPdfAssetService;
use Tests\TestCase;

class ProgressReportPdfAssetServiceTest extends TestCase
{
    public function test_optimized_logo_is_smaller_than_source_when_gd_available(): void
    {
        if (! function_exists('imagecreatefrompng')) {
            $this->markTestSkipped('GD extension not available.');
        }

        $source = public_path('images/logo-musikkita-light-mode.PNG');
        if (! file_exists($source)) {
            $this->markTestSkipped('Source logo not found.');
        }

        $service = new ProgressReportPdfAssetService();
        $optimized = $service->optimizedLogoPath();

        $this->assertNotNull($optimized);
        $this->assertFileExists($optimized);
        $this->assertLessThan(filesize($source), filesize($optimized));
        $this->assertStringEndsWith('.jpg', $optimized);
    }
}
