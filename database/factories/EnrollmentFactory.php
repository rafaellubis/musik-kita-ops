<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory untuk model Enrollment — digunakan di test unit/feature.
 * Secara default membuat Student, Package, dan Teacher baru via factory masing-masing.
 *
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    public function definition(): array
    {
        return [
            'student_id'     => Student::factory(),
            'package_id'     => Package::factory(),
            'teacher_id'     => Teacher::factory(),
            'effective_date' => today(),
            'end_date'       => null,
            'status'         => 'ACTIVE',
            'is_primary'     => false,
            'notes'          => null,
        ];
    }
}
