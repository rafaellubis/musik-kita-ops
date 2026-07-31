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
            $table->string('pending_requested_by', 10)
                ->nullable()
                ->after('status');

            $table->index(
                ['status', 'pending_requested_by'],
                'class_sessions_status_pending_requester_index'
            );
        });

        // Pending lama berasal dari alur izin murid sebelum pilihan peminta tersedia.
        DB::table('class_sessions')
            ->where('status', 'IZIN_PENDING')
            ->whereNull('pending_requested_by')
            ->update(['pending_requested_by' => 'MURID']);
    }

    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropIndex('class_sessions_status_pending_requester_index');
            $table->dropColumn('pending_requested_by');
        });
    }
};
