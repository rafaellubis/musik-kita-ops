<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEnrollmentPromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Owner', 'Admin'])
            && $this->route('enrollment')->student_id === $this->route('student')->id;
    }

    public function rules(): array
    {
        return [
            'package_id' => ['required', 'integer', 'exists:packages,id'],
            'teacher_id' => ['required', 'integer', 'exists:teachers,id'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'effective_date' => ['required', 'date_format:Y-m-d', Rule::in([today()->startOfMonth()->addMonth()->toDateString()])],
            'evaluation_note' => ['required', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            '*.required' => ':attribute wajib diisi.', '*.integer' => ':attribute tidak valid.',
            '*.exists' => ':attribute tidak ditemukan.', '*.date_format' => 'Format :attribute tidak valid.',
            'day_of_week.between' => 'Hari tidak valid.',
            'effective_date.in' => 'Tanggal berlaku sudah berubah. Buka ulang pratinjau kenaikan.',
            'evaluation_note.max' => 'Catatan evaluasi maksimal 2000 karakter.',
        ];
    }

    public function attributes(): array
    {
        return ['package_id' => 'Paket tujuan', 'teacher_id' => 'Guru', 'room_id' => 'Ruangan',
            'day_of_week' => 'Hari', 'start_time' => 'Jam mulai', 'effective_date' => 'Tanggal berlaku', 'evaluation_note' => 'Evaluasi guru'];
    }
}
