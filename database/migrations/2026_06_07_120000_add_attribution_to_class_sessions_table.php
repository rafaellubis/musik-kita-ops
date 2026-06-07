<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->unsignedTinyInteger('attribution_month')->nullable()->after('session_sequence');
            $table->unsignedSmallInteger('attribution_year')->nullable()->after('attribution_month');
            $table->enum('session_type', ['REGULAR', 'MANUAL'])->default('REGULAR')->after('attribution_year');
        });

        DB::table('class_sessions')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $date = \Carbon\Carbon::parse($row->session_date);
                DB::table('class_sessions')->where('id', $row->id)->update([
                    'attribution_month' => $date->month,
                    'attribution_year'  => $date->year,
                    'session_type'      => 'REGULAR',
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropColumn(['attribution_month', 'attribution_year', 'session_type']);
        });
    }
};
