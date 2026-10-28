<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_reminder_logs', function (Blueprint $table) {
            $table->renameColumn('wablas_message_ids', 'provider_message_ids');
            $table->string('provider', 20)->nullable();
        });
        // Nama kolom lama tidak membuktikan provider asal seluruh riwayat.
        DB::table('invoice_reminder_logs')->whereNull('provider')->update(['provider' => 'legacy']);
    }

    public function down(): void
    {
        Schema::table('invoice_reminder_logs', function (Blueprint $table) {
            $table->renameColumn('provider_message_ids', 'wablas_message_ids');
            $table->dropColumn('provider');
        });
    }
};
