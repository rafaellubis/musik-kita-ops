<?php

namespace Database\Factories;

use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory untuk model Room — digunakan di test unit/feature.
 * Menghasilkan data ruangan yang valid sesuai skema tabel rooms.
 *
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        // Kode ruang unik format R-NN agar tidak bentrok antar test
        $seq = str_pad($this->faker->unique()->numberBetween(1, 99), 2, '0', STR_PAD_LEFT);

        return [
            'code'                  => "R{$seq}",
            'name'                  => "Studio {$seq}",
            'capacity'              => 1,
            'supported_instruments' => [],
            'notes'                 => null,
            'is_active'             => true,
        ];
    }
}
