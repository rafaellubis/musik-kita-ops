<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RemoveFineRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi role sudah dijaga oleh middleware route (role:Owner|Admin)
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan hapus denda wajib diisi.',
            'reason.min'      => 'Alasan hapus denda minimal 3 karakter.',
            'reason.max'      => 'Alasan hapus denda maksimal 500 karakter.',
        ];
    }
}
