<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_expenses', function (Blueprint $table) {
            $table->id();

            // Format PCE/YYYY/MM/NNNN (reset per bulan)
            $table->string('expense_number', 30)->unique();

            $table->foreignId('expense_category_id')
                  ->constrained('expense_categories')
                  ->restrictOnDelete();

            $table->unsignedInteger('amount');
            $table->string('description', 255);
            $table->date('expense_date');
            $table->string('receipt_image')->nullable(); // path foto bukti
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_expenses');
    }
};
