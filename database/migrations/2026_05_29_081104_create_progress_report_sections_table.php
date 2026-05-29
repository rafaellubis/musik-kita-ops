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
        Schema::create('progress_report_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('progress_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_template_section_id')->constrained()->restrictOnDelete();
            $table->text('summary')->nullable();
            $table->timestamps();
            // Nama index diperpendek karena batas 64 karakter MySQL
            $table->unique(['progress_report_id', 'report_template_section_id'], 'prs_report_section_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('progress_report_sections');
    }
};
