<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tambah nilai TRIAL ke enum enrollments.status.
 * TRIAL dipakai saat murid Calon sedang menjalani sesi trial (belum jadi murid aktif).
 * Enrollment TRIAL → COMPLETED saat murid mundur atau lanjut jadi ACTIVE.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE enrollments
            MODIFY COLUMN status
            ENUM('ACTIVE','ON_LEAVE','INACTIVE','COMPLETED','TRIAL')
            NOT NULL DEFAULT 'ACTIVE'
        ");
    }

    public function down(): void
    {
        // Pastikan tidak ada row TRIAL sebelum rollback
        DB::table('enrollments')->where('status', 'TRIAL')->update(['status' => 'INACTIVE']);

        DB::statement("
            ALTER TABLE enrollments
            MODIFY COLUMN status
            ENUM('ACTIVE','ON_LEAVE','INACTIVE','COMPLETED')
            NOT NULL DEFAULT 'ACTIVE'
        ");
    }
};
