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
        $reminderOn = $this->boolean('schedule_reminder_enabled');
        $reminderMode = $this->input('schedule_reminder_mode');

        return [
            'scheduler_sessions_generate_enabled' => 'sometimes|boolean',
            'scheduler_sessions_generate_day'     => [Rule::requiredIf($sessionsOn), 'integer', 'min:1', 'max:31'],
            'scheduler_sessions_generate_time'    => [Rule::requiredIf($sessionsOn), 'date_format:H:i'],

            'scheduler_invoices_generate_enabled' => 'sometimes|boolean',
            'scheduler_invoices_generate_day'     => [Rule::requiredIf($invoicesOn), 'integer', 'min:1', 'max:31'],
            'scheduler_invoices_generate_time'    => [Rule::requiredIf($invoicesOn), 'date_format:H:i'],

            'scheduler_honor_calculate_enabled' => 'sometimes|boolean',
            'scheduler_honor_calculate_days_before_eom' => [Rule::requiredIf($honorOn), 'integer', 'min:1', 'max:28'],
            'scheduler_honor_calculate_time'    => [Rule::requiredIf($honorOn), 'date_format:H:i'],

            'scheduler_fines_apply_enabled' => 'sometimes|boolean',
            'scheduler_fines_apply_time'    => [Rule::requiredIf($finesOn), 'date_format:H:i'],

            'scheduler_overdue_check_enabled' => 'sometimes|boolean',
            'scheduler_overdue_check_day'     => [Rule::requiredIf($overdueOn), 'integer', 'min:1', 'max:31'],
            'scheduler_overdue_check_time'    => [Rule::requiredIf($overdueOn), 'date_format:H:i'],

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

            'scheduler_honor_calculate_days_before_eom.required' => 'Hari sebelum akhir bulan wajib diisi.',
            'scheduler_honor_calculate_days_before_eom.integer'  => 'Hari sebelum akhir bulan harus berupa angka.',
            'scheduler_honor_calculate_days_before_eom.min'      => 'Hari sebelum akhir bulan minimal 1.',
            'scheduler_honor_calculate_days_before_eom.max'      => 'Hari sebelum akhir bulan maksimal 28.',
            'scheduler_honor_calculate_time.required'    => 'Jam pelaksanaan kalkulasi honor wajib diisi.',
            'scheduler_honor_calculate_time.date_format' => 'Format jam kalkulasi honor harus HH:MM.',

            'scheduler_fines_apply_time.required'        => 'Jam pelaksanaan denda wajib diisi.',
            'scheduler_fines_apply_time.date_format'     => 'Format jam denda harus HH:MM.',

            'scheduler_overdue_check_day.required'      => 'Tanggal cek murid overdue wajib diisi.',
            'scheduler_overdue_check_day.integer'       => 'Tanggal cek murid overdue harus berupa angka.',
            'scheduler_overdue_check_day.min'           => 'Tanggal cek murid overdue minimal tanggal 1.',
            'scheduler_overdue_check_day.max'           => 'Tanggal cek murid overdue maksimal tanggal 31.',
            'scheduler_overdue_check_time.required'      => 'Jam pelaksanaan overdue check wajib diisi.',
            'scheduler_overdue_check_time.date_format'   => 'Format jam overdue check harus HH:MM.',

            'schedule_reminder_mode.required'            => 'Mode WhatsApp reminder wajib dipilih.',
            'schedule_reminder_mode.in'                  => 'Mode WhatsApp reminder tidak valid.',
            'schedule_reminder_send_time.required'       => 'Jam kirim WhatsApp reminder wajib diisi.',
            'schedule_reminder_send_time.date_format'     => 'Format jam kirim WhatsApp reminder harus HH:MM.',
            'schedule_reminder_hours_before.required'    => 'Selisih jam WhatsApp reminder wajib diisi.',
            'schedule_reminder_hours_before.integer'     => 'Selisih jam WhatsApp reminder harus berupa angka.',
            'schedule_reminder_hours_before.min'         => 'Selisih jam minimal 1 jam.',
            'schedule_reminder_hours_before.max'         => 'Selisih jam maksimal 24 jam.',
        ];
    }
}
