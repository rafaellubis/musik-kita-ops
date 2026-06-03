<?php

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
| Jadwal otomatis untuk M03/M05. Supaya jadwal ini benar-benar berjalan,
| service `php artisan schedule:run` harus dieksekusi setiap menit oleh
| Task Scheduler Windows. Lihat dokumentasi setup di docs/SCHEDULER.md.
|
| Cek jadwal yang aktif:
|   php artisan schedule:list
|
| Jalankan manual untuk testing:
|   php artisan schedule:run         (eksekusi yang due saat ini)
|   php artisan schedule:work        (loop terus seperti di production)
*/

// ===== M03: Generator sesi mingguan =====
// Tanggal 25 setiap bulan, jam 06:00 — generate sesi untuk bulan berikutnya.
// Idempotent: aman dijalankan ulang.
Schedule::command('sessions:generate-month')
    ->monthlyOn(25, '06:00')
    ->name('m03-generate-monthly-sessions')
    ->withoutOverlapping();

// ===== M05: Generator SPP bulanan =====
// Tanggal 1 setiap bulan, jam 06:00 — terbitkan SPP untuk semua murid Aktif.
// Idempotent: invoice yang sudah ada tidak duplikat (BR-5.1).
Schedule::command('invoices:generate-spp')
    ->monthlyOn(1, '06:00')
    ->name('m05-generate-monthly-spp')
    ->withoutOverlapping();

// ===== M06: Kalkulasi honor guru =====
// H-2 sebelum akhir bulan, jam 06:00 — aggregate honor dari absensi bulan ini.
// Dijalankan harian tapi when() hanya eksekusi di hari yang tepat (H-2).
// Idempotent: slip PAID tidak diubah, slip CALCULATED di-update base_honor-nya.
Schedule::command('honor:calculate')
    ->dailyAt('06:00')
    ->when(fn () => now()->day === now()->copy()->endOfMonth()->subDays(2)->day)
    ->name('m06-calculate-teacher-honor')
    ->withoutOverlapping();

// ===== M05: Apply denda harian =====
// Setiap hari jam 06:00 mulai tanggal 11 — hitung & update denda Rp 5.000/hari
// untuk invoice UNPAID/PARTIAL bulan ini (BR-5.3). Idempotent.
Schedule::command('invoices:apply-fines')
    ->dailyAt('06:00')
    ->when(fn () => now()->day >= 11)
    ->name('m05-apply-late-fines')
    ->withoutOverlapping();

// ===== M05: Deteksi murid overdue & notif Admin/Owner =====
// Tgl 1 tiap bulan jam 06:05 — setelah generate-spp (06:00).
// Kirim MuridOverdueNotification ke Admin + Owner untuk murid
// Aktif dengan invoice UNPAID/PARTIAL dari bulan sebelumnya.
// Idempotent: murid yang sudah dinotif bulan ini tidak dinotif ulang.
Schedule::command('students:check-overdue')
    ->monthlyOn(1, '06:05')
    ->name('m05-check-overdue-students')
    ->withoutOverlapping();

// ===== M03: Pengingat jadwal kelas via Fonnte =====
// Mode day_before / same_day: jam dari SCHEDULE_REMINDER_SEND_TIME (default 18:00).
// Mode hours_before: tiap 15 menit, kirim ~N jam sebelum start_time sesi.
Schedule::command('schedule-reminders:send')
    ->dailyAt(config('schedule_reminder.send_time', '18:00'))
    ->when(fn () => config('schedule_reminder.enabled')
        && config('schedule_reminder.mode') !== 'hours_before')
    ->name('m03-schedule-reminders-daily')
    ->withoutOverlapping();

Schedule::command('schedule-reminders:send')
    ->everyFifteenMinutes()
    ->when(fn () => config('schedule_reminder.enabled')
        && config('schedule_reminder.mode') === 'hours_before')
    ->name('m03-schedule-reminders-hours-before')
    ->withoutOverlapping();
