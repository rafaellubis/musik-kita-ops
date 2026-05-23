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
            // Pakai tanggal lampau agar test yang generate sesi untuk bulan masa lalu
            // (mis. Januari–April 2026) tidak terblokir guard enrollment boundary.
            'effective_date' => '2025-01-01',
            'end_date'       => null,
            'status'         => 'ACTIVE',
            'is_primary'     => false,
            'notes'          => null,
        ];
    }
}
