<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Owner', 'Admin']) ?? false;
    }

    public function rules(): array
    {
        return [
            'start_time' => ['required', 'date_format:H:i'],
            'end_time'   => ['required', 'date_format:H:i', 'after:start_time'],
            'teacher_id' => ['required', 'exists:teachers,id'],
            'room_id'    => ['nullable', 'exists:rooms,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'start_time.required'    => 'Jam mulai wajib diisi.',
            'start_time.date_format' => 'Format jam mulai tidak valid (HH:MM).',
            'end_time.required'      => 'Jam selesai wajib diisi.',
            'end_time.date_format'   => 'Format jam selesai tidak valid (HH:MM).',
            'end_time.after'         => 'Jam selesai harus setelah jam mulai.',
            'teacher_id.required'    => 'Guru wajib dipilih.',
            'teacher_id.exists'      => 'Guru tidak ditemukan.',
            'room_id.exists'         => 'Ruang tidak ditemukan.',
        ];
    }
}
