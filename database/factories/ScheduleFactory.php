<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory untuk model Schedule — digunakan di test unit/feature.
 * Menghasilkan jadwal mingguan tetap yang valid.
 * day_of_week: 0=Minggu, 1=Senin, ..., 6=Sabtu (konvensi Carbon).
 *
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    protected $model = Schedule::class;

    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'day_of_week'   => $this->faker->numberBetween(1, 6), // Senin-Sabtu
            'start_time'    => '10:00:00',
            'end_time'      => '10:30:00',
            'room_id'       => null,
            'is_active'     => true,
            'notes'         => null,
        ];
    }
}
