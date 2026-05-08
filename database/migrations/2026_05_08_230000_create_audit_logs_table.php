<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel audit_logs: jejak aksi penting di sistem (M09).
 *
 * action enum: CREATE, UPDATE, DELETE, LOGIN, LOGOUT, PRINT, VOID
 * entity_type: nama model (Student, Invoice, Payment, dst)
 * old_values/new_values: snapshot data sebelum/sesudah perubahan (JSON)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name', 100)->nullable()->comment('Disimpan langsung agar tetap terbaca jika user dihapus');
            $table->enum('action', ['CREATE', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT', 'PRINT', 'VOID', 'LIFECYCLE']);
            $table->string('entity_type', 100)->nullable()->comment('Nama model, mis. Student, Invoice');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('entity_label', 200)->nullable()->comment('Label human-readable, mis. nomor invoice');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('notes', 500)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['entity_type', 'entity_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
