<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoices — tagihan ke murid (M05).
 *
 * Format nomor: INV/YYYY/MM/NNNN, reset per bulan (BR-5.16).
 *
 * Status enum:
 *   UNPAID  — belum ada pembayaran sama sekali
 *   PARTIAL — ada pembayaran, tapi belum lunas (paid < total)
 *   PAID    — paid >= total (BR-5.4: lunas = SPP + seluruh denda)
 *   VOID    — invoice dibatalkan (jarang, audit trail)
 *
 * Field month/year tidak SELALU = bulan invoice diterbitkan.
 * Untuk SPP: month/year = bulan tagihan SPP. Untuk REG/CUTI/UJI/MC: month/year
 * = bulan invoice diterbitkan (untuk grouping reset nomor).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 30)->unique();

            $table->foreignId('student_id')
                  ->constrained('students')
                  ->restrictOnDelete();

            // Periode penagihan (untuk reset nomor + grouping SPP)
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');

            $table->string('description', 255)->nullable();
            $table->integer('total_amount');
            $table->integer('paid_amount')->default(0);

            $table->enum('status', ['UNPAID', 'PARTIAL', 'PAID', 'VOID'])
                  ->default('UNPAID');

            $table->date('due_date');
            $table->date('issued_at');

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['student_id', 'year', 'month']);
            $table->index(['year', 'month', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
