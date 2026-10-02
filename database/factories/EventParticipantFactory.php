<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\Event;
use App\Models\EventParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EventParticipant> */
class EventParticipantFactory extends Factory
{
    protected $model = EventParticipant::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'enrollment_id' => Enrollment::factory(),
            'student_id' => fn (array $attributes) => Enrollment::findOrFail($attributes['enrollment_id'])->student_id,
            'status' => EventParticipant::STATUS_ACTIVE,
            'active_teacher_assignment_id' => null,
            'invoice_id' => null,
            'invoice_item_id' => null,
            'snapshot_package_id' => null,
            'snapshot_package_code' => 'REG-PIANO-BASIC',
            'snapshot_instrument_name' => 'Piano',
            'snapshot_class_type' => 'REGULER',
            'snapshot_grade' => 'BASIC',
            'snapshot_duration_min' => 30,
            'snapshot_package_price' => 340000,
            'snapshot_mc_fee' => 295000,
            'snapshot_honor_amount' => 42500,
            'attendance_status' => EventParticipant::ATTENDANCE_PENDING,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => EventParticipant::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Partisipasi dibatalkan untuk pengujian.',
        ]);
    }
}
