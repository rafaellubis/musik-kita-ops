<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory untuk model Student — digunakan di test unit/feature.
 * Menghasilkan data murid yang valid sesuai skema tabel students.
 *
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        // Generate kode murid format M-YYYY-NNNN
        $year = now()->year;
        $seq  = str_pad($this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT);

        return [
            'student_code' => "M-{$year}-{$seq}",
            'full_name'    => $this->faker->name(),
            'nickname'     => $this->faker->firstName(),
            'gender'       => $this->faker->randomElement(['L', 'P']),
            'status'       => 'Calon',
            'birth_date'   => null,
            'phone'        => null,
            'email'        => null,
            'address'      => null,
            'notes'        => null,
            'parent_name'  => null,
            'parent_phone' => null,
            'parent_email' => null,
            'parent_relationship' => null,
            'package_id'          => null,
            'assigned_teacher_id' => null,
            'assigned_room_id'    => null,
            'preferred_day'       => null,
            'preferred_time'      => null,
            'trial_date'          => null,
            'active_since'        => null,
            'last_session_at'     => null,
        ];
    }
}
