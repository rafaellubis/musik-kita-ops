<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            // Kelompok A: Identitas
            $table->id();
            $table->string('student_code', 15)->unique();
            $table->string('full_name', 100);
            $table->string('nickname', 30)->nullable();
            $table->enum('gender', ['L', 'P']);

            // Kelompok B: Kontak & Personal
            $table->date('birth_date')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();

            // Kelompok C: Parent/Guardian
            $table->string('parent_name', 100)->nullable();
            $table->string('parent_phone', 20)->nullable();
            $table->string('parent_email', 100)->nullable();
            $table->enum('parent_relationship', ['Ayah', 'Ibu', 'Wali'])->nullable();

            // Kelompok D: Status Belajar
            $table->enum('status', [
                'Calon', 'Trial', 'Aktif', 'Cuti', 'Selesai', 'Mengundurkan Diri'
            ])->default('Calon');
            $table->foreignId('package_id')->nullable()
                ->constrained('packages')->restrictOnDelete();
            $table->foreignId('assigned_teacher_id')->nullable()
                ->constrained('teachers')->restrictOnDelete();
            $table->foreignId('assigned_room_id')->nullable()
                ->constrained('rooms')->restrictOnDelete();
            $table->enum('preferred_day', [
                'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'
            ])->nullable();
            $table->time('preferred_time')->nullable();
            $table->dateTime('trial_date')->nullable();
            $table->date('active_since')->nullable();

            // Kelompok E: Tracking
            $table->dateTime('last_session_at')->nullable();
            $table->timestamps();

            // Index untuk query performa
            $table->index('status');
            $table->index('student_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
