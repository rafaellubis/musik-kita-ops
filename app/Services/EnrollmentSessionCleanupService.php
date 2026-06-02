<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ClassSession;
use App\Models\Enrollment;
use Carbon\Carbon;

/**
 * Bersihkan sesi orphan saat enrollment ditutup (hentikan kelas / ganti grade).
 *
 * Mencegah SessionGenerator bentrok: sesi SCHEDULED/LIBUR dari enrollment lama
 * masih menempati slot guru meski enrollment sudah INACTIVE/COMPLETED.
 */
class EnrollmentSessionCleanupService
{
    /**
     * Hapus sesi SCHEDULED/LIBUR yang jatuh setelah end_date enrollment.
     * Sesi pada hari end_date sendiri dipertahankan (murid masih aktif hari itu).
     *
     * @return int Jumlah sesi yang dihapus
     */
    public function purgeFutureSessions(Enrollment $enrollment): int
    {
        $cutoff = $enrollment->end_date
            ? Carbon::parse($enrollment->end_date)->toDateString()
            : now()->toDateString();

        $sessions = ClassSession::query()
            ->where('enrollment_id', $enrollment->id)
            ->whereIn('status', [
                ClassSession::STATUS_SCHEDULED,
                ClassSession::STATUS_LIBUR,
            ])
            ->where('session_date', '>', $cutoff)
            ->get();

        $deleted = 0;

        foreach ($sessions as $session) {
            AuditLog::record(
                action: AuditLog::ACTION_DELETE,
                entity: $session,
                entityLabel: 'Sesi orphan enrollment ditutup ' . $session->session_date,
            );
            $session->delete();
            $deleted++;
        }

        return $deleted;
    }
}
