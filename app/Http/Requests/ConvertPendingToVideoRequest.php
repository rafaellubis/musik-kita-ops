<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConvertPendingToVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // middleware role:Owner|Admin sudah di route group
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'notes.string' => 'Catatan harus berupa teks.',
            'notes.max'    => 'Catatan maksimal 500 karakter.',
        ];
    }
}
