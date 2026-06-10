<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_payroll_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('staff_payroll_slip_id')
                ->constrained('staff_payroll_slips')
                ->cascadeOnDelete();

            $table->enum('item_type', ['ALLOWANCE', 'OVERTIME', 'DEDUCTION']);
            $table->string('item_code', 30);
            $table->string('description', 255);
            $table->unsignedInteger('amount');
            $table->json('metadata')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_payroll_items');
    }
};
