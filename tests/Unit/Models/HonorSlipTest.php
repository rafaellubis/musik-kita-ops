<?php

namespace Tests\Unit\Models;

use App\Models\HonorSlip;
use Tests\TestCase;

class HonorSlipTest extends TestCase
{
    public function test_recalc_total_menyertakan_event_honor(): void
    {
        $slip = new HonorSlip();
        $slip->base_honor      = 3_200_000;
        $slip->event_honor     = 250_000;
        $slip->transport_honor = 100_000;
        $slip->other_honor     = 0;

        $slip->recalcTotal();

        $this->assertEquals(3_550_000, $slip->total_honor);
    }

    public function test_recalc_total_tanpa_event_honor_tetap_benar(): void
    {
        $slip = new HonorSlip();
        $slip->base_honor      = 2_000_000;
        $slip->event_honor     = 0;
        $slip->transport_honor = 50_000;
        $slip->other_honor     = 0;

        $slip->recalcTotal();

        $this->assertEquals(2_050_000, $slip->total_honor);
    }

    public function test_has_event_honor_true_jika_event_honor_lebih_dari_nol(): void
    {
        $slip = new HonorSlip();
        $slip->event_honor = 250_000;

        $this->assertTrue($slip->hasEventHonor());
    }

    public function test_has_event_honor_false_jika_event_honor_nol(): void
    {
        $slip = new HonorSlip();
        $slip->event_honor = 0;

        $this->assertFalse($slip->hasEventHonor());
    }

    public function test_has_event_honor_false_jika_event_honor_null(): void
    {
        $slip = new HonorSlip();
        $slip->event_honor = null;

        $this->assertFalse($slip->hasEventHonor());
    }
}
