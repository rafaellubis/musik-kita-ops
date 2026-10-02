<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventTeacherAssignment;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EventTeacherAssignment> */
class EventTeacherAssignmentFactory extends Factory
{
    protected $model = EventTeacherAssignment::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'teacher_id' => Teacher::factory(),
            'attendance_status' => EventTeacherAssignment::ATTENDANCE_PENDING,
            'confirmed_at' => null,
            'confirmed_by' => null,
            'absence_reason' => null,
        ];
    }

    public function hadir(): static
    {
        return $this->state(fn () => [
            'attendance_status' => EventTeacherAssignment::ATTENDANCE_HADIR,
            'confirmed_at' => now(),
        ]);
    }

    public function tidakHadir(): static
    {
        return $this->state(fn () => [
            'attendance_status' => EventTeacherAssignment::ATTENDANCE_TIDAK_HADIR,
            'confirmed_at' => now(),
            'absence_reason' => 'Guru berhalangan hadir.',
        ]);
    }
}
