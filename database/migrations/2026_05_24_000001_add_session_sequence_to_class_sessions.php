<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            // Nomor urut sesi dalam bulan untuk murid ini (1–4).
            // NULL untuk sesi LIBUR yang punya replacement_date.
            $table->tinyInteger('session_sequence')->unsigned()->nullable()->after('honor_amount');

            // FK ke sesi asal saat reschedule atau pengganti holiday.
            // nullOnDelete: jika sesi asal dihapus, kolom ini jadi NULL (tidak cascade).
            $table->foreignId('origin_session_id')->nullable()->after('session_sequence')
                  ->constrained('class_sessions')->nullOnDelete();
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
            if ($fkExists) {
                $table->dropForeign(['origin_session_id']);
            }
            $table->dropColumn(['session_sequence', 'origin_session_id']);
        });
    }
};
