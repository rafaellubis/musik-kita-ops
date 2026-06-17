<?php

namespace App\Notifications;

use App\Models\Student;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi murid kembali Aktif setelah periode cuti berakhir.
 * Dikirim ke Admin + Owner via cron students:return-from-leave.
 */
class MuridKembaliDariCutiNotification extends Notification
{
    public function __construct(
        private Student $student,
        private bool    $auto = true,
    ) {}

    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(mixed $notifiable): array
    {
        return [
            'student_id'   => $this->student->id,
            'student_name' => $this->student->full_name,
            'student_code' => $this->student->student_code,
            'auto'         => $this->auto,
            'student_url'  => route('students.show', $this->student),
        ];
    }
}
