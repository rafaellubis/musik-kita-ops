<?php

namespace Database\Factories;

use App\Models\PayrollConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory untuk model PayrollConfig — digunakan di test unit/feature.
 * Menghasilkan data konfigurasi payroll yang valid sesuai skema tabel payroll_configs.
 *
 * @extends Factory<PayrollConfig>
 */
class PayrollConfigFactory extends Factory
{
    protected $model = PayrollConfig::class;

    public function definition(): array
    {
        return [
            'scenario_code'    => $this->faker->unique()->lexify('SC_???'),
            'scenario_name'    => $this->faker->words(3, true),
            'formula_type'     => 'FIXED',
            'value_or_formula' => '250000',
            'description'      => null,
            'is_active'        => true,
        ];
    }
}
