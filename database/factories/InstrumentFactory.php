<?php

namespace Database\Factories;

use App\Models\Instrument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory untuk model Instrument — digunakan di test unit/feature.
 * Menghasilkan data instrumen yang valid sesuai skema tabel instruments.
 *
 * @extends Factory<Instrument>
 */
class InstrumentFactory extends Factory
{
    protected $model = Instrument::class;

    public function definition(): array
    {
        // Kode instrumen unik format INS-NNN
        $seq = str_pad($this->faker->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT);

        return [
            'code'        => "INS-{$seq}",
            'name'        => $this->faker->word(),
            'description' => null,
            'is_active'   => true,
            'sort_order'  => 0,
        ];
    }
}
