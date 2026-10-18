<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReassignEventParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['Owner', 'Admin']);
    }

    public function rules(): array
    {
        return [
            'teacher_id' => ['required', 'integer', Rule::exists('teachers', 'id')->where('is_active', true)],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'teacher_id.required' => 'Guru pendamping wajib dipilih.',
            'teacher_id.exists' => 'Guru yang dipilih tidak aktif.',
            'reason.required' => 'Alasan penggantian guru wajib diisi.',
            'reason.max' => 'Alasan maksimal 1000 karakter.',
        ];
    }
}
