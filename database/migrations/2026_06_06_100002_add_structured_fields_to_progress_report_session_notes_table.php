<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progress_report_session_notes', function (Blueprint $table) {
            $table->foreignId('class_session_id')
                ->nullable()
                ->after('progress_report_id')
                ->constrained('class_sessions')
                ->nullOnDelete();
            $table->text('material_learned')->nullable()->after('notes');
            $table->text('homework_notes')->nullable()->after('material_learned');
            $table->unsignedTinyInteger('session_sequence')->nullable()->after('homework_notes');
        });
    }

    public function down(): void
    {
        Schema::table('progress_report_session_notes', function (Blueprint $table) {
            $table->dropForeign(['class_session_id']);
            $table->dropColumn([
                'class_session_id',
                'material_learned',
                'homework_notes',
                'session_sequence',
            ]);
        });
    }
};
