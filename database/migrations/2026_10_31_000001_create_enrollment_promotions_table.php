<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_enrollment_id')->unique()->constrained('enrollments')->restrictOnDelete();
            $table->foreignId('successor_enrollment_id')->unique()->constrained('enrollments')->restrictOnDelete();
            $table->date('effective_date')->index();
            $table->string('status', 20)->default('SCHEDULED');
            $table->text('evaluation_note');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->json('snapshot');
            $table->json('invoice_adjustments');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_promotions');
    }
};
