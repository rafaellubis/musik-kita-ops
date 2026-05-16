<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            // Tambah kolom baru setelah capacity
            $table->json('supported_instruments')->nullable()->after('capacity');

            // Hapus 3 boolean lama
            $table->dropColumn(['has_piano', 'has_drum', 'has_amplifier']);
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('supported_instruments');
            $table->boolean('has_piano')->default(false)->after('capacity');
            $table->boolean('has_drum')->default(false)->after('has_piano');
            $table->boolean('has_amplifier')->default(false)->after('has_drum');
        });
    }
};
