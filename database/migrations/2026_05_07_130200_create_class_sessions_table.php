<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * class_sessions — sesi kelas konkret per tanggal.
 *
 * Catatan nama: CLAUDE.md awal menyebut tabel ini `sessions`, tapi nama
 * itu sudah dipakai Laravel default untuk session driver database (lihat
 * migration `0001_01_01_000000_create_users_table.php`). Karena itu kita
 * pakai `class_sessions`. Model-nya dinamai `ClassSession` (bukan `Session`,
 * yang sudah dipakai facade Laravel).
 *
 * Tabel ini dipanggil paling sering: absensi (M04), kalkulasi honor (M06),
 * dashboard (M09). Karena itu kita denormalisasi student_id dan teacher_id
 * supaya query "sesi guru X bulan Y" cepat tanpa join enrollment.
 *
 * Status enum (8 nilai) sumber CLAUDE.md:
 *   SCHEDULED, HADIR, HADIR_TERLAMBAT, IZIN_RESCHEDULE,
 *   IZIN_VIDEO, HANGUS, LIBUR, DIGANTI
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_sessions', function (Blueprint $table) {
            $table->id();

            // schedule_id NULL = sesi ad-hoc (mis. trial sebelum enrollment,
            // atau reschedule manual yang tidak pakai jadwal mingguan).
            $table->foreignId('schedule_id')
                  ->nullable()
                  ->constrained('schedules')
                  ->nullOnDelete();

            $table->foreignId('enrollment_id')
                  ->nullable()  // Trial belum punya enrollment.
                  ->constrained('enrollments')
                  ->nullOnDelete();

            $table->foreignId('student_id')
                  ->constrained('students')
                  ->restrictOnDelete();

            // Guru asli sesuai schedule. Kalau diganti, isi substitute_teacher_id.
            $table->foreignId('teacher_id')
                  ->constrained('teachers')
                  ->restrictOnDelete();

            $table->foreignId('substitute_teacher_id')
                  ->nullable()
                  ->constrained('teachers')
                  ->nullOnDelete();

            $table->date('session_date');
            $table->time('start_time');
            $table->time('end_time');

            $table->foreignId('room_id')
                  ->nullable()
                  ->constrained('rooms')
                  ->nullOnDelete();

            $table->enum('status', [
                'SCHEDULED',
                'HADIR',
                'HADIR_TERLAMBAT',
                'IZIN_RESCHEDULE',
                'IZIN_VIDEO',
                'HANGUS',
                'LIBUR',
                'DIGANTI',
            ])->default('SCHEDULED');

            $table->unsignedSmallInteger('late_minutes')->nullable();
            $table->text('notes')->nullable();

            // Honor: diisi saat absensi disubmit (M04) atau saat kalkulasi
            // honor (M06). NULL berarti belum dihitung.
            $table->string('honor_code', 20)->nullable();
            $table->integer('honor_amount')->nullable();

            $table->timestamps();

            // Indeks untuk query yang sering dipakai
            $table->index('session_date');
            $table->index(['teacher_id', 'session_date']);
            $table->index(['student_id', 'session_date']);
            $table->index(['schedule_id', 'session_date']); // idempotent generator
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_sessions');
    }
};
