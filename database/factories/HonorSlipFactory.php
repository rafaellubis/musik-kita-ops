<?php

namespace Database\Factories;

use App\Models\HonorSlip;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory untuk model HonorSlip — digunakan di test unit/feature.
 * Menghasilkan data slip honor guru yang valid sesuai skema tabel teacher_honor_slips.
 *
 * @extends Factory<HonorSlip>
 */
class HonorSlipFactory extends Factory
{
    protected $model = HonorSlip::class;

    public function definition(): array
    {
        $month = $this->faker->numberBetween(1, 12);
        $year  = now()->year;
        $seq   = str_pad($this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT);

        $monthPad = str_pad($month, 2, '0', STR_PAD_LEFT);

        $baseHonor      = 0;
        $eventHonor     = 0;
        $transportHonor = 0;
        $otherHonor     = 0;

        return [
            'slip_number'      => "SLIP/{$year}/{$monthPad}/{$seq}",
            'teacher_id'       => Teacher::factory(),
            'month'            => $month,
            'year'             => $year,
            'base_honor'       => $baseHonor,
            'event_honor'      => $eventHonor,
            'event_honor_note' => null,
            'transport_honor'  => $transportHonor,
            'other_honor'      => $otherHonor,
            'other_honor_note' => null,
            'total_honor'      => $baseHonor + $eventHonor + $transportHonor + $otherHonor,
            'status'           => HonorSlip::STATUS_DRAFT,
            'paid_at'          => null,
            'paid_by'          => null,
            'created_by'       => null,
        ];
    }
}
