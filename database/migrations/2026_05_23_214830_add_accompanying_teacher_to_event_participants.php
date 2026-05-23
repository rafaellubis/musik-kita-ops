<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('event_participants', function (Blueprint $table) {
            // Guru yang mendampingi murid di event (Konser KITA)
            // NULL = tidak ada pendamping / guru tidak bisa hadir
            $table->foreignId('accompanying_teacher_id')
                ->nullable()
                ->after('enrollment_id')
                ->constrained('teachers')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_participants', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Teacher::class, 'accompanying_teacher_id');
            $table->dropColumn('accompanying_teacher_id');
        });
    }
};
