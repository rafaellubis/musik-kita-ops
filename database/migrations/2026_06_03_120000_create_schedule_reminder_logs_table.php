<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Log pengiriman pengingat jadwal via Fonnte (cron otomatis).
     */
    public function up(): void
    {
        Schema::create('schedule_reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('target_date');
            $table->string('reminder_mode', 20);
            $table->json('class_session_ids');
            $table->string('provider', 20)->default('fonnte');
            $table->string('phone', 20);
            $table->text('message_body');
            $table->json('provider_message_ids')->nullable();
            $table->string('status', 20);
            $table->text('error_message')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['student_id', 'target_date', 'reminder_mode'], 'sched_rem_log_student_date_mode_idx');
            $table->index(['target_date', 'reminder_mode'], 'sched_rem_log_date_mode_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_reminder_logs');
    }
};
