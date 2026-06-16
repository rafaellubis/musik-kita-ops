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

// ===== Ambil Konfigurasi Terjadwal Dinamis dari Database =====
use Illuminate\Support\Facades\Schema;
use App\Models\Setting;

$sessionsGenerateEnabled = true;
$sessionsGenerateDay = 25;
$sessionsGenerateTime = '06:00';

$invoicesGenerateEnabled = true;
$invoicesGenerateDay = 1;
$invoicesGenerateTime = '06:00';

$honorCalculateEnabled = true;
$honorCalculateTime = '06:00';

$finesApplyEnabled = true;
$finesApplyTime = '06:00';

$overdueCheckEnabled = true;
$overdueCheckTime = '06:05';

try {
    if (Schema::hasTable('settings')) {
        $sessionsGenerateEnabled = filter_var(Setting::get('scheduler_sessions_generate_enabled', 'true'), FILTER_VALIDATE_BOOLEAN);
        $sessionsGenerateDay = (int) Setting::get('scheduler_sessions_generate_day', 25);
        $sessionsGenerateTime = Setting::get('scheduler_sessions_generate_time', '06:00');

        $invoicesGenerateEnabled = filter_var(Setting::get('scheduler_invoices_generate_enabled', 'true'), FILTER_VALIDATE_BOOLEAN);
        $invoicesGenerateDay = (int) Setting::get('scheduler_invoices_generate_day', 1);
        $invoicesGenerateTime = Setting::get('scheduler_invoices_generate_time', '06:00');

        $honorCalculateEnabled = filter_var(Setting::get('scheduler_honor_calculate_enabled', 'true'), FILTER_VALIDATE_BOOLEAN);
        $honorCalculateTime = Setting::get('scheduler_honor_calculate_time', '06:00');

        $finesApplyEnabled = filter_var(Setting::get('scheduler_fines_apply_enabled', 'true'), FILTER_VALIDATE_BOOLEAN);
        $finesApplyTime = Setting::get('scheduler_fines_apply_time', '06:00');

        $overdueCheckEnabled = filter_var(Setting::get('scheduler_overdue_check_enabled', 'true'), FILTER_VALIDATE_BOOLEAN);
        $overdueCheckTime = Setting::get('scheduler_overdue_check_time', '06:05');
    }
} catch (\Throwable $e) {
    // Abaikan jika database belum siap saat memuat artisan
}

// ===== M03: Generator sesi mingguan =====
// Tanggal 25 setiap bulan, jam 06:00 — generate sesi untuk bulan berikutnya.
// Idempotent: aman dijalankan ulang.
Schedule::command('sessions:generate-month')
    ->monthlyOn($sessionsGenerateDay, $sessionsGenerateTime)
    ->when(fn () => $sessionsGenerateEnabled)
    ->name('m03-generate-monthly-sessions')
    ->withoutOverlapping();

// ===== M05: Generator SPP bulanan =====
// Tanggal 1 setiap bulan, jam 06:00 — terbitkan SPP untuk semua murid Aktif.
// Idempotent: invoice yang sudah ada tidak duplikat (BR-5.1).
Schedule::command('invoices:generate-spp')
    ->monthlyOn($invoicesGenerateDay, $invoicesGenerateTime)
    ->when(fn () => $invoicesGenerateEnabled)
    ->name('m05-generate-monthly-spp')
    ->withoutOverlapping();

// ===== M06: Kalkulasi honor guru =====
// H-2 sebelum akhir bulan, jam 06:00 — aggregate honor dari absensi bulan ini.
// Dijalankan harian tapi when() hanya eksekusi di hari yang tepat (H-2).
// Idempotent: slip PAID tidak diubah, slip CALCULATED di-update base_honor-nya.
Schedule::command('honor:calculate')
    ->dailyAt($honorCalculateTime)
    ->when(fn () => $honorCalculateEnabled && now()->day === now()->copy()->endOfMonth()->subDays(2)->day)
    ->name('m06-calculate-teacher-honor')
    ->withoutOverlapping();

// ===== M05: Apply denda harian =====
// Setiap hari jam 06:00 mulai tanggal 11 — hitung & update denda Rp 5.000/hari
// untuk invoice UNPAID/PARTIAL bulan ini (BR-5.3). Idempotent.
Schedule::command('invoices:apply-fines')
    ->dailyAt($finesApplyTime)
    ->when(fn () => $finesApplyEnabled && now()->day >= 11)
    ->name('m05-apply-late-fines')
    ->withoutOverlapping();

// ===== M05: Deteksi murid overdue & notif Admin/Owner =====
// Tgl 1 tiap bulan jam 06:05 — setelah generate-spp (06:00).
// Kirim MuridOverdueNotification ke Admin + Owner untuk murid
// Aktif dengan invoice UNPAID/PARTIAL dari bulan sebelumnya.
// Idempotent: murid yang sudah dinotif bulan ini tidak dinotif ulang.
Schedule::command('students:check-overdue')
    ->monthlyOn(1, $overdueCheckTime)
    ->when(fn () => $overdueCheckEnabled)
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
