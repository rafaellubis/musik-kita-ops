<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel event_participants: daftar murid yang ikut event (M08).
 *
 * participation_type:
 *   UJIAN_TAMPIL  -> biaya Rp 395.000 (kode invoice UJI)
 *   TAMPIL_SAJA   -> biaya Rp 295.000 (kode invoice MC)
 *
 * exam_result: diisi setelah event selesai (LULUS/TIDAK_LULUS).
 * grade_before/after: dicatat saat proses upgrade grade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('enrollment_id')->nullable();
            $table->enum('participation_type', ['UJIAN_TAMPIL', 'TAMPIL_SAJA'])
                  ->default('TAMPIL_SAJA');
            $table->integer('fee_amount')->default(0)->comment('Disimpan saat daftar untuk referensi');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('invoice_item_id')->nullable();
            $table->enum('exam_result', ['LULUS', 'TIDAK_LULUS'])->nullable();
            $table->string('grade_before', 20)->nullable();
            $table->string('grade_after', 20)->nullable();
            $table->text('exam_notes')->nullable();
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->foreign('student_id')->references('id')->on('students');
            $table->foreign('enrollment_id')->references('id')->on('enrollments')->nullOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
            $table->foreign('invoice_item_id')->references('id')->on('invoice_items')->nullOnDelete();

            $table->unique(['event_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_participants');
    }
};
