<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('enrollment_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_teacher_id')->constrained('teachers')->restrictOnDelete();
            $table->foreignId('target_teacher_id')->constrained('teachers')->restrictOnDelete();
            $table->date('effective_date')->index();
            $table->string('status')->default('SCHEDULED')->index();
            $table->json('before');
            $table->json('after');
            $table->text('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->text('blocked_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_transfers');
    }
};
