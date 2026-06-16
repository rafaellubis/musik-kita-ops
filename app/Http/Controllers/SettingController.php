<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSchedulerRequest;
use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Tampilkan form untuk mengedit scheduler.
     */
    public function editScheduler()
    {
        $settings = [
            'scheduler_sessions_generate_enabled' => Setting::get('scheduler_sessions_generate_enabled', 'true') === 'true',
            'scheduler_sessions_generate_day'     => (int) Setting::get('scheduler_sessions_generate_day', 25),
            'scheduler_sessions_generate_time'    => Setting::get('scheduler_sessions_generate_time', '06:00'),

            'scheduler_invoices_generate_enabled' => Setting::get('scheduler_invoices_generate_enabled', 'true') === 'true',
            'scheduler_invoices_generate_day'     => (int) Setting::get('scheduler_invoices_generate_day', 1),
            'scheduler_invoices_generate_time'    => Setting::get('scheduler_invoices_generate_time', '06:00'),

            'scheduler_honor_calculate_enabled' => Setting::get('scheduler_honor_calculate_enabled', 'true') === 'true',
            'scheduler_honor_calculate_time'    => Setting::get('scheduler_honor_calculate_time', '06:00'),

            'scheduler_fines_apply_enabled' => Setting::get('scheduler_fines_apply_enabled', 'true') === 'true',
            'scheduler_fines_apply_time'    => Setting::get('scheduler_fines_apply_time', '06:00'),

            'scheduler_overdue_check_enabled' => Setting::get('scheduler_overdue_check_enabled', 'true') === 'true',
            'scheduler_overdue_check_time'    => Setting::get('scheduler_overdue_check_time', '06:05'),

            'schedule_reminder_enabled'       => Setting::get('schedule_reminder_enabled', 'false') === 'true',
            'schedule_reminder_mode'          => Setting::get('schedule_reminder_mode', 'day_before'),
            'schedule_reminder_send_time'     => Setting::get('schedule_reminder_send_time', '18:00'),
            'schedule_reminder_hours_before'  => (int) Setting::get('schedule_reminder_hours_before', 2),
        ];

        return view('scheduler.edit', compact('settings'));
    }

    /**
     * Simpan perubahan scheduler.
     */
    public function updateScheduler(UpdateSchedulerRequest $request)
    {
        $keys = [
            'scheduler_sessions_generate_enabled' => $request->boolean('scheduler_sessions_generate_enabled') ? 'true' : 'false',
            'scheduler_sessions_generate_day'     => $request->input('scheduler_sessions_generate_day'),
            'scheduler_sessions_generate_time'    => $request->input('scheduler_sessions_generate_time'),

            'scheduler_invoices_generate_enabled' => $request->boolean('scheduler_invoices_generate_enabled') ? 'true' : 'false',
            'scheduler_invoices_generate_day'     => $request->input('scheduler_invoices_generate_day'),
            'scheduler_invoices_generate_time'    => $request->input('scheduler_invoices_generate_time'),

            'scheduler_honor_calculate_enabled' => $request->boolean('scheduler_honor_calculate_enabled') ? 'true' : 'false',
            'scheduler_honor_calculate_time'    => $request->input('scheduler_honor_calculate_time'),

            'scheduler_fines_apply_enabled' => $request->boolean('scheduler_fines_apply_enabled') ? 'true' : 'false',
            'scheduler_fines_apply_time'    => $request->input('scheduler_fines_apply_time'),

            'scheduler_overdue_check_enabled' => $request->boolean('scheduler_overdue_check_enabled') ? 'true' : 'false',
            'scheduler_overdue_check_time'    => $request->input('scheduler_overdue_check_time'),

            'schedule_reminder_enabled'       => $request->boolean('schedule_reminder_enabled') ? 'true' : 'false',
            'schedule_reminder_mode'          => $request->input('schedule_reminder_mode'),
            'schedule_reminder_send_time'     => $request->input('schedule_reminder_send_time'),
            'schedule_reminder_hours_before'  => $request->input('schedule_reminder_hours_before'),
        ];

        $oldValues = [];
        $newValues = [];

        foreach ($keys as $key => $newValue) {
            $oldValue = Setting::get($key);
            if ($oldValue !== $newValue) {
                $oldValues[$key] = $oldValue;
                $newValues[$key] = $newValue;
                Setting::set($key, $newValue);
            }
        }

        // Catat ke log audit jika ada perubahan
        if (!empty($newValues)) {
            AuditLog::record(
                AuditLog::ACTION_UPDATE,
                null,
                'Scheduler Settings',
                $oldValues,
                $newValues,
                'Memperbarui konfigurasi scheduler sistem'
            );
        }

        return redirect()->route('scheduler.edit')
            ->with('success', 'Konfigurasi scheduler berhasil disimpan.');
    }
}
