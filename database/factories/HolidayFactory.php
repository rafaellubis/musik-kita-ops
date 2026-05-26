<?php

namespace Database\Factories;

use App\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory untuk model Holiday — digunakan di test unit/feature.
 * Menghasilkan data hari libur yang valid sesuai skema tabel holidays.
 *
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    protected $model = Holiday::class;

    public function definition(): array
    {
        return [
            'date'             => $this->faker->unique()->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'name'             => $this->faker->words(3, true),
            'type'             => $this->faker->randomElement(['Nasional', 'Cuti Bersama']),
            'replacement_date' => null,
            'is_honor_paid'    => true,
            'is_active'        => true,
            'notes'            => null,
        ];
    }

    /**
     * State: hari libur internal (Konser KITA, event studio).
     * Honor guru Rp 0 untuk sesi LIBUR ini.
     */
    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'type'          => 'Internal',
            'is_honor_paid' => false,
        ]);
    }
}
