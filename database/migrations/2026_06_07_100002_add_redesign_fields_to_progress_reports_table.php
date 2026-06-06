<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progress_reports', function (Blueprint $table) {
            $table->unsignedTinyInteger('rating_teknik')->nullable()->after('repertoire');
            $table->unsignedTinyInteger('rating_materi')->nullable()->after('rating_teknik');
            $table->unsignedTinyInteger('rating_reading')->nullable()->after('rating_materi');
            $table->unsignedTinyInteger('rating_repertoar')->nullable()->after('rating_reading');
            $table->text('catatan_perkembangan_musikal')->nullable()->after('rating_repertoar');
            $table->text('catatan_karakter')->nullable()->after('catatan_perkembangan_musikal');
            $table->enum('kesimpulan_progress', [
                'PERLU_PENDAMPINGAN', 'CUKUP', 'BAIK', 'SANGAT_BAIK',
            ])->nullable()->after('catatan_karakter');
            $table->unsignedTinyInteger('progress_percent')->nullable()->after('kesimpulan_progress');
        });
    }

    public function down(): void
    {
        Schema::table('progress_reports', function (Blueprint $table) {
            $table->dropColumn([
                'rating_teknik', 'rating_materi', 'rating_reading', 'rating_repertoar',
                'catatan_perkembangan_musikal', 'catatan_karakter',
                'kesimpulan_progress', 'progress_percent',
            ]);
        });
    }
};
