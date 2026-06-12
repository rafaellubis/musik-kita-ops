<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flag waiver denda manual per invoice (M05).
 * Setelah di-waive, cron apply-fines tidak membuat ulang item DENDA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('fine_waived_at')->nullable()->after('voided_reason');
            $table->foreignId('fine_waived_by')->nullable()->after('fine_waived_at')
                  ->constrained('users')->nullOnDelete();
            $table->text('fine_waive_reason')->nullable()->after('fine_waived_by');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['fine_waived_by']);
            $table->dropColumn(['fine_waived_at', 'fine_waived_by', 'fine_waive_reason']);
        });
    }
};
