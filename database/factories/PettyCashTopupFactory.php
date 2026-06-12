<?php

namespace Database\Factories;

use App\Models\PettyCashTopup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory untuk model PettyCashTopup — isi saldo petty cash (M07).
 *
 * @extends Factory<PettyCashTopup>
 */
class PettyCashTopupFactory extends Factory
{
    protected $model = PettyCashTopup::class;

    public function definition(): array
    {
        $year     = now()->year;
        $month    = now()->month;
        $monthPad = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
        $seq      = str_pad($this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT);

        return [
            'topup_number'  => "PCU/{$year}/{$monthPad}/{$seq}",
            'amount'        => $this->faker->numberBetween(50_000, 1_000_000),
            'topup_date'    => now()->toDateString(),
            'description'   => $this->faker->sentence(3),
            'notes'         => null,
            'receipt_image' => null,
            'created_by'    => null,
        ];
    }
}
