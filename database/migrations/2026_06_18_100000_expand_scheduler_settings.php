<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tambah key scheduler expanded + integrasi WA + tempo SPP.
     */
    public function up(): void
    {
        $honorDay = '28';
        $oldOffset = DB::table('settings')
            ->where('key', 'scheduler_honor_calculate_days_before_eom')
            ->value('value');

        if ($oldOffset !== null) {
            $honorDay = (string) min(
                28,
                Carbon::now()->endOfMonth()->subDays((int) $oldOffset)->day
            );
        }

        $rows = [
            [
                'key'         => 'scheduler_honor_calculate_day',
                'value'       => $honorDay,
                'description' => 'Tanggal bulanan kalkulasi honor guru (1-31, default 28)',
            ],
            [
                'key'         => 'scheduler_fines_start_day',
                'value'       => '11',
                'description' => 'Tanggal mulai apply denda harian (default 11)',
            ],
            [
                'key'         => 'spp_due_day',
                'value'       => '10',
                'description' => 'Tanggal jatuh tempo SPP setiap bulan (default 10)',
            ],
            [
                'key'         => 'schedule_reminder_poll_minutes',
                'value'       => '15',
                'description' => 'Interval poll reminder jadwal mode hours_before (menit, 5-60)',
            ],
            [
                'key'         => 'scheduler_invoice_reminder_enabled',
                'value'       => 'false',
                'description' => 'Toggle pengingat tagihan WA otomatis',
            ],
            [
                'key'         => 'scheduler_invoice_reminder_days_before_due',
                'value'       => '3',
                'description' => 'Hari sebelum jatuh tempo untuk kirim reminder tagihan (H-N)',
            ],
            [
                'key'         => 'scheduler_invoice_reminder_time',
                'value'       => '10:00',
                'description' => 'Jam harian cek dan kirim reminder tagihan',
            ],
            [
                'key'         => 'scheduler_invoice_reminder_template_id',
                'value'       => '',
                'description' => 'ID template WA untuk reminder tagihan (nullable)',
            ],
            [
                'key'         => 'scheduler_cuti_return_enabled',
                'value'       => 'true',
                'description' => 'Toggle otomatis murid Cuti kembali Aktif',
            ],
            [
                'key'         => 'scheduler_cuti_return_time',
                'value'       => '23:59',
                'description' => 'Jam harian cek murid selesai cuti',
            ],
            [
                'key'         => 'scheduler_staff_payroll_enabled',
                'value'       => 'true',
                'description' => 'Toggle generate slip gaji staff bulanan',
            ],
            [
                'key'         => 'scheduler_staff_payroll_day',
                'value'       => '28',
                'description' => 'Tanggal bulanan generate slip gaji staff',
            ],
            [
                'key'         => 'scheduler_staff_payroll_time',
                'value'       => '07:00',
                'description' => 'Jam generate slip gaji staff',
            ],
            [
                'key'         => 'integration_fonnte_token',
                'value'       => '',
                'description' => 'Token Fonnte (encrypted)',
            ],
            [
                'key'         => 'integration_fonnte_base_url',
                'value'       => 'https://api.fonnte.com',
                'description' => 'Base URL API Fonnte',
            ],
            [
                'key'         => 'integration_wablas_token',
                'value'       => '',
                'description' => 'Token Wablas (encrypted)',
            ],
            [
                'key'         => 'integration_wablas_secret_key',
                'value'       => '',
                'description' => 'Secret key Wablas (encrypted)',
            ],
            [
                'key'         => 'integration_wablas_base_url',
                'value'       => 'https://solo.wablas.com',
                'description' => 'Base URL API Wablas',
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
            'scheduler_honor_calculate_day',
            'scheduler_fines_start_day',
            'spp_due_day',
            'schedule_reminder_poll_minutes',
            'scheduler_invoice_reminder_enabled',
            'scheduler_invoice_reminder_days_before_due',
            'scheduler_invoice_reminder_time',
            'scheduler_invoice_reminder_template_id',
            'scheduler_cuti_return_enabled',
            'scheduler_cuti_return_time',
            'scheduler_staff_payroll_enabled',
            'scheduler_staff_payroll_day',
            'scheduler_staff_payroll_time',
            'integration_fonnte_token',
            'integration_fonnte_base_url',
            'integration_wablas_token',
            'integration_wablas_secret_key',
            'integration_wablas_base_url',
        ])->delete();
    }
};
