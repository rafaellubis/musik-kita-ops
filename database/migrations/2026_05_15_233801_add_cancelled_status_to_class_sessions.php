<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL tidak bisa ALTER ENUM kolom langsung — harus re-define enum.
        // Tambah 'CANCELLED' di akhir daftar status.
        DB::statement("ALTER TABLE class_sessions MODIFY COLUMN status ENUM(
            'SCHEDULED',
            'HADIR',
            'HADIR_TERLAMBAT',
            'IZIN_RESCHEDULE',
            'IZIN_VIDEO',
            'HANGUS',
            'LIBUR',
            'DIGANTI',
            'CANCELLED'
        ) NOT NULL DEFAULT 'SCHEDULED'");
    }

    public function down(): void
    {
        // Hapus CANCELLED dari enum (hati-hati: jika ada row CANCELLED, down() akan error)
        DB::statement("ALTER TABLE class_sessions MODIFY COLUMN status ENUM(
            'SCHEDULED',
            'HADIR',
            'HADIR_TERLAMBAT',
            'IZIN_RESCHEDULE',
            'IZIN_VIDEO',
            'HANGUS',
            'LIBUR',
            'DIGANTI'
        ) NOT NULL DEFAULT 'SCHEDULED'");
    }
};
