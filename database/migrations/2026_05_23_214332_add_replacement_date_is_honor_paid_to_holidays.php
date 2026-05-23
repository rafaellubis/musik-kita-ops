<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            // replacement_date: tanggal sesi pengganti (dalam bulan yang sama)
            // unique: tidak boleh dua holiday berbagi tanggal pengganti yang sama
            $table->date('replacement_date')->nullable()->unique()->after('date');

            // is_honor_paid: false untuk Internal/Konser KITA — honor Rp 0
            $table->boolean('is_honor_paid')->default(true)->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->dropUnique(['replacement_date']);
            $table->dropColumn(['replacement_date', 'is_honor_paid']);
        });
    }
};
