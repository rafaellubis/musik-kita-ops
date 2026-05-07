<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice items — line item per invoice (M05).
 *
 * item_code mengikuti enum dari tabel invoice_components master:
 *   REG     — Registrasi (Rp 250.000)
 *   SPP     — SPP Bulanan (= harga paket)
 *   KIDS_FP — Final Project Kids (Rp 140.000)
 *   CUTI    — Biaya Cuti (Rp 100.000)
 *   UJI     — Ujian + Mini Concert (Rp 395.000)
 *   MC      — Mini Concert saja (Rp 295.000)
 *   DENDA   — Denda keterlambatan (Rp 5.000/hari)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')
                  ->constrained('invoices')
                  ->cascadeOnDelete();

            $table->string('item_code', 20);
            $table->string('description', 255);
            $table->integer('amount');

            // Metadata: jumlah hari telat untuk DENDA, package_id untuk SPP, dll.
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index('invoice_id');
            $table->index('item_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
