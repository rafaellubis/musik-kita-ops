<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreManualSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Owner', 'Admin']) ?? false;
    }

    public function rules(): array
    {
        return [
            'session_date'       => ['required', 'date_format:Y-m-d', 'after_or_equal:2024-01-01', 'before_or_equal:2030-12-31'],
            'start_time'         => ['required', 'date_format:H:i'],
            'room_id'            => ['nullable', 'integer', 'exists:rooms,id'],
            'attribution_year'   => ['required', 'integer', 'min:2024', 'max:2030'],
            'attribution_month'  => ['required', 'integer', 'min:1', 'max:12'],
            'session_sequence'   => ['nullable', 'integer', 'min:1', 'max:4'],
        ];
    }

    public function messages(): array
    {
        return [
            'session_date.required'      => 'Tanggal sesi wajib diisi.',
            'start_time.required'        => 'Jam mulai wajib diisi.',
            'attribution_year.required'  => 'Tahun atribusi wajib diisi.',
            'attribution_month.required' => 'Bulan atribusi wajib diisi.',
        ];
    }
}
