<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_payroll_slips', function (Blueprint $table) {
            $table->id();

            // Format LMK/SLIP/YYYY/MM/NNN (reset per bulan)
            $table->string('slip_number', 30)->unique();

            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->unsignedSmallInteger('month');
            $table->unsignedSmallInteger('year');

            // Snapshot gaji pokok saat slip dibuat
            $table->unsignedInteger('base_salary')->default(0);
            $table->unsignedInteger('total_allowances')->default(0);
            $table->unsignedInteger('total_deductions')->default(0);
            $table->unsignedInteger('net_salary')->default(0);

            $table->enum('status', ['DRAFT', 'CALCULATED', 'PAID'])->default('DRAFT');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['employee_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_payroll_slips');
    }
};
