<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSchedulerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('Owner');
    }

    public function rules(): array
    {
        $sessionsOn = $this->boolean('scheduler_sessions_generate_enabled');
        $invoicesOn = $this->boolean('scheduler_invoices_generate_enabled');
        $honorOn    = $this->boolean('scheduler_honor_calculate_enabled');
        $finesOn    = $this->boolean('scheduler_fines_apply_enabled');
        $overdueOn  = $this->boolean('scheduler_overdue_check_enabled');
        $invoiceReminderOn = $this->boolean('scheduler_invoice_reminder_enabled');
        $cutiReturnOn = $this->boolean('scheduler_cuti_return_enabled');
        $staffPayrollOn = $this->boolean('scheduler_staff_payroll_enabled');
        $reminderOn = $this->boolean('schedule_reminder_enabled');
        $reminderMode = $this->input('schedule_reminder_mode');
        $sppDueDay = max(2, (int) $this->input('spp_due_day', 10));

        return [
            'scheduler_sessions_generate_enabled' => 'sometimes|boolean',
            'scheduler_sessions_generate_day'     => [Rule::requiredIf($sessionsOn), 'integer', 'min:1', 'max:31'],
            'scheduler_sessions_generate_time'    => [Rule::requiredIf($sessionsOn), 'date_format:H:i'],

            'scheduler_invoices_generate_enabled' => 'sometimes|boolean',
            'scheduler_invoices_generate_day'     => [Rule::requiredIf($invoicesOn), 'integer', 'min:1', 'max:31'],
            'scheduler_invoices_generate_time'    => [Rule::requiredIf($invoicesOn), 'date_format:H:i'],
            'spp_due_day'                         => ['required', 'integer', 'min:1', 'max:31'],

            'scheduler_honor_calculate_enabled' => 'sometimes|boolean',
            'scheduler_honor_calculate_day'     => [Rule::requiredIf($honorOn), 'integer', 'min:1', 'max:31'],
            'scheduler_honor_calculate_time'    => [Rule::requiredIf($honorOn), 'date_format:H:i'],

            'scheduler_fines_apply_enabled' => 'sometimes|boolean',
            'scheduler_fines_apply_time'    => [Rule::requiredIf($finesOn), 'date_format:H:i'],
            'scheduler_fines_start_day'     => [Rule::requiredIf($finesOn), 'integer', 'min:1', 'max:31'],

            'scheduler_overdue_check_enabled' => 'sometimes|boolean',
            'scheduler_overdue_check_day'     => [Rule::requiredIf($overdueOn), 'integer', 'min:1', 'max:31'],
            'scheduler_overdue_check_time'    => [Rule::requiredIf($overdueOn), 'date_format:H:i'],

            'scheduler_invoice_reminder_enabled'         => 'sometimes|boolean',
            'scheduler_invoice_reminder_days_before_due' => [
                Rule::requiredIf($invoiceReminderOn),
                'integer',
                'min:1',
                'max:' . ($sppDueDay - 1),
            ],
            'scheduler_invoice_reminder_time' => [Rule::requiredIf($invoiceReminderOn), 'date_format:H:i'],

            'scheduler_cuti_return_enabled' => 'sometimes|boolean',
            'scheduler_cuti_return_time'    => [Rule::requiredIf($cutiReturnOn), 'date_format:H:i'],

            'scheduler_staff_payroll_enabled' => 'sometimes|boolean',
            'scheduler_staff_payroll_day'     => [Rule::requiredIf($staffPayrollOn), 'integer', 'min:1', 'max:31'],
            'scheduler_staff_payroll_time'    => [Rule::requiredIf($staffPayrollOn), 'date_format:H:i'],

            'schedule_reminder_enabled'      => 'sometimes|boolean',
            'schedule_reminder_mode'         => [Rule::requiredIf($reminderOn), 'in:day_before,same_day,hours_before'],
            'schedule_reminder_send_time'    => [
                Rule::requiredIf($reminderOn && $reminderMode !== 'hours_before'),
                'nullable',
                'date_format:H:i',
            ],
            'schedule_reminder_hours_before' => [
                Rule::requiredIf($reminderOn && $reminderMode === 'hours_before'),
                'nullable',
                'integer',
                'min:1',
                'max:24',
            ],
            'schedule_reminder_poll_minutes' => [
                Rule::requiredIf($reminderOn && $reminderMode === 'hours_before'),
                'nullable',
                'integer',
                'min:5',
                'max:60',
            ],

            'integration_fonnte_token'       => ['nullable', 'string', 'max:500'],
            'integration_fonnte_base_url'    => ['nullable', 'url', 'max:255'],
            'integration_wablas_token'       => ['nullable', 'string', 'max:500'],
            'integration_wablas_secret_key'  => ['nullable', 'string', 'max:500'],
            'integration_wablas_base_url'    => ['nullable', 'url', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'scheduler_sessions_generate_day.required'  => 'Tanggal generator sesi mingguan wajib diisi.',
            'scheduler_sessions_generate_day.integer'   => 'Tanggal generator sesi mingguan harus berupa angka.',
            'scheduler_sessions_generate_day.min'       => 'Tanggal generator sesi mingguan minimal tanggal 1.',
            'scheduler_sessions_generate_day.max'       => 'Tanggal generator sesi mingguan maksimal tanggal 31.',
            'scheduler_sessions_generate_time.required' => 'Jam pelaksanaan generator sesi mingguan wajib diisi.',
            'scheduler_sessions_generate_time.date_format' => 'Format jam generator sesi mingguan harus HH:MM (contoh 06:00).',

            'scheduler_invoices_generate_day.required'  => 'Tanggal generator SPP bulanan wajib diisi.',
            'scheduler_invoices_generate_day.integer'   => 'Tanggal generator SPP bulanan harus berupa angka.',
            'scheduler_invoices_generate_day.min'       => 'Tanggal generator SPP bulanan minimal tanggal 1.',
            'scheduler_invoices_generate_day.max'       => 'Tanggal generator SPP bulanan maksimal tanggal 31.',
            'scheduler_invoices_generate_time.required' => 'Jam pelaksanaan generator SPP bulanan wajib diisi.',
            'scheduler_invoices_generate_time.date_format' => 'Format jam generator SPP bulanan harus HH:MM (contoh 06:00).',

            'spp_due_day.required' => 'Tempo akhir bayar SPP wajib diisi.',
            'spp_due_day.integer'  => 'Tempo akhir bayar SPP harus berupa angka.',
            'spp_due_day.min'      => 'Tempo akhir bayar SPP minimal tanggal 1.',
            'spp_due_day.max'      => 'Tempo akhir bayar SPP maksimal tanggal 31.',

            'scheduler_honor_calculate_day.required' => 'Tanggal kalkulasi honor wajib diisi.',
            'scheduler_honor_calculate_day.integer'  => 'Tanggal kalkulasi honor harus berupa angka.',
            'scheduler_honor_calculate_day.min'      => 'Tanggal kalkulasi honor minimal 1.',
            'scheduler_honor_calculate_day.max'      => 'Tanggal kalkulasi honor maksimal 31.',
            'scheduler_honor_calculate_time.required'    => 'Jam pelaksanaan kalkulasi honor wajib diisi.',
            'scheduler_honor_calculate_time.date_format' => 'Format jam kalkulasi honor harus HH:MM.',

            'scheduler_fines_apply_time.required'        => 'Jam pelaksanaan denda wajib diisi.',
            'scheduler_fines_apply_time.date_format'     => 'Format jam denda harus HH:MM.',
            'scheduler_fines_start_day.required'         => 'Tanggal mulai denda wajib diisi.',
            'scheduler_fines_start_day.integer'          => 'Tanggal mulai denda harus berupa angka.',
            'scheduler_fines_start_day.min'              => 'Tanggal mulai denda minimal tanggal 1.',
            'scheduler_fines_start_day.max'              => 'Tanggal mulai denda maksimal tanggal 31.',

            'scheduler_overdue_check_day.required'      => 'Tanggal cek murid overdue wajib diisi.',
            'scheduler_overdue_check_day.integer'       => 'Tanggal cek murid overdue harus berupa angka.',
            'scheduler_overdue_check_day.min'           => 'Tanggal cek murid overdue minimal tanggal 1.',
            'scheduler_overdue_check_day.max'           => 'Tanggal cek murid overdue maksimal tanggal 31.',
            'scheduler_overdue_check_time.required'      => 'Jam pelaksanaan overdue check wajib diisi.',
            'scheduler_overdue_check_time.date_format'   => 'Format jam overdue check harus HH:MM.',

            'scheduler_invoice_reminder_days_before_due.required' => 'Hari sebelum tempo wajib diisi.',
            'scheduler_invoice_reminder_days_before_due.integer'  => 'Hari sebelum tempo harus berupa angka.',
            'scheduler_invoice_reminder_days_before_due.min'      => 'Minimal 1 hari sebelum tempo.',
            'scheduler_invoice_reminder_days_before_due.max'      => 'Reminder harus sebelum tanggal tempo (kurang dari tempo SPP).',
            'scheduler_invoice_reminder_time.required'            => 'Jam reminder tagihan wajib diisi.',
            'scheduler_invoice_reminder_time.date_format'         => 'Format jam reminder tagihan harus HH:MM.',


            'scheduler_cuti_return_time.required'        => 'Jam cuti→aktif wajib diisi.',
            'scheduler_cuti_return_time.date_format'     => 'Format jam cuti→aktif harus HH:MM.',

            'scheduler_staff_payroll_day.required'      => 'Tanggal generate slip gaji staff wajib diisi.',
            'scheduler_staff_payroll_day.integer'       => 'Tanggal generate slip gaji staff harus berupa angka.',
            'scheduler_staff_payroll_day.min'           => 'Tanggal generate slip gaji staff minimal tanggal 1.',
            'scheduler_staff_payroll_day.max'           => 'Tanggal generate slip gaji staff maksimal tanggal 31.',
            'scheduler_staff_payroll_time.required'      => 'Jam generate slip gaji staff wajib diisi.',
            'scheduler_staff_payroll_time.date_format'   => 'Format jam generate slip gaji staff harus HH:MM.',

            'schedule_reminder_mode.required'            => 'Mode WhatsApp reminder wajib dipilih.',
            'schedule_reminder_mode.in'                  => 'Mode WhatsApp reminder tidak valid.',
            'schedule_reminder_send_time.required'       => 'Jam kirim WhatsApp reminder wajib diisi.',
            'schedule_reminder_send_time.date_format'     => 'Format jam kirim WhatsApp reminder harus HH:MM.',
            'schedule_reminder_hours_before.required'    => 'Selisih jam WhatsApp reminder wajib diisi.',
            'schedule_reminder_hours_before.integer'     => 'Selisih jam WhatsApp reminder harus berupa angka.',
            'schedule_reminder_hours_before.min'         => 'Selisih jam minimal 1 jam.',
            'schedule_reminder_hours_before.max'         => 'Selisih jam maksimal 24 jam.',
            'schedule_reminder_poll_minutes.required'    => 'Interval poll reminder wajib diisi.',
            'schedule_reminder_poll_minutes.integer'     => 'Interval poll harus berupa angka.',
            'schedule_reminder_poll_minutes.min'         => 'Interval poll minimal 5 menit.',
            'schedule_reminder_poll_minutes.max'         => 'Interval poll maksimal 60 menit.',

            'integration_fonnte_base_url.url'   => 'Base URL Fonnte harus berupa URL valid.',
            'integration_wablas_base_url.url'   => 'Base URL Wablas harus berupa URL valid.',
        ];
    }
}
