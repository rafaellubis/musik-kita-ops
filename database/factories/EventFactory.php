<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory untuk model Event — digunakan di test unit/feature.
 * Menghasilkan data event (Mini Concert / Ujian) yang valid sesuai skema tabel events.
 *
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        // Generate nomor event unik format EVT/YYYY/NNNN
        $year = now()->year;
        $seq  = str_pad($this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT);

        return [
            'event_number' => "EVT/{$year}/{$seq}",
            'name'         => 'Konser KITA ' . $this->faker->monthName() . ' ' . $year,
            'type'         => Event::TYPE_MINI_CONCERT,
            'event_date'   => $this->faker->dateTimeBetween('now', '+3 months')->format('Y-m-d'),
            'notes'        => null,
            'status'       => Event::STATUS_DRAFT,
            'created_by'   => User::factory(),
        ];
    }

    /**
     * State: event sudah selesai.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Event::STATUS_COMPLETED,
        ]);
    }
}
