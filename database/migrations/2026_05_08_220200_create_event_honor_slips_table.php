<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel event_honor_slips: slip honor guru untuk event (M08).
 *
 * Berbeda dari teacher_honor_slips (M06) yang berbasis bulan,
 * slip ini berbasis event satu kali.
 *
 * base_honor default Rp 250.000 (H_UJIAN flat).
 * transport_honor + other_honor diisi manual oleh Owner.
 * other_honor_note wajib jika other_honor > 0 (dicek di controller).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_honor_slips', function (Blueprint $table) {
            $table->id();
            $table->string('slip_number', 30)->unique()->comment('EVT-SLIP/YYYY/NNNN');
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('teacher_id');
            $table->string('role', 100)->nullable()->comment('misal: Pengawas Ujian, Pelatih Piano');
            $table->integer('base_honor')->default(250000);
            $table->integer('transport_honor')->default(0);
            $table->integer('other_honor')->default(0);
            $table->string('other_honor_note', 255)->nullable();
            $table->integer('total_honor')->default(250000);
            $table->enum('status', ['DRAFT', 'PAID'])->default('DRAFT');
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->foreign('teacher_id')->references('id')->on('teachers');
            $table->foreign('paid_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users');

            $table->unique(['event_id', 'teacher_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_honor_slips');
    }
};
