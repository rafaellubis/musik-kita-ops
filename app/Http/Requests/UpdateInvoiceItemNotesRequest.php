<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceItemNotesRequest extends FormRequest
{
    protected $errorBag = 'itemNote';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['present', 'nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'notes.present' => 'Isian catatan harus dikirim, boleh dikosongkan.',
            'notes.string' => 'Catatan harus berupa teks.',
            'notes.max' => 'Catatan maksimal 500 karakter.',
        ];
    }
}
