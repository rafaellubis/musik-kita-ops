<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_honor_slips', function (Blueprint $table) {
            // Honor event diinput manual oleh Owner — tidak ada formula otomatis
            $table->unsignedInteger('event_honor')->default(0)->after('base_honor');
            $table->string('event_honor_note')->nullable()->after('event_honor');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_honor_slips', function (Blueprint $table) {
            $table->dropColumn(['event_honor', 'event_honor_note']);
        });
    }
};
