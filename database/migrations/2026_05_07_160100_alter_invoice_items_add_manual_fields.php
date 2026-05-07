<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extend invoice_items untuk mendukung item tagihan manual (revisi 2026-05-07).
 *
 * Perubahan:
 *   - MODIFY : item_code VARCHAR(30) — sebelumnya VARCHAR(20), cukup untuk kode
 *              dinamis dari katalog Owner (misal: BUKU_MATERI, KOSTUM_KIDS).
 *              Tidak pakai enum agar tidak perlu ALTER TABLE setiap ada item baru.
 *   - ADD    : invoice_component_id — nullable FK ke invoice_components.
 *              NULL = item sistem (SPP, REG, DENDA, dll. — auto-generated).
 *              Diisi = item manual dari katalog Owner.
 *   - ADD    : added_by — nullable FK ke users.
 *              NULL = di-generate otomatis oleh sistem (cron/lifecycle).
 *              Diisi = diinput manual oleh Admin/Owner.
 *
 * Rule hapus item: hanya boleh kalau added_by IS NOT NULL (item manual).
 * Item sistem (added_by NULL) tidak boleh dihapus via UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            // Lebarkan item_code dari 20 → 30 karakter
            $table->string('item_code', 30)->change();

            $table->foreignId('invoice_component_id')
                  ->nullable()
                  ->after('invoice_id')
                  ->constrained('invoice_components')
                  ->nullOnDelete();

            $table->foreignId('added_by')
                  ->nullable()
                  ->after('invoice_component_id')
                  ->constrained('users')
                  ->nullOnDelete();

            $table->index('invoice_component_id');
            $table->index('added_by');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['invoice_component_id']);
            $table->dropForeign(['added_by']);
            $table->dropIndex(['invoice_component_id']);
            $table->dropIndex(['added_by']);
            $table->dropColumn(['invoice_component_id', 'added_by']);
            $table->string('item_code', 20)->change();
        });
    }
};
