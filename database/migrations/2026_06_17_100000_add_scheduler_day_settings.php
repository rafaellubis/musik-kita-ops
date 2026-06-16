<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tambah key scheduler untuk tanggal overdue dan offset honor H-N.
     */
    public function up(): void
    {
        $rows = [
            [
                'key'         => 'scheduler_overdue_check_day',
                'value'       => '1',
                'description' => 'Tanggal pelaksanaan cek murid overdue (1-31)',
            ],
            [
                'key'         => 'scheduler_honor_calculate_days_before_eom',
                'value'       => '2',
                'description' => 'Hari sebelum akhir bulan untuk kalkulasi honor (default 2 = H-2)',
            ],
        ];

        foreach ($rows as $row) {
            if (! DB::table('settings')->where('key', $row['key'])->exists()) {
                DB::table('settings')->insert(array_merge($row, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'scheduler_overdue_check_day',
            'scheduler_honor_calculate_days_before_eom',
        ])->delete();
    }
};
