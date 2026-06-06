<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progress_reports', function (Blueprint $table) {
            $table->text('catatan_teknik')->nullable()->after('rating_repertoar');
            $table->text('catatan_materi')->nullable()->after('catatan_teknik');
            $table->text('catatan_reading')->nullable()->after('catatan_materi');
            $table->text('catatan_repertoar')->nullable()->after('catatan_reading');
        });
    }

    public function down(): void
    {
        Schema::table('progress_reports', function (Blueprint $table) {
            $table->dropColumn([
                'catatan_teknik',
                'catatan_materi',
                'catatan_reading',
                'catatan_repertoar',
            ]);
        });
    }
};
