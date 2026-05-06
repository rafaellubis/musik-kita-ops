<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Room;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = [
            ['R1', 'Studio 1', 1, true, false, true, 'Studio piano standar'],
            ['R2', 'Studio 2', 1, true, false, true, 'Studio piano standar'],
            ['R3', 'Studio 3', 1, true, false, true, 'Studio piano standar'],
            ['R4', 'Studio Drum', 1, false, true, true, 'Khusus drum kit'],
            ['R5', 'Studio Vocal', 1, true, false, true, 'Untuk vocal + piano accompaniment'],
            ['R6', 'Studio Kids Class', 4, true, false, true, 'Ruang grup kids class kapasitas 4 anak'],
        ];

        foreach ($rooms as [$code, $name, $cap, $piano, $drum, $amp, $notes]) {
            Room::firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'capacity' => $cap,
                    'has_piano' => $piano,
                    'has_drum' => $drum,
                    'has_amplifier' => $amp,
                    'notes' => $notes,
                    'is_active' => true,
                ]
            );
        }
    }
}