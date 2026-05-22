<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kolom baru ke enrollments dulu (sebelum student butuh FK-nya)
        Schema::table('enrollments', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false)->after('notes');
            // Extend enum status: tambah ON_LEAVE
            $table->enum('status', ['ACTIVE', 'ON_LEAVE', 'INACTIVE', 'COMPLETED'])
                  ->default('ACTIVE')->change();
        });

        // 2. Tambah kolom enrollment_id ke invoices
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('enrollment_id')
                  ->nullable()
                  ->after('student_id')
                  ->constrained('enrollments')
                  ->nullOnDelete();
        });

        // 3. Tambah primary_enrollment_id ke students (nullable dulu, isi data dulu)
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('primary_enrollment_id')
                  ->nullable()
                  ->after('status')
                  ->constrained('enrollments')
                  ->nullOnDelete();
        });

        // 4. Data migration: isi is_primary + primary_enrollment_id dari data existing
        //    Enrollment ACTIVE pertama (MIN id) per murid dijadikan primary.
        //    Gunakan loop per-student agar kompatibel dengan MySQL dan SQLite (test environment).
        $studentIds = DB::table('enrollments')
            ->where('status', 'ACTIVE')
            ->distinct()
            ->pluck('student_id');

        foreach ($studentIds as $studentId) {
            $minId = DB::table('enrollments')
                ->where('status', 'ACTIVE')
                ->where('student_id', $studentId)
                ->min('id');
            if ($minId) {
                DB::table('enrollments')->where('id', $minId)->update(['is_primary' => true]);
            }
        }

        // Set primary_enrollment_id di students
        $primaries = DB::table('enrollments')
            ->where('is_primary', true)
            ->get(['id', 'student_id']);

        foreach ($primaries as $enrollment) {
            DB::table('students')
                ->where('id', $enrollment->student_id)
                ->update(['primary_enrollment_id' => $enrollment->id]);
        }

        // 5. Hapus kolom lama dari students (package_id, assigned_teacher_id, assigned_room_id, dll)
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['package_id']);
            $table->dropForeign(['assigned_teacher_id']);
            $table->dropForeign(['assigned_room_id']);
            $table->dropColumn([
                'package_id',
                'assigned_teacher_id',
                'assigned_room_id',
                'preferred_day',
                'preferred_time',
            ]);
        });
    }

    public function down(): void
    {
        // Kembalikan kolom students yang dihapus
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('package_id')->nullable()->constrained('packages');
            $table->foreignId('assigned_teacher_id')->nullable()->constrained('teachers');
            $table->foreignId('assigned_room_id')->nullable()->constrained('rooms');
            $table->enum('preferred_day', ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'])->nullable();
            $table->time('preferred_time')->nullable();
        });

        // Hapus primary_enrollment_id dari students
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['primary_enrollment_id']);
            $table->dropColumn('primary_enrollment_id');
        });

        // Hapus enrollment_id dari invoices
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['enrollment_id']);
            $table->dropColumn('enrollment_id');
        });

        // Kembalikan enrollments ke kondisi semula
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn('is_primary');
            $table->enum('status', ['ACTIVE', 'INACTIVE', 'COMPLETED'])
                  ->default('ACTIVE')->change();
        });
    }
};
