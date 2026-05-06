<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payroll_configs', function (Blueprint $table) {
            $table->id();
            $table->string('scenario_code', 30)->unique();
            $table->string('scenario_name', 100);
            $table->enum('formula_type', ['PERCENTAGE', 'PER_STUDENT', 'FIXED', 'CONSTANT']);
            $table->string('value_or_formula', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_configs');
    }
};
