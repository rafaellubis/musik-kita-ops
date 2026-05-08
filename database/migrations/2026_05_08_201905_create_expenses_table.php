<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();

            // Format EXP/YYYY/MM/NNNN (reset per bulan)
            $table->string('expense_number', 30)->unique();

            $table->foreignId('expense_category_id')
                  ->constrained('expense_categories')
                  ->restrictOnDelete();

            $table->unsignedInteger('amount');
            $table->string('description', 255);     // keterangan pengeluaran
            $table->date('expense_date');

            // Metode pembayaran — CASH penting untuk hitung saldo petty cash
            $table->enum('payment_method', ['CASH', 'TRANSFER'])->default('CASH');

            $table->string('receipt_image')->nullable(); // path foto bukti
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
