<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventParticipantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'enrollment_ids' => ['nullable', 'array', 'min:1'],
            'enrollment_ids.*' => ['integer', 'distinct', 'exists:enrollments,id'],
            'reason' => [Rule::requiredIf(fn () => $this->route('event')?->isPublished()), 'nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        // Kalimat disamakan dengan pesan EventParticipantService supaya kondisi yang sama berbunyi sama.
        return [
            'student_id.required' => 'Murid wajib dipilih.',
            'student_id.exists' => 'Murid yang dipilih tidak ditemukan.',
            'enrollment_ids.min' => 'Pilih minimal satu enrollment aktif milik murid.',
            'enrollment_ids.*.exists' => 'Enrollment harus aktif dan dimiliki oleh murid yang dipilih.',
            'enrollment_ids.*.distinct' => 'Enrollment yang dipilih sudah terdaftar pada event ini.',
            'reason.required' => 'Alasan penambahan wajib diisi.',
            'reason.max' => 'Alasan penambahan maksimal 1000 karakter.',
        ];
    }
}
