<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repurpose invoice_components menjadi katalog item manual (revisi 2026-05-07).
 *
 * Perubahan:
 *   - DROP  : kolom type (enum tidak fleksibel, tidak dipakai oleh service manapun)
 *   - DROP  : kolom amount_or_formula (diganti default_price integer yang lebih jelas)
 *   - ADD   : default_price — harga default item, bisa dioverride saat input manual
 *
 * Catatan: tabel ini sebelumnya dekoratif. Setelah migration ini, ia menjadi
 * katalog item tagihan yang bisa dikelola Owner dan dipakai Admin saat tambah
 * item manual ke invoice murid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_components', function (Blueprint $table) {
            $table->dropColumn(['type', 'amount_or_formula']);
            // Default 0 agar kolom NOT NULL tetap aman saat migration
            $table->unsignedInteger('default_price')->default(0)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_components', function (Blueprint $table) {
            $table->dropColumn('default_price');
            $table->enum('type', ['REGULER', 'TRIAL', 'KIDS_FINAL', 'CUTI', 'UJIAN', 'MINI_CONCERT', 'DENDA'])
                  ->after('name');
            $table->string('amount_or_formula', 100)->after('type');
        });
    }
};
