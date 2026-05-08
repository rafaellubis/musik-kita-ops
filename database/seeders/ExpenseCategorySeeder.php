<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

/**
 * Seed kategori pengeluaran default studio (M07).
 * Idempotent — gunakan firstOrCreate agar aman dijalankan ulang.
 */
class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['code' => 'SEWA',       'name' => 'Sewa Tempat',          'sort_order' => 1],
            ['code' => 'LISTRIK',    'name' => 'Listrik',              'sort_order' => 2],
            ['code' => 'AIR',        'name' => 'Air / PDAM',           'sort_order' => 3],
            ['code' => 'INTERNET',   'name' => 'Internet & Telepon',   'sort_order' => 4],
            ['code' => 'GAJI_STAFF', 'name' => 'Gaji Staff Non-Guru',  'sort_order' => 5],
            ['code' => 'PERALATAN',  'name' => 'Peralatan & Servis',   'sort_order' => 6],
            ['code' => 'ATK',        'name' => 'Alat Tulis & Kantor',  'sort_order' => 7],
            ['code' => 'KONSUMSI',   'name' => 'Konsumsi & Snack',     'sort_order' => 8],
            ['code' => 'PROMOSI',    'name' => 'Promosi & Marketing',  'sort_order' => 9],
            ['code' => 'LAINNYA',    'name' => 'Lain-lain',            'sort_order' => 10],
        ];

        foreach ($categories as $data) {
            ExpenseCategory::firstOrCreate(
                ['code' => $data['code']],
                [
                    'name'       => $data['name'],
                    'is_active'  => true,
                    'sort_order' => $data['sort_order'],
                ]
            );
        }
    }
}
