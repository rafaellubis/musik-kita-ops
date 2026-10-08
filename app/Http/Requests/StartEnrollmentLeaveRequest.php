<?php

namespace App\Http\Requests;

use App\Models\Enrollment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartEnrollmentLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $enrollment = is_scalar($this->input('enrollment_id'))
            ? Enrollment::where('student_id', $this->route('student'))
                ->whereKey($this->input('enrollment_id'))->first()
            : null;
        $leave = $enrollment?->currentLeave;

        return [
            'enrollment_id' => ['required', 'integer', Rule::exists('enrollments', 'id')
                ->where('student_id', $this->route('student'))
                ->whereIn('status', [Enrollment::STATUS_ACTIVE, Enrollment::STATUS_ON_LEAVE])],
            'cuti_from' => $leave ? ['exclude'] : ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'cuti_until' => ['required', 'date_format:Y-m-d', $leave
                ? 'after:'.$leave->cuti_until->format('Y-m-d')
                : 'after_or_equal:cuti_from'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'enrollment_id.required' => 'Pilih kelas yang akan cuti.',
            'enrollment_id.integer' => 'Pilihan kelas tidak valid.',
            'enrollment_id.exists' => 'Kelas harus milik murid ini dan berstatus aktif atau cuti.',
            'cuti_from.required' => 'Tanggal mulai cuti wajib diisi.',
            'cuti_from.date_format' => 'Tanggal mulai cuti harus berformat YYYY-MM-DD.',
            'cuti_from.after_or_equal' => 'Tanggal mulai cuti tidak boleh sebelum hari ini.',
            'cuti_until.required' => 'Tanggal akhir cuti wajib diisi.',
            'cuti_until.date_format' => 'Tanggal akhir cuti harus berformat YYYY-MM-DD.',
            'cuti_until.after' => 'Tanggal akhir perpanjangan harus setelah tanggal akhir cuti sebelumnya.',
            'cuti_until.after_or_equal' => 'Tanggal akhir cuti tidak boleh sebelum tanggal mulai.',
            'reason.required' => 'Alasan cuti wajib diisi.',
            'reason.string' => 'Alasan cuti harus berupa teks.',
            'reason.max' => 'Alasan cuti maksimal 500 karakter.',
        ];
    }
}
