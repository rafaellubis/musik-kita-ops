<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            $table->string('employee_code', 20)->unique();
            $table->string('full_name', 100);
            $table->string('position', 100);
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();

            $table->unsignedInteger('base_salary')->default(0);

            $table->string('bank_name', 50)->nullable();
            $table->string('bank_account', 30)->nullable();
            $table->string('bank_account_holder', 100)->nullable();

            $table->date('joined_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
