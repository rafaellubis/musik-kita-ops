<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel ini digantikan oleh kolom event_honor di teacher_honor_slips
        Schema::dropIfExists('event_honor_slips');
    }

    public function down(): void
    {
        // Recreate minimal — hanya untuk rollback darurat
        Schema::create('event_honor_slips', function (Blueprint $table) {
            $table->id();
            $table->string('slip_number', 30)->unique();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('teacher_id');
            $table->string('role', 100)->nullable();
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
        });
    }
};