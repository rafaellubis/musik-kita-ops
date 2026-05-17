<?php

namespace Database\Factories;

use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory untuk model Teacher — digunakan di test unit/feature.
 * Menghasilkan data guru yang valid sesuai skema tabel teachers.
 *
 * @extends Factory<Teacher>
 */
class TeacherFactory extends Factory
{
    protected $model = Teacher::class;

    public function definition(): array
    {
        // Generate kode guru unik format T-NNN
        $seq = str_pad($this->faker->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT);

        return [
            'code'      => "T-{$seq}",
            'name'      => $this->faker->name(),
            'email'     => $this->faker->optional()->safeEmail(),
            'phone'     => null,
            'bank_name' => null,
            'bank_account' => null,
            'joined_date'  => null,
            'is_active' => true,
            'notes'     => null,
        ];
    }
}
