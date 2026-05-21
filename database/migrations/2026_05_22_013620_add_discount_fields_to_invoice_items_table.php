<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom diskon ke invoice_items untuk mendukung fitur diskon per item.
 *
 * Kolom baru:
 * - parent_item_id: FK self-referential ke invoice_items.id (nullable)
 * - discount_type: NOMINAL atau PERCENT (nullable)
 * - discount_value: nilai diskon dalam Rp atau % (nullable)
 * - discount_reason: alasan diskon (nullable, wajib diisi saat pembuatan)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            // FK ke item induk — null berarti ini bukan item diskon
            $table->unsignedBigInteger('parent_item_id')
                  ->nullable()
                  ->after('invoice_id');
            $table->foreign('parent_item_id')
                  ->references('id')
                  ->on('invoice_items')
                  ->nullOnDelete();

            // Kolom diskon — hanya diisi oleh item dengan item_code='DISKON'
            $table->string('discount_type', 10)->nullable()->after('metadata');
            $table->integer('discount_value')->nullable()->after('discount_type');
            $table->string('discount_reason', 500)->nullable()->after('discount_value');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['parent_item_id']);
            $table->dropColumn(['parent_item_id', 'discount_type', 'discount_value', 'discount_reason']);
        });
    }
};
