<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSchedulerRequest;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\WhatsappMessageTemplate;
use App\Services\IntegrationSettings;
use App\Services\InvoiceService;
use App\Services\SchedulerPreviewService;
use App\Services\SchedulerSettings;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    /**
     * Tampilkan form untuk mengedit scheduler.
     */
    public function editScheduler(SchedulerPreviewService $previewService, InvoiceService $invoiceService)
    {
        $settings = $this->loadSchedulerSettings();
        $preview = $previewService->build();
        $finePerDay = $invoiceService->finePerDay();
        $whatsappTemplates = WhatsappMessageTemplate::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        return view('scheduler.edit', compact('settings', 'preview', 'finePerDay', 'whatsappTemplates'));
    }

    /**
     * Simpan perubahan scheduler.
     */
    public function updateScheduler(UpdateSchedulerRequest $request)
    {
        DB::transaction(function () use ($request) {
            $keys = $this->buildSchedulerUpdates($request);
            $oldValues = [];
            $newValues = [];

            foreach ($keys as $key => $newValue) {
                $oldValue = Setting::get($key);
                if (IntegrationSettings::isEncryptedKey($key)) {
                    if (! IntegrationSettings::secretMatches($oldValue, (string) $newValue)) {
                        $oldValues[$key] = IntegrationSettings::maskValueForAudit($key, $oldValue);
                        $newValues[$key] = IntegrationSettings::maskSecret((string) $newValue);
                        IntegrationSettings::set($key, (string) $newValue);
                    }
                } elseif ($oldValue !== (string) $newValue) {
                    $oldValues[$key] = $oldValue;
                    $newValues[$key] = (string) $newValue;
                    Setting::set($key, (string) $newValue);
                }
            }

            if ($newValues !== []) {
                AuditLog::record(
                    AuditLog::ACTION_UPDATE, null, 'Scheduler Settings',
                    $oldValues, $newValues, 'Memperbarui konfigurasi scheduler sistem'
                );
            }
        });

        return redirect()->route('scheduler.edit')
            ->with('success', 'Konfigurasi scheduler berhasil disimpan.');
    }

    /** @return array<string, mixed> */
    private function loadSchedulerSettings(): array
    {
        $fonnteToken = IntegrationSettings::fonnteToken();
        $wablasToken = IntegrationSettings::wablasToken();
        $wablasSecret = IntegrationSettings::wablasSecretKey();

        return [
            'scheduler_sessions_generate_enabled' => SchedulerSettings::sessionsGenerateEnabled(),
            'scheduler_sessions_generate_day'     => SchedulerSettings::sessionsGenerateDay(),
            'scheduler_sessions_generate_time'    => SchedulerSettings::sessionsGenerateTime(),

            'scheduler_invoices_generate_enabled' => SchedulerSettings::invoicesGenerateEnabled(),
            'scheduler_invoices_generate_day'     => SchedulerSettings::invoicesGenerateDay(),
            'scheduler_invoices_generate_time'    => SchedulerSettings::invoicesGenerateTime(),
            'spp_due_day'                         => SchedulerSettings::sppDueDay(),

            'scheduler_honor_calculate_enabled' => SchedulerSettings::honorCalculateEnabled(),
            'scheduler_honor_calculate_day'   => SchedulerSettings::honorCalculateDay(),
            'scheduler_honor_calculate_time'  => SchedulerSettings::honorCalculateTime(),

            'scheduler_fines_apply_enabled' => SchedulerSettings::finesApplyEnabled(),
            'scheduler_fines_apply_time'    => SchedulerSettings::finesApplyTime(),
            'scheduler_fines_start_day'     => SchedulerSettings::finesStartDay(),

            'scheduler_overdue_check_enabled' => SchedulerSettings::overdueCheckEnabled(),
            'scheduler_overdue_check_day'     => SchedulerSettings::overdueCheckDay(),
            'scheduler_overdue_check_time'    => SchedulerSettings::overdueCheckTime(),

            'scheduler_invoice_reminder_enabled'            => SchedulerSettings::invoiceReminderEnabled(),
            'scheduler_invoice_reminder_days_before_due'    => SchedulerSettings::invoiceReminderDaysBeforeDue(),
            'scheduler_invoice_reminder_time'               => SchedulerSettings::invoiceReminderTime(),
            // ponytail: template_id dihapus — auto-fallback via service

            'scheduler_cuti_return_enabled' => SchedulerSettings::cutiReturnEnabled(),
            'scheduler_cuti_return_time'    => SchedulerSettings::cutiReturnTime(),

            'scheduler_staff_payroll_enabled' => SchedulerSettings::staffPayrollEnabled(),
            'scheduler_staff_payroll_day'     => SchedulerSettings::staffPayrollDay(),
            'scheduler_staff_payroll_time'    => SchedulerSettings::staffPayrollTime(),

            'schedule_reminder_enabled'       => SchedulerSettings::reminderEnabled(),
            'schedule_reminder_mode'          => SchedulerSettings::reminderMode(),
            'schedule_reminder_send_time'     => SchedulerSettings::reminderSendTime(),
            'schedule_reminder_hours_before'  => SchedulerSettings::reminderHoursBefore(),
            'schedule_reminder_poll_minutes'  => SchedulerSettings::reminderPollMinutes(),

            'integration_fonnte_base_url'          => IntegrationSettings::fonnteBaseUrl(),
            'integration_fonnte_token_masked'      => filled($fonnteToken) ? IntegrationSettings::maskSecret($fonnteToken) : '',
            'integration_fonnte_token_configured'  => filled($fonnteToken),
            'integration_fonnte_token_from_env'    => filled($fonnteToken) && ! IntegrationSettings::isFonnteTokenStoredInDb(),
            'integration_whatsapp_provider' => Setting::get('integration_whatsapp_provider', 'fonnte'),
            'integration_gowa_base_url' => Setting::get('integration_gowa_base_url', ''),
            'integration_gowa_username' => Setting::get('integration_gowa_username', ''),
            'integration_gowa_device_id' => Setting::get('integration_gowa_device_id', ''),
            'integration_gowa_password_configured' => filled(Setting::get('integration_gowa_password')),

            'integration_wablas_base_url'            => IntegrationSettings::wablasBaseUrl(),
            'integration_wablas_token_masked'        => filled($wablasToken) ? IntegrationSettings::maskSecret($wablasToken) : '',
            'integration_wablas_token_configured'    => filled($wablasToken),
            'integration_wablas_token_from_env'      => filled($wablasToken) && ! IntegrationSettings::isWablasTokenStoredInDb(),

            'integration_wablas_secret_masked'       => filled($wablasSecret) ? IntegrationSettings::maskSecret($wablasSecret) : '',
            'integration_wablas_secret_configured'   => filled($wablasSecret),
            'integration_wablas_secret_from_env'     => filled($wablasSecret) && ! IntegrationSettings::isWablasSecretStoredInDb(),
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
            'spp_due_day'                         => fn () => $request->input('spp_due_day'),

            'scheduler_honor_calculate_enabled' => fn () => $request->boolean('scheduler_honor_calculate_enabled') ? 'true' : 'false',
            'scheduler_honor_calculate_day'   => fn () => $request->input('scheduler_honor_calculate_day'),
            'scheduler_honor_calculate_time'  => fn () => $request->input('scheduler_honor_calculate_time'),

            'scheduler_fines_apply_enabled' => fn () => $request->boolean('scheduler_fines_apply_enabled') ? 'true' : 'false',
            'scheduler_fines_apply_time'    => fn () => $request->input('scheduler_fines_apply_time'),
            'scheduler_fines_start_day'     => fn () => $request->input('scheduler_fines_start_day'),

            'scheduler_overdue_check_enabled' => fn () => $request->boolean('scheduler_overdue_check_enabled') ? 'true' : 'false',
            'scheduler_overdue_check_day'     => fn () => $request->input('scheduler_overdue_check_day'),
            'scheduler_overdue_check_time'    => fn () => $request->input('scheduler_overdue_check_time'),

            'scheduler_invoice_reminder_enabled'         => fn () => $request->boolean('scheduler_invoice_reminder_enabled') ? 'true' : 'false',
            'scheduler_invoice_reminder_days_before_due' => fn () => $request->input('scheduler_invoice_reminder_days_before_due'),
            'scheduler_invoice_reminder_time'            => fn () => $request->input('scheduler_invoice_reminder_time'),
            // ponytail: template_id tidak disimpan dari form — field dihapus

            'scheduler_cuti_return_enabled' => fn () => $request->boolean('scheduler_cuti_return_enabled') ? 'true' : 'false',
            'scheduler_cuti_return_time'    => fn () => $request->input('scheduler_cuti_return_time'),

            'scheduler_staff_payroll_enabled' => fn () => $request->boolean('scheduler_staff_payroll_enabled') ? 'true' : 'false',
            'scheduler_staff_payroll_day'     => fn () => $request->input('scheduler_staff_payroll_day'),
            'scheduler_staff_payroll_time'    => fn () => $request->input('scheduler_staff_payroll_time'),

            'schedule_reminder_enabled'       => fn () => $request->boolean('schedule_reminder_enabled') ? 'true' : 'false',
            'schedule_reminder_mode'          => fn () => $request->input('schedule_reminder_mode'),
            'schedule_reminder_send_time'     => fn () => $request->input('schedule_reminder_send_time'),
            'schedule_reminder_hours_before'  => fn () => $request->input('schedule_reminder_hours_before'),
            'schedule_reminder_poll_minutes'  => fn () => $request->input('schedule_reminder_poll_minutes'),

            'integration_fonnte_base_url'    => fn () => $request->input('integration_fonnte_base_url'),
            'integration_wablas_base_url'    => fn () => $request->input('integration_wablas_base_url'),
        ];

        foreach ([
            'integration_whatsapp_provider', 'integration_gowa_base_url',
            'integration_gowa_username', 'integration_gowa_device_id',
        ] as $key) {
            if ($request->has($key)) {
                $map[$key] = fn () => $request->input($key);
            }
        }
        if (! $request->has('integration_fonnte_base_url')) {
            unset($map['integration_fonnte_base_url']);
        }
        $gowaPassword = $request->input('integration_gowa_password');
        if (filled($gowaPassword) && ! IntegrationSettings::isMaskedPlaceholder($gowaPassword)) {
            $map['integration_gowa_password'] = fn () => $gowaPassword;
        }

        // Kredensial: kosong atau nilai mask preview = skip update (pertahankan nilai DB)
        $fonnteToken = $request->input('integration_fonnte_token');
        if (filled($fonnteToken) && ! IntegrationSettings::isMaskedPlaceholder($fonnteToken)) {
            $map['integration_fonnte_token'] = fn () => $fonnteToken;
        }
        $wablasToken = $request->input('integration_wablas_token');
        if (filled($wablasToken) && ! IntegrationSettings::isMaskedPlaceholder($wablasToken)) {
            $map['integration_wablas_token'] = fn () => $wablasToken;
        }
        $wablasSecret = $request->input('integration_wablas_secret_key');
        if (filled($wablasSecret) && ! IntegrationSettings::isMaskedPlaceholder($wablasSecret)) {
            $map['integration_wablas_secret_key'] = fn () => $wablasSecret;
        }

        // Saat toggle mati, jangan overwrite parameter jadwal — pertahankan nilai DB.
        $skipWhenDisabled = [
            'scheduler_sessions_generate_enabled' => ['scheduler_sessions_generate_day', 'scheduler_sessions_generate_time'],
            'scheduler_invoices_generate_enabled' => ['scheduler_invoices_generate_day', 'scheduler_invoices_generate_time'],
            'scheduler_honor_calculate_enabled' => ['scheduler_honor_calculate_day', 'scheduler_honor_calculate_time'],
            'scheduler_fines_apply_enabled'       => ['scheduler_fines_apply_time', 'scheduler_fines_start_day'],
            'scheduler_overdue_check_enabled'     => ['scheduler_overdue_check_day', 'scheduler_overdue_check_time'],
            'scheduler_invoice_reminder_enabled'  => ['scheduler_invoice_reminder_days_before_due', 'scheduler_invoice_reminder_time'],
            'scheduler_cuti_return_enabled'     => ['scheduler_cuti_return_time'],
            'scheduler_staff_payroll_enabled'     => ['scheduler_staff_payroll_day', 'scheduler_staff_payroll_time'],
            'schedule_reminder_enabled'           => [
                'schedule_reminder_mode',
                'schedule_reminder_send_time',
                'schedule_reminder_hours_before',
                'schedule_reminder_poll_minutes',
            ],
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
