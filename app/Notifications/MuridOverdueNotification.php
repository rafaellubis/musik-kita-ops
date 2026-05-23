<?php

namespace App\Notifications;

use App\Models\Student;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi murid Aktif dengan tunggakan >1 bulan.
 * Dikirim ke Admin + Owner setiap tgl 1 via cron students:check-overdue.
 */
class MuridOverdueNotification extends Notification
{
    public function __construct(
        private Student $student,
        private int     $totalOverdue,
        private string  $invoiceMonth,
    ) {}

    /**
     * Kirim hanya via database (tidak perlu email/push di Fase 1).
     */
    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    /**
     * Payload yang disimpan di kolom data (JSON) tabel notifications.
     */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'student_id'    => $this->student->id,
            'student_name'  => $this->student->full_name,
            'student_code'  => $this->student->student_code,
            'total_overdue' => $this->totalOverdue,
            'invoice_month' => $this->invoiceMonth,
            'student_url'   => route('students.show', $this->student),
        ];
    }
}
