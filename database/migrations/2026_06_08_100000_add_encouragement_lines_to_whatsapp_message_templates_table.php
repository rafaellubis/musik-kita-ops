<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Pesan semangat per rating untuk template laporan sesi (JSON). */
    public function up(): void
    {
        Schema::table('whatsapp_message_templates', function (Blueprint $table) {
            $table->json('encouragement_lines')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_message_templates', function (Blueprint $table) {
            $table->dropColumn('encouragement_lines');
        });
    }
};
