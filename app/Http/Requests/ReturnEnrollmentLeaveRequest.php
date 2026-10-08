<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReturnEnrollmentLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enrollment_id' => ['required', 'integer', Rule::exists('enrollments', 'id')
                ->where('student_id', $this->route('student'))],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'enrollment_id.required' => 'Pilih kelas yang akan diaktifkan kembali.',
            'enrollment_id.integer' => 'Pilihan kelas tidak valid.',
            'enrollment_id.exists' => 'Kelas harus milik murid ini.',
            'notes.string' => 'Catatan harus berupa teks.',
            'notes.max' => 'Catatan maksimal 500 karakter.',
        ];
    }
}
