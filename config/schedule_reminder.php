<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pengingat Jadwal WhatsApp (Fonnte)
    |--------------------------------------------------------------------------
    |
    | mode:
    |   day_before  — kirim di send_time untuk sesi besok
    |   same_day    — kirim di send_time untuk sesi hari ini
    |   hours_before — kirim ~hours_before jam sebelum start_time (cron tiap 15 menit)
    |
    */

    'enabled' => env('SCHEDULE_REMINDER_ENABLED', false),

    'mode' => env('SCHEDULE_REMINDER_MODE', 'day_before'),

    'send_time' => env('SCHEDULE_REMINDER_SEND_TIME', '18:00'),

    'hours_before' => (int) env('SCHEDULE_REMINDER_HOURS_BEFORE', 2),

    /** Toleransi window (menit) untuk mode hours_before saat cron tiap 15 menit. */
    'hours_before_window_minutes' => (int) env('SCHEDULE_REMINDER_WINDOW_MINUTES', 7),

];
