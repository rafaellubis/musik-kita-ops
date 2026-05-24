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
        // Check if FK exists before dropping
        $fkExists = \DB::selectOne("
            SELECT CONSTRAINT_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_NAME = 'class_sessions'
              AND COLUMN_NAME = 'origin_session_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ") !== null;

        Schema::table('class_sessions', function (Blueprint $table) use ($fkExists) {
            // FK constraint harus di-drop lebih dulu sebelum composite index bisa di-drop
            // (MySQL error 1553: Cannot drop index — needed in a foreign key constraint)
            if ($fkExists) {
                $table->dropForeign(['origin_session_id']);
            }

            // Drop composite index
            if (Schema::hasIndex('class_sessions', 'cs_origin_split_idx')) {
                $table->dropIndex('cs_origin_split_idx');
            }

            // Drop column split_part
            if (Schema::hasColumn('class_sessions', 'split_part')) {
                $table->dropColumn('split_part');
            }
        });

        // Recreate FK yang didrop di atas (agar migration 000001 tetap valid)
        if ($fkExists) {
            Schema::table('class_sessions', function (Blueprint $table) {
                $table->foreignId('origin_session_id')->nullable()
                      ->constrained('class_sessions')->nullOnDelete();
            });
        }
    }
};
