<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->date('cuti_from');
            $table->date('cuti_until');
            $table->unsignedTinyInteger('extension_count')->default(0);
            $table->string('reason', 500);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['enrollment_id', 'completed_at']);
        });

        // Salin periode lama ke setiap kelas yang sebelumnya mengikuti cuti murid.
        // Kolom murid dipertahankan; riwayat baru tidak dihapus setelah kembali aktif.
        DB::table('students')->whereNotNull('cuti_until')->orderBy('id')->chunkById(200, function ($students) {
            foreach ($students as $student) {
                $history = DB::table('student_status_histories')
                    ->where('student_id', $student->id)->where('to_status', 'Cuti')->orderByDesc('id')->first();
                $metadata = $history ? json_decode($history->metadata ?? '{}', true) : [];
                $until = Carbon::parse($student->cuti_until)->startOfDay();
                // Data impor lama dapat hanya berisi tanggal akhir; gunakan awal dari audit bila tersedia.
                $from = Carbon::parse($student->cuti_from ?? ($metadata['cuti_from'] ?? $until->copy()->subDays(30)))->startOfDay();
                $completed = $until->lt(today());
                $enrollments = DB::table('enrollments')->where('student_id', $student->id)
                    ->whereIn('status', ['ACTIVE', 'ON_LEAVE'])->get();

                foreach ($enrollments as $enrollment) {
                    DB::table('enrollment_leaves')->insert([
                        'enrollment_id' => $enrollment->id,
                        'cuti_from' => $from->toDateString(),
                        'cuti_until' => $until->toDateString(),
                        'extension_count' => !empty($metadata['is_extension']) || $from->diffInDays($until) + 1 > 31 ? 1 : 0,
                        'reason' => mb_substr($history->reason ?? 'Migrasi periode cuti murid', 0, 500),
                        'completed_at' => $completed ? $until->copy()->addDay()->toDateTimeString() : null,
                        'created_at' => $student->created_at,
                        'updated_at' => now(),
                    ]);
                    DB::table('enrollments')->where('id', $enrollment->id)->update([
                        'status' => !$completed && $from->lte(today()) ? 'ON_LEAVE' : 'ACTIVE',
                    ]);
                }

                if (in_array($student->status, ['Aktif', 'Cuti'], true) && $enrollments->isNotEmpty()) {
                    DB::table('students')->where('id', $student->id)->update([
                        'status' => !$completed && $from->lte(today()) ? 'Cuti' : 'Aktif',
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_leaves');
    }
};
