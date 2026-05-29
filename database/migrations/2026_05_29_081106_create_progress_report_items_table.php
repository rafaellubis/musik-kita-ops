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
        Schema::create('progress_report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('progress_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_template_item_id')->constrained()->restrictOnDelete();
            $table->boolean('is_checked')->default(false);
            $table->timestamps();
            // Nama index diperpendek karena batas 64 karakter MySQL
            $table->unique(['progress_report_id', 'report_template_item_id'], 'pri_report_item_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('progress_report_items');
    }
};
