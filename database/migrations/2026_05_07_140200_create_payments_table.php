<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payments — pembayaran terhadap invoice (M05).
 *
 * Format kuitansi: KW/YYYY/MM/NNNN, reset per bulan (BR-5.17).
 *
 * Pembayaran bisa di-VOID (BR-5.18: hanya OWNER yang berhak), tapi
 * row TIDAK dihapus — di-set voided_at + voided_by + voided_reason
 * untuk audit trail. Saat void, paid_amount invoice di-recalc tanpa
 * payment ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 30)->unique();

            $table->foreignId('invoice_id')
                  ->constrained('invoices')
                  ->restrictOnDelete();

            $table->integer('amount');
            // Empat metode valid (BR-5.19). Migrasi alter enum terpisah
            // hanya berlaku di MySQL — SQLite pakai definisi ini langsung.
            $table->enum('method', ['CASH', 'TRANSFER', 'QRIS', 'DEBIT']);
            $table->date('payment_date');

            // Path file upload bukti (storage/app/public/payments/...)
            $table->string('proof_image', 255)->nullable();

            $table->text('notes')->nullable();

            // Void info — kalau di-void, row tetap ada untuk audit
            $table->dateTime('voided_at')->nullable();
            $table->foreignId('voided_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->text('voided_reason')->nullable();

            // Siapa yang catat pembayaran (admin/owner)
            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();

            $table->index('invoice_id');
            $table->index('payment_date');
            $table->index('voided_at'); // untuk filter "valid payments"
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
