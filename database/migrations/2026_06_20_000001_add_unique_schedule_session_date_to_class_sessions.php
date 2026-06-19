<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambahkan UNIQUE constraint (schedule_id, session_date) di class_sessions.
 *
 * Tujuan: mencegah duplikasi sesi yang ter-generate oleh SessionGeneratorService
 * saat terjadi race condition (dua request bersamaan) atau koneksi terputus lalu
 * di-retry. MySQL mengizinkan multiple NULL di unique index, jadi sesi ad-hoc
 * (trial, reschedule, manual) dengan schedule_id=NULL tidak terpengaruh.
 *
 * Migrasi ini juga menghapus index biasa (schedule_id, session_date) yang sudah
 * ada — karena UNIQUE index secara otomatis berfungsi sebagai index biasa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            // Tambahkan UNIQUE constraint dulu — MySQL butuh setidaknya satu
            // index yang meng-cover kolom FK (schedule_id). Setelah UNIQUE
            // index ada, index biasa bisa dihapus dengan aman.
            $table->unique(['schedule_id', 'session_date'], 'class_sessions_schedule_date_unique');

            // Hapus index biasa yang sudah ada (dibuat di migration awal) —
            // UNIQUE index di atas sudah meng-cover fungsinya
            $table->dropIndex('class_sessions_schedule_id_session_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropUnique('class_sessions_schedule_date_unique');
            $table->index(['schedule_id', 'session_date']);
        });
    }
};
