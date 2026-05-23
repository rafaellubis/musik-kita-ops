<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah nilai TRIAL ke enum enrollments.status.
 * TRIAL dipakai saat murid Calon sedang menjalani sesi trial (belum jadi murid aktif).
 * Enrollment TRIAL → COMPLETED saat murid mundur atau lanjut jadi ACTIVE.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite: pakai ->change() via Doctrine/DBAL untuk update CHECK constraint
            Schema::table('enrollments', function (Blueprint $table) {
                $table->enum('status', ['ACTIVE', 'ON_LEAVE', 'INACTIVE', 'COMPLETED', 'TRIAL'])
                      ->default('ACTIVE')->change();
            });
            return;
        }

        DB::statement("
            ALTER TABLE enrollments
            MODIFY COLUMN status
            ENUM('ACTIVE','ON_LEAVE','INACTIVE','COMPLETED','TRIAL')
            NOT NULL DEFAULT 'ACTIVE'
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->enum('status', ['ACTIVE', 'ON_LEAVE', 'INACTIVE', 'COMPLETED'])
                      ->default('ACTIVE')->change();
            });
            return;
        }

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
