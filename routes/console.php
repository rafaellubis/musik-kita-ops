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
// Offset H-N sebelum akhir bulan (default N=2 = H-2). when() untuk tanggal H-N saja.
if (SchedulerSettings::honorCalculateEnabled()) {
    Schedule::command('honor:calculate')
        ->dailyAt(SchedulerSettings::honorCalculateTime())
        ->when(fn () => SchedulerSettings::isHonorRunDay(now()))
        ->name('m06-calculate-teacher-honor')
        ->withoutOverlapping();
}

// ===== M05: Apply denda harian =====
if (SchedulerSettings::finesApplyEnabled()) {
    Schedule::command('invoices:apply-fines')
        ->dailyAt(SchedulerSettings::finesApplyTime())
        ->when(fn () => now()->day >= 11)
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
        Schedule::command('schedule-reminders:send')
            ->everyFifteenMinutes()
            ->name('m03-schedule-reminders-hours-before')
            ->withoutOverlapping();
    } else {
        Schedule::command('schedule-reminders:send')
            ->dailyAt(SchedulerSettings::reminderSendTime())
            ->name('m03-schedule-reminders-daily')
            ->withoutOverlapping();
    }
}
