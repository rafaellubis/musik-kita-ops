<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail lifecycle murid (M02).
 *
 * Setiap kali status murid berubah (Calon -> Trial, Trial -> Aktif, Aktif -> Cuti,
 * dst), 1 baris ditambah di sini. Sumber kebenaran transisi status,
 * dipakai juga di laporan retensi murid (M09).
 *
 * Catatan desain:
 * - from_status NULL hanya untuk record awal saat murid baru dibuat.
 * - skipped_trial = true menandai jalur Hybrid (Calon -> langsung Aktif),
 *   wajib disertai reason. Lihat CLAUDE.md "Skip Trial (Hybrid Flow)".
 * - metadata JSON menyimpan info tambahan: cuti_from/cuti_until, reason_code
 *   (walk_in/migrasi/reaktivasi/lulus_kids), pending_invoices, dll.
 * - changed_by NULL kalau perubahan dipicu cron (mis. auto-mundur tunggakan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_status_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')
                  ->constrained('students')
                  ->cascadeOnDelete();

            // Pakai string (bukan enum) untuk fleksibilitas:
            // kalau enum status di tabel students nanti diubah, history tetap valid.
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);

            // Alasan transisi. Wajib untuk: skip trial, mundur, cuti.
            $table->text('reason')->nullable();

            // Flag jalur Hybrid (Calon -> Aktif tanpa lewat Trial).
            $table->boolean('skipped_trial')->default(false);

            // Data tambahan terkait transisi (cuti_until, reason_code, dll).
            $table->json('metadata')->nullable();

            // Siapa yang melakukan transisi. NULL = system/cron.
            $table->foreignId('changed_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();

            // Index untuk query "history murid X urut terbaru"
            $table->index(['student_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_status_histories');
    }
};
