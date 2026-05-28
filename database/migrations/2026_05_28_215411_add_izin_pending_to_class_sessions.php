<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE class_sessions MODIFY COLUMN status ENUM(
            'SCHEDULED','HADIR','HADIR_TERLAMBAT',
            'IZIN_RESCHEDULE','IZIN_PENDING',
            'IZIN_VIDEO','HANGUS','LIBUR','DIGANTI','CANCELLED'
        ) NOT NULL DEFAULT 'SCHEDULED'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE class_sessions MODIFY COLUMN status ENUM(
            'SCHEDULED','HADIR','HADIR_TERLAMBAT',
            'IZIN_RESCHEDULE',
            'IZIN_VIDEO','HANGUS','LIBUR','DIGANTI','CANCELLED'
        ) NOT NULL DEFAULT 'SCHEDULED'");
    }
};
