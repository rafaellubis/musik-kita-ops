<?php

namespace Database\Factories;

use App\Models\Instrument;
use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory untuk model Package — digunakan di test unit/feature.
 * Menghasilkan data paket yang valid sesuai skema tabel packages.
 * Secara default membuat Instrument baru via InstrumentFactory.
 *
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    protected $model = Package::class;

    public function definition(): array
    {
        // Kode paket unik format PKG-NNN
        $seq = str_pad($this->faker->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT);

        return [
            'code'            => "PKG-{$seq}",
            'instrument_id'   => Instrument::factory(),
            'class_type'      => 'REGULER',
            'grade'           => 'Basic',
            'duration_min'    => 30,
            'price_per_month' => 340000,
            'is_active'       => true,
            'sort_order'      => 0,
        ];
    }
}
