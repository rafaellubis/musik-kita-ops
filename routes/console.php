<?php

use App\Services\SchedulerSettings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
| Jadwal otomatis untuk M03/M05/M06. Supaya jadwal ini benar-benar berjalan,
| service `php artisan schedule:run` harus dieksekusi setiap menit oleh
| Task Scheduler Windows. Lihat dokumentasi setup di docs/SCHEDULER.md.
|
| Konfigurasi waktu/tanggal dibaca dari tabel settings (halaman /scheduler).
| Job nonaktif di UI tidak didaftarkan — schedule:list selaras dengan toggle.
|
| Cek jadwal yang aktif:
|   php artisan schedule:list
|
| Jalankan manual untuk testing:
|   php artisan schedule:run         (eksekusi yang due saat ini)
|   php artisan schedule:work        (loop terus seperti di production)
*/

// Batas periode kelas tetap disinkronkan walaupun generator dinonaktifkan.
Schedule::command('enrollments:apply-promotions')->dailyAt('00:00')->withoutOverlapping();
Schedule::command('enrollments:apply-transfers')->dailyAt('00:00')->withoutOverlapping();

// ===== M03: Generator sesi mingguan =====
if (SchedulerSettings::sessionsGenerateEnabled()) {
    Schedule::command('sessions:generate-month')
        ->monthlyOn(SchedulerSettings::sessionsGenerateDay(), SchedulerSettings::sessionsGenerateTime())
        ->name('m03-generate-monthly-sessions')
        ->withoutOverlapping();
}

// ===== M05: Generator SPP bulanan =====
if (SchedulerSettings::invoicesGenerateEnabled()) {
    Schedule::command('invoices:generate-spp')
        ->monthlyOn(SchedulerSettings::invoicesGenerateDay(), SchedulerSettings::invoicesGenerateTime())
        ->name('m05-generate-monthly-spp')
        ->withoutOverlapping();
}

// ===== M06: Kalkulasi honor guru =====
// Tanggal tetap setiap bulan (default tgl 28).
if (SchedulerSettings::honorCalculateEnabled()) {
    Schedule::command('honor:calculate')
        ->monthlyOn(SchedulerSettings::honorCalculateDay(), SchedulerSettings::honorCalculateTime())
        ->name('m06-calculate-teacher-honor')
        ->withoutOverlapping();
}

// ===== M05: Apply denda harian =====
if (SchedulerSettings::finesApplyEnabled()) {
    Schedule::command('invoices:apply-fines')
        ->dailyAt(SchedulerSettings::finesApplyTime())
        ->when(fn () => now()->day >= SchedulerSettings::finesStartDay())
        ->name('m05-apply-late-fines')
        ->withoutOverlapping();
}

// ===== M05: Deteksi murid overdue & notif Admin/Owner =====
if (SchedulerSettings::overdueCheckEnabled()) {
    Schedule::command('students:check-overdue')
        ->monthlyOn(SchedulerSettings::overdueCheckDay(), SchedulerSettings::overdueCheckTime())
        ->name('m05-check-overdue-students')
        ->withoutOverlapping();
}

// ===== M03: Pengingat jadwal kelas via Fonnte =====
if (SchedulerSettings::reminderEnabled()) {
    if (SchedulerSettings::reminderMode() === 'hours_before') {
        $pollMinutes = SchedulerSettings::reminderPollMinutes();
        Schedule::command('schedule-reminders:send')
            ->cron("*/{$pollMinutes} * * * *")
            ->name('m03-schedule-reminders-hours-before')
            ->withoutOverlapping();
    } else {
        Schedule::command('schedule-reminders:send')
            ->dailyAt(SchedulerSettings::reminderSendTime())
            ->name('m03-schedule-reminders-daily')
            ->withoutOverlapping();
    }
}

// ===== M02: Cuti → Aktif otomatis =====
if (SchedulerSettings::cutiReturnEnabled()) {
    // Awal hari: tanggal mulai/akhir cuti berlaku tepat pada hari kalender.
    Schedule::command('students:return-from-leave')
        ->dailyAt('00:00')
        ->name('m02-sync-enrollment-leave')
        ->withoutOverlapping();
    Schedule::command('students:return-from-leave')
        ->dailyAt(SchedulerSettings::cutiReturnTime())
        ->name('m02-return-students-from-leave')
        ->withoutOverlapping();
}

// ===== M05: Reminder tagihan WA terjadwal (H-N sebelum tempo, teks saja) =====
if (SchedulerSettings::invoiceReminderEnabled()) {
    Schedule::command('invoice-reminders:send-scheduled')
        ->dailyAt(SchedulerSettings::invoiceReminderTime())
        ->name('m05-invoice-reminder-scheduled')
        ->withoutOverlapping();
}

// ===== M12: Generate slip gaji staff bulanan =====
if (SchedulerSettings::staffPayrollEnabled()) {
    Schedule::command('staff-payroll:generate-slips')
        ->monthlyOn(SchedulerSettings::staffPayrollDay(), SchedulerSettings::staffPayrollTime())
        ->name('m12-generate-staff-payroll-slips')
        ->withoutOverlapping();
}
