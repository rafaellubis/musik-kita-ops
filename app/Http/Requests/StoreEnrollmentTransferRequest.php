<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEnrollmentTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Owner', 'Admin'])
            && $this->route('enrollment')->student_id === $this->route('student')->id;
    }

    public function rules(): array
    {
        return [
            'teacher_id' => ['required', 'integer', 'exists:teachers,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'effective_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'reason' => ['required', 'string', 'max:2000'],
            'fingerprint' => [$this->routeIs('students.enrollments.transfers.store') ? 'required' : 'nullable', 'string', 'size:64'],
        ];
    }

    public function messages(): array
    {
        return ['*.required' => ':attribute wajib diisi.', '*.integer' => ':attribute tidak valid.',
            '*.exists' => ':attribute tidak ditemukan.', '*.date_format' => 'Format :attribute tidak valid.',
            'day_of_week.between' => 'Hari tidak valid.', 'effective_date.after_or_equal' => 'Tanggal berlaku tidak boleh lampau.',
            'reason.max' => 'Alasan maksimal 2000 karakter.', 'fingerprint.size' => 'Pratinjau tidak valid. Periksa perubahan kembali.'];
    }

    public function attributes(): array
    {
        return ['teacher_id' => 'Guru tujuan', 'room_id' => 'Ruangan', 'day_of_week' => 'Hari',
            'start_time' => 'Jam mulai', 'effective_date' => 'Tanggal berlaku', 'reason' => 'Alasan', 'fingerprint' => 'Pratinjau'];
    }
}
