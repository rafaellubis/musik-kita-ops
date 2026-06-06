<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_report_wa_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 20);
            $table->text('message_body');
            $table->string('provider', 20)->default('fonnte');
            $table->json('provider_message_ids')->nullable();
            $table->string('status', 20);
            $table->boolean('is_update')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['class_session_id', 'sent_at'], 'sess_wa_log_session_sent_idx');
            $table->index(['student_id', 'sent_at'], 'sess_wa_log_student_sent_idx');
            $table->index(['status', 'sent_at'], 'sess_wa_log_status_sent_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_report_wa_logs');
    }
};
