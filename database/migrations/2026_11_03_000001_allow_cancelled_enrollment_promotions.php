<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Indeks pengganti harus tersedia sebelum indeks pendukung foreign key dilepas.
        Schema::table('enrollment_promotions', function (Blueprint $table) {
            $table->index('source_enrollment_id');
        });
        Schema::table('enrollment_promotions', function (Blueprint $table) {
            $table->dropUnique(['source_enrollment_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('enrollment_promotions')->select('source_enrollment_id')
            ->groupBy('source_enrollment_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Migrasi tidak dapat dibatalkan karena riwayat kenaikan level memiliki kelas asal berulang.');
        }

        Schema::table('enrollment_promotions', function (Blueprint $table) {
            $table->unique('source_enrollment_id');
        });
        Schema::table('enrollment_promotions', function (Blueprint $table) {
            $table->dropIndex(['source_enrollment_id']);
        });
    }
};
