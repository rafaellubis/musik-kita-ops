<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VoidInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi role Owner|Admin dijaga middleware route.
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
            'reason.required' => 'Alasan void wajib diisi untuk audit trail.',
            'reason.min'      => 'Alasan void minimal 3 karakter.',
            'reason.max'      => 'Alasan void maksimal 500 karakter.',
        ];
    }
}
