<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_topups', function (Blueprint $table) {
            $table->id();

            // Format PCU/YYYY/MM/NNNN (reset per bulan)
            $table->string('topup_number', 30)->unique();

            $table->unsignedInteger('amount');
            $table->date('topup_date');
            $table->string('description', 255);
            $table->text('notes')->nullable();
            $table->string('receipt_image')->nullable(); // path foto bukti

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_topups');
    }
};
