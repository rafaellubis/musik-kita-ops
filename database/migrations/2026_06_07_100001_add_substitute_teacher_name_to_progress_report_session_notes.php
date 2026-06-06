<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progress_report_session_notes', function (Blueprint $table) {
            $table->string('substitute_teacher_name')->nullable()->after('session_rating');
        });
    }

    public function down(): void
    {
        Schema::table('progress_report_session_notes', function (Blueprint $table) {
            $table->dropColumn('substitute_teacher_name');
        });
    }
};
