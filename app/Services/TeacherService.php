<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pusat logika bisnis guru — fokus pada deactivation cascade (Gap 10).
 */
class TeacherService
{
    /**
     * Nonaktifkan guru dengan validasi cascade.
     *
     * Aturan:
     * 1. BLOCK jika guru masih punya murid aktif (enrollment ACTIVE).
     * 2. WARNING jika ada sesi SCHEDULED di masa depan — tambah notes tapi tidak hapus.
     * 3. Set is_active = false.
     *
     * @throws InvalidArgumentException jika guru masih punya murid aktif.
     * @return array{warning: string|null}  warning berisi pesan jika ada sesi orphan.
     */
    public function deactivate(Teacher $teacher): array
    {
        // 1. Block jika masih ada murid aktif
        $activeStudentCount = $teacher->enrollments()
            ->where('status', 'ACTIVE')
            ->count();

        if ($activeStudentCount > 0) {
            throw new InvalidArgumentException(
                "Guru {$teacher->name} masih mengajar {$activeStudentCount} murid aktif. " .
                "Pindahkan murid ke guru lain sebelum menonaktifkan."
            );
        }

        $warning = null;

        return DB::transaction(function () use ($teacher, &$warning) {
            // 2. Cek sesi SCHEDULED masa depan (reschedule/rapel tanpa enrollment aktif)
            $orphanedSessions = ClassSession::where('teacher_id', $teacher->id)
                ->where('status', ClassSession::STATUS_SCHEDULED)
                ->where('session_date', '>', Carbon::today()->toDateString())
                ->get();

            if ($orphanedSessions->isNotEmpty()) {
                $count = $orphanedSessions->count();
                $noteText = "Guru {$teacher->name} dinonaktifkan " .
                    Carbon::today()->format('d M Y') . " — perlu pengganti";

                // Tambah notes ke tiap sesi agar admin tahu perlu assign guru pengganti
                ClassSession::where('teacher_id', $teacher->id)
                    ->where('status', ClassSession::STATUS_SCHEDULED)
                    ->where('session_date', '>', Carbon::today()->toDateString())
                    ->update(['notes' => $noteText]);

                $warning = "Terdapat {$count} sesi terjadwal masa depan yang perlu guru pengganti.";
            }

            // 3. Nonaktifkan guru
            $teacher->update(['is_active' => false]);

            return ['warning' => $warning];
        });
    }
}
