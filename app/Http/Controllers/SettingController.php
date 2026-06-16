<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSchedulerRequest;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\SchedulerPreviewService;
use App\Services\SchedulerSettings;

class SettingController extends Controller
{
    /**
     * Tampilkan form untuk mengedit scheduler.
     */
    public function editScheduler(SchedulerPreviewService $previewService)
    {
        $settings = $this->loadSchedulerSettings();
        $preview = $previewService->build();

        return view('scheduler.edit', compact('settings', 'preview'));
    }

    /**
     * Simpan perubahan scheduler.
     */
    public function updateScheduler(UpdateSchedulerRequest $request)
    {
        $keys = $this->buildSchedulerUpdates($request);

        $oldValues = [];
        $newValues = [];

        foreach ($keys as $key => $newValue) {
            $oldValue = Setting::get($key);
            if ($oldValue !== (string) $newValue) {
                $oldValues[$key] = $oldValue;
                $newValues[$key] = (string) $newValue;
                Setting::set($key, (string) $newValue);
            }
        }

        if (! empty($newValues)) {
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

    /** @return array<string, mixed> */
    private function loadSchedulerSettings(): array
    {
        return [
            'scheduler_sessions_generate_enabled' => SchedulerSettings::sessionsGenerateEnabled(),
            'scheduler_sessions_generate_day'     => SchedulerSettings::sessionsGenerateDay(),
            'scheduler_sessions_generate_time'    => SchedulerSettings::sessionsGenerateTime(),

            'scheduler_invoices_generate_enabled' => SchedulerSettings::invoicesGenerateEnabled(),
            'scheduler_invoices_generate_day'     => SchedulerSettings::invoicesGenerateDay(),
            'scheduler_invoices_generate_time'    => SchedulerSettings::invoicesGenerateTime(),

            'scheduler_honor_calculate_enabled'         => SchedulerSettings::honorCalculateEnabled(),
            'scheduler_honor_calculate_days_before_eom' => SchedulerSettings::honorDaysBeforeEom(),
            'scheduler_honor_calculate_time'            => SchedulerSettings::honorCalculateTime(),

            'scheduler_fines_apply_enabled' => SchedulerSettings::finesApplyEnabled(),
            'scheduler_fines_apply_time'    => SchedulerSettings::finesApplyTime(),

            'scheduler_overdue_check_enabled' => SchedulerSettings::overdueCheckEnabled(),
            'scheduler_overdue_check_day'     => SchedulerSettings::overdueCheckDay(),
            'scheduler_overdue_check_time'    => SchedulerSettings::overdueCheckTime(),

            'schedule_reminder_enabled'       => SchedulerSettings::reminderEnabled(),
            'schedule_reminder_mode'          => SchedulerSettings::reminderMode(),
            'schedule_reminder_send_time'     => SchedulerSettings::reminderSendTime(),
            'schedule_reminder_hours_before'  => SchedulerSettings::reminderHoursBefore(),
        ];
    }

    /** @return array<string, string> */
    private function buildSchedulerUpdates(UpdateSchedulerRequest $request): array
    {
        $map = [
            'scheduler_sessions_generate_enabled' => fn () => $request->boolean('scheduler_sessions_generate_enabled') ? 'true' : 'false',
            'scheduler_sessions_generate_day'     => fn () => $request->input('scheduler_sessions_generate_day'),
            'scheduler_sessions_generate_time'    => fn () => $request->input('scheduler_sessions_generate_time'),

            'scheduler_invoices_generate_enabled' => fn () => $request->boolean('scheduler_invoices_generate_enabled') ? 'true' : 'false',
            'scheduler_invoices_generate_day'     => fn () => $request->input('scheduler_invoices_generate_day'),
            'scheduler_invoices_generate_time'    => fn () => $request->input('scheduler_invoices_generate_time'),

            'scheduler_honor_calculate_enabled'         => fn () => $request->boolean('scheduler_honor_calculate_enabled') ? 'true' : 'false',
            'scheduler_honor_calculate_days_before_eom' => fn () => $request->input('scheduler_honor_calculate_days_before_eom'),
            'scheduler_honor_calculate_time'            => fn () => $request->input('scheduler_honor_calculate_time'),

            'scheduler_fines_apply_enabled' => fn () => $request->boolean('scheduler_fines_apply_enabled') ? 'true' : 'false',
            'scheduler_fines_apply_time'    => fn () => $request->input('scheduler_fines_apply_time'),

            'scheduler_overdue_check_enabled' => fn () => $request->boolean('scheduler_overdue_check_enabled') ? 'true' : 'false',
            'scheduler_overdue_check_day'     => fn () => $request->input('scheduler_overdue_check_day'),
            'scheduler_overdue_check_time'    => fn () => $request->input('scheduler_overdue_check_time'),

            'schedule_reminder_enabled'       => fn () => $request->boolean('schedule_reminder_enabled') ? 'true' : 'false',
            'schedule_reminder_mode'          => fn () => $request->input('schedule_reminder_mode'),
            'schedule_reminder_send_time'     => fn () => $request->input('schedule_reminder_send_time'),
            'schedule_reminder_hours_before'  => fn () => $request->input('schedule_reminder_hours_before'),
        ];

        // Saat toggle mati, jangan overwrite parameter jadwal — pertahankan nilai DB.
        $skipWhenDisabled = [
            'scheduler_sessions_generate_enabled' => ['scheduler_sessions_generate_day', 'scheduler_sessions_generate_time'],
            'scheduler_invoices_generate_enabled' => ['scheduler_invoices_generate_day', 'scheduler_invoices_generate_time'],
            'scheduler_honor_calculate_enabled'   => ['scheduler_honor_calculate_days_before_eom', 'scheduler_honor_calculate_time'],
            'scheduler_fines_apply_enabled'       => ['scheduler_fines_apply_time'],
            'scheduler_overdue_check_enabled'     => ['scheduler_overdue_check_day', 'scheduler_overdue_check_time'],
            'schedule_reminder_enabled'           => ['schedule_reminder_mode', 'schedule_reminder_send_time', 'schedule_reminder_hours_before'],
        ];

        $result = [];

        foreach ($map as $key => $resolver) {
            foreach ($skipWhenDisabled as $toggle => $children) {
                if (in_array($key, $children, true) && ! $request->boolean($toggle)) {
                    continue 2;
                }
            }

            $result[$key] = $resolver();
        }

        return $result;
    }
}
