<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enrollments — pendaftaran murid ke sebuah paket dengan guru tertentu.
 *
 * 1 murid bisa punya banyak enrollment historis (mis: berhenti L1 Piano,
 * lalu re-enroll ke L2 Piano), tapi biasanya hanya 1 ACTIVE pada satu waktu.
 *
 * Catatan untuk Kids Class: tiap murid Kids tetap punya enrollment sendiri.
 * 4 murid kelas Kids = 4 enrollment dengan paket KIDS_CLASS / KIDS_CLASS_BUNDLE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')
                  ->constrained('students')
                  ->restrictOnDelete();

            $table->foreignId('package_id')
                  ->constrained('packages')
                  ->restrictOnDelete();

            $table->foreignId('teacher_id')
                  ->constrained('teachers')
                  ->restrictOnDelete();

            // Mulai berlaku — biasanya tanggal murid jadi Aktif.
            $table->date('effective_date');

            // Berakhir — diisi saat murid Mundur/Selesai/ganti paket.
            $table->date('end_date')->nullable();

            $table->enum('status', ['ACTIVE', 'INACTIVE', 'COMPLETED'])
                  ->default('ACTIVE');

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('student_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
