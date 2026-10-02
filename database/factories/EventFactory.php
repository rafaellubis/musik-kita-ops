<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Event> */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $eventDate = $this->faker->dateTimeBetween('+1 week', '+3 months');
        $year = (int) $eventDate->format('Y');
        $sequence = str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT);

        return [
            'event_number' => "EVT/{$year}/{$sequence}",
            'name' => 'Konser KITA '.$this->faker->monthName().' '.$year,
            'type' => Event::TYPE_MINI_CONCERT,
            'event_date' => $eventDate->format('Y-m-d'),
            'payment_due_date' => (clone $eventDate)->modify('-7 days')->format('Y-m-d'),
            'status' => Event::STATUS_DRAFT,
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => Event::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => Event::STATUS_COMPLETED,
            'published_at' => now()->subDay(),
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => Event::STATUS_CANCELLED,
            'published_at' => now()->subDay(),
            'cancelled_at' => now(),
            'cancellation_reason' => 'Event dibatalkan untuk pengujian.',
        ]);
    }
}
