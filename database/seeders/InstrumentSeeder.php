<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Instrument;

class InstrumentSeeder extends Seeder
{
    public function run(): void
    {
        $instruments = [
            ['code' => 'PIANO',  'name' => 'Piano',      'description' => 'Piano Classic / POP / Keyboard',                       'sort_order' => 1],
            ['code' => 'GITAR',  'name' => 'Gitar',      'description' => 'Gitar Elektrik / Akustik / Classic',                   'sort_order' => 2],
            ['code' => 'DRUM',   'name' => 'Drum',       'description' => 'Drum Set',                                             'sort_order' => 3],
            ['code' => 'VOCAL',  'name' => 'Vocal',      'description' => 'Vocal / Solo Vokal',                                   'sort_order' => 4],
            ['code' => 'BASS',   'name' => 'Bass',       'description' => 'Bass Elektrik',                                        'sort_order' => 5],
            ['code' => 'VIOLIN', 'name' => 'Violin',     'description' => 'Violin (Biola)',                                       'sort_order' => 6],
            ['code' => 'KIDS',   'name' => 'Kids Class', 'description' => 'Kelas musik grup untuk anak usia 4 — <5 tahun',        'sort_order' => 7],
            ['code' => 'SAX',    'name' => 'Saxophone',  'description' => 'Saxophone (belum ada guru aktif, paket masih ditawarkan)', 'sort_order' => 8],
        ];

        foreach ($instruments as $data) {
            Instrument::firstOrCreate(
                ['code' => $data['code']],
                array_merge($data, ['is_active' => true])
            );
        }
    }
}