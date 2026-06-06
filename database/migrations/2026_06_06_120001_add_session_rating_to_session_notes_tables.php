<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_teacher_notes', function (Blueprint $table) {
            $table->unsignedTinyInteger('session_rating')->nullable()->after('notes');
        });

        Schema::table('progress_report_session_notes', function (Blueprint $table) {
            $table->unsignedTinyInteger('session_rating')->nullable()->after('session_sequence');
        });
    }

    public function down(): void
    {
        Schema::table('session_teacher_notes', function (Blueprint $table) {
            $table->dropColumn('session_rating');
        });

        Schema::table('progress_report_session_notes', function (Blueprint $table) {
            $table->dropColumn('session_rating');
        });
    }
};
