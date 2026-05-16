<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        // Data ruangan aktual studio — sumber: CLAUDE.md
        $rooms = [
            [
                'code' => 'R1', 'name' => 'Studio 1', 'capacity' => 4,
                'supported_instruments' => ['Vocal', 'Kids Class', 'Gitar'],
                'notes' => 'Ruang Kids Class — kapasitas 4 anak',
            ],
            [
                'code' => 'R2', 'name' => 'Studio 2', 'capacity' => 1,
                'supported_instruments' => ['Piano', 'Vocal', 'Gitar'],
                'notes' => null,
            ],
            [
                'code' => 'R3', 'name' => 'Studio 3', 'capacity' => 1,
                'supported_instruments' => ['Piano'],
                'notes' => null,
            ],
            [
                'code' => 'R4', 'name' => 'Studio 4', 'capacity' => 1,
                'supported_instruments' => ['Piano', 'Gitar'],
                'notes' => null,
            ],
            [
                'code' => 'R5', 'name' => 'Studio 5', 'capacity' => 1,
                'supported_instruments' => ['Bass', 'Gitar'],
                'notes' => null,
            ],
            [
                'code' => 'R6', 'name' => 'Studio 6', 'capacity' => 1,
                'supported_instruments' => ['Violin'],
                'notes' => null,
            ],
            [
                'code' => 'R7', 'name' => 'Studio 7', 'capacity' => 1,
                'supported_instruments' => ['Piano', 'Vocal'],
                'notes' => null,
            ],
            [
                'code' => 'R8', 'name' => 'Studio 8', 'capacity' => 1,
                'supported_instruments' => ['Drum'],
                'notes' => null,
            ],
            [
                'code' => 'R9', 'name' => 'Studio 9', 'capacity' => 1,
                'supported_instruments' => ['Drum'],
                'notes' => null,
            ],
        ];

        foreach ($rooms as $data) {
            // updateOrCreate: aman dijalankan ulang tanpa duplikat
            Room::updateOrCreate(
                ['code' => $data['code']],
                array_merge($data, ['is_active' => true])
            );
        }
    }
}
