<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\EnrollmentLeave;
use App\Models\User;
use App\Notifications\MuridKembaliDariCutiNotification;
use App\Services\StudentLifecycleService;
use Illuminate\Console\Command;

/**
 * Sinkronkan awal cuti dan kembalikan kelas setelah hari terakhir cuti.
 * Periode disimpan per enrollment; kelas lain tetap berjalan.
 */
class ReturnStudentsFromLeave extends Command
{
    protected $signature   = 'students:return-from-leave';
    protected $description = 'Sinkronkan cuti per kelas dan aktifkan kelas yang cutinya selesai';

    public function handle(StudentLifecycleService $lifecycle): int
    {
        $leaves = EnrollmentLeave::query()
            ->whereNull('completed_at')
            ->whereHas('enrollment.student', fn ($query) => $query->whereIn('status', ['Aktif', 'Cuti']))
            ->with('enrollment.student')
            ->get();

        foreach ($leaves->pluck('enrollment.student')->unique('id') as $student) {
            $lifecycle->synchronizeLeaveStatus($student);
        }

        $expired = $leaves->filter(fn ($leave) => $leave->cuti_until->lt(today()));
        if ($expired->isEmpty()) {
            $this->info('Tidak ada kelas dengan cuti yang sudah selesai.');
        }

        $penerima = User::role(['Admin', 'Owner'])->get();
        $diproses = 0;

        foreach ($expired as $leave) {
            $enrollment = $leave->enrollment;
            $student = $enrollment->student;
            try {
                $lifecycle->aktifkanDariCuti($student, ['enrollment_id' => $enrollment->id, 'auto' => true]);
            } catch (\InvalidArgumentException $e) {
                $this->warn("  → Lewati {$student->student_code}: {$e->getMessage()}");
                continue;
            }

            AuditLog::create([
                'user_id'      => null,
                'user_name'    => 'System (Scheduler)',
                'action'       => AuditLog::ACTION_LIFECYCLE,
                'entity_type'  => 'Enrollment',
                'entity_id'    => $enrollment->id,
                'entity_label' => $student->full_name . ' — kelas #' . $enrollment->id,
                'old_values'   => ['status' => 'ON_LEAVE'],
                'new_values'   => ['status' => 'ACTIVE', 'auto' => true, 'leave_id' => $leave->id],
                'notes'        => 'Cuti kelas berakhir otomatis (scheduler)',
            ]);

            foreach ($penerima as $user) {
                $user->notify(new MuridKembaliDariCutiNotification($student->fresh(), true, $enrollment->id));
            }

            $diproses++;
            $this->line("  → {$student->full_name}: kelas #{$enrollment->id} kembali aktif");
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
                'notes'        => "Cron students:return-from-leave — {$diproses} kelas dikembalikan ke aktif",
            ]);
        }

        $this->info("Selesai: {$diproses} kelas dikembalikan ke aktif.");

        return self::SUCCESS;
    }
}
