<?php

namespace Tests\Unit;

use App\Models\Instrument;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageReportLabelTest extends TestCase
{
    use RefreshDatabase;

    private function makePackage(string $classType, ?string $grade = null): Package
    {
        $instrument = Instrument::create(['code' => 'PIANO', 'name' => 'Piano', 'is_active' => true, 'sort_order' => 1]);

        return Package::create([
            'code'            => 'TEST',
            'instrument_id'   => $instrument->id,
            'class_type'      => $classType,
            'grade'           => $grade,
            'duration_min'    => 30,
            'price_per_month' => 390000,
            'is_active'       => true,
            'sort_order'      => 1,
        ])->load('instrument');
    }

    public function test_hobby_shows_instrument_only(): void
    {
        $this->assertSame('Piano', $this->makePackage('HOBBY')->getReportInstrumentLabel());
    }

    public function test_reguler_l2_shows_level_one_format(): void
    {
        $this->assertSame('Piano · Level 2', $this->makePackage('REGULER', 'L2')->getReportInstrumentLabel());
    }

    public function test_duo_shows_basic(): void
    {
        $this->assertSame('Piano · Basic', $this->makePackage('DUO', 'BASIC')->getReportInstrumentLabel());
    }
}
