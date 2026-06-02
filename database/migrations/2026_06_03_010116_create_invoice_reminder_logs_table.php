<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Log pengiriman reminder tagihan via WhatsApp (Wablas).
     */
    public function up(): void
    {
        Schema::create('invoice_reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sent_by')->constrained('users')->cascadeOnDelete();
            $table->string('phone', 20);
            $table->text('message_body');
            $table->json('invoice_ids');
            $table->json('pdf_filenames')->nullable();
            $table->json('wablas_message_ids')->nullable();
            $table->unsignedTinyInteger('documents_sent')->default(0);
            $table->unsignedTinyInteger('documents_total')->default(0);
            $table->string('status', 20); // SUCCESS | PARTIAL | FAILED
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['student_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_reminder_logs');
    }
};
