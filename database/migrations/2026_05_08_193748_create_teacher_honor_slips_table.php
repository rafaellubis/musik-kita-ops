<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_honor_slips', function (Blueprint $table) {
            $table->id();

            // Format SLIP/YYYY/MM/NNNN (reset per bulan)
            $table->string('slip_number', 30)->unique();

            $table->foreignId('teacher_id')->constrained('teachers')->restrictOnDelete();
            $table->unsignedSmallInteger('month');  // 1-12
            $table->unsignedSmallInteger('year');

            // Komponen honor — sesuai BRD M06 + CLAUDE.md revisi v1.1
            $table->unsignedInteger('base_honor')->default(0);      // otomatis dari sesi
            $table->unsignedInteger('transport_honor')->default(0); // INPUT MANUAL
            $table->unsignedInteger('other_honor')->default(0);     // INPUT MANUAL
            $table->string('other_honor_note')->nullable();         // wajib diisi jika other_honor > 0

            // Total dihitung: base + transport + other
            $table->unsignedInteger('total_honor')->default(0);

            // Status lifecycle: DRAFT → CALCULATED → PAID
            $table->enum('status', ['DRAFT', 'CALCULATED', 'PAID'])->default('DRAFT');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Satu slip per guru per bulan (unique constraint)
            $table->unique(['teacher_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_honor_slips');
    }
};
