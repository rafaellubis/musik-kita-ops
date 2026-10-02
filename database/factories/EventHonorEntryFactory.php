<?php

namespace Database\Factories;

use App\Models\EventHonorEntry;
use App\Models\EventParticipant;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EventHonorEntry> */
class EventHonorEntryFactory extends Factory
{
    protected $model = EventHonorEntry::class;

    public function definition(): array
    {
        return [
            'event_participant_id' => EventParticipant::factory(),
            'event_id' => fn (array $attributes) => EventParticipant::findOrFail($attributes['event_participant_id'])->event_id,
            'teacher_id' => Teacher::factory(),
            'snapshot_package_id' => null,
            'snapshot_package_code' => 'REG-PIANO-BASIC',
            'snapshot_package_price' => 340000,
            'honor_formula' => 'package_price * 0.5 / 4',
            'amount' => 42500,
            'honor_slip_id' => null,
            'status' => EventHonorEntry::STATUS_POSTED,
            'posted_at' => now(),
            'posted_by' => null,
        ];
    }

    public function posted(): static
    {
        return $this->state(fn () => [
            'status' => EventHonorEntry::STATUS_POSTED,
            'posted_at' => now(),
            'reversed_at' => null,
            'reversal_reason' => null,
        ]);
    }

    public function reversed(): static
    {
        return $this->state(fn () => [
            'status' => EventHonorEntry::STATUS_REVERSED,
            'reversed_at' => now(),
            'reversal_reason' => 'Posting honor dibalik untuk pengujian.',
        ]);
    }
}
