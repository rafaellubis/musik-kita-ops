<?php

use App\Models\ClassSession;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Sesi pengganti kalender akademik tidak lagi pre-fill honor saat generate.
     * Bersihkan honor pada row SCHEDULED yang masih menyimpan nilai lama.
     */
    public function up(): void
    {
        ClassSession::query()
            ->where('status', ClassSession::STATUS_SCHEDULED)
            ->whereNotNull('origin_session_id')
            ->whereNull('split_part')
            ->where('notes', 'Sesi pengganti dari tanggal libur')
            ->update([
                'honor_code'   => null,
                'honor_amount' => null,
            ]);
    }

    public function down(): void
    {
        // Tidak bisa restore nilai honor lama — data sudah di-null-kan
    }
};
