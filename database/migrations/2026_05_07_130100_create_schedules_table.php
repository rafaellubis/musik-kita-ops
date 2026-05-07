<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schedules — jadwal mingguan tetap per enrollment.
 *
 * 1 enrollment biasanya punya 1 schedule aktif. Saat jadwal pindah dalam
 * bulan yang sama (BR-3.9 v1.1), kita update kolom langsung; sesi yang
 * sudah ter-generate tetap memegang start_time/end_time/room_id mereka.
 *
 * day_of_week mengikuti konvensi Carbon::dayOfWeek:
 *   0 = Minggu, 1 = Senin, 2 = Selasa, ..., 6 = Sabtu
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('enrollment_id')
                  ->constrained('enrollments')
                  ->cascadeOnDelete();

            $table->unsignedTinyInteger('day_of_week'); // 0=Minggu..6=Sabtu

            $table->time('start_time');
            $table->time('end_time');

            $table->foreignId('room_id')
                  ->nullable()
                  ->constrained('rooms')
                  ->nullOnDelete();

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('enrollment_id');
            // Untuk lookup konflik guru/ruang
            $table->index(['day_of_week', 'start_time']);
            $table->index(['room_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
