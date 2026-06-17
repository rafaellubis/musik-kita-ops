<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Student;
use App\Models\User;
use App\Notifications\MuridKembaliDariCutiNotification;
use App\Services\StudentLifecycleService;
use Illuminate\Console\Command;

/**
 * Kembalikan murid Cuti ke Aktif otomatis saat cuti_until sudah lewat.
 * Dijadwalkan harian (default 23:59) via routes/console.php.
 */
class ReturnStudentsFromLeave extends Command
{
    protected $signature   = 'students:return-from-leave';
    protected $description = 'Kembalikan murid Cuti ke Aktif otomatis setelah cuti_until berakhir';

    public function handle(StudentLifecycleService $lifecycle): int
    {
        $students = Student::query()
            ->where('status', 'Cuti')
            ->whereDate('cuti_until', '<=', today())
            ->get();

        if ($students->isEmpty()) {
            $this->info('Tidak ada murid Cuti yang perlu dikembalikan ke Aktif.');
            return self::SUCCESS;
        }

        $penerima = User::role(['Admin', 'Owner'])->get();
        $diproses = 0;

        foreach ($students as $student) {
            try {
                $lifecycle->aktifkanDariCuti($student, ['auto' => true]);
            } catch (\InvalidArgumentException $e) {
                $this->warn("  → Lewati {$student->student_code}: {$e->getMessage()}");
                continue;
            }

            AuditLog::create([
                'user_id'      => null,
                'user_name'    => 'System (Scheduler)',
                'action'       => AuditLog::ACTION_LIFECYCLE,
                'entity_type'  => 'Student',
                'entity_id'    => $student->id,
                'entity_label' => $student->full_name . ' (' . $student->student_code . ')',
                'old_values'   => ['status' => 'Cuti'],
                'new_values'   => ['status' => 'Aktif', 'auto' => true],
                'notes'        => 'Cuti berakhir otomatis (scheduler)',
            ]);

            foreach ($penerima as $user) {
                $user->notify(new MuridKembaliDariCutiNotification($student->fresh(), true));
            }

            $diproses++;
            $this->line("  → {$student->full_name} ({$student->student_code}) kembali Aktif");
        }

        if ($diproses > 0) {
            AuditLog::create([
                'user_id'      => null,
                'user_name'    => 'System (Scheduler)',
                'action'       => AuditLog::ACTION_UPDATE,
                'entity_type'  => null,
                'entity_id'    => null,
                'entity_label' => 'Scheduler Cuti → Aktif',
                'new_values'   => [
                    'processed' => $diproses,
                    'date'      => today()->toDateString(),
                ],
                'notes'        => "Cron students:return-from-leave — {$diproses} murid dikembalikan ke Aktif",
            ]);
        }

        $this->info("Selesai: {$diproses} murid dikembalikan ke Aktif.");

        return self::SUCCESS;
    }
}
