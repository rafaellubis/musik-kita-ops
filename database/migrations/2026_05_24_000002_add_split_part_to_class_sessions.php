<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            // null = sesi normal, 1 = bagian pertama split, 2 = bagian kedua split
            $table->tinyInteger('split_part')->unsigned()->nullable()->after('origin_session_id');
            // Index untuk query "apakah Part 2 sudah ada untuk origin ini?"
            $table->index(['origin_session_id', 'split_part'], 'cs_origin_split_idx');
        });
    }

    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropIndex('cs_origin_split_idx');
            $table->dropColumn('split_part');
        });
    }
};
