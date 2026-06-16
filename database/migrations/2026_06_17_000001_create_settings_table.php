<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('key')->unique();
            $blueprint->text('value');
            $blueprint->string('description')->nullable();
            $blueprint->timestamps();
        });

        // Masukkan data awal (default settings) untuk scheduler & WA reminder
        DB::table('settings')->insert([
            // 1. Generator Sesi Mingguan
            [
                'key'         => 'scheduler_sessions_generate_enabled',
                'value'       => 'true',
                'description' => 'Mengaktifkan generator sesi mingguan otomatis bulanan',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'scheduler_sessions_generate_day',
                'value'         => '25',
                'description' => 'Tanggal pelaksanaan generator sesi mingguan (1-31)',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'scheduler_sessions_generate_time',
                'value'         => '06:00',
                'description' => 'Jam pelaksanaan generator sesi mingguan (HH:MM)',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],

            // 2. Generator SPP Bulanan
            [
                'key'         => 'scheduler_invoices_generate_enabled',
                'value'       => 'true',
                'description' => 'Mengaktifkan generator SPP bulanan otomatis',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'scheduler_invoices_generate_day',
                'value'         => '1',
                'description' => 'Tanggal pelaksanaan generator SPP bulanan (1-31)',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'scheduler_invoices_generate_time',
                'value'         => '06:00',
                'description' => 'Jam pelaksanaan generator SPP bulanan (HH:MM)',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],

            // 3. Kalkulasi Honor Guru
            [
                'key'         => 'scheduler_honor_calculate_enabled',
                'value'       => 'true',
                'description' => 'Mengaktifkan kalkulasi honor guru otomatis di akhir bulan',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'scheduler_honor_calculate_time',
                'value'         => '06:00',
                'description' => 'Jam pelaksanaan kalkulasi honor guru (HH:MM)',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],

            // 4. Apply Denda Harian
            [
                'key'         => 'scheduler_fines_apply_enabled',
                'value'       => 'true',
                'description' => 'Mengaktifkan apply denda keterlambatan SPP otomatis',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'scheduler_fines_apply_time',
                'value'         => '06:00',
                'description' => 'Jam pelaksanaan apply denda (HH:MM)',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],

            // 5. Deteksi Murid Overdue
            [
                'key'         => 'scheduler_overdue_check_enabled',
                'value'       => 'true',
                'description' => 'Mengaktifkan deteksi status murid overdue otomatis',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'scheduler_overdue_check_time',
                'value'         => '06:05',
                'description' => 'Jam pelaksanaan cek murid overdue (HH:MM)',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],

            // 6. WhatsApp Reminders
            [
                'key'         => 'schedule_reminder_enabled',
                'value'       => 'false',
                'description' => 'Mengaktifkan pengingat jadwal pelajaran via WhatsApp',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'schedule_reminder_mode',
                'value'       => 'day_before',
                'description' => 'Mode pengingat jadwal (day_before, same_day, hours_before)',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'schedule_reminder_send_time',
                'value'       => '18:00',
                'description' => 'Jam pengiriman pesan pengingat jadwal harian (HH:MM)',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'schedule_reminder_hours_before',
                'value'       => '2',
                'description' => 'Waktu pengiriman pengingat jadwal (N jam sebelum mulai)',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
