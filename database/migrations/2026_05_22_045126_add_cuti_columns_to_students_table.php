<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Tanggal mulai cuti — diisi saat ajukanCuti(), di-clear saat aktifkanDariCuti()
            $table->date('cuti_from')->nullable()->after('active_since');
            // Tanggal akhir cuti — dipakai untuk enforce tidak bisa akhiri lebih awal
            $table->date('cuti_until')->nullable()->after('cuti_from');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['cuti_from', 'cuti_until']);
        });
    }
};
