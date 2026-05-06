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
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('instrument_id')
                  ->constrained('instruments')
                  ->onDelete('restrict');
            $table->enum('class_type', ['REGULER', 'HOBBY', 'KIDS_CLASS', 'KIDS_CLASS_BUNDLE']);
            $table->string('grade', 10)->nullable();
            $table->integer('duration_min');
            $table->integer('price_per_month');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
