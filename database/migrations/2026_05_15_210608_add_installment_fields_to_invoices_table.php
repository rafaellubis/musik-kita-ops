<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom cicilan dan class_type ke tabel invoices.
     *
     * class_type        : snapshot class_type paket saat invoice dibuat (KIDS_CLASS_BUNDLE, dll).
     *                     Nullable karena invoice lama tidak punya kolom ini.
     * payment_mode      : FULL (bayar sekali) atau INSTALLMENT (cicilan 3 termin).
     *                     Hanya relevan untuk KIDS_CLASS_BUNDLE.
     * installment_number: urutan termin (1, 2, atau 3). Null untuk invoice FULL.
     * installment_group_id: UUID pengikat 3 invoice cicilan satu paket.
     *                       Null untuk invoice FULL.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('class_type', 30)->nullable()->after('month');
            $table->enum('payment_mode', ['FULL', 'INSTALLMENT'])->default('FULL')->after('class_type');
            $table->unsignedTinyInteger('installment_number')->nullable()->after('payment_mode');
            $table->string('installment_group_id', 36)->nullable()->after('installment_number');

            $table->index(['class_type', 'payment_mode'], 'invoices_class_payment_idx');
            $table->index('installment_group_id', 'invoices_installment_group_idx');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_class_payment_idx');
            $table->dropIndex('invoices_installment_group_idx');
            $table->dropColumn(['class_type', 'payment_mode', 'installment_number', 'installment_group_id']);
        });
    }
};
